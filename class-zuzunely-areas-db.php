<?php
/**
 * Classe para gerenciar banco de dados de Áreas do restaurante
 */

if (!defined('ABSPATH')) {
    exit;
}

class Zuzunely_Areas_DB {
    private $areas_table;

    public function __construct() {
        global $wpdb;
        $this->areas_table = $wpdb->prefix . 'zuzunely_areas';
    }

    public function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$this->areas_table} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text NOT NULL,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function insert_area($data) {
        global $wpdb;
        $insert = array(
            'name' => sanitize_text_field($data['name']),
            'description' => wp_kses_post($data['description']),
            'is_active' => isset($data['is_active']) ? 1 : 0,
        );
        $result = $wpdb->insert($this->areas_table, $insert, array('%s', '%s', '%d'));
        if ($result === false) {
            return false;
        }
        return $wpdb->insert_id;
    }

    public function update_area($id, $data) {
        global $wpdb;
        $update = array(
            'name' => sanitize_text_field($data['name']),
            'description' => wp_kses_post($data['description']),
            'is_active' => isset($data['is_active']) ? 1 : 0,
        );
        $result = $wpdb->update($this->areas_table, $update, array('id' => $id), array('%s', '%s', '%d'), array('%d'));
        return $result !== false;
    }

    public function delete_area($id) {
        global $wpdb;
        $result = $wpdb->delete($this->areas_table, array('id' => $id), array('%d'));
        return $result !== false;
    }

    public function get_area($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->areas_table} WHERE id = %d", $id), ARRAY_A);
    }

    public function get_areas($args = array()) {
        global $wpdb;
        $defaults = array(
            'number' => 20,
            'offset' => 0,
            'orderby' => 'id',
            'order' => 'DESC',
            'include_inactive' => false,
        );
        $args = wp_parse_args($args, $defaults);
        $where = '';
        if (!$args['include_inactive']) {
            $where = "WHERE is_active = 1";
        }
        $valid_orderby_fields = array('id', 'name', 'created_at');
        $orderby = in_array($args['orderby'], $valid_orderby_fields) ? $args['orderby'] : 'id';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        $sql = "SELECT * FROM {$this->areas_table} $where ORDER BY $orderby $order LIMIT %d, %d";
        return $wpdb->get_results($wpdb->prepare($sql, $args['offset'], $args['number']), ARRAY_A);
    }

    public function count_areas($include_inactive = false) {
        global $wpdb;
        $where = $include_inactive ? '' : "WHERE is_active = 1";
        return (int)$wpdb->get_var("SELECT COUNT(id) FROM {$this->areas_table} $where");
    }
}
