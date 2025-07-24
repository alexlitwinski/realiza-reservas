<?php
/**
 * Lista de Áreas para admin
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class Zuzunely_Areas_List_Table extends WP_List_Table {
    public function __construct() {
        parent::__construct([
            'singular' => 'area',
            'plural'   => 'areas',
            'ajax'     => false,
        ]);
    }

    public function get_columns() {
        return [
            'cb'         => '<input type="checkbox" />',
            'name'       => __('Nome', 'zuzunely-restaurant'),
            'description'=> __('Descrição', 'zuzunely-restaurant'),
            'is_active'  => __('Status', 'zuzunely-restaurant'),
            'created_at' => __('Data de Criação', 'zuzunely-restaurant'),
        ];
    }

    public function get_sortable_columns() {
        return [
            'name'       => ['name', true],
            'created_at' => ['created_at', true],
        ];
    }

    public function prepare_items() {
        $per_page = 10;
        $current_page = $this->get_pagenum();
        $db = new Zuzunely_Areas_DB();
        $total_items = $db->count_areas(true);
        $orderby = isset($_REQUEST['orderby']) ? sanitize_text_field($_REQUEST['orderby']) : 'id';
        $order = isset($_REQUEST['order']) ? sanitize_text_field($_REQUEST['order']) : 'DESC';
        $this->items = $db->get_areas([
            'number' => $per_page,
            'offset' => ($current_page - 1) * $per_page,
            'orderby'=> $orderby,
            'order'  => $order,
            'include_inactive' => true,
        ]);
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page),
        ]);
        $this->_column_headers = [$this->get_columns(), [], $this->get_sortable_columns(), 'name'];
    }

    public function column_cb($item) {
        return sprintf('<input type="checkbox" name="area_id[]" value="%s" />', $item['id']);
    }

    public function column_name($item) {
        $edit_url = admin_url(sprintf('admin.php?page=zuzunely-areas&action=edit&id=%s', $item['id']));
        $actions = [
            'edit'   => sprintf('<a href="%s">%s</a>', $edit_url, __('Editar', 'zuzunely-restaurant')),
        ];
        return sprintf('<strong><a href="%s">%s</a></strong> %s', $edit_url, $item['name'], $this->row_actions($actions));
    }

    public function column_description($item) {
        return wp_trim_words($item['description'], 10, '...');
    }

    public function column_is_active($item) {
        return $item['is_active'] ? __('Ativa', 'zuzunely-restaurant') : __('Inativa', 'zuzunely-restaurant');
    }

    public function column_created_at($item) {
        $date = new DateTime($item['created_at']);
        return $date->format(get_option('date_format') . ' ' . get_option('time_format'));
    }

    public function column_default($item, $column_name) {
        return isset($item[$column_name]) ? $item[$column_name] : '—';
    }

    public function get_bulk_actions() {
        return [
            'delete' => __('Excluir', 'zuzunely-restaurant'),
        ];
    }

    public function no_items() {
        _e('Nenhuma área encontrada.', 'zuzunely-restaurant');
    }
}
