<?php
/**
 * Classe para gerenciar banco de dados de salões do plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class Zuzunely_Saloons_DB {
    
    // Nomes das tabelas
    private $saloons_table;
    
    // Construtor
    public function __construct() {
        global $wpdb;
        
        // Definir nomes das tabelas
        $this->saloons_table = $wpdb->prefix . 'zuzunely_saloons';
    }
    
    // Criar tabelas personalizadas
    public function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // SQL para criar tabela de salões
        $sql = "CREATE TABLE {$this->saloons_table} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text NOT NULL,
            images longtext,
            area_id mediumint(9) NOT NULL,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        
        // Usar dbDelta para executar SQL seguro
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Log para facilitar depuração
        error_log('Tabela criada: ' . $this->saloons_table);
    }
    
    // Obter nome da tabela de salões
    public function get_saloons_table() {
        return $this->saloons_table;
    }
    
    // Inserir salão
    public function insert_saloon($data) {
        global $wpdb;
        
        // Certificar que imagens estão em formato JSON
        if (isset($data['images']) && is_array($data['images'])) {
            $data['images'] = wp_json_encode($data['images']);
        } else {
            $data['images'] = wp_json_encode(array());
        }
        
        // Garantir que todos os campos estejam presentes
        $insert_data = array(
            'name' => sanitize_text_field($data['name']),
            'description' => wp_kses_post($data['description']),
            'images' => $data['images'],
            'area_id' => intval($data['area_id']),
            'is_active' => isset($data['is_active']) ? 1 : 0,
        );
        
        // Log para depuração
        error_log('Tentando inserir no banco: ' . print_r($insert_data, true));
        
        // Inserir no banco de dados
        $result = $wpdb->insert(
            $this->saloons_table,
            $insert_data,
            array('%s', '%s', '%s', '%d', '%d')
        );
        
        if ($result === false) {
            // Log do erro para depuração
            error_log('Erro SQL ao inserir: ' . $wpdb->last_error);
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    // Atualizar salão
    public function update_saloon($id, $data) {
        global $wpdb;
        
        // Certificar que imagens estão em formato JSON
        if (isset($data['images']) && is_array($data['images'])) {
            $data['images'] = wp_json_encode($data['images']);
        } else {
            $data['images'] = wp_json_encode(array());
        }
        
        // Garantir que todos os campos estejam presentes
        $update_data = array(
            'name' => sanitize_text_field($data['name']),
            'description' => wp_kses_post($data['description']),
            'images' => $data['images'],
            'area_id' => intval($data['area_id']),
            'is_active' => isset($data['is_active']) ? 1 : 0,
        );
        
        // Log para depuração
        error_log('Tentando atualizar no banco: ' . print_r($update_data, true));
        
        // Atualizar no banco de dados
        $result = $wpdb->update(
            $this->saloons_table,
            $update_data,
            array('id' => $id),
            array('%s', '%s', '%s', '%d', '%d'),
            array('%d')
        );
        
        if ($result === false) {
            // Log do erro para depuração
            error_log('Erro SQL ao atualizar: ' . $wpdb->last_error);
            return false;
        }
        
        return true;
    }
    
    // Excluir salão
    public function delete_saloon($id) {
        global $wpdb;
        
        // Excluir do banco de dados
        $result = $wpdb->delete(
            $this->saloons_table,
            array('id' => $id),
            array('%d')
        );
        
        if ($result === false) {
            error_log('Erro SQL ao excluir: ' . $wpdb->last_error);
            return false;
        }
        
        return true;
    }
    
    // Obter salão por ID
    public function get_saloon($id) {
        global $wpdb;
        
        // Buscar do banco de dados
        $saloon = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT s.*, a.name as area_name FROM {$this->saloons_table} s LEFT JOIN {$wpdb->prefix}zuzunely_areas a ON s.area_id = a.id WHERE s.id = %d",
                $id
            ),
            ARRAY_A
        );
        
        // Deserializar imagens
        if ($saloon && isset($saloon['images']) && !empty($saloon['images'])) {
            $decoded = json_decode($saloon['images'], true);
            $saloon['images'] = (is_array($decoded)) ? $decoded : array();
        } else if ($saloon) {
            $saloon['images'] = array();
        }
        
        return $saloon;
    }
    
    // Obter todos os salões
    public function get_saloons($args = array()) {
        global $wpdb;
        
        // Argumentos padrão
        $defaults = array(
            'number' => 20,
            'offset' => 0,
            'orderby' => 'id',
            'order' => 'DESC',
            'include_inactive' => false,
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // Log para depuração
        error_log('Obtendo salões com args: ' . print_r($args, true));
        
        // Condição para salões ativos/inativos
        $where = '';
        if (!$args['include_inactive']) {
            $where = "WHERE is_active = 1";
        }
        
        // Validar campos de ordenação para evitar injeção SQL
        $valid_orderby_fields = array('id', 'name', 'is_active', 'area_id', 'created_at', 'updated_at');
        $orderby = in_array($args['orderby'], $valid_orderby_fields) ? $args['orderby'] : 'id';
        
        $valid_order = array('ASC', 'DESC');
        $order = in_array(strtoupper($args['order']), $valid_order) ? strtoupper($args['order']) : 'DESC';
        
        // Consulta SQL básica
        $sql = "SELECT s.*, a.name as area_name FROM {$this->saloons_table} s LEFT JOIN {$wpdb->prefix}zuzunely_areas a ON s.area_id = a.id $where ORDER BY s.$orderby $order LIMIT %d, %d";
        
        // Log da consulta SQL para depuração
        error_log('SQL para obter salões: ' . $wpdb->prepare($sql, $args['offset'], $args['number']));
        
        // Executar consulta
        $saloons = $wpdb->get_results(
            $wpdb->prepare($sql, $args['offset'], $args['number']),
            ARRAY_A
        );
        
        // Log de dados obtidos
        error_log('Total de salões obtidos: ' . count($saloons));
        
        // Deserializar imagens para cada salão
        if ($saloons) {
            foreach ($saloons as &$saloon) {
                if (isset($saloon['images']) && !empty($saloon['images'])) {
                    $decoded = json_decode($saloon['images'], true);
                    $saloon['images'] = (is_array($decoded)) ? $decoded : array();
                } else {
                    $saloon['images'] = array();
                }
            }
        }
        
        return $saloons;
    }
    
    // Contar total de salões
    public function count_saloons($include_inactive = false) {
        global $wpdb;
        
        // Condição para salões ativos/inativos
        $where = '';
        if (!$include_inactive) {
            $where = "WHERE is_active = 1";
        }
        
        // Contar total
        return (int) $wpdb->get_var("SELECT COUNT(id) FROM {$this->saloons_table} $where");
    }
}