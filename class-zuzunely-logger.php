<?php
/**
 * Classe para gerenciar logs do plugin Zuzunely Restaurant
 */

if (!defined('ABSPATH')) {
    exit;
}

class Zuzunely_Logger {
    
    // Caminho do arquivo de log
    private static $log_file;
    
    // Nível de log (1=ERROR, 2=WARNING, 3=INFO, 4=DEBUG)
    private static $log_level = 4;
    
    // Inicializar logger
    public static function init() {
        // Definir caminho do arquivo de log
        self::$log_file = ZUZUNELY_PLUGIN_DIR . 'zuzunely-debug.log';
        
        // Criar arquivo se não existir
        if (!file_exists(self::$log_file)) {
            touch(self::$log_file);
            chmod(self::$log_file, 0666); // Garantir permissões de escrita
        }
    }
    
    /**
     * Escrever mensagem no log
     * 
     * @param string $message Mensagem a ser registrada
     * @param string $level Nível do log (ERROR, WARNING, INFO, DEBUG)
     * @param array $context Dados adicionais para incluir no log
     * @return void
     */
    public static function log($message, $level = 'INFO', $context = array()) {
        // Inicializar se necessário
        if (empty(self::$log_file)) {
            self::init();
        }
        
        // Verificar nível de log
        $numeric_level = self::get_numeric_level($level);
        if ($numeric_level > self::$log_level) {
            return;
        }
        
        // Formatar timestamp
        $timestamp = date('Y-m-d H:i:s');
        
        // Formatar contexto
        $context_str = '';
        if (!empty($context)) {
            $context_str = ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE);
        }
        
        // Obter informações do chamador para rastreabilidade
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = isset($backtrace[1]) ? $backtrace[1] : array();
        $caller_info = '';
        
        if (!empty($caller)) {
            $file = isset($caller['file']) ? basename($caller['file']) : 'unknown';
            $line = isset($caller['line']) ? $caller['line'] : 'unknown';
            $function = isset($caller['function']) ? $caller['function'] : 'unknown';
            $caller_info = " | {$file}:{$line} in {$function}()";
        }
        
        // Construir mensagem de log
        $log_entry = "[{$timestamp}] [{$level}]{$caller_info} | {$message}{$context_str}\n";
        
        // Escrever no arquivo
        file_put_contents(self::$log_file, $log_entry, FILE_APPEND);
        
        // Para logs de erro, também exibir na página de administração se estiver no backend
        if ($level == 'ERROR' && is_admin()) {
            add_settings_error(
                'zuzunely_logger',
                'zuzunely_error',
                $message,
                'error'
            );
        }
    }
    
    /**
     * Log de nível ERROR
     */
    public static function error($message, $context = array()) {
        self::log($message, 'ERROR', $context);
    }
    
    /**
     * Log de nível WARNING
     */
    public static function warning($message, $context = array()) {
        self::log($message, 'WARNING', $context);
    }
    
    /**
     * Log de nível INFO
     */
    public static function info($message, $context = array()) {
        self::log($message, 'INFO', $context);
    }
    
    /**
     * Log de nível DEBUG
     */
    public static function debug($message, $context = array()) {
        self::log($message, 'DEBUG', $context);
    }
    
    /**
     * Converter nível textual para numérico
     */
    private static function get_numeric_level($level) {
        switch (strtoupper($level)) {
            case 'ERROR':
                return 1;
            case 'WARNING':
                return 2;
            case 'INFO':
                return 3;
            case 'DEBUG':
                return 4;
            default:
                return 3;
        }
    }
    
    /**
     * Limpar arquivo de log
     */
    public static function clear_log() {
        if (empty(self::$log_file)) {
            self::init();
        }
        
        file_put_contents(self::$log_file, '');
    }
    
    /**
     * Obter conteúdo do arquivo de log
     */
    public static function get_log_content() {
        if (empty(self::$log_file)) {
            self::init();
        }
        
        if (file_exists(self::$log_file)) {
            return file_get_contents(self::$log_file);
        }
        
        return '';
    }
}