<?php
/**
 * Classe para exibir lista de reservas em formato de tabela
 */

if (!defined('ABSPATH')) {
    exit;
}

// Verificar se a classe WP_List_Table existe
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class Zuzunely_Reservations_List_Table extends WP_List_Table {
    
    /**
     * Construtor
     */
    public function __construct() {
        parent::__construct(array(
            'singular' => 'reservation',
            'plural' => 'reservations',
            'ajax' => false
        ));
    }
    
    /**
     * Obter colunas da tabela
     */
    public function get_columns() {
        $columns = array(
            'cb' => '<input type="checkbox" />',
            'id' => __('ID', 'zuzunely-restaurant'),
            'customer_name' => __('Cliente', 'zuzunely-restaurant'),
            'guests_count' => __('Pessoas', 'zuzunely-restaurant'),
            'reservation_datetime' => __('Data e Hora', 'zuzunely-restaurant'),
            'table_info' => __('Mesa / Salão', 'zuzunely-restaurant'),
            'status' => __('Status', 'zuzunely-restaurant'),
            'contact' => __('Contato', 'zuzunely-restaurant')
        );
        
        return $columns;
    }
    
    /**
     * Colunas que podem ser ordenadas
     */
    public function get_sortable_columns() {
        $sortable_columns = array(
            'id' => array('id', true),
            'customer_name' => array('customer_name', false),
            'guests_count' => array('guests_count', false),
            'reservation_datetime' => array('reservation_date', false),
            'status' => array('status', false)
        );
        
        return $sortable_columns;
    }
    
    /**
     * Obter ações em massa
     */
    public function get_bulk_actions() {
        $actions = array(
            'delete' => __('Excluir', 'zuzunely-restaurant'),
            'confirm' => __('Confirmar', 'zuzunely-restaurant'),
            'cancel' => __('Cancelar', 'zuzunely-restaurant')
        );
        
        return $actions;
    }
    
    /**
     * Processar ações em massa
     */
    public function process_bulk_action() {
        // Verificar se uma ação e seleção de itens foram enviadas
        if ($this->current_action() && isset($_POST['reservation']) && is_array($_POST['reservation'])) {
            // Instanciar banco de dados
            $db = new Zuzunely_Reservations_DB();
            
            // Obter IDs selecionados
            $ids = array_map('intval', $_POST['reservation']);
            
            // Processar ação
            switch ($this->current_action()) {
                case 'delete':
                    foreach ($ids as $id) {
                        $db->delete_reservation($id);
                    }
                    
                    // Adicionar mensagem de sucesso
                    add_settings_error(
                        'zuzunely_reservations',
                        'zuzunely_reservation_success',
                        __('Reservas excluídas com sucesso!', 'zuzunely-restaurant'),
                        'success'
                    );
                    break;
                    
                case 'confirm':
                    foreach ($ids as $id) {
                        $reservation = $db->get_reservation($id);
                        if ($reservation) {
                            $reservation['status'] = 'confirmed';
                            $db->update_reservation($id, $reservation);
                        }
                    }
                    
                    // Adicionar mensagem de sucesso
                    add_settings_error(
                        'zuzunely_reservations',
                        'zuzunely_reservation_success',
                        __('Reservas confirmadas com sucesso!', 'zuzunely-restaurant'),
                        'success'
                    );
                    break;
                    
                case 'cancel':
                    foreach ($ids as $id) {
                        $reservation = $db->get_reservation($id);
                        if ($reservation) {
                            $reservation['status'] = 'cancelled';
                            $db->update_reservation($id, $reservation);
                        }
                    }
                    
                    // Adicionar mensagem de sucesso
                    add_settings_error(
                        'zuzunely_reservations',
                        'zuzunely_reservation_success',
                        __('Reservas canceladas com sucesso!', 'zuzunely-restaurant'),
                        'success'
                    );
                    break;
            }
            
            // Redirecionar para atualizar a página
            wp_redirect(admin_url('admin.php?page=zuzunely-reservations&updated=1'));
            exit;
        }
    }
    
    /**
     * Renderizar coluna padrão
     */
    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'id':
                return $item['id'];
                
            case 'guests_count':
                return $item['guests_count'];
                
            case 'reservation_datetime':
                $date = date_i18n(get_option('date_format'), strtotime($item['reservation_date']));
                $time = date_i18n(get_option('time_format'), strtotime($item['reservation_time']));
                return $date . ' ' . $time;
                
            case 'table_info':
                $table_name = isset($item['table_name']) ? $item['table_name'] : __('Desconhecida', 'zuzunely-restaurant');
                $saloon_name = isset($item['saloon_name']) ? $item['saloon_name'] : __('Desconhecido', 'zuzunely-restaurant');
                return $table_name . ' / ' . $saloon_name;
                
            case 'status':
                $status_labels = Zuzunely_Reservations_DB::get_status_list();
                $status = isset($status_labels[$item['status']]) ? $status_labels[$item['status']] : $item['status'];
                
                $class = '';
                switch ($item['status']) {
                    case 'pending':
                        $class = 'pending';
                        break;
                    case 'confirmed':
                        $class = 'confirmed';
                        break;
                    case 'completed':
                        $class = 'completed';
                        break;
                    case 'cancelled':
                        $class = 'cancelled';
                        break;
                    case 'no_show':
                        $class = 'no-show';
                        break;
                }
                
                return '<span class="reservation-status ' . esc_attr($class) . '">' . esc_html($status) . '</span>';
                
            case 'contact':
                $phone = !empty($item['customer_phone']) ? '<span class="dashicons dashicons-phone"></span> ' . esc_html($item['customer_phone']) . '<br>' : '';
                $email = !empty($item['customer_email']) ? '<span class="dashicons dashicons-email"></span> ' . esc_html($item['customer_email']) : '';
                return $phone . $email;
                
            default:
                return isset($item[$column_name]) ? $item[$column_name] : '';
        }
    }
    
    /**
     * Renderizar coluna de checkbox
     */
    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="reservation[]" value="%s" />',
            $item['id']
        );
    }
    
    /**
     * Renderizar coluna do nome do cliente
     */
    public function column_customer_name($item) {
        // Criar ações
        $actions = array(
            'edit' => sprintf('<a href="%s">%s</a>', 
                esc_url(admin_url('admin.php?page=zuzunely-reservations&action=edit&id=' . $item['id'])),
                __('Editar', 'zuzunely-restaurant')
            ),
            'delete' => sprintf('<a href="%s" onclick="return confirm(\'%s\');">%s</a>', 
                esc_url(wp_nonce_url(admin_url('admin.php?page=zuzunely-reservations&action=delete&id=' . $item['id']), 'delete_reservation_' . $item['id'])),
                __('Tem certeza que deseja excluir esta reserva?', 'zuzunely-restaurant'),
                __('Excluir', 'zuzunely-restaurant')
            ),
        );
        
        // Adicionar ações específicas de status
        if ($item['status'] === 'pending') {
            $actions['confirm'] = sprintf('<a href="%s">%s</a>', 
                esc_url(wp_nonce_url(admin_url('admin.php?page=zuzunely-reservations&action=confirm&id=' . $item['id']), 'confirm_reservation_' . $item['id'])),
                __('Confirmar', 'zuzunely-restaurant')
            );
        }
        
        if ($item['status'] === 'pending' || $item['status'] === 'confirmed') {
            $actions['cancel'] = sprintf('<a href="%s">%s</a>', 
                esc_url(wp_nonce_url(admin_url('admin.php?page=zuzunely-reservations&action=cancel&id=' . $item['id']), 'cancel_reservation_' . $item['id'])),
                __('Cancelar', 'zuzunely-restaurant')
            );
        }
        
        // Retornar nome com ações
        return sprintf(
            '<strong><a href="%s">%s</a></strong> %s',
            esc_url(admin_url('admin.php?page=zuzunely-reservations&action=edit&id=' . $item['id'])),
            esc_html($item['customer_name']),
            $this->row_actions($actions)
        );
    }
    
    /**
     * Preparar itens para exibição
     */
    public function prepare_items() {
        // Processar ações em massa
        $this->process_bulk_action();
        
        // Definir número de itens por página
        $per_page = 20;
        
        // Definir colunas
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();
        
        // Configurar cabeçalho
        $this->_column_headers = array($columns, $hidden, $sortable);
        
        // Obter argumentos para consulta
        $orderby = (isset($_REQUEST['orderby'])) ? sanitize_text_field($_REQUEST['orderby']) : 'reservation_date';
        $order = (isset($_REQUEST['order'])) ? sanitize_text_field($_REQUEST['order']) : 'DESC';
        $paged = (isset($_REQUEST['paged'])) ? max(0, intval($_REQUEST['paged']) - 1) : 0;
        $search = (isset($_REQUEST['s'])) ? sanitize_text_field($_REQUEST['s']) : '';
        
        // Filtros adicionais
        $status_filter = (isset($_REQUEST['status'])) ? sanitize_text_field($_REQUEST['status']) : '';
        $date_filter = (isset($_REQUEST['date'])) ? sanitize_text_field($_REQUEST['date']) : '';
        
        // Configurar argumentos de consulta
        $args = array(
            'number' => $per_page,
            'offset' => $paged * $per_page,
            'orderby' => $orderby,
            'order' => $order,
            'include_inactive' => false,
        );
        
        // Adicionar filtros se preenchidos
        if (!empty($status_filter)) {
            $args['status'] = $status_filter;
        }
        
        if (!empty($date_filter)) {
            $args['date'] = $date_filter;
        }
        
        // Adicionar busca se preenchida
        if (!empty($search)) {
            global $wpdb;
            $search_term = '%' . $wpdb->esc_like($search) . '%';
            $db = new Zuzunely_Reservations_DB();
            $table_name = $db->get_reservations_table();
            
            // Construir condição WHERE para a busca
            $search_where = "
                AND (
                    customer_name LIKE '%s' OR 
                    customer_phone LIKE '%s' OR 
                    customer_email LIKE '%s'
                )
            ";
            
            // Preparar consulta SQL personalizada
            $sql = $wpdb->prepare(
                "SELECT r.*, t.name as table_name, s.name as saloon_name, s.id as saloon_id
                FROM $table_name r
                LEFT JOIN {$wpdb->prefix}zuzunely_tables t ON r.table_id = t.id
                LEFT JOIN {$wpdb->prefix}zuzunely_saloons s ON t.saloon_id = s.id
                WHERE r.is_active = 1 " . $search_where . "
                ORDER BY r.$orderby $order
                LIMIT %d, %d",
                $search_term, $search_term, $search_term,
                $paged * $per_page, $per_page
            );
            
            $items = $wpdb->get_results($sql, ARRAY_A);
            
            // Contar total para paginação
            $total_sql = $wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name 
                WHERE is_active = 1 " . $search_where,
                $search_term, $search_term, $search_term
            );
            
            $total_items = $wpdb->get_var($total_sql);
        } else {
            // Obter dados usando a classe DB
            $db = new Zuzunely_Reservations_DB();
            $items = $db->get_reservations($args);
            $total_items = $db->count_reservations($args);
        }
        
        // Definir itens
        $this->items = $items;
        
        // Configurar paginação
        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page' => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ));
    }
    
    /**
     * Mensagem para quando não há itens
     */
    public function no_items() {
        echo esc_html__('Nenhuma reserva encontrada.', 'zuzunely-restaurant');
    }
    
    /**
     * Exibir filtros adicionais
     */
    public function extra_tablenav($which) {
        if ($which == 'top') {
            $status = isset($_REQUEST['status']) ? sanitize_text_field($_REQUEST['status']) : '';
            $date = isset($_REQUEST['date']) ? sanitize_text_field($_REQUEST['date']) : '';
            
            // Obter lista de status
            $status_list = Zuzunely_Reservations_DB::get_status_list();
            
            ?>
            <div class="alignleft actions">
                <select name="status">
                    <option value=""><?php esc_html_e('Todos os status', 'zuzunely-restaurant'); ?></option>
                    <?php foreach ($status_list as $key => $label): ?>
                        <option value="<?php echo esc_attr($key); ?>" <?php selected($status, $key); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <input type="date" name="date" placeholder="<?php esc_attr_e('Filtrar por data', 'zuzunely-restaurant'); ?>" value="<?php echo esc_attr($date); ?>">
                
                <?php submit_button(__('Filtrar', 'zuzunely-restaurant'), 'button', 'filter_action', false); ?>
            </div>
            <?php
        }
    }
}