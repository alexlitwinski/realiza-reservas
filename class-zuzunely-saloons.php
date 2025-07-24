<?php
/**
 * Classe para gerenciar salões
 */

if (!defined('ABSPATH')) {
    exit;
}

class Zuzunely_Saloons {
    
    // Construtor
    public function __construct() {
        // Adicionar ajax handlers para salões
        add_action('wp_ajax_zuzunely_save_saloon', array($this, 'ajax_save_saloon'));
        add_action('wp_ajax_zuzunely_delete_saloon', array($this, 'ajax_delete_saloon'));
        add_action('wp_ajax_zuzunely_bulk_delete_saloons', array($this, 'ajax_bulk_delete_saloons'));
    }
    
    // Página de administração para salões
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
        if (!isset($_REQUEST['_wpnonce']) || !wp_verify_nonce($_REQUEST['_wpnonce'], 'bulk-saloons')) {
            wp_die('Ação não autorizada');
        }
        
        if (isset($_REQUEST['saloon_id']) && is_array($_REQUEST['saloon_id'])) {
            $saloon_ids = array_map('intval', $_REQUEST['saloon_id']);
            
            if (!empty($saloon_ids)) {
                $db = new Zuzunely_Saloons_DB();
                $count = 0;
                
                foreach ($saloon_ids as $saloon_id) {
                    if ($db->delete_saloon($saloon_id)) {
                        $count++;
                    }
                }
                
                // Adicionar mensagem de sucesso
                if ($count > 0) {
                    add_action('admin_notices', function() use ($count) {
                        echo '<div class="notice notice-success is-dismissible">';
                        echo '<p>' . sprintf(_n('%d salão excluído com sucesso.', '%d salões excluídos com sucesso.', $count, 'zuzunely-restaurant'), $count) . '</p>';
                        echo '</div>';
                    });
                }
            }
        }
    }
    
    // Renderizar lista de salões
    private static function render_list_table() {
        // Incluir WP_List_Table se não estiver disponível
        if (!class_exists('WP_List_Table')) {
            require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
        }
        
        // Processar ações em massa
        $table = new Zuzunely_Saloons_List_Table();
        
        // Verifica se uma ação em massa foi solicitada e processa
        $doaction = $table->current_action();
        if ($doaction && 'delete' === $doaction) {
            self::process_bulk_action();
        }
        
        // Verificar se tem algum salão adicionado
        $db = new Zuzunely_Saloons_DB();
        $count = $db->count_saloons(true);
        
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php echo esc_html__('Salões', 'zuzunely-restaurant'); ?></h1>
            <a href="<?php echo admin_url('admin.php?page=zuzunely-saloons&action=add'); ?>" class="page-title-action"><?php echo esc_html__('Adicionar Novo', 'zuzunely-restaurant'); ?></a>
            <a href="<?php echo admin_url('admin.php?page=zuzunely-saloons&refresh=' . time()); ?>" class="page-title-action"><?php echo esc_html__('Atualizar Lista', 'zuzunely-restaurant'); ?></a>
            
            <?php if ($count > 0) : ?>
                <div class="notice notice-info is-dismissible">
                    <p><?php echo sprintf(esc_html__('Há %d salão(ões) cadastrado(s).', 'zuzunely-restaurant'), $count); ?></p>
                </div>
            <?php endif; ?>
            
            <form method="post">
                <?php
                // Adicionar os nonces e outras ações necessárias para o bulk action
                $table->prepare_items(); // Adicionando esta linha para preparar os itens
                wp_nonce_field('bulk-saloons'); // Este nonce precisa corresponder à verificação em process_bulk_action
                $table->display(); 
                ?>
            </form>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Exclusão de salão
            $(document).on('click', '.zuzunely-delete-saloon', function(e) {
                e.preventDefault();
                
                if (!confirm(zuzunely_l10n.confirm_delete)) {
                    return;
                }
                
                var $this = $(this);
                var saloon_id = $this.data('id');
                var nonce = $this.data('nonce');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'zuzunely_delete_saloon',
                        saloon_id: saloon_id,
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
        // Se temos ID, vamos buscar o salão
        $saloon = array(
            'name' => '',
            'description' => '',
            'images' => array(),
            'is_internal' => 1,
            'is_active' => 1
        );
        
        $title = __('Adicionar Novo Salão', 'zuzunely-restaurant');
        
        if ($id > 0) {
            $db = new Zuzunely_Saloons_DB();
            $loaded_saloon = $db->get_saloon($id);
            
            if ($loaded_saloon) {
                $saloon = $loaded_saloon;
                $title = __('Editar Salão', 'zuzunely-restaurant');
            }
        }
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html($title); ?></h1>
            
            <form id="zuzunely-saloon-form" method="post">
                <input type="hidden" name="saloon_id" value="<?php echo $id; ?>">
                <?php wp_nonce_field('zuzunely_saloon_nonce', 'zuzunely_nonce'); ?>
                
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="saloon_name"><?php echo esc_html__('Nome do Salão', 'zuzunely-restaurant'); ?></label>
                            </th>
                            <td>
                                <input type="text" name="saloon_name" id="saloon_name" class="regular-text" 
                                       value="<?php echo esc_attr($saloon['name']); ?>" required>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="saloon_description"><?php echo esc_html__('Descrição', 'zuzunely-restaurant'); ?></label>
                            </th>
                            <td>
                                <?php
                                wp_editor(
                                    $saloon['description'],
                                    'saloon_description',
                                    array(
                                        'textarea_name' => 'saloon_description',
                                        'textarea_rows' => 5,
                                        'media_buttons' => false,
                                    )
                                );
                                ?>
                                <p class="description"><?php echo esc_html__('Descrição detalhada do salão.', 'zuzunely-restaurant'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="saloon_images"><?php echo esc_html__('Imagens', 'zuzunely-restaurant'); ?></label>
                            </th>
                            <td>
                                <div id="zuzunely-image-container">
                                    <?php
                                    if (!empty($saloon['images']) && is_array($saloon['images'])) {
                                        foreach ($saloon['images'] as $attachment_id) {
                                            echo '<div class="zuzunely-image-preview">';
                                            echo wp_get_attachment_image($attachment_id, 'thumbnail');
                                            echo '<input type="hidden" name="saloon_images[]" value="' . intval($attachment_id) . '">';
                                            echo '<button type="button" class="button zuzunely-remove-image">' . esc_html__('Remover', 'zuzunely-restaurant') . '</button>';
                                            echo '</div>';
                                        }
                                    }
                                    ?>
                                </div>
                                
                                <button type="button" id="zuzunely-add-images" class="button">
                                    <?php echo esc_html__('Adicionar Imagens', 'zuzunely-restaurant'); ?>
                                </button>
                                
                                <p class="description"><?php echo esc_html__('Adicione imagens para o salão.', 'zuzunely-restaurant'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="saloon_location"><?php echo esc_html__('Localização', 'zuzunely-restaurant'); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="radio" name="saloon_internal" id="saloon_internal_yes" value="1" 
                                           <?php checked($saloon['is_internal'], 1); ?>>
                                    <?php echo esc_html__('Área Interna', 'zuzunely-restaurant'); ?>
                                </label>
                                <br>
                                <label>
                                    <input type="radio" name="saloon_internal" id="saloon_internal_no" value="0" 
                                           <?php checked($saloon['is_internal'], 0); ?>>
                                    <?php echo esc_html__('Área Externa', 'zuzunely-restaurant'); ?>
                                </label>
                                <p class="description"><?php echo esc_html__('Selecione se o salão fica na área interna ou externa do restaurante.', 'zuzunely-restaurant'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="saloon_active"><?php echo esc_html__('Status', 'zuzunely-restaurant'); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" name="saloon_active" id="saloon_active" value="1" 
                                           <?php checked($saloon['is_active'], 1); ?>>
                                    <?php echo esc_html__('Ativo', 'zuzunely-restaurant'); ?>
                                </label>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <p class="submit">
                    <button type="submit" name="submit" id="submit" class="button button-primary">
                        <?php echo esc_html__('Salvar Salão', 'zuzunely-restaurant'); ?>
                    </button>
                    
                    <a href="<?php echo admin_url('admin.php?page=zuzunely-saloons'); ?>" class="button button-secondary">
                        <?php echo esc_html__('Cancelar', 'zuzunely-restaurant'); ?>
                    </a>
                </p>
            </form>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Seletor de mídia WordPress
            var mediaUploader;
            
            $('#zuzunely-add-images').on('click', function(e) {
                e.preventDefault();
                
                // Se o uploader já existir, abra-o
                if (mediaUploader) {
                    mediaUploader.open();
                    return;
                }
                
                // Criar o uploader de mídia
                mediaUploader = wp.media({
                    title: zuzunely_l10n.select_images,
                    button: {
                        text: zuzunely_l10n.use_images
                    },
                    multiple: true
                });
                
                // Quando uma imagem é selecionada
                mediaUploader.on('select', function() {
                    var attachments = mediaUploader.state().get('selection').toJSON();
                    
                    $.each(attachments, function(i, attachment) {
                        var preview = '<div class="zuzunely-image-preview">';
                        preview += '<img src="' + (attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url) + '" alt="">';
                        preview += '<input type="hidden" name="saloon_images[]" value="' + attachment.id + '">';
                        preview += '<button type="button" class="button zuzunely-remove-image">' + zuzunely_l10n.remove + '</button>';
                        preview += '</div>';
                        
                        $('#zuzunely-image-container').append(preview);
                    });
                });
                
                // Abrir o uploader
                mediaUploader.open();
            });
            
            // Remover imagem
            $(document).on('click', '.zuzunely-remove-image', function() {
                $(this).closest('.zuzunely-image-preview').remove();
            });
            
            // Enviar formulário
            $('#zuzunely-saloon-form').on('submit', function(e) {
                e.preventDefault();
                
                // Garantir que o editor visual salvou o conteúdo
                if (typeof tinyMCE !== 'undefined') {
                    tinyMCE.triggerSave();
                }
                
                var form_data = $(this).serialize();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'zuzunely_save_saloon',
                        form_data: form_data
                    },
                    beforeSend: function() {
                        $('#submit').prop('disabled', true);
                    },
                    success: function(response) {
                        if (response.success) {
                            window.location.href = zuzunely_admin_urls.saloons_list;
                        } else {
                            alert(response.data);
                            $('#submit').prop('disabled', false);
                        }
                    },
                    error: function() {
                        alert(zuzunely_l10n.error_saving);
                        $('#submit').prop('disabled', false);
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    // Ajax handler para salvar salão
    public function ajax_save_saloon() {
        // Verificar se temos dados do formulário
        if (!isset($_POST['form_data'])) {
            wp_send_json_error(__('Dados do formulário não encontrados.', 'zuzunely-restaurant'));
        }
        
        // Parse dos dados do formulário
        parse_str($_POST['form_data'], $form_data);
        
        // Verificar nonce
        if (!isset($form_data['zuzunely_nonce']) || !wp_verify_nonce($form_data['zuzunely_nonce'], 'zuzunely_saloon_nonce')) {
            wp_send_json_error(__('Erro de segurança. Tente recarregar a página.', 'zuzunely-restaurant'));
        }
        
        // Capturar dados
        $saloon_id = isset($form_data['saloon_id']) ? intval($form_data['saloon_id']) : 0;
        $saloon_name = isset($form_data['saloon_name']) ? sanitize_text_field($form_data['saloon_name']) : '';
        $saloon_description = isset($form_data['saloon_description']) ? wp_kses_post($form_data['saloon_description']) : '';
        $saloon_images = isset($form_data['saloon_images']) ? array_map('intval', $form_data['saloon_images']) : array();
        $saloon_internal = isset($form_data['saloon_internal']) ? intval($form_data['saloon_internal']) : 1;
        $saloon_active = isset($form_data['saloon_active']) ? 1 : 0;
        
        // Validar nome
        if (empty($saloon_name)) {
            wp_send_json_error(__('Nome do salão é obrigatório.', 'zuzunely-restaurant'));
        }
        
        // Preparar dados para salvar
        $saloon_data = array(
            'name' => $saloon_name,
            'description' => $saloon_description,
            'images' => $saloon_images,
            'is_internal' => $saloon_internal,
            'is_active' => $saloon_active
        );
        
        // Inicializar classe de DB
        $db = new Zuzunely_Saloons_DB();
        
        // Inserir ou atualizar
        if ($saloon_id > 0) {
            $result = $db->update_saloon($saloon_id, $saloon_data);
        } else {
            $result = $db->insert_saloon($saloon_data);
        }
        
        // Verificar resultado
        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error(__('Erro ao salvar dados. Tente novamente.', 'zuzunely-restaurant'));
        }
    }
    
    // Ajax handler para excluir salão
    public function ajax_delete_saloon() {
        // Verificar nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'zuzunely_delete_saloon')) {
            wp_send_json_error(__('Erro de segurança.', 'zuzunely-restaurant'));
        }
        
        // Capturar ID
        $saloon_id = isset($_POST['saloon_id']) ? intval($_POST['saloon_id']) : 0;
        
        if ($saloon_id <= 0) {
            wp_send_json_error(__('ID de salão inválido.', 'zuzunely-restaurant'));
        }
        
        // Inicializar classe de DB
        $db = new Zuzunely_Saloons_DB();
        
        // Excluir salão
        $result = $db->delete_saloon($saloon_id);
        
        // Verificar resultado
        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error(__('Erro ao excluir salão. Tente novamente.', 'zuzunely-restaurant'));
        }
    }
    
    // Ajax handler para exclusão em massa
    public function ajax_bulk_delete_saloons() {
        // Verificar nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'bulk-saloons')) {
            wp_send_json_error(__('Erro de segurança.', 'zuzunely-restaurant'));
        }
        
        // Capturar IDs
        if (!isset($_POST['saloon_ids']) || !is_array($_POST['saloon_ids'])) {
            wp_send_json_error(__('Nenhum salão selecionado.', 'zuzunely-restaurant'));
        }
        
        $saloon_ids = array_map('intval', $_POST['saloon_ids']);
        
        if (empty($saloon_ids)) {
            wp_send_json_error(__('Nenhum salão selecionado.', 'zuzunely-restaurant'));
        }
        
        // Inicializar classe de DB
        $db = new Zuzunely_Saloons_DB();
        
        // Contador de sucesso
        $success_count = 0;
        
        // Excluir cada salão
        foreach ($saloon_ids as $saloon_id) {
            if ($db->delete_saloon($saloon_id)) {
                $success_count++;
            }
        }
        
        if ($success_count > 0) {
            wp_send_json_success(array(
                'message' => sprintf(_n('%d salão excluído com sucesso.', '%d salões excluídos com sucesso.', $success_count, 'zuzunely-restaurant'), $success_count)
            ));
        } else {
            wp_send_json_error(__('Erro ao excluir salões. Tente novamente.', 'zuzunely-restaurant'));
        }
    }
}

// Inicializar classe
add_action('plugins_loaded', function() {
    new Zuzunely_Saloons();
});