<?php
/**
 * ZC DMT Settings Page
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Check user capabilities
if (!current_user_can('manage_options')) {
    return;
}

// Handle form submissions
$messages = array();

// Handle saving of general settings
if (isset($_POST['submit']) && isset($_POST['zc_dmt_settings_nonce'])) {
    // Verify nonce
    if (!wp_verify_nonce($_POST['zc_dmt_settings_nonce'], 'zc_dmt_save_settings')) {
        wp_die(__('Security check failed', 'zc-dmt'));
    }
    
    // Save General Settings
    $default_timezone = sanitize_text_field($_POST['default_timezone']);
    $default_date_format = sanitize_text_field($_POST['default_date_format']);
    $default_decimal_separator = sanitize_text_field($_POST['default_decimal_separator']);
    $default_thousand_separator = sanitize_text_field($_POST['default_thousand_separator']);
    $cache_duration = intval($_POST['cache_duration']);
    
    update_option('zc_dmt_default_timezone', $default_timezone);
    update_option('zc_dmt_default_date_format', $default_date_format);
    update_option('zc_dmt_default_decimal_separator', $default_decimal_separator);
    update_option('zc_dmt_default_thousand_separator', $default_thousand_separator);
    update_option('zc_dmt_cache_duration', $cache_duration);
    
    // Save Backup Settings
    $enable_drive_backup = isset($_POST['enable_drive_backup']) ? 1 : 0;
    $drive_folder_id = sanitize_text_field($_POST['drive_folder_id']);
    $backup_schedule = sanitize_key($_POST['backup_schedule']);
    $backup_retention = intval($_POST['backup_retention']);
    
    update_option('zc_dmt_enable_drive_backup', $enable_drive_backup);
    update_option('zc_dmt_drive_folder_id', $drive_folder_id);
    update_option('zc_dmt_backup_schedule', $backup_schedule);
    update_option('zc_dmt_backup_retention', $backup_retention);
    
    // Save Chart Settings
    $chart_engine = sanitize_key($_POST['chart_engine']);
    update_option('zc_dmt_chart_engine', $chart_engine);
    
    // Save Importer Settings
    $enable_csv_import = isset($_POST['enable_csv_import']) ? 1 : 0;
    update_option('zc_dmt_enable_csv_import', $enable_csv_import);
    
    // Save Error Logging Settings
    $enable_error_logging = isset($_POST['enable_error_logging']) ? 1 : 0;
    $enable_email_alerts = isset($_POST['enable_email_alerts']) ? 1 : 0;
    $alert_email = sanitize_email($_POST['alert_email']);
    
    update_option('zc_dmt_enable_error_logging', $enable_error_logging);
    update_option('zc_dmt_enable_email_alerts', $enable_email_alerts);
    update_option('zc_dmt_alert_email', $alert_email);
    
    // Save API Keys Settings
    $fred_api_key = sanitize_text_field($_POST['fred_api_key']);
    $eurostat_api_key = sanitize_text_field($_POST['eurostat_api_key']);
    $worldbank_api_key = sanitize_text_field($_POST['worldbank_api_key']);
    
    update_option('zc_dmt_fred_api_key', $fred_api_key);
    update_option('zc_dmt_eurostat_api_key', $eurostat_api_key);
    update_option('zc_dmt_worldbank_api_key', $worldbank_api_key);
    
    $messages[] = array(
        'type' => 'success',
        'text' => __('Settings saved successfully.', 'zc-dmt')
    );
}

// Get current settings
$default_timezone = get_option('zc_dmt_default_timezone', 'UTC');
$default_date_format = get_option('zc_dmt_default_date_format', 'Y-m-d');
$default_decimal_separator = get_option('zc_dmt_default_decimal_separator', '.');
$default_thousand_separator = get_option('zc_dmt_default_thousand_separator', ',');
$cache_duration = get_option('zc_dmt_cache_duration', 15);

$enable_drive_backup = get_option('zc_dmt_enable_drive_backup', 0);
$drive_folder_id = get_option('zc_dmt_drive_folder_id', '');
$backup_schedule = get_option('zc_dmt_backup_schedule', 'daily');
$backup_retention = get_option('zc_dmt_backup_retention', 30);

$chart_engine = get_option('zc_dmt_chart_engine', 'chartjs');

$enable_csv_import = get_option('zc_dmt_enable_csv_import', 1);

$enable_error_logging = get_option('zc_dmt_enable_error_logging', 1);
$enable_email_alerts = get_option('zc_dmt_enable_email_alerts', 0);
$alert_email = get_option('zc_dmt_alert_email', get_option('admin_email'));

$fred_api_key = get_option('zc_dmt_fred_api_key', '');
$eurostat_api_key = get_option('zc_dmt_eurostat_api_key', '');
$worldbank_api_key = get_option('zc_dmt_worldbank_api_key', '');
?>

<div class="wrap zc-dmt-settings">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <?php foreach ($messages as $message): ?>
        <div class="notice notice-<?php echo esc_attr($message['type']); ?> is-dismissible">
            <p><?php echo esc_html($message['text']); ?></p>
        </div>
    <?php endforeach; ?>
    
    <form method="post" action="">
        <?php wp_nonce_field('zc_dmt_save_settings', 'zc_dmt_settings_nonce'); ?>
        
        <div class="zc-settings-tabs">
            <ul class="zc-tabs-nav">
                <li><a href="#general-settings" class="nav-tab nav-tab-active"><?php _e('General', 'zc-dmt'); ?></a></li>
                <li><a href="#backup-settings" class="nav-tab"><?php _e('Backup & Fallback', 'zc-dmt'); ?></a></li>
                <li><a href="#chart-settings" class="nav-tab"><?php _e('Chart Engine', 'zc-dmt'); ?></a></li>
                <li><a href="#importer-settings" class="nav-tab"><?php _e('Importer & Data Fetch', 'zc-dmt'); ?></a></li>
                <li><a href="#error-settings" class="nav-tab"><?php _e('Error Logging & Alerts', 'zc-dmt'); ?></a></li>
                <li><a href="#api-keys-settings" class="nav-tab"><?php _e('API Keys', 'zc-dmt'); ?></a></li>
            </ul>
            
            <!-- General Settings -->
            <div id="general-settings" class="zc-tab-content">
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Default Timezone', 'zc-dmt'); ?></th>
                        <td>
                            <select name="default_timezone">
                                <?php foreach (timezone_identifiers_list() as $timezone) : ?>
                                    <option value="<?php echo esc_attr($timezone); ?>" <?php selected($default_timezone, $timezone); ?>>
                                        <?php echo esc_html($timezone); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php _e('Select the default timezone for date operations.', 'zc-dmt'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Default Date Format', 'zc-dmt'); ?></th>
                        <td>
                            <input type="text" name="default_date_format" value="<?php echo esc_attr($default_date_format); ?>" class="regular-text">
                            <p class="description"><?php _e('Default format for displaying dates (PHP date format).', 'zc-dmt'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Decimal Separator', 'zc-dmt'); ?></th>
                        <td>
                            <input type="text" name="default_decimal_separator" value="<?php echo esc_attr($default_decimal_separator); ?>" class="small-text">
                            <p class="description"><?php _e('Character used to separate decimal points.', 'zc-dmt'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Thousand Separator', 'zc-dmt'); ?></th>
                        <td>
                            <input type="text" name="default_thousand_separator" value="<?php echo esc_attr($default_thousand_separator); ?>" class="small-text">
                            <p class="description"><?php _e('Character used to separate thousands.', 'zc-dmt'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Cache Duration', 'zc-dmt'); ?></th>
                        <td>
                            <input type="number" name="cache_duration" value="<?php echo esc_attr($cache_duration); ?>" class="small-text" min="0" max="1440">
                            <?php _e('minutes', 'zc-dmt'); ?>
                            <p class="description"><?php _e('How long to cache data before refreshing (0 to disable caching).', 'zc-dmt'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Backup Settings -->
            <div id="backup-settings" class="zc-tab-content" style="display: none;">
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Enable Google Drive Backup', 'zc-dmt'); ?></th>
                        <td>
                            <input type="checkbox" name="enable_drive_backup" id="enable_drive_backup" value="1" <?php checked($enable_drive_backup, 1); ?>>
                            <label for="enable_drive_backup"><?php _e('Enable automatic backups to Google Drive', 'zc-dmt'); ?></label>
                            <p class="description"><?php _e('When enabled, indicator data will be automatically backed up to your Google Drive account.', 'zc-dmt'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Google Drive Folder ID', 'zc-dmt'); ?></th>
                        <td>
                            <input type="text" name="drive_folder_id" value="<?php echo esc_attr($drive_folder_id); ?>" class="regular-text">
                            <p class="description"><?php _e('The ID of the Google Drive folder where backups will be stored.', 'zc-dmt'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Backup Schedule', 'zc-dmt'); ?></th>
                        <td>
                            <select name="backup_schedule">
                                <option value="hourly" <?php selected($backup_schedule, 'hourly'); ?>><?php _e('Hourly', 'zc-dmt'); ?></option>
                                <option value="twicedaily" <?php selected($backup_schedule, 'twicedaily'); ?>><?php _e('Twice Daily', 'zc-dmt'); ?></option>
                                <option value="daily" <?php selected($backup_schedule, 'daily'); ?>><?php _e('Daily', 'zc-dmt'); ?></option>
                            </select>
                            <p class="description"><?php _e('How often backups should be automatically created.', 'zc-dmt'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Backup Retention', 'zc-dmt'); ?></th>
                        <td>
                            <input type="number" name="backup_retention" value="<?php echo esc_attr($backup_retention); ?>" class="small-text" min="1" max="365">
                            <?php _e('days', 'zc-dmt'); ?>
                            <p class="description"><?php _e('How many days of backups to retain (older backups will be deleted).', 'zc-dmt'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Chart Settings -->
            <div id="chart-settings" class="zc-tab-content" style="display: none;">
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Default Chart Engine', 'zc-dmt'); ?></th>
                        <td>
                            <select name="chart_engine">
                                <option value="chartjs" <?php selected($chart_engine, 'chartjs'); ?>>Chart.js</option>
                                <option value="highcharts" <?php selected($chart_engine, 'highcharts'); ?>>Highcharts</option>
                            </select>
                            <p class="description"><?php _e('Select the default charting library to use for visualizations.', 'zc-dmt'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Importer Settings -->
            <div id="importer-settings" class="zc-tab-content" style="display: none;">
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Enable CSV Import', 'zc-dmt'); ?></th>
                        <td>
                            <input type="checkbox" name="enable_csv_import" id="enable_csv_import" value="1" <?php checked($enable_csv_import, 1); ?>>
                            <label for="enable_csv_import"><?php _e('Enable CSV file import functionality', 'zc-dmt'); ?></label>
                            <p class="description"><?php _e('Allow importing data from CSV files.', 'zc-dmt'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Error Logging Settings -->
            <div id="error-settings" class="zc-tab-content" style="display: none;">
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Enable Error Logging', 'zc-dmt'); ?></th>
                        <td>
                            <input type="checkbox" name="enable_error_logging" id="enable_error_logging" value="1" <?php checked($enable_error_logging, 1); ?>>
                            <label for="enable_error_logging"><?php _e('Log errors and system events', 'zc-dmt'); ?></label>
                            <p class="description"><?php _e('Record system events and errors for troubleshooting.', 'zc-dmt'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Enable Email Alerts', 'zc-dmt'); ?></th>
                        <td>
                            <input type="checkbox" name="enable_email_alerts" id="enable_email_alerts" value="1" <?php checked($enable_email_alerts, 1); ?>>
                            <label for="enable_email_alerts"><?php _e('Send email alerts for critical errors', 'zc-dmt'); ?></label>
                            <p class="description"><?php _e('Receive email notifications when critical errors occur.', 'zc-dmt'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Alert Email Address', 'zc-dmt'); ?></th>
                        <td>
                            <input type="email" name="alert_email" value="<?php echo esc_attr($alert_email); ?>" class="regular-text">
                            <p class="description"><?php _e('Email address to receive critical error alerts.', 'zc-dmt'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- API Keys Settings -->
            <div id="api-keys-settings" class="zc-tab-content" style="display: none;">
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('FRED API Key', 'zc-dmt'); ?></th>
                        <td>
                            <input type="password" name="fred_api_key" value="<?php echo esc_attr($fred_api_key); ?>" class="regular-text">
                            <p class="description"><?php _e('API key for Federal Reserve Economic Data (FRED).', 'zc-dmt'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Eurostat API Key', 'zc-dmt'); ?></th>
                        <td>
                            <input type="password" name="eurostat_api_key" value="<?php echo esc_attr($eurostat_api_key); ?>" class="regular-text">
                            <p class="description"><?php _e('API key for Eurostat data access.', 'zc-dmt'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('World Bank API Key', 'zc-dmt'); ?></th>
                        <td>
                            <input type="password" name="worldbank_api_key" value="<?php echo esc_attr($worldbank_api_key); ?>" class="regular-text">
                            <p class="description"><?php _e('API key for World Bank data access.', 'zc-dmt'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <?php submit_button(__('Save Changes', 'zc-dmt')); ?>
    </form>
</div>

<style>
.zc-settings-tabs {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin: 20px 0;
}

.zc-tabs-nav {
    margin: 0 0 20px;
    padding: 0;
    list-style: none;
    border-bottom: 1px solid #ccc;
}

.zc-tabs-nav .nav-tab {
    border: 1px solid #ccc;
    border-bottom: none;
    border-radius: 4px 4px 0 0;
    background: #f1f1f1;
    margin-bottom: -1px;
    padding: 8px 12px;
    text-decoration: none;
    display: inline-block;
}

.zc-tabs-nav .nav-tab.nav-tab-active {
    background: #fff;
    border-bottom: 1px solid #fff;
    color: #000;
}

.zc-tab-content {
    display: none;
}

.zc-tab-content:first-child {
    display: block;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Tab switching
    $('.zc-tabs-nav a').on('click', function(e) {
        e.preventDefault();
        
        // Remove active classes
        $('.zc-tabs-nav a').removeClass('nav-tab-active');
        $('.zc-tab-content').hide();
        
        // Add active class to clicked tab
        $(this).addClass('nav-tab-active');
        
        // Show corresponding content
        var target = $(this).attr('href');
        $(target).show();
    });
});
</script>
