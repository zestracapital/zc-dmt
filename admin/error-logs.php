<?php
/**
 * ZC DMT Error Logs Page
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Check user capabilities
if (!current_user_can('manage_options')) {
    return;
}

// Handle actions
$action = isset($_GET['action']) ? sanitize_key($_GET['action']) : '';

// Handle clearing logs
if (isset($_POST['action']) && $_POST['action'] === 'clear_logs' && 
    isset($_POST['zc_dmt_clear_logs_nonce']) && 
    wp_verify_nonce($_POST['zc_dmt_clear_logs_nonce'], 'zc_dmt_clear_logs')) {
    
    if (class_exists('ZC_DMT_Error_Logger')) {
        $logger = new ZC_DMT_Error_Logger();
        $result = $logger->clear_logs();
        
        if ($result) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success"><p>' . esc_html__('Error logs cleared successfully.', 'zc-dmt') . '</p></div>';
            });
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>' . esc_html__('Failed to clear error logs.', 'zc-dmt') . '</p></div>';
            });
        }
    }
}

// Get filter parameters
$module_filter = isset($_GET['module']) ? sanitize_text_field($_GET['module']) : '';
$level_filter = isset($_GET['level']) ? sanitize_text_field($_GET['level']) : '';
$page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$per_page = 50;

// Get logs and counts
$logs = array();
$log_counts = array('info' => 0, 'warning' => 0, 'error' => 0, 'critical' => 0);
$total_logs = 0;
$total_pages = 1;

if (class_exists('ZC_DMT_Database')) {
    try {
        $db = ZC_DMT_Database::get_instance();
        
        // Get log counts
        $log_counts = $db->get_error_log_counts();
        
        // Get filtered logs
        $args = array(
            'limit' => $per_page,
            'offset' => ($page - 1) * $per_page,
            'module' => $module_filter,
            'level' => $level_filter,
            'order_by' => 'created_at',
            'order' => 'DESC'
        );
        
        $logs = $db->get_error_logs($args);
        
        // Get total count for pagination
        global $wpdb;
        $where_clauses = array();
        $query_params = array();
        
        if (!empty($module_filter)) {
            $where_clauses[] = "module = %s";
            $query_params[] = $module_filter;
        }
        
        if (!empty($level_filter)) {
            $where_clauses[] = "level = %s";
            $query_params[] = $level_filter;
        }
        
        $where_sql = '';
        if (!empty($where_clauses)) {
            $where_sql = " WHERE " . implode(" AND ", $where_clauses);
        }
        
        $total_logs = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$db->error_logs_table} {$where_sql}",
                $query_params
            )
        );
        
        $total_pages = ceil($total_logs / $per_page);
    } catch (Exception $e) {
        error_log('ZC DMT Error Logs: Failed to retrieve logs - ' . $e->getMessage());
        add_action('admin_notices', function() use ($e) {
            echo '<div class="notice notice-error"><p>' . 
                 sprintf(esc_html__('Failed to retrieve error logs: %s', 'zc-dmt'), esc_html($e->getMessage())) . 
                 '</p></div>';
        });
    }
}
?>

<div class="wrap zc-dmt-error-logs">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <?php settings_errors(); ?>
    
    <!-- Filters -->
    <div class="zc-log-filters">
        <form method="get" action="">
            <input type="hidden" name="page" value="zc-dmt-error-logs">
            
            <select name="module">
                <option value=""><?php _e('All Modules', 'zc-dmt'); ?></option>
                <option value="general" <?php selected($module_filter, 'general'); ?>><?php _e('General', 'zc-dmt'); ?></option>
                <option value="indicators" <?php selected($module_filter, 'indicators'); ?>><?php _e('Indicators', 'zc-dmt'); ?></option>
                <option value="calculations" <?php selected($module_filter, 'calculations'); ?>><?php _e('Calculations', 'zc-dmt'); ?></option>
                <option value="backup" <?php selected($module_filter, 'backup'); ?>><?php _e('Backup', 'zc-dmt'); ?></option>
                <option value="api" <?php selected($module_filter, 'api'); ?>><?php _e('API', 'zc-dmt'); ?></option>
                <option value="data_sources" <?php selected($module_filter, 'data_sources'); ?>><?php _e('Data Sources', 'zc-dmt'); ?></option>
                <option value="security" <?php selected($module_filter, 'security'); ?>><?php _e('Security', 'zc-dmt'); ?></option>
            </select>
            
            <select name="level">
                <option value=""><?php _e('All Levels', 'zc-dmt'); ?></option>
                <option value="info" <?php selected($level_filter, 'info'); ?>><?php _e('Info', 'zc-dmt'); ?></option>
                <option value="warning" <?php selected($level_filter, 'warning'); ?>><?php _e('Warning', 'zc-dmt'); ?></option>
                <option value="error" <?php selected($level_filter, 'error'); ?>><?php _e('Error', 'zc-dmt'); ?></option>
                <option value="critical" <?php selected($level_filter, 'critical'); ?>><?php _e('Critical', 'zc-dmt'); ?></option>
            </select>
            
            <?php submit_button(__('Filter', 'zc-dmt'), 'secondary', '', false); ?>
        </form>
        
        <form method="post" action="" style="display: inline-block; margin-left: 10px;">
            <?php wp_nonce_field('zc_dmt_clear_logs', 'zc_dmt_clear_logs_nonce'); ?>
            <input type="hidden" name="action" value="clear_logs">
            <?php submit_button(__('Clear Logs', 'zc-dmt'), 'delete', '', false, array('onclick' => "return confirm('" . esc_attr__('Are you sure you want to clear the error logs?', 'zc-dmt') . "');")); ?>
        </form>
    </div>
    
    <!-- Log Summary -->
    <div class="zc-log-summary">
        <div class="zc-log-count">
            <span class="zc-count-number zc-count-info"><?php echo esc_html($log_counts['info']); ?></span>
            <span class="zc-count-label"><?php _e('Info', 'zc-dmt'); ?></span>
        </div>
        
        <div class="zc-log-count">
            <span class="zc-count-number zc-count-warning"><?php echo esc_html($log_counts['warning']); ?></span>
            <span class="zc-count-label"><?php _e('Warnings', 'zc-dmt'); ?></span>
        </div>
        
        <div class="zc-log-count">
            <span class="zc-count-number zc-count-error"><?php echo esc_html($log_counts['error']); ?></span>
            <span class="zc-count-label"><?php _e('Errors', 'zc-dmt'); ?></span>
        </div>
        
        <div class="zc-log-count">
            <span class="zc-count-number zc-count-critical"><?php echo esc_html($log_counts['critical']); ?></span>
            <span class="zc-count-label"><?php _e('Critical', 'zc-dmt'); ?></span>
        </div>
    </div>
    
    <!-- Logs Table -->
    <?php if (!empty($logs)) : ?>
        <table class="wp-list-table widefat fixed striped error-logs-table">
            <thead>
                <tr>
                    <th><?php _e('Time', 'zc-dmt'); ?></th>
                    <th><?php _e('Module', 'zc-dmt'); ?></th>
                    <th><?php _e('Action', 'zc-dmt'); ?></th>
                    <th><?php _e('Message', 'zc-dmt'); ?></th>
                    <th><?php _e('Level', 'zc-dmt'); ?></th>
                    <th><?php _e('Context', 'zc-dmt'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log) : ?>
                    <tr>
                        <td><?php echo esc_html($log->created_at); ?></td>
                        <td><?php echo esc_html($log->module); ?></td>
                        <td><?php echo esc_html($log->action); ?></td>
                        <td><?php echo esc_html($log->message); ?></td>
                        <td>
                            <span class="zc-log-level zc-log-level-<?php echo esc_attr($log->level); ?>">
                                <?php echo esc_html(ucfirst($log->level)); ?>
                            </span>
                        </td>
                        <td>
                            <?php if (!empty($log->context)) : ?>
                                <button class="button button-small zc-view-context" 
                                        data-context="<?php echo esc_attr($log->context); ?>">
                                    <?php _e('View', 'zc-dmt'); ?>
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1) : ?>
            <div class="tablenav">
                <div class="tablenav-pages">
                    <?php
                    echo paginate_links(array(
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => __('&laquo;'),
                        'next_text' => __('&raquo;'),
                        'total' => $total_pages,
                        'current' => $page,
                        'add_args' => array(
                            'module' => $module_filter,
                            'level' => $level_filter
                        )
                    ));
                    ?>
                </div>
            </div>
        <?php endif; ?>
    <?php else : ?>
        <p><?php _e('No error logs found.', 'zc-dmt'); ?></p>
    <?php endif; ?>
</div>

<style>
.zc-log-filters {
    margin: 20px 0;
    padding: 15px;
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.zc-log-summary {
    display: flex;
    gap: 20px;
    margin: 20px 0;
    padding: 15px;
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.zc-log-count {
    text-align: center;
}

.zc-count-number {
    display: block;
    font-size: 1.5em;
    font-weight: bold;
}

.zc-count-info {
    color: #0073aa;
}

.zc-count-warning {
    color: #ffb900;
}

.zc-count-error {
    color: #dc3232;
}

.zc-count-critical {
    color: #b32d2e;
}

.zc-log-level {
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 0.8em;
    font-weight: bold;
}

.zc-log-level-info {
    background: #d1ecf1;
    color: #0c5460;
}

.zc-log-level-warning {
    background: #fff3cd;
    color: #856404;
}

.zc-log-level-error {
    background: #f8d7da;
    color: #721c24;
}

.zc-log-level-critical {
    background: #f5c6cb;
    color: #721c24;
}

.tablenav-pages {
    margin: 15px 0;
}

.tablenav-pages .current {
    background: #0073aa;
    color: #fff;
    padding: 5px 10px;
    border-radius: 3px;
}
</style>

<script>
jQuery(document).ready(function($) {
    // View context functionality
    $('.zc-view-context').on('click', function() {
        var context = $(this).data('context');
        var decodedContext = '';
        
        try {
            // Try to parse as JSON
            var parsedContext = JSON.parse(context);
            decodedContext = JSON.stringify(parsedContext, null, 2);
        } catch (e) {
            // If not JSON, display as is
            decodedContext = context;
        }
        
        // Display in a modal or alert
        var modal = $('<div class="zc-context-modal"><pre>' + decodedContext + '</pre></div>');
        var overlay = $('<div class="zc-context-overlay"></div>');
        
        $('body').append(overlay).append(modal);
        
        // Close on click
        overlay.on('click', function() {
            modal.remove();
            overlay.remove();
        });
    });
});

// Basic modal styles
jQuery(document).ready(function($) {
    if ($('.zc-context-overlay').length === 0) {
        $('head').append('<style>.zc-context-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9998; }.zc-context-modal { position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: #fff; padding: 20px; border-radius: 4px; z-index: 9999; max-width: 80%; max-height: 80%; overflow: auto; }</style>');
    }
});
</script>
