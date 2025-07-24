<?php
/**
 * Classe para gerenciar mesas
 */

if (!defined('ABSPATH')) {
    exit;
}

class Zuzunely_Tables {
    
    // Construtor
    public function __construct() {
        // Adicionar ajax handlers para mesas
        add_action('wp_ajax_zuzunely_save_table', array($this, 'ajax_save_table'));
        add_action('wp_ajax_zuzunely_delete_table', array($this, 'ajax_delete_table'));
        add_action('wp_ajax_zuzunely_bulk_delete_tables', array($this, 'ajax_bulk_delete_tables'));
    }
    
    // Página de administração para mesas
    public static function admin_page() {
        // Verificar se há ação específica (adicionar/editar)
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        switch ($action) {
            case 'add':
                self::render_add_edit_form();
                break;
                
            case 'edit':
                self::render_add_edit_form($id);
                break;
                
            default:
                self::render_list_table();
                break;
        }
    }
    
    // Processar exclusão em massa
    private static function process_bulk_action() {
        // Verificar segurança
        if (!isset($_REQUEST['_wpnonce']) || !wp_verify_nonce($_REQUEST['_wpnonce'], 'bulk-tables')) {
            wp_die('Ação não autorizada');
        }
        
        if (isset($_REQUEST['table_id']) && is_array($_REQUEST['table_id'])) {
            $table_ids = array_map('intval', $_REQUEST['table_id']);
            
            if (!empty($table_ids)) {
                $db = new Zuzunely_Tables_DB();
                $count = 0;
                
                foreach ($table_ids as $table_id) {
                    if ($db->delete_table($table_id)) {
                        $count++;
                    }
                }
                
                // Adicionar mensagem de sucesso
                if ($count > 0) {
                    add_action('admin_notices', function() use ($count) {
                        echo '<div class="notice notice-success is-dismissible">';
                        echo '<p>' . sprintf(_n('%d mesa excluída com sucesso.', '%d mesas excluídas com sucesso.', $count, 'zuzunely-restaurant'), $count) . '</p>';
                        echo '</div>';
                    });
                }
            }
        }
    }
    
    // Renderizar lista de mesas
    private static function render_list_table() {
        // Incluir WP_List_Table se não estiver disponível
        if (!class_exists('WP_List_Table')) {
            require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
        }
        
        // Incluir classe da tabela de listagem
        require_once ZUZUNELY_PLUGIN_DIR . 'class-zuzunely-tables-list-table.php';
        
        // Processar ações em massa
        $table = new Zuzunely_Tables_List_Table();
        
        // Verifica se uma ação em massa foi solicitada e processa
        $doaction = $table->current_action();
        if ($doaction && 'delete' === $doaction) {
            self::process_bulk_action();
        }
        
        // Filtros
        $saloon_filter = isset($_GET['saloon']) ? intval($_GET['saloon']) : 0;
        
        // Criar instância da tabela
        $tables_db = new Zuzunely_Tables_DB();
        $table->prepare_items($saloon_filter);
        
        // Obter salões para o filtro
        $saloons_db = new Zuzunely_saloons_DB();
        $saloons = $saloons_db->get_saloons(array('include_inactive' => false));
        
        // Total de mesas
        $count = $tables_db->count_tables(array('include_inactive' => true));
        
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php echo esc_html__('Mesas', 'zuzunely-restaurant'); ?></h1>
            <a href="<?php echo admin_url('admin.php?page=zuzunely-tables&action=add'); ?>" class="page-title-action"><?php echo esc_html__('Adicionar Nova', 'zuzunely-restaurant'); ?></a>
            <a href="<?php echo admin_url('admin.php?page=zuzunely-tables&refresh=' . time()); ?>" class="page-title-action"><?php echo esc_html__('Atualizar Lista', 'zuzunely-restaurant'); ?></a>
            
            <?php if ($count > 0) : ?>
                <div class="notice notice-info is-dismissible">
                    <p><?php echo sprintf(esc_html__('Há %d mesa(s) cadastrada(s).', 'zuzunely-restaurant'), $count); ?></p>
                </div>
            <?php endif; ?>
            
            <div class="tablenav top">
                <div class="alignleft actions">
                    <form method="get">
                        <input type="hidden" name="page" value="zuzunely-tables">
                        <select name="saloon">
                            <option value="0"><?php echo esc_html__('Todos os salões', 'zuzunely-restaurant'); ?></option>
                            <?php foreach ($saloons as $saloon) : ?>
                                <option value="<?php echo $saloon['id']; ?>" <?php selected($saloon_filter, $saloon['id']); ?>>
                                    <?php echo esc_html($saloon['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="submit" class="button" value="<?php echo esc_attr__('Filtrar', 'zuzunely-restaurant'); ?>">
                    </form>
                </div>
                <br class="clear">
            </div>
            
            <form method="post">
                <?php
                // Adicionar os nonces e outras ações necessárias para o bulk action
                $table->prepare_items();
                $table->display();
                ?>
            </form>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Exclusão de mesa
            $(document).on('click', '.zuzunely-delete-table', function(e) {
                e.preventDefault();
                
                if (!confirm(zuzunely_l10n.confirm_delete)) {
                    return;
                }
                
                var $this = $(this);
                var table_id = $this.data('id');
                var nonce = $this.data('nonce');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'zuzunely_delete_table',
                        table_id: table_id,
                        nonce: nonce
                    },
                    beforeSend: function() {
                        $this.text(zuzunely_l10n.deleting);
                    },
                    success: function(response) {
                        if (response.success) {
                            $this.closest('tr').fadeOut(400, function() {
                                $(this).remove();
                            });
                        } else {
                            alert(response.data);
                            $this.text(zuzunely_l10n.delete);
                        }
                    },
                    error: function() {
                        alert(zuzunely_l10n.error_deleting);
                        $this.text(zuzunely_l10n.delete);
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    // Renderizar formulário de adição/edição
    private static function render_add_edit_form($id = 0) {
        // Se temos ID, vamos buscar a mesa
        $table = array(
            'name' => '',
            'description' => '',
            'saloon_id' => 0,
            'capacity' => 4,
            'is_active' => 1
        );
        
        $title = __('Adicionar Nova Mesa', 'zuzunely-restaurant');
        
        if ($id > 0) {
            $db = new Zuzunely_Tables_DB();
            $loaded_table = $db->get_table($id);
            
            if ($loaded_table) {
                $table = $loaded_table;
                $title = __('Editar Mesa', 'zuzunely-restaurant');
            }
        }
        
        // Obter salões para o dropdown
        $saloons_db = new Zuzunely_saloons_DB();
        $saloons = $saloons_db->get_saloons(array('include_inactive' => false));
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html($title); ?></h1>
            
            <form id="zuzunely-table-form" method="post">
                <input type="hidden" name="table_id" value="<?php echo $id; ?>">
                <?php wp_nonce_field('zuzunely_table_nonce', 'zuzunely_nonce'); ?>
                
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="table_name"><?php echo esc_html__('Nome da Mesa', 'zuzunely-restaurant'); ?></label>
                            </th>
                            <td>
                                <input type="text" name="table_name" id="table_name" class="regular-text" 
                                       value="<?php echo esc_attr($table['name']); ?>" required>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="table_saloon"><?php echo esc_html__('Salão', 'zuzunely-restaurant'); ?></label>
                            </th>
                            <td>
                                <select name="table_saloon" id="table_saloon" class="regular-text" required>
                                    <option value=""><?php echo esc_html__('Selecione um salão', 'zuzunely-restaurant'); ?></option>
                                    <?php foreach ($saloons as $saloon) : ?>
                                        <option value="<?php echo $saloon['id']; ?>" <?php selected($table['saloon_id'], $saloon['id']); ?>>
                                            <?php echo esc_html($saloon['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (empty($saloons)) : ?>
                                    <p class="description" style="color: #cc0000;">
                                        <?php 
                                        echo esc_html__('Nenhum salão cadastrado. ', 'zuzunely-restaurant');
                                        echo sprintf(
                                            '<a href="%s">%s</a>',
                                            admin_url('admin.php?page=zuzunely-saloons&action=add'),
                                            esc_html__('Adicionar um salão', 'zuzunely-restaurant')
                                        );
                                        ?>
                                    </p>
                                <?php endif; ?>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="table_capacity"><?php echo esc_html__('Capacidade', 'zuzunely-restaurant'); ?></label>
                            </th>
                            <td>
                                <input type="number" name="table_capacity" id="table_capacity" class="small-text" 
                                       value="<?php echo esc_attr($table['capacity']); ?>" min="1" max="100" required>
                                <p class="description"><?php echo esc_html__('Número máximo de pessoas.', 'zuzunely-restaurant'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="table_description"><?php echo esc_html__('Descrição', 'zuzunely-restaurant'); ?></label>
                            </th>
                            <td>
                                <?php
                                wp_editor(
                                    $table['description'],
                                    'table_description',
                                    array(
                                        'textarea_name' => 'table_description',
                                        'textarea_rows' => 5,
                                        'media_buttons' => false,
                                    )
                                );
                                ?>
                                <p class="description"><?php echo esc_html__('Informações adicionais sobre a mesa.', 'zuzunely-restaurant'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="table_active"><?php echo esc_html__('Status', 'zuzunely-restaurant'); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" name="table_active" id="table_active" value="1" 
                                           <?php checked($table['is_active'], 1); ?>>
                                    <?php echo esc_html__('Ativa', 'zuzunely-restaurant'); ?>
                                </label>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <p class="submit">
                    <button type="submit" name="submit" id="submit" class="button button-primary">
                        <?php echo esc_html__('Salvar Mesa', 'zuzunely-restaurant'); ?>
                    </button>
                    
                    <a href="<?php echo admin_url('admin.php?page=zuzunely-tables'); ?>" class="button button-secondary">
                        <?php echo esc_html__('Cancelar', 'zuzunely-restaurant'); ?>
                    </a>
                </p>
            </form>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Enviar formulário
            $('#zuzunely-table-form').on('submit', function(e) {
                e.preventDefault();
                
                // Garantir que o editor visual salvou o conteúdo
                if (typeof tinyMCE !== 'undefined') {
                    tinyMCE.triggerSave();
                }
                
                var form_data = $(this).serialize();
                console.log('Dados serializados:', form_data);
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'zuzunely_save_table',
                        form_data: form_data
                    },
                    beforeSend: function() {
                        $('#submit').prop('disabled', true);
                        console.log('Enviando dados para o servidor...');
                    },
                    success: function(response) {
                        console.log('Resposta:', response);
                        
                        if (response.success) {
                            window.location.href = zuzunely_admin_urls.tables_list;
                        } else {
                            alert(response.data);
                            $('#submit').prop('disabled', false);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Erro AJAX:', error);
                        alert('Erro ao salvar. Tente novamente. Detalhes: ' + error);
                        $('#submit').prop('disabled', false);
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    // Ajax handler para exclusão em massa
    public function ajax_bulk_delete_tables() {
        // Verificar nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'bulk-tables')) {
            wp_send_json_error(__('Erro de segurança.', 'zuzunely-restaurant'));
        }
        
        // Capturar IDs
        if (!isset($_POST['table_ids']) || !is_array($_POST['table_ids'])) {
            wp_send_json_error(__('Nenhuma mesa selecionada.', 'zuzunely-restaurant'));
        }
        
        $table_ids = array_map('intval', $_POST['table_ids']);
        
        if (empty($table_ids)) {
            wp_send_json_error(__('Nenhuma mesa selecionada.', 'zuzunely-restaurant'));
        }
        
        // Inicializar classe de DB
        $db = new Zuzunely_Tables_DB();
        
        // Contador de sucesso
        $success_count = 0;
        
        // Excluir cada mesa
        foreach ($table_ids as $table_id) {
            if ($db->delete_table($table_id)) {
                $success_count++;
            }
        }
        
        if ($success_count > 0) {
            wp_send_json_success(array(
                'message' => sprintf(_n('%d mesa excluída com sucesso.', '%d mesas excluídas com sucesso.', $success_count, 'zuzunely-restaurant'), $success_count)
            ));
        } else {
            wp_send_json_error(__('Erro ao excluir mesas. Tente novamente.', 'zuzunely-restaurant'));
        }
    }
    
    // Ajax handler para salvar mesa
    public function ajax_save_table() {
        // Log de início
        error_log('Iniciando salvamento AJAX da mesa');
        
        // Verificar nonce
        if (!isset($_POST['form_data'])) {
            error_log('Dados do formulário não encontrados');
            wp_send_json_error(__('Erro de dados. Recarregue a página.', 'zuzunely-restaurant'));
            return;
        }
        
        // Capturar dados do formulário
        parse_str($_POST['form_data'], $form_data);
        
        // Verificar nonce
        if (!isset($form_data['zuzunely_nonce']) || !wp_verify_nonce($form_data['zuzunely_nonce'], 'zuzunely_table_nonce')) {
            error_log('Nonce inválido');
            wp_send_json_error(__('Erro de segurança. Recarregue a página.', 'zuzunely-restaurant'));
            return;
        }
        
        $table_id = isset($form_data['table_id']) ? intval($form_data['table_id']) : 0;
        $table_name = isset($form_data['table_name']) ? sanitize_text_field($form_data['table_name']) : '';
        $table_saloon = isset($form_data['table_saloon']) ? intval($form_data['table_saloon']) : 0;
        $table_capacity = isset($form_data['table_capacity']) ? intval($form_data['table_capacity']) : 4;
        $table_description = isset($form_data['table_description']) ? wp_kses_post($form_data['table_description']) : '';
        $table_active = isset($form_data['table_active']) ? 1 : 0;
        
        // Log de dados recebidos
        error_log('Dados do formulário: ' . print_r([
            'id' => $table_id,
            'name' => $table_name,
            'saloon_id' => $table_saloon,
            'capacity' => $table_capacity,
            'active' => $table_active
        ], true));
        
        // Validar dados básicos
        if (empty($table_name)) {
            error_log('Nome da mesa vazio');
            wp_send_json_error(__('O nome da mesa é obrigatório.', 'zuzunely-restaurant'));
            return;
        }
        
        if (empty($table_saloon)) {
            error_log('Salão não selecionado');
            wp_send_json_error(__('É necessário selecionar um salão.', 'zuzunely-restaurant'));
            return;
        }
        
        // Validar capacidade
        if ($table_capacity < 1 || $table_capacity > 100) {
            error_log('Capacidade inválida: ' . $table_capacity);
            wp_send_json_error(__('A capacidade deve ser entre 1 e 100 pessoas.', 'zuzunely-restaurant'));
            return;
        }
        
        // Preparar dados para salvar
        $table_data = array(
            'name' => $table_name,
            'saloon_id' => $table_saloon,
            'capacity' => $table_capacity,
            'description' => $table_description,
            'is_active' => $table_active
        );
        
        // Inicializar classe de DB
        $db = new Zuzunely_Tables_DB();
        
        // Inserir ou atualizar
        if ($table_id > 0) {
            $result = $db->update_table($table_id, $table_data);
            error_log('Tentativa de atualização: ' . ($result ? 'Sucesso' : 'Falha'));
        } else {
            $result = $db->insert_table($table_data);
            error_log('Tentativa de inserção. Resultado: ' . $result);
        }
        
        // Verificar resultado
        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error(__('Erro ao salvar dados. Tente novamente.', 'zuzunely-restaurant'));
        }
    }
    
    // Ajax handler para excluir mesa
    public function ajax_delete_table() {
        // Verificar nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'zuzunely_delete_table')) {
            wp_send_json_error(__('Erro de segurança.', 'zuzunely-restaurant'));
        }
        
        // Capturar ID
        $table_id = isset($_POST['table_id']) ? intval($_POST['table_id']) : 0;
        
        if ($table_id <= 0) {
            wp_send_json_error(__('ID de mesa inválido.', 'zuzunely-restaurant'));
        }
        
        // Inicializar classe de DB
        $db = new Zuzunely_Tables_DB();
        
        // Excluir mesa
        $result = $db->delete_table($table_id);
        
        // Verificar resultado
        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error(__('Erro ao excluir mesa. Tente novamente.', 'zuzunely-restaurant'));
        }
    }
}

// Inicializar classe
add_action('plugins_loaded', function() {
    new Zuzunely_Tables();
});