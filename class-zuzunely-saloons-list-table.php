<?php
/**
 * Classe para tabela de listagem de salões
 */

if (!defined('ABSPATH')) {
    exit;
}

// Verificar se a classe base existe
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class Zuzunely_Saloons_List_Table extends WP_List_Table {
    
    // Construtor
    public function __construct() {
        parent::__construct(array(
            'singular' => 'saloon',
            'plural' => 'saloons',
            'ajax' => false
        ));
    }
    
    // Colunas da tabela
    public function get_columns() {
        $columns = array(
            'cb' => '<input type="checkbox" />',
            'name' => __('Nome', 'zuzunely-restaurant'),
            'thumbnail' => __('Imagem', 'zuzunely-restaurant'),
            'description' => __('Descrição', 'zuzunely-restaurant'),
            'location' => __('Localização', 'zuzunely-restaurant'),
            'is_active' => __('Status', 'zuzunely-restaurant'),
            'created_at' => __('Data de Criação', 'zuzunely-restaurant'),
        );
        
        return $columns;
    }
    
    // Colunas que podem ser ordenadas
    public function get_sortable_columns() {
        $sortable_columns = array(
            'name' => array('name', true),
            'location' => array('is_internal', true),
            'is_active' => array('is_active', true),
            'created_at' => array('created_at', true),
        );
        
        return $sortable_columns;
    }
    
    // Preparar itens para exibição
    public function prepare_items() {
        // Página atual
        $current_page = $this->get_pagenum();
        
        // Número de itens por página
        $per_page = 10;
        
        // Inicializar DB
        $db = new Zuzunely_Saloons_DB();
        
        // Total de itens - sempre incluir inativos na contagem
        $total_items = $db->count_saloons(true);
        
        // Configurar página
        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page' => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ));
        
        // Ordenação
        $orderby = isset($_REQUEST['orderby']) ? sanitize_text_field($_REQUEST['orderby']) : 'id';
        $order = isset($_REQUEST['order']) ? sanitize_text_field($_REQUEST['order']) : 'DESC';
        
        // Buscar salões - sempre incluir inativos na listagem admin
        $this->items = $db->get_saloons(array(
            'number' => $per_page,
            'offset' => ($current_page - 1) * $per_page,
            'orderby' => $orderby,
            'order' => $order,
            'include_inactive' => true
        ));
        
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
            '<input type="checkbox" name="saloon_id[]" value="%s" />',
            $item['id']
        );
    }
    
    // Renderizar coluna de nome (com ações)
    public function column_name($item) {
        // URLs para ações
        $edit_url = admin_url(sprintf('admin.php?page=zuzunely-saloons&action=edit&id=%s', $item['id']));
        $delete_url = admin_url(sprintf('admin.php?page=zuzunely-saloons&action=delete&id=%s', $item['id']));
        
        // Ações disponíveis
        $actions = array(
            'edit' => sprintf('<a href="%s">%s</a>', $edit_url, __('Editar', 'zuzunely-restaurant')),
            'delete' => sprintf(
                '<a href="#" class="zuzunely-delete-saloon" data-id="%s" data-nonce="%s">%s</a>',
                $item['id'],
                wp_create_nonce('zuzunely_delete_saloon'),
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
    
    // Renderizar coluna de thumbnail
    public function column_thumbnail($item) {
        if (!empty($item['images']) && is_array($item['images']) && !empty($item['images'][0])) {
            return wp_get_attachment_image($item['images'][0], 'thumbnail');
        }
        
        return '—';
    }
    
    // Renderizar coluna de descrição
    public function column_description($item) {
        $excerpt = wp_trim_words($item['description'], 10, '...');
        return $excerpt;
    }
    
    // Renderizar coluna de localização
    public function column_location($item) {
        if (isset($item['is_internal'])) {
            if ($item['is_internal']) {
                return '<span class="zuzunely-location zuzunely-location-internal">' . __('Área Interna', 'zuzunely-restaurant') . '</span>';
            } else {
                return '<span class="zuzunely-location zuzunely-location-external">' . __('Área Externa', 'zuzunely-restaurant') . '</span>';
            }
        }
        return '—';
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
        _e('Nenhum salão encontrado.', 'zuzunely-restaurant');
    }
    
    /**
     * Processa as ações em massa
     */
    public function process_bulk_action() {
        // Obter ação atual
        $action = $this->current_action();
        
        // Verificar se é uma exclusão
        if ('delete' === $action && isset($_REQUEST['saloon_id'])) {
            // Verificar nonce
            check_admin_referer('bulk-' . $this->_args['plural']);
            
            // Obter IDs selecionados
            $saloon_ids = isset($_REQUEST['saloon_id']) ? array_map('absint', (array) $_REQUEST['saloon_id']) : array();
            
            if (!empty($saloon_ids)) {
                // Processar exclusão em massa
                $db = new Zuzunely_Saloons_DB();
                $count = 0;
                
                foreach ($saloon_ids as $saloon_id) {
                    if ($db->delete_saloon($saloon_id)) {
                        $count++;
                    }
                }
                
                // Redirecionar com mensagem de sucesso
                wp_redirect(add_query_arg(
                    array(
                        'page' => 'zuzunely-saloons',
                        'deleted' => $count,
                    ),
                    admin_url('admin.php')
                ));
                exit;
            }
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
                    echo '<tr class="no-items"><td class="colspanchange" colspan="' . $this->get_column_count() . '">' . __('Nenhum salão encontrado.', 'zuzunely-restaurant') . '</td></tr>';
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
            return;
        }
        
        foreach ($this->items as $item) {
            echo $this->single_row($item);
        }
    }
    
    // Método para renderizar uma única linha
    public function single_row($item) {
        $row_class = 'zuzunely-saloon-row';
        if (!$item['is_active']) {
            $row_class .= ' inactive';
        }
        
        $output = '<tr id="saloon-' . $item['id'] . '" class="' . $row_class . '">';
        
        $columns = $this->get_columns();
        foreach ($columns as $column_name => $column_display_name) {
            $class = "class='$column_name column-$column_name'";
            
            $attributes = "$class";
            
            $output .= "<td $attributes>";
            
            switch ($column_name) {
                case 'cb':
                    $output .= $this->column_cb($item);
                    break;
                case 'name':
                    $output .= $this->column_name($item);
                    break;
                case 'thumbnail':
                    $output .= $this->column_thumbnail($item);
                    break;
                case 'description':
                    $output .= $this->column_description($item);
                    break;
                case 'location':
                    $output .= $this->column_location($item);
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
}