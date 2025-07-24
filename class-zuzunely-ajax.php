<?php
/**
 * Classe para gerenciar requisições AJAX do plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class Zuzunely_Ajax {
    
    // Construtor
    public function __construct() {
        // Registrar ações AJAX
        add_action('wp_ajax_zuzunely_get_available_tables', array($this, 'get_available_tables'));
        add_action('wp_ajax_zuzunely_check_table_availability', array($this, 'check_table_availability'));
        add_action('wp_ajax_zuzunely_quick_update_reservation', array($this, 'quick_update_reservation'));
    }
    
    /**
     * Obter mesas disponíveis via AJAX
     */
    public function get_available_tables() {
        // Verificar nonce
        check_ajax_referer('zuzunely_reservations', 'nonce');
        
        // Obter dados da requisição
        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
        $time = isset($_POST['time']) ? sanitize_text_field($_POST['time']) : '';
        $guests = isset($_POST['guests']) ? intval($_POST['guests']) : 1;
        $override = isset($_POST['override']) ? (bool)$_POST['override'] : false;
        
        // Validar dados
        if (empty($date) || empty($time)) {
            wp_send_json_error(array(
                'message' => __('Data e hora são obrigatórios.', 'zuzunely-restaurant')
            ));
        }
        
        // Instanciar banco de dados
        $db = new Zuzunely_Reservations_DB();
        
        // Obter mesas disponíveis
        $tables = $db->get_available_tables($date, $time, $guests, $override);
        
        // Retornar resultado
        wp_send_json_success(array(
            'tables' => $tables,
            'date' => $date,
            'time' => $time,
            'guests' => $guests,
            'override' => $override
        ));
    }
    
    /**
     * Verificar disponibilidade de uma mesa específica
     */
    public function check_table_availability() {
        // Verificar nonce
        check_ajax_referer('zuzunely_reservations', 'nonce');
        
        // Obter dados da requisição
        $table_id = isset($_POST['table_id']) ? intval($_POST['table_id']) : 0;
        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
        $time = isset($_POST['time']) ? sanitize_text_field($_POST['time']) : '';
        $duration = isset($_POST['duration']) ? intval($_POST['duration']) : 60;
        
        // Validar dados
        if (empty($table_id) || empty($date) || empty($time)) {
            wp_send_json_error(array(
                'message' => __('Mesa, data e hora são obrigatórios.', 'zuzunely-restaurant')
            ));
        }
        
        // Instanciar banco de dados
        $db = new Zuzunely_Reservations_DB();
        
        // Verificar disponibilidade
        $is_available = $db->is_table_available($table_id, $date, $time, $duration);
        
        // Obter informações adicionais
        $blocks_db = new Zuzunely_Blocks_DB();
        $has_blocks = $blocks_db->has_table_blocks($table_id, $date, $date, $time, date('H:i:s', strtotime($time) + ($duration * 60)));
        
        $availability_db = new Zuzunely_Availability_DB();
        $weekday = date('w', strtotime($date));
        $is_available_day = $availability_db->is_table_available($table_id, $weekday, $time);
        
        // Obter outras reservas na mesa nesta data
        $other_reservations = $db->get_reservations(array(
            'table_id' => $table_id,
            'date' => $date,
            'status' => 'confirmed'
        ));
        
        // Retornar resultado
        wp_send_json_success(array(
            'is_available' => $is_available,
            'has_blocks' => $has_blocks,
            'is_available_day' => $is_available_day,
            'other_reservations' => $other_reservations,
            'table_id' => $table_id,
            'date' => $date,
            'time' => $time,
            'duration' => $duration
        ));
    }
    
    /**
     * Atualização rápida de status de reserva
     */
    public function quick_update_reservation() {
        // Verificar nonce
        check_ajax_referer('zuzunely_reservations', 'nonce');
        
        // Verificar permissões
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Você não tem permissão para realizar esta ação.', 'zuzunely-restaurant')
            ));
        }
        
        // Obter dados da requisição
        $reservation_id = isset($_POST['reservation_id']) ? intval($_POST['reservation_id']) : 0;
        $new_status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
        
        // Validar dados
        if (empty($reservation_id) || empty($new_status)) {
            wp_send_json_error(array(
                'message' => __('ID da reserva e novo status são obrigatórios.', 'zuzunely-restaurant')
            ));
        }
        
        // Verificar se o status é válido
        $valid_statuses = array_keys(Zuzunely_Reservations_DB::get_status_list());
        if (!in_array($new_status, $valid_statuses)) {
            wp_send_json_error(array(
                'message' => __('Status inválido.', 'zuzunely-restaurant')
            ));
        }
        
        // Instanciar banco de dados
        $db = new Zuzunely_Reservations_DB();
        
        // Obter reserva atual
        $reservation = $db->get_reservation($reservation_id);
        
        if (!$reservation) {
            wp_send_json_error(array(
                'message' => __('Reserva não encontrada.', 'zuzunely-restaurant')
            ));
        }
        
        // Atualizar status
        $reservation['status'] = $new_status;
        $result = $db->update_reservation($reservation_id, $reservation);
        
        if ($result) {
            wp_send_json_success(array(
                'message' => __('Status atualizado com sucesso!', 'zuzunely-restaurant'),
                'new_status' => $new_status,
                'status_label' => Zuzunely_Reservations_DB::get_status_list()[$new_status]
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Erro ao atualizar status.', 'zuzunely-restaurant')
            ));
        }
    }
}