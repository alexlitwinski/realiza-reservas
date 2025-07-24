<?php
/**
 * Classe para gerenciar operações de banco de dados relacionadas às disponibilidades
 */

if (!defined('ABSPATH')) {
    exit;
}

class Zuzunely_Availability_DB {
    
    // Nome da tabela de disponibilidades
    private $availability_table;
    
    // Dias da semana
    const MONDAY = 1;
    const TUESDAY = 2;
    const WEDNESDAY = 3;
    const THURSDAY = 4;
    const FRIDAY = 5;
    const SATURDAY = 6;
    const SUNDAY = 0;
    
    // Construtor
    public function __construct() {
        global $wpdb;
        
        // Definir nome da tabela
        $this->availability_table = $wpdb->prefix . 'zuzunely_availability';
    }
    
    // Criar tabela de disponibilidades
    public function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Verificar se a tabela já existe
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->availability_table}'") === $this->availability_table;
        
        // Log de verificação
        error_log('Verificando tabela de disponibilidades: ' . ($table_exists ? 'Existe' : 'Não existe'));
        
        // Se a tabela não existir, criar
        if (!$table_exists) {
            // SQL para criar tabela de disponibilidades
            $sql = "CREATE TABLE {$this->availability_table} (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                table_id mediumint(9) NOT NULL,
                weekday tinyint(1) NOT NULL,
                start_time time NOT NULL,
                end_time time NOT NULL,
                is_active tinyint(1) DEFAULT 1,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY table_weekday (table_id, weekday)
            ) $charset_collate;";
            
            // Usar dbDelta para executar SQL seguro
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            $result = dbDelta($sql);
            
            // Log para facilitar depuração
            error_log('Tabela de disponibilidades criada: ' . $this->availability_table);
            error_log('Resultado dbDelta: ' . print_r($result, true));
            
            // Verificar se a tabela foi criada
            $table_exists_after = $wpdb->get_var("SHOW TABLES LIKE '{$this->availability_table}'") === $this->availability_table;
            error_log('Tabela criada com sucesso? ' . ($table_exists_after ? 'Sim' : 'Não'));
            
            // Tentativa direta se dbDelta falhar
            if (!$table_exists_after) {
                error_log('Tentando criar tabela diretamente com query...');
                $wpdb->query("CREATE TABLE IF NOT EXISTS {$this->availability_table} (
                    id mediumint(9) NOT NULL AUTO_INCREMENT,
                    table_id mediumint(9) NOT NULL,
                    weekday tinyint(1) NOT NULL,
                    start_time time NOT NULL,
                    end_time time NOT NULL,
                    is_active tinyint(1) DEFAULT 1,
                    created_at datetime DEFAULT CURRENT_TIMESTAMP,
                    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY  (id),
                    KEY table_weekday (table_id, weekday)
                ) $charset_collate;");
                
                // Verificar novamente
                $table_exists_after_direct = $wpdb->get_var("SHOW TABLES LIKE '{$this->availability_table}'") === $this->availability_table;
                error_log('Tabela criada após tentativa direta? ' . ($table_exists_after_direct ? 'Sim' : 'Não'));
            }
        } else {
            error_log('Tabela de disponibilidades já existe, ignorando criação.');
        }
    }
    
    // Obter nome da tabela de disponibilidades
    public function get_availability_table() {
        return $this->availability_table;
    }
    
    // Inserir disponibilidade
    public function insert_availability($data) {
        global $wpdb;
        
        // Validar dados obrigatórios
        if (empty($data['table_id']) || !isset($data['weekday']) || 
            empty($data['start_time']) || empty($data['end_time'])) {
            error_log('Dados obrigatórios ausentes para inserir disponibilidade');
            return false;
        }
        
        // Validar dia da semana
        if ($data['weekday'] < 0 || $data['weekday'] > 6) {
            error_log('Dia da semana inválido: ' . $data['weekday']);
            return false;
        }
        
        // Garantir que todos os campos estejam presentes
        $insert_data = array(
            'table_id' => intval($data['table_id']),
            'weekday' => intval($data['weekday']),
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'is_active' => isset($data['is_active']) ? 1 : 0,
        );
        
        // Log para depuração
        error_log('Tentando inserir disponibilidade no banco: ' . print_r($insert_data, true));
        
        // Inserir no banco de dados
        $result = $wpdb->insert(
            $this->availability_table,
            $insert_data,
            array('%d', '%d', '%s', '%s', '%d')
        );
        
        if ($result === false) {
            // Log do erro para depuração
            error_log('Erro SQL ao inserir disponibilidade: ' . $wpdb->last_error);
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    // Atualizar disponibilidade
    public function update_availability($id, $data) {
        global $wpdb;
        
        // Validar dados obrigatórios
        if (empty($data['table_id']) || !isset($data['weekday']) || 
            empty($data['start_time']) || empty($data['end_time'])) {
            error_log('Dados obrigatórios ausentes para atualizar disponibilidade');
            return false;
        }
        
        // Validar dia da semana
        if ($data['weekday'] < 0 || $data['weekday'] > 6) {
            error_log('Dia da semana inválido: ' . $data['weekday']);
            return false;
        }
        
        // Garantir que todos os campos estejam presentes
        $update_data = array(
            'table_id' => intval($data['table_id']),
            'weekday' => intval($data['weekday']),
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'is_active' => isset($data['is_active']) ? 1 : 0,
        );
        
        // Log para depuração
        error_log('Tentando atualizar disponibilidade no banco: ' . print_r($update_data, true));
        
        // Atualizar no banco de dados
        $result = $wpdb->update(
            $this->availability_table,
            $update_data,
            array('id' => $id),
            array('%d', '%d', '%s', '%s', '%d'),
            array('%d')
        );
        
        if ($result === false) {
            // Log do erro para depuração
            error_log('Erro SQL ao atualizar disponibilidade: ' . $wpdb->last_error);
            return false;
        }
        
        return true;
    }
    
    // Excluir disponibilidade
    public function delete_availability($id) {
        global $wpdb;
        
        // Excluir do banco de dados
        $result = $wpdb->delete(
            $this->availability_table,
            array('id' => $id),
            array('%d')
        );
        
        if ($result === false) {
            error_log('Erro SQL ao excluir disponibilidade: ' . $wpdb->last_error);
            return false;
        }
        
        return true;
    }
    
    // Excluir todas as disponibilidades de uma mesa
    public function delete_table_availability($table_id) {
        global $wpdb;
        
        // Excluir do banco de dados
        $result = $wpdb->delete(
            $this->availability_table,
            array('table_id' => $table_id),
            array('%d')
        );
        
        if ($result === false) {
            error_log('Erro SQL ao excluir disponibilidades da mesa: ' . $wpdb->last_error);
            return false;
        }
        
        return true;
    }
    
    // Obter disponibilidade por ID
    public function get_availability($id) {
        global $wpdb;
        
        // Buscar do banco de dados
        $availability = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->availability_table} WHERE id = %d", $id),
            ARRAY_A
        );
        
        return $availability;
    }
    
    // Obter todas as disponibilidades
    public function get_availabilities($args = array()) {
        global $wpdb;
        
        // Argumentos padrão
        $defaults = array(
            'number' => 20,
            'offset' => 0,
            'orderby' => 'id',
            'order' => 'DESC',
            'include_inactive' => false,
            'table_id' => 0,
            'weekday' => null,
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // Log para depuração
        error_log('Obtendo disponibilidades com args: ' . print_r($args, true));
        
        // Iniciar condição WHERE
        $where = array();
        
        // Filtrar por status
        if (!$args['include_inactive']) {
            $where[] = "is_active = 1";
        }
        
        // Filtrar por mesa
        if (!empty($args['table_id'])) {
            $where[] = $wpdb->prepare("table_id = %d", $args['table_id']);
        }
        
        // Filtrar por dia da semana
        if (isset($args['weekday']) && $args['weekday'] !== null) {
            $where[] = $wpdb->prepare("weekday = %d", $args['weekday']);
        }
        
        // Montar cláusula WHERE completa
        $where_clause = !empty($where) ? "WHERE " . implode(' AND ', $where) : '';
        
        // Validar campos de ordenação para evitar injeção SQL
        $valid_orderby_fields = array('id', 'table_id', 'weekday', 'start_time', 'end_time', 'is_active', 'created_at');
        $orderby = in_array($args['orderby'], $valid_orderby_fields) ? $args['orderby'] : 'id';
        
        $valid_order = array('ASC', 'DESC');
        $order = in_array(strtoupper($args['order']), $valid_order) ? strtoupper($args['order']) : 'DESC';
        
        // Consulta SQL para obter disponibilidades com informações relacionadas
        $sql = "SELECT a.*, t.name as table_name, s.name as saloon_name, s.id as saloon_id
                FROM {$this->availability_table} a
                LEFT JOIN {$wpdb->prefix}zuzunely_tables t ON a.table_id = t.id
                LEFT JOIN {$wpdb->prefix}zuzunely_saloons s ON t.saloon_id = s.id
                $where_clause 
                ORDER BY $orderby $order 
                LIMIT %d, %d";
        
        // Log da consulta SQL para depuração
        error_log('SQL para obter disponibilidades: ' . $wpdb->prepare($sql, $args['offset'], $args['number']));
        
        // Executar consulta
        $availabilities = $wpdb->get_results(
            $wpdb->prepare($sql, $args['offset'], $args['number']),
            ARRAY_A
        );
        
        // Log de dados obtidos
        error_log('Total de disponibilidades obtidas: ' . count($availabilities));
        
        return $availabilities;
    }
    
    // Contar total de disponibilidades
    public function count_availabilities($args = array()) {
        global $wpdb;
        
        // Argumentos padrão
        $defaults = array(
            'include_inactive' => false,
            'table_id' => 0,
            'weekday' => null,
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // Iniciar condição WHERE
        $where = array();
        
        // Filtrar por status
        if (!$args['include_inactive']) {
            $where[] = "is_active = 1";
        }
        
        // Filtrar por mesa
        if (!empty($args['table_id'])) {
            $where[] = $wpdb->prepare("table_id = %d", $args['table_id']);
        }
        
        // Filtrar por dia da semana
        if (isset($args['weekday']) && $args['weekday'] !== null) {
            $where[] = $wpdb->prepare("weekday = %d", $args['weekday']);
        }
        
        // Montar cláusula WHERE completa
        $where_clause = !empty($where) ? "WHERE " . implode(' AND ', $where) : '';
        
        // Contar total
        return (int) $wpdb->get_var("SELECT COUNT(id) FROM {$this->availability_table} $where_clause");
    }
    
    // Verificar se uma mesa está disponível em um determinado dia e hora
    public function is_table_available($table_id, $weekday, $time) {
        global $wpdb;
        
        $sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->availability_table} 
            WHERE table_id = %d 
            AND weekday = %d 
            AND %s >= start_time 
            AND %s <= end_time 
            AND is_active = 1",
            $table_id,
            $weekday,
            $time,
            $time
        );
        
        $count = (int) $wpdb->get_var($sql);
        
        return $count > 0;
    }
    
    // Obter disponibilidades de uma mesa
    public function get_table_availabilities($table_id) {
        return $this->get_availabilities(array(
            'table_id' => $table_id,
            'include_inactive' => true,
            'number' => 100, // Limite mais alto para obter todas as disponibilidades da mesa
            'orderby' => 'weekday',
            'order' => 'ASC'
        ));
    }
    
    // Copiar disponibilidades de uma mesa para outra
    public function copy_availabilities($source_table_id, $target_table_id) {
        global $wpdb;
        
        // Primeiro, obter todas as disponibilidades da mesa de origem
        $source_availabilities = $this->get_table_availabilities($source_table_id);
        
        if (empty($source_availabilities)) {
            return 0; // Nada para copiar
        }
        
        $copied_count = 0;
        
        // Para cada disponibilidade, criar uma cópia na mesa de destino
        foreach ($source_availabilities as $availability) {
            // Verificar se já existe configuração similar para evitar duplicatas
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->availability_table} 
                WHERE table_id = %d AND weekday = %d 
                AND ((start_time < %s AND end_time > %s) OR 
                     (start_time < %s AND end_time > %s) OR
                     (start_time >= %s AND end_time <= %s))",
                $target_table_id,
                $availability['weekday'],
                $availability['end_time'], $availability['start_time'],  // Caso 1: Nova disponibilidade começa antes e termina durante existente
                $availability['start_time'], $availability['start_time'], // Caso 2: Nova disponibilidade começa durante e termina depois da existente
                $availability['start_time'], $availability['end_time']    // Caso 3: Nova disponibilidade está completamente dentro da existente
            ));
            
            if ($exists > 0) {
                continue; // Já existe, pular
            }
            
            // Criar novo registro para a mesa de destino
            $data = array(
                'table_id' => $target_table_id,
                'weekday' => $availability['weekday'],
                'start_time' => $availability['start_time'],
                'end_time' => $availability['end_time'],
                'is_active' => $availability['is_active']
            );
            
            $result = $this->insert_availability($data);
            
            if ($result) {
                $copied_count++;
            }
        }
        
        return $copied_count;
    }
    
    // Copiar disponibilidades para todas as mesas de um salão
    public function copy_availabilities_to_saloon($source_table_id, $saloon_id) {
        global $wpdb;
        
        // CORREÇÃO: Obter mesas diretamente do banco
        $tables = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}zuzunely_tables 
                WHERE saloon_id = %d AND is_active = 1",
                $saloon_id
            ),
            ARRAY_A
        );
        
        error_log('CORREÇÃO: Mesas encontradas para o salão ' . $saloon_id . ': ' . count($tables));
        
        if (empty($tables)) {
            return 0; // Nenhuma mesa encontrada no salão
        }
        
        $total_copied = 0;
        
        // Para cada mesa, copiar as disponibilidades
        foreach ($tables as $table) {
            // Pular a mesa de origem
            if ($table['id'] == $source_table_id) {
                continue;
            }
            
            $copied = $this->copy_availabilities($source_table_id, $table['id']);
            $total_copied += $copied;
        }
        
        return $total_copied;
    }
    
    // Obter array de dias da semana para exibição
    public static function get_weekdays() {
        return array(
            self::MONDAY => __('Segunda-feira', 'zuzunely-restaurant'),
            self::TUESDAY => __('Terça-feira', 'zuzunely-restaurant'),
            self::WEDNESDAY => __('Quarta-feira', 'zuzunely-restaurant'),
            self::THURSDAY => __('Quinta-feira', 'zuzunely-restaurant'),
            self::FRIDAY => __('Sexta-feira', 'zuzunely-restaurant'),
            self::SATURDAY => __('Sábado', 'zuzunely-restaurant'),
            self::SUNDAY => __('Domingo', 'zuzunely-restaurant')
        );
    }
}