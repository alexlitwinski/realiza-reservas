<?php
/**
 * Classe para gerenciar operações de banco de dados relacionadas às mesas
 */

if (!defined('ABSPATH')) {
    exit;
}

class Zuzunely_Tables_DB {
    
    // Nome da tabela de mesas
    private $tables_table;
    
    // Construtor
    public function __construct() {
        global $wpdb;
        
        // Definir nome da tabela
        $this->tables_table = $wpdb->prefix . 'zuzunely_tables';
    }
    
    // Criar tabela de mesas
    public function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // SQL para criar tabela de mesas
        $sql = "CREATE TABLE {$this->tables_table} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text NOT NULL,
            saloon_id mediumint(9) NOT NULL,
            capacity int(5) NOT NULL DEFAULT 1,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        
        // Usar dbDelta para executar SQL seguro
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Log para facilitar depuração
        error_log('Tabela de mesas criada: ' . $this->tables_table);
    }
    
    // Obter nome da tabela de mesas
    public function get_tables_table() {
        return $this->tables_table;
    }
    
    // Inserir mesa
    public function insert_table($data) {
        global $wpdb;
        
        // Validar dados obrigatórios
        if (empty($data['name']) || empty($data['saloon_id'])) {
            error_log('Dados obrigatórios ausentes para inserir mesa');
            return false;
        }
        
        // Garantir que todos os campos estejam presentes
        $insert_data = array(
            'name' => sanitize_text_field($data['name']),
            'description' => wp_kses_post($data['description']),
            'saloon_id' => intval($data['saloon_id']),
            'capacity' => intval($data['capacity']),
            'is_active' => isset($data['is_active']) ? 1 : 0,
        );
        
        // Log para depuração
        error_log('Tentando inserir mesa no banco: ' . print_r($insert_data, true));
        
        // Inserir no banco de dados
        $result = $wpdb->insert(
            $this->tables_table,
            $insert_data,
            array('%s', '%s', '%d', '%d', '%d')
        );
        
        if ($result === false) {
            // Log do erro para depuração
            error_log('Erro SQL ao inserir mesa: ' . $wpdb->last_error);
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    // Atualizar mesa
    public function update_table($id, $data) {
        global $wpdb;
        
        // Validar dados obrigatórios
        if (empty($data['name']) || empty($data['saloon_id'])) {
            error_log('Dados obrigatórios ausentes para atualizar mesa');
            return false;
        }
        
        // Garantir que todos os campos estejam presentes
        $update_data = array(
            'name' => sanitize_text_field($data['name']),
            'description' => wp_kses_post($data['description']),
            'saloon_id' => intval($data['saloon_id']),
            'capacity' => intval($data['capacity']),
            'is_active' => isset($data['is_active']) ? 1 : 0,
        );
        
        // Log para depuração
        error_log('Tentando atualizar mesa no banco: ' . print_r($update_data, true));
        
        // Atualizar no banco de dados
        $result = $wpdb->update(
            $this->tables_table,
            $update_data,
            array('id' => $id),
            array('%s', '%s', '%d', '%d', '%d'),
            array('%d')
        );
        
        if ($result === false) {
            // Log do erro para depuração
            error_log('Erro SQL ao atualizar mesa: ' . $wpdb->last_error);
            return false;
        }
        
        return true;
    }
    
    // Excluir mesa
    public function delete_table($id) {
        global $wpdb;
        
        // Excluir do banco de dados
        $result = $wpdb->delete(
            $this->tables_table,
            array('id' => $id),
            array('%d')
        );
        
        if ($result === false) {
            error_log('Erro SQL ao excluir mesa: ' . $wpdb->last_error);
            return false;
        }
        
        return true;
    }
    
    // Obter mesa por ID
    public function get_table($id) {
        global $wpdb;
        
        // Buscar do banco de dados
        $table = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->tables_table} WHERE id = %d", $id),
            ARRAY_A
        );
        
        return $table;
    }
    
    // Obter todas as mesas - MÉTODO CORRIGIDO COM is_internal
    public function get_tables($args = array()) {
        global $wpdb;
        
        // Argumentos padrão
        $defaults = array(
            'number' => 20,
            'offset' => 0,
            'orderby' => 'id',
            'order' => 'DESC',
            'include_inactive' => false,
            'saloon_id' => 0,
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // Log para depuração
        error_log('Obtendo mesas com args: ' . print_r($args, true));
        
        // Iniciar condição WHERE
        $where = array();
        
        // Filtrar por status
        if (!$args['include_inactive']) {
            $where[] = "t.is_active = 1";
        }
        
        // Filtrar por salão
        if (!empty($args['saloon_id'])) {
            $where[] = $wpdb->prepare("t.saloon_id = %d", $args['saloon_id']);
        }
        
        // Montar cláusula WHERE completa
        $where_clause = !empty($where) ? "WHERE " . implode(' AND ', $where) : '';
        
        // Validar campos de ordenação para evitar injeção SQL
        $valid_orderby_fields = array('id', 'name', 'saloon_id', 'capacity', 'is_active', 'created_at');
        $orderby = in_array($args['orderby'], $valid_orderby_fields) ? $args['orderby'] : 'id';
        
        $valid_order = array('ASC', 'DESC');
        $order = in_array(strtoupper($args['order']), $valid_order) ? strtoupper($args['order']) : 'DESC';
        
        // Consulta SQL básica - CORRIGIDA PARA INCLUIR is_internal
        $sql = "SELECT t.*, s.name as saloon_name, s.is_internal as saloon_is_internal, s.id as saloon_id
                FROM {$this->tables_table} t
                LEFT JOIN {$wpdb->prefix}zuzunely_saloons s ON t.saloon_id = s.id
                $where_clause 
                ORDER BY t.$orderby $order 
                LIMIT %d, %d";
        
        // Log da consulta SQL para depuração
        error_log('SQL para obter mesas: ' . $wpdb->prepare($sql, $args['offset'], $args['number']));
        
        // Executar consulta
        $tables = $wpdb->get_results(
            $wpdb->prepare($sql, $args['offset'], $args['number']),
            ARRAY_A
        );
        
        // Log de dados obtidos
        error_log('Total de mesas obtidas: ' . count($tables));
        
        return $tables;
    }
    
    // Contar total de mesas - MÉTODO CORRIGIDO
    public function count_tables($args = array()) {
        global $wpdb;
        
        // Argumentos padrão
        $defaults = array(
            'include_inactive' => false,
            'saloon_id' => 0,
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // Iniciar condição WHERE
        $where = array();
        
        // Filtrar por status
        if (!$args['include_inactive']) {
            $where[] = "t.is_active = 1";
        }
        
        // Filtrar por salão
        if (!empty($args['saloon_id'])) {
            $where[] = $wpdb->prepare("t.saloon_id = %d", $args['saloon_id']);
        }
        
        // Montar cláusula WHERE completa
        $where_clause = !empty($where) ? "WHERE " . implode(' AND ', $where) : '';
        
        // Consulta SQL para contar - CORRIGIDA PARA USAR JOIN
        $sql = "SELECT COUNT(t.id) FROM {$this->tables_table} t
                LEFT JOIN {$wpdb->prefix}zuzunely_saloons s ON t.saloon_id = s.id
                $where_clause";
        
        // Contar total
        return (int) $wpdb->get_var($sql);
    }
    
    // Obter mesas por salão
    public function get_tables_by_saloon($saloon_id, $include_inactive = false) {
        return $this->get_tables(array(
            'saloon_id' => $saloon_id,
            'include_inactive' => $include_inactive,
            'number' => 100, // Limite mais alto para obter todas as mesas do salão
        ));
    }
    
    // Contar mesas por salão
    public function count_tables_by_saloon($saloon_id, $include_inactive = false) {
        return $this->count_tables(array(
            'saloon_id' => $saloon_id,
            'include_inactive' => $include_inactive
        ));
    }
}