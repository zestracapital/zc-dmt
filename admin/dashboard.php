<?php
/**
 * ZC DMT Dashboard Page (Robust Version)
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    return; // Changed from exit to return
}

// Check user capabilities
if (!current_user_can('manage_options')) {
    return;
}

// Initialize variables to prevent undefined variable notices
$stats = array(
    'total_indicators' => 0,
    'active_indicators' => 0,
    'data_points' => 0
);

$recent_logs = array();
$log_counts = array(
    'info' => 0,
    'warning' => 0,
    'error' => 0,
    'critical' => 0
);

$api_keys = array();
$source_list = array();

// Get indicators stats - SAFELY
if (class_exists('ZC_DMT_Indicators')) {
    try {
        $indicators = new ZC_DMT_Indicators();
        $all_indicators = $indicators->get_indicators();
        
        $stats['total_indicators'] = count($all_indicators);
        
        // Count active indicators
        $active_count = 0;
        foreach ($all_indicators as $indicator) {
            if (isset($indicator->is_active) && $indicator->is_active) {
                $active_count++;
            }
        }
        $stats['active_indicators'] = $active_count;
        
        // Count total data points
        $data_point_count = 0;
        if (class_exists('ZC_DMT_Database')) {
            try {
                $db = ZC_DMT_Database::get_instance();
                global $wpdb;
                $count = $wpdb->get_var("SELECT COUNT(*) FROM {$db->data_points_table}");
                $data_point_count = $count ? (int)$count : 0;
            } catch (Exception $e) {
                error_log('ZC DMT Dashboard: Failed to count data points - ' . $e->getMessage());
            }
        }
        $stats['data_points'] = $data_point_count;
        
    } catch (Exception $e) {
        error_log('ZC DMT Dashboard: Failed to get indicators - ' . $e->getMessage());
        echo '<div class="notice notice-warning"><p>' . sprintf(esc_html__('ZC DMT Dashboard Warning: Could not load indicators (%s).', 'zc-dmt'), esc_html($e->getMessage())) . '</p></div>';
    }
}

// Get recent logs - SAFELY
if (class_exists('ZC_DMT_Error_Logger')) {
    try {
        $logger = new ZC_DMT_Error_Logger();
        $recent_logs = $logger->get_recent_logs(5);
        $log_counts = $logger->get_log_level_counts();
    } catch (Exception $e) {
        error_log('ZC DMT Dashboard: Failed to get error logs - ' . $e->getMessage());
        echo '<div class="notice notice-warning"><p>' . sprintf(esc_html__('ZC DMT Dashboard Warning: Could not load error logs (%s).', 'zc-dmt'), esc_html($e->getMessage())) . '</p></div>';
    }
}

// Get API keys - SAFELY
if (class_exists('ZC_DMT_Security')) {
    try {
        $security = ZC_DMT_Security::get_instance();
        $api_keys = $security->get_all_keys();
    } catch (Exception $e) {
        error_log('ZC DMT Dashboard: Failed to get API keys - ' . $e->getMessage());
        echo '<div class="notice notice-warning"><p>' . sprintf(esc_html__('ZC DMT Dashboard Warning: Could not load API keys (%s).', 'zc-dmt'), esc_html($e->getMessage())) . '</p></div>';
    }
}

// Get data sources - SAFELY
if (class_exists('ZC_DMT_Data_Sources')) {
    try {
        $sources = new ZC_DMT_Data_Sources();
        $source_list = $sources->get_sources();
    } catch (Exception $e) {
        error_log('ZC DMT Dashboard: Failed to get data sources - ' . $e->getMessage());
        // Keep default empty sources
    }
}
?>

<div class="wrap zc-dmt-dashboard">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <?php settings_errors(); ?>
    
    <!-- Dashboard Stats -->
    <div class="zc-dashboard-stats">
        <div class="zc-stat-card">
            <div class="zc-stat-value"><?php echo esc_html(number_format_i18n($stats['total_indicators'])); ?></div>
            <div class="zc-stat-label"><?php esc_html_e('Total Indicators', 'zc-dmt'); ?></div>
        </div>
        
        <div class="zc-stat-card">
            <div class="zc-stat-value"><?php echo esc_html(number_format_i18n($stats['active_indicators'])); ?></div>
            <div class="zc-stat-label"><?php esc_html_e('Active Indicators', 'zc-dmt'); ?></div>
        </div>
        
        <div class="zc-stat-card">
            <div class="zc-stat-value"><?php echo esc_html(number_format_i18n($stats['data_points'])); ?></div>
            <div class="zc-stat-label"><?php esc_html_e('Data Points', 'zc-dmt'); ?></div>
        </div>
        
        <div class="zc-stat-card">
            <div class="zc-stat-value"><?php echo esc_html(count($api_keys)); ?></div>
            <div class="zc-stat-label"><?php esc_html_e('API Keys', 'zc-dmt'); ?></div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="zc-dashboard-section">
        <h2><?php esc_html_e('Quick Actions', 'zc-dmt'); ?></h2>
        <div class="zc-quick-actions">
            <a href="<?php echo esc_url(admin_url('admin.php?page=zc-dmt-indicators')); ?>" class="button"><?php esc_html_e('Manage Indicators', 'zc-dmt'); ?></a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=zc-dmt-data-sources')); ?>" class="button"><?php esc_html_e('Configure Sources', 'zc-dmt'); ?></a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=zc-dmt-calculations')); ?>" class="button"><?php esc_html_e('Manual Calculations', 'zc-dmt'); ?></a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=zc-dmt-backup')); ?>" class="button"><?php esc_html_e('Backup Settings', 'zc-dmt'); ?></a>
        </div>
    </div>
    
    <!-- Recent Activity -->
    <div class="zc-dashboard-section">
        <h2><?php esc_html_e('Recent Activity', 'zc-dmt'); ?></h2>
        
        <?php if (!empty($recent_logs)) : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Time', 'zc-dmt'); ?></th>
                        <th><?php esc_html_e('Module', 'zc-dmt'); ?></th>
                        <th><?php esc_html_e('Message', 'zc-dmt'); ?></th>
                        <th><?php esc_html_e('Level', 'zc-dmt'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_logs as $log) : ?>
                        <tr>
                            <td><?php echo esc_html($log->created_at); ?></td>
                            <td><?php echo esc_html($log->module); ?></td>
                            <td><?php echo esc_html($log->message); ?></td>
                            <td>
                                <span class="zc-log-level zc-log-level-<?php echo esc_attr($log->level); ?>">
                                    <?php echo esc_html(ucfirst($log->level)); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <p><?php esc_html_e('No recent activity found.', 'zc-dmt'); ?></p>
        <?php endif; ?>
    </div>
    
    <!-- System Information -->
    <div class="zc-dashboard-section">
        <h2><?php esc_html_e('System Information', 'zc-dmt'); ?></h2>
        
        <div class="zc-system-info">
            <div class="zc-info-card">
                <h3><?php esc_html_e('Plugin Version', 'zc-dmt'); ?></h3>
                <p><?php echo esc_html(ZC_DMT_VERSION); ?></p>
            </div>
            
            <div class="zc-info-card">
                <h3><?php esc_html_e('WordPress Version', 'zc-dmt'); ?></h3>
                <p><?php echo esc_html($GLOBALS['wp_version']); ?></p>
            </div>
            
            <div class="zc-info-card">
                <h3><?php esc_html_e('PHP Version', 'zc-dmt'); ?></h3>
                <p><?php echo esc_html(phpversion()); ?></p>
            </div>
        </div>
    </div>
</div>

<style>
.zc-dmt-dashboard {
    padding: 20px 0;
}

.zc-dashboard-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.zc-stat-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.zc-stat-value {
    font-size: 2em;
    font-weight: bold;
    color: #0073aa;
    margin-bottom: 5px;
}

.zc-stat-label {
    font-size: 0.9em;
    color: #666;
}

.zc-dashboard-section {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin-bottom: 30px;
}

.zc-dashboard-section h2 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

.zc-quick-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.zc-quick-actions .button {
    margin-right: 10px;
    margin-bottom: 10px;
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

.zc-system-info {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}

.zc-info-card {
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 15px;
}

.zc-info-card h3 {
    margin-top: 0;
    font-size: 1em;
    color: #666;
}
</style>
