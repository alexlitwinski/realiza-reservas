/**
 * JavaScript para administração do plugin Zuzunely Restaurant
 */
jQuery(document).ready(function($) {
    
    // Inicializar variáveis localizadas (normalmente definidas no PHP)
    // Isso é apenas um fallback
    if (typeof zuzunely_l10n === 'undefined') {
        window.zuzunely_l10n = {
            confirm_delete: 'Tem certeza que deseja excluir este item?',
            deleting: 'Excluindo...',
            delete: 'Excluir',
            error_deleting: 'Erro ao excluir. Tente novamente.'
        };
    }
    
    if (typeof zuzunely_admin_urls === 'undefined') {
        window.zuzunely_admin_urls = {
            saloons_list: 'admin.php?page=zuzunely-saloons',
            tables_list: 'admin.php?page=zuzunely-tables',
            blocks_list: 'admin.php?page=zuzunely-blocks'
        };
    }
});