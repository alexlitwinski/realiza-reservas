<?php
/**
 * Classe para tabela de listagem de bloqueios
 */

if (!defined('ABSPATH')) {
    exit;
}

// Verificar se a classe base existe
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class Zuzunely_Blocks_List_Table extends WP_List_Table {
    
    // Filtros
    private $block_type_filter = '';
    private $reference_id_filter = 0;
    
    // Construtor
    public function __construct() {
        parent::__construct(array(
            'singular' => 'block',
            'plural' => 'blocks',
            'ajax' => false
        ));
    }
    
    // Colunas da tabela
    public function get_columns() {
        $columns = array(
            'cb' => '<input type="checkbox" />',
            'block_type' => __('Tipo', 'zuzunely-restaurant'),
            'reference' => __('Item Bloqueado', 'zuzunely-restaurant'),
            'period' => __('Período', 'zuzunely-restaurant'),
            'time' => __('Horário', 'zuzunely-restaurant'),
            'reason' => __('Motivo', 'zuzunely-restaurant'),
            'is_active' => __('Status', 'zuzunely-restaurant'),
            'created_at' => __('Data de Criação', 'zuzunely-restaurant'),
        );
        
        return $columns;
    }
    
    // Colunas que podem ser ordenadas
    public function get_sortable_columns() {
        $sortable_columns = array(
            'block_type' => array('block_type', true),
            'period' => array('start_date', false),
            'is_active' => array('is_active', true),
            'created_at' => array('created_at', true),
        );
        
        return $sortable_columns;
    }
    
    // Preparar itens para exibição
    public function prepare_items($block_type_filter = '', $reference_id_filter = 0) {
        // Página atual
        $current_page = $this->get_pagenum();
        
        // Salvar filtros
        $this->block_type_filter = $block_type_filter;
        $this->reference_id_filter = $reference_id_filter;
        
        // Número de itens por página
        $per_page = 10;
        
        // Inicializar DB
        $db = new Zuzunely_Blocks_DB();
        
        // Argumentos para busca
        $args = array(
            'number' => $per_page,
            'offset' => ($current_page - 1) * $per_page,
            'include_inactive' => true,
        );
        
        // Adicionar filtro de tipo se estiver definido
        if (!empty($block_type_filter)) {
            $args['block_type'] = $block_type_filter;
        }
        
        // Adicionar filtro de referência se estiver definido
        if ($reference_id_filter > 0) {
            $args['reference_id'] = $reference_id_filter;
        }
        
        // Ordenação
        $orderby = isset($_REQUEST['orderby']) ? sanitize_text_field($_REQUEST['orderby']) : 'id';
        $order = isset($_REQUEST['order']) ? sanitize_text_field($_REQUEST['order']) : 'DESC';
        
        $args['orderby'] = $orderby;
        $args['order'] = $order;
        
        // Total de itens com filtros aplicados
        $total_items = $db->count_blocks($args);
        
        // Configurar página
        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page' => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ));
        
        // Buscar bloqueios
        $this->items = $db->get_blocks($args);
        
        // Definir colunas escondidas
        $this->_column_headers = array(
            $this->get_columns(),
            array(),
            $this->get_sortable_columns(),
            'block_type'
        );
    }
    
    // Renderizar checkbox de cada item
    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="block_id[]" value="%s" />',
            $item['id']
        );
    }
    
    // Renderizar coluna de tipo (com ações)
    public function column_block_type($item) {
        // URLs para ações
        $edit_url = admin_url(sprintf('admin.php?page=zuzunely-blocks&action=edit&id=%s', $item['id']));
        $delete_url = admin_url(sprintf('admin.php?page=zuzunely-blocks&action=delete&id=%s', $item['id']));
        
        // Ações disponíveis
        $actions = array(
            'edit' => sprintf('<a href="%s">%s</a>', $edit_url, __('Editar', 'zuzunely-restaurant')),
            'delete' => sprintf(
                '<a href="#" class="zuzunely-delete-block" data-id="%s" data-nonce="%s">%s</a>',
                $item['id'],
                wp_create_nonce('zuzunely_delete_block'),
                __('Excluir', 'zuzunely-restaurant')
            ),
        );
        
        // Texto do tipo de bloqueio
        $block_type_text = '';
        switch ($item['block_type']) {
            case 'restaurant':
                $block_type_text = __('Restaurante', 'zuzunely-restaurant');
                break;
            case 'saloon':
                $block_type_text = __('Salão', 'zuzunely-restaurant');
                break;
            case 'table':
                $block_type_text = __('Mesa', 'zuzunely-restaurant');
                break;
            default:
                $block_type_text = $item['block_type'];
                break;
        }
        
        // Retornar tipo com ações
        return sprintf(
            '<strong><a href="%s">%s</a></strong> %s',
            $edit_url,
            $block_type_text,
            $this->row_actions($actions)
        );
    }
    
    // Renderizar coluna de referência
    public function column_reference($item) {
        if ($item['block_type'] === 'restaurant') {
            return '<span class="block-reference-badge block-reference-restaurant">' . __('Restaurante Inteiro', 'zuzunely-restaurant') . '</span>';
        } else if (isset($item['reference_name']) && !empty($item['reference_name'])) {
            $url = admin_url('admin.php?page=zuzunely-blocks&block_type=' . $item['block_type'] . '&reference_id=' . $item['reference_id']);
            
            if ($item['block_type'] === 'saloon') {
                return sprintf('<a href="%s" class="block-reference-badge block-reference-saloon">%s</a>', $url, $item['reference_name']);
            } else if ($item['block_type'] === 'table') {
                return sprintf('<a href="%s" class="block-reference-badge block-reference-table">%s</a>', $url, $item['reference_name']);
            }
        }
        
        return '—';
    }
    
    // Renderizar coluna de período
    public function column_period($item) {
        $start_date = date_i18n(get_option('date_format'), strtotime($item['start_date']));
        $end_date = date_i18n(get_option('date_format'), strtotime($item['end_date']));
        
        if ($start_date === $end_date) {
            return sprintf('<span class="block-period">%s</span>', $start_date);
        } else {
            return sprintf('<span class="block-period">%s - %s</span>', $start_date, $end_date);
        }
    }
    
    // Renderizar coluna de horário
    public function column_time($item) {
        $start_time = date_i18n(get_option('time_format'), strtotime($item['start_time']));
        $end_time = date_i18n(get_option('time_format'), strtotime($item['end_time']));
        
        return sprintf('<span class="block-time">%s - %s</span>', $start_time, $end_time);
    }
    
    // Renderizar coluna de motivo
    public function column_reason($item) {
        $excerpt = wp_trim_words($item['reason'], 10, '...');
        return $excerpt;
    }
    
    // Renderizar coluna de status
    public function column_is_active($item) {
        if ($item['is_active']) {
            return '<span class="zuzunely-status zuzunely-status-active">' . __('Ativo', 'zuzunely-restaurant') . '</span>';
        } else {
            return '<span class="zuzunely-status zuzunely-status-inactive">' . __('Inativo', 'zuzunely-restaurant') . '</span>';
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
        if ($this->block_type_filter) {
            if ($this->reference_id_filter > 0) {
                _e('Nenhum bloqueio encontrado para este item.', 'zuzunely-restaurant');
            } else {
                _e('Nenhum bloqueio encontrado para este tipo.', 'zuzunely-restaurant');
            }
        } else {
            _e('Nenhum bloqueio encontrado.', 'zuzunely-restaurant');
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
        if ('delete' === $action && isset($_REQUEST['block_id'])) {
            // Verificar nonce
            check_admin_referer('bulk-' . $this->_args['plural']);
            
            // Obter IDs selecionados
            $block_ids = isset($_REQUEST['block_id']) ? array_map('absint', (array) $_REQUEST['block_id']) : array();
            
            if (!empty($block_ids)) {
                // Processar exclusão em massa
                $db = new Zuzunely_Blocks_DB();
                $count = 0;
                
                foreach ($block_ids as $block_id) {
                    if ($db->delete_block($block_id)) {
                        $count++;
                    }
                }
                
                // Redirecionar com mensagem de sucesso
                wp_redirect(add_query_arg(
                    array(
                        'page' => 'zuzunely-blocks',
                        'deleted' => $count,
                    ),
                    admin_url('admin.php')
                ));
                exit;
            }
        }
    }
}