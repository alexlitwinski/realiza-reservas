<?php
/**
 * Gestão de Áreas do restaurante
 */

if (!defined('ABSPATH')) {
    exit;
}

class Zuzunely_Areas {
    public function __construct() {
        add_action('wp_ajax_zuzunely_save_area', [$this, 'ajax_save_area']);
    }

    public static function admin_page() {
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

    private static function render_list_table() {
        if (!class_exists('WP_List_Table')) {
            require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
        }
        $table = new Zuzunely_Areas_List_Table();
        $table->prepare_items();
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php echo esc_html__('Áreas', 'zuzunely-restaurant'); ?></h1>
            <a href="<?php echo admin_url('admin.php?page=zuzunely-areas&action=add'); ?>" class="page-title-action"><?php echo esc_html__('Adicionar Nova', 'zuzunely-restaurant'); ?></a>
            <form method="post">
                <?php
                wp_nonce_field('bulk-areas');
                $table->display();
                ?>
            </form>
        </div>
        <?php
    }

    private static function render_add_edit_form($id = 0) {
        $area = ['name' => '', 'description' => '', 'is_active' => 1];
        $title = __('Adicionar Nova Área', 'zuzunely-restaurant');
        if ($id > 0) {
            $db = new Zuzunely_Areas_DB();
            $loaded = $db->get_area($id);
            if ($loaded) {
                $area = $loaded;
                $title = __('Editar Área', 'zuzunely-restaurant');
            }
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html($title); ?></h1>
            <form id="zuzunely-area-form" method="post">
                <input type="hidden" name="area_id" value="<?php echo $id; ?>">
                <?php wp_nonce_field('zuzunely_area_nonce', 'zuzunely_nonce'); ?>
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="area_name"><?php echo esc_html__('Nome', 'zuzunely-restaurant'); ?></label></th>
                            <td><input type="text" name="area_name" id="area_name" class="regular-text" value="<?php echo esc_attr($area['name']); ?>" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="area_description"><?php echo esc_html__('Descrição', 'zuzunely-restaurant'); ?></label></th>
                            <td><?php wp_editor($area['description'], 'area_description', ['textarea_name' => 'area_description', 'textarea_rows' => 5, 'media_buttons' => false]); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="area_active"><?php echo esc_html__('Status', 'zuzunely-restaurant'); ?></label></th>
                            <td><label><input type="checkbox" name="area_active" id="area_active" value="1" <?php checked($area['is_active'], 1); ?>> <?php echo esc_html__('Ativa', 'zuzunely-restaurant'); ?></label></td>
                        </tr>
                    </tbody>
                </table>
                <p class="submit">
                    <button type="submit" class="button button-primary"><?php echo esc_html__('Salvar Área', 'zuzunely-restaurant'); ?></button>
                    <a href="<?php echo admin_url('admin.php?page=zuzunely-areas'); ?>" class="button button-secondary"><?php echo esc_html__('Cancelar', 'zuzunely-restaurant'); ?></a>
                </p>
            </form>
        </div>
        <script>
        jQuery(function($){
            $('#zuzunely-area-form').on('submit', function(e){
                e.preventDefault();
                if (typeof tinyMCE !== 'undefined') { tinyMCE.triggerSave(); }
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {action:'zuzunely_save_area', form_data: $(this).serialize()},
                    success:function(r){ if(r.success){ window.location.href='<?php echo admin_url('admin.php?page=zuzunely-areas'); ?>'; } else { alert(r.data); } }
                });
            });
        });
        </script>
        <?php
    }

    public function ajax_save_area() {
        if (!isset($_POST['form_data'])) {
            wp_send_json_error(__('Dados do formulário não encontrados.', 'zuzunely-restaurant'));
        }
        parse_str($_POST['form_data'], $data);
        if (!isset($data['zuzunely_nonce']) || !wp_verify_nonce($data['zuzunely_nonce'], 'zuzunely_area_nonce')) {
            wp_send_json_error(__('Erro de segurança.', 'zuzunely-restaurant'));
        }
        $id = isset($data['area_id']) ? intval($data['area_id']) : 0;
        $name = sanitize_text_field($data['area_name']);
        $description = wp_kses_post($data['area_description']);
        $active = isset($data['area_active']) ? 1 : 0;
        if (empty($name)) {
            wp_send_json_error(__('Nome da área é obrigatório.', 'zuzunely-restaurant'));
        }
        $db = new Zuzunely_Areas_DB();
        $area_data = ['name'=>$name,'description'=>$description,'is_active'=>$active];
        if ($id > 0) {
            $result = $db->update_area($id, $area_data);
        } else {
            $result = $db->insert_area($area_data);
        }
        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error(__('Erro ao salvar dados.', 'zuzunely-restaurant'));
        }
    }
}
