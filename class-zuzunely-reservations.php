<?php
/**
 * Classe para gerenciar as reservas do restaurante
 * VERSÃO EMERGENCIAL - Processamento de formulário simplificado
 */

if (!defined('ABSPATH')) {
    exit;
}

class Zuzunely_Reservations {
    
    // Construtor
    public function __construct() {
        // Removemos todos os hooks de processamento
        // Processamento será feito diretamente sem hooks
    }
    
    /**
     * Página administrativa de reservas
     */
    public static function admin_page() {
        // Verificar permissões
        if (!current_user_can('manage_options')) {
            wp_die(__('Você não tem permissão para acessar esta página.', 'zuzunely-restaurant'));
        }
        
        try {
            // Log de início
            if (class_exists('Zuzunely_Logger')) {
                Zuzunely_Logger::info('Acessando admin_page de reservas');
            } else {
                error_log('Zuzunely_Logger não encontrado');
            }
            
            // VERIFICAR E PROCESSAR FORMULÁRIO DIRETAMENTE
            $reservations = new self();
            
            // Verificação emergencial de envio de formulário
            if (isset($_POST['submit']) && isset($_POST['zuzunely_reservation'])) {
                try {
                    error_log('Tentando processar formulário direto...');
                    $reservations->process_reservation_form_direct();
                    // Se chegou aqui, o processamento foi bem-sucedido
                    // Não fazemos redirect - queremos ver mensagens de erro
                } catch (Exception $e) {
                    error_log('Erro ao processar formulário: ' . $e->getMessage());
                    echo '<div class="error"><p>Erro: ' . esc_html($e->getMessage()) . '</p></div>';
                }
            }
            
            // Definir e obter ação atual (listar, adicionar, editar)
            $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
            
            // Executar ação correspondente
            switch ($action) {
                case 'add':
                    $reservations->add_reservation_page();
                    break;
                    
                case 'edit':
                    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
                    $reservations->edit_reservation_page($id);
                    break;
                    
                case 'delete':
                    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
                    $reservations->delete_reservation($id);
                    break;
                    
                case 'confirm':
                    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
                    $reservations->confirm_reservation($id);
                    break;
                    
                case 'cancel':
                    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
                    $reservations->cancel_reservation($id);
                    break;
                    
                default:
                    $reservations->list_reservations_page();
                    break;
            }
            
        } catch (Exception $e) {
            error_log('Erro em admin_page: ' . $e->getMessage());
            echo '<div class="error"><p>Erro: ' . esc_html($e->getMessage()) . '</p></div>';
        }
    }
    
    /**
     * Processamento direto de formulário (versão emergencial)
     */
    public function process_reservation_form_direct() {
        global $wpdb;
        
        // Depuração básica - verificar se estamos chegando aqui
        error_log('CHAMADA DIRETA: process_reservation_form_direct() - ' . date('H:i:s'));
        error_log('POST data: ' . print_r($_POST, true));
        
        try {
            // Verificar nonce
            if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'zuzunely_reservation')) {
                throw new Exception(__('Erro de segurança. Por favor, recarregue a página e tente novamente.', 'zuzunely-restaurant'));
            }
            
            // Obter os dados do formulário
            $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
            $is_new = ($id === 0);
            
            error_log('Tipo de operação: ' . ($is_new ? 'Nova reserva' : 'Atualização de reserva ID: ' . $id));
            
            // Preparar dados da reserva
            $reservation_data = array(
                'table_id' => isset($_POST['table_id']) ? intval($_POST['table_id']) : 0,
                'customer_name' => isset($_POST['customer_name']) ? sanitize_text_field($_POST['customer_name']) : '',
                'customer_phone' => isset($_POST['customer_phone']) ? sanitize_text_field($_POST['customer_phone']) : '',
                'customer_email' => isset($_POST['customer_email']) ? sanitize_email($_POST['customer_email']) : '',
                'guests_count' => isset($_POST['guests_count']) ? intval($_POST['guests_count']) : 1,
                'reservation_date' => isset($_POST['reservation_date']) ? sanitize_text_field($_POST['reservation_date']) : '',
                'reservation_time' => isset($_POST['reservation_time']) ? sanitize_text_field($_POST['reservation_time']) : '',
                'duration' => isset($_POST['duration']) ? intval($_POST['duration']) : $this->get_default_duration(),
                'status' => isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'pending',
                'notes' => isset($_POST['notes']) ? sanitize_textarea_field($_POST['notes']) : '',
                'override_rules' => isset($_POST['override_rules']) ? 1 : 0,
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
            );
            
            error_log('Dados do formulário: ' . print_r($reservation_data, true));
            
            // VALIDAÇÃO DO NÚMERO MÁXIMO DE PESSOAS - CORRIGIDA
            $settings = get_option('zuzunely_restaurant_settings', array());
            $max_guests = isset($settings['max_guests_per_reservation']) ? intval($settings['max_guests_per_reservation']) : 12;
            
            if ($reservation_data['guests_count'] > $max_guests) {
                throw new Exception(sprintf(__('O número máximo de pessoas por reserva é %d', 'zuzunely-restaurant'), $max_guests));
            }
            
            // Validar dados
            if (empty($reservation_data['table_id'])) {
                throw new Exception(__('Por favor, selecione uma mesa.', 'zuzunely-restaurant'));
            }
            
            if (empty($reservation_data['customer_name'])) {
                throw new Exception(__('O nome do cliente é obrigatório.', 'zuzunely-restaurant'));
            }
            
            if (empty($reservation_data['customer_phone'])) {
                throw new Exception(__('O telefone do cliente é obrigatório.', 'zuzunely-restaurant'));
            }
            
            if (empty($reservation_data['customer_email'])) {
                throw new Exception(__('O email do cliente é obrigatório.', 'zuzunely-restaurant'));
            }
            
            if (empty($reservation_data['reservation_date'])) {
                throw new Exception(__('A data da reserva é obrigatória.', 'zuzunely-restaurant'));
            }
            
            if (empty($reservation_data['reservation_time'])) {
                throw new Exception(__('O horário da reserva é obrigatório.', 'zuzunely-restaurant'));
            }
            
            if ($reservation_data['guests_count'] <= 0) {
                throw new Exception(__('Número de pessoas deve ser maior que zero', 'zuzunely-restaurant'));
            }
            
            // INSERÇÃO DIRETA - VERSÃO SIMPLIFICADA
            $table_name = $wpdb->prefix . 'zuzunely_reservations';
            
            // Verificar se a tabela existe
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
            if (!$table_exists) {
                throw new Exception("Tabela de reservas não existe: {$table_name}");
            }
            
            // INSERÇÃO SIMPLES SEM VALIDAÇÃO EXTRA
            if ($is_new) {
                error_log('Tentando inserir nova reserva...');
                
                // Definir formato para cada campo
                $formats = array(
                    '%d', // table_id
                    '%s', // customer_name
                    '%s', // customer_phone
                    '%s', // customer_email
                    '%d', // guests_count
                    '%s', // reservation_date
                    '%s', // reservation_time
                    '%d', // duration
                    '%s', // status
                    '%s', // notes
                    '%d', // override_rules
                    '%d'  // is_active
                );
                
                // Inserir direto
                $insert_result = $wpdb->insert(
                    $table_name,
                    $reservation_data,
                    $formats
                );
                
                // Verificar resultado
                if ($insert_result === false) {
                    throw new Exception('Erro ao inserir reserva: ' . $wpdb->last_error);
                }
                
                $inserted_id = $wpdb->insert_id;
                error_log("Reserva inserida com sucesso! ID: {$inserted_id}");
                
                // Mensagem de sucesso
                echo '<div class="updated"><p>' . esc_html__('Reserva adicionada com sucesso!', 'zuzunely-restaurant') . ' ID: ' . $inserted_id . '</p></div>';
                
            } else {
                error_log('Tentando atualizar reserva existente (ID: ' . $id . ')...');
                
                // Definir formato para cada campo
                $formats = array(
                    '%d', // table_id
                    '%s', // customer_name
                    '%s', // customer_phone
                    '%s', // customer_email
                    '%d', // guests_count
                    '%s', // reservation_date
                    '%s', // reservation_time
                    '%d', // duration
                    '%s', // status
                    '%s', // notes
                    '%d', // override_rules
                    '%d'  // is_active
                );
                
                // Atualizar
                $update_result = $wpdb->update(
                    $table_name,
                    $reservation_data,
                    array('id' => $id),
                    $formats,
                    array('%d')
                );
                
                // Verificar resultado
                if ($update_result === false) {
                    throw new Exception('Erro ao atualizar reserva: ' . $wpdb->last_error);
                }
                
                error_log("Reserva atualizada com sucesso! ID: {$id}");
                
                // Mensagem de sucesso
                echo '<div class="updated"><p>' . esc_html__('Reserva atualizada com sucesso!', 'zuzunely-restaurant') . '</p></div>';
            }
            
        } catch (Exception $e) {
            error_log('EXCEÇÃO: ' . $e->getMessage());
            echo '<div class="error"><p>' . esc_html($e->getMessage()) . '</p></div>';
        }
    }
    
    /**
     * Página de listagem de reservas
     */
    private function list_reservations_page() {
        // Instanciar tabela de listagem
        require_once ZUZUNELY_PLUGIN_DIR . 'class-zuzunely-reservations-list-table.php';
        $list_table = new Zuzunely_Reservations_List_Table();
        $list_table->prepare_items();
        
        // Exibir página
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php echo esc_html__('Reservas', 'zuzunely-restaurant'); ?></h1>
            <a href="<?php echo esc_url(admin_url('admin.php?page=zuzunely-reservations&action=add')); ?>" class="page-title-action"><?php echo esc_html__('Adicionar Nova', 'zuzunely-restaurant'); ?></a>
            <hr class="wp-header-end">
            
            <form method="post">
                <?php $list_table->search_box(__('Buscar', 'zuzunely-restaurant'), 'zuzunely-reservations-search'); ?>
                <?php $list_table->display(); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Página de adicionar reserva
     */
    private function add_reservation_page() {
        // Título da página
        $title = __('Adicionar Nova Reserva', 'zuzunely-restaurant');
        $button_text = __('Adicionar Reserva', 'zuzunely-restaurant');
        
        // Dados para o formulário
        $reservation = array(
            'id' => 0,
            'table_id' => 0,
            'customer_name' => '',
            'customer_phone' => '',
            'customer_email' => '',
            'guests_count' => 1,
            'reservation_date' => date('Y-m-d'),
            'reservation_time' => '19:00:00',
            'duration' => $this->get_default_duration(),
            'status' => 'pending',
            'notes' => '',
            'override_rules' => 0,
            'is_active' => 1
        );
        
        // Exibir formulário
        $this->reservation_form($reservation, $title, $button_text);
    }
    
    /**
     * Página de editar reserva
     */
    private function edit_reservation_page($id) {
        // Verificar se a reserva existe
        $db = new Zuzunely_Reservations_DB();
        $reservation = $db->get_reservation($id);
        
        if (!$reservation) {
            wp_die(__('Reserva não encontrada.', 'zuzunely-restaurant'));
        }
        
        // Título da página
        $title = __('Editar Reserva', 'zuzunely-restaurant');
        $button_text = __('Atualizar Reserva', 'zuzunely-restaurant');
        
        // Exibir formulário
        $this->reservation_form($reservation, $title, $button_text);
    }
    
    /**
     * Confirmar reserva
     */
    private function confirm_reservation($id) {
        // Verificar se o ID é válido
        if ($id <= 0) {
            error_log('Tentativa de confirmar reserva com ID inválido: ' . $id);
            wp_die(__('ID de reserva inválido.', 'zuzunely-restaurant'));
        }
        
        // Instanciar banco de dados
        $db = new Zuzunely_Reservations_DB();
        
        // Verificar se a reserva existe
        $reservation = $db->get_reservation($id);
        if (!$reservation) {
            error_log('Tentativa de confirmar reserva não encontrada. ID: ' . $id);
            wp_die(__('Reserva não encontrada.', 'zuzunely-restaurant'));
        }
        
        error_log('Confirmando reserva ID: ' . $id);
        
        // Atualizar status
        $reservation['status'] = 'confirmed';
        $result = $db->update_reservation($id, $reservation);
        
        // Verificar resultado
        if ($result) {
            // Adicionar mensagem de sucesso
            error_log('Reserva ID: ' . $id . ' confirmada com sucesso');
            
            add_settings_error(
                'zuzunely_reservations',
                'zuzunely_reservation_success',
                __('Reserva confirmada com sucesso!', 'zuzunely-restaurant'),
                'success'
            );
        } else {
            // Adicionar mensagem de erro
            error_log('Erro ao confirmar reserva ID: ' . $id);
            
            add_settings_error(
                'zuzunely_reservations',
                'zuzunely_reservation_error',
                __('Erro ao confirmar a reserva. Por favor, tente novamente.', 'zuzunely-restaurant'),
                'error'
            );
        }
        
        // Redirecionar para a listagem
        wp_redirect(admin_url('admin.php?page=zuzunely-reservations&updated=1'));
        exit;
    }
    
    /**
     * Cancelar reserva
     */
    private function cancel_reservation($id) {
        // Verificar se o ID é válido
        if ($id <= 0) {
            error_log('Tentativa de cancelar reserva com ID inválido: ' . $id);
            wp_die(__('ID de reserva inválido.', 'zuzunely-restaurant'));
        }
        
        // Instanciar banco de dados
        $db = new Zuzunely_Reservations_DB();
        
        // Verificar se a reserva existe
        $reservation = $db->get_reservation($id);
        if (!$reservation) {
            error_log('Tentativa de cancelar reserva não encontrada. ID: ' . $id);
            wp_die(__('Reserva não encontrada.', 'zuzunely-restaurant'));
        }
        
        error_log('Cancelando reserva ID: ' . $id);
        
        // Atualizar status
        $reservation['status'] = 'cancelled';
        $result = $db->update_reservation($id, $reservation);
        
        // Verificar resultado
        if ($result) {
            // Adicionar mensagem de sucesso
            error_log('Reserva ID: ' . $id . ' cancelada com sucesso');
            
            add_settings_error(
                'zuzunely_reservations',
                'zuzunely_reservation_success',
                __('Reserva cancelada com sucesso!', 'zuzunely-restaurant'),
                'success'
            );
        } else {
            // Adicionar mensagem de erro
            error_log('Erro ao cancelar reserva ID: ' . $id);
            
            add_settings_error(
                'zuzunely_reservations',
                'zuzunely_reservation_error',
                __('Erro ao cancelar a reserva. Por favor, tente novamente.', 'zuzunely-restaurant'),
                'error'
            );
        }
        
        // Redirecionar para a listagem
        wp_redirect(admin_url('admin.php?page=zuzunely-reservations&updated=1'));
        exit;
    }
    
    /**
     * Formulário de reserva - VERSÃO EMERGENCIAL
     */
    private function reservation_form($reservation, $title, $button_text) {
        // Obter dados necessários
        $status_list = Zuzunely_Reservations_DB::get_status_list();
        $db = new Zuzunely_Reservations_DB();
        
        // Verificar se é modo de busca ou edição direta
        $search_mode = ($reservation['id'] == 0);
        $edit_mode = !$search_mode;
        
        // Se em modo de busca e temos post data, usar esses dados
        if ($search_mode && isset($_POST['zuzunely_search_reservation'])) {
            $reservation['guests_count'] = isset($_POST['guests_count']) ? intval($_POST['guests_count']) : 1;
            $reservation['reservation_date'] = isset($_POST['reservation_date']) ? sanitize_text_field($_POST['reservation_date']) : date('Y-m-d');
            $reservation['reservation_time'] = isset($_POST['reservation_time']) ? sanitize_text_field($_POST['reservation_time']) : '19:00:00';
            $reservation['override_rules'] = isset($_POST['override_rules']) ? 1 : 0;
            
            // Buscar mesas disponíveis
            $available_tables = $db->get_available_tables(
                $reservation['reservation_date'],
                $reservation['reservation_time'],
                $reservation['guests_count'],
                $reservation['override_rules']
            );
        }
        
        // URL de ação para o formulário - SIMPLIFICADA
        $action_url = $edit_mode 
            ? admin_url('admin.php?page=zuzunely-reservations&action=edit&id=' . $reservation['id'])
            : admin_url('admin.php?page=zuzunely-reservations&action=add');
        
        // Obter configuração do máximo de pessoas
        $settings = get_option('zuzunely_restaurant_settings', array());
        $max_guests = isset($settings['max_guests_per_reservation']) ? intval($settings['max_guests_per_reservation']) : 12;
        
        // Formulário
        ?>
        <div class="wrap">
            <h1><?php echo esc_html($title); ?></h1>
            
            <?php settings_errors('zuzunely_reservations'); ?>
            <?php settings_errors('zuzunely_db_check'); ?>
            <?php settings_errors('zuzunely_logger'); ?>
            
            <div class="zuzunely-errors"></div>
            
            <?php if ($search_mode && !isset($_POST['zuzunely_search_reservation'])): ?>
                <!-- Formulário de busca inicial -->
                <form method="post" action="<?php echo esc_url($action_url); ?>">
                    <?php wp_nonce_field('zuzunely_search_reservation'); ?>
                    <input type="hidden" name="zuzunely_search_reservation" value="1">
                    
                    <div class="zuzunely-form-section">
                        <h2><?php echo esc_html__('Buscar Mesas Disponíveis', 'zuzunely-restaurant'); ?></h2>
                        
                        <table class="form-table">
                            <tr>
                                <th><label for="guests_count"><?php echo esc_html__('Número de Pessoas', 'zuzunely-restaurant'); ?></label></th>
                                <td>
                                    <input type="number" name="guests_count" id="guests_count" class="regular-text" min="1" max="<?php echo esc_attr($max_guests); ?>" value="<?php echo esc_attr($reservation['guests_count']); ?>" required>
                                    <p class="description"><?php echo sprintf(__('Máximo: %d pessoas por reserva', 'zuzunely-restaurant'), $max_guests); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="reservation_date"><?php echo esc_html__('Data', 'zuzunely-restaurant'); ?></label></th>
                                <td>
                                    <input type="date" name="reservation_date" id="reservation_date" class="regular-text" value="<?php echo esc_attr($reservation['reservation_date']); ?>" required>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="reservation_time"><?php echo esc_html__('Horário', 'zuzunely-restaurant'); ?></label></th>
                                <td>
                                    <input type="time" name="reservation_time" id="reservation_time" class="regular-text" value="<?php echo esc_attr($reservation['reservation_time']); ?>" required>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="override_rules"><?php echo esc_html__('Desconsiderar Regras', 'zuzunely-restaurant'); ?></label></th>
                                <td>
                                    <input type="checkbox" name="override_rules" id="override_rules" value="1" <?php checked($reservation['override_rules'], 1); ?>>
                                    <span class="description"><?php echo esc_html__('Marque para mostrar todas as mesas, independente de disponibilidade', 'zuzunely-restaurant'); ?></span>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php echo esc_attr__('Buscar Mesas Disponíveis', 'zuzunely-restaurant'); ?>">
                        </p>
                    </div>
                </form>
            <?php endif; ?>
            
            <?php if ($edit_mode || isset($_POST['zuzunely_search_reservation'])): ?>
                <!-- Formulário principal de reserva - SIMPLIFICADO (MESMO URL)  -->
                <form method="post" action="<?php echo esc_url($action_url); ?>" id="zuzunely-reservation-form">
                    <?php wp_nonce_field('zuzunely_reservation'); ?>
                    
                    <!-- Compatibilidade com método anterior -->
                    <input type="hidden" name="zuzunely_reservation" value="1">
                    
                    <!-- Resto dos campos do formulário -->
                    <input type="hidden" name="id" value="<?php echo esc_attr($reservation['id']); ?>">
                    <input type="hidden" name="reservation_date" value="<?php echo esc_attr($reservation['reservation_date']); ?>">
                    <input type="hidden" name="reservation_time" value="<?php echo esc_attr($reservation['reservation_time']); ?>">
                    <input type="hidden" name="guests_count" value="<?php echo esc_attr($reservation['guests_count']); ?>">
                    <input type="hidden" name="override_rules" value="<?php echo esc_attr($reservation['override_rules']); ?>">
                    
                    <div class="zuzunely-form-section">
                        <h2><?php echo esc_html__('Detalhes da Reserva', 'zuzunely-restaurant'); ?></h2>
                        
                        <table class="form-table">
                            <?php if ($search_mode): ?>
                                <tr>
                                    <th><label for="table_id"><?php echo esc_html__('Mesa', 'zuzunely-restaurant'); ?></label></th>
                                    <td>
                                        <select name="table_id" id="table_id" class="regular-text" required>
                                            <option value=""><?php echo esc_html__('Selecione uma mesa', 'zuzunely-restaurant'); ?></option>
                                            <?php 
                                            if (!empty($available_tables)) {
                                                foreach ($available_tables as $table) {
                                                    echo '<option value="' . esc_attr($table['id']) . '">' . 
                                                          esc_html($table['name'] . ' - ' . __('Salão:', 'zuzunely-restaurant') . ' ' . $table['saloon_name'] . ' - ' . __('Capacidade:', 'zuzunely-restaurant') . ' ' . $table['capacity']) . 
                                                          '</option>';
                                                }
                                            } else {
                                                echo '<option value="" disabled>' . esc_html__('Nenhuma mesa disponível para os critérios selecionados', 'zuzunely-restaurant') . '</option>';
                                            }
                                            ?>
                                        </select>
                                        
                                        <?php if (empty($available_tables)): ?>
                                            <p class="description error"><?php echo esc_html__('Não há mesas disponíveis para os critérios selecionados.', 'zuzunely-restaurant'); ?></p>
                                            <p class="submit">
                                                <a href="<?php echo esc_url(admin_url('admin.php?page=zuzunely-reservations&action=add')); ?>" class="button"><?php echo esc_html__('Voltar', 'zuzunely-restaurant'); ?></a>
                                            </p>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php echo esc_html__('Data e Hora', 'zuzunely-restaurant'); ?></th>
                                    <td>
                                        <strong><?php echo esc_html(date('d/m/Y', strtotime($reservation['reservation_date'])) . ' ' . date('H:i', strtotime($reservation['reservation_time']))); ?></strong>
                                        <p class="description">
                                            <?php echo esc_html__('Número de pessoas:', 'zuzunely-restaurant'); ?> <strong><?php echo esc_html($reservation['guests_count']); ?></strong>
                                        </p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <tr>
                                    <th><label for="table_id"><?php echo esc_html__('Mesa', 'zuzunely-restaurant'); ?></label></th>
                                    <td>
                                        <select name="table_id" id="table_id" class="regular-text" required>
                                            <option value=""><?php echo esc_html__('Selecione uma mesa', 'zuzunely-restaurant'); ?></option>
                                            <?php 
                                            $tables_db = new Zuzunely_Tables_DB();
                                            $tables = $tables_db->get_tables(array('number' => 100));
                                            
                                            foreach ($tables as $table) {
                                                echo '<option value="' . esc_attr($table['id']) . '" ' . selected($reservation['table_id'], $table['id'], false) . '>' . 
                                                      esc_html($table['name'] . ' - ' . __('Salão:', 'zuzunely-restaurant') . ' ' . $table['saloon_name'] . ' - ' . __('Capacidade:', 'zuzunely-restaurant') . ' ' . $table['capacity']) . 
                                                      '</option>';
                                            }
                                            ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="reservation_date"><?php echo esc_html__('Data', 'zuzunely-restaurant'); ?></label></th>
                                    <td>
                                        <input type="date" name="reservation_date" id="reservation_date" class="regular-text" value="<?php echo esc_attr($reservation['reservation_date']); ?>" required>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="reservation_time"><?php echo esc_html__('Horário', 'zuzunely-restaurant'); ?></label></th>
                                    <td>
                                        <input type="time" name="reservation_time" id="reservation_time" class="regular-text" value="<?php echo esc_attr($reservation['reservation_time']); ?>" required>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="guests_count"><?php echo esc_html__('Número de Pessoas', 'zuzunely-restaurant'); ?></label></th>
                                    <td>
                                        <input type="number" name="guests_count" id="guests_count" class="regular-text" min="1" max="<?php echo esc_attr($max_guests); ?>" value="<?php echo esc_attr($reservation['guests_count']); ?>" required>
                                        <p class="description"><?php echo sprintf(__('Máximo: %d pessoas por reserva', 'zuzunely-restaurant'), $max_guests); ?></p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                            
                            <tr>
                                <th><label for="customer_name"><?php echo esc_html__('Nome do Cliente', 'zuzunely-restaurant'); ?></label></th>
                                <td>
                                    <input type="text" name="customer_name" id="customer_name" class="regular-text" value="<?php echo esc_attr($reservation['customer_name']); ?>" required>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="customer_phone"><?php echo esc_html__('Telefone', 'zuzunely-restaurant'); ?></label></th>
                                <td>
                                    <input type="text" name="customer_phone" id="customer_phone" class="regular-text" value="<?php echo esc_attr($reservation['customer_phone']); ?>" required>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="customer_email"><?php echo esc_html__('Email', 'zuzunely-restaurant'); ?></label></th>
                                <td>
                                    <input type="email" name="customer_email" id="customer_email" class="regular-text" value="<?php echo esc_attr($reservation['customer_email']); ?>" required>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="duration"><?php echo esc_html__('Duração (minutos)', 'zuzunely-restaurant'); ?></label></th>
                                <td>
                                    <input type="number" name="duration" id="duration" class="regular-text" min="30" max="240" step="15" value="<?php echo esc_attr($reservation['duration']); ?>" required>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="status"><?php echo esc_html__('Status', 'zuzunely-restaurant'); ?></label></th>
                                <td>
                                    <select name="status" id="status" class="regular-text">
                                        <?php foreach ($status_list as $status_key => $status_label): ?>
                                            <option value="<?php echo esc_attr($status_key); ?>" <?php selected($reservation['status'], $status_key); ?>>
                                                <?php echo esc_html($status_label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="notes"><?php echo esc_html__('Observações', 'zuzunely-restaurant'); ?></label></th>
                                <td>
                                    <textarea name="notes" id="notes" class="large-text" rows="5"><?php echo esc_textarea($reservation['notes']); ?></textarea>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="is_active"><?php echo esc_html__('Ativo', 'zuzunely-restaurant'); ?></label></th>
                                <td>
                                    <input type="checkbox" name="is_active" id="is_active" value="1" <?php checked($reservation['is_active'], 1); ?>>
                                </td>
                            </tr>
                            <?php if ($search_mode): ?>
                                <tr>
                                    <th><label for="override_rules_display"><?php echo esc_html__('Desconsiderar Regras', 'zuzunely-restaurant'); ?></label></th>
                                    <td>
                                        <input type="checkbox" name="override_rules_display" id="override_rules_display" value="1" <?php checked($reservation['override_rules'], 1); ?> disabled>
                                        <span class="description"><?php echo esc_html__('Opção selecionada anteriormente', 'zuzunely-restaurant'); ?></span>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                    
                    <p class="submit">
                        <?php if (!$search_mode || !empty($available_tables)): ?>
                            <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php echo esc_attr($button_text); ?>">
                        <?php endif; ?>
                        
                        <a href="<?php echo esc_url(admin_url('admin.php?page=zuzunely-reservations')); ?>" class="button"><?php echo esc_html__('Cancelar', 'zuzunely-restaurant'); ?></a>
                    </p>
                </form>
            <?php endif; ?>
        </div>
        <?php
    }
    
    // Método antigo de processamento - mantido apenas para compatibilidade
    public function process_reservation_form() {
        // Este método está vazio deliberadamente
        // Todo o processamento agora é feito em process_reservation_form_direct()
        error_log('AVISO: Método process_reservation_form() chamado, mas foi substituído por process_reservation_form_direct()');
    }
    
    /**
     * Excluir reserva
     */
    private function delete_reservation($id) {
        // Verificar se o ID é válido
        if ($id <= 0) {
            error_log('Tentativa de excluir reserva com ID inválido: ' . $id);
            wp_die(__('ID de reserva inválido.', 'zuzunely-restaurant'));
        }
        
        // Instanciar banco de dados
        $db = new Zuzunely_Reservations_DB();
        
        // Verificar se a reserva existe
        $reservation = $db->get_reservation($id);
        if (!$reservation) {
            error_log('Tentativa de excluir reserva não encontrada. ID: ' . $id);
            wp_die(__('Reserva não encontrada.', 'zuzunely-restaurant'));
        }
        
        error_log('Excluindo reserva ID: ' . $id);
        
        // Excluir reserva
        $result = $db->delete_reservation($id);
        
        // Verificar resultado
        if ($result) {
            // Adicionar mensagem de sucesso
            error_log('Reserva ID: ' . $id . ' excluída com sucesso');
            
            add_settings_error(
                'zuzunely_reservations',
                'zuzunely_reservation_success',
                __('Reserva excluída com sucesso!', 'zuzunely-restaurant'),
                'success'
            );
        } else {
            // Adicionar mensagem de erro
            error_log('Erro ao excluir reserva ID: ' . $id);
            
            add_settings_error(
                'zuzunely_reservations',
                'zuzunely_reservation_error',
                __('Erro ao excluir a reserva. Por favor, tente novamente.', 'zuzunely-restaurant'),
                'error'
            );
        }
        
        // Redirecionar para a listagem
        wp_redirect(admin_url('admin.php?page=zuzunely-reservations&updated=1'));
        exit;
    }
    
    /**
     * Obter duração padrão de reservas das configurações
     */
    private function get_default_duration() {
        $settings = get_option('zuzunely_restaurant_settings', array());
        $duration = isset($settings['default_reservation_duration']) ? intval($settings['default_reservation_duration']) : 60;
        return $duration;
    }
}