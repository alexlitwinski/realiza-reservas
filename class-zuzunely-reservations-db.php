<?php
/**
 * Classe para gerenciar operações de banco de dados relacionadas às reservas
 */

if (!defined('ABSPATH')) {
    exit;
}

class Zuzunely_Reservations_DB {
    
    // Nome da tabela de reservas
    private $reservations_table;
    
    // Status de reserva
    const STATUS_PENDING = 'pending';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_NO_SHOW = 'no_show';
    
    // Construtor
    public function __construct() {
        global $wpdb;
        
        // Definir nome da tabela
        $this->reservations_table = $wpdb->prefix . 'zuzunely_reservations';
    }
    
    // Criar tabela de reservas
    public function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Verificar se a tabela já existe
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->reservations_table}'") === $this->reservations_table;
        
        // Log de verificação
        Zuzunely_Logger::debug('Verificando tabela de reservas: ' . ($table_exists ? 'Existe' : 'Não existe'));
        
        // Se a tabela não existir, criar
        if (!$table_exists) {
            // SQL para criar tabela de reservas
            $sql = "CREATE TABLE {$this->reservations_table} (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                table_id mediumint(9) NOT NULL,
                customer_name varchar(255) NOT NULL,
                customer_phone varchar(50) NOT NULL,
                customer_email varchar(100) NOT NULL,
                guests_count int(3) NOT NULL DEFAULT 1,
                reservation_date date NOT NULL,
                reservation_time time NOT NULL,
                duration int(5) NOT NULL DEFAULT 60,
                status varchar(20) NOT NULL DEFAULT 'pending',
                notes text,
                override_rules tinyint(1) DEFAULT 0,
                is_active tinyint(1) DEFAULT 1,
                reminder_sent tinyint(1) DEFAULT 0,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY table_datetime (table_id, reservation_date, reservation_time)
            ) $charset_collate;";
            
            // Usar dbDelta para executar SQL seguro
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            $result = dbDelta($sql);
            
            // Log para facilitar depuração
            Zuzunely_Logger::info('Tabela de reservas criada: ' . $this->reservations_table);
            Zuzunely_Logger::debug('Resultado dbDelta: ', $result);
            
            // Verificar se a tabela foi criada
            $table_exists_after = $wpdb->get_var("SHOW TABLES LIKE '{$this->reservations_table}'") === $this->reservations_table;
            Zuzunely_Logger::info('Tabela criada com sucesso? ' . ($table_exists_after ? 'Sim' : 'Não'));
            
            // Tentativa direta se dbDelta falhar
            if (!$table_exists_after) {
                Zuzunely_Logger::warning('Tentando criar tabela diretamente com query...');
                $wpdb->query("CREATE TABLE IF NOT EXISTS {$this->reservations_table} (
                    id mediumint(9) NOT NULL AUTO_INCREMENT,
                    table_id mediumint(9) NOT NULL,
                    customer_name varchar(255) NOT NULL,
                    customer_phone varchar(50) NOT NULL,
                    customer_email varchar(100) NOT NULL,
                    guests_count int(3) NOT NULL DEFAULT 1,
                    reservation_date date NOT NULL,
                    reservation_time time NOT NULL,
                    duration int(5) NOT NULL DEFAULT 60,
                    status varchar(20) NOT NULL DEFAULT 'pending',
                    notes text,
                    override_rules tinyint(1) DEFAULT 0,
                    is_active tinyint(1) DEFAULT 1,
                    reminder_sent tinyint(1) DEFAULT 0,
                    created_at datetime DEFAULT CURRENT_TIMESTAMP,
                    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY  (id),
                    KEY table_datetime (table_id, reservation_date, reservation_time)
                ) $charset_collate;");
                
                // Verificar novamente
                $table_exists_after_direct = $wpdb->get_var("SHOW TABLES LIKE '{$this->reservations_table}'") === $this->reservations_table;
                Zuzunely_Logger::info('Tabela criada após tentativa direta? ' . ($table_exists_after_direct ? 'Sim' : 'Não'));
            }
        } else {
            Zuzunely_Logger::debug('Tabela de reservas já existe, ignorando criação.');
        }
    }
    
    // Obter nome da tabela de reservas
    public function get_reservations_table() {
        return $this->reservations_table;
    }
    
    /**
     * Inserir reserva - VERSÃO MODIFICADA PARA VALIDAÇÃO
     */
    public function insert_reservation($data) {
        global $wpdb;
        
        Zuzunely_Logger::debug('Iniciando inserção de reserva', $data);
        
        // Verificar se a tabela existe
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->reservations_table}'") === $this->reservations_table;
        if (!$table_exists) {
            Zuzunely_Logger::error("Tabela de reservas não existe: {$this->reservations_table}");
            
            add_settings_error(
                'zuzunely_reservations',
                'zuzunely_table_error',
                sprintf(__('Tabela de reservas não existe: %s', 'zuzunely-restaurant'), $this->reservations_table),
                'error'
            );
            return false;
        }
        
        // Validar dados obrigatórios
        if (empty($data['table_id']) || empty($data['customer_name']) || empty($data['customer_phone']) || 
            empty($data['customer_email']) || empty($data['reservation_date']) || empty($data['reservation_time'])) {
            
            // Identificar campos faltantes
            $missing_fields = array();
            if (empty($data['table_id'])) $missing_fields[] = 'table_id';
            if (empty($data['customer_name'])) $missing_fields[] = 'customer_name';
            if (empty($data['customer_phone'])) $missing_fields[] = 'customer_phone';
            if (empty($data['customer_email'])) $missing_fields[] = 'customer_email';
            if (empty($data['reservation_date'])) $missing_fields[] = 'reservation_date';
            if (empty($data['reservation_time'])) $missing_fields[] = 'reservation_time';
            
            $missing_fields_str = implode(', ', $missing_fields);
            Zuzunely_Logger::error("Dados obrigatórios ausentes: {$missing_fields_str}");
            
            // Registrar erro
            add_settings_error(
                'zuzunely_reservations',
                'zuzunely_insert_error',
                sprintf(__('Dados obrigatórios ausentes: %s', 'zuzunely-restaurant'), $missing_fields_str),
                'error'
            );
            
            return false;
        }
        
        // Garantir que todos os campos estejam presentes
        $insert_data = array(
            'table_id' => intval($data['table_id']),
            'customer_name' => sanitize_text_field($data['customer_name']),
            'customer_phone' => sanitize_text_field($data['customer_phone']),
            'customer_email' => sanitize_email($data['customer_email']),
            'guests_count' => intval($data['guests_count']),
            'reservation_date' => $data['reservation_date'],
            'reservation_time' => $data['reservation_time'],
            'duration' => isset($data['duration']) ? intval($data['duration']) : $this->get_default_duration(),
            'status' => isset($data['status']) ? sanitize_text_field($data['status']) : self::STATUS_PENDING,
            'notes' => isset($data['notes']) ? sanitize_textarea_field($data['notes']) : '',
            'override_rules' => isset($data['override_rules']) ? 1 : 0,
            'is_active' => isset($data['is_active']) ? 1 : 0,
        );
        
        // VALIDAÇÃO DO NÚMERO MÁXIMO DE PESSOAS - ADICIONADA
        $settings = get_option('zuzunely_restaurant_settings', array());
        $max_guests = isset($settings['max_guests_per_reservation']) ? intval($settings['max_guests_per_reservation']) : 12;
        
        if ($insert_data['guests_count'] > $max_guests) {
            Zuzunely_Logger::error("Tentativa de inserir reserva com número de pessoas ({$insert_data['guests_count']}) acima do limite ({$max_guests})");
            
            add_settings_error(
                'zuzunely_reservations',
                'zuzunely_insert_error',
                sprintf(__('O número máximo de pessoas por reserva é %d', 'zuzunely-restaurant'), $max_guests),
                'error'
            );
            return false;
        }
        
        // Log para depuração
        Zuzunely_Logger::debug('Dados para inserção: ', $insert_data);
        
        add_settings_error(
            'zuzunely_reservations',
            'zuzunely_insert_data',
            'Dados para inserção: <pre>' . print_r($insert_data, true) . '</pre>',
            'info'
        );
        
        // Inserir no banco de dados
        $result = $wpdb->insert(
            $this->reservations_table,
            $insert_data,
            array('%d', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%d', '%d')
        );
        
        if ($result === false) {
            // Log do erro para depuração
            Zuzunely_Logger::error("Erro SQL ao inserir reserva: {$wpdb->last_error}");
            
            add_settings_error(
                'zuzunely_reservations',
                'zuzunely_insert_error',
                sprintf(__('Erro SQL ao inserir reserva: %s', 'zuzunely-restaurant'), $wpdb->last_error),
                'error'
            );
            return false;
        }
        
        $insert_id = $wpdb->insert_id;
        
        if (!$insert_id) {
            Zuzunely_Logger::error("Inserção falhou - não foi obtido ID: {$wpdb->last_error}");
            
            add_settings_error(
                'zuzunely_reservations',
                'zuzunely_insert_error',
                sprintf(__('Inserção falhou - não foi obtido ID: %s', 'zuzunely-restaurant'), $wpdb->last_error),
                'error'
            );
            return false;
        }
        
        Zuzunely_Logger::info("Reserva inserida com sucesso. ID: {$insert_id}");
        
        add_settings_error(
            'zuzunely_reservations',
            'zuzunely_insert_success',
            sprintf(__('Reserva inserida com sucesso. ID: %d', 'zuzunely-restaurant'), $insert_id),
            'success'
        );
        
        return $insert_id;
    }
    
    // Atualizar reserva
    public function update_reservation($id, $data) {
        global $wpdb;
        
        Zuzunely_Logger::debug("Iniciando atualização da reserva ID: {$id}", $data);
        
        // Validar dados obrigatórios
        if (empty($data['table_id']) || empty($data['customer_name']) || empty($data['customer_phone']) || 
            empty($data['customer_email']) || empty($data['reservation_date']) || empty($data['reservation_time'])) {
            
            Zuzunely_Logger::error("Dados obrigatórios ausentes para atualizar reserva ID: {$id}");
            
            add_settings_error(
                'zuzunely_reservations',
                'zuzunely_update_error',
                __('Dados obrigatórios ausentes para atualizar reserva', 'zuzunely-restaurant'),
                'error'
            );
            return false;
        }
        
        // Garantir que todos os campos estejam presentes
        $update_data = array(
            'table_id' => intval($data['table_id']),
            'customer_name' => sanitize_text_field($data['customer_name']),
            'customer_phone' => sanitize_text_field($data['customer_phone']),
            'customer_email' => sanitize_email($data['customer_email']),
            'guests_count' => intval($data['guests_count']),
            'reservation_date' => $data['reservation_date'],
            'reservation_time' => $data['reservation_time'],
            'duration' => isset($data['duration']) ? intval($data['duration']) : $this->get_default_duration(),
            'status' => isset($data['status']) ? sanitize_text_field($data['status']) : self::STATUS_PENDING,
            'notes' => isset($data['notes']) ? sanitize_textarea_field($data['notes']) : '',
            'override_rules' => isset($data['override_rules']) ? 1 : 0,
            'is_active' => isset($data['is_active']) ? 1 : 0,
        );
        
        // VALIDAÇÃO DO NÚMERO MÁXIMO DE PESSOAS - ADICIONADA
        $settings = get_option('zuzunely_restaurant_settings', array());
        $max_guests = isset($settings['max_guests_per_reservation']) ? intval($settings['max_guests_per_reservation']) : 12;
        
        if ($update_data['guests_count'] > $max_guests) {
            Zuzunely_Logger::error("Tentativa de atualizar reserva com número de pessoas ({$update_data['guests_count']}) acima do limite ({$max_guests})");
            
            add_settings_error(
                'zuzunely_reservations',
                'zuzunely_update_error',
                sprintf(__('O número máximo de pessoas por reserva é %d', 'zuzunely-restaurant'), $max_guests),
                'error'
            );
            return false;
        }
        
        // Log para depuração
        Zuzunely_Logger::debug("Dados para atualização da reserva ID {$id}: ", $update_data);
        
        add_settings_error(
            'zuzunely_reservations',
            'zuzunely_update_data',
            'Dados para atualização: <pre>' . print_r($update_data, true) . '</pre>',
            'info'
        );
        
        // Atualizar no banco de dados
        $result = $wpdb->update(
            $this->reservations_table,
            $update_data,
            array('id' => $id),
            array('%d', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%d', '%d'),
            array('%d')
        );
        
        if ($result === false) {
            // Log do erro para depuração
            Zuzunely_Logger::error("Erro SQL ao atualizar reserva ID {$id}: {$wpdb->last_error}");
            
            add_settings_error(
                'zuzunely_reservations',
                'zuzunely_update_error',
                sprintf(__('Erro SQL ao atualizar reserva: %s', 'zuzunely-restaurant'), $wpdb->last_error),
                'error'
            );
            return false;
        }
        
        Zuzunely_Logger::info("Reserva ID {$id} atualizada com sucesso");
        
        return true;
    }
    
    // Excluir reserva
    public function delete_reservation($id) {
        global $wpdb;
        
        Zuzunely_Logger::debug("Iniciando exclusão da reserva ID: {$id}");
        
        // Excluir do banco de dados
        $result = $wpdb->delete(
            $this->reservations_table,
            array('id' => $id),
            array('%d')
        );
        
        if ($result === false) {
            Zuzunely_Logger::error("Erro SQL ao excluir reserva ID {$id}: {$wpdb->last_error}");
            return false;
        }
        
        Zuzunely_Logger::info("Reserva ID {$id} excluída com sucesso");
        
        return true;
    }
    
    // Obter reserva por ID
    public function get_reservation($id) {
        global $wpdb;
        
        // Buscar do banco de dados
        $reservation = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->reservations_table} WHERE id = %d", $id),
            ARRAY_A
        );
        
        return $reservation;
    }
    
    // Obter todas as reservas
    public function get_reservations($args = array()) {
        global $wpdb;
        
        // Argumentos padrão
        $defaults = array(
            'number' => 20,
            'offset' => 0,
            'orderby' => 'reservation_date',
            'order' => 'DESC',
            'include_inactive' => false,
            'table_id' => 0,
            'status' => '',
            'date' => '',
            'date_range' => false,
            'date_start' => '',
            'date_end' => '',
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // Log para depuração
        Zuzunely_Logger::debug('Obtendo reservas com args: ', $args);
        
        // Iniciar condição WHERE
        $where = array();
        
        // Filtrar por status
        if (!$args['include_inactive']) {
            $where[] = "r.is_active = 1";
        }
        
        // Filtrar por mesa
        if (!empty($args['table_id'])) {
            $where[] = $wpdb->prepare("r.table_id = %d", $args['table_id']);
        }
        
        // Filtrar por status de reserva
        if (!empty($args['status'])) {
            $where[] = $wpdb->prepare("r.status = %s", $args['status']);
        }
        
        // Filtrar por data específica
        if (!empty($args['date'])) {
            $where[] = $wpdb->prepare("r.reservation_date = %s", $args['date']);
        }
        
        // Filtrar por intervalo de datas
        if ($args['date_range'] && !empty($args['date_start']) && !empty($args['date_end'])) {
            $where[] = $wpdb->prepare(
                "(r.reservation_date BETWEEN %s AND %s)",
                $args['date_start'],
                $args['date_end']
            );
        }
        
        // Montar cláusula WHERE completa
        $where_clause = !empty($where) ? "WHERE " . implode(' AND ', $where) : '';
        
        // Validar campos de ordenação para evitar injeção SQL
        $valid_orderby_fields = array('id', 'table_id', 'customer_name', 'guests_count', 'reservation_date', 'reservation_time', 'status', 'created_at');
        $orderby = in_array($args['orderby'], $valid_orderby_fields) ? 'r.'.$args['orderby'] : 'r.reservation_date';
        
        $valid_order = array('ASC', 'DESC');
        $order = in_array(strtoupper($args['order']), $valid_order) ? strtoupper($args['order']) : 'DESC';
        
        // Consulta SQL para obter reservas com informações relacionadas
        $sql = "SELECT r.*, t.name as table_name, s.name as saloon_name, s.id as saloon_id
                FROM {$this->reservations_table} r
                LEFT JOIN {$wpdb->prefix}zuzunely_tables t ON r.table_id = t.id
                LEFT JOIN {$wpdb->prefix}zuzunely_saloons s ON t.saloon_id = s.id
                $where_clause 
                ORDER BY $orderby $order 
                LIMIT %d, %d";
        
        // Log da consulta SQL para depuração
        Zuzunely_Logger::debug('SQL para obter reservas: ' . $wpdb->prepare($sql, $args['offset'], $args['number']));
        
        // Executar consulta
        $reservations = $wpdb->get_results(
            $wpdb->prepare($sql, $args['offset'], $args['number']),
            ARRAY_A
        );
        
        // Log de dados obtidos
        Zuzunely_Logger::debug('Total de reservas obtidas: ' . count($reservations));
        
        return $reservations;
    }
    
    // Contar total de reservas
    public function count_reservations($args = array()) {
        global $wpdb;
        
        // Argumentos padrão
        $defaults = array(
            'include_inactive' => false,
            'table_id' => 0,
            'status' => '',
            'date' => '',
            'date_range' => false,
            'date_start' => '',
            'date_end' => '',
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
        
        // Filtrar por status de reserva
        if (!empty($args['status'])) {
            $where[] = $wpdb->prepare("status = %s", $args['status']);
        }
        
        // Filtrar por data específica
        if (!empty($args['date'])) {
            $where[] = $wpdb->prepare("reservation_date = %s", $args['date']);
        }
        
        // Filtrar por intervalo de datas
        if ($args['date_range'] && !empty($args['date_start']) && !empty($args['date_end'])) {
            $where[] = $wpdb->prepare(
                "(reservation_date BETWEEN %s AND %s)",
                $args['date_start'],
                $args['date_end']
            );
        }
        
        // Montar cláusula WHERE completa
        $where_clause = !empty($where) ? "WHERE " . implode(' AND ', $where) : '';
        
        // Contar total
        return (int) $wpdb->get_var("SELECT COUNT(id) FROM {$this->reservations_table} $where_clause");
    }
    
    // Verificar se uma mesa está disponível para reserva em um horário específico
    public function is_table_available($table_id, $date, $time, $duration = null, $exclude_reservation_id = 0) {
        global $wpdb;
        
        // Se não informada a duração, usar padrão
        if ($duration === null) {
            $duration = $this->get_default_duration();
        }
        
        // Calcular horário de término corretamente
        $seconds = $duration * 60; // Converter minutos para segundos
        $end_time = date('H:i:s', strtotime($time) + $seconds);
        
        Zuzunely_Logger::debug("Verificando disponibilidade de mesa {$table_id} na data {$date} das {$time} até {$end_time} (duração: {$duration} minutos)");
        
        // 1. Verificar se há bloqueios na data/hora
        $blocks_db = new Zuzunely_Blocks_DB();
        if ($blocks_db->has_table_blocks($table_id, $date, $date, $time, $end_time)) {
            Zuzunely_Logger::debug("Mesa {$table_id} possui bloqueios na data {$date} de {$time} até {$end_time}");
            return false;
        }
        
        // 2. Verificar se está dentro do horário de funcionamento
        $availability_db = new Zuzunely_Availability_DB();
        $weekday = date('w', strtotime($date)); // 0 (domingo) a 6 (sábado)
        if (!$availability_db->is_table_available($table_id, $weekday, $time)) {
            Zuzunely_Logger::debug("Mesa {$table_id} não está disponível no dia {$weekday} às {$time}");
            return false;
        }
        
        // 3. Verificar se já há reservas no horário
        $exclusion_clause = "";
        if ($exclude_reservation_id > 0) {
            $exclusion_clause = $wpdb->prepare(" AND id != %d", $exclude_reservation_id);
        }
        
        // SQL para verificar sobreposição de horários
        $sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->reservations_table} 
            WHERE table_id = %d 
            AND reservation_date = %s 
            AND status IN ('pending', 'confirmed')
            AND (
                (reservation_time <= %s AND ADDTIME(reservation_time, SEC_TO_TIME(duration * 60)) > %s) OR
                (reservation_time < %s AND ADDTIME(reservation_time, SEC_TO_TIME(duration * 60)) >= %s) OR
                (reservation_time >= %s AND reservation_time < %s)
            )" . $exclusion_clause,
            $table_id,
            $date,
            $time, $time,
            $end_time, $end_time,
            $time, $end_time
        );
        
        Zuzunely_Logger::debug('SQL para verificar disponibilidade: ' . $sql);
        
        $reservation_count = (int) $wpdb->get_var($sql);
        
        if ($reservation_count > 0) {
            Zuzunely_Logger::debug("Mesa {$table_id} já possui {$reservation_count} reserva(s) na data {$date} de {$time} até {$end_time}");
        }
        
        return $reservation_count === 0;
    }
    
    // Obter duração padrão de reservas das configurações
    public function get_default_duration() {
        $options = get_option('zuzunely_restaurant_settings');
        $duration = isset($options['default_reservation_duration']) ? $options['default_reservation_duration'] : 60;
        return intval($duration);
    }
    
    // Obter mesas disponíveis para uma data/hora e número de pessoas - MÉTODO CORRIGIDO
    public function get_available_tables($date, $time, $guests_count, $ignore_rules = false) {
        global $wpdb;
        
        Zuzunely_Logger::debug("Buscando mesas disponíveis - Data: {$date}, Hora: {$time}, Pessoas: {$guests_count}, Ignorar regras: " . ($ignore_rules ? 'Sim' : 'Não'));
        
        // Obter todas as mesas ativas com informação dos salões e áreas
        $tables_sql = "SELECT t.*, s.name as saloon_name, s.area_id as saloon_area_id, a.name as area_name, s.id as saloon_id
                       FROM {$wpdb->prefix}zuzunely_tables t
                       LEFT JOIN {$wpdb->prefix}zuzunely_saloons s ON t.saloon_id = s.id
                       LEFT JOIN {$wpdb->prefix}zuzunely_areas a ON s.area_id = a.id
                       WHERE t.is_active = 1 AND s.is_active = 1
                       ORDER BY t.name";
        
        $tables = $wpdb->get_results($tables_sql, ARRAY_A);
        
        if (empty($tables)) {
            Zuzunely_Logger::debug("Nenhuma mesa encontrada");
            return array();
        }
        
        // Se estiver ignorando regras, retorna todas as mesas com capacidade suficiente
        if ($ignore_rules) {
            $available_tables = array();
            foreach ($tables as $table) {
                if ($table['capacity'] >= $guests_count) {
                    $available_tables[] = $table;
                }
            }
            Zuzunely_Logger::debug("Retornando " . count($available_tables) . " mesas (ignorando regras)");
            return $available_tables;
        }
        
        // Verificar disponibilidade de cada mesa
        $available_tables = array();
        $duration = $this->get_default_duration();
        
        foreach ($tables as $table) {
            // Verificar capacidade
            if ($table['capacity'] < $guests_count) {
                continue;
            }
            
            // Verificar disponibilidade
            if ($this->is_table_available($table['id'], $date, $time, $duration)) {
                $available_tables[] = $table;
            }
        }
        
        Zuzunely_Logger::debug("Retornando " . count($available_tables) . " mesas disponíveis");
        return $available_tables;
    }
    
    // Obter lista de status
    public static function get_status_list() {
        return array(
            self::STATUS_PENDING => __('Pendente', 'zuzunely-restaurant'),
            self::STATUS_CONFIRMED => __('Confirmada', 'zuzunely-restaurant'),
            self::STATUS_COMPLETED => __('Concluída', 'zuzunely-restaurant'),
            self::STATUS_CANCELLED => __('Cancelada', 'zuzunely-restaurant'),
            self::STATUS_NO_SHOW => __('Não Compareceu', 'zuzunely-restaurant')
        );
    }
}