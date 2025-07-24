<?php
/**
 * Utilitário para instalação e verificação de tabelas
 */

if (!defined('ABSPATH')) {
    exit;
}

class Zuzunely_Install {
    
    /**
     * Criar todas as tabelas necessárias
     */
    public static function create_tables() {
        // Criar tabela de salões
        $saloons_db = new Zuzunely_Saloons_DB();
        $saloons_db->create_tables();
        
        // Criar tabela de mesas
        $tables_db = new Zuzunely_Tables_DB();
        $tables_db->create_tables();
        
        // Criar tabela de bloqueios
        $blocks_db = new Zuzunely_Blocks_DB();
        $blocks_db->create_tables();
        
        // Criar tabela de disponibilidades
        $availability_db = new Zuzunely_Availability_DB();
        $availability_db->create_tables();
        
        // Garantir que a tabela de disponibilidades seja criada
        self::create_availability_table();
        
        // Salvar versão da instalação
        update_option('zuzunely_db_version', ZUZUNELY_VERSION);
        
        // Log de criação
        error_log('Zuzunely: Tabelas criadas - Versão ' . ZUZUNELY_VERSION);
    }
    
    /**
     * Verificar se as tabelas existem e criar se necessário
     */
    public static function verify_tables() {
        global $wpdb;
        
        // Verificar tabela de salões
        $saloons_table = $wpdb->prefix . 'zuzunely_saloons';
        $saloons_exists = $wpdb->get_var("SHOW TABLES LIKE '$saloons_table'") === $saloons_table;
        
        // Verificar tabela de mesas
        $tables_table = $wpdb->prefix . 'zuzunely_tables';
        $tables_exists = $wpdb->get_var("SHOW TABLES LIKE '$tables_table'") === $tables_table;
        
        // Verificar tabela de bloqueios
        $blocks_table = $wpdb->prefix . 'zuzunely_blocks';
        $blocks_exists = $wpdb->get_var("SHOW TABLES LIKE '$blocks_table'") === $blocks_table;
        
        // Verificar tabela de disponibilidades
        $availability_table = $wpdb->prefix . 'zuzunely_availability';
        $availability_exists = $wpdb->get_var("SHOW TABLES LIKE '$availability_table'") === $availability_table;
        
        // Log da verificação
        error_log('Zuzunely: Verificação de tabelas - Salões: ' . ($saloons_exists ? 'Existe' : 'Não existe') . 
                  ', Mesas: ' . ($tables_exists ? 'Existe' : 'Não existe') . 
                  ', Bloqueios: ' . ($blocks_exists ? 'Existe' : 'Não existe') .
                  ', Disponibilidades: ' . ($availability_exists ? 'Existe' : 'Não existe'));
        
        // Criar tabelas faltantes
        if (!$saloons_exists) {
            $saloons_db = new Zuzunely_Saloons_DB();
            $saloons_db->create_tables();
            error_log('Zuzunely: Tabela de salões criada');
        }
        
        if (!$tables_exists) {
            $tables_db = new Zuzunely_Tables_DB();
            $tables_db->create_tables();
            error_log('Zuzunely: Tabela de mesas criada');
        }
        
        if (!$blocks_exists) {
            $blocks_db = new Zuzunely_Blocks_DB();
            $blocks_db->create_tables();
            error_log('Zuzunely: Tabela de bloqueios criada');
        }
        
        if (!$availability_exists) {
            // Tentar criar usando a classe
            $availability_db = new Zuzunely_Availability_DB();
            $availability_db->create_tables();
            
            // Verificar se a tabela foi criada
            $availability_exists = $wpdb->get_var("SHOW TABLES LIKE '$availability_table'") === $availability_table;
            
            // Se ainda não existe, criar diretamente
            if (!$availability_exists) {
                error_log('Zuzunely: Tentando criar tabela de disponibilidades diretamente');
                self::create_availability_table();
            }
            
            error_log('Zuzunely: Tabela de disponibilidades criada');
        }
        
        return array(
            'saloons' => $saloons_exists,
            'tables' => $tables_exists,
            'blocks' => $blocks_exists,
            'availability' => $availability_exists,
            'created_now' => !$saloons_exists || !$tables_exists || !$blocks_exists || !$availability_exists
        );
    }
    
    /**
     * Criar tabela de disponibilidades diretamente
     * Este método garante que a tabela seja criada, mesmo se houver problemas com a classe
     */
    public static function create_availability_table() {
        global $wpdb;
        
        $availability_table = $wpdb->prefix . 'zuzunely_availability';
        $charset_collate = $wpdb->get_charset_collate();
        
        // Verificar se a tabela já existe
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$availability_table}'") === $availability_table;
        
        // Log de verificação
        error_log('Criação manual - Verificando tabela de disponibilidades: ' . ($table_exists ? 'Existe' : 'Não existe'));
        
        // Se a tabela não existir, criar
        if (!$table_exists) {
            // SQL para criar tabela de disponibilidades
            $sql = "CREATE TABLE {$availability_table} (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                table_id mediumint(9) NOT NULL,
                weekday tinyint(1) NOT NULL,
                start_time time NOT NULL,
                end_time time NOT NULL,
                is_active tinyint(1) DEFAULT 1,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY table_weekday (table_id, weekday)
            ) $charset_collate;";
            
            // Usar dbDelta para executar SQL seguro
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            $result = dbDelta($sql);
            
            // Log para facilitar depuração
            error_log('Criação manual - Tabela de disponibilidades criada: ' . $availability_table);
            error_log('Criação manual - Resultado dbDelta: ' . print_r($result, true));
            
            // Verificar se a tabela foi criada
            $table_exists_after = $wpdb->get_var("SHOW TABLES LIKE '{$availability_table}'") === $availability_table;
            error_log('Criação manual - Tabela criada com sucesso? ' . ($table_exists_after ? 'Sim' : 'Não'));
            
            // Tentativa direta se dbDelta falhar
            if (!$table_exists_after) {
                error_log('Criação manual - Tentando criar tabela diretamente com query...');
                $wpdb->query("CREATE TABLE IF NOT EXISTS {$availability_table} (
                    id mediumint(9) NOT NULL AUTO_INCREMENT,
                    table_id mediumint(9) NOT NULL,
                    weekday tinyint(1) NOT NULL,
                    start_time time NOT NULL,
                    end_time time NOT NULL,
                    is_active tinyint(1) DEFAULT 1,
                    created_at datetime DEFAULT CURRENT_TIMESTAMP,
                    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY  (id),
                    KEY table_weekday (table_id, weekday)
                ) $charset_collate;");
                
                // Verificar novamente
                $table_exists_after_direct = $wpdb->get_var("SHOW TABLES LIKE '{$availability_table}'") === $availability_table;
                error_log('Criação manual - Tabela criada após tentativa direta? ' . ($table_exists_after_direct ? 'Sim' : 'Não'));
            }
        } else {
            error_log('Criação manual - Tabela de disponibilidades já existe, ignorando criação.');
        }
    }
}