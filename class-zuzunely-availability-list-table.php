<?php
/**
 * Classe para tabela de listagem de disponibilidades
 */

if (!defined('ABSPATH')) {
    exit;
}

// Verificar se a classe base existe
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class Zuzunely_Availability_List_Table extends WP_List_Table {
    
    // Filtros
    private $table_filter = 0;
    private $weekday_filter = -1;
    
    // Construtor
    public function __construct() {
        parent::__construct(array(
            'singular' => 'availability',
            'plural' => 'availabilities',
            'ajax' => false
        ));
    }
    
    // Colunas da tabela
    public function get_columns() {
        $columns = array(
            'cb' => '<input type="checkbox" />',
            'table' => __('Mesa', 'zuzunely-restaurant'),
            'weekday' => __('Dia da Semana', 'zuzunely-restaurant'),
            'time_period' => __('Horário', 'zuzunely-restaurant'),
            'saloon' => __('Salão', 'zuzunely-restaurant'),
            'is_active' => __('Status', 'zuzunely-restaurant'),
            'created_at' => __('Data de Criação', 'zuzunely-restaurant'),
        );
        
        return $columns;
    }
    
    // Colunas que podem ser ordenadas
    public function get_sortable_columns() {
        $sortable_columns = array(
            'table' => array('table_id', true),
            'weekday' => array('weekday', false),
            'time_period' => array('start_time', false),
            'saloon' => array('saloon_id', false),
            'is_active' => array('is_active', true),
            'created_at' => array('created_at', true),
        );
        
        return $sortable_columns;
    }
    
    // Preparar itens para exibição
    public function prepare_items($table_filter = 0, $weekday_filter = -1) {
        // Página atual
        $current_page = $this->get_pagenum();
        
        // Salvar filtros
        $this->table_filter = $table_filter;
        $this->weekday_filter = $weekday_filter;
        
        // Número de itens por página
        $per_page = 10;
        
        // Inicializar DB
        $db = new Zuzunely_Availability_DB();
        
        // Argumentos para busca
        $args = array(
            'number' => $per_page,
            'offset' => ($current_page - 1) * $per_page,
            'include_inactive' => true,
        );
        
        // Adicionar filtro de mesa se estiver definido
        if ($table_filter > 0) {
            $args['table_id'] = $table_filter;
        }
        
        // Adicionar filtro de dia da semana se estiver definido
        if ($weekday_filter >= 0 && $weekday_filter <= 6) {
            $args['weekday'] = $weekday_filter;
        }
        
        // Ordenação
        $orderby = isset($_REQUEST['orderby']) ? sanitize_text_field($_REQUEST['orderby']) : 'id';
        $order = isset($_REQUEST['order']) ? sanitize_text_field($_REQUEST['order']) : 'DESC';
        
        $args['orderby'] = $orderby;
        $args['order'] = $order;
        
        // Log para depuração
        error_log('Buscando disponibilidades com args: ' . print_r($args, true));
        
        // Total de itens com filtros aplicados
        $total_items = $db->count_availabilities($args);
        error_log('Total de disponibilidades encontradas: ' . $total_items);
        
        // Configurar página
        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page' => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ));
        
        // Buscar disponibilidades
        $this->items = $db->get_availabilities($args);
        
        // Log dos itens recuperados
        error_log('Itens recuperados para a tabela: ' . print_r(array_column($this->items, 'id'), true));
    }
    
    // Renderizar checkbox de cada item
    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="availability_id[]" value="%s" class="availability-cb" />',
            $item['id']
        );
    }
    
    // Renderizar coluna de mesa (com ações)
    public function column_table($item) {
        // URLs para ações
        $edit_url = admin_url(sprintf('admin.php?page=zuzunely-availability&action=edit&id=%s', $item['id']));
        $delete_url = admin_url(sprintf('admin.php?page=zuzunely-availability&action=delete&id=%s', $item['id']));
        
        // Ações disponíveis
        $actions = array(
            'edit' => sprintf('<a href="%s">%s</a>', $edit_url, __('Editar', 'zuzunely-restaurant')),
            'delete' => sprintf(
                '<a href="#" class="zuzunely-delete-availability" data-id="%s" data-nonce="%s">%s</a>',
                $item['id'],
                wp_create_nonce('zuzunely_delete_availability'),
                __('Excluir', 'zuzunely-restaurant')
            ),
        );
        
        // Nome da mesa
        $table_name = isset($item['table_name']) ? $item['table_name'] : __('Mesa Desconhecida', 'zuzunely-restaurant');
        
        // Retornar nome com ações
        return sprintf(
            '<strong><a href="%s">%s</a></strong> %s',
            $edit_url,
            $table_name,
            $this->row_actions($actions)
        );
    }
    
    // Renderizar coluna de dia da semana
    public function column_weekday($item) {
        $weekdays = Zuzunely_Availability_DB::get_weekdays();
        $weekday = isset($weekdays[$item['weekday']]) ? $weekdays[$item['weekday']] : $item['weekday'];
        
        $url = admin_url('admin.php?page=zuzunely-availability&weekday=' . $item['weekday']);
        return sprintf('<a href="%s" class="weekday-badge">%s</a>', $url, $weekday);
    }
    
    // Renderizar coluna de salão
    public function column_saloon($item) {
        if (isset($item['saloon_name']) && !empty($item['saloon_name'])) {
            $url = admin_url('admin.php?page=zuzunely-availability&saloon_id=' . $item['saloon_id']);
            return sprintf('<a href="%s">%s</a>', $url, $item['saloon_name']);
        }
        return '—';
    }
    
    // Renderizar coluna de período de tempo
    public function column_time_period($item) {
        $start_time = date_i18n(get_option('time_format'), strtotime($item['start_time']));
        $end_time = date_i18n(get_option('time_format'), strtotime($item['end_time']));
        
        return sprintf('<span class="time-period">%s - %s</span>', $start_time, $end_time);
    }
    
    // Renderizar coluna de status
    public function column_is_active($item) {
        if ($item['is_active']) {
            return '<span class="zuzunely-status zuzunely-status-active">' . __('Ativa', 'zuzunely-restaurant') . '</span>';
        } else {
            return '<span class="zuzunely-status zuzunely-status-inactive">' . __('Inativa', 'zuzunely-restaurant') . '</span>';
        }
    }
    
    // Renderizar coluna de data de criação
    public function column_created_at($item) {
        $date = new DateTime($item['created_at']);
        return $date->format(get_option('date_format') . ' ' . get_option('time_format'));
    }
    
    // Renderizar valor padrão para outras colunas
    public function column_default($item, $column_name) {
        return isset($item[$column_name]) ? $item[$column_name] : '—';
    }
    
    // Configurar ações em massa
    public function get_bulk_actions() {
        $actions = array(
            'delete' => __('Excluir', 'zuzunely-restaurant'),
        );
        
        return $actions;
    }
    
    // Mostrar mensagem quando não houver itens
    public function no_items() {
        if ($this->table_filter > 0) {
            _e('Nenhuma disponibilidade encontrada para esta mesa.', 'zuzunely-restaurant');
        } else if ($this->weekday_filter >= 0) {
            $weekdays = Zuzunely_Availability_DB::get_weekdays();
            $weekday = isset($weekdays[$this->weekday_filter]) ? $weekdays[$this->weekday_filter] : $this->weekday_filter;
            
            echo sprintf(
                __('Nenhuma disponibilidade encontrada para %s.', 'zuzunely-restaurant'),
                $weekday
            );
        } else {
            _e('Nenhuma disponibilidade encontrada.', 'zuzunely-restaurant');
        }
    }
    
    // Exibir filtros extras no topo da tabela
    protected function extra_tablenav($which) {
        if ($which === 'top') {
            // Filtros adicionais podem ser adicionados aqui
        }
    }
    
    // Sobrescrever o método para renderizar a tabela completa
    public function display() {
        $singular = $this->_args['singular'];
        
        // Este é o método correto para renderizar a tabela
        $this->display_tablenav('top');
        ?>
        <table class="wp-list-table <?php echo implode(' ', $this->get_table_classes()); ?>">
            <thead>
                <tr>
                    <?php $this->print_column_headers(); ?>
                </tr>
            </thead>

            <tbody id="the-list"<?php if ($singular) echo " data-wp-lists='list:$singular'"; ?>>
                <?php 
                if (!empty($this->items)) {
                    $this->display_rows_or_placeholder();
                } else {
                    echo '<tr class="no-items"><td class="colspanchange" colspan="' . $this->get_column_count() . '">' . $this->no_items() . '</td></tr>';
                }
                ?>
            </tbody>

            <tfoot>
                <tr>
                    <?php $this->print_column_headers(false); ?>
                </tr>
            </tfoot>
        </table>
        <?php
        $this->display_tablenav('bottom');
    }
    
    // Método para exibir cada linha
    public function display_rows() {
        if (empty($this->items)) {
            // Log para depuração
            error_log('Nenhum item para exibir na tabela de disponibilidades');
            return;
        }
        
        // Log para depuração
        error_log('Exibindo ' . count($this->items) . ' itens na tabela de disponibilidades');
        
        foreach ($this->items as $item) {
            echo $this->single_row($item);
        }
    }
    
    // Método para renderizar uma única linha
    public function single_row($item) {
        $row_class = 'zuzunely-availability-row';
        if (!$item['is_active']) {
            $row_class .= ' inactive';
        }
        
        $output = '<tr id="availability-' . $item['id'] . '" class="' . $row_class . '">';
        
        $columns = $this->get_columns();
        foreach ($columns as $column_name => $column_display_name) {
            $class = "class='$column_name column-$column_name'";
            
            $attributes = "$class";
            
            $output .= "<td $attributes>";
            
            switch ($column_name) {
                case 'cb':
                    $output .= $this->column_cb($item);
                    break;
                case 'table':
                    $output .= $this->column_table($item);
                    break;
                case 'weekday':
                    $output .= $this->column_weekday($item);
                    break;
                case 'time_period':
                    $output .= $this->column_time_period($item);
                    break;
                case 'saloon':
                    $output .= $this->column_saloon($item);
                    break;
                case 'is_active':
                    $output .= $this->column_is_active($item);
                    break;
                case 'created_at':
                    $output .= $this->column_created_at($item);
                    break;
                default:
                    $output .= $this->column_default($item, $column_name);
            }
            
            $output .= "</td>";
        }
        
        $output .= "</tr>";
        
        return $output;
    }
    
    // Processar ações em massa
    public function process_bulk_action() {
        // Verificar nonce
        if (!isset($_POST['zuzunely_bulk_nonce']) || !wp_verify_nonce($_POST['zuzunely_bulk_nonce'], 'zuzunely_bulk_actions')) {
            return;
        }
        
        $action = $this->current_action();
        
        // Processar exclusão em massa
        if ('delete' === $action) {
            $availability_ids = isset($_POST['availability_id']) ? array_map('intval', $_POST['availability_id']) : array();
            
            if (!empty($availability_ids)) {
                $db = new Zuzunely_Availability_DB();
                $deleted = 0;
                
                foreach ($availability_ids as $id) {
                    if ($db->delete_availability($id)) {
                        $deleted++;
                    }
                }
                
                // Redirecionar com mensagem
                wp_redirect(add_query_arg(
                    array(
                        'page' => 'zuzunely-availability',
                        'deleted' => $deleted
                    ),
                    admin_url('admin.php')
                ));
                exit;
            }
        }
    }
}