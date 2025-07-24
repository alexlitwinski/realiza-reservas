<?php
/**
 * Shortcode para reservas no frontend
 * 
 * Uso: [zuzunely_reservations]
 */

if (!defined('ABSPATH')) {
    exit;
}

class Zuzunely_Frontend_Reservations {
    
    public function __construct() {
        // Registrar shortcode
        add_shortcode('zuzunely_reservations', array($this, 'render_reservation_form'));
        
        // Processar formulário
        add_action('wp_ajax_zuzunely_frontend_reservation', array($this, 'process_frontend_reservation'));
        add_action('wp_ajax_nopriv_zuzunely_frontend_reservation', array($this, 'process_frontend_reservation'));
        
        // Buscar mesas disponíveis
        add_action('wp_ajax_zuzunely_search_available_tables', array($this, 'search_available_tables'));
        add_action('wp_ajax_nopriv_zuzunely_search_available_tables', array($this, 'search_available_tables'));
        
        // NOVO: Buscar horários disponíveis
        add_action('wp_ajax_zuzunely_get_available_times', array($this, 'get_available_times'));
        add_action('wp_ajax_nopriv_zuzunely_get_available_times', array($this, 'get_available_times'));
        
        // Enqueue scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    /**
     * NOVO MÉTODO: Buscar horários disponíveis via AJAX
     * VERSÃO DEBUG: Com logs detalhados para diagnosticar o problema
     */
    public function get_available_times() {
        // Log de início
        error_log('=== INICIO get_available_times ===');
        error_log('POST data: ' . print_r($_POST, true));
        
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'zuzunely_frontend_reservation')) {
            error_log('ERRO: Nonce inválido');
            wp_send_json_error(__('Erro de segurança', 'zuzunely-restaurant'));
        }
        
        $date = sanitize_text_field($_POST['date']);
        error_log('Data recebida: ' . $date);
        
        // Validar data
        if (empty($date)) {
            error_log('ERRO: Data vazia');
            wp_send_json_error(__('Data é obrigatória', 'zuzunely-restaurant'));
        }
        
        // Validar se a data não é no passado
        if (strtotime($date) < strtotime(date('Y-m-d'))) {
            error_log('ERRO: Data no passado');
            wp_send_json_error(__('A data selecionada não pode ser no passado', 'zuzunely-restaurant'));
        }
        
        try {
            // Obter configurações
            $settings = get_option('zuzunely_restaurant_settings', array());
            error_log('Configurações: ' . print_r($settings, true));
            
            $min_advance_hours = isset($settings['min_advance_time']) ? intval($settings['min_advance_time']) : 2;
            $reservation_interval = isset($settings['reservation_interval']) ? intval($settings['reservation_interval']) : 30;
            
            // Se o intervalo for 0, usar 30 minutos como padrão
            if ($reservation_interval <= 0) {
                $reservation_interval = 30;
            }
            
            error_log('Min advance hours: ' . $min_advance_hours);
            error_log('Reservation interval: ' . $reservation_interval);
            
            // Calcular dia da semana (0 = domingo, 1 = segunda, etc.)
            $weekday = date('w', strtotime($date));
            error_log('Weekday calculado: ' . $weekday);
            
            // Buscar disponibilidades para este dia da semana
            global $wpdb;
            $availability_table = $wpdb->prefix . 'zuzunely_availability';
            error_log('Tabela de disponibilidades: ' . $availability_table);
            
            // Verificar se a tabela existe
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$availability_table}'") === $availability_table;
            error_log('Tabela existe? ' . ($table_exists ? 'Sim' : 'Não'));
            
            if (!$table_exists) {
                error_log('ERRO: Tabela de disponibilidades não existe');
                wp_send_json_error(__('Sistema não configurado. Contate o administrador.', 'zuzunely-restaurant'));
            }
            
            $sql = "SELECT MIN(start_time) as earliest_time, MAX(end_time) as latest_time 
                    FROM {$availability_table} 
                    WHERE weekday = %d AND is_active = 1";
            
            error_log('SQL: ' . $wpdb->prepare($sql, $weekday));
            
            $result = $wpdb->get_row($wpdb->prepare($sql, $weekday));
            error_log('Resultado da consulta: ' . print_r($result, true));
            
            // Se não há resultados, vamos verificar se há disponibilidades cadastradas
            if (!$result || !$result->earliest_time || !$result->latest_time) {
                // Verificar quantas disponibilidades existem no total
                $total_availabilities = $wpdb->get_var("SELECT COUNT(*) FROM {$availability_table}");
                error_log('Total de disponibilidades no banco: ' . $total_availabilities);
                
                // Verificar se há disponibilidades para este weekday
                $weekday_availabilities = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$availability_table} WHERE weekday = %d", 
                    $weekday
                ));
                error_log('Disponibilidades para weekday ' . $weekday . ': ' . $weekday_availabilities);
                
                // Verificar se há disponibilidades ativas para este weekday
                $active_weekday_availabilities = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$availability_table} WHERE weekday = %d AND is_active = 1", 
                    $weekday
                ));
                error_log('Disponibilidades ativas para weekday ' . $weekday . ': ' . $active_weekday_availabilities);
                
                // Listar todas as disponibilidades para debug
                $all_availabilities = $wpdb->get_results("SELECT * FROM {$availability_table}", ARRAY_A);
                error_log('Todas as disponibilidades: ' . print_r($all_availabilities, true));
                
                wp_send_json_error(__('Não há horários de funcionamento configurados para este dia da semana.', 'zuzunely-restaurant'));
            }
            
            // Determinar se é hoje
            $is_today = (date('Y-m-d') === $date);
            error_log('É hoje? ' . ($is_today ? 'Sim' : 'Não'));
            
            // Gerar horários disponíveis
            $times = array();
            $start_time = $result->earliest_time;
            $end_time = $result->latest_time;
            
            error_log('Horário de funcionamento: ' . $start_time . ' às ' . $end_time);
            
            // Converter para timestamps para facilitar cálculos
            $start_timestamp = strtotime($date . ' ' . $start_time);
            $end_timestamp = strtotime($date . ' ' . $end_time);
            
            error_log('Start timestamp: ' . $start_timestamp . ' (' . date('Y-m-d H:i:s', $start_timestamp) . ')');
            error_log('End timestamp: ' . $end_timestamp . ' (' . date('Y-m-d H:i:s', $end_timestamp) . ')');
            
            // Se for hoje, ajustar horário inicial considerando antecedência mínima
            if ($is_today) {
                $now = time();
                $min_timestamp = $now + ($min_advance_hours * 3600); // Adicionar horas de antecedência
                
                error_log('Agora: ' . $now . ' (' . date('Y-m-d H:i:s', $now) . ')');
                error_log('Mínimo permitido: ' . $min_timestamp . ' (' . date('Y-m-d H:i:s', $min_timestamp) . ')');
                
                // Se o horário mínimo é maior que o horário de abertura, usar o horário mínimo
                if ($min_timestamp > $start_timestamp) {
                    // Arredondar para o próximo intervalo
                    $minutes_since_midnight = date('H', $min_timestamp) * 60 + date('i', $min_timestamp);
                    $interval_minutes = $reservation_interval;
                    $rounded_minutes = ceil($minutes_since_midnight / $interval_minutes) * $interval_minutes;
                    
                    $start_timestamp = strtotime($date . ' ' . sprintf('%02d:%02d', 
                        floor($rounded_minutes / 60), 
                        $rounded_minutes % 60
                    ));
                    
                    error_log('Horário ajustado para: ' . $start_timestamp . ' (' . date('Y-m-d H:i:s', $start_timestamp) . ')');
                }
            }
            
            // Gerar lista de horários
            $current_timestamp = $start_timestamp;
            $loop_count = 0;
            $max_loops = 100; // Evitar loop infinito
            
            error_log('Iniciando loop de geração de horários...');
            
            while ($current_timestamp <= $end_timestamp && $loop_count < $max_loops) {
                $loop_count++;
                $time_str = date('H:i', $current_timestamp);
                
                error_log('Loop ' . $loop_count . ': ' . $time_str . ' (timestamp: ' . $current_timestamp . ')');
                
                $times[] = array(
                    'value' => $time_str,
                    'label' => $time_str
                );
                
                // Avançar para o próximo slot
                $current_timestamp += ($reservation_interval * 60);
            }
            
            error_log('Total de horários gerados: ' . count($times));
            
            // Se não há horários disponíveis
            if (empty($times)) {
                if ($is_today) {
                    $message = sprintf(
                        __('Não há horários disponíveis para hoje. É necessário um mínimo de %d horas de antecedência.', 'zuzunely-restaurant'),
                        $min_advance_hours
                    );
                } else {
                    $message = __('Não há horários disponíveis para esta data.', 'zuzunely-restaurant');
                }
                error_log('ERRO: ' . $message);
                wp_send_json_error($message);
            }
            
            $response_data = array(
                'times' => $times,
                'earliest' => $start_time,
                'latest' => $end_time,
                'weekday' => $weekday,
                'interval' => $reservation_interval,
                'min_advance_hours' => $min_advance_hours,
                'is_today' => $is_today,
                'debug' => array(
                    'original_start' => $result->earliest_time,
                    'original_end' => $result->latest_time,
                    'adjusted_start' => date('H:i', $start_timestamp),
                    'adjusted_end' => date('H:i', $end_timestamp),
                    'total_slots' => count($times),
                    'date' => $date,
                    'weekday' => $weekday,
                    'current_time' => date('Y-m-d H:i:s'),
                    'min_advance_hours' => $min_advance_hours,
                    'loop_count' => $loop_count
                )
            );
            
            error_log('Resposta de sucesso: ' . print_r($response_data, true));
            error_log('=== FIM get_available_times ===');
            
            wp_send_json_success($response_data);
            
        } catch (Exception $e) {
            error_log('EXCEÇÃO: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            wp_send_json_error(__('Erro interno. Tente novamente.', 'zuzunely-restaurant'));
        }
    }
    
    /**
     * Enqueue scripts e estilos necessários
     */
    public function enqueue_scripts() {
        if ($this->has_reservation_shortcode()) {
            wp_enqueue_script('jquery');
            wp_enqueue_script('zuzunely-frontend-reservations', ZUZUNELY_PLUGIN_URL . 'frontend-reservations.js', array('jquery'), ZUZUNELY_VERSION, true);
            
            // Obter configurações para o JavaScript
            $settings = get_option('zuzunely_restaurant_settings', array());
            $max_guests = isset($settings['max_guests_per_reservation']) ? intval($settings['max_guests_per_reservation']) : 12;
            $min_advance_hours = isset($settings['min_advance_time']) ? intval($settings['min_advance_time']) : 2;
            $max_advance_days = isset($settings['max_advance_time']) ? intval($settings['max_advance_time']) : 30;
            
            // CORREÇÃO: Calcular datas min e max corretamente
            $min_date = date('Y-m-d');
            $max_date = date('Y-m-d', strtotime("+{$max_advance_days} days"));
            
            wp_localize_script('zuzunely-frontend-reservations', 'zuzunely_frontend', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('zuzunely_frontend_reservation'),
                'max_guests' => $max_guests,
                'min_advance_hours' => $min_advance_hours,
                'max_advance_days' => $max_advance_days,
                'min_date' => $min_date,
                'max_date' => $max_date,
                'messages' => array(
                    'searching_tables' => __('Buscando mesas disponíveis...', 'zuzunely-restaurant'),
                    'no_tables_found' => __('Nenhuma mesa disponível encontrada para os critérios selecionados.', 'zuzunely-restaurant'),
                    'select_table' => __('Por favor, selecione uma mesa.', 'zuzunely-restaurant'),
                    'select_area' => __('Por favor, selecione uma área.', 'zuzunely-restaurant'),
                    'select_date' => __('Por favor, selecione uma data.', 'zuzunely-restaurant'),
                    'select_time' => __('Por favor, selecione um horário.', 'zuzunely-restaurant'),
                    'select_guests' => __('Por favor, selecione o número de pessoas.', 'zuzunely-restaurant'),
                    'fill_required_fields' => __('Por favor, preencha todos os campos obrigatórios.', 'zuzunely-restaurant'),
                    'processing_reservation' => __('Processando reserva...', 'zuzunely-restaurant'),
                    'reservation_success' => __('Reserva realizada com sucesso! Você receberá uma confirmação por email e/ou WhatsApp.', 'zuzunely-restaurant'),
                    'reservation_error' => __('Erro ao processar reserva. Tente novamente.', 'zuzunely-restaurant'),
                    'max_guests_exceeded' => sprintf(__('O número máximo de pessoas por reserva é %d.', 'zuzunely-restaurant'), $max_guests),
                    'loading_times' => __('Carregando horários...', 'zuzunely-restaurant'),
                    'no_times_available' => __('Não há horários disponíveis para esta data.', 'zuzunely-restaurant'),
                    'connection_error' => __('Erro de conexão. Tente novamente.', 'zuzunely-restaurant'),
                    'min_advance_error' => sprintf(__('É necessário um mínimo de %d horas de antecedência para fazer uma reserva.', 'zuzunely-restaurant'), $min_advance_hours)
                )
            ));
            
            // Adicionar estilos
            wp_add_inline_style('wp-block-library', $this->get_frontend_styles());
        }
    }
    
    /**
     * Verificar se a página atual contém o shortcode
     */
    private function has_reservation_shortcode() {
        global $post;
        return $post && has_shortcode($post->post_content, 'zuzunely_reservations');
    }
    
    /**
     * Estilos CSS para o frontend
     */
    private function get_frontend_styles() {
        return '
        .zuzunely-reservation-form {
            max-width: 600px;
            margin: 20px auto;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: #f9f9f9;
        }
        
        .zuzunely-reservation-form h3 {
            margin-top: 0;
            color: #333;
        }
        
        .zuzunely-form-row {
            margin-bottom: 15px;
        }
        
        .zuzunely-form-row label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }
        
        .zuzunely-form-row input,
        .zuzunely-form-row select,
        .zuzunely-form-row textarea {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .zuzunely-form-row input:focus,
        .zuzunely-form-row select:focus,
        .zuzunely-form-row textarea:focus {
            outline: none;
            border-color: #0073aa;
            box-shadow: 0 0 5px rgba(0, 115, 170, 0.3);
        }
        
        .zuzunely-form-row select:disabled {
            background-color: #f5f5f5;
            color: #999;
            cursor: not-allowed;
        }
        
        .zuzunely-form-row select:disabled option {
            color: #999;
        }
        
        .loading-times {
            color: #666;
            font-style: italic;
        }
        
        .zuzunely-form-columns {
            display: flex;
            gap: 15px;
        }
        
        .zuzunely-form-columns .zuzunely-form-row {
            flex: 1;
        }
        
        .zuzunely-btn {
            background: #0073aa;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            text-decoration: none;
            display: inline-block;
            margin-right: 10px;
        }
        
        .zuzunely-btn:hover {
            background: #005a87;
            color: white;
        }
        
        .zuzunely-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        
        .zuzunely-btn-secondary {
            background: #666;
        }
        
        .zuzunely-btn-secondary:hover {
            background: #555;
        }
        
        .zuzunely-message {
            padding: 12px;
            margin-bottom: 15px;
            border-radius: 4px;
        }
        
        .zuzunely-message.success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        .zuzunely-message.error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        .zuzunely-message.info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }
        
        .zuzunely-tables-list {
            margin: 15px 0;
        }
        
        .zuzunely-table-option {
            margin-bottom: 10px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: white;
        }
        
        .zuzunely-table-option input[type="radio"] {
            margin-right: 8px;
        }
        
        .zuzunely-table-info {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
        }
        
        .zuzunely-step {
            display: none;
        }
        
        .zuzunely-step.active {
            display: block;
        }
        
        .zuzunely-step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        
        .zuzunely-step-indicator .step {
            flex: 1;
            padding: 10px;
            text-align: center;
            background: #f0f0f0;
            color: #666;
            border-radius: 4px;
            margin: 0 2px;
            font-size: 14px;
        }
        
        .zuzunely-step-indicator .step.active {
            background: #0073aa;
            color: white;
        }
        
        .zuzunely-step-indicator .step.completed {
            background: #46b450;
            color: white;
        }
        
        /* CSS para seleção de área */
        .zuzunely-area-selection {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin: 20px 0;
        }
        
        .zuzunely-area-option {
            position: relative;
        }
        
        .zuzunely-area-option label {
            display: block;
            cursor: pointer;
            margin: 0;
        }
        
        .zuzunely-area-option input[type="radio"] {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .area-card {
            border: 2px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s ease;
            background: #fff;
            min-height: 120px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
        }
        
        .area-card:hover {
            border-color: #0073aa;
            box-shadow: 0 2px 10px rgba(0,115,170,0.1);
            transform: translateY(-2px);
        }
        
        .zuzunely-area-option input[type="radio"]:checked + .area-card {
            border-color: #0073aa;
            background-color: #f0f8ff;
            box-shadow: 0 2px 10px rgba(0,115,170,0.2);
            transform: translateY(-2px);
        }
        
        .area-card h5 {
            margin: 0 0 10px 0;
            font-size: 18px;
            color: #333;
            font-weight: 600;
        }
        
        .area-card p {
            margin: 0;
            color: #666;
            font-size: 14px;
            line-height: 1.4;
        }
        
        .zuzunely-area-option input[type="radio"]:checked + .area-card h5 {
            color: #0073aa;
        }
        
        .zuzunely-area-option input[type="radio"]:checked + .area-card p {
            color: #333;
        }
        
        .zuzunely-area-option input[type="radio"]:checked + .area-card::before {
            content: "✓";
            position: absolute;
            top: 10px;
            right: 10px;
            background: #0073aa;
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 12px;
        }
        
        @media (max-width: 768px) {
            .zuzunely-form-columns {
                flex-direction: column;
            }
            
            .zuzunely-step-indicator {
                flex-direction: column;
            }
            
            .zuzunely-step-indicator .step {
                margin: 2px 0;
            }
            
            .zuzunely-area-selection {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .area-card {
                min-height: 100px;
                padding: 15px;
            }
            
            .area-card h5 {
                font-size: 16px;
            }
        }
        ';
    }
    
    /**
     * Renderizar formulário de reserva
     * CORREÇÃO: Datas min/max dinâmicas baseadas nas configurações
     */
    public function render_reservation_form($atts) {
        $atts = shortcode_atts(array(
            'title' => __('Faça sua Reserva', 'zuzunely-restaurant'),
            'show_steps' => 'yes'
        ), $atts);
        
        // Obter configurações
        $settings = get_option('zuzunely_restaurant_settings', array());
        $selection_mode = isset($settings['frontend_table_selection_mode']) ? $settings['frontend_table_selection_mode'] : 'table';
        $max_guests = isset($settings['max_guests_per_reservation']) ? intval($settings['max_guests_per_reservation']) : 12;
        $min_advance_hours = isset($settings['min_advance_time']) ? intval($settings['min_advance_time']) : 2;
        $max_advance_days = isset($settings['max_advance_time']) ? intval($settings['max_advance_time']) : 30;

        $areas_db = new Zuzunely_Areas_DB();
        $areas = $areas_db->get_areas(['include_inactive' => false, 'number' => 100]);
        
        // CORREÇÃO: Calcular datas min e max dinamicamente
        $min_date = date('Y-m-d');
        $max_date = date('Y-m-d', strtotime("+{$max_advance_days} days"));
        
        ob_start();
        ?>
        <div class="zuzunely-reservation-form" id="zuzunely-reservation-form">
            <h3><?php echo esc_html($atts['title']); ?></h3>
            
            <div id="zuzunely-messages"></div>
            
            <?php if ($atts['show_steps'] === 'yes'): ?>
            <div class="zuzunely-step-indicator">
                <div class="step active" data-step="1"><?php _e('1. Data e Hora', 'zuzunely-restaurant'); ?></div>
                <div class="step" data-step="2"><?php 
                    if ($selection_mode === 'area') {
                        _e('2. Escolher Área', 'zuzunely-restaurant');
                    } elseif ($selection_mode === 'table') {
                        _e('2. Escolher Mesa', 'zuzunely-restaurant');
                    } else {
                        _e('2. Escolher Local', 'zuzunely-restaurant'); 
                    }
                ?></div>
                <div class="step" data-step="3"><?php _e('3. Seus Dados', 'zuzunely-restaurant'); ?></div>
                <div class="step" data-step="4"><?php _e('4. Confirmação', 'zuzunely-restaurant'); ?></div>
            </div>
            <?php endif; ?>
            
            <form id="zuzunely-frontend-form" method="post">
                <?php wp_nonce_field('zuzunely_frontend_reservation', 'zuzunely_nonce'); ?>
                
                <!-- Passo 1: Data, Hora e Número de Pessoas -->
                <div class="zuzunely-step active" data-step="1">
                    <h4><?php _e('Selecione a data e horário', 'zuzunely-restaurant'); ?></h4>
                    
                    <div class="zuzunely-form-columns">
                        <div class="zuzunely-form-row">
                            <label for="reservation_date"><?php _e('Data da Reserva', 'zuzunely-restaurant'); ?> *</label>
                            <input type="date" id="reservation_date" name="reservation_date" required 
                                   min="<?php echo esc_attr($min_date); ?>" 
                                   max="<?php echo esc_attr($max_date); ?>">
                            <p class="description"><?php echo sprintf(__('Antecedência mínima: %d horas. Máximo: %d dias.', 'zuzunely-restaurant'), $min_advance_hours, $max_advance_days); ?></p>
                        </div>
                        
                        <div class="zuzunely-form-row">
                            <label for="reservation_time"><?php _e('Horário', 'zuzunely-restaurant'); ?> *</label>
                            <select id="reservation_time" name="reservation_time" required disabled>
                                <option value=""><?php _e('Primeiro selecione uma data', 'zuzunely-restaurant'); ?></option>
                            </select>
                            <p class="description" id="time-description" style="display: none;"></p>
                        </div>
                    </div>
                    
                    <div class="zuzunely-form-row">
                        <label for="guests_count"><?php _e('Número de Pessoas', 'zuzunely-restaurant'); ?> *</label>
                        <select id="guests_count" name="guests_count" required>
                            <option value=""><?php _e('Selecione', 'zuzunely-restaurant'); ?></option>
                            <?php for ($i = 1; $i <= $max_guests; $i++): ?>
                                <option value="<?php echo $i; ?>"><?php echo $i; ?> <?php echo $i == 1 ? __('pessoa', 'zuzunely-restaurant') : __('pessoas', 'zuzunely-restaurant'); ?></option>
                            <?php endfor; ?>
                        </select>
                        <p class="description"><?php echo sprintf(__('Máximo: %d pessoas por reserva', 'zuzunely-restaurant'), $max_guests); ?></p>
                    </div>
                    
                    <div class="zuzunely-form-row">
                        <button type="button" class="zuzunely-btn" id="continue-to-step2">
                            <?php _e('Continuar →', 'zuzunely-restaurant'); ?>
                        </button>
                    </div>
                </div>
                
                <!-- Passo 2: Seleção de Mesa/Área -->
                <div class="zuzunely-step" data-step="2">
                    <?php if ($selection_mode === 'area'): ?>
                        <!-- Modo área: escolher entre áreas cadastradas -->
                        <h4><?php _e('Escolha a área de sua preferência', 'zuzunely-restaurant'); ?></h4>
                        <div class="zuzunely-area-selection">
                            <?php foreach ($areas as $area) : ?>
                                <div class="zuzunely-area-option">
                                    <label>
                                        <input type="radio" name="area_selection" value="<?php echo $area['id']; ?>" data-area-name="<?php echo esc_attr($area['name']); ?>">
                                        <div class="area-card">
                                            <h5><?php echo esc_html($area['name']); ?></h5>
                                            <p><?php echo esc_html(wp_trim_words($area['description'], 15, '...')); ?></p>
                                        </div>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <!-- Outros modos: buscar mesas/salões -->
                        <h4><?php 
                            if ($selection_mode === 'table') {
                                _e('Escolha sua mesa', 'zuzunely-restaurant');
                            } elseif ($selection_mode === 'saloon') {
                                _e('Escolha o salão', 'zuzunely-restaurant');
                            }
                        ?></h4>
                        
                        <div class="zuzunely-form-row">
                            <button type="button" class="zuzunely-btn" id="search-tables-btn">
                                <?php 
                                if ($selection_mode === 'table') {
                                    _e('Buscar Mesas Disponíveis', 'zuzunely-restaurant');
                                } else {
                                    _e('Buscar Locais Disponíveis', 'zuzunely-restaurant');
                                }
                                ?>
                            </button>
                        </div>
                        
                        <div id="available-tables-container">
                            <p><?php _e('Use o botão acima para ver as opções disponíveis.', 'zuzunely-restaurant'); ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <div class="zuzunely-form-row">
                        <button type="button" class="zuzunely-btn zuzunely-btn-secondary" id="back-to-step1">
                            <?php _e('← Voltar', 'zuzunely-restaurant'); ?>
                        </button>
                        <button type="button" class="zuzunely-btn" id="continue-to-step3">
                            <?php _e('Continuar →', 'zuzunely-restaurant'); ?>
                        </button>
                    </div>
                </div>
                
                <!-- Passo 3: Dados do Cliente -->
                <div class="zuzunely-step" data-step="3">
                    <h4><?php _e('Seus dados para contato', 'zuzunely-restaurant'); ?></h4>
                    
                    <div class="zuzunely-form-row">
                        <label for="customer_name"><?php _e('Nome Completo', 'zuzunely-restaurant'); ?> *</label>
                        <input type="text" id="customer_name" name="customer_name" required>
                    </div>
                    
                    <div class="zuzunely-form-columns">
                        <div class="zuzunely-form-row">
                            <label for="customer_phone"><?php _e('Telefone/WhatsApp', 'zuzunely-restaurant'); ?> *</label>
                            <input type="tel" id="customer_phone" name="customer_phone" required 
                                   placeholder="(11) 99999-9999">
                        </div>
                        
                        <div class="zuzunely-form-row">
                            <label for="customer_email"><?php _e('E-mail', 'zuzunely-restaurant'); ?> *</label>
                            <input type="email" id="customer_email" name="customer_email" required>
                        </div>
                    </div>
                    
                    <div class="zuzunely-form-row">
                        <label for="notes"><?php _e('Observações (opcional)', 'zuzunely-restaurant'); ?></label>
                        <textarea id="notes" name="notes" rows="3" 
                                  placeholder="<?php _e('Alguma observação especial, restrição alimentar, comemoração, etc.', 'zuzunely-restaurant'); ?>"></textarea>
                    </div>
                    
                    <div class="zuzunely-form-row">
                        <button type="button" class="zuzunely-btn zuzunely-btn-secondary" id="back-to-step2">
                            <?php _e('← Voltar', 'zuzunely-restaurant'); ?>
                        </button>
                        <button type="button" class="zuzunely-btn" id="continue-to-step4">
                            <?php _e('Revisar Reserva →', 'zuzunely-restaurant'); ?>
                        </button>
                    </div>
                </div>
                
                <!-- Passo 4: Confirmação -->
                <div class="zuzunely-step" data-step="4">
                    <h4><?php _e('Confirme sua reserva', 'zuzunely-restaurant'); ?></h4>
                    
                    <div id="reservation-summary">
                        <!-- Será preenchido via JavaScript -->
                    </div>
                    
                    <div class="zuzunely-form-row">
                        <label>
                            <input type="checkbox" id="accept_terms" required>
                            <?php _e('Aceito os termos e condições da reserva', 'zuzunely-restaurant'); ?> *
                        </label>
                    </div>
                    
                    <div class="zuzunely-form-row">
                        <button type="button" class="zuzunely-btn zuzunely-btn-secondary" id="back-to-step3">
                            <?php _e('← Voltar', 'zuzunely-restaurant'); ?>
                        </button>
                        <button type="submit" class="zuzunely-btn" id="confirm-reservation">
                            <?php _e('Confirmar Reserva', 'zuzunely-restaurant'); ?>
                        </button>
                    </div>
                </div>
                
                <!-- Campos ocultos -->
                <input type="hidden" id="selected_table_id" name="table_id" value="">
                <input type="hidden" id="selection_mode" name="selection_mode" value="<?php echo esc_attr($selection_mode); ?>">
                <input type="hidden" id="selected_area" name="selected_area" value="">
                <input type="hidden" id="max_guests_setting" value="<?php echo esc_attr($max_guests); ?>">
                <input type="hidden" name="action" value="zuzunely_frontend_reservation">
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Buscar mesas disponíveis via AJAX
     * CORREÇÃO: Adicionar validação de antecedência mínima
     */
    public function search_available_tables() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'zuzunely_frontend_reservation')) {
            wp_send_json_error(__('Erro de segurança', 'zuzunely-restaurant'));
        }
        
        $date = sanitize_text_field($_POST['date']);
        $time = sanitize_text_field($_POST['time']);
        $guests_count = intval($_POST['guests_count']);
        $selection_mode = isset($_POST['selection_mode']) ? sanitize_text_field($_POST['selection_mode']) : 'table';
        
        // Validar dados
        if (empty($date) || empty($time) || $guests_count <= 0) {
            wp_send_json_error(__('Dados inválidos', 'zuzunely-restaurant'));
        }
        
        // CORREÇÃO: Validar antecedência mínima
        $settings = get_option('zuzunely_restaurant_settings', array());
        $min_advance_hours = isset($settings['min_advance_time']) ? intval($settings['min_advance_time']) : 2;
        
        // Validar data (não pode ser no passado)
        if (strtotime($date) < strtotime(date('Y-m-d'))) {
            wp_send_json_error(__('A data selecionada não pode ser no passado', 'zuzunely-restaurant'));
        }
        
        // Validar antecedência mínima para hoje
        $selected_datetime = strtotime($date . ' ' . $time);
        $min_allowed_datetime = strtotime("+{$min_advance_hours} hours");
        
        if ($selected_datetime < $min_allowed_datetime) {
            wp_send_json_error(sprintf(
                __('É necessário um mínimo de %d horas de antecedência para fazer uma reserva.', 'zuzunely-restaurant'),
                $min_advance_hours
            ));
        }
        
        try {
            // Buscar mesas disponíveis
            $reservations_db = new Zuzunely_Reservations_DB();
            $available_tables = $reservations_db->get_available_tables($date, $time, $guests_count, false);
            
            if (empty($available_tables)) {
                wp_send_json_error(__('Nenhuma mesa disponível encontrada para os critérios selecionados.', 'zuzunely-restaurant'));
            }
            
            // Processar de acordo com o modo de seleção
            $html = $this->generate_selection_html($available_tables, $selection_mode);
            
            wp_send_json_success(array(
                'html' => $html,
                'count' => count($available_tables)
            ));
            
        } catch (Exception $e) {
            Zuzunely_Logger::error('Erro ao buscar mesas disponíveis: ' . $e->getMessage());
            wp_send_json_error(__('Erro interno. Tente novamente.', 'zuzunely-restaurant'));
        }
    }
    
    /**
     * Gerar HTML de seleção baseado no modo
     */
    private function generate_selection_html($available_tables, $selection_mode) {
        $html = '<div class="zuzunely-tables-list">';
        
        switch ($selection_mode) {
            case 'table':
                // Modo mesa: mostrar cada mesa individualmente
                foreach ($available_tables as $table) {
                    $html .= '<div class="zuzunely-table-option">';
                    $html .= '<label>';
                    $html .= '<input type="radio" name="table_selection" value="' . esc_attr($table['id']) . '" data-table-name="' . esc_attr($table['name']) . '" data-saloon-name="' . esc_attr($table['saloon_name']) . '" data-capacity="' . esc_attr($table['capacity']) . '">';
                    $html .= '<strong>' . esc_html($table['name']) . '</strong>';
                    $html .= '<div class="zuzunely-table-info">';
                    $html .= __('Salão:', 'zuzunely-restaurant') . ' ' . esc_html($table['saloon_name']) . ' | ';
                    $html .= __('Capacidade:', 'zuzunely-restaurant') . ' ' . esc_html($table['capacity']) . ' ' . __('pessoas', 'zuzunely-restaurant');
                    $html .= '</div>';
                    $html .= '</label>';
                    $html .= '</div>';
                }
                break;
                
            case 'saloon':
                // Modo salão: agrupar por salão
                $saloons = array();
                foreach ($available_tables as $table) {
                    $saloon_id = $table['saloon_id'];
                    if (!isset($saloons[$saloon_id])) {
                        $saloons[$saloon_id] = array(
                            'name' => $table['saloon_name'],
                            'table_count' => 0,
                            'table_ids' => array()
                        );
                    }
                    $saloons[$saloon_id]['table_count']++;
                    $saloons[$saloon_id]['table_ids'][] = $table['id'];
                }
                
                foreach ($saloons as $saloon_id => $saloon_data) {
                    $html .= '<div class="zuzunely-table-option">';
                    $html .= '<label>';
                    $html .= '<input type="radio" name="table_selection" value="saloon_' . esc_attr($saloon_id) . '" data-saloon-id="' . esc_attr($saloon_id) . '" data-saloon-name="' . esc_attr($saloon_data['name']) . '" data-table-ids="' . esc_attr(implode(',', $saloon_data['table_ids'])) . '">';
                    $html .= '<strong>' . esc_html($saloon_data['name']) . '</strong>';
                    $html .= '<div class="zuzunely-table-info">';
                    $html .= sprintf(__('%d mesa(s) disponível(is)', 'zuzunely-restaurant'), $saloon_data['table_count']);
                    $html .= '</div>';
                    $html .= '</label>';
                    $html .= '</div>';
                }
                break;
        }
        
        $html .= '</div>';
        return $html;
    }
    
    /**
     * Processar formulário de reserva do frontend
     * CORREÇÃO: Adicionar validação de antecedência mínima
     */
    public function process_frontend_reservation() {
        try {
            // Verificar nonce
            if (!wp_verify_nonce($_POST['zuzunely_nonce'], 'zuzunely_frontend_reservation')) {
                throw new Exception(__('Erro de segurança', 'zuzunely-restaurant'));
            }
            
            // Obter configurações
            $settings = get_option('zuzunely_restaurant_settings', array());
            $selection_mode = isset($_POST['selection_mode']) ? sanitize_text_field($_POST['selection_mode']) : 'table';
            $min_advance_hours = isset($settings['min_advance_time']) ? intval($settings['min_advance_time']) : 2;
            
            // Obter dados do formulário
            $reservation_data = array(
                'customer_name' => sanitize_text_field($_POST['customer_name']),
                'customer_phone' => sanitize_text_field($_POST['customer_phone']),
                'customer_email' => sanitize_email($_POST['customer_email']),
                'guests_count' => intval($_POST['guests_count']),
                'reservation_date' => sanitize_text_field($_POST['reservation_date']),
                'reservation_time' => sanitize_text_field($_POST['reservation_time']),
                'notes' => sanitize_textarea_field($_POST['notes']),
                'status' => 'confirmed', // Reservas do frontend são confirmadas automaticamente
                'is_active' => 1,
                'override_rules' => 0
            );
            
            // CORREÇÃO: Validar antecedência mínima
            $selected_datetime = strtotime($reservation_data['reservation_date'] . ' ' . $reservation_data['reservation_time']);
            $min_allowed_datetime = strtotime("+{$min_advance_hours} hours");
            
            if ($selected_datetime < $min_allowed_datetime) {
                throw new Exception(sprintf(
                    __('É necessário um mínimo de %d horas de antecedência para fazer uma reserva.', 'zuzunely-restaurant'),
                    $min_advance_hours
                ));
            }
            
            // Determinar mesa baseado no modo de seleção
            $table_id = $this->determine_table_id($selection_mode, $_POST, $reservation_data);
            $reservation_data['table_id'] = $table_id;
            
            // Obter duração padrão
            $reservations_db = new Zuzunely_Reservations_DB();
            $reservation_data['duration'] = $reservations_db->get_default_duration();
            
            // Validar dados obrigatórios
            if (empty($reservation_data['table_id'])) {
                throw new Exception(__('Não foi possível encontrar uma mesa disponível', 'zuzunely-restaurant'));
            }
            
            if (empty($reservation_data['customer_name'])) {
                throw new Exception(__('Nome é obrigatório', 'zuzunely-restaurant'));
            }
            
            if (empty($reservation_data['customer_phone'])) {
                throw new Exception(__('Telefone é obrigatório', 'zuzunely-restaurant'));
            }
            
            if (empty($reservation_data['customer_email'])) {
                throw new Exception(__('E-mail é obrigatório', 'zuzunely-restaurant'));
            }
            
            if (empty($reservation_data['reservation_date'])) {
                throw new Exception(__('Data da reserva é obrigatória', 'zuzunely-restaurant'));
            }
            
            if (empty($reservation_data['reservation_time'])) {
                throw new Exception(__('Horário da reserva é obrigatório', 'zuzunely-restaurant'));
            }
            
            if ($reservation_data['guests_count'] <= 0) {
                throw new Exception(__('Número de pessoas deve ser maior que zero', 'zuzunely-restaurant'));
            }
            
            // Validar número máximo de pessoas
            $max_guests = isset($settings['max_guests_per_reservation']) ? intval($settings['max_guests_per_reservation']) : 12;
            if ($reservation_data['guests_count'] > $max_guests) {
                throw new Exception(sprintf(__('O número máximo de pessoas por reserva é %d', 'zuzunely-restaurant'), $max_guests));
            }
            
            // Validar data (não pode ser no passado)
            if (strtotime($reservation_data['reservation_date']) < strtotime(date('Y-m-d'))) {
                throw new Exception(__('A data selecionada não pode ser no passado', 'zuzunely-restaurant'));
            }
            
            // Verificar se a mesa ainda está disponível
            if (!$reservations_db->is_table_available(
                $reservation_data['table_id'],
                $reservation_data['reservation_date'],
                $reservation_data['reservation_time'],
                $reservation_data['duration']
            )) {
                throw new Exception(__('A mesa selecionada não está mais disponível para este horário', 'zuzunely-restaurant'));
            }
            
            // Inserir reserva
            $reservation_id = $reservations_db->insert_reservation($reservation_data);
            
            if (!$reservation_id) {
                throw new Exception(__('Erro ao salvar reserva. Tente novamente.', 'zuzunely-restaurant'));
            }
            
            wp_send_json_success(array(
                'message' => __('Reserva realizada com sucesso!', 'zuzunely-restaurant'),
                'reservation_id' => $reservation_id
            ));
            
        } catch (Exception $e) {
            Zuzunely_Logger::error('Erro ao processar reserva frontend: ' . $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Determinar ID da mesa baseado no modo de seleção
     */
    private function determine_table_id($selection_mode, $post_data, $reservation_data) {
        $reservations_db = new Zuzunely_Reservations_DB();
        $settings = get_option('zuzunely_restaurant_settings', array());
        
        switch ($selection_mode) {
            case 'table':
                // Modo mesa: ID da mesa vem diretamente
                return intval($post_data['table_id']);
                
            case 'saloon':
                // Modo salão: escolher mesa automaticamente no salão selecionado
                $selected_saloon_id = null;
                $table_selection = sanitize_text_field($post_data['table_id']);
                
                if (strpos($table_selection, 'saloon_') === 0) {
                    $selected_saloon_id = intval(str_replace('saloon_', '', $table_selection));
                }
                
                return $this->auto_assign_table_in_saloon(
                    $selected_saloon_id, 
                    $reservation_data['reservation_date'],
                    $reservation_data['reservation_time'],
                    $reservation_data['guests_count'],
                    $settings
                );
                
            case 'area':
                // Modo área: escolher mesa automaticamente na área especificada
                $selected_area = isset($post_data['selected_area']) ? sanitize_text_field($post_data['selected_area']) : null;
                
                return $this->auto_assign_table_in_area(
                    $selected_area,
                    $reservation_data['reservation_date'],
                    $reservation_data['reservation_time'], 
                    $reservation_data['guests_count'],
                    $settings
                );
                
            default:
                throw new Exception(__('Modo de seleção inválido', 'zuzunely-restaurant'));
        }
    }
    
    /**
     * Atribuir mesa automaticamente em um salão específico
     */
    private function auto_assign_table_in_saloon($saloon_id, $date, $time, $guests_count, $settings) {
        $reservations_db = new Zuzunely_Reservations_DB();
        $available_tables = $reservations_db->get_available_tables($date, $time, $guests_count, false);
        
        // Filtrar mesas do salão especificado
        $saloon_tables = array_filter($available_tables, function($table) use ($saloon_id) {
            return $table['saloon_id'] == $saloon_id;
        });
        
        if (empty($saloon_tables)) {
            throw new Exception(__('Nenhuma mesa disponível no salão selecionado', 'zuzunely-restaurant'));
        }
        
        return $this->select_table_by_strategy($saloon_tables, $guests_count, $settings);
    }
    
    /**
     * Atribuir mesa automaticamente em uma área específica
     */
    private function auto_assign_table_in_area($area_id, $date, $time, $guests_count, $settings) {
        $reservations_db = new Zuzunely_Reservations_DB();
        $available_tables = $reservations_db->get_available_tables($date, $time, $guests_count, false);

        // Filtrar mesas da área especificada
        $area_tables = array_filter($available_tables, function($table) use ($area_id) {
            return intval($table['saloon_area_id']) === intval($area_id);
        });
        
        if (empty($area_tables)) {
            // Se não há mesas na área preferida, usar qualquer mesa disponível
            $area_tables = $available_tables;
        }
        
        if (empty($area_tables)) {
            throw new Exception(__('Nenhuma mesa disponível', 'zuzunely-restaurant'));
        }
        
        return $this->select_table_by_strategy($area_tables, $guests_count, $settings);
    }
    
    /**
     * Selecionar mesa baseado na estratégia configurada
     */
    private function select_table_by_strategy($tables, $guests_count, $settings) {
        $strategy = isset($settings['frontend_auto_assign_strategy']) ? $settings['frontend_auto_assign_strategy'] : 'smallest_suitable';
        
        switch ($strategy) {
            case 'smallest_suitable':
                // Ordenar por capacidade (menor primeiro) e selecionar a primeira adequada
                usort($tables, function($a, $b) {
                    return $a['capacity'] - $b['capacity'];
                });
                
                foreach ($tables as $table) {
                    if ($table['capacity'] >= $guests_count) {
                        return $table['id'];
                    }
                }
                break;
                
            case 'largest_available':
                // Ordenar por capacidade (maior primeiro) e selecionar a primeira
                usort($tables, function($a, $b) {
                    return $b['capacity'] - $a['capacity'];
                });
                return $tables[0]['id'];
                
            case 'random':
                // Selecionar aleatoriamente entre as mesas adequadas
                $suitable_tables = array_filter($tables, function($table) use ($guests_count) {
                    return $table['capacity'] >= $guests_count;
                });
                
                if (!empty($suitable_tables)) {
                    $random_table = $suitable_tables[array_rand($suitable_tables)];
                    return $random_table['id'];
                }
                break;
        }
        
        // Fallback: retornar a primeira mesa disponível
        return $tables[0]['id'];
    }
}

// Inicializar a classe
new Zuzunely_Frontend_Reservations();