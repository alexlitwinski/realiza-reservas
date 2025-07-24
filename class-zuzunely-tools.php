<?php
/**
 * Diagnóstico e reparação de tabelas
 */

if (!defined('ABSPATH')) {
    exit;
}

class Zuzunely_Tools {
    
    /**
     * Renderiza a página de ferramentas
     */
    public static function render_page() {
        global $wpdb;
        
        // Verifica se o usuário solicitou reparo
        $repair_request = isset($_POST['repair_tables']) && $_POST['repair_tables'] == 1;
        $create_tables_manual = isset($_POST['create_tables_manual']) && $_POST['create_tables_manual'] == 1;
        
        // Nomes das tabelas
        $saloons_table = $wpdb->prefix . 'zuzunely_saloons';
        $tables_table = $wpdb->prefix . 'zuzunely_tables';
        
        // Verifica se as tabelas existem
        $saloons_exists = $wpdb->get_var("SHOW TABLES LIKE '$saloons_table'") === $saloons_table;
        $tables_exists = $wpdb->get_var("SHOW TABLES LIKE '$tables_table'") === $tables_table;
        
        // Ação de reparo
        $repair_message = '';
        $repair_status = false;
        
        if ($repair_request) {
            // Criar tabelas via instalador
            require_once ZUZUNELY_PLUGIN_DIR . 'class-zuzunely-install.php';
            Zuzunely_Install::create_tables();
            
            // Verificar novamente
            $saloons_exists_after = $wpdb->get_var("SHOW TABLES LIKE '$saloons_table'") === $saloons_table;
            $tables_exists_after = $wpdb->get_var("SHOW TABLES LIKE '$tables_table'") === $tables_table;
            
            if ($saloons_exists_after && $tables_exists_after) {
                $repair_message = __('Tabelas criadas/reparadas com sucesso!', 'zuzunely-restaurant');
                $repair_status = true;
            } else {
                $repair_message = __('Falha ao criar/reparar tabelas. Verifique os logs do WordPress.', 'zuzunely-restaurant');
                $repair_status = false;
            }
            
            // Atualizar status
            $saloons_exists = $saloons_exists_after;
            $tables_exists = $tables_exists_after;
        }
        
        // Criação manual
        $manual_create_message = '';
        $manual_create_status = false;
        
        if ($create_tables_manual) {
            // SQL para criar tabela de salões
            $charset_collate = $wpdb->get_charset_collate();
            
            // Criar tabela de salões
            $wpdb->query("CREATE TABLE IF NOT EXISTS $saloons_table (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                name varchar(255) NOT NULL,
                description text NOT NULL,
                images longtext,
                is_active tinyint(1) DEFAULT 1,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (id)
            ) $charset_collate;");
            
            // Criar tabela de mesas
            $wpdb->query("CREATE TABLE IF NOT EXISTS $tables_table (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                name varchar(255) NOT NULL,
                description text NOT NULL,
                saloon_id mediumint(9) NOT NULL,
                capacity int(5) NOT NULL DEFAULT 1,
                is_active tinyint(1) DEFAULT 1,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (id)
            ) $charset_collate;");
            
            // Verificar novamente
            $saloons_exists_after = $wpdb->get_var("SHOW TABLES LIKE '$saloons_table'") === $saloons_table;
            $tables_exists_after = $wpdb->get_var("SHOW TABLES LIKE '$tables_table'") === $tables_table;
            
            if ($saloons_exists_after && $tables_exists_after) {
                $manual_create_message = __('Tabelas criadas manualmente com sucesso!', 'zuzunely-restaurant');
                $manual_create_status = true;
            } else {
                $manual_create_message = __('Falha ao criar tabelas manualmente. Verifique os logs do WordPress.', 'zuzunely-restaurant');
                $manual_create_status = false;
            }
            
            // Atualizar status
            $saloons_exists = $saloons_exists_after;
            $tables_exists = $tables_exists_after;
        }
        
        // Obter informações do MySQL
        $mysql_version = $wpdb->get_var("SELECT VERSION()");
        $mysql_variables = $wpdb->get_results("SHOW VARIABLES LIKE 'max%'");
        $mysql_vars = array();
        foreach ($mysql_variables as $var) {
            $mysql_vars[$var->Variable_name] = $var->Value;
        }
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Ferramentas de Diagnóstico - Zuzunely Restaurant', 'zuzunely-restaurant'); ?></h1>
            
            <?php if ($repair_request && $repair_status) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php echo esc_html($repair_message); ?></p>
                </div>
            <?php elseif ($repair_request && !$repair_status) : ?>
                <div class="notice notice-error is-dismissible">
                    <p><?php echo esc_html($repair_message); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if ($create_tables_manual && $manual_create_status) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php echo esc_html($manual_create_message); ?></p>
                </div>
            <?php elseif ($create_tables_manual && !$manual_create_status) : ?>
                <div class="notice notice-error is-dismissible">
                    <p><?php echo esc_html($manual_create_message); ?></p>
                </div>
            <?php endif; ?>
            
            <h2><?php echo esc_html__('Diagnóstico de Tabelas', 'zuzunely-restaurant'); ?></h2>
            
            <table class="widefat" style="margin-top: 20px;">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('Tabela', 'zuzunely-restaurant'); ?></th>
                        <th><?php echo esc_html__('Nome Completo', 'zuzunely-restaurant'); ?></th>
                        <th><?php echo esc_html__('Status', 'zuzunely-restaurant'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong><?php echo esc_html__('Salões', 'zuzunely-restaurant'); ?></strong></td>
                        <td><code><?php echo esc_html($saloons_table); ?></code></td>
                        <td>
                            <?php if ($saloons_exists) : ?>
                                <span style="background-color: #dff0d8; padding: 3px 8px; border-radius: 3px; color: #3c763d;">
                                    <?php echo esc_html__('Existe', 'zuzunely-restaurant'); ?>
                                </span>
                            <?php else : ?>
                                <span style="background-color: #f2dede; padding: 3px 8px; border-radius: 3px; color: #a94442;">
                                    <?php echo esc_html__('Não existe', 'zuzunely-restaurant'); ?>
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong><?php echo esc_html__('Mesas', 'zuzunely-restaurant'); ?></strong></td>
                        <td><code><?php echo esc_html($tables_table); ?></code></td>
                        <td>
                            <?php if ($tables_exists) : ?>
                                <span style="background-color: #dff0d8; padding: 3px 8px; border-radius: 3px; color: #3c763d;">
                                    <?php echo esc_html__('Existe', 'zuzunely-restaurant'); ?>
                                </span>
                            <?php else : ?>
                                <span style="background-color: #f2dede; padding: 3px 8px; border-radius: 3px; color: #a94442;">
                                    <?php echo esc_html__('Não existe', 'zuzunely-restaurant'); ?>
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tbody>
            </table>
            
            <h2><?php echo esc_html__('Ações', 'zuzunely-restaurant'); ?></h2>
            
            <div style="display: flex; gap: 20px;">
                <div style="flex: 1; background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px;">
                    <h3><?php echo esc_html__('Reparar Tabelas', 'zuzunely-restaurant'); ?></h3>
                    <p><?php echo esc_html__('Use esta ferramenta para tentar criar ou reparar as tabelas do banco de dados.', 'zuzunely-restaurant'); ?></p>
                    
                    <form method="post">
                        <input type="hidden" name="repair_tables" value="1">
                        <p class="submit">
                            <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php echo esc_attr__('Reparar Tabelas', 'zuzunely-restaurant'); ?>">
                        </p>
                    </form>
                </div>
                
                <div style="flex: 1; background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px;">
                    <h3><?php echo esc_html__('Criar Tabelas Manualmente', 'zuzunely-restaurant'); ?></h3>
                    <p><?php echo esc_html__('Use esta opção apenas se o método padrão falhar.', 'zuzunely-restaurant'); ?></p>
                    
                    <form method="post">
                        <input type="hidden" name="create_tables_manual" value="1">
                        <p class="submit">
                            <input type="submit" name="submit" id="submit-manual" class="button button-secondary" value="<?php echo esc_attr__('Criar Tabelas Manualmente', 'zuzunely-restaurant'); ?>">
                        </p>
                    </form>
                </div>
            </div>
            
            <h2><?php echo esc_html__('Informações do Sistema', 'zuzunely-restaurant'); ?></h2>
            
            <table class="widefat" style="margin-top: 20px;">
                <tbody>
                    <tr>
                        <td width="200"><strong><?php echo esc_html__('Versão do WordPress', 'zuzunely-restaurant'); ?></strong></td>
                        <td><?php echo esc_html(get_bloginfo('version')); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php echo esc_html__('Versão do PHP', 'zuzunely-restaurant'); ?></strong></td>
                        <td><?php echo esc_html(phpversion()); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php echo esc_html__('Versão do MySQL', 'zuzunely-restaurant'); ?></strong></td>
                        <td><?php echo esc_html($mysql_version); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php echo esc_html__('Prefixo de Tabelas', 'zuzunely-restaurant'); ?></strong></td>
                        <td><?php echo esc_html($wpdb->prefix); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php echo esc_html__('Versão do Plugin', 'zuzunely-restaurant'); ?></strong></td>
                        <td><?php echo esc_html(ZUZUNELY_VERSION); ?></td>
                    </tr>
                </tbody>
            </table>
            
            <h2><?php echo esc_html__('Variáveis do MySQL', 'zuzunely-restaurant'); ?></h2>
            
            <table class="widefat" style="margin-top: 20px;">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('Variável', 'zuzunely-restaurant'); ?></th>
                        <th><?php echo esc_html__('Valor', 'zuzunely-restaurant'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($mysql_vars as $name => $value) : ?>
                    <tr>
                        <td><code><?php echo esc_html($name); ?></code></td>
                        <td><?php echo esc_html($value); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}