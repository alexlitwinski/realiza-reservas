<?php
/**
 * Classe para gerenciar as configurações do plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class Zuzunely_Settings {
    
    // Nome da opção no banco de dados
    private static $option_name = 'zuzunely_restaurant_settings';
    
    /**
     * Página administrativa de configurações
     */
    public static function admin_page() {
        // Verificar permissões
        if (!current_user_can('manage_options')) {
            wp_die(__('Você não tem permissão para acessar esta página.', 'zuzunely-restaurant'));
        }
        
        // Obter aba atual
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
        
        // Processar formulário se enviado
        if (isset($_POST['zuzunely_settings_submit'])) {
            // Verificar nonce
            check_admin_referer('zuzunely_settings');
            
            // Processar configurações por aba
            if ($active_tab === 'general') {
                self::process_general_settings();
            } elseif ($active_tab === 'brevo') {
                self::process_brevo_settings();
            }
        }
        
        // Exibir página
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Configurações - Zuzunely Restaurant', 'zuzunely-restaurant'); ?></h1>
            
            <?php settings_errors('zuzunely_settings'); ?>
            
            <h2 class="nav-tab-wrapper">
                <?php
                $tabs = apply_filters('zuzunely_settings_tabs', array(
                    'general' => __('Geral', 'zuzunely-restaurant')
                ));
                
                foreach ($tabs as $tab_key => $tab_label) {
                    $active_class = ($active_tab === $tab_key) ? ' nav-tab-active' : '';
                    echo '<a href="?page=zuzunely-settings&tab=' . esc_attr($tab_key) . '" class="nav-tab' . $active_class . '">' . esc_html($tab_label) . '</a>';
                }
                ?>
            </h2>
            
            <form method="post" action="">
                <?php wp_nonce_field('zuzunely_settings'); ?>
                <input type="hidden" name="zuzunely_settings_submit" value="1">
                
                <?php
                // Renderizar conteúdo da aba
                do_action('zuzunely_settings_tab_content_' . $active_tab);
                
                // Se for a aba geral, renderizar diretamente
                if ($active_tab === 'general') {
                    self::render_general_tab();
                }
                ?>
                
                <p class="submit">
                    <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php echo esc_attr__('Salvar Alterações', 'zuzunely-restaurant'); ?>" />
                </p>
            </form>
        </div>
        <?php
    }
    
    /**
     * Processar configurações gerais
     */
    private static function process_general_settings() {
        // Obter configurações existentes
        $settings = get_option(self::$option_name, array());
        
        $settings = array_merge($settings, array(
            'default_reservation_duration' => isset($_POST['default_reservation_duration']) ? intval($_POST['default_reservation_duration']) : 60,
            'reservation_interval' => isset($_POST['reservation_interval']) ? intval($_POST['reservation_interval']) : 0,
            'opening_time' => isset($_POST['opening_time']) ? sanitize_text_field($_POST['opening_time']) : '11:00',
            'closing_time' => isset($_POST['closing_time']) ? sanitize_text_field($_POST['closing_time']) : '23:00',
            'working_days' => isset($_POST['working_days']) ? array_map('sanitize_text_field', $_POST['working_days']) : array(),
            'min_advance_time' => isset($_POST['min_advance_time']) ? intval($_POST['min_advance_time']) : 2,
            'max_advance_time' => isset($_POST['max_advance_time']) ? intval($_POST['max_advance_time']) : 30,
            'max_guests_per_reservation' => isset($_POST['max_guests_per_reservation']) ? intval($_POST['max_guests_per_reservation']) : 12,
            'frontend_table_selection_mode' => isset($_POST['frontend_table_selection_mode']) ? sanitize_text_field($_POST['frontend_table_selection_mode']) : 'table',
            'frontend_auto_assign_strategy' => isset($_POST['frontend_auto_assign_strategy']) ? sanitize_text_field($_POST['frontend_auto_assign_strategy']) : 'smallest_suitable',
        ));
        
        // Salvar configurações
        update_option(self::$option_name, $settings);
        
        // Adicionar mensagem de sucesso
        add_settings_error(
            'zuzunely_settings',
            'zuzunely_settings_updated',
            __('Configurações salvas com sucesso!', 'zuzunely-restaurant'),
            'success'
        );
    }
    
    /**
     * Processar configurações do Brevo
     */
    private static function process_brevo_settings() {
        $brevo_option_name = 'zuzunely_brevo_settings';
        
        // Obter valores do Brevo
        $brevo_settings = array(
            'api_key' => isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '',
            'enable_email_notifications' => isset($_POST['enable_email_notifications']) ? 1 : 0,
            'enable_whatsapp_notifications' => isset($_POST['enable_whatsapp_notifications']) ? 1 : 0,
            'whatsapp_sender' => isset($_POST['whatsapp_sender']) ? sanitize_text_field($_POST['whatsapp_sender']) : '',
            'email_confirmation_template' => isset($_POST['email_confirmation_template']) ? intval($_POST['email_confirmation_template']) : 0,
            'email_reminder_template' => isset($_POST['email_reminder_template']) ? intval($_POST['email_reminder_template']) : 0,
            'email_problem_template' => isset($_POST['email_problem_template']) ? intval($_POST['email_problem_template']) : 0,
            'whatsapp_confirmation_template' => isset($_POST['whatsapp_confirmation_template']) ? intval($_POST['whatsapp_confirmation_template']) : 0,
            'whatsapp_reminder_template' => isset($_POST['whatsapp_reminder_template']) ? intval($_POST['whatsapp_reminder_template']) : 0,
            'whatsapp_problem_template' => isset($_POST['whatsapp_problem_template']) ? intval($_POST['whatsapp_problem_template']) : 0,
            'reminder_hours_before' => isset($_POST['reminder_hours_before']) ? intval($_POST['reminder_hours_before']) : 24,
        );
        
        // Obter configurações atuais do Brevo
        $current_brevo_settings = get_option($brevo_option_name, array());
        
        // Salvar configurações do Brevo
        update_option($brevo_option_name, $brevo_settings);
        
        // Se a chave API mudou, limpar cache de templates
        if (isset($current_brevo_settings['api_key']) && $current_brevo_settings['api_key'] !== $brevo_settings['api_key']) {
            delete_transient('zuzunely_brevo_email_templates');
            delete_transient('zuzunely_brevo_whatsapp_templates');
        }
        
        // Adicionar mensagem de sucesso
        add_settings_error(
            'zuzunely_settings',
            'zuzunely_brevo_settings_updated',
            __('Configurações do Brevo salvas com sucesso!', 'zuzunely-restaurant'),
            'success'
        );
    }
    
    /**
     * Renderizar aba de configurações gerais
     */
    private static function render_general_tab() {
        // Obter configurações atuais
        $settings = get_option(self::$option_name, array());
        
        // Valores padrão
        $defaults = array(
            'default_reservation_duration' => 60,
            'reservation_interval' => 0,
            'opening_time' => '11:00',
            'closing_time' => '23:00',
            'working_days' => array('1', '2', '3', '4', '5', '6', '0'),
            'min_advance_time' => 2,
            'max_advance_time' => 30,
            'max_guests_per_reservation' => 12,
            'frontend_table_selection_mode' => 'table',
            'frontend_auto_assign_strategy' => 'smallest_suitable',
        );
        
        $settings = wp_parse_args($settings, $defaults);
        
        ?>
        <h3><?php echo esc_html__('Configurações Gerais', 'zuzunely-restaurant'); ?></h3>
        <table class="form-table">
            <tr>
                <th><label for="default_reservation_duration"><?php echo esc_html__('Duração Padrão da Reserva (minutos)', 'zuzunely-restaurant'); ?></label></th>
                <td>
                    <input type="number" name="default_reservation_duration" id="default_reservation_duration" class="regular-text" min="30" max="300" step="15" value="<?php echo esc_attr($settings['default_reservation_duration']); ?>" />
                    <p class="description"><?php echo esc_html__('Duração padrão em minutos para cada reserva.', 'zuzunely-restaurant'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="reservation_interval"><?php echo esc_html__('Intervalo Entre Reservas (minutos)', 'zuzunely-restaurant'); ?></label></th>
                <td>
                    <input type="number" name="reservation_interval" id="reservation_interval" class="regular-text" min="0" max="60" step="5" value="<?php echo esc_attr($settings['reservation_interval']); ?>" />
                    <p class="description"><?php echo esc_html__('Tempo de intervalo entre reservas para limpeza/preparação. Use 0 para desabilitar.', 'zuzunely-restaurant'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="max_guests_per_reservation"><?php echo esc_html__('Máximo de Pessoas por Reserva', 'zuzunely-restaurant'); ?></label></th>
                <td>
                    <input type="number" name="max_guests_per_reservation" id="max_guests_per_reservation" class="regular-text" min="1" max="50" value="<?php echo esc_attr($settings['max_guests_per_reservation']); ?>" />
                    <p class="description"><?php echo esc_html__('Número máximo de pessoas permitido em uma única reserva.', 'zuzunely-restaurant'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="opening_time"><?php echo esc_html__('Horário de Abertura', 'zuzunely-restaurant'); ?></label></th>
                <td>
                    <input type="time" name="opening_time" id="opening_time" class="regular-text" value="<?php echo esc_attr($settings['opening_time']); ?>" />
                </td>
            </tr>
            <tr>
                <th><label for="closing_time"><?php echo esc_html__('Horário de Fechamento', 'zuzunely-restaurant'); ?></label></th>
                <td>
                    <input type="time" name="closing_time" id="closing_time" class="regular-text" value="<?php echo esc_attr($settings['closing_time']); ?>" />
                </td>
            </tr>
            <tr>
                <th><label><?php echo esc_html__('Dias de Funcionamento', 'zuzunely-restaurant'); ?></label></th>
                <td>
                    <?php
                    $days = array(
                        '0' => __('Domingo', 'zuzunely-restaurant'),
                        '1' => __('Segunda-feira', 'zuzunely-restaurant'),
                        '2' => __('Terça-feira', 'zuzunely-restaurant'),
                        '3' => __('Quarta-feira', 'zuzunely-restaurant'),
                        '4' => __('Quinta-feira', 'zuzunely-restaurant'),
                        '5' => __('Sexta-feira', 'zuzunely-restaurant'),
                        '6' => __('Sábado', 'zuzunely-restaurant')
                    );
                    
                    foreach ($days as $day_key => $day_label) {
                        $checked = in_array($day_key, $settings['working_days']) ? 'checked' : '';
                        echo '<label><input type="checkbox" name="working_days[]" value="' . esc_attr($day_key) . '" ' . $checked . '> ' . esc_html($day_label) . '</label><br>';
                    }
                    ?>
                </td>
            </tr>
            <tr>
                <th><label for="min_advance_time"><?php echo esc_html__('Antecedência Mínima (horas)', 'zuzunely-restaurant'); ?></label></th>
                <td>
                    <input type="number" name="min_advance_time" id="min_advance_time" class="regular-text" min="0" max="72" value="<?php echo esc_attr($settings['min_advance_time']); ?>" />
                    <p class="description"><?php echo esc_html__('Horas mínimas de antecedência para fazer uma reserva.', 'zuzunely-restaurant'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="max_advance_time"><?php echo esc_html__('Antecedência Máxima (dias)', 'zuzunely-restaurant'); ?></label></th>
                <td>
                    <input type="number" name="max_advance_time" id="max_advance_time" class="regular-text" min="1" max="365" value="<?php echo esc_attr($settings['max_advance_time']); ?>" />
                    <p class="description"><?php echo esc_html__('Número máximo de dias de antecedência para fazer uma reserva.', 'zuzunely-restaurant'); ?></p>
                </td>
            </tr>
        </table>
        
        <h3><?php echo esc_html__('Configurações do Frontend de Reservas', 'zuzunely-restaurant'); ?></h3>
        <table class="form-table">
            <tr>
                <th><label for="frontend_table_selection_mode"><?php echo esc_html__('Modo de Seleção de Mesa', 'zuzunely-restaurant'); ?></label></th>
                <td>
                    <select name="frontend_table_selection_mode" id="frontend_table_selection_mode" class="regular-text">
                        <option value="table" <?php selected($settings['frontend_table_selection_mode'], 'table'); ?>>
                            <?php echo esc_html__('Cliente escolhe a mesa específica', 'zuzunely-restaurant'); ?>
                        </option>
                        <option value="saloon" <?php selected($settings['frontend_table_selection_mode'], 'saloon'); ?>>
                            <?php echo esc_html__('Cliente escolhe o salão (sistema escolhe a mesa)', 'zuzunely-restaurant'); ?>
                        </option>
                        <option value="area" <?php selected($settings['frontend_table_selection_mode'], 'area'); ?>>
                            <?php echo esc_html__('Cliente escolhe área interna/externa (sistema escolhe tudo)', 'zuzunely-restaurant'); ?>
                        </option>
                    </select>
                    <p class="description">
                        <?php echo esc_html__('Define como os clientes podem escolher onde sentar no formulário de reservas. A área interna/externa é definida automaticamente no cadastro de cada salão.', 'zuzunely-restaurant'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th><label for="frontend_auto_assign_strategy"><?php echo esc_html__('Estratégia de Atribuição Automática', 'zuzunely-restaurant'); ?></label></th>
                <td>
                    <select name="frontend_auto_assign_strategy" id="frontend_auto_assign_strategy" class="regular-text">
                        <option value="smallest_suitable" <?php selected($settings['frontend_auto_assign_strategy'], 'smallest_suitable'); ?>>
                            <?php echo esc_html__('Menor mesa adequada', 'zuzunely-restaurant'); ?>
                        </option>
                        <option value="largest_available" <?php selected($settings['frontend_auto_assign_strategy'], 'largest_available'); ?>>
                            <?php echo esc_html__('Maior mesa disponível', 'zuzunely-restaurant'); ?>
                        </option>
                        <option value="random" <?php selected($settings['frontend_auto_assign_strategy'], 'random'); ?>>
                            <?php echo esc_html__('Aleatória', 'zuzunely-restaurant'); ?>
                        </option>
                    </select>
                    <p class="description">
                        <?php echo esc_html__('Como o sistema deve escolher a mesa quando não é selecionada pelo cliente.', 'zuzunely-restaurant'); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Criar configurações padrão se não existirem
     */
    public static function create_default_settings() {
        $options = get_option(self::$option_name);
        
        if ($options === false) {
            $default_options = array(
                'default_reservation_duration' => 60,
                'reservation_interval' => 0,
                'opening_time' => '11:00',
                'closing_time' => '23:00',
                'working_days' => array('1', '2', '3', '4', '5', '6', '0'),
                'min_advance_time' => 2,
                'max_advance_time' => 30,
                'max_guests_per_reservation' => 12,
                'frontend_table_selection_mode' => 'table',
                'frontend_auto_assign_strategy' => 'smallest_suitable',
            );
            
            update_option(self::$option_name, $default_options);
            Zuzunely_Logger::info('Configurações padrão criadas');
        }
    }
    
    /**
     * Obter uma configuração específica
     */
    public static function get_setting($key, $default = null) {
        $settings = get_option(self::$option_name, array());
        return isset($settings[$key]) ? $settings[$key] : $default;
    }
    
    /**
     * Obter todas as configurações
     */
    public static function get_settings() {
        return get_option(self::$option_name, array());
    }
}