<?php
/**
 * Classe para gerenciar operações de banco de dados relacionadas aos bloqueios
 */

if (!defined('ABSPATH')) {
    exit;
}

class Zuzunely_Blocks_DB {
    
    // Nome da tabela de bloqueios
    private $blocks_table;
    
    // Tipos de bloqueio
    const TYPE_TABLE = 'table';
    const TYPE_SALOON = 'saloon';
    const TYPE_RESTAURANT = 'restaurant';
    
    // Construtor
    public function __construct() {
        global $wpdb;
        
        // Definir nome da tabela
        $this->blocks_table = $wpdb->prefix . 'zuzunely_blocks';
    }
    
    // Criar tabela de bloqueios
    public function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Verificar se a tabela já existe
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->blocks_table}'") === $this->blocks_table;
        
        // Log de verificação
        error_log('Verificando tabela de bloqueios: ' . ($table_exists ? 'Existe' : 'Não existe'));
        
        // Se a tabela não existir, criar
        if (!$table_exists) {
            // SQL para criar tabela de bloqueios
            $sql = "CREATE TABLE {$this->blocks_table} (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                block_type varchar(20) NOT NULL,
                reference_id mediumint(9) DEFAULT 0,
                start_date date NOT NULL,
                end_date date NOT NULL,
                start_time time NOT NULL,
                end_time time NOT NULL,
                reason text,
                is_active tinyint(1) DEFAULT 1,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (id)
            ) $charset_collate;";
            
            // Usar dbDelta para executar SQL seguro
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            $result = dbDelta($sql);
            
            // Log para facilitar depuração
            error_log('Tabela de bloqueios criada: ' . $this->blocks_table);
            error_log('Resultado dbDelta: ' . print_r($result, true));
            
            // Verificar se a tabela foi criada
            $table_exists_after = $wpdb->get_var("SHOW TABLES LIKE '{$this->blocks_table}'") === $this->blocks_table;
            error_log('Tabela criada com sucesso? ' . ($table_exists_after ? 'Sim' : 'Não'));
            
            // Tentativa direta se dbDelta falhar
            if (!$table_exists_after) {
                error_log('Tentando criar tabela diretamente com query...');
                $wpdb->query("CREATE TABLE IF NOT EXISTS {$this->blocks_table} (
                    id mediumint(9) NOT NULL AUTO_INCREMENT,
                    block_type varchar(20) NOT NULL,
                    reference_id mediumint(9) DEFAULT 0,
                    start_date date NOT NULL,
                    end_date date NOT NULL,
                    start_time time NOT NULL,
                    end_time time NOT NULL,
                    reason text,
                    is_active tinyint(1) DEFAULT 1,
                    created_at datetime DEFAULT CURRENT_TIMESTAMP,
                    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY  (id)
                ) $charset_collate;");
                
                // Verificar novamente
                $table_exists_after_direct = $wpdb->get_var("SHOW TABLES LIKE '{$this->blocks_table}'") === $this->blocks_table;
                error_log('Tabela criada após tentativa direta? ' . ($table_exists_after_direct ? 'Sim' : 'Não'));
            }
        } else {
            error_log('Tabela de bloqueios já existe, ignorando criação.');
        }
    }
    
    // Obter nome da tabela de bloqueios
    public function get_blocks_table() {
        return $this->blocks_table;
    }
    
    // Inserir bloqueio
    public function insert_block($data) {
        global $wpdb;
        
        // Validar dados obrigatórios
        if (empty($data['block_type']) || empty($data['start_date']) || empty($data['end_date']) || 
            empty($data['start_time']) || empty($data['end_time'])) {
            error_log('Dados obrigatórios ausentes para inserir bloqueio');
            return false;
        }
        
        // Validar tipo de bloqueio
        if (!in_array($data['block_type'], array(self::TYPE_TABLE, self::TYPE_SALOON, self::TYPE_RESTAURANT))) {
            error_log('Tipo de bloqueio inválido: ' . $data['block_type']);
            return false;
        }
        
        // Validar reference_id para tipos que precisam
        if (($data['block_type'] == self::TYPE_TABLE || $data['block_type'] == self::TYPE_SALOON) && empty($data['reference_id'])) {
            error_log('ID de referência obrigatório para bloqueio de mesa ou salão');
            return false;
        }
        
        // Garantir que todos os campos estejam presentes
        $insert_data = array(
            'block_type' => sanitize_text_field($data['block_type']),
            'reference_id' => intval($data['reference_id']),
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'reason' => isset($data['reason']) ? sanitize_textarea_field($data['reason']) : '',
            'is_active' => isset($data['is_active']) ? 1 : 0,
        );
        
        // Log para depuração
        error_log('Tentando inserir bloqueio no banco: ' . print_r($insert_data, true));
        
        // Inserir no banco de dados
        $result = $wpdb->insert(
            $this->blocks_table,
            $insert_data,
            array('%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d')
        );
        
        if ($result === false) {
            // Log do erro para depuração
            error_log('Erro SQL ao inserir bloqueio: ' . $wpdb->last_error);
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    // Atualizar bloqueio
    public function update_block($id, $data) {
        global $wpdb;
        
        // Validar dados obrigatórios
        if (empty($data['block_type']) || empty($data['start_date']) || empty($data['end_date']) || 
            empty($data['start_time']) || empty($data['end_time'])) {
            error_log('Dados obrigatórios ausentes para atualizar bloqueio');
            return false;
        }
        
        // Validar tipo de bloqueio
        if (!in_array($data['block_type'], array(self::TYPE_TABLE, self::TYPE_SALOON, self::TYPE_RESTAURANT))) {
            error_log('Tipo de bloqueio inválido: ' . $data['block_type']);
            return false;
        }
        
        // Validar reference_id para tipos que precisam
        if (($data['block_type'] == self::TYPE_TABLE || $data['block_type'] == self::TYPE_SALOON) && empty($data['reference_id'])) {
            error_log('ID de referência obrigatório para bloqueio de mesa ou salão');
            return false;
        }
        
        // Garantir que todos os campos estejam presentes
        $update_data = array(
            'block_type' => sanitize_text_field($data['block_type']),
            'reference_id' => intval($data['reference_id']),
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'reason' => isset($data['reason']) ? sanitize_textarea_field($data['reason']) : '',
            'is_active' => isset($data['is_active']) ? 1 : 0,
        );
        
        // Log para depuração
        error_log('Tentando atualizar bloqueio no banco: ' . print_r($update_data, true));
        
        // Atualizar no banco de dados
        $result = $wpdb->update(
            $this->blocks_table,
            $update_data,
            array('id' => $id),
            array('%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d'),
            array('%d')
        );
        
        if ($result === false) {
            // Log do erro para depuração
            error_log('Erro SQL ao atualizar bloqueio: ' . $wpdb->last_error);
            return false;
        }
        
        return true;
    }
    
    // Excluir bloqueio
    public function delete_block($id) {
        global $wpdb;
        
        // Excluir do banco de dados
        $result = $wpdb->delete(
            $this->blocks_table,
            array('id' => $id),
            array('%d')
        );
        
        if ($result === false) {
            error_log('Erro SQL ao excluir bloqueio: ' . $wpdb->last_error);
            return false;
        }
        
        return true;
    }
    
    // Obter bloqueio por ID
    public function get_block($id) {
        global $wpdb;
        
        // Buscar do banco de dados
        $block = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->blocks_table} WHERE id = %d", $id),
            ARRAY_A
        );
        
        return $block;
    }
    
    // Obter todos os bloqueios
    public function get_blocks($args = array()) {
        global $wpdb;
        
        // Argumentos padrão
        $defaults = array(
            'number' => 20,
            'offset' => 0,
            'orderby' => 'id',
            'order' => 'DESC',
            'include_inactive' => false,
            'block_type' => '',
            'reference_id' => 0,
            'date_range' => false,
            'start_date' => '',
            'end_date' => '',
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // Log para depuração
        error_log('Obtendo bloqueios com args: ' . print_r($args, true));
        
        // Iniciar condição WHERE
        $where = array();
        
        // Filtrar por status
        if (!$args['include_inactive']) {
            $where[] = "is_active = 1";
        }
        
        // Filtrar por tipo de bloqueio
        if (!empty($args['block_type'])) {
            $where[] = $wpdb->prepare("block_type = %s", $args['block_type']);
        }
        
        // Filtrar por ID de referência
        if (!empty($args['reference_id'])) {
            $where[] = $wpdb->prepare("reference_id = %d", $args['reference_id']);
        }
        
        // Filtrar por intervalo de datas
        if ($args['date_range'] && !empty($args['start_date']) && !empty($args['end_date'])) {
            $where[] = $wpdb->prepare(
                "(start_date <= %s AND end_date >= %s)",
                $args['end_date'],
                $args['start_date']
            );
        }
        
        // Montar cláusula WHERE completa
        $where_clause = !empty($where) ? "WHERE " . implode(' AND ', $where) : '';
        
        // Validar campos de ordenação para evitar injeção SQL
        $valid_orderby_fields = array('id', 'block_type', 'reference_id', 'start_date', 'end_date', 'is_active', 'created_at');
        $orderby = in_array($args['orderby'], $valid_orderby_fields) ? $args['orderby'] : 'id';
        
        $valid_order = array('ASC', 'DESC');
        $order = in_array(strtoupper($args['order']), $valid_order) ? strtoupper($args['order']) : 'DESC';
        
        // Consulta SQL para obter bloqueios com informações relacionadas
        $sql = "SELECT b.*, 
                CASE 
                    WHEN b.block_type = 'table' THEN t.name
                    WHEN b.block_type = 'saloon' THEN s.name
                    ELSE NULL
                END as reference_name
                FROM {$this->blocks_table} b
                LEFT JOIN {$wpdb->prefix}zuzunely_tables t ON b.reference_id = t.id AND b.block_type = 'table'
                LEFT JOIN {$wpdb->prefix}zuzunely_saloons s ON b.reference_id = s.id AND b.block_type = 'saloon'
                $where_clause 
                ORDER BY $orderby $order 
                LIMIT %d, %d";
        
        // Log da consulta SQL para depuração
        error_log('SQL para obter bloqueios: ' . $wpdb->prepare($sql, $args['offset'], $args['number']));
        
        // Executar consulta
        $blocks = $wpdb->get_results(
            $wpdb->prepare($sql, $args['offset'], $args['number']),
            ARRAY_A
        );
        
        // Log de dados obtidos
        error_log('Total de bloqueios obtidos: ' . count($blocks));
        
        return $blocks;
    }
    
    // Contar total de bloqueios
    public function count_blocks($args = array()) {
        global $wpdb;
        
        // Argumentos padrão
        $defaults = array(
            'include_inactive' => false,
            'block_type' => '',
            'reference_id' => 0,
            'date_range' => false,
            'start_date' => '',
            'end_date' => '',
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // Iniciar condição WHERE
        $where = array();
        
        // Filtrar por status
        if (!$args['include_inactive']) {
            $where[] = "is_active = 1";
        }
        
        // Filtrar por tipo de bloqueio
        if (!empty($args['block_type'])) {
            $where[] = $wpdb->prepare("block_type = %s", $args['block_type']);
        }
        
        // Filtrar por ID de referência
        if (!empty($args['reference_id'])) {
            $where[] = $wpdb->prepare("reference_id = %d", $args['reference_id']);
        }
        
        // Filtrar por intervalo de datas
        if ($args['date_range'] && !empty($args['start_date']) && !empty($args['end_date'])) {
            $where[] = $wpdb->prepare(
                "(start_date <= %s AND end_date >= %s)",
                $args['end_date'],
                $args['start_date']
            );
        }
        
        // Montar cláusula WHERE completa
        $where_clause = !empty($where) ? "WHERE " . implode(' AND ', $where) : '';
        
        // Contar total
        return (int) $wpdb->get_var("SELECT COUNT(id) FROM {$this->blocks_table} $where_clause");
    }
    
    // Verificar se há bloqueios em um período para uma mesa
    public function has_table_blocks($table_id, $date_start, $date_end, $time_start = '', $time_end = '') {
        global $wpdb;
        
        // Condições básicas: tipo de bloqueio e ID da mesa
        $where = array(
            $wpdb->prepare("(block_type = %s AND reference_id = %d)", self::TYPE_TABLE, $table_id),
            // Ou bloqueio de salão (verificar a que salão a mesa pertence)
            $wpdb->prepare(
                "(block_type = %s AND reference_id = (SELECT saloon_id FROM {$wpdb->prefix}zuzunely_tables WHERE id = %d))",
                self::TYPE_SALOON,
                $table_id
            ),
            // Ou bloqueio de todo o restaurante
            $wpdb->prepare("(block_type = %s)", self::TYPE_RESTAURANT),
        );
        
        // Condição de data
        $date_condition = $wpdb->prepare(
            "(start_date <= %s AND end_date >= %s)",
            $date_end,
            $date_start
        );
        
        // Adicionar condição de data
        $where[] = $date_condition;
        
        // Adicionar condição de horário, se especificado
        if (!empty($time_start) && !empty($time_end)) {
            $time_condition = $wpdb->prepare(
                "(start_time < %s AND end_time > %s)",
                $time_end,
                $time_start
            );
            $where[] = $time_condition;
        }
        
        // Somente bloqueios ativos
        $where[] = "is_active = 1";
        
        // Montar query final
        $sql = "SELECT COUNT(id) FROM {$this->blocks_table} WHERE " . implode(' AND ', $where);
        
        // Executar a query
        $count = (int) $wpdb->get_var($sql);
        
        return $count > 0;
    }
    
    // Listar bloqueios ativos para uma mesa em um período
    public function get_table_blocks($table_id, $date_start, $date_end) {
        global $wpdb;
        
        // Condições básicas: tipo de bloqueio e ID da mesa
        $where = array(
            $wpdb->prepare(
                "((block_type = %s AND reference_id = %d) OR 
                 (block_type = %s AND reference_id = (SELECT saloon_id FROM {$wpdb->prefix}zuzunely_tables WHERE id = %d)) OR
                 (block_type = %s))",
                self::TYPE_TABLE,
                $table_id,
                self::TYPE_SALOON,
                $table_id,
                self::TYPE_RESTAURANT
            )
        );
        
        // Condição de data
        $where[] = $wpdb->prepare(
            "(start_date <= %s AND end_date >= %s)",
            $date_end,
            $date_start
        );
        
        // Somente bloqueios ativos
        $where[] = "is_active = 1";
        
        // Montar query final
        $sql = "SELECT * FROM {$this->blocks_table} WHERE " . implode(' AND ', $where) . " ORDER BY start_date ASC, start_time ASC";
        
        // Executar a query
        $blocks = $wpdb->get_results($sql, ARRAY_A);
        
        return $blocks;
    }
    
    // Verificar conflitos de bloqueio
    public function check_block_conflicts($data, $exclude_id = 0) {
        global $wpdb;
        
        // Se for bloqueio de restaurante, não há conflitos a verificar
        if ($data['block_type'] === self::TYPE_RESTAURANT) {
            return array();
        }
        
        // Iniciar condições
        $where = array();
        
        // Condição de data
        $where[] = $wpdb->prepare(
            "(start_date <= %s AND end_date >= %s)",
            $data['end_date'],
            $data['start_date']
        );
        
        // Condição de horário
        $where[] = $wpdb->prepare(
            "(start_time < %s AND end_time > %s)",
            $data['end_time'],
            $data['start_time']
        );
        
        // Excluir o ID atual (se estiver editando)
        if ($exclude_id > 0) {
            $where[] = $wpdb->prepare("id != %d", $exclude_id);
        }
        
        // Somente bloqueios ativos
        $where[] = "is_active = 1";
        
        // Condições específicas para o tipo de bloqueio
        if ($data['block_type'] === self::TYPE_TABLE) {
            // Para mesa: bloqueios da mesma mesa ou do salão ao qual a mesa pertence ou do restaurante inteiro
            $where[] = $wpdb->prepare(
                "((block_type = %s AND reference_id = %d) OR 
                (block_type = %s AND reference_id = (SELECT saloon_id FROM {$wpdb->prefix}zuzunely_tables WHERE id = %d)) OR
                (block_type = %s))",
                self::TYPE_TABLE,
                $data['reference_id'],
                self::TYPE_SALOON,
                $data['reference_id'],
                self::TYPE_RESTAURANT
            );
        } else if ($data['block_type'] === self::TYPE_SALOON) {
            // Para salão: bloqueios do mesmo salão ou do restaurante inteiro
            $where[] = $wpdb->prepare(
                "((block_type = %s AND reference_id = %d) OR (block_type = %s))",
                self::TYPE_SALOON,
                $data['reference_id'],
                self::TYPE_RESTAURANT
            );
        }
        
        // Montar query final
        $sql = "SELECT * FROM {$this->blocks_table} WHERE " . implode(' AND ', $where);
        
        // Executar a query
        $conflicts = $wpdb->get_results($sql, ARRAY_A);
        
        return $conflicts;
    }
}