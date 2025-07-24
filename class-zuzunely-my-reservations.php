<?php
/**
 * Classe para gerenciar "Minhas Reservas" no frontend
 * Sistema completo de visualização e gerenciamento de reservas do cliente
 * VERSÃO MELHORADA COM DESIGN MODERNO
 */

if (!defined('ABSPATH')) {
    exit;
}

class Zuzunely_My_Reservations {
    
    private static $endpoint = 'minhas-reservas';
    
    public function __construct() {
        // Hooks de inicialização
        add_action('init', array($this, 'add_endpoints'), 10);
        add_action('init', array($this, 'check_flush_rewrite_rules'), 20);
        
        // Shortcode para exibir reservas do cliente
        add_shortcode('zuzunely_my_reservations', array($this, 'render_my_reservations'));
        
        // AJAX para cancelar reserva
        add_action('wp_ajax_zuzunely_cancel_my_reservation', array($this, 'cancel_reservation'));
        add_action('wp_ajax_nopriv_zuzunely_cancel_my_reservation', array($this, 'cancel_reservation_guest'));
        
        // Integração com WooCommerce My Account
        if (class_exists('WooCommerce')) {
            add_filter('woocommerce_account_menu_items', array($this, 'add_woocommerce_menu_item'), 10, 1);
            add_action('woocommerce_account_' . self::$endpoint . '_endpoint', array($this, 'woocommerce_reservations_content'));
            add_filter('woocommerce_get_query_vars', array($this, 'add_query_vars'), 0);
        }
        
        // Scripts e estilos
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // Endpoint personalizado para WordPress (sem WooCommerce)
        add_action('template_redirect', array($this, 'handle_reservations_page'));
        
        // Adicionar link no menu de usuário
        add_filter('wp_nav_menu_items', array($this, 'add_reservations_menu_link'), 10, 2);
        
        // Registrar query vars
        add_filter('query_vars', array($this, 'add_query_vars_wp'), 0);
    }
    
    /**
     * Adicionar endpoints
     */
    public function add_endpoints() {
        // WooCommerce endpoint
        if (class_exists('WooCommerce')) {
            add_rewrite_endpoint(self::$endpoint, EP_ROOT | EP_PAGES);
        }
        
        // WordPress standalone endpoints
        add_rewrite_rule('^' . self::$endpoint . '/?$', 'index.php?minhas_reservas=1', 'top');
        add_rewrite_rule('^' . self::$endpoint . '/([^/]+)/?$', 'index.php?minhas_reservas=1&consulta_token=$matches[1]', 'top');
    }
    
    /**
     * Verificar se precisa fazer flush das regras de rewrite
     */
    public function check_flush_rewrite_rules() {
        if (get_option('zuzunely_flush_rewrite_rules_flag')) {
            flush_rewrite_rules();
            delete_option('zuzunely_flush_rewrite_rules_flag');
            Zuzunely_Logger::info('Rewrite rules flushed for My Reservations');
        }
    }
    
    /**
     * Adicionar query vars para WooCommerce
     */
    public function add_query_vars($vars) {
        $vars[self::$endpoint] = self::$endpoint;
        return $vars;
    }
    
    /**
     * Adicionar query vars para WordPress
     */
    public function add_query_vars_wp($vars) {
        $vars[] = 'minhas_reservas';
        $vars[] = 'consulta_token';
        return $vars;
    }
    
    /**
     * Adicionar item ao menu da conta WooCommerce
     */
    public function add_woocommerce_menu_item($items) {
        // Inserir antes do logout
        $logout = $items['customer-logout'];
        unset($items['customer-logout']);
        
        $items[self::$endpoint] = __('Minhas Reservas', 'zuzunely-restaurant');
        $items['customer-logout'] = $logout;
        
        return $items;
    }
    
    /**
     * Conteúdo da página de reservas no WooCommerce
     */
    public function woocommerce_reservations_content() {
        if (is_user_logged_in()) {
            $current_user = wp_get_current_user();
            echo $this->render_reservations_for_user($current_user->user_email);
        } else {
            echo $this->render_login_form();
        }
    }
    
    /**
     * Lidar com a página de reservas standalone
     */
    public function handle_reservations_page() {
        if (!get_query_var('minhas_reservas')) {
            return;
        }
        
        $consulta_token = get_query_var('consulta_token');
        
        // Verificar se é uma página WooCommerce
        if (class_exists('WooCommerce') && is_account_page()) {
            return; // Deixar WooCommerce lidar com isso
        }
        
        $this->render_standalone_page($consulta_token);
        exit;
    }
    
    /**
     * Renderizar página standalone
     */
    private function render_standalone_page($consulta_token = '') {
        get_header();
        
        echo '<div class="container zuzunely-reservations-container">';
        echo '<div class="zuzunely-page-header">';
        echo '<h1 class="zuzunely-page-title">' . __('Minhas Reservas', 'zuzunely-restaurant') . '</h1>';
        echo '<p class="zuzunely-page-subtitle">' . __('Gerencie suas reservas no restaurante', 'zuzunely-restaurant') . '</p>';
        echo '</div>';
        
        if ($consulta_token) {
            // Decodificar token para obter email
            $email = base64_decode($consulta_token);
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                echo $this->render_reservations_for_user($email);
            } else {
                echo '<div class="zuzunely-message error">' . __('Token de consulta inválido.', 'zuzunely-restaurant') . '</div>';
            }
        } elseif (is_user_logged_in()) {
            $current_user = wp_get_current_user();
            echo $this->render_reservations_for_user($current_user->user_email);
        } else {
            echo $this->render_login_form();
        }
        
        echo '</div>';
        
        get_footer();
    }
    
    /**
     * Adicionar link no menu
     */
    public function add_reservations_menu_link($items, $args) {
        // Só adicionar se o usuário estiver logado e for o menu principal
        if (is_user_logged_in() && isset($args->theme_location) && $args->theme_location === 'primary') {
            $reservations_link = '<li class="menu-item"><a href="' . $this->get_reservations_url() . '">' . __('Minhas Reservas', 'zuzunely-restaurant') . '</a></li>';
            $items .= $reservations_link;
        }
        
        return $items;
    }
    
    /**
     * Obter URL das reservas
     */
    private function get_reservations_url() {
        if (class_exists('WooCommerce')) {
            return wc_get_account_endpoint_url(self::$endpoint);
        } else {
            return home_url('/' . self::$endpoint . '/');
        }
    }
    
    /**
     * Enqueue scripts e estilos
     */
    public function enqueue_scripts() {
        if ($this->is_reservations_page()) {
            // Criar arquivo JS inline se não existir
            wp_enqueue_script('zuzunely-my-reservations', '', array('jquery'), ZUZUNELY_VERSION, true);
            wp_add_inline_script('zuzunely-my-reservations', $this->get_inline_js());
            
            wp_localize_script('zuzunely-my-reservations', 'zuzunely_my_reservations', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('zuzunely_my_reservations'),
                'messages' => array(
                    'confirm_cancel' => __('Tem certeza que deseja cancelar esta reserva?', 'zuzunely-restaurant'),
                    'cancelling' => __('Cancelando...', 'zuzunely-restaurant'),
                    'cancelled_success' => __('Reserva cancelada com sucesso!', 'zuzunely-restaurant'),
                    'cancel_error' => __('Erro ao cancelar reserva. Tente novamente.', 'zuzunely-restaurant'),
                )
            ));
            
            // Adicionar estilos
            wp_enqueue_style('zuzunely-my-reservations-style', '', array(), ZUZUNELY_VERSION);
            wp_add_inline_style('zuzunely-my-reservations-style', $this->get_styles());
        }
    }
    
    /**
     * JavaScript inline
     */
    private function get_inline_js() {
        return "
        jQuery(document).ready(function($) {
            // Cancelar reserva
            $('.cancel-reservation-btn').on('click', function() {
                if (!confirm(zuzunely_my_reservations.messages.confirm_cancel)) {
                    return;
                }
                
                var \$btn = $(this);
                var reservationId = \$btn.data('reservation-id');
                var \$card = \$btn.closest('.zuzunely-reservation-card');
                
                // Obter email do cliente se não estiver logado
                var customerEmail = '';
                if (!$('body').hasClass('logged-in')) {
                    customerEmail = prompt('Digite seu email para confirmar o cancelamento:');
                    if (!customerEmail) {
                        return;
                    }
                }
                
                \$btn.prop('disabled', true).text(zuzunely_my_reservations.messages.cancelling);
                \$btn.addClass('loading');
                
                $.ajax({
                    url: zuzunely_my_reservations.ajaxurl,
                    type: 'POST',
                    data: {
                        action: $('body').hasClass('logged-in') ? 'zuzunely_cancel_my_reservation' : 'zuzunely_cancel_my_reservation',
                        nonce: zuzunely_my_reservations.nonce,
                        reservation_id: reservationId,
                        customer_email: customerEmail
                    },
                    success: function(response) {
                        if (response.success) {
                            // Mostrar mensagem de sucesso
                            var successMsg = '<div class=\"zuzunely-message success\">' + 
                                           '<svg class=\"message-icon\" viewBox=\"0 0 24 24\" width=\"20\" height=\"20\"><path fill=\"currentColor\" d=\"M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z\"/></svg>' +
                                           response.data + 
                                           '</div>';
                            \$card.before(successMsg);
                            
                            // Atualizar o card para mostrar status cancelado
                            \$card.find('.status-badge')
                                 .removeClass()
                                 .addClass('status-badge status-cancelled')
                                 .html('<svg class=\"status-icon\" viewBox=\"0 0 24 24\" width=\"14\" height=\"14\"><path fill=\"currentColor\" d=\"M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z\"/></svg>Cancelada');
                            
                            // Remover botão de cancelar
                            \$card.find('.reservation-actions').fadeOut();
                            
                            // Adicionar classe de cancelada
                            \$card.addClass('reservation-cancelled');
                            
                            // Remover mensagem após 5 segundos
                            setTimeout(function() {
                                $('.zuzunely-message.success').fadeOut();
                            }, 5000);
                        } else {
                            alert(response.data || zuzunely_my_reservations.messages.cancel_error);
                            \$btn.prop('disabled', false).text('Cancelar Reserva').removeClass('loading');
                        }
                    },
                    error: function() {
                        alert(zuzunely_my_reservations.messages.cancel_error);
                        \$btn.prop('disabled', false).text('Cancelar Reserva').removeClass('loading');
                    }
                });
            });
            
            // Máscara para telefone
            $('#consultation_phone').on('input', function() {
                var value = $(this).val().replace(/\D/g, '');
                var formattedValue = '';
                
                if (value.length > 0) {
                    if (value.length <= 2) {
                        formattedValue = '(' + value;
                    } else if (value.length <= 7) {
                        formattedValue = '(' + value.substr(0, 2) + ') ' + value.substr(2);
                    } else {
                        formattedValue = '(' + value.substr(0, 2) + ') ' + value.substr(2, 5) + '-' + value.substr(7, 4);
                    }
                }
                
                $(this).val(formattedValue);
            });
            
            // Animações
            $('.zuzunely-reservation-card').each(function(index) {
                $(this).css('animation-delay', (index * 0.1) + 's');
            });
        });
        ";
    }
    
    /**
     * Verificar se estamos na página de reservas
     */
    private function is_reservations_page() {
        global $wp;
        
        // WooCommerce
        if (class_exists('WooCommerce') && is_account_page() && isset($wp->query_vars[self::$endpoint])) {
            return true;
        }
        
        // WordPress standalone
        if (get_query_var('minhas_reservas')) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Shortcode: [zuzunely_my_reservations]
     */
    public function render_my_reservations($atts) {
        $atts = shortcode_atts(array(
            'email' => '',
            'phone' => '',
            'show_form' => 'yes',
            'only_future' => 'no'
        ), $atts);
        
        ob_start();
        
        echo '<div class="zuzunely-my-reservations">';
        
        // Se usuário logado, mostrar suas reservas
        if (is_user_logged_in()) {
            $current_user = wp_get_current_user();
            echo $this->render_reservations_for_user($current_user->user_email, $atts['only_future'] === 'yes');
        }
        // Se tem email/phone nos atributos, mostrar reservas
        elseif (!empty($atts['email']) || !empty($atts['phone'])) {
            $identifier = !empty($atts['email']) ? $atts['email'] : $atts['phone'];
            echo $this->render_reservations_for_user($identifier, $atts['only_future'] === 'yes');
        }
        // Mostrar formulário de consulta
        elseif ($atts['show_form'] === 'yes') {
            echo $this->render_consultation_form();
        }
        else {
            echo '<p>' . __('Faça login para ver suas reservas.', 'zuzunely-restaurant') . '</p>';
        }
        
        echo '</div>';
        
        return ob_get_clean();
    }
    
    /**
     * Renderizar formulário de login/consulta
     */
    private function render_login_form() {
        ob_start();
        ?>
        <div class="zuzunely-login-section">
            <div class="login-icon">
                <svg viewBox="0 0 24 24" width="48" height="48">
                    <path fill="currentColor" d="M12 2C13.1 2 14 2.9 14 4C14 5.1 13.1 6 12 6C10.9 6 10 5.1 10 4C10 2.9 10.9 2 12 2ZM21 9V7L15 4V6C13.89 6 13 6.89 13 8V22H11V16H9V22H7V8C7 6.89 7.89 6 9 6V4L3 7V9H1V11H3L4 21H20L21 11H23V9H21Z"/>
                </svg>
            </div>
            <h2><?php _e('Acesse suas reservas', 'zuzunely-restaurant'); ?></h2>
            
            <?php if (class_exists('WooCommerce')) : ?>
                <p><?php _e('Faça login em sua conta para ver todas as suas reservas:', 'zuzunely-restaurant'); ?></p>
                <a href="<?php echo wc_get_page_permalink('myaccount'); ?>" class="button button-primary">
                    <svg viewBox="0 0 24 24" width="20" height="20">
                        <path fill="currentColor" d="M10 17V14H3V12H10V9L15 13L10 17M10 2H19C20.11 2 21 2.9 21 4V20C21 21.1 20.11 22 19 22H10C8.89 22 8 21.1 8 20V18H10V20H19V4H10V6H8V4C8 2.9 8.89 2 10 2Z"/>
                    </svg>
                    <?php _e('Fazer Login', 'zuzunely-restaurant'); ?>
                </a>
            <?php else : ?>
                <p><?php _e('Faça login para ver suas reservas:', 'zuzunely-restaurant'); ?></p>
                <a href="<?php echo wp_login_url(get_permalink()); ?>" class="button button-primary">
                    <svg viewBox="0 0 24 24" width="20" height="20">
                        <path fill="currentColor" d="M10 17V14H3V12H10V9L15 13L10 17M10 2H19C20.11 2 21 2.9 21 4V20C21 21.1 20.11 22 19 22H10C8.89 22 8 21.1 8 20V18H10V20H19V4H10V6H8V4C8 2.9 8.89 2 10 2Z"/>
                    </svg>
                    <?php _e('Fazer Login', 'zuzunely-restaurant'); ?>
                </a>
            <?php endif; ?>
            
            <div class="zuzunely-divider">
                <span><?php _e('ou', 'zuzunely-restaurant'); ?></span>
            </div>
            
            <?php echo $this->render_consultation_form(); ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Renderizar formulário de consulta por email/telefone
     */
    private function render_consultation_form() {
        ob_start();
        ?>
        <div class="zuzunely-consultation-form">
            <div class="form-header">
                <div class="form-icon">
                    <svg viewBox="0 0 24 24" width="32" height="32">
                        <path fill="currentColor" d="M9.5 3C10.3 3 11 3.7 11 4.5S10.3 6 9.5 6 8 5.3 8 4.5 8.7 3 9.5 3M6.5 8C7.3 8 8 8.7 8 9.5S7.3 11 6.5 11 5 10.3 5 9.5 5.7 8 6.5 8M9.5 13C8.1 13 7 14.1 7 15.5S8.1 18 9.5 18 12 16.9 12 15.5 10.9 13 9.5 13M14.5 3C13.7 3 13 3.7 13 4.5S13.7 6 14.5 6 16 5.3 16 4.5 15.3 3 14.5 3M17.5 8C16.7 8 16 8.7 16 9.5S16.7 11 17.5 11 19 10.3 19 9.5 18.3 8 17.5 8M14.5 13C15.9 13 17 14.1 17 15.5S15.9 18 14.5 18 12 16.9 12 15.5 13.1 13 14.5 13Z"/>
                    </svg>
                </div>
                <h3><?php _e('Consultar Reservas', 'zuzunely-restaurant'); ?></h3>
                <p><?php _e('Digite seu email ou telefone para encontrar suas reservas:', 'zuzunely-restaurant'); ?></p>
            </div>
            
            <form method="post" id="zuzunely-consultation-form">
                <?php wp_nonce_field('zuzunely_consultation', 'consultation_nonce'); ?>
                
                <div class="form-group">
                    <label for="consultation_email">
                        <svg viewBox="0 0 24 24" width="16" height="16">
                            <path fill="currentColor" d="M20 4H4C2.9 4 2.01 4.9 2.01 6L2 18C2 19.1 2.9 20 4 20H20C21.1 20 22 19.1 22 18V6C22 4.9 21.1 4 20 4ZM20 8L12 13L4 8V6L12 11L20 6V8Z"/>
                        </svg>
                        <?php _e('Email', 'zuzunely-restaurant'); ?>
                    </label>
                    <input type="email" id="consultation_email" name="consultation_email" 
                           placeholder="<?php _e('Digite seu email', 'zuzunely-restaurant'); ?>"
                           value="<?php echo isset($_POST['consultation_email']) ? esc_attr($_POST['consultation_email']) : ''; ?>">
                </div>
                
                <div class="form-divider">
                    <span><?php _e('OU', 'zuzunely-restaurant'); ?></span>
                </div>
                
                <div class="form-group">
                    <label for="consultation_phone">
                        <svg viewBox="0 0 24 24" width="16" height="16">
                            <path fill="currentColor" d="M6.62 10.79C8.06 13.62 10.38 15.94 13.21 17.38L15.41 15.18C15.69 14.9 16.08 14.82 16.43 14.93C17.55 15.3 18.75 15.5 20 15.5C20.55 15.5 21 15.95 21 16.5V20C21 20.55 20.55 21 20 21C10.61 21 3 13.39 3 4C3 3.45 3.45 3 4 3H7.5C8.05 3 8.5 3.45 8.5 4C8.5 5.25 8.7 6.45 9.07 7.57C9.18 7.92 9.1 8.31 8.82 8.59L6.62 10.79Z"/>
                        </svg>
                        <?php _e('Telefone', 'zuzunely-restaurant'); ?>
                    </label>
                    <input type="tel" id="consultation_phone" name="consultation_phone" 
                           placeholder="(11) 99999-9999"
                           value="<?php echo isset($_POST['consultation_phone']) ? esc_attr($_POST['consultation_phone']) : ''; ?>">
                </div>
                
                <button type="submit" class="button zuzunely-btn">
                    <svg viewBox="0 0 24 24" width="20" height="20">
                        <path fill="currentColor" d="M15.5 14H14.71L14.43 13.73C15.41 12.59 16 11.11 16 9.5C16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16C11.11 16 12.59 15.41 13.73 14.43L14 14.71V15.5L19 20.49L20.49 19L15.5 14M9.5 14C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14Z"/>
                    </svg>
                    <?php _e('Consultar Reservas', 'zuzunely-restaurant'); ?>
                </button>
            </form>
            
            <?php
            // Processar formulário
            if (isset($_POST['consultation_nonce']) && wp_verify_nonce($_POST['consultation_nonce'], 'zuzunely_consultation')) {
                $email = sanitize_email($_POST['consultation_email']);
                $phone = sanitize_text_field($_POST['consultation_phone']);
                
                if (!empty($email) || !empty($phone)) {
                    $identifier = !empty($email) ? $email : $phone;
                    echo $this->render_reservations_for_user($identifier, false, true);
                } else {
                    echo '<div class="zuzunely-message error">' . __('Por favor, informe seu email ou telefone.', 'zuzunely-restaurant') . '</div>';
                }
            }
            ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Renderizar reservas para um usuário específico
     */
    private function render_reservations_for_user($identifier, $only_future = false, $is_guest_query = false) {
        if (!class_exists('Zuzunely_Reservations_DB')) {
            return '<div class="zuzunely-message error">' . __('Sistema de reservas não disponível.', 'zuzunely-restaurant') . '</div>';
        }
        
        $reservations_db = new Zuzunely_Reservations_DB();
        
        // Buscar reservas por email ou telefone
        global $wpdb;
        $reservations_table = $reservations_db->get_reservations_table();
        
        $sql = "SELECT r.*, t.name as table_name, s.name as saloon_name 
                FROM $reservations_table r
                LEFT JOIN {$wpdb->prefix}zuzunely_tables t ON r.table_id = t.id
                LEFT JOIN {$wpdb->prefix}zuzunely_saloons s ON t.saloon_id = s.id
                WHERE (r.customer_email = %s OR r.customer_phone = %s)
                AND r.is_active = 1
                ORDER BY r.reservation_date DESC, r.reservation_time DESC";
        
        $reservations = $wpdb->get_results(
            $wpdb->prepare($sql, $identifier, $identifier),
            ARRAY_A
        );
        
        if (empty($reservations)) {
            return '<div class="zuzunely-message info">
                <svg viewBox="0 0 24 24" width="24" height="24">
                    <path fill="currentColor" d="M13 13H11V7H13M13 17H11V15H13M12 2C6.48 2 2 6.48 2 12S6.48 22 12 22 22 17.52 22 12 17.52 2 12 2Z"/>
                </svg>
                ' . __('Nenhuma reserva encontrada.', 'zuzunely-restaurant') . '
            </div>';
        }
        
        // Separar reservas futuras e passadas
        $now = current_time('mysql');
        $future_reservations = array();
        $past_reservations = array();
        
        foreach ($reservations as $reservation) {
            $reservation_datetime = $reservation['reservation_date'] . ' ' . $reservation['reservation_time'];
            
            if (strtotime($reservation_datetime) > strtotime($now)) {
                $future_reservations[] = $reservation;
            } else {
                $past_reservations[] = $reservation;
            }
        }
        
        ob_start();
        
        if ($is_guest_query) {
            echo '<div class="zuzunely-guest-results">';
            echo '<div class="guest-header">';
            echo '<svg viewBox="0 0 24 24" width="32" height="32">';
            echo '<path fill="currentColor" d="M9 11H7V9H9V11M13 11H11V9H13V11M17 11H15V9H17V11M19 3H18V1H16V3H8V1H6V3H5C3.89 3 3.01 3.9 3.01 5L3 19C3 20.1 3.89 21 5 21H19C20.1 21 21 20.1 21 19V5C21 3.9 20.1 3 19 3M19 19H5V8H19V19Z"/>';
            echo '</svg>';
            echo '<h3>' . sprintf(__('Reservas encontradas para %s', 'zuzunely-restaurant'), esc_html($identifier)) . '</h3>';
            echo '</div>';
        }
        
        // Reservas futuras
        if (!empty($future_reservations) || !$only_future) {
            echo '<div class="zuzunely-future-reservations">';
            echo '<div class="section-header">';
            echo '<svg viewBox="0 0 24 24" width="24" height="24">';
            echo '<path fill="currentColor" d="M12 2C6.48 2 2 6.48 2 12S6.48 22 12 22 22 17.52 22 12 17.52 2 12 2M13 17L8 12L9.41 10.59L13 14.17L20.59 6.58L22 8L13 17Z"/>';
            echo '</svg>';
            echo '<h3>' . __('Próximas Reservas', 'zuzunely-restaurant') . '</h3>';
            echo '</div>';
            
            if (!empty($future_reservations)) {
                echo $this->render_reservations_table($future_reservations, true);
            } else {
                echo '<div class="empty-state">';
                echo '<svg viewBox="0 0 24 24" width="48" height="48">';
                echo '<path fill="currentColor" d="M19 3H18V1H16V3H8V1H6V3H5C3.89 3 3.01 3.9 3.01 5L3 19C3 20.1 3.89 21 5 21H19C20.1 21 21 20.1 21 19V5C21 3.9 20.1 3 19 3M19 19H5V8H19V19Z"/>';
                echo '</svg>';
                echo '<p>' . __('Você não possui reservas futuras.', 'zuzunely-restaurant') . '</p>';
                echo '</div>';
            }
            
            echo '</div>';
        }
        
        // Reservas passadas (só se não for only_future)
        if (!$only_future) {
            echo '<div class="zuzunely-past-reservations">';
            echo '<div class="section-header">';
            echo '<svg viewBox="0 0 24 24" width="24" height="24">';
            echo '<path fill="currentColor" d="M12 2C6.5 2 2 6.5 2 12S6.5 22 12 22 22 17.5 22 12 17.5 2 12 2M12 20C7.59 20 4 16.41 4 12S7.59 4 12 4 20 7.59 20 12 16.41 20 12 20M16.5 12C16.5 14.49 14.49 16.5 12 16.5S7.5 14.49 7.5 12 9.51 7.5 12 7.5 16.5 9.51 16.5 12M15 12C15 13.66 13.66 15 12 15S9 13.66 9 12 10.34 9 12 9 15 10.34 15 12Z"/>';
            echo '</svg>';
            echo '<h3>' . __('Reservas Anteriores', 'zuzunely-restaurant') . '</h3>';
            echo '</div>';
            
            if (!empty($past_reservations)) {
                echo $this->render_reservations_table($past_reservations, false);
            } else {
                echo '<div class="empty-state">';
                echo '<svg viewBox="0 0 24 24" width="48" height="48">';
                echo '<path fill="currentColor" d="M12 2C6.5 2 2 6.5 2 12S6.5 22 12 22 22 17.5 22 12 17.5 2 12 2M12 20C7.59 20 4 16.41 4 12S7.59 4 12 4 20 7.59 20 12 16.41 20 12 20Z"/>';
                echo '</svg>';
                echo '<p>' . __('Você não possui reservas anteriores.', 'zuzunely-restaurant') . '</p>';
                echo '</div>';
            }
            
            echo '</div>';
        }
        
        if ($is_guest_query) {
            echo '</div>';
        }
        
        return ob_get_clean();
    }
    
    /**
     * Renderizar tabela de reservas
     */
    private function render_reservations_table($reservations, $allow_cancel = false) {
        $status_labels = array(
            'pending' => __('Pendente', 'zuzunely-restaurant'),
            'confirmed' => __('Confirmada', 'zuzunely-restaurant'),
            'completed' => __('Concluída', 'zuzunely-restaurant'),
            'cancelled' => __('Cancelada', 'zuzunely-restaurant'),
            'no_show' => __('Não compareceu', 'zuzunely-restaurant')
        );
        
        $status_icons = array(
            'pending' => '<path fill="currentColor" d="M12 2C6.48 2 2 6.48 2 12S6.48 22 12 22 22 17.52 22 12 17.52 2 12 2M13 17H11V15H13M13 13H11V7H13"/>',
            'confirmed' => '<path fill="currentColor" d="M12 2C6.48 2 2 6.48 2 12S6.48 22 12 22 22 17.52 22 12 17.52 2 12 2M10 17L5 12L6.41 10.59L10 14.17L17.59 6.58L19 8L10 17Z"/>',
            'completed' => '<path fill="currentColor" d="M12 2C6.48 2 2 6.48 2 12S6.48 22 12 22 22 17.52 22 12 17.52 2 12 2M9 16.2L4.8 12L6.21 10.59L9 13.38L17.79 4.59L19.2 6L9 16.2Z"/>',
            'cancelled' => '<path fill="currentColor" d="M12 2C6.47 2 2 6.47 2 12S6.47 22 12 22 22 17.53 22 12 17.53 2 12 2M17 15.59L15.59 17L12 13.41L8.41 17L7 15.59L10.59 12L7 8.41L8.41 7L12 10.59L15.59 7L17 8.41L13.41 12L17 15.59Z"/>',
            'no_show' => '<path fill="currentColor" d="M12 2C6.48 2 2 6.48 2 12S6.48 22 12 22 22 17.52 22 12 17.52 2 12 2M8.5 7L10 8.5L8.5 10L7 8.5L8.5 7M16 8.5L14.5 10L16 11.5L17.5 10L16 8.5M16 14H8C8 16.21 9.79 18 12 18S16 16.21 16 14Z"/>'
        );
        
        ob_start();
        ?>
        <div class="zuzunely-reservations-grid">
            <?php foreach ($reservations as $reservation) : ?>
                <div class="zuzunely-reservation-card fadeInUp" data-reservation-id="<?php echo $reservation['id']; ?>">
                    <div class="reservation-header">
                        <div class="reservation-date">
                            <div class="date-display">
                                <span class="day"><?php echo date_i18n('d', strtotime($reservation['reservation_date'])); ?></span>
                                <span class="month"><?php echo date_i18n('M', strtotime($reservation['reservation_date'])); ?></span>
                                <span class="year"><?php echo date_i18n('Y', strtotime($reservation['reservation_date'])); ?></span>
                            </div>
                            <div class="time-display">
                                <svg viewBox="0 0 24 24" width="16" height="16">
                                    <path fill="currentColor" d="M12 2C6.5 2 2 6.5 2 12S6.5 22 12 22 22 17.5 22 12 17.5 2 12 2M12 20C7.58 20 4 16.42 4 12S7.58 4 12 4 20 7.58 20 12 16.42 20 12 20M12.5 7H11V12.25L15.5 14.85L16.25 13.65L12.5 11.5V7Z"/>
                                </svg>
                                <span><?php echo date_i18n('H:i', strtotime($reservation['reservation_time'])); ?></span>
                            </div>
                        </div>
                        <div class="reservation-status">
                            <span class="status-badge status-<?php echo $reservation['status']; ?>">
                                <svg class="status-icon" viewBox="0 0 24 24" width="14" height="14">
                                    <?php echo $status_icons[$reservation['status']] ?? $status_icons['pending']; ?>
                                </svg>
                                <?php echo isset($status_labels[$reservation['status']]) ? $status_labels[$reservation['status']] : $reservation['status']; ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="reservation-details">
                        <?php if (!empty($reservation['table_name'])) : ?>
                            <div class="detail-row">
                                <svg class="detail-icon" viewBox="0 0 24 24" width="16" height="16">
                                    <path fill="currentColor" d="M12 2C6.48 2 2 6.48 2 12S6.48 22 12 22 22 17.52 22 12 17.52 2 12 2M12 20C7.58 20 4 16.42 4 12S7.58 4 12 4 20 7.58 20 12 16.42 20 12 20M17 12H12V7H10V14H17V12Z"/>
                                </svg>
                                <span class="label"><?php _e('Mesa:', 'zuzunely-restaurant'); ?></span>
                                <span class="value"><?php echo esc_html($reservation['table_name']); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($reservation['saloon_name'])) : ?>
                            <div class="detail-row">
                                <svg class="detail-icon" viewBox="0 0 24 24" width="16" height="16">
                                    <path fill="currentColor" d="M12 2L2 7V10H22V7L12 2M4 11V19C4 20.1 4.9 21 6 21H8C9.1 21 10 20.1 10 19V11H4M14 11V19C14 20.1 14.9 21 16 21H18C19.1 21 20 20.1 20 19V11H14Z"/>
                                </svg>
                                <span class="label"><?php _e('Salão:', 'zuzunely-restaurant'); ?></span>
                                <span class="value"><?php echo esc_html($reservation['saloon_name']); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="detail-row">
                            <svg class="detail-icon" viewBox="0 0 24 24" width="16" height="16">
                                <path fill="currentColor" d="M16 4C16.55 4 17 4.45 17 5S16.55 6 16 6 15 5.55 15 5 15.45 4 16 4M13 1.07C13 .47 13.47 0 14.07 0H17.93C18.53 0 19 .47 19 1.07S18.53 2.14 17.93 2.14H14.07C13.47 2.14 13 1.67 13 1.07M12.5 12H11.5V11H12.5V12M12.5 20H11.5V13H12.5V20M16 9C13.79 9 12 10.79 12 13S13.79 17 16 17 20 15.21 20 13 18.21 9 16 9M16 15.5C14.62 15.5 13.5 14.38 13.5 13S14.62 10.5 16 10.5 18.5 11.62 18.5 13 17.38 15.5 16 15.5Z"/>
                            </svg>
                            <span class="label"><?php _e('Pessoas:', 'zuzunely-restaurant'); ?></span>
                            <span class="value"><?php echo $reservation['guests_count']; ?></span>
                        </div>
                        
                        <div class="detail-row">
                            <svg class="detail-icon" viewBox="0 0 24 24" width="16" height="16">
                                <path fill="currentColor" d="M12 20C16.42 20 20 16.42 20 12S16.42 4 12 4 4 7.58 4 12 7.58 20 12 20M12 2C17.52 2 22 6.48 22 12S17.52 22 12 22 2 17.52 2 12 6.48 2 12 2M12.5 7H11V12.25L15.5 14.85L16.25 13.65L12.5 11.5V7Z"/>
                            </svg>
                            <span class="label"><?php _e('Duração:', 'zuzunely-restaurant'); ?></span>
                            <span class="value"><?php echo $reservation['duration']; ?> min</span>
                        </div>
                        
                        <?php if (!empty($reservation['notes'])) : ?>
                            <div class="detail-row notes">
                                <svg class="detail-icon" viewBox="0 0 24 24" width="16" height="16">
                                    <path fill="currentColor" d="M14 2H6C4.89 2 4 2.9 4 4V20C4 21.11 4.89 22 6 22H18C19.11 22 20 21.11 20 20V8L14 2M18 20H6V4H13V9H18V20Z"/>
                                </svg>
                                <span class="label"><?php _e('Observações:', 'zuzunely-restaurant'); ?></span>
                                <span class="value"><?php echo esc_html($reservation['notes']); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="detail-row reservation-id">
                            <svg class="detail-icon" viewBox="0 0 24 24" width="16" height="16">
                                <path fill="currentColor" d="M12 2C6.48 2 2 6.48 2 12S6.48 22 12 22 22 17.52 22 12 17.52 2 12 2M13 17H11V15H13M13 13H11V7H13"/>
                            </svg>
                            <span class="label"><?php _e('ID:', 'zuzunely-restaurant'); ?></span>
                            <span class="value">#<?php echo $reservation['id']; ?></span>
                        </div>
                    </div>
                    
                    <?php if ($allow_cancel && in_array($reservation['status'], array('pending', 'confirmed'))) : ?>
                        <div class="reservation-actions">
                            <button type="button" class="cancel-reservation-btn" 
                                    data-reservation-id="<?php echo $reservation['id']; ?>">
                                <svg viewBox="0 0 24 24" width="16" height="16">
                                    <path fill="currentColor" d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                                </svg>
                                <?php _e('Cancelar Reserva', 'zuzunely-restaurant'); ?>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Cancelar reserva (usuário logado)
     */
    public function cancel_reservation() {
        check_ajax_referer('zuzunely_my_reservations', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(__('Você precisa estar logado para cancelar uma reserva.', 'zuzunely-restaurant'));
        }
        
        $reservation_id = intval($_POST['reservation_id']);
        $current_user = wp_get_current_user();
        
        $this->process_cancellation($reservation_id, $current_user->user_email);
    }
    
    /**
     * Cancelar reserva (usuário não logado)
     */
    public function cancel_reservation_guest() {
        check_ajax_referer('zuzunely_my_reservations', 'nonce');
        
        $reservation_id = intval($_POST['reservation_id']);
        $email = sanitize_email($_POST['customer_email']);
        
        if (empty($email)) {
            wp_send_json_error(__('Email é obrigatório para cancelar a reserva.', 'zuzunely-restaurant'));
        }
        
        $this->process_cancellation($reservation_id, $email);
    }
    
    /**
     * Processar cancelamento
     */
    private function process_cancellation($reservation_id, $customer_identifier) {
        if (!class_exists('Zuzunely_Reservations_DB')) {
            wp_send_json_error(__('Sistema de reservas não disponível.', 'zuzunely-restaurant'));
        }
        
        $reservations_db = new Zuzunely_Reservations_DB();
        
        // Verificar se a reserva existe e pertence ao cliente
        $reservation = $reservations_db->get_reservation($reservation_id);
        
        if (!$reservation) {
            wp_send_json_error(__('Reserva não encontrada.', 'zuzunely-restaurant'));
        }
        
        // Verificar se pertence ao cliente
        if ($reservation['customer_email'] !== $customer_identifier && $reservation['customer_phone'] !== $customer_identifier) {
            wp_send_json_error(__('Esta reserva não pertence a você.', 'zuzunely-restaurant'));
        }
        
        // Verificar se pode ser cancelada
        if (!in_array($reservation['status'], array('pending', 'confirmed'))) {
            wp_send_json_error(__('Esta reserva não pode ser cancelada.', 'zuzunely-restaurant'));
        }
        
        // Verificar se é uma reserva futura
        $reservation_datetime = $reservation['reservation_date'] . ' ' . $reservation['reservation_time'];
        if (strtotime($reservation_datetime) <= current_time('timestamp')) {
            wp_send_json_error(__('Não é possível cancelar reservas passadas.', 'zuzunely-restaurant'));
        }
        
        // Atualizar status para cancelada
        $reservation['status'] = 'cancelled';
        $result = $reservations_db->update_reservation($reservation_id, $reservation);
        
        if ($result) {
            // Log do cancelamento
            if (class_exists('Zuzunely_Logger')) {
                Zuzunely_Logger::info("Reserva #{$reservation_id} cancelada pelo cliente: {$customer_identifier}");
            }
            
            wp_send_json_success(__('Reserva cancelada com sucesso!', 'zuzunely-restaurant'));
        } else {
            wp_send_json_error(__('Erro ao cancelar reserva. Tente novamente.', 'zuzunely-restaurant'));
        }
    }
    
    /**
     * Estilos CSS melhorados
     */
    private function get_styles() {
        return '
        /* Reset e base */
        .zuzunely-reservations-container,
        .zuzunely-my-reservations {
            --primary-color: #667eea;
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --error-color: #ef4444;
            --info-color: #3b82f6;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --bg-primary: #ffffff;
            --bg-secondary: #f9fafb;
            --border-color: #e5e7eb;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --radius-sm: 6px;
            --radius-md: 8px;
            --radius-lg: 12px;
            --radius-xl: 16px;
        }
        
        /* Container principal */
        .zuzunely-reservations-container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 0 20px;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            line-height: 1.6;
            color: var(--text-primary);
        }
        
        .zuzunely-my-reservations {
            max-width: 1200px;
            margin: 20px auto;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            line-height: 1.6;
            color: var(--text-primary);
        }
        
        /* Header da página */
        .zuzunely-page-header {
            text-align: center;
            margin-bottom: 40px;
            padding: 40px 20px;
            background: var(--primary-gradient);
            color: white;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-xl);
        }
        
        .zuzunely-page-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 0 0 12px 0;
            letter-spacing: -0.025em;
        }
        
        .zuzunely-page-subtitle {
            font-size: 1.125rem;
            opacity: 0.9;
            margin: 0;
            font-weight: 400;
        }
        
        /* Seção de login */
        .zuzunely-login-section {
            text-align: center;
            padding: 60px 40px;
            background: var(--primary-gradient);
            color: white;
            border-radius: var(--radius-xl);
            margin-bottom: 40px;
            box-shadow: var(--shadow-xl);
            position: relative;
            overflow: hidden;
        }
        
        .zuzunely-login-section::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width=\"60\" height=\"60\" viewBox=\"0 0 60 60\" xmlns=\"http://www.w3.org/2000/svg\"%3E%3Cg fill=\"none\" fill-rule=\"evenodd\"%3E%3Cg fill=\"%23ffffff\" fill-opacity=\"0.1\"%3E%3Ccircle cx=\"30\" cy=\"30\" r=\"2\"/%3E%3C/g%3E%3C/g%3E%3C/svg%3E") repeat;
            pointer-events: none;
        }
        
        .login-icon {
            margin-bottom: 24px;
            opacity: 0.9;
        }
        
        .login-icon svg {
            filter: drop-shadow(0 4px 8px rgba(0,0,0,0.1));
        }
        
        .zuzunely-login-section h2 {
            font-size: 2rem;
            font-weight: 700;
            margin: 0 0 16px 0;
            position: relative;
        }
        
        .zuzunely-login-section p {
            font-size: 1.125rem;
            margin-bottom: 32px;
            opacity: 0.9;
            position: relative;
        }
        
        .zuzunely-login-section .button {
            background: white;
            color: var(--primary-color);
            padding: 16px 32px;
            border: none;
            border-radius: var(--radius-md);
            font-weight: 600;
            font-size: 1rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-lg);
            position: relative;
        }
        
        .zuzunely-login-section .button:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-xl);
            color: var(--primary-color);
        }
        
        .zuzunely-login-section .button svg {
            transition: transform 0.3s ease;
        }
        
        .zuzunely-login-section .button:hover svg {
            transform: translateX(2px);
        }
        
        /* Formulário de consulta */
        .zuzunely-consultation-form {
            background: var(--bg-primary);
            padding: 40px;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-xl);
            margin-top: 30px;
            border: 1px solid var(--border-color);
            position: relative;
        }
        
        .form-header {
            text-align: center;
            margin-bottom: 32px;
        }
        
        .form-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 64px;
            height: 64px;
            background: var(--primary-gradient);
            border-radius: 50%;
            margin-bottom: 20px;
            color: white;
            box-shadow: var(--shadow-lg);
        }
        
        .zuzunely-consultation-form h3 {
            margin: 0 0 12px 0;
            color: var(--text-primary);
            font-size: 1.875rem;
            font-weight: 700;
            letter-spacing: -0.025em;
        }
        
        .zuzunely-consultation-form > p {
            color: var(--text-secondary);
            margin-bottom: 0;
            font-size: 1.125rem;
        }
        
        /* Grupos de formulário */
        .form-group {
            margin-bottom: 24px;
        }
        
        .form-group label {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .form-group label svg {
            color: var(--primary-color);
        }
        
        .form-group input {
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
            padding: 16px 20px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-lg);
            font-size: 1rem;
            transition: all 0.3s ease;
            background: var(--bg-secondary);
            color: var(--text-primary);
            font-family: inherit;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: var(--primary-color);
            background: var(--bg-primary);
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
            transform: translateY(-1px);
        }
        
        .form-group input::placeholder {
            color: var(--text-secondary);
        }
        
        /* Divisores */
        .form-divider,
        .zuzunely-divider {
            text-align: center;
            margin: 32px 0;
            position: relative;
        }
        
        .form-divider::before,
        .zuzunely-divider::before {
            content: "";
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: var(--border-color);
            z-index: 1;
        }
        
        .form-divider span,
        .zuzunely-divider span {
            background: var(--bg-primary);
            padding: 0 20px;
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            position: relative;
            z-index: 2;
        }
        
        .zuzunely-divider span {
            background: transparent;
        }
        
        /* Botões */
        .zuzunely-btn,
        .button {
            background: var(--primary-gradient);
            color: white;
            padding: 16px 24px;
            border: none;
            border-radius: var(--radius-md);
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-md);
            font-family: inherit;
        }
        
        .zuzunely-btn:hover,
        .button:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            color: white;
        }
        
        .zuzunely-btn svg,
        .button svg {
            transition: transform 0.3s ease;
        }
        
        .zuzunely-btn:hover svg,
        .button:hover svg {
            transform: scale(1.1);
        }
        
        /* Seções de reservas */
        .zuzunely-future-reservations,
        .zuzunely-past-reservations {
            margin-bottom: 40px;
        }
        
        .section-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 2px solid var(--border-color);
        }
        
        .section-header svg {
            color: var(--primary-color);
        }
        
        .section-header h3 {
            margin: 0;
            color: var(--text-primary);
            font-size: 1.5rem;
            font-weight: 700;
        }
        
        /* Grid de reservas */
        .zuzunely-reservations-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 24px;
            margin-top: 20px;
        }
        
        /* Cards de reserva */
        .zuzunely-reservation-card {
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-xl);
            overflow: hidden;
            box-shadow: var(--shadow-md);
            transition: all 0.3s ease;
            position: relative;
        }
        
        .zuzunely-reservation-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-xl);
        }
        
        .zuzunely-reservation-card.reservation-cancelled {
            opacity: 0.7;
            filter: grayscale(0.3);
        }
        
        /* Animação de entrada */
        .fadeInUp {
            animation: fadeInUp 0.6s ease-out forwards;
            opacity: 0;
            transform: translateY(20px);
        }
        
        @keyframes fadeInUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Header do card */
        .reservation-header {
            background: linear-gradient(135deg, var(--bg-secondary) 0%, #f3f4f6 100%);
            padding: 20px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
        }
        
        .reservation-date {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .date-display {
            display: flex;
            flex-direction: column;
            align-items: center;
            background: var(--primary-gradient);
            color: white;
            padding: 12px;
            border-radius: var(--radius-md);
            min-width: 60px;
            box-shadow: var(--shadow-sm);
        }
        
        .date-display .day {
            font-size: 1.5rem;
            font-weight: 700;
            line-height: 1;
        }
        
        .date-display .month {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            opacity: 0.9;
        }
        
        .date-display .year {
            font-size: 0.75rem;
            opacity: 0.8;
        }
        
        .time-display {
            display: flex;
            align-items: center;
            gap: 6px;
            color: var(--text-secondary);
            font-weight: 600;
        }
        
        .time-display span {
            font-size: 1.125rem;
            color: var(--text-primary);
        }
        
        /* Status badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            box-shadow: var(--shadow-sm);
        }
        
        .status-badge.status-pending {
            background: #fef3c7;
            color: #d97706;
            border: 1px solid #fbbf24;
        }
        
        .status-badge.status-confirmed {
            background: #d1fae5;
            color: #059669;
            border: 1px solid #34d399;
        }
        
        .status-badge.status-completed {
            background: #dbeafe;
            color: #2563eb;
            border: 1px solid #60a5fa;
        }
        
        .status-badge.status-cancelled {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #f87171;
        }
        
        .status-badge.status-no_show {
            background: #f3f4f6;
            color: #6b7280;
            border: 1px solid #d1d5db;
        }
        
        .status-icon {
            flex-shrink: 0;
        }
        
        /* Detalhes da reserva */
        .reservation-details {
            padding: 24px;
        }
        
        .detail-row {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 16px;
            padding: 8px 0;
        }
        
        .detail-row:last-child {
            margin-bottom: 0;
        }
        
        .detail-row.notes {
            flex-direction: column;
            align-items: flex-start;
            background: var(--bg-secondary);
            padding: 16px;
            border-radius: var(--radius-md);
            border-left: 4px solid var(--primary-color);
        }
        
        .detail-row.notes .detail-icon {
            margin-bottom: 8px;
        }
        
        .detail-row.reservation-id {
            background: #f8fafc;
            padding: 12px;
            border-radius: var(--radius-md);
            border: 1px dashed var(--border-color);
        }
        
        .detail-icon {
            color: var(--primary-color);
            flex-shrink: 0;
            margin-top: 2px;
        }
        
        .detail-row .label {
            font-weight: 600;
            color: var(--text-secondary);
            min-width: 80px;
            font-size: 0.875rem;
        }
        
        .detail-row .value {
            color: var(--text-primary);
            flex: 1;
            font-weight: 500;
        }
        
        .detail-row.notes .label,
        .detail-row.notes .value {
            width: 100%;
        }
        
        /* Ações da reserva */
        .reservation-actions {
            padding: 20px 24px;
            background: var(--bg-secondary);
            border-top: 1px solid var(--border-color);
            text-align: center;
        }
        
        .cancel-reservation-btn {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: var(--radius-md);
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
        }
        
        .cancel-reservation-btn:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }
        
        .cancel-reservation-btn:disabled,
        .cancel-reservation-btn.loading {
            background: #9ca3af;
            cursor: not-allowed;
            transform: none;
        }
        
        .cancel-reservation-btn.loading {
            position: relative;
        }
        
        .cancel-reservation-btn.loading::after {
            content: "";
            position: absolute;
            width: 16px;
            height: 16px;
            border: 2px solid transparent;
            border-top: 2px solid currentColor;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-left: 8px;
        }
        
        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }
        
        /* Mensagens */
        .zuzunely-message {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px 20px;
            border-radius: var(--radius-md);
            margin: 20px 0;
            font-weight: 500;
            box-shadow: var(--shadow-sm);
        }
        
        .message-icon {
            flex-shrink: 0;
        }
        
        .zuzunely-message.success {
            background: #d1fae5;
            border: 1px solid #34d399;
            color: #065f46;
        }
        
        .zuzunely-message.error {
            background: #fee2e2;
            border: 1px solid #f87171;
            color: #991b1b;
        }
        
        .zuzunely-message.info {
            background: #dbeafe;
            border: 1px solid #60a5fa;
            color: #1e40af;
        }
        
        /* Resultados de busca de convidado */
        .zuzunely-guest-results {
            background: var(--bg-secondary);
            padding: 32px;
            border-radius: var(--radius-xl);
            margin-top: 32px;
            border: 1px solid var(--border-color);
        }
        
        .guest-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 2px solid var(--border-color);
        }
        
        .guest-header svg {
            color: var(--primary-color);
        }
        
        .guest-header h3 {
            margin: 0;
            color: var(--text-primary);
            font-size: 1.5rem;
            font-weight: 700;
        }
        
        /* Estado vazio */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
        }
        
        .empty-state svg {
            opacity: 0.5;
            margin-bottom: 16px;
        }
        
        .empty-state p {
            font-size: 1.125rem;
            margin: 0;
        }
        
        /* Responsividade */
        @media (max-width: 768px) {
            .zuzunely-reservations-container {
                padding: 0 16px;
            }
            
            .zuzunely-consultation-form {
                padding: 24px;
                margin: 20px 0;
            }
            
            .zuzunely-login-section {
                padding: 40px 24px;
            }
            
            .zuzunely-page-header {
                padding: 32px 20px;
            }
            
            .zuzunely-page-title {
                font-size: 2rem;
            }
            
            .zuzunely-reservations-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }
            
            .reservation-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
                padding: 16px 20px;
            }
            
            .reservation-date {
                width: 100%;
                justify-content: space-between;
            }
            
            .detail-row {
                flex-direction: column;
                gap: 8px;
            }
            
            .detail-row .label {
                min-width: auto;
                margin-bottom: 4px;
            }
            
            .form-group input {
                font-size: 16px; /* Evita zoom no iOS */
            }
            
            .date-display {
                min-width: 50px;
                padding: 8px;
            }
            
            .date-display .day {
                font-size: 1.25rem;
            }
            
            .time-display span {
                font-size: 1rem;
            }
        }
        
        @media (max-width: 480px) {
            .zuzunely-consultation-form {
                padding: 20px;
            }
            
            .zuzunely-login-section {
                padding: 32px 20px;
            }
            
            .reservation-details {
                padding: 20px;
            }
            
            .reservation-actions {
                padding: 16px 20px;
            }
        }
        
        /* Melhorias de acessibilidade */
        .zuzunely-reservation-card:focus-within {
            outline: 2px solid var(--primary-color);
            outline-offset: 2px;
        }
        
        .cancel-reservation-btn:focus {
            outline: 2px solid var(--error-color);
            outline-offset: 2px;
        }
        
        .form-group input:focus {
            outline: 2px solid var(--primary-color);
            outline-offset: 2px;
        }
        
        /* Estados de carregamento */
        .zuzunely-reservations-grid.loading {
            opacity: 0.6;
            pointer-events: none;
        }
        
        /* Animações suaves */
        * {
            transition: opacity 0.3s ease, transform 0.3s ease;
        }
        
        /* Scroll suave para ancoras */
        html {
            scroll-behavior: smooth;
        }
        ';
    }
    
    /**
     * Método estático para marcar flush de rewrite rules
     */
    public static function mark_flush_rewrite_rules() {
        update_option('zuzunely_flush_rewrite_rules_flag', 1);
    }
}

// Inicializar a classe
new Zuzunely_My_Reservations();