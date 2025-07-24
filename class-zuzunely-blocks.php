<?php
/**
 * Classe para gerenciar bloqueios
 */

if (!defined('ABSPATH')) {
    exit;
}

class Zuzunely_Blocks {
    
    // Construtor
    public function __construct() {
        // Adicionar ajax handlers para bloqueios
        add_action('wp_ajax_zuzunely_save_block', array($this, 'ajax_save_block'));
        add_action('wp_ajax_zuzunely_delete_block', array($this, 'ajax_delete_block'));
        add_action('wp_ajax_zuzunely_bulk_delete_blocks', array($this, 'ajax_bulk_delete_blocks'));
    }
    
    // Página de administração para bloqueios
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
        if (!isset($_REQUEST['_wpnonce']) || !wp_verify_nonce($_REQUEST['_wpnonce'], 'bulk-blocks')) {
            wp_die('Ação não autorizada');
        }
        
        if (isset($_REQUEST['block_id']) && is_array($_REQUEST['block_id'])) {
            $block_ids = array_map('intval', $_REQUEST['block_id']);
            
            if (!empty($block_ids)) {
                $db = new Zuzunely_Blocks_DB();
                $count = 0;
                
                foreach ($block_ids as $block_id) {
                    if ($db->delete_block($block_id)) {
                        $count++;
                    }
                }
                
                // Adicionar mensagem de sucesso
                if ($count > 0) {
                    add_action('admin_notices', function() use ($count) {
                        echo '<div class="notice notice-success is-dismissible">';
                        echo '<p>' . sprintf(_n('%d bloqueio excluído com sucesso.', '%d bloqueios excluídos com sucesso.', $count, 'zuzunely-restaurant'), $count) . '</p>';
                        echo '</div>';
                    });
                }
            }
        }
    }
    
    // Renderizar lista de bloqueios
    private static function render_list_table() {
        // Incluir WP_List_Table se não estiver disponível
        if (!class_exists('WP_List_Table')) {
            require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
        }
        
        // Incluir classe da tabela de listagem
        require_once ZUZUNELY_PLUGIN_DIR . 'class-zuzunely-blocks-list-table.php';
        
        // Processar ações em massa
        $table = new Zuzunely_Blocks_List_Table();
        
        // Verifica se uma ação em massa foi solicitada e processa
        $doaction = $table->current_action();
        if ($doaction && 'delete' === $doaction) {
            self::process_bulk_action();
        }
        
        // Filtros
        $block_type_filter = isset($_GET['block_type']) ? sanitize_text_field($_GET['block_type']) : '';
        $reference_id_filter = isset($_GET['reference_id']) ? intval($_GET['reference_id']) : 0;
        
        // Criar instância da tabela
        $blocks_db = new Zuzunely_Blocks_DB();
        $table->prepare_items($block_type_filter, $reference_id_filter);
        
        // CORREÇÃO: Obter salões diretamente do banco de dados
        global $wpdb;
        $saloons_table = $wpdb->prefix . 'zuzunely_saloons';
        $saloons_query = "SELECT id, name FROM {$saloons_table} WHERE is_active = 1 ORDER BY name";
        $saloons = $wpdb->get_results($saloons_query, ARRAY_A);
        
        // CORREÇÃO: Obter mesas diretamente do banco de dados
        $tables_table = $wpdb->prefix . 'zuzunely_tables';
        $tables_query = "SELECT t.id, t.name, s.name as saloon_name 
                         FROM {$tables_table} t 
                         LEFT JOIN {$saloons_table} s ON t.saloon_id = s.id 
                         WHERE t.is_active = 1
                         ORDER BY s.name, t.name";
        $tables = $wpdb->get_results($tables_query, ARRAY_A);
        
        // Total de bloqueios
        $count = $blocks_db->count_blocks(array('include_inactive' => true));
        
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php echo esc_html__('Bloqueios', 'zuzunely-restaurant'); ?></h1>
            <a href="<?php echo admin_url('admin.php?page=zuzunely-blocks&action=add'); ?>" class="page-title-action"><?php echo esc_html__('Adicionar Novo', 'zuzunely-restaurant'); ?></a>
            <a href="<?php echo admin_url('admin.php?page=zuzunely-blocks&refresh=' . time()); ?>" class="page-title-action"><?php echo esc_html__('Atualizar Lista', 'zuzunely-restaurant'); ?></a>
            
            <?php if ($count > 0) : ?>
                <div class="notice notice-info is-dismissible">
                    <p><?php echo sprintf(esc_html__('Há %d bloqueio(s) cadastrado(s).', 'zuzunely-restaurant'), $count); ?></p>
                </div>
            <?php endif; ?>
            
            <div class="tablenav top">
                <div class="alignleft actions">
                    <form method="get">
                        <input type="hidden" name="page" value="zuzunely-blocks">
                        <select name="block_type">
                            <option value=""><?php echo esc_html__('Todos os tipos', 'zuzunely-restaurant'); ?></option>
                            <option value="restaurant" <?php selected($block_type_filter, 'restaurant'); ?>>
                                <?php echo esc_html__('Bloqueio do Restaurante', 'zuzunely-restaurant'); ?>
                            </option>
                            <option value="saloon" <?php selected($block_type_filter, 'saloon'); ?>>
                                <?php echo esc_html__('Bloqueio de Salão', 'zuzunely-restaurant'); ?>
                            </option>
                            <option value="table" <?php selected($block_type_filter, 'table'); ?>>
                                <?php echo esc_html__('Bloqueio de Mesa', 'zuzunely-restaurant'); ?>
                            </option>
                        </select>
                        
                        <?php if ($block_type_filter === 'saloon' && !empty($saloons)) : ?>
                            <select name="reference_id">
                                <option value="0"><?php echo esc_html__('Todos os salões', 'zuzunely-restaurant'); ?></option>
                                <?php foreach ($saloons as $saloon) : ?>
                                    <option value="<?php echo $saloon['id']; ?>" <?php selected($reference_id_filter, $saloon['id']); ?>>
                                        <?php echo esc_html($saloon['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                        
                        <?php if ($block_type_filter === 'table' && !empty($tables)) : ?>
                            <select name="reference_id">
                                <option value="0"><?php echo esc_html__('Todas as mesas', 'zuzunely-restaurant'); ?></option>
                                <?php foreach ($tables as $table) : ?>
                                    <option value="<?php echo $table['id']; ?>" <?php selected($reference_id_filter, $table['id']); ?>>
                                        <?php echo esc_html($table['name']); ?> (<?php echo esc_html($table['saloon_name']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                        
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
            // Exclusão de bloqueio
            $(document).on('click', '.zuzunely-delete-block', function(e) {
                e.preventDefault();
                
                if (!confirm(zuzunely_l10n.confirm_delete)) {
                    return;
                }
                
                var $this = $(this);
                var block_id = $this.data('id');
                var nonce = $this.data('nonce');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'zuzunely_delete_block',
                        block_id: block_id,
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
        // Se temos ID, vamos buscar o bloqueio
        $block = array(
            'block_type' => 'restaurant',
            'reference_id' => 0,
            'start_date' => date('Y-m-d'),
            'end_date' => date('Y-m-d'),
            'start_time' => '00:00',
            'end_time' => '23:59',
            'reason' => '',
            'is_active' => 1
        );
        
        $title = __('Adicionar Novo Bloqueio', 'zuzunely-restaurant');
        
        if ($id > 0) {
            $db = new Zuzunely_Blocks_DB();
            $loaded_block = $db->get_block($id);
            
            if ($loaded_block) {
                $block = $loaded_block;
                $title = __('Editar Bloqueio', 'zuzunely-restaurant');
            }
        }
        
        // CORREÇÃO: Obter salões diretamente do banco de dados
        global $wpdb;
        $saloons_table = $wpdb->prefix . 'zuzunely_saloons';
        $saloons_query = "SELECT id, name FROM {$saloons_table} WHERE is_active = 1 ORDER BY name";
        $saloons = $wpdb->get_results($saloons_query, ARRAY_A);
        
        // CORREÇÃO: Obter mesas diretamente do banco de dados
        $tables_table = $wpdb->prefix . 'zuzunely_tables';
        $tables_query = "SELECT t.id, t.name, s.name as saloon_name 
                         FROM {$tables_table} t 
                         LEFT JOIN {$saloons_table} s ON t.saloon_id = s.id 
                         WHERE t.is_active = 1
                         ORDER BY s.name, t.name";
        $tables = $wpdb->get_results($tables_query, ARRAY_A);
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html($title); ?></h1>
            
            <form id="zuzunely-block-form" method="post">
                <input type="hidden" name="block_id" value="<?php echo $id; ?>">
                <?php wp_nonce_field('zuzunely_block_nonce', 'zuzunely_nonce'); ?>
                
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="block_type"><?php echo esc_html__('Tipo de Bloqueio', 'zuzunely-restaurant'); ?></label>
                            </th>
                            <td>
                                <select name="block_type" id="block_type" class="regular-text" required>
                                    <option value="restaurant" <?php selected($block['block_type'], 'restaurant'); ?>>
                                        <?php echo esc_html__('Bloqueio do Restaurante Inteiro', 'zuzunely-restaurant'); ?>
                                    </option>
                                    <option value="saloon" <?php selected($block['block_type'], 'saloon'); ?>>
                                        <?php echo esc_html__('Bloqueio de Salão', 'zuzunely-restaurant'); ?>
                                    </option>
                                    <option value="table" <?php selected($block['block_type'], 'table'); ?>>
                                        <?php echo esc_html__('Bloqueio de Mesa', 'zuzunely-restaurant'); ?>
                                    </option>
                                </select>
                                <p class="description"><?php echo esc_html__('Selecione o tipo de bloqueio que deseja criar.', 'zuzunely-restaurant'); ?></p>
                            </td>
                        </tr>
                        
                        <!-- Bloco de referência para Salão (exibido/ocultado via JavaScript) -->
                        <tr class="reference-block saloon-reference" style="<?php echo ($block['block_type'] != 'saloon') ? 'display:none;' : ''; ?>">
                            <th scope="row">
                                <label for="reference_saloon"><?php echo esc_html__('Salão', 'zuzunely-restaurant'); ?></label>
                            </th>
                            <td>
                                <select name="reference_saloon" id="reference_saloon" class="regular-text">
                                    <option value=""><?php echo esc_html__('Selecione um salão', 'zuzunely-restaurant'); ?></option>
                                    <?php foreach ($saloons as $saloon) : ?>
                                        <option value="<?php echo $saloon['id']; ?>" <?php selected($block['reference_id'], $saloon['id']); ?>>
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
                        
                        <!-- Bloco de referência para Mesa (exibido/ocultado via JavaScript) -->
                        <tr class="reference-block table-reference" style="<?php echo ($block['block_type'] != 'table') ? 'display:none;' : ''; ?>">
                            <th scope="row">
                                <label for="reference_table"><?php echo esc_html__('Mesa', 'zuzunely-restaurant'); ?></label>
                            </th>
                            <td>
                                <select name="reference_table" id="reference_table" class="regular-text">
                                    <option value=""><?php echo esc_html__('Selecione uma mesa', 'zuzunely-restaurant'); ?></option>
                                    <?php foreach ($tables as $table) : ?>
                                        <option value="<?php echo $table['id']; ?>" <?php selected($block['reference_id'], $table['id']); ?>>
                                            <?php echo esc_html($table['name']); ?> (<?php echo esc_html($table['saloon_name']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (empty($tables)) : ?>
                                    <p class="description" style="color: #cc0000;">
                                        <?php 
                                        echo esc_html__('Nenhuma mesa cadastrada. ', 'zuzunely-restaurant');
                                        echo sprintf(
                                            '<a href="%s">%s</a>',
                                            admin_url('admin.php?page=zuzunely-tables&action=add'),
                                            esc_html__('Adicionar uma mesa', 'zuzunely-restaurant')
                                        );
                                        ?>
                                    </p>
                                <?php endif; ?>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="start_date"><?php echo esc_html__('Data de Início', 'zuzunely-restaurant'); ?></label>
                            </th>
                            <td>
                                <input type="date" name="start_date" id="start_date" class="regular-text" 
                                       value="<?php echo esc_attr($block['start_date']); ?>" required>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="end_date"><?php echo esc_html__('Data de Término', 'zuzunely-restaurant'); ?></label>
                            </th>
                            <td>
                                <input type="date" name="end_date" id="end_date" class="regular-text" 
                                       value="<?php echo esc_attr($block['end_date']); ?>" required>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="start_time"><?php echo esc_html__('Horário de Início', 'zuzunely-restaurant'); ?></label>
                            </th>
                            <td>
                                <input type="time" name="start_time" id="start_time" class="regular-text" 
                                       value="<?php echo esc_attr($block['start_time']); ?>" required>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="end_time"><?php echo esc_html__('Horário de Término', 'zuzunely-restaurant'); ?></label>
                            </th>
                            <td>
                                <input type="time" name="end_time" id="end_time" class="regular-text" 
                                       value="<?php echo esc_attr($block['end_time']); ?>" required>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="block_reason"><?php echo esc_html__('Motivo do Bloqueio', 'zuzunely-restaurant'); ?></label>
                            </th>
                            <td>
                                <?php
                                wp_editor(
                                    $block['reason'],
                                    'block_reason',
                                    array(
                                        'textarea_name' => 'block_reason',
                                        'textarea_rows' => 5,
                                        'media_buttons' => false,
                                    )
                                );
                                ?>
                                <p class="description"><?php echo esc_html__('Motivo ou observações sobre este bloqueio.', 'zuzunely-restaurant'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="block_active"><?php echo esc_html__('Status', 'zuzunely-restaurant'); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" name="block_active" id="block_active" value="1" 
                                           <?php checked($block['is_active'], 1); ?>>
                                    <?php echo esc_html__('Ativo', 'zuzunely-restaurant'); ?>
                                </label>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <p class="submit">
                    <button type="submit" name="submit" id="submit" class="button button-primary">
                        <?php echo esc_html__('Salvar Bloqueio', 'zuzunely-restaurant'); ?>
                    </button>
                    
                    <a href="<?php echo admin_url('admin.php?page=zuzunely-blocks'); ?>" class="button button-secondary">
                        <?php echo esc_html__('Cancelar', 'zuzunely-restaurant'); ?>
                    </a>
                </p>
            </form>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Alternar campos de referência com base no tipo de bloqueio
            $('#block_type').on('change', function() {
                var blockType = $(this).val();
                
                // Ocultar todos os campos de referência
                $('.reference-block').hide();
                
                // Mostrar campo específico com base no tipo selecionado
                if (blockType === 'saloon') {
                    $('.saloon-reference').show();
                } else if (blockType === 'table') {
                    $('.table-reference').show();
                }
            });
            
            // Trigger para configuração inicial
            $('#block_type').trigger('change');
            
            // Enviar formulário
            $('#zuzunely-block-form').on('submit', function(e) {
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
                        action: 'zuzunely_save_block',
                        form_data: form_data
                    },
                    beforeSend: function() {
                        $('#submit').prop('disabled', true);
                        console.log('Enviando dados para o servidor...');
                    },
                    success: function(response) {
                        console.log('Resposta:', response);
                        
                        if (response.success) {
                            window.location.href = zuzunely_admin_urls.blocks_list;
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
    public function ajax_bulk_delete_blocks() {
        // Verificar nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'bulk-blocks')) {
            wp_send_json_error(__('Erro de segurança.', 'zuzunely-restaurant'));
        }
        
        // Capturar IDs
        if (!isset($_POST['block_ids']) || !is_array($_POST['block_ids'])) {
            wp_send_json_error(__('Nenhum bloqueio selecionado.', 'zuzunely-restaurant'));
        }
        
        $block_ids = array_map('intval', $_POST['block_ids']);
        
        if (empty($block_ids)) {
            wp_send_json_error(__('Nenhum bloqueio selecionado.', 'zuzunely-restaurant'));
        }
        
        // Inicializar classe de DB
        $db = new Zuzunely_Blocks_DB();
        
        // Contador de sucesso
        $success_count = 0;
        
        // Excluir cada bloqueio
        foreach ($block_ids as $block_id) {
            if ($db->delete_block($block_id)) {
                $success_count++;
            }
        }
        
        if ($success_count > 0) {
            wp_send_json_success(array(
                'message' => sprintf(_n('%d bloqueio excluído com sucesso.', '%d bloqueios excluídos com sucesso.', $success_count, 'zuzunely-restaurant'), $success_count)
            ));
        } else {
            wp_send_json_error(__('Erro ao excluir bloqueios. Tente novamente.', 'zuzunely-restaurant'));
        }
    }
    
    // Ajax handler para salvar bloqueio
    public function ajax_save_block() {
        // Verificar nonce
        if (!isset($_POST['form_data'])) {
            wp_send_json_error(__('Erro de dados. Recarregue a página.', 'zuzunely-restaurant'));
            return;
        }
        
        // Capturar dados do formulário
        parse_str($_POST['form_data'], $form_data);
        
        // Verificar nonce
        if (!isset($form_data['zuzunely_nonce']) || !wp_verify_nonce($form_data['zuzunely_nonce'], 'zuzunely_block_nonce')) {
            wp_send_json_error(__('Erro de segurança. Recarregue a página.', 'zuzunely-restaurant'));
            return;
        }
        
        $block_id = isset($form_data['block_id']) ? intval($form_data['block_id']) : 0;
        $block_type = isset($form_data['block_type']) ? sanitize_text_field($form_data['block_type']) : '';
        $reference_id = 0;
        
        // Obter ID de referência com base no tipo
        if ($block_type === 'saloon' && isset($form_data['reference_saloon'])) {
            $reference_id = intval($form_data['reference_saloon']);
        } else if ($block_type === 'table' && isset($form_data['reference_table'])) {
            $reference_id = intval($form_data['reference_table']);
        }
        
        $start_date = isset($form_data['start_date']) ? sanitize_text_field($form_data['start_date']) : '';
        $end_date = isset($form_data['end_date']) ? sanitize_text_field($form_data['end_date']) : '';
        $start_time = isset($form_data['start_time']) ? sanitize_text_field($form_data['start_time']) : '';
        $end_time = isset($form_data['end_time']) ? sanitize_text_field($form_data['end_time']) : '';
        $reason = isset($form_data['block_reason']) ? wp_kses_post($form_data['block_reason']) : '';
        $is_active = isset($form_data['block_active']) ? 1 : 0;
        
        // Validar dados básicos
        if (empty($block_type)) {
            wp_send_json_error(__('O tipo de bloqueio é obrigatório.', 'zuzunely-restaurant'));
            return;
        }
        
        // Validar datas
        if (empty($start_date) || empty($end_date)) {
            wp_send_json_error(__('As datas de início e término são obrigatórias.', 'zuzunely-restaurant'));
            return;
        }
        
        // Validar horários
        if (empty($start_time) || empty($end_time)) {
            wp_send_json_error(__('Os horários de início e término são obrigatórios.', 'zuzunely-restaurant'));
            return;
        }
        
        // Validar referência para tipos que necessitam
        if (($block_type === 'saloon' || $block_type === 'table') && empty($reference_id)) {
            wp_send_json_error(__('É necessário selecionar um item de referência.', 'zuzunely-restaurant'));
            return;
        }
        
        // Validar datas (início <= término)
        if (strtotime($start_date) > strtotime($end_date)) {
            wp_send_json_error(__('A data de início deve ser anterior ou igual à data de término.', 'zuzunely-restaurant'));
            return;
        }
        
        // Preparar dados para salvar
        $block_data = array(
            'block_type' => $block_type,
            'reference_id' => $reference_id,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'start_time' => $start_time,
            'end_time' => $end_time,
            'reason' => $reason,
            'is_active' => $is_active
        );
        
        // Inicializar classe de DB
        $db = new Zuzunely_Blocks_DB();
        
        // Inserir ou atualizar
        if ($block_id > 0) {
            $result = $db->update_block($block_id, $block_data);
        } else {
            $result = $db->insert_block($block_data);
        }
        
        // Verificar resultado
        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error(__('Erro ao salvar dados. Tente novamente.', 'zuzunely-restaurant'));
        }
    }
    
    // Ajax handler para excluir bloqueio
    public function ajax_delete_block() {
        // Verificar nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'zuzunely_delete_block')) {
            wp_send_json_error(__('Erro de segurança.', 'zuzunely-restaurant'));
        }
        
        // Capturar ID
        $block_id = isset($_POST['block_id']) ? intval($_POST['block_id']) : 0;
        
        if ($block_id <= 0) {
            wp_send_json_error(__('ID de bloqueio inválido.', 'zuzunely-restaurant'));
        }
        
        // Inicializar classe de DB
        $db = new Zuzunely_Blocks_DB();
        
        // Excluir bloqueio
        $result = $db->delete_block($block_id);
        
        // Verificar resultado
        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error(__('Erro ao excluir bloqueio. Tente novamente.', 'zuzunely-restaurant'));
        }
    }
}

// Inicializar classe
add_action('plugins_loaded', function() {
    new Zuzunely_Blocks();
});