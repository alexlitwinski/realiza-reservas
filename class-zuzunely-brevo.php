<?php
/**
 * Classe para integração com Brevo (antigo Sendinblue)
 * para envio de e-mails e mensagens de WhatsApp
 */

if (!defined('ABSPATH')) {
    exit;
}

class Zuzunely_Brevo {
    // URL base da API do Brevo
    private $api_url = 'https://api.brevo.com/v3';
    
    // Chave API
    private $api_key = '';
    
    // Nome da opção no banco de dados
    private static $option_name = 'zuzunely_brevo_settings';
    
    // Instância única
    private static $instance = null;
    
    /**
     * Obter instância única (Singleton)
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }
    
    /**
     * Construtor
     */
    private function __construct() {
        // Inicializar opções padrão se não existirem
        self::create_default_settings();
        
        // Obter configurações
        $settings = get_option(self::$option_name);
        
        // Configurar chave API
        if (isset($settings['api_key'])) {
            $this->api_key = $settings['api_key'];
        }
        
        // Adicionar aba nas configurações
        add_filter('zuzunely_settings_tabs', array($this, 'add_settings_tab'));
        add_action('zuzunely_settings_tab_content_brevo', array($this, 'render_settings_tab'));
        
        // Adicionar hooks para AJAX
        add_action('wp_ajax_zuzunely_test_brevo_email', array($this, 'test_email_connection'));
        add_action('wp_ajax_zuzunely_test_brevo_whatsapp', array($this, 'test_whatsapp_connection'));
        add_action('wp_ajax_zuzunely_refresh_brevo_templates', array($this, 'ajax_refresh_templates'));
        add_action('wp_ajax_zuzunely_debug_brevo_api', array($this, 'debug_brevo_api'));
        add_action('wp_ajax_zuzunely_debug_brevo_whatsapp_api', array($this, 'debug_brevo_whatsapp_api'));
        
        // Hooks para envio de notificações
        add_action('zuzunely_after_reservation_confirmed', array($this, 'send_reservation_confirmation'));
    }
    
    /**
     * Adicionar aba de configurações do Brevo
     * 
     * @param array $tabs Abas existentes
     * @return array Abas atualizadas
     */
    public function add_settings_tab($tabs) {
        $tabs['brevo'] = __('Brevo', 'zuzunely-restaurant');
        return $tabs;
    }
    
    /**
     * Renderizar aba de configurações do Brevo
     * VERSÃO CORRIGIDA - Remove o formulário aninhado
     */
    public function render_settings_tab() {
        // Verificar permissões
        if (!current_user_can('manage_options')) {
            wp_die(__('Você não tem permissão para acessar esta página.', 'zuzunely-restaurant'));
        }
        
        // Obter configurações atuais
        $settings = get_option(self::$option_name);
        
        // Obter templates de e-mail e WhatsApp
        $email_templates = $this->get_email_templates();
        $whatsapp_templates = $this->get_whatsapp_templates();
        
        ?>
        <div class="zuzunely-brevo-settings">
            
            <h3><?php echo esc_html__('Configurações da API', 'zuzunely-restaurant'); ?></h3>
            <table class="form-table">
                <tr>
                    <th><label for="api_key"><?php echo esc_html__('Chave API', 'zuzunely-restaurant'); ?></label></th>
                    <td>
                        <input type="text" name="api_key" id="api_key" value="<?php echo esc_attr($settings['api_key']); ?>" class="regular-text" />
                        <p class="description">
                            <?php echo esc_html__('Chave API do Brevo. Você pode encontrá-la no painel do Brevo em SMTP & API > API Keys.', 'zuzunely-restaurant'); ?>
                            <a href="https://app.brevo.com/settings/keys/api" target="_blank"><?php echo esc_html__('Acessar configurações', 'zuzunely-restaurant'); ?></a>
                        </p>
                    </td>
                </tr>
            </table>
            
            <h3><?php echo esc_html__('Templates de E-mail', 'zuzunely-restaurant'); ?></h3>
            <p>
                <button type="button" id="refresh_brevo_templates" class="button button-secondary">
                    <?php echo esc_html__('Atualizar Templates', 'zuzunely-restaurant'); ?>
                </button>
                <button type="button" id="debug_brevo_api" class="button button-secondary">
                    <?php echo esc_html__('Debug API', 'zuzunely-restaurant'); ?>
                </button>
                <span id="refresh_templates_status"></span>
            </p>
            <table class="form-table">
                <tr>
                    <th><label for="enable_email_notifications"><?php echo esc_html__('Ativar Notificações por E-mail', 'zuzunely-restaurant'); ?></label></th>
                    <td>
                        <input type="checkbox" name="enable_email_notifications" id="enable_email_notifications" value="1" <?php checked(1, $settings['enable_email_notifications']); ?> />
                        <p class="description"><?php echo esc_html__('Habilitar o envio de notificações por e-mail através do Brevo.', 'zuzunely-restaurant'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="email_confirmation_template"><?php echo esc_html__('Template de Confirmação', 'zuzunely-restaurant'); ?></label></th>
                    <td>
                        <select name="email_confirmation_template" id="email_confirmation_template" class="regular-text">
                            <option value="0"><?php echo esc_html__('Selecione um template', 'zuzunely-restaurant'); ?></option>
                            <?php
                            if (is_array($email_templates)) {
                                foreach ($email_templates as $template) {
                                    printf(
                                        '<option value="%s" %s>%s</option>',
                                        esc_attr($template['id']),
                                        selected($settings['email_confirmation_template'], $template['id'], false),
                                        esc_html($template['name'])
                                    );
                                }
                            }
                            ?>
                        </select>
                        <p class="description"><?php echo esc_html__('Template de e-mail para confirmação de reservas.', 'zuzunely-restaurant'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="email_reminder_template"><?php echo esc_html__('Template de Lembrete', 'zuzunely-restaurant'); ?></label></th>
                    <td>
                        <select name="email_reminder_template" id="email_reminder_template" class="regular-text">
                            <option value="0"><?php echo esc_html__('Selecione um template', 'zuzunely-restaurant'); ?></option>
                            <?php
                            if (is_array($email_templates)) {
                                foreach ($email_templates as $template) {
                                    printf(
                                        '<option value="%s" %s>%s</option>',
                                        esc_attr($template['id']),
                                        selected($settings['email_reminder_template'], $template['id'], false),
                                        esc_html($template['name'])
                                    );
                                }
                            }
                            ?>
                        </select>
                        <p class="description"><?php echo esc_html__('Template de e-mail para lembrete de reservas.', 'zuzunely-restaurant'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="email_problem_template"><?php echo esc_html__('Template de Problema', 'zuzunely-restaurant'); ?></label></th>
                    <td>
                        <select name="email_problem_template" id="email_problem_template" class="regular-text">
                            <option value="0"><?php echo esc_html__('Selecione um template', 'zuzunely-restaurant'); ?></option>
                            <?php
                            if (is_array($email_templates)) {
                                foreach ($email_templates as $template) {
                                    printf(
                                        '<option value="%s" %s>%s</option>',
                                        esc_attr($template['id']),
                                        selected($settings['email_problem_template'], $template['id'], false),
                                        esc_html($template['name'])
                                    );
                                }
                            }
                            ?>
                        </select>
                        <p class="description"><?php echo esc_html__('Template de e-mail para notificação de problemas com reservas.', 'zuzunely-restaurant'); ?></p>
                    </td>
                </tr>
                <tr>
                    <td colspan="2">
                        <button type="button" id="test_brevo_email" class="button button-secondary">
                            <?php echo esc_html__('Testar Envio de E-mail', 'zuzunely-restaurant'); ?>
                        </button>
                        <span id="test_email_status"></span>
                    </td>
                </tr>
            </table>
            
            <h3><?php echo esc_html__('Templates de WhatsApp', 'zuzunely-restaurant'); ?></h3>
            <p>
                <button type="button" id="debug_brevo_whatsapp_api" class="button button-secondary">
                    <?php echo esc_html__('Debug API WhatsApp', 'zuzunely-restaurant'); ?>
                </button>
                <span id="debug_whatsapp_api_status"></span>
            </p>
            <table class="form-table">
                <tr>
                    <th><label for="enable_whatsapp_notifications"><?php echo esc_html__('Ativar Notificações por WhatsApp', 'zuzunely-restaurant'); ?></label></th>
                    <td>
                        <input type="checkbox" name="enable_whatsapp_notifications" id="enable_whatsapp_notifications" value="1" <?php checked(1, $settings['enable_whatsapp_notifications']); ?> />
                        <p class="description"><?php echo esc_html__('Habilitar o envio de notificações por WhatsApp através do Brevo.', 'zuzunely-restaurant'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="whatsapp_sender"><?php echo esc_html__('Número Remetente WhatsApp', 'zuzunely-restaurant'); ?></label></th>
                    <td>
                        <input type="text" name="whatsapp_sender" id="whatsapp_sender" value="<?php echo esc_attr($settings['whatsapp_sender']); ?>" class="regular-text" />
                        <p class="description"><?php echo esc_html__('Seu número aprovado no Brevo para envio de WhatsApp (formato internacional: ex. 5511999999999).', 'zuzunely-restaurant'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="whatsapp_confirmation_template"><?php echo esc_html__('Template de Confirmação', 'zuzunely-restaurant'); ?></label></th>
                    <td>
                        <select name="whatsapp_confirmation_template" id="whatsapp_confirmation_template" class="regular-text">
                            <option value="0"><?php echo esc_html__('Selecione um template', 'zuzunely-restaurant'); ?></option>
                            <?php
                            if (is_array($whatsapp_templates)) {
                                foreach ($whatsapp_templates as $template) {
                                    printf(
                                        '<option value="%s" %s>%s</option>',
                                        esc_attr($template['id']),
                                        selected($settings['whatsapp_confirmation_template'], $template['id'], false),
                                        esc_html($template['name'])
                                    );
                                }
                            }
                            ?>
                        </select>
                        <p class="description"><?php echo esc_html__('Template WhatsApp para confirmação de reservas.', 'zuzunely-restaurant'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="whatsapp_reminder_template"><?php echo esc_html__('Template de Lembrete', 'zuzunely-restaurant'); ?></label></th>
                    <td>
                        <select name="whatsapp_reminder_template" id="whatsapp_reminder_template" class="regular-text">
                            <option value="0"><?php echo esc_html__('Selecione um template', 'zuzunely-restaurant'); ?></option>
                            <?php
                            if (is_array($whatsapp_templates)) {
                                foreach ($whatsapp_templates as $template) {
                                    printf(
                                        '<option value="%s" %s>%s</option>',
                                        esc_attr($template['id']),
                                        selected($settings['whatsapp_reminder_template'], $template['id'], false),
                                        esc_html($template['name'])
                                    );
                                }
                            }
                            ?>
                        </select>
                        <p class="description"><?php echo esc_html__('Template WhatsApp para lembrete de reservas.', 'zuzunely-restaurant'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="whatsapp_problem_template"><?php echo esc_html__('Template de Problema', 'zuzunely-restaurant'); ?></label></th>
                    <td>
                        <select name="whatsapp_problem_template" id="whatsapp_problem_template" class="regular-text">
                            <option value="0"><?php echo esc_html__('Selecione um template', 'zuzunely-restaurant'); ?></option>
                            <?php
                            if (is_array($whatsapp_templates)) {
                                foreach ($whatsapp_templates as $template) {
                                    printf(
                                        '<option value="%s" %s>%s</option>',
                                        esc_attr($template['id']),
                                        selected($settings['whatsapp_problem_template'], $template['id'], false),
                                        esc_html($template['name'])
                                    );
                                }
                            }
                            ?>
                        </select>
                        <p class="description"><?php echo esc_html__('Template WhatsApp para notificação de problemas com reservas.', 'zuzunely-restaurant'); ?></p>
                    </td>
                </tr>
                <tr>
                    <td colspan="2">
                        <button type="button" id="test_brevo_whatsapp" class="button button-secondary">
                            <?php echo esc_html__('Testar Envio de WhatsApp', 'zuzunely-restaurant'); ?>
                        </button>
                        <span id="test_whatsapp_status"></span>
                        
                        <!-- Modal para solicitar número de telefone -->
                        <div id="whatsapp_phone_modal" style="display: none; background: rgba(0,0,0,0.5); position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 100000;">
                            <div style="background: white; margin: 10% auto; padding: 20px; width: 400px; border-radius: 5px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                                <h3><?php echo esc_html__('Teste de WhatsApp', 'zuzunely-restaurant'); ?></h3>
                                <p><?php echo esc_html__('Informe o número de telefone que receberá a mensagem de teste:', 'zuzunely-restaurant'); ?></p>
                                <input type="text" id="test_whatsapp_phone" placeholder="5511999999999" style="width: 100%; padding: 8px; margin: 10px 0;" />
                                <p style="font-size: 12px; color: #666;">
                                    <?php echo esc_html__('Use o formato internacional (ex: 5511999999999 para um número brasileiro)', 'zuzunely-restaurant'); ?>
                                </p>
                                <div style="text-align: right; margin-top: 15px;">
                                    <button type="button" id="cancel_whatsapp_test" class="button" style="margin-right: 10px;">
                                        <?php echo esc_html__('Cancelar', 'zuzunely-restaurant'); ?>
                                    </button>
                                    <button type="button" id="confirm_whatsapp_test" class="button button-primary">
                                        <?php echo esc_html__('Enviar Teste', 'zuzunely-restaurant'); ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
            </table>
            
            <h3><?php echo esc_html__('Configurações de Lembretes', 'zuzunely-restaurant'); ?></h3>
            <table class="form-table">
                <tr>
                    <th><label for="reminder_hours_before"><?php echo esc_html__('Horas Antes da Reserva', 'zuzunely-restaurant'); ?></label></th>
                    <td>
                        <input type="number" name="reminder_hours_before" id="reminder_hours_before" value="<?php echo esc_attr($settings['reminder_hours_before']); ?>" min="1" max="72" class="small-text" />
                        <p class="description"><?php echo esc_html__('Quantas horas antes da reserva enviar o lembrete.', 'zuzunely-restaurant'); ?></p>
                    </td>
                </tr>
            </table>
            
            <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Testar envio de e-mail
                $('#test_brevo_email').on('click', function() {
                    var button = $(this);
                    var status = $('#test_email_status');
                    
                    button.prop('disabled', true);
                    status.html('<?php echo esc_js(__('Enviando e-mail de teste...', 'zuzunely-restaurant')); ?>');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'zuzunely_test_brevo_email',
                            nonce: '<?php echo wp_create_nonce('zuzunely_test_brevo'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                status.html('<span style="color:green;">' + response.data + '</span>');
                            } else {
                                status.html('<span style="color:red;">' + response.data + '</span>');
                            }
                            button.prop('disabled', false);
                        },
                        error: function() {
                            status.html('<span style="color:red;"><?php echo esc_js(__('Erro na requisição AJAX', 'zuzunely-restaurant')); ?></span>');
                            button.prop('disabled', false);
                        }
                    });
                });
                
                // Testar envio de WhatsApp - Mostrar modal primeiro
                $('#test_brevo_whatsapp').on('click', function() {
                    $('#whatsapp_phone_modal').show();
                    $('#test_whatsapp_phone').focus();
                });
                
                // Cancelar teste de WhatsApp
                $('#cancel_whatsapp_test').on('click', function() {
                    $('#whatsapp_phone_modal').hide();
                    $('#test_whatsapp_phone').val('');
                });
                
                // Confirmar e enviar teste de WhatsApp
                $('#confirm_whatsapp_test').on('click', function() {
                    var phone = $('#test_whatsapp_phone').val().trim();
                    var button = $('#test_brevo_whatsapp');
                    var status = $('#test_whatsapp_status');
                    
                    if (!phone) {
                        alert('<?php echo esc_js(__('Por favor, informe um número de telefone válido.', 'zuzunely-restaurant')); ?>');
                        return;
                    }
                    
                    // Fechar modal
                    $('#whatsapp_phone_modal').hide();
                    
                    // Mostrar status
                    button.prop('disabled', true);
                    status.html('<?php echo esc_js(__('Enviando WhatsApp de teste...', 'zuzunely-restaurant')); ?>');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'zuzunely_test_brevo_whatsapp',
                            nonce: '<?php echo wp_create_nonce('zuzunely_test_brevo'); ?>',
                            phone: phone
                        },
                        success: function(response) {
                            if (response.success) {
                                status.html('<span style="color:green;">' + response.data + '</span>');
                            } else {
                                status.html('<span style="color:red;">' + response.data + '</span>');
                            }
                            button.prop('disabled', false);
                            $('#test_whatsapp_phone').val('');
                        },
                        error: function() {
                            status.html('<span style="color:red;"><?php echo esc_js(__('Erro na requisição AJAX', 'zuzunely-restaurant')); ?></span>');
                            button.prop('disabled', false);
                            $('#test_whatsapp_phone').val('');
                        }
                    });
                });
                
                // Permitir envio com Enter no campo de telefone
                $('#test_whatsapp_phone').on('keypress', function(e) {
                    if (e.which == 13) { // Enter
                        $('#confirm_whatsapp_test').click();
                    }
                });
                
                // Fechar modal clicando fora dele
                $('#whatsapp_phone_modal').on('click', function(e) {
                    if (e.target === this) {
                        $(this).hide();
                        $('#test_whatsapp_phone').val('');
                    }
                });
                
                // Atualizar templates
                $('#refresh_brevo_templates').on('click', function() {
                    var button = $(this);
                    var status = $('#refresh_templates_status');
                    
                    button.prop('disabled', true);
                    status.html('<?php echo esc_js(__('Atualizando templates...', 'zuzunely-restaurant')); ?>');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'zuzunely_refresh_brevo_templates',
                            nonce: '<?php echo wp_create_nonce('zuzunely_test_brevo'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                status.html('<span style="color:green;">' + response.data + '</span>');
                                setTimeout(function() {
                                    location.reload();
                                }, 1500);
                            } else {
                                status.html('<span style="color:red;">' + response.data + '</span>');
                                button.prop('disabled', false);
                            }
                        },
                        error: function() {
                            status.html('<span style="color:red;"><?php echo esc_js(__('Erro na requisição AJAX', 'zuzunely-restaurant')); ?></span>');
                            button.prop('disabled', false);
                        }
                    });
                });
                
                // Debug API
                $('#debug_brevo_api').on('click', function() {
                    var button = $(this);
                    var status = $('#refresh_templates_status');
                    
                    button.prop('disabled', true);
                    status.html('<?php echo esc_js(__('Testando API...', 'zuzunely-restaurant')); ?>');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'zuzunely_debug_brevo_api',
                            nonce: '<?php echo wp_create_nonce('zuzunely_test_brevo'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                status.html('<span style="color:green;">' + response.data + '</span>');
                            } else {
                                status.html('<span style="color:red;">' + response.data + '</span>');
                            }
                            button.prop('disabled', false);
                        },
                        error: function() {
                            status.html('<span style="color:red;"><?php echo esc_js(__('Erro na requisição AJAX', 'zuzunely-restaurant')); ?></span>');
                            button.prop('disabled', false);
                        }
                    });
                });
                
                // Debug API WhatsApp específica
                $('#debug_brevo_whatsapp_api').on('click', function() {
                    var button = $(this);
                    var status = $('#debug_whatsapp_api_status');
                    
                    button.prop('disabled', true);
                    status.html('<?php echo esc_js(__('Testando API de WhatsApp...', 'zuzunely-restaurant')); ?>');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'zuzunely_debug_brevo_whatsapp_api',
                            nonce: '<?php echo wp_create_nonce('zuzunely_test_brevo'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                status.html('<span style="color:green;">' + response.data + '</span>');
                            } else {
                                status.html('<span style="color:red;">' + response.data + '</span>');
                            }
                            button.prop('disabled', false);
                        },
                        error: function() {
                            status.html('<span style="color:red;"><?php echo esc_js(__('Erro na requisição AJAX', 'zuzunely-restaurant')); ?></span>');
                            button.prop('disabled', false);
                        }
                    });
                });
            });
            </script>
        </div>
        <?php
    }
    
    /**
     * Criar configurações padrão se não existirem
     */
    public static function create_default_settings() {
        $options = get_option(self::$option_name);
        
        if ($options === false) {
            $default_options = array(
                'api_key' => '',
                'whatsapp_sender' => '',
                'email_confirmation_template' => 0,
                'email_reminder_template' => 0,
                'email_problem_template' => 0,
                'whatsapp_confirmation_template' => 0,
                'whatsapp_reminder_template' => 0,
                'whatsapp_problem_template' => 0,
                'reminder_hours_before' => 24,
                'enable_email_notifications' => 0,
                'enable_whatsapp_notifications' => 0,
            );
            
            update_option(self::$option_name, $default_options);
            Zuzunely_Logger::info('Configurações padrão do Brevo criadas');
        }
    }
    
    /**
     * Fazer uma requisição para a API do Brevo - VERSÃO CORRIGIDA COM DEBUG MELHORADO
     * 
     * @param string $endpoint Endpoint da API
     * @param string $method Método HTTP (GET, POST, etc)
     * @param array $data Dados para enviar (para POST, PUT, etc)
     * @return array|WP_Error Resposta da API ou erro
     */
    private function api_request($endpoint, $method = 'GET', $data = null) {
        if (empty($this->api_key)) {
            Zuzunely_Logger::error('Tentativa de requisição à API do Brevo sem chave API configurada');
            return new WP_Error('no_api_key', __('Chave API do Brevo não configurada', 'zuzunely-restaurant'));
        }
        
        $url = $this->api_url . '/' . ltrim($endpoint, '/');
        Zuzunely_Logger::debug('Requisição API Brevo: ' . $method . ' ' . $url);
        
        $args = array(
            'method' => $method,
            'headers' => array(
                'api-key' => $this->api_key,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ),
            'timeout' => 30
        );
        
        if ($data !== null && in_array($method, array('POST', 'PUT'))) {
            $args['body'] = json_encode($data);
            Zuzunely_Logger::debug('Dados enviados: ' . json_encode($data));
        }
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            Zuzunely_Logger::error('Erro na requisição à API do Brevo: ' . $response->get_error_message());
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $headers = wp_remote_retrieve_headers($response);
        
        // Log detalhado da resposta
        Zuzunely_Logger::debug('Resposta API Brevo (código ' . $response_code . ')');
        Zuzunely_Logger::debug('Headers de resposta: ' . print_r($headers, true));
        Zuzunely_Logger::debug('Corpo da resposta (primeiros 500 chars): ' . substr($body, 0, 500));
        
        // Verificar se a resposta está vazia
        if (empty($body)) {
            Zuzunely_Logger::error('Resposta da API está vazia');
            return new WP_Error('empty_response', __('Resposta vazia da API do Brevo', 'zuzunely-restaurant'));
        }
        
        // Verificar se a resposta é HTML (erro comum)
        if (strpos(trim($body), '<') === 0) {
            Zuzunely_Logger::error('API retornou HTML ao invés de JSON: ' . $body);
            return new WP_Error('html_response', __('API retornou HTML ao invés de JSON. Verifique a chave API e o endpoint.', 'zuzunely-restaurant'));
        }
        
        // Tentar decodificar JSON
        $json_body = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $json_error = json_last_error_msg();
            Zuzunely_Logger::error('Erro ao decodificar resposta JSON: ' . $json_error);
            Zuzunely_Logger::error('Resposta completa: ' . $body);
            return new WP_Error('json_decode_error', sprintf(__('Erro ao decodificar resposta da API: %s', 'zuzunely-restaurant'), $json_error));
        }
        
        // Verificar códigos de erro HTTP
        if ($response_code >= 400) {
            $error_message = 'Erro HTTP ' . $response_code;
            
            // Tentar extrair mensagem de erro do JSON
            if (isset($json_body['message'])) {
                $error_message = $json_body['message'];
            } elseif (isset($json_body['error'])) {
                $error_message = $json_body['error'];
            } elseif (isset($json_body['detail'])) {
                $error_message = $json_body['detail'];
            }
            
            Zuzunely_Logger::error('Erro na API do Brevo: ' . $error_message . ' (Código: ' . $response_code . ')');
            Zuzunely_Logger::error('Resposta completa de erro: ' . print_r($json_body, true));
            
            return new WP_Error('api_error', $error_message, array('status' => $response_code, 'response' => $json_body));
        }
        
        return $json_body;
    }
    
    /**
     * Obter templates de e-mail do Brevo
     * 
     * @param bool $force_refresh Forçar atualização dos templates
     * @return array Array de templates ou array vazio em caso de erro
     */
    public function get_email_templates($force_refresh = false) {
        $transient_key = 'zuzunely_brevo_email_templates';
        $cached = get_transient($transient_key);
        
        if ($cached !== false && !$force_refresh) {
            return $cached;
        }
        
        $response = $this->api_request('smtp/templates');
        
        if (is_wp_error($response)) {
            return array();
        }
        
        $templates = array();
        
        if (isset($response['templates']) && is_array($response['templates'])) {
            foreach ($response['templates'] as $template) {
                if (isset($template['id']) && isset($template['name'])) {
                    $templates[] = array(
                        'id' => $template['id'],
                        'name' => $template['name']
                    );
                }
            }
        }
        
        // Cache por 1 hora
        set_transient($transient_key, $templates, HOUR_IN_SECONDS);
        
        return $templates;
    }
    
    /**
     * Obter templates de WhatsApp do Brevo
     * 
     * @param bool $force_refresh Forçar atualização dos templates
     * @return array Array de templates ou array vazio em caso de erro
     */
    public function get_whatsapp_templates($force_refresh = false) {
        $transient_key = 'zuzunely_brevo_whatsapp_templates';
        $cached = get_transient($transient_key);
        
        if ($cached !== false && !$force_refresh) {
            return $cached;
        }
        
        // Usar o endpoint correto para templates de WhatsApp
        $response = $this->api_request('whatsappCampaigns/template-list');
        
        if (is_wp_error($response)) {
            Zuzunely_Logger::error('Erro ao obter templates de WhatsApp: ' . $response->get_error_message());
            return array();
        }
        
        Zuzunely_Logger::debug('Resposta da API de templates WhatsApp: ' . print_r($response, true));
        
        $templates = array();
        
        // Processar de acordo com a estrutura de resposta da API
        if (isset($response['templates']) && is_array($response['templates'])) {
            foreach ($response['templates'] as $template) {
                if (isset($template['id']) && isset($template['name'])) {
                    $templates[] = array(
                        'id' => $template['id'],
                        'name' => $template['name']
                    );
                }
            }
        }
        // Estrutura alternativa: pode ser um array direto de templates
        elseif (is_array($response)) {
            foreach ($response as $template) {
                if (isset($template['id']) && isset($template['name'])) {
                    $templates[] = array(
                        'id' => $template['id'],
                        'name' => $template['name']
                    );
                }
            }
        }
        
        Zuzunely_Logger::info('Templates de WhatsApp obtidos com sucesso: ' . count($templates) . ' templates encontrados');
        
        // Cache por 1 hora
        set_transient($transient_key, $templates, HOUR_IN_SECONDS);
        
        return $templates;
    }
    
    /**
     * Atualizar templates via AJAX
     */
    public function ajax_refresh_templates() {
        check_ajax_referer('zuzunely_test_brevo', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permissão negada', 'zuzunely-restaurant'));
            return;
        }
        
        $this->get_email_templates(true);
        $this->get_whatsapp_templates(true);
        
        wp_send_json_success(__('Templates atualizados com sucesso!', 'zuzunely-restaurant'));
    }
    
    /**
     * Debug da API do Brevo
     */
    public function debug_brevo_api() {
        check_ajax_referer('zuzunely_test_brevo', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permissão negada', 'zuzunely-restaurant'));
            return;
        }
        
        // Verificar endpoints disponíveis de WhatsApp
        $endpoints = [
            'whatsapp/templates',
            'whatsapp/templates/list',
            'whatsapp',
            'whatsapp/campaigns',
            'whatsappCampaigns/template-list',
            'account'
        ];
        
        $results = [];
        
        foreach ($endpoints as $endpoint) {
            $response = $this->api_request($endpoint);
            
            if (is_wp_error($response)) {
                $results[$endpoint] = 'Erro: ' . $response->get_error_message();
            } else {
                $results[$endpoint] = 'Sucesso: ' . count($response) . ' itens encontrados';
            }
        }
        
        // Verificar a conta
        $account_response = $this->api_request('account');
        if (!is_wp_error($account_response)) {
            $results['account'] = 'Conta válida: ' . (isset($account_response['email']) ? $account_response['email'] : 'Desconhecido');
            
            // Verificar se a conta tem recursos de WhatsApp
            if (isset($account_response['plan']) && isset($account_response['plan']['features'])) {
                $features = $account_response['plan']['features'];
                $has_whatsapp = false;
                
                foreach ($features as $feature) {
                    if (strpos(strtolower($feature['name']), 'whatsapp') !== false) {
                        $has_whatsapp = true;
                        $results['whatsapp_feature'] = 'Recurso WhatsApp: Disponível';
                        break;
                    }
                }
                
                if (!$has_whatsapp) {
                    $results['whatsapp_feature'] = 'Recurso WhatsApp: Não disponível na sua conta';
                }
            }
        } else {
            $results['account'] = 'Erro ao verificar conta: ' . $account_response->get_error_message();
        }
        
        // Formatar resultado como HTML
        $html = '<div style="background: #f0f0f1; padding: 10px; margin-top: 10px; max-height: 200px; overflow-y: auto;">';
        $html .= '<h4>' . __('Resultados do Debug da API:', 'zuzunely-restaurant') . '</h4>';
        $html .= '<ul>';
        
        foreach ($results as $key => $value) {
            $html .= '<li><strong>' . esc_html($key) . '</strong>: ' . esc_html($value) . '</li>';
        }
        
        $html .= '</ul>';
        $html .= '<p>' . __('Verifique os logs para mais detalhes.', 'zuzunely-restaurant') . '</p>';
        $html .= '</div>';
        
        wp_send_json_success($html);
    }
    
    /**
     * Debug melhorado da API de WhatsApp do Brevo
     */
    public function debug_brevo_whatsapp_api() {
        check_ajax_referer('zuzunely_test_brevo', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permissão negada', 'zuzunely-restaurant'));
            return;
        }
        
        $results = [];
        
        // 1. Verificar conta
        $account_response = $this->api_request('account');
        if (!is_wp_error($account_response)) {
            $results['✅ Conta'] = 'Válida: ' . (isset($account_response['email']) ? $account_response['email'] : 'Email não disponível');
            
            // Verificar plano e recursos
            if (isset($account_response['plan'])) {
                $plan = $account_response['plan'];
                $results['📋 Plano'] = isset($plan['type']) ? $plan['type'] : 'Tipo não especificado';
                
                if (isset($plan['credits'])) {
                    $results['💳 Créditos'] = 'SMS: ' . (isset($plan['credits']['sms']) ? $plan['credits']['sms'] : 'N/A') . 
                                             ', Email: ' . (isset($plan['credits']['email']) ? $plan['credits']['email'] : 'N/A');
                }
            }
        } else {
            $results['❌ Conta'] = 'Erro: ' . $account_response->get_error_message();
        }
        
        // 2. Verificar endpoints de WhatsApp
        $whatsapp_endpoints = [
            'whatsappCampaigns/template-list' => 'Lista de templates',
            'whatsapp/templates' => 'Templates (endpoint alternativo)',
            'conversations/whatsapp' => 'Conversações WhatsApp'
        ];
        
        foreach ($whatsapp_endpoints as $endpoint => $description) {
            $response = $this->api_request($endpoint);
            
            if (is_wp_error($response)) {
                $error_data = $response->get_error_data();
                $status = isset($error_data['status']) ? $error_data['status'] : 'desconhecido';
                $results["❌ {$description}"] = "HTTP {$status}: " . $response->get_error_message();
            } else {
                $count = is_array($response) ? count($response) : (isset($response['templates']) ? count($response['templates']) : 'estrutura desconhecida');
                $results["✅ {$description}"] = "Sucesso: {$count} itens";
            }
        }
        
        // 3. Verificar se WhatsApp está disponível
        $settings = get_option(self::$option_name);
        if (empty($settings['whatsapp_sender'])) {
            $results['⚠️ Configuração'] = 'Número remetente não configurado';
        } else {
            $results['📱 Número Remetente'] = $settings['whatsapp_sender'];
        }
        
        // 4. Testar endpoint específico para verificar se aceita requisições POST
        $test_data = array('test' => true);
        $test_response = $this->api_request('whatsappCampaigns/template-send', 'POST', $test_data);
        
        if (is_wp_error($test_response)) {
            $error_data = $test_response->get_error_data();
            $status = isset($error_data['status']) ? $error_data['status'] : 'desconhecido';
            
            if ($status == 400) {
                $results['✅ Endpoint POST'] = 'Endpoint aceita POST (erro 400 esperado com dados de teste)';
            } else {
                $results["❌ Endpoint POST"] = "HTTP {$status}: " . $test_response->get_error_message();
            }
        } else {
            $results['🤔 Endpoint POST'] = 'Resposta inesperada para dados de teste';
        }
        
        // Formatar resultado como HTML
        $html = '<div style="background: #f0f0f1; padding: 15px; margin-top: 10px; max-height: 300px; overflow-y: auto; font-family: monospace;">';
        $html .= '<h4>' . __('🔍 Debug Detalhado da API de WhatsApp:', 'zuzunely-restaurant') . '</h4>';
        
        foreach ($results as $key => $value) {
            $html .= '<div style="margin: 8px 0; padding: 5px; background: white; border-left: 3px solid #0073aa;">';
            $html .= '<strong>' . esc_html($key) . ':</strong> ' . esc_html($value);
            $html .= '</div>';
        }
        
        $html .= '<div style="margin-top: 15px; padding: 10px; background: #fff3cd; border: 1px solid #ffeaa7;">';
        $html .= '<strong>💡 Dicas:</strong><br>';
        $html .= '• Verifique se sua conta Brevo tem o recurso WhatsApp ativado<br>';
        $html .= '• Confirme se o número remetente está aprovado no Brevo<br>';
        $html .= '• Templates de WhatsApp precisam ser pré-aprovados pelo WhatsApp<br>';
        $html .= '• Verifique os logs para detalhes técnicos completos';
        $html .= '</div>';
        
        $html .= '</div>';
        
        wp_send_json_success($html);
    }
    
    /**
     * Testar conexão de e-mail
     */
    public function test_email_connection() {
        check_ajax_referer('zuzunely_test_brevo', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permissão negada', 'zuzunely-restaurant'));
            return;
        }
        
        $current_user = wp_get_current_user();
        $email = $current_user->user_email;
        $name = $current_user->display_name;
        
        $settings = get_option(self::$option_name);
        
        if (empty($settings['email_confirmation_template'])) {
            wp_send_json_error(__('Selecione um template de e-mail para teste', 'zuzunely-restaurant'));
            return;
        }
        
        $result = $this->send_email_template(
            $settings['email_confirmation_template'],
            $email,
            $name,
            array(
                'NOME' => $name,
                'DATA_RESERVA' => date_i18n(get_option('date_format'), current_time('timestamp')),
                'HORA_RESERVA' => date_i18n(get_option('time_format'), current_time('timestamp')),
                'PESSOAS' => '4',
                'MESA' => 'Mesa de Teste',
                'RESTAURANTE' => get_bloginfo('name')
            )
        );
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
            return;
        }
        
        wp_send_json_success(__('E-mail de teste enviado com sucesso para ', 'zuzunely-restaurant') . $email);
    }
    
    /**
     * Testar conexão de WhatsApp - VERSÃO CORRIGIDA
     */
    public function test_whatsapp_connection() {
        check_ajax_referer('zuzunely_test_brevo', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permissão negada', 'zuzunely-restaurant'));
            return;
        }
        
        $settings = get_option(self::$option_name);
        
        if (empty($settings['whatsapp_sender'])) {
            wp_send_json_error(__('Configure o número remetente do WhatsApp', 'zuzunely-restaurant'));
            return;
        }
        
        if (empty($settings['whatsapp_confirmation_template'])) {
            wp_send_json_error(__('Selecione um template de WhatsApp para teste', 'zuzunely-restaurant'));
            return;
        }
        
        // Obter número para teste
        $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
        
        if (empty($phone)) {
            wp_send_json_error(__('Número de telefone é obrigatório para o teste', 'zuzunely-restaurant'));
            return;
        }
        
        // Validar formato básico do telefone
        if (!preg_match('/^\d{10,15}$/', $phone)) {
            wp_send_json_error(__('Formato de telefone inválido. Use apenas números (ex: 5511999999999)', 'zuzunely-restaurant'));
            return;
        }
        
        // Enviar mensagem de teste com parâmetros no formato correto
        $current_user = wp_get_current_user();
        $name = $current_user->display_name;
        
        // Usar formato simples de parâmetros como no código que funciona
        $test_params = array(
            'NOME' => $name,
            'DATA_RESERVA' => date_i18n(get_option('date_format'), current_time('timestamp')),
            'HORA_RESERVA' => date_i18n(get_option('time_format'), current_time('timestamp')),
            'PESSOAS' => '4',
            'MESA' => 'Mesa de Teste',
            'RESTAURANTE' => get_bloginfo('name')
        );
        
        $result = $this->send_whatsapp_template(
            $settings['whatsapp_confirmation_template'],
            $phone,
            $test_params
        );
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
            return;
        }
        
        wp_send_json_success(__('WhatsApp de teste enviado com sucesso para ', 'zuzunely-restaurant') . $phone);
    }
    
    /**
     * Enviar e-mail usando template do Brevo
     * 
     * @param int $template_id ID do template
     * @param string $to_email E-mail de destino
     * @param string $to_name Nome do destinatário
     * @param array $params Parâmetros para o template
     * @return bool|WP_Error True em caso de sucesso ou WP_Error em caso de erro
     */
    public function send_email_template($template_id, $to_email, $to_name, $params = array()) {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('Chave API do Brevo não configurada', 'zuzunely-restaurant'));
        }
        
        $data = array(
            'templateId' => (int) $template_id,
            'to' => array(
                array(
                    'email' => $to_email,
                    'name' => $to_name
                )
            ),
            'params' => $params
        );
        
        $response = $this->api_request('smtp/email', 'POST', $data);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        Zuzunely_Logger::info('E-mail enviado com sucesso para ' . $to_email . ' usando template ' . $template_id);
        return true;
    }
    
    /**
     * Enviar WhatsApp usando template do Brevo - BASEADO NO CÓDIGO QUE FUNCIONA
     * 
     * @param int $template_id ID do template
     * @param string $to_phone Número de telefone de destino
     * @param array $params Parâmetros para o template
     * @return bool|WP_Error True em caso de sucesso ou WP_Error em caso de erro
     */
    public function send_whatsapp_template($template_id, $to_phone, $params = array()) {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('Chave API do Brevo não configurada', 'zuzunely-restaurant'));
        }
        
        $settings = get_option(self::$option_name);
        $sender = isset($settings['whatsapp_sender']) ? $settings['whatsapp_sender'] : '';
        
        if (empty($sender)) {
            return new WP_Error('no_sender', __('Número remetente de WhatsApp não configurado', 'zuzunely-restaurant'));
        }
        
        // Converter parâmetros do formato {{1}} para formato direto
        $converted_params = array();
        foreach ($params as $key => $value) {
            // Remover {{}} se existir e usar números como chaves
            $clean_key = str_replace(array('{{', '}}'), '', $key);
            if (is_numeric($clean_key)) {
                $converted_params[$clean_key] = $value;
            } else {
                $converted_params[$key] = $value;
            }
        }
        
        // Estrutura baseada no código que funciona
        $payload = array(
            'templateId' => intval($template_id),
            'senderNumber' => $sender,
            'contactNumbers' => array($to_phone),
            'params' => $converted_params
        );
        
        Zuzunely_Logger::debug('Enviando WhatsApp com payload (formato funcional): ' . json_encode($payload));
        
        // Usar o endpoint que funciona
        $url = 'https://api.brevo.com/v3/whatsapp/sendMessage';
        
        $args = array(
            'method' => 'POST',
            'headers' => array(
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'api-key' => $this->api_key
            ),
            'body' => json_encode($payload),
            'timeout' => 30
        );
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            Zuzunely_Logger::error('Erro na requisição WhatsApp: ' . $response->get_error_message());
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        Zuzunely_Logger::debug('Resposta WhatsApp - Código: ' . $response_code . ', Corpo: ' . $body);
        
        // Códigos 200, 201 e 202 são sucesso
        if ($response_code >= 200 && $response_code < 300) {
            Zuzunely_Logger::info('WhatsApp enviado com sucesso para ' . $to_phone . ' usando template ' . $template_id);
            return true;
        } else {
            // Tentar decodificar erro
            $error_data = json_decode($body, true);
            $error_message = 'Erro HTTP ' . $response_code;
            
            if (is_array($error_data)) {
                if (isset($error_data['message'])) {
                    $error_message = $error_data['message'];
                } elseif (isset($error_data['error'])) {
                    $error_message = $error_data['error'];
                }
                
                // Log detalhado do erro
                Zuzunely_Logger::error('Erro detalhado da API: ' . print_r($error_data, true));
            }
            
            Zuzunely_Logger::error('Erro ao enviar WhatsApp: ' . $error_message);
            return new WP_Error('whatsapp_send_error', $error_message, array('status' => $response_code));
        }
    }
    
    /**
     * Enviar notificação de confirmação de reserva
     * 
     * @param int $reservation_id ID da reserva
     * @return array Resultado do envio (email_result e whatsapp_result)
     */
    public function send_reservation_confirmation($reservation_id) {
        global $wpdb;
        $settings = get_option(self::$option_name);
        $results = array(
            'email_result' => false,
            'whatsapp_result' => false
        );
        
        // Verificar se as notificações estão ativadas
        $email_enabled = !empty($settings['enable_email_notifications']);
        $whatsapp_enabled = !empty($settings['enable_whatsapp_notifications']);
        
        if (!$email_enabled && !$whatsapp_enabled) {
            return $results;
        }
        
        // Obter dados da reserva
        $reservations_table = $wpdb->prefix . 'zuzunely_reservations';
        $reservation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $reservations_table WHERE id = %d",
            $reservation_id
        ));
        
        if (!$reservation) {
            Zuzunely_Logger::error('Tentativa de enviar confirmação para reserva inexistente: ' . $reservation_id);
            return $results;
        }
        
        // Obter dados da mesa
        $tables_table = $wpdb->prefix . 'zuzunely_tables';
        $table = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $tables_table WHERE id = %d",
            $reservation->table_id
        ));
        
        $table_name = $table ? $table->name : __('Mesa não especificada', 'zuzunely-restaurant');
        
        // Preparar dados comuns
        $date_format = get_option('date_format');
        $time_format = get_option('time_format');
        
        $reservation_date = date_i18n($date_format, strtotime($reservation->reservation_date));
        $reservation_time = date_i18n($time_format, strtotime($reservation->reservation_time));
        
        // Enviar e-mail se habilitado
        if ($email_enabled && !empty($settings['email_confirmation_template'])) {
            $email_params = array(
                'NOME' => $reservation->customer_name,
                'DATA_RESERVA' => $reservation_date,
                'HORA_RESERVA' => $reservation_time,
                'PESSOAS' => $reservation->number_of_people,
                'MESA' => $table_name,
                'RESTAURANTE' => get_bloginfo('name'),
                'ID_RESERVA' => $reservation->id,
                'TELEFONE' => $reservation->customer_phone,
                'EMAIL' => $reservation->customer_email,
                'OBSERVACOES' => $reservation->notes
            );
            
            $email_result = $this->send_email_template(
                $settings['email_confirmation_template'],
                $reservation->customer_email,
                $reservation->customer_name,
                $email_params
            );
            
            $results['email_result'] = !is_wp_error($email_result);
            
            if (is_wp_error($email_result)) {
                Zuzunely_Logger::error('Erro ao enviar e-mail de confirmação: ' . $email_result->get_error_message());
            }
        }
        
        // Enviar WhatsApp se habilitado - FORMATO CORRIGIDO
        if ($whatsapp_enabled && !empty($settings['whatsapp_confirmation_template']) && !empty($reservation->customer_phone)) {
            // Usar formato simples de parâmetros (sem {{}} )
            $whatsapp_params = array(
                'NOME' => $reservation->customer_name,
                'DATA_RESERVA' => $reservation_date,
                'HORA_RESERVA' => $reservation_time,
                'PESSOAS' => $reservation->number_of_people,
                'MESA' => $table_name,
                'RESTAURANTE' => get_bloginfo('name'),
                'ID_RESERVA' => $reservation->id
            );
            
            $whatsapp_result = $this->send_whatsapp_template(
                $settings['whatsapp_confirmation_template'],
                $reservation->customer_phone,
                $whatsapp_params
            );
            
            $results['whatsapp_result'] = !is_wp_error($whatsapp_result);
            
            if (is_wp_error($whatsapp_result)) {
                Zuzunely_Logger::error('Erro ao enviar WhatsApp de confirmação: ' . $whatsapp_result->get_error_message());
            }
        }
        
        return $results;
    }
    
    /**
     * Enviar lembrete de reserva
     * 
     * @param int $reservation_id ID da reserva
     * @return array Resultado do envio (email_result e whatsapp_result)
     */
    public function send_reservation_reminder($reservation_id) {
        global $wpdb;
        $settings = get_option(self::$option_name);
        $results = array(
            'email_result' => false,
            'whatsapp_result' => false
        );
        
        // Verificar se as notificações estão ativadas
        $email_enabled = !empty($settings['enable_email_notifications']);
        $whatsapp_enabled = !empty($settings['enable_whatsapp_notifications']);
        
        if (!$email_enabled && !$whatsapp_enabled) {
            return $results;
        }
        
        // Obter dados da reserva
        $reservations_table = $wpdb->prefix . 'zuzunely_reservations';
        $reservation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $reservations_table WHERE id = %d",
            $reservation_id
        ));
        
        if (!$reservation) {
            Zuzunely_Logger::error('Tentativa de enviar lembrete para reserva inexistente: ' . $reservation_id);
            return $results;
        }
        
        // Obter dados da mesa
        $tables_table = $wpdb->prefix . 'zuzunely_tables';
        $table = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $tables_table WHERE id = %d",
            $reservation->table_id
        ));
        
        $table_name = $table ? $table->name : __('Mesa não especificada', 'zuzunely-restaurant');
        
        // Preparar dados comuns
        $date_format = get_option('date_format');
        $time_format = get_option('time_format');
        
        $reservation_date = date_i18n($date_format, strtotime($reservation->reservation_date));
        $reservation_time = date_i18n($time_format, strtotime($reservation->reservation_time));
        
        // Enviar e-mail se habilitado
        if ($email_enabled && !empty($settings['email_reminder_template'])) {
            $email_params = array(
                'NOME' => $reservation->customer_name,
                'DATA_RESERVA' => $reservation_date,
                'HORA_RESERVA' => $reservation_time,
                'PESSOAS' => $reservation->number_of_people,
                'MESA' => $table_name,
                'RESTAURANTE' => get_bloginfo('name'),
                'ID_RESERVA' => $reservation->id,
                'TELEFONE' => $reservation->customer_phone,
                'EMAIL' => $reservation->customer_email,
                'OBSERVACOES' => $reservation->notes
            );
            
            $email_result = $this->send_email_template(
                $settings['email_reminder_template'],
                $reservation->customer_email,
                $reservation->customer_name,
                $email_params
            );
            
            $results['email_result'] = !is_wp_error($email_result);
            
            if (is_wp_error($email_result)) {
                Zuzunely_Logger::error('Erro ao enviar e-mail de lembrete: ' . $email_result->get_error_message());
            }
        }
        
        // Enviar WhatsApp se habilitado - FORMATO CORRIGIDO
        if ($whatsapp_enabled && !empty($settings['whatsapp_reminder_template']) && !empty($reservation->customer_phone)) {
            // Usar formato simples de parâmetros (sem {{}})
            $whatsapp_params = array(
                'NOME' => $reservation->customer_name,
                'DATA_RESERVA' => $reservation_date,
                'HORA_RESERVA' => $reservation_time,
                'PESSOAS' => $reservation->number_of_people,
                'MESA' => $table_name,
                'RESTAURANTE' => get_bloginfo('name')
            );
            
            $whatsapp_result = $this->send_whatsapp_template(
                $settings['whatsapp_reminder_template'],
                $reservation->customer_phone,
                $whatsapp_params
            );
            
            $results['whatsapp_result'] = !is_wp_error($whatsapp_result);
            
            if (is_wp_error($whatsapp_result)) {
                Zuzunely_Logger::error('Erro ao enviar WhatsApp de lembrete: ' . $whatsapp_result->get_error_message());
            }
        }
        
        return $results;
    }
    
    /**
     * Enviar notificação de problema com reserva
     * 
     * @param int $reservation_id ID da reserva
     * @param string $problem_message Mensagem descrevendo o problema
     * @return array Resultado do envio (email_result e whatsapp_result)
     */
    public function send_reservation_problem_notification($reservation_id, $problem_message) {
        global $wpdb;
        $settings = get_option(self::$option_name);
        $results = array(
            'email_result' => false,
            'whatsapp_result' => false
        );
        
        // Verificar se as notificações estão ativadas
        $email_enabled = !empty($settings['enable_email_notifications']);
        $whatsapp_enabled = !empty($settings['enable_whatsapp_notifications']);
        
        if (!$email_enabled && !$whatsapp_enabled) {
            return $results;
        }
        
        // Obter dados da reserva
        $reservations_table = $wpdb->prefix . 'zuzunely_reservations';
        $reservation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $reservations_table WHERE id = %d",
            $reservation_id
        ));
        
        if (!$reservation) {
            Zuzunely_Logger::error('Tentativa de enviar notificação de problema para reserva inexistente: ' . $reservation_id);
            return $results;
        }
        
        // Preparar dados comuns
        $date_format = get_option('date_format');
        $time_format = get_option('time_format');
        
        $reservation_date = date_i18n($date_format, strtotime($reservation->reservation_date));
        $reservation_time = date_i18n($time_format, strtotime($reservation->reservation_time));
        
        // Enviar e-mail se habilitado
        if ($email_enabled && !empty($settings['email_problem_template'])) {
            $email_params = array(
                'NOME' => $reservation->customer_name,
                'DATA_RESERVA' => $reservation_date,
                'HORA_RESERVA' => $reservation_time,
                'PESSOAS' => $reservation->number_of_people,
                'RESTAURANTE' => get_bloginfo('name'),
                'ID_RESERVA' => $reservation->id,
                'PROBLEMA' => $problem_message
            );
            
            $email_result = $this->send_email_template(
                $settings['email_problem_template'],
                $reservation->customer_email,
                $reservation->customer_name,
                $email_params
            );
            
            $results['email_result'] = !is_wp_error($email_result);
            
            if (is_wp_error($email_result)) {
                Zuzunely_Logger::error('Erro ao enviar e-mail de problema: ' . $email_result->get_error_message());
            }
        }
        
        // Enviar WhatsApp se habilitado - FORMATO CORRIGIDO
        if ($whatsapp_enabled && !empty($settings['whatsapp_problem_template']) && !empty($reservation->customer_phone)) {
            // Usar formato simples de parâmetros (sem {{}})
            $whatsapp_params = array(
                'NOME' => $reservation->customer_name,
                'DATA_RESERVA' => $reservation_date,
                'HORA_RESERVA' => $reservation_time,
                'PROBLEMA' => $problem_message,
                'RESTAURANTE' => get_bloginfo('name')
            );
            
            $whatsapp_result = $this->send_whatsapp_template(
                $settings['whatsapp_problem_template'],
                $reservation->customer_phone,
                $whatsapp_params
            );
            
            $results['whatsapp_result'] = !is_wp_error($whatsapp_result);
            
            if (is_wp_error($whatsapp_result)) {
                Zuzunely_Logger::error('Erro ao enviar WhatsApp de problema: ' . $whatsapp_result->get_error_message());
            }
        }
        
        return $results;
    }
    
    /**
     * Verificar por lembretes a serem enviados
     * Esta função deve ser chamada por um cron job
     */
    public function check_for_reminders() {
        global $wpdb;
        $settings = get_option(self::$option_name);
        
        // Verificar se as notificações estão ativadas
        $email_enabled = !empty($settings['enable_email_notifications']);
        $whatsapp_enabled = !empty($settings['enable_whatsapp_notifications']);
        
        if (!$email_enabled && !$whatsapp_enabled) {
            return;
        }
        
        // Verificar se há templates de lembrete configurados
        $has_email_reminder = !empty($settings['email_reminder_template']);
        $has_whatsapp_reminder = !empty($settings['whatsapp_reminder_template']);
        
        if (!$has_email_reminder && !$has_whatsapp_reminder) {
            return;
        }
        
        // Obter horas de antecedência para lembrete
        $hours_before = isset($settings['reminder_hours_before']) ? intval($settings['reminder_hours_before']) : 24;
        
        // Calcular intervalo de tempo para enviar lembretes
        $now = current_time('mysql');
        $reminder_time = date('Y-m-d H:i:s', strtotime("+$hours_before hours"));
        
        // Buscar reservas que precisam de lembrete
        $reservations_table = $wpdb->prefix . 'zuzunely_reservations';
        $reservations = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $reservations_table 
             WHERE CONCAT(reservation_date, ' ', reservation_time) BETWEEN %s AND %s
             AND status = 'confirmed'
             AND reminder_sent = 0",
            $now,
            $reminder_time
        ));
        
        if (empty($reservations)) {
            return;
        }
        
        Zuzunely_Logger::info('Verificando lembretes para ' . count($reservations) . ' reservas');
        
        foreach ($reservations as $reservation) {
            // Enviar lembrete
            $result = $this->send_reservation_reminder($reservation->id);
            
            // Marcar como enviado se pelo menos um tipo de notificação foi enviado com sucesso
            if ($result['email_result'] || $result['whatsapp_result']) {
                $wpdb->update(
                    $reservations_table,
                    array('reminder_sent' => 1),
                    array('id' => $reservation->id),
                    array('%d'),
                    array('%d')
                );
                
                Zuzunely_Logger::info('Lembrete enviado para reserva #' . $reservation->id);
            }
        }
    }
}

// Inicializar a classe
add_action('plugins_loaded', array('Zuzunely_Brevo', 'get_instance'));

// Configurar Cron Job para verificar lembretes
if (!wp_next_scheduled('zuzunely_check_reminders')) {
    wp_schedule_event(time(), 'hourly', 'zuzunely_check_reminders');
}
add_action('zuzunely_check_reminders', array('Zuzunely_Brevo', 'get_instance'), 10, 0);

// Ao desativar o plugin, limpar o cron job
register_deactivation_hook(__FILE__, 'zuzunely_brevo_deactivation');
function zuzunely_brevo_deactivation() {
    wp_clear_scheduled_hook('zuzunely_check_reminders');
}