<?php
/**
 * Classe para gerenciar disponibilidades de mesas
 */

if (!defined('ABSPATH')) {
    exit;
}

class Zuzunely_Availability {
    
    // Construtor
    public function __construct() {
        // Adicionar ajax handlers para disponibilidades
        add_action('wp_ajax_zuzunely_save_availability', array($this, 'ajax_save_availability'));
        add_action('wp_ajax_zuzunely_delete_availability', array($this, 'ajax_delete_availability'));
        add_action('wp_ajax_zuzunely_copy_availability', array($this, 'ajax_copy_availability'));
    }
    
    // Página de administração para disponibilidades
    public static function admin_page() {
        // Verificar se há ação específica (adicionar/editar)
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        switch ($action) {
            case 'add':
                self::render_add_edit_form();
                break;
                
            case 'edit':
                self::render_add_edit_form($id);
                break;
                
            default:
                self::render_list_table();
                break;
        }
    }
    
    // Renderizar lista de disponibilidades
    private static function render_list_table() {
        // Incluir WP_List_Table se não estiver disponível
        if (!class_exists('WP_List_Table')) {
            require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
        }
        
        // Incluir classe da tabela de listagem
        require_once ZUZUNELY_PLUGIN_DIR . 'class-zuzunely-availability-list-table.php';
        
        // Filtros
        $table_filter = isset($_GET['table_id']) ? intval($_GET['table_id']) : 0;
        $weekday_filter = isset($_GET['weekday']) ? intval($_GET['weekday']) : -1; // -1 para todos
        
        // Criar instância da tabela
        $availability_db = new Zuzunely_Availability_DB();
        $table = new Zuzunely_Availability_List_Table();
        $table->prepare_items($table_filter, $weekday_filter);
        
        // CORREÇÃO: Obter mesas diretamente do banco de dados, igual ao render_add_edit_form
        global $wpdb;
        $tables_table = $wpdb->prefix . 'zuzunely_tables';
        $saloons_table = $wpdb->prefix . 'zuzunely_saloons';
        
        $direct_query = "SELECT t.id, t.name, s.name as saloon_name, t.saloon_id 
                         FROM {$tables_table} t 
                         LEFT JOIN {$saloons_table} s ON t.saloon_id = s.id 
                         WHERE t.is_active = 1
                         ORDER BY s.name, t.name";
                         
        $tables = $wpdb->get_results($direct_query, ARRAY_A);
        
        // Log para depuração
        error_log('CORREÇÃO: Mesas encontradas na consulta direta para cópia: ' . count($tables));
        
        // Obter dias da semana
        $weekdays = Zuzunely_Availability_DB::get_weekdays();
        
        // Total de disponibilidades
        $count = $availability_db->count_availabilities(array('include_inactive' => true));
        
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php echo esc_html__('Disponibilidades', 'zuzunely-restaurant'); ?></h1>
            <a href="<?php echo admin_url('admin.php?page=zuzunely-availability&action=add'); ?>" class="page-title-action"><?php echo esc_html__('Adicionar Nova', 'zuzunely-restaurant'); ?></a>
            <a href="<?php echo admin_url('admin.php?page=zuzunely-availability&refresh=' . time()); ?>" class="page-title-action"><?php echo esc_html__('Atualizar Lista', 'zuzunely-restaurant'); ?></a>
            
            <?php if ($count > 0) : ?>
                <div class="notice notice-info is-dismissible">
                    <p><?php echo sprintf(esc_html__('Há %d disponibilidade(s) cadastrada(s).', 'zuzunely-restaurant'), $count); ?></p>
                </div>
            <?php endif; ?>
            
            <div class="tablenav top">
                <div class="alignleft actions">
                    <form method="get">
                        <input type="hidden" name="page" value="zuzunely-availability">
                        
                        <select name="table_id">
                            <option value="0"><?php echo esc_html__('Todas as mesas', 'zuzunely-restaurant'); ?></option>
                            <?php foreach ($tables as $table_item) : ?>
                                <option value="<?php echo $table_item['id']; ?>" <?php selected($table_filter, $table_item['id']); ?>>
                                    <?php echo esc_html($table_item['name']); ?> (<?php echo esc_html($table_item['saloon_name']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <select name="weekday">
                            <option value="-1"><?php echo esc_html__('Todos os dias', 'zuzunely-restaurant'); ?></option>
                            <?php foreach ($weekdays as $key => $day) : ?>
                                <option value="<?php echo $key; ?>" <?php selected($weekday_filter, $key); ?>>
                                    <?php echo esc_html($day); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <input type="submit" class="button" value="<?php echo esc_attr__('Filtrar', 'zuzunely-restaurant'); ?>">
                    </form>
                </div>
                <br class="clear">
            </div>
            
            <?php if ($count > 0) : ?>
            <!-- Formulário para cópia em massa -->
            <div class="zuzunely-bulk-copy-form" style="margin: 10px 0; padding: 10px; background: #f8f8f8; border: 1px solid #ddd;">
                <h3><?php echo esc_html__('Copiar Disponibilidades Selecionadas', 'zuzunely-restaurant'); ?></h3>
                <p><?php echo esc_html__('Selecione registros na tabela abaixo e escolha o destino para cópia:', 'zuzunely-restaurant'); ?></p>
                
                <div class="copy-options" style="display: flex; align-items: center; margin-bottom: 10px;">
                    <label style="margin-right: 15px;">
                        <input type="radio" name="copy_target_type" value="table" checked>
                        <?php echo esc_html__('Para mesa específica', 'zuzunely-restaurant'); ?>
                    </label>
                    
                    <label>
                        <input type="radio" name="copy_target_type" value="saloon">
                        <?php echo esc_html__('Para todas as mesas de um salão', 'zuzunely-restaurant'); ?>
                    </label>
                </div>
                
                <div class="copy-destination table-destination">
                    <select id="copy_to_table" name="copy_to_table" class="regular-text">
                        <option value=""><?php echo esc_html__('Selecione uma mesa', 'zuzunely-restaurant'); ?></option>
                        <?php foreach ($tables as $table_item) : ?>
                            <option value="<?php echo $table_item['id']; ?>">
                                <?php echo esc_html($table_item['name']); ?> (<?php echo esc_html($table_item['saloon_name']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="copy-destination saloon-destination" style="display: none;">
                    <?php
                    // CORREÇÃO: Obter salões diretamente do banco de dados
                    $saloons_query = "SELECT id, name FROM {$saloons_table} WHERE is_active = 1 ORDER BY name";
                    $saloons = $wpdb->get_results($saloons_query, ARRAY_A);
                    
                    error_log('CORREÇÃO: Salões encontrados na consulta direta: ' . count($saloons));
                    ?>
                    <select id="copy_to_saloon" name="copy_to_saloon" class="regular-text">
                        <option value=""><?php echo esc_html__('Selecione um salão', 'zuzunely-restaurant'); ?></option>
                        <?php foreach ($saloons as $saloon) : ?>
                            <option value="<?php echo $saloon['id']; ?>">
                                <?php echo esc_html($saloon['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="button" id="copy_selected_availabilities" class="button button-primary" style="margin-top: 10px;">
                    <?php echo esc_html__('Copiar Selecionados', 'zuzunely-restaurant'); ?>
                </button>
                
                <div id="copy_result_message" style="margin-top: 10px; padding: 5px; display: none;"></div>
            </div>
            <?php endif; ?>
            
            <form method="post" id="availability-items-form">
                <?php 
                // Nonce para operações em massa
                wp_nonce_field('zuzunely_bulk_actions', 'zuzunely_bulk_nonce');
                
                // Exibir tabela
                $table->display(); 
                ?>
            </form>
            
            <div class="zuzunely-debug-info" style="margin-top: 20px; padding: 10px; background: #f8f8f8; border: 1px solid #ddd;">
                <h3><?php echo esc_html__('Informações de Depuração', 'zuzunely-restaurant'); ?></h3>
                <p><?php echo esc_html__('Tabela do banco:', 'zuzunely-restaurant'); ?> <code><?php echo $availability_db->get_availability_table(); ?></code></p>
                <p><?php echo esc_html__('Total de registros:', 'zuzunely-restaurant'); ?> <strong><?php echo $count; ?></strong></p>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // SCRIPT DE AÇÕES ESPECÍFICAS PARA DISPONIBILIDADES
            
            // Exclusão de disponibilidade
            $(document).on('click', '.zuzunely-delete-availability', function(e) {
                e.preventDefault();
                
                if (!confirm(zuzunely_l10n.confirm_delete)) {
                    return;
                }
                
                var $this = $(this);
                var availability_id = $this.data('id');
                var nonce = $this.data('nonce');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'zuzunely_delete_availability',
                        availability_id: availability_id,
                        nonce: nonce
                    },
                    beforeSend: function() {
                        $this.text(zuzunely_l10n.deleting);
                    },
                    success: function(response) {
                        if (response.success) {
                            $this.closest('tr').fadeOut(400, function() {
                                $(this).remove();
                            });
                        } else {
                            alert(response.data);
                            $this.text(zuzunely_l10n.delete);
                        }
                    },
                    error: function() {
                        alert(zuzunely_l10n.error_deleting);
                        $this.text(zuzunely_l10n.delete);
                    }
                });
            });
            
            // Mostrar/ocultar opções de destino com base no tipo selecionado
            $('input[name="copy_target_type"]').on('change', function() {
                var targetType = $('input[name="copy_target_type"]:checked').val();
                
                if (targetType === 'table') {
                    $('.table-destination').show();
                    $('.saloon-destination').hide();
                } else {
                    $('.table-destination').hide();
                    $('.saloon-destination').show();
                }
            });
            
            // Manipular clique no botão de cópia
            $('#copy_selected_availabilities').on('click', function() {
                var selectedItems = $('.availability-cb:checked');
                
                if (selectedItems.length === 0) {
                    alert('<?php echo esc_js(__('Por favor, selecione pelo menos uma disponibilidade para copiar.', 'zuzunely-restaurant')); ?>');
                    return;
                }
                
                var targetType = $('input[name="copy_target_type"]:checked').val();
                var targetId = 0;
                
                if (targetType === 'table') {
                    targetId = $('#copy_to_table').val();
                    if (!targetId) {
                        alert('<?php echo esc_js(__('Por favor, selecione uma mesa de destino.', 'zuzunely-restaurant')); ?>');
                        return;
                    }
                } else {
                    targetId = $('#copy_to_saloon').val();
                    if (!targetId) {
                        alert('<?php echo esc_js(__('Por favor, selecione um salão de destino.', 'zuzunely-restaurant')); ?>');
                        return;
                    }
                }
                
                // Coletar IDs selecionados
                var selectedIds = [];
                selectedItems.each(function() {
                    selectedIds.push($(this).val());
                });
                
                // Exibir indicador de carregamento
                $('#copy_result_message').html('<?php echo esc_js(__('Copiando disponibilidades...', 'zuzunely-restaurant')); ?>').addClass('notice notice-info').removeClass('notice-success notice-error').show();
                
                // Enviar AJAX para copiar
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'zuzunely_copy_availability',
                        availability_ids: selectedIds,
                        target_type: targetType,
                        target_id: targetId,
                        nonce: '<?php echo wp_create_nonce('zuzunely_copy_availability'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#copy_result_message').html(response.data).removeClass('notice-info notice-error').addClass('notice-success');
                            
                            // Recarregar a página após um curto atraso
                            setTimeout(function() {
                                window.location.reload();
                            }, 2000);
                        } else {
                            $('#copy_result_message').html(response.data).removeClass('notice-info notice-success').addClass('notice-error');
                        }
                    },
                    error: function() {
                        $('#copy_result_message').html('<?php echo esc_js(__('Erro ao copiar disponibilidades. Tente novamente.', 'zuzunely-restaurant')); ?>').removeClass('notice-info notice-success').addClass('notice-error');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    // Renderizar formulário de adição/edição - VERSÃO CORRIGIDA
    private static function render_add_edit_form($id = 0) {
        // Se temos ID, vamos buscar a disponibilidade
        $availability = array(
            'table_id' => 0,
            'weekday' => 1, // Segunda-feira por padrão
            'start_time' => '10:00',
            'end_time' => '22:00',
            'is_active' => 1
        );
        
        $title = __('Adicionar Nova Disponibilidade', 'zuzunely-restaurant');
        
        if ($id > 0) {
            $db = new Zuzunely_Availability_DB();
            $loaded_availability = $db->get_availability($id);
            
            if ($loaded_availability) {
                $availability = $loaded_availability;
                $title = __('Editar Disponibilidade', 'zuzunely-restaurant');
            }
        }
        
        // SOLUÇÃO: Obter mesas diretamente do banco de dados, sem filtros complexos
        global $wpdb;
        $tables_table = $wpdb->prefix . 'zuzunely_tables';
        $saloons_table = $wpdb->prefix . 'zuzunely_saloons';
        
        $direct_query = "SELECT t.id, t.name, s.name as saloon_name 
                         FROM {$tables_table} t 
                         LEFT JOIN {$saloons_table} s ON t.saloon_id = s.id 
                         ORDER BY s.name, t.name";
                         
        $tables = $wpdb->get_results($direct_query, ARRAY_A);
        
        // Log para depuração
        error_log('SOLUÇÃO DIRETA: Mesas encontradas na consulta direta: ' . count($tables));
        
        // Obter dias da semana
        $weekdays = Zuzunely_Availability_DB::get_weekdays();
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html($title); ?></h1>
            
            <?php if (count($tables) == 0): ?>
            <div class="notice notice-error">
                <p><strong><?php echo esc_html__('Erro:', 'zuzunely-restaurant'); ?></strong> 
                <?php echo esc_html__('Não foram encontradas mesas no banco de dados. Por favor, verifique se há mesas cadastradas antes de criar disponibilidades.', 'zuzunely-restaurant'); ?></p>
                <p><a href="<?php echo admin_url('admin.php?page=zuzunely-tables&action=add'); ?>" class="button"><?php echo esc_html__('Cadastrar Nova Mesa', 'zuzunely-restaurant'); ?></a></p>
            </div>
            <?php endif; ?>
            
            <form id="zuzunely-availability-form" method="post">
                <input type="hidden" name="availability_id" value="<?php echo $id; ?>">
                <?php wp_nonce_field('zuzunely_availability_nonce', 'zuzunely_nonce'); ?>
                
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="table_id"><?php echo esc_html__('Mesa', 'zuzunely-restaurant'); ?></label>
                            </th>
                            <td>
                                <select name="table_id" id="table_id" class="regular-text" required>
                                    <option value=""><?php echo esc_html__('Selecione uma mesa', 'zuzunely-restaurant'); ?></option>
                                    <?php foreach ($tables as $table) : ?>
                                        <option value="<?php echo $table['id']; ?>" <?php selected($availability['table_id'], $table['id']); ?>>
                                            <?php echo esc_html($table['name']); ?> 
                                            (<?php echo isset($table['saloon_name']) ? esc_html($table['saloon_name']) : ''; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                
                                <?php if (empty($tables)) : ?>
                                    <p class="description" style="color: #cc0000;">
                                        <?php 
                                        echo esc_html__('Nenhuma mesa cadastrada. ', 'zuzunely-restaurant');
                                        echo sprintf(
                                            '<a href="%s">%s</a>',
                                            admin_url('admin.php?page=zuzunely-tables&action=add'),
                                            esc_html__('Adicionar uma mesa', 'zuzunely-restaurant')
                                        );
                                        ?>
                                    </p>
                                <?php endif; ?>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="weekday"><?php echo esc_html__('Dia da Semana', 'zuzunely-restaurant'); ?></label>
                            </th>
                            <td>
                                <select name="weekday" id="weekday" class="regular-text" required>
                                    <?php foreach ($weekdays as $key => $day) : ?>
                                        <option value="<?php echo $key; ?>" <?php selected($availability['weekday'], $key); ?>>
                                            <?php echo esc_html($day); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="start_time"><?php echo esc_html__('Hora de Início', 'zuzunely-restaurant'); ?></label>
                            </th>
                            <td>
                                <input type="time" name="start_time" id="start_time" class="regular-text" 
                                       value="<?php echo esc_attr($availability['start_time']); ?>" required>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="end_time"><?php echo esc_html__('Hora de Término', 'zuzunely-restaurant'); ?></label>
                            </th>
                            <td>
                                <input type="time" name="end_time" id="end_time" class="regular-text" 
                                       value="<?php echo esc_attr($availability['end_time']); ?>" required>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="is_active"><?php echo esc_html__('Status', 'zuzunely-restaurant'); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" name="is_active" id="is_active" value="1" 
                                           <?php checked($availability['is_active'], 1); ?>>
                                    <?php echo esc_html__('Ativa', 'zuzunely-restaurant'); ?>
                                </label>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <p class="submit">
                    <button type="submit" name="submit" id="submit" class="button button-primary">
                        <?php echo esc_html__('Salvar Disponibilidade', 'zuzunely-restaurant'); ?>
                    </button>
                    
                    <a href="<?php echo admin_url('admin.php?page=zuzunely-availability'); ?>" class="button button-secondary">
                        <?php echo esc_html__('Cancelar', 'zuzunely-restaurant'); ?>
                    </a>
                </p>
            </form>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Enviar formulário
            $('#zuzunely-availability-form').on('submit', function(e) {
                e.preventDefault();
                
                var startTime = $('#start_time').val();
                var endTime = $('#end_time').val();
                
                if (startTime >= endTime) {
                    alert('<?php echo esc_js(__('A hora de início deve ser anterior à hora de término.', 'zuzunely-restaurant')); ?>');
                    return false;
                }
                
                // Verificar se uma mesa foi selecionada
                if ($('#table_id').val() === '') {
                    alert('<?php echo esc_js(__('Por favor, selecione uma mesa para continuar.', 'zuzunely-restaurant')); ?>');
                    return false;
                }
                
                var form_data = $(this).serialize();
                console.log('Dados serializados:', form_data);
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'zuzunely_save_availability',
                        form_data: form_data
                    },
                    beforeSend: function() {
                        $('#submit').prop('disabled', true);
                        console.log('Enviando dados para o servidor...');
                    },
                    success: function(response) {
                        console.log('Resposta:', response);
                        
                        if (response.success) {
                            window.location.href = '<?php echo admin_url('admin.php?page=zuzunely-availability'); ?>';
                        } else {
                            alert(response.data);
                            $('#submit').prop('disabled', false);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Erro AJAX:', error);
                        alert('Erro ao salvar. Tente novamente. Detalhes: ' + error);
                        $('#submit').prop('disabled', false);
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    // Ajax handler para salvar disponibilidade
    public function ajax_save_availability() {
        // Log de início
        error_log('Iniciando salvamento AJAX da disponibilidade');
        
        // Verificar nonce
        if (!isset($_POST['form_data'])) {
            error_log('Dados do formulário não encontrados');
            wp_send_json_error(__('Erro de dados. Recarregue a página.', 'zuzunely-restaurant'));
            return;
        }
        
        // Capturar dados do formulário
        parse_str($_POST['form_data'], $form_data);
        
        // Verificar nonce
        if (!isset($form_data['zuzunely_nonce']) || !wp_verify_nonce($form_data['zuzunely_nonce'], 'zuzunely_availability_nonce')) {
            error_log('Nonce inválido');
            wp_send_json_error(__('Erro de segurança. Recarregue a página.', 'zuzunely-restaurant'));
            return;
        }
        
        $availability_id = isset($form_data['availability_id']) ? intval($form_data['availability_id']) : 0;
        $table_id = isset($form_data['table_id']) ? intval($form_data['table_id']) : 0;
        $weekday = isset($form_data['weekday']) ? intval($form_data['weekday']) : 0;
        $start_time = isset($form_data['start_time']) ? sanitize_text_field($form_data['start_time']) : '';
        $end_time = isset($form_data['end_time']) ? sanitize_text_field($form_data['end_time']) : '';
        $is_active = isset($form_data['is_active']) ? 1 : 0;
        
        // Log de dados recebidos
        error_log('Dados do formulário: ' . print_r([
            'id' => $availability_id,
            'table_id' => $table_id,
            'weekday' => $weekday,
            'start_time' => $start_time,
            'end_time' => $end_time,
            'is_active' => $is_active
        ], true));
        
        // Validar dados básicos
        if (empty($table_id)) {
            error_log('Mesa não selecionada');
            wp_send_json_error(__('É necessário selecionar uma mesa.', 'zuzunely-restaurant'));
            return;
        }
        
        if (!isset($weekday) || $weekday < 0 || $weekday > 6) {
            error_log('Dia da semana inválido');
            wp_send_json_error(__('Dia da semana inválido.', 'zuzunely-restaurant'));
            return;
        }
        
        if (empty($start_time) || empty($end_time)) {
            error_log('Horários vazios');
            wp_send_json_error(__('Os horários de início e término são obrigatórios.', 'zuzunely-restaurant'));
            return;
        }
        
        // Validar horários (início < término)
        if ($start_time >= $end_time) {
            error_log('Horário de início maior ou igual ao de término');
            wp_send_json_error(__('A hora de início deve ser anterior à hora de término.', 'zuzunely-restaurant'));
            return;
        }
        
        // Preparar dados para salvar
        $availability_data = array(
            'table_id' => $table_id,
            'weekday' => $weekday,
            'start_time' => $start_time,
            'end_time' => $end_time,
            'is_active' => $is_active
        );
        
        // Inicializar classe de DB
        $db = new Zuzunely_Availability_DB();
        
        // Verificar se já existe disponibilidade similar para evitar duplicatas
        if ($availability_id === 0) {
            $exists = $db->get_availabilities(array(
                'table_id' => $table_id,
                'weekday' => $weekday,
                'include_inactive' => true
            ));
            
            // Verificar sobreposição de horários
            foreach ($exists as $exist) {
                if (
                    ($start_time < $exist['end_time'] && $end_time > $exist['start_time'])
                ) {
                    error_log('Sobreposição de horários detectada');
                    wp_send_json_error(__('Já existe uma disponibilidade para esta mesa neste dia com horário sobreposto.', 'zuzunely-restaurant'));
                    return;
                }
            }
        }
        
        // Inserir ou atualizar
        if ($availability_id > 0) {
            $result = $db->update_availability($availability_id, $availability_data);
            error_log('Tentativa de atualização: ' . ($result ? 'Sucesso' : 'Falha'));
        } else {
            $result = $db->insert_availability($availability_data);
            error_log('Tentativa de inserção. Resultado: ' . $result);
        }
        
        // Verificar resultado
        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error(__('Erro ao salvar dados. Tente novamente.', 'zuzunely-restaurant'));
        }
    }
    
    // Ajax handler para excluir disponibilidade
    public function ajax_delete_availability() {
        // Verificar nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'zuzunely_delete_availability')) {
            wp_send_json_error(__('Erro de segurança.', 'zuzunely-restaurant'));
        }
        
        // Capturar ID
        $availability_id = isset($_POST['availability_id']) ? intval($_POST['availability_id']) : 0;
        
        if ($availability_id <= 0) {
            wp_send_json_error(__('ID de disponibilidade inválido.', 'zuzunely-restaurant'));
        }
        
        // Inicializar classe de DB
        $db = new Zuzunely_Availability_DB();
        
        // Excluir disponibilidade
        $result = $db->delete_availability($availability_id);
        
        // Verificar resultado
        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error(__('Erro ao excluir disponibilidade. Tente novamente.', 'zuzunely-restaurant'));
        }
    }
    
    // Ajax handler para copiar disponibilidades
    public function ajax_copy_availability() {
        // Verificar nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'zuzunely_copy_availability')) {
            wp_send_json_error(__('Erro de segurança.', 'zuzunely-restaurant'));
            return;
        }
        
        // Capturar dados
        $availability_ids = isset($_POST['availability_ids']) ? array_map('intval', $_POST['availability_ids']) : array();
        $target_type = isset($_POST['target_type']) ? sanitize_text_field($_POST['target_type']) : '';
        $target_id = isset($_POST['target_id']) ? intval($_POST['target_id']) : 0;
        
        // Validar dados
        if (empty($availability_ids)) {
            wp_send_json_error(__('Nenhuma disponibilidade selecionada para cópia.', 'zuzunely-restaurant'));
            return;
        }
        
        if (empty($target_type) || empty($target_id)) {
            wp_send_json_error(__('Destino da cópia inválido.', 'zuzunely-restaurant'));
            return;
        }
        
        // Inicializar classes de DB
        $db = new Zuzunely_Availability_DB();
        
        // Log início da operação
        error_log('Iniciando cópia de disponibilidades. IDs: ' . implode(',', $availability_ids) . ' Para: ' . $target_type . ' ID: ' . $target_id);
        
        // Total de cópias realizadas
        $total_copied = 0;
        
        // Processar cada disponibilidade selecionada
        foreach ($availability_ids as $id) {
            // Obter disponibilidade
            $availability = $db->get_availability($id);
            
            if (!$availability) {
                error_log('Disponibilidade não encontrada: ' . $id);
                continue;
            }
            
            // Preparar dados para copiar
            $data = array(
                'table_id' => ($target_type === 'table') ? $target_id : 0,
                'weekday' => $availability['weekday'],
                'start_time' => $availability['start_time'],
                'end_time' => $availability['end_time'],
                'is_active' => $availability['is_active']
            );
            
            // Copiar para mesa ou salão
            if ($target_type === 'table') {
                // Se o ID da mesa for igual, pular
                if ($availability['table_id'] == $target_id) {
                    error_log('Ignorando cópia para a mesma mesa: ' . $target_id);
                    continue;
                }
                
                // CORREÇÃO: Melhor verificação de sobreposição
                $exists = false;
                $existing = $db->get_availabilities(array(
                    'table_id' => $target_id,
                    'weekday' => $availability['weekday'],
                    'include_inactive' => true
                ));
                
                error_log('Verificando ' . count($existing) . ' disponibilidades existentes para a mesa ' . $target_id);
                
                foreach ($existing as $exist) {
                    // Lógica de sobreposição: Se há qualquer sobreposição nos horários
                    if (
                        ($data['start_time'] < $exist['end_time'] && $data['end_time'] > $exist['start_time'])
                    ) {
                        error_log("Sobreposição encontrada: " . $data['start_time'] . "-" . $data['end_time'] . " e " . $exist['start_time'] . "-" . $exist['end_time']);
                        $exists = true;
                        break;
                    }
                }
                
                if (!$exists) {
                    error_log('Inserindo nova disponibilidade para mesa ' . $target_id);
                    $result = $db->insert_availability($data);
                    if ($result) {
                        $total_copied++;
                    }
                }
            } else if ($target_type === 'saloon') {
                // CORREÇÃO: Obter mesas diretamente do banco de dados
                global $wpdb;
                $tables_table = $wpdb->prefix . 'zuzunely_tables';
                
                $direct_query = $wpdb->prepare(
                    "SELECT id, name FROM {$tables_table} 
                     WHERE saloon_id = %d AND is_active = 1",
                    $target_id
                );
                
                $tables = $wpdb->get_results($direct_query, ARRAY_A);
                
                error_log('Mesas encontradas para o salão ' . $target_id . ': ' . count($tables));
                
                foreach ($tables as $table) {
                    // Se for a mesma mesa da origem, pular
                    if ($table['id'] == $availability['table_id']) {
                        error_log('Ignorando cópia para a mesma mesa: ' . $table['id']);
                        continue;
                    }
                    
                    $data['table_id'] = $table['id'];
                    
                    // CORREÇÃO: Melhor verificação de sobreposição
                    $exists = false;
                    $existing = $db->get_availabilities(array(
                        'table_id' => $table['id'],
                        'weekday' => $availability['weekday'],
                        'include_inactive' => true
                    ));
                    
                    error_log('Verificando ' . count($existing) . ' disponibilidades existentes para a mesa ' . $table['id']);
                    
                    foreach ($existing as $exist) {
                        // Lógica de sobreposição: Se há qualquer sobreposição nos horários
                        if (
                            ($data['start_time'] < $exist['end_time'] && $data['end_time'] > $exist['start_time'])
                        ) {
                            error_log("Sobreposição encontrada: " . $data['start_time'] . "-" . $data['end_time'] . " e " . $exist['start_time'] . "-" . $exist['end_time']);
                            $exists = true;
                            break;
                        }
                    }
                    
                    if (!$exists) {
                        error_log('Inserindo nova disponibilidade para mesa ' . $table['id']);
                        $result = $db->insert_availability($data);
                        if ($result) {
                            $total_copied++;
                        }
                    }
                }
            }
        }
        
        // Verificar resultado
        if ($total_copied > 0) {
            wp_send_json_success(sprintf(
                __('%d disponibilidade(s) copiada(s) com sucesso.', 'zuzunely-restaurant'),
                $total_copied
            ));
        } else {
            wp_send_json_error(__('Nenhuma disponibilidade foi copiada. Verifique se já existem registros similares no destino.', 'zuzunely-restaurant'));
        }
    }
}

// Inicializar classe
add_action('plugins_loaded', function() {
    new Zuzunely_Availability();
});