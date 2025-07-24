<?php
/**
 * Classe para tabela de listagem de mesas
 */

if (!defined('ABSPATH')) {
    exit;
}

// Verificar se a classe base existe
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class Zuzunely_Tables_List_Table extends WP_List_Table {
    
    // Filtro de salão
    private $saloon_filter = 0;
    
    // Construtor
    public function __construct() {
        parent::__construct(array(
            'singular' => 'table',
            'plural' => 'tables',
            'ajax' => false
        ));
    }
    
    // Colunas da tabela
    public function get_columns() {
        $columns = array(
            'cb' => '<input type="checkbox" />',
            'name' => __('Nome', 'zuzunely-restaurant'),
            'saloon' => __('Salão', 'zuzunely-restaurant'),
            'capacity' => __('Capacidade', 'zuzunely-restaurant'),
            'description' => __('Descrição', 'zuzunely-restaurant'),
            'is_active' => __('Status', 'zuzunely-restaurant'),
            'created_at' => __('Data de Criação', 'zuzunely-restaurant'),
        );
        
        return $columns;
    }
    
    // Colunas que podem ser ordenadas
    public function get_sortable_columns() {
        $sortable_columns = array(
            'name' => array('name', true),
            'saloon' => array('saloon_id', false),
            'capacity' => array('capacity', false),
            'is_active' => array('is_active', true),
            'created_at' => array('created_at', true),
        );
        
        return $sortable_columns;
    }
    
    // Preparar itens para exibição
    public function prepare_items($saloon_filter = 0) {
        // Página atual
        $current_page = $this->get_pagenum();
        
        // Salvar filtro de salão
        $this->saloon_filter = $saloon_filter;
        
        // Número de itens por página
        $per_page = 10;
        
        // Inicializar DB
        $db = new Zuzunely_Tables_DB();
        
        // Argumentos para busca
        $args = array(
            'number' => $per_page,
            'offset' => ($current_page - 1) * $per_page,
            'include_inactive' => true,
        );
        
        // Adicionar filtro de salão se estiver definido
        if ($saloon_filter > 0) {
            $args['saloon_id'] = $saloon_filter;
        }
        
        // Ordenação
        $orderby = isset($_REQUEST['orderby']) ? sanitize_text_field($_REQUEST['orderby']) : 'id';
        $order = isset($_REQUEST['order']) ? sanitize_text_field($_REQUEST['order']) : 'DESC';
        
        $args['orderby'] = $orderby;
        $args['order'] = $order;
        
        // Total de itens com filtros aplicados
        $total_items = $db->count_tables($args);
        
        // Configurar página
        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page' => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ));
        
        // Buscar mesas
        $this->items = $db->get_tables($args);
        
        // Definir colunas escondidas
        $this->_column_headers = array(
            $this->get_columns(),
            array(),
            $this->get_sortable_columns(),
            'name'
        );
    }
    
    // Renderizar checkbox de cada item
    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="table_id[]" value="%s" />',
            $item['id']
        );
    }
    
    // Renderizar coluna de nome (com ações)
    public function column_name($item) {
        // URLs para ações
        $edit_url = admin_url(sprintf('admin.php?page=zuzunely-tables&action=edit&id=%s', $item['id']));
        $delete_url = admin_url(sprintf('admin.php?page=zuzunely-tables&action=delete&id=%s', $item['id']));
        
        // Ações disponíveis
        $actions = array(
            'edit' => sprintf('<a href="%s">%s</a>', $edit_url, __('Editar', 'zuzunely-restaurant')),
            'delete' => sprintf(
                '<a href="#" class="zuzunely-delete-table" data-id="%s" data-nonce="%s">%s</a>',
                $item['id'],
                wp_create_nonce('zuzunely_delete_table'),
                __('Excluir', 'zuzunely-restaurant')
            ),
        );
        
        // Retornar nome com ações
        return sprintf(
            '<strong><a href="%s">%s</a></strong> %s',
            $edit_url,
            $item['name'],
            $this->row_actions($actions)
        );
    }
    
    // Renderizar coluna de salão
    public function column_saloon($item) {
        if (isset($item['saloon_name'])) {
            $url = admin_url('admin.php?page=zuzunely-tables&saloon=' . $item['saloon_id']);
            return sprintf('<a href="%s">%s</a>', $url, $item['saloon_name']);
        }
        return '—';
    }
    
    // Renderizar coluna de capacidade
    public function column_capacity($item) {
        $capacity = intval($item['capacity']);
        
        if ($capacity == 1) {
            return sprintf('<span class="capacity-badge">%d %s</span>', $capacity, __('pessoa', 'zuzunely-restaurant'));
        } else {
            return sprintf('<span class="capacity-badge">%d %s</span>', $capacity, __('pessoas', 'zuzunely-restaurant'));
        }
    }
    
    // Renderizar coluna de descrição
    public function column_description($item) {
        $excerpt = wp_trim_words($item['description'], 10, '...');
        return $excerpt;
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
        if ($this->saloon_filter > 0) {
            _e('Nenhuma mesa encontrada para este salão.', 'zuzunely-restaurant');
        } else {
            _e('Nenhuma mesa encontrada.', 'zuzunely-restaurant');
        }
    }
    
    // Exibir filtros extras no topo da tabela
    protected function extra_tablenav($which) {
        if ($which === 'top') {
            // Filtros adicionais podem ser adicionados aqui
        }
    }
    
    /**
     * Processa as ações em massa
     */
    public function process_bulk_action() {
        // Obter ação atual
        $action = $this->current_action();
        
        // Verificar se é uma exclusão
        if ('delete' === $action && isset($_REQUEST['table_id'])) {
            // Verificar nonce
            check_admin_referer('bulk-' . $this->_args['plural']);
            
            // Obter IDs selecionados
            $table_ids = isset($_REQUEST['table_id']) ? array_map('absint', (array) $_REQUEST['table_id']) : array();
            
            if (!empty($table_ids)) {
                // Processar exclusão em massa
                $db = new Zuzunely_Tables_DB();
                $count = 0;
                
                foreach ($table_ids as $table_id) {
                    if ($db->delete_table($table_id)) {
                        $count++;
                    }
                }
                
                // Redirecionar com mensagem de sucesso
                wp_redirect(add_query_arg(
                    array(
                        'page' => 'zuzunely-tables',
                        'deleted' => $count,
                    ),
                    admin_url('admin.php')
                ));
                exit;
            }
        }
    }
}