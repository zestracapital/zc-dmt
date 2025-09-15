<?php
/**
 * ZC DMT Backup Settings Page
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Check user capabilities
if (!current_user_can('manage_options')) {
    return;
}

// Get current settings
$enable_backup = get_option('zc_dmt_enable_drive_backup', 0);
$drive_folder_id = get_option('zc_dmt_drive_folder_id', '');
$backup_schedule = get_option('zc_dmt_backup_schedule', 'daily');
$backup_retention = get_option('zc-dmt_backup_retention', 30);
$google_client_id = get_option('zc_dmt_google_client_id', '');
$google_client_secret = get_option('zc_dmt_google_client_secret', '');
$google_refresh_token = get_option('zc_dmt_google_refresh_token', '');

// Handle form submissions
if (isset($_POST['submit']) && isset($_POST['zc_dmt_backup_settings_nonce'])) {
    // Verify nonce
    if (!wp_verify_nonce($_POST['zc_dmt_backup_settings_nonce'], 'zc_dmt_save_backup_settings')) {
        wp_die(__('Security check failed', 'zc-dmt'));
    }
    
    // Save settings
    $enable_backup = isset($_POST['zc_dmt_enable_drive_backup']) ? 1 : 0;
    $drive_folder_id = sanitize_text_field($_POST['zc_dmt_drive_folder_id']);
    $backup_schedule = sanitize_key($_POST['zc_dmt_backup_schedule']);
    $backup_retention = intval($_POST['zc_dmt_backup_retention']);
    $google_client_id = sanitize_text_field($_POST['zc_dmt_google_client_id']);
    $google_client_secret = sanitize_text_field($_POST['zc_dmt_google_client_secret']);
    $google_refresh_token = sanitize_text_field($_POST['zc_dmt_google_refresh_token']);
    
    update_option('zc_dmt_enable_drive_backup', $enable_backup);
    update_option('zc_dmt_drive_folder_id', $drive_folder_id);
    update_option('zc_dmt_backup_schedule', $backup_schedule);
    update_option('zc_dmt_backup_retention', $backup_retention);
    update_option('zc_dmt_google_client_id', $google_client_id);
    update_option('zc_dmt_google_client_secret', $google_client_secret);
    update_option('zc_dmt_google_refresh_token', $google_refresh_token);
    
    // Schedule backups if enabled
    if ($enable_backup && class_exists('ZC_DMT_Backup')) {
        $backup = new ZC_DMT_Backup();
        $backup->schedule_backups();
    }
    
    add_action('admin_notices', function() {
        echo '<div class="notice notice-success"><p>' . esc_html__('Backup settings saved successfully.', 'zc-dmt') . '</p></div>';
    });
}

// Test connection if requested
$connection_test_result = null;
if (isset($_POST['test_connection']) && isset($_POST['zc_dmt_backup_settings_nonce'])) {
    // Verify nonce
    if (!wp_verify_nonce($_POST['zc_dmt_backup_settings_nonce'], 'zc_dmt_save_backup_settings')) {
        wp_die(__('Security check failed', 'zc-dmt'));
    }
    
    if (class_exists('ZC_DMT_Backup')) {
        $backup = new ZC_DMT_Backup();
        $connection_test_result = $backup->test_connection();
    }
}
?>

<div class="wrap zc-dmt-backup-settings">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <?php settings_errors(); ?>
    
    <form method="post" action="">
        <?php wp_nonce_field('zc_dmt_save_backup_settings', 'zc_dmt_backup_settings_nonce'); ?>
        
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('Enable Google Drive Backup', 'zc-dmt'); ?></th>
                <td>
                    <input type="checkbox" name="zc_dmt_enable_drive_backup" id="zc_dmt_enable_drive_backup" value="1" <?php checked($enable_backup, 1); ?>>
                    <label for="zc_dmt_enable_drive_backup"><?php esc_html_e('Enable automatic backups to Google Drive', 'zc-dmt'); ?></label>
                    <p class="description"><?php esc_html_e('When enabled, indicator data will be automatically backed up to your Google Drive account.', 'zc-dmt'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php esc_html_e('Google Drive Folder ID', 'zc-dmt'); ?></th>
                <td>
                    <input type="text" name="zc_dmt_drive_folder_id" id="zc_dmt_drive_folder_id" value="<?php echo esc_attr($drive_folder_id); ?>" class="regular-text">
                    <p class="description"><?php esc_html_e('The ID of the Google Drive folder where backups will be stored. You can find this in the URL when viewing the folder in Google Drive.', 'zc-dmt'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php esc_html_e('Google Client ID', 'zc-dmt'); ?></th>
                <td>
                    <input type="text" name="zc_dmt_google_client_id" id="zc_dmt_google_client_id" value="<?php echo esc_attr($google_client_id); ?>" class="regular-text">
                    <p class="description"><?php esc_html_e('Your Google API Client ID for OAuth authentication.', 'zc-dmt'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php esc_html_e('Google Client Secret', 'zc-dmt'); ?></th>
                <td>
                    <input type="password" name="zc_dmt_google_client_secret" id="zc_dmt_google_client_secret" value="<?php echo esc_attr($google_client_secret); ?>" class="regular-text">
                    <p class="description"><?php esc_html_e('Your Google API Client Secret for OAuth authentication.', 'zc-dmt'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php esc_html_e('Google Refresh Token', 'zc-dmt'); ?></th>
                <td>
                    <input type="password" name="zc_dmt_google_refresh_token" id="zc_dmt_google_refresh_token" value="<?php echo esc_attr($google_refresh_token); ?>" class="regular-text">
                    <p class="description"><?php esc_html_e('Your Google API Refresh Token for OAuth authentication.', 'zc-dmt'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php esc_html_e('Backup Schedule', 'zc-dmt'); ?></th>
                <td>
                    <select name="zc_dmt_backup_schedule" id="zc_dmt_backup_schedule">
                        <option value="hourly" <?php selected($backup_schedule, 'hourly'); ?>><?php esc_html_e('Hourly', 'zc-dmt'); ?></option>
                        <option value="twicedaily" <?php selected($backup_schedule, 'twicedaily'); ?>><?php esc_html_e('Twice Daily', 'zc-dmt'); ?></option>
                        <option value="daily" <?php selected($backup_schedule, 'daily'); ?>><?php esc_html_e('Daily', 'zc-dmt'); ?></option>
                    </select>
                    <p class="description"><?php esc_html_e('How often backups should be automatically created.', 'zc-dmt'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php esc_html_e('Backup Retention', 'zc-dmt'); ?></th>
                <td>
                    <input type="number" name="zc_dmt_backup_retention" id="zc_dmt_backup_retention" value="<?php echo esc_attr($backup_retention); ?>" min="1" max="365" class="small-text">
                    <?php esc_html_e('backups to keep', 'zc-dmt'); ?>
                    <p class="description"><?php esc_html_e('The number of backup files to retain for each indicator. Older backups will be automatically deleted.', 'zc-dmt'); ?></p>
                </td>
            </tr>
        </table>
        
        <?php submit_button(__('Save Changes', 'zc-dmt')); ?>
        
        <?php if ($enable_backup) : ?>
            <?php submit_button(__('Test Connection', 'zc-dmt'), 'secondary', 'test_connection'); ?>
        <?php endif; ?>
    </form>
    
    <?php if ($connection_test_result) : ?>
        <?php if (!is_wp_error($connection_test_result)) : ?>
            <div class="notice notice-success">
                <p><?php esc_html_e('Connection to Google Drive successful!', 'zc-dmt'); ?></p>
                <?php if (isset($connection_test_result['user'])) : ?>
                    <p><?php printf(esc_html__('Connected as: %s (%s)', 'zc-dmt'), esc_html($connection_test_result['user']), esc_html($connection_test_result['email'])); ?></p>
                <?php endif; ?>
            </div>
        <?php else : ?>
            <div class="notice notice-error">
                <p><?php echo esc_html($connection_test_result->get_error_message()); ?></p>
            </div>
        <?php endif; ?>
    <?php endif; ?>
    
    <div class="zc-backup-info">
        <h2><?php esc_html_e('Backup Information', 'zc-dmt'); ?></h2>
        <p><?php esc_html_e('Google Drive backups provide a fallback mechanism for your indicator data. If a live data source becomes unavailable, the system can use the most recent backup from Google Drive to display charts.', 'zc-dmt'); ?></p>
        
        <h3><?php esc_html_e('How It Works', 'zc-dmt'); ?></h3>
        <ul>
            <li><?php esc_html_e('When enabled, the system automatically creates backups of your indicator data and uploads them to your Google Drive.', 'zc-dmt'); ?></li>
            <li><?php esc_html_e('Backups are created according to your schedule (hourly, daily, etc.).', 'zc-dmt'); ?></li>
            <li><?php esc_html_e('Only the most recent N backups are kept (as configured in retention settings).', 'zc-dmt'); ?></li>
            <li><?php esc_html_e('If a live data fetch fails, the system automatically attempts to retrieve data from the latest backup.', 'zc-dmt'); ?></li>
        </ul>
        
        <h3><?php esc_html_e('Setup Instructions', 'zc-dmt'); ?></h3>
        <ol>
            <li><?php esc_html_e('Create a Google Cloud Platform project and enable the Drive API.', 'zc-dmt'); ?></li>
            <li><?php esc_html_e('Create OAuth credentials (Client ID and Client Secret) in the Google Cloud Console.', 'zc-dmt'); ?></li>
            <li><?php esc_html_e('Enter your Client ID and Client Secret in the fields above.', 'zc-dmt'); ?></li>
            <li><?php esc_html_e('Obtain a refresh token through the OAuth flow and enter it above.', 'zc-dmt'); ?></li>
            <li><?php esc_html_e('Create a folder in Google Drive and share it with the service account email address.', 'zc-dmt'); ?></li>
            <li><?php esc_html_e('Copy the folder ID from the Google Drive URL and paste it in the "Google Drive Folder ID" field.', 'zc-dmt'); ?></li>
        </ol>
    </div>
</div>
