<?php
/**
 * Uninstall ZC DMT
 * 
 * This file is executed when the plugin is deleted.
 */

// Exit if accessed directly
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Check if the user has the required capability
if (!current_user_can('activate_plugins')) {
    exit;
}

// Delete plugin options
delete_option('zc_dmt_db_version');
delete_option('zc_dmt_enable_drive_backup');
delete_option('zc_dmt_drive_folder_id');
delete_option('zc_dmt_backup_schedule');
delete_option('zc_dmt_backup_retention');
delete_option('zc_dmt_google_client_id');
delete_option('zc_dmt_google_client_secret');
delete_option('zc_dmt_google_refresh_token');
delete_option('zc_dmt_default_timezone');
delete_option('zc_dmt_default_date_format');
delete_option('zc_dmt_default_decimal_separator');
delete_option('zc_dmt_default_thousand_separator');
delete_option('zc_dmt_cache_duration');
delete_option('zc_dmt_chart_engine');
delete_option('zc_dmt_enable_csv_import');
delete_option('zc_dmt_enable_error_logging');
delete_option('zc_dmt_enable_email_alerts');
delete_option('zc_dmt_alert_email');
delete_option('zc_dmt_fred_api_key');
delete_option('zc_dmt_eurostat_api_key');
delete_option('zc_dmt_worldbank_api_key');

// Clear any transients
delete_transient('zc_dmt_api_keys_cache');
delete_transient('zc_dmt_indicators_cache');

// Remove custom database tables
global $wpdb;

$tables = array(
    $wpdb->prefix . 'zc_dmt_indicators',
    $wpdb->prefix . 'zc_dmt_data_points',
    $wpdb->prefix . 'zc_dmt_calculations',
    $wpdb->prefix . 'zc_dmt_api_keys',
    $wpdb->prefix . 'zc_dmt_error_logs',
    $wpdb->prefix . 'zc_dmt_backup_history'
);

foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS {$table}");
}

// Clear any scheduled events that might have been set
wp_clear_scheduled_hook('zc_dmt_scheduled_backup');
wp_clear_scheduled_hook('zc_dmt_automated_import');

// Remove any uploaded files or directories created by the plugin
$upload_dir = wp_upload_dir();
$zc_dmt_dir = $upload_dir['basedir'] . '/zc_dmt_backups';

if (file_exists($zc_dmt_dir)) {
    // Recursively remove the directory
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($zc_dmt_dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    
    foreach ($files as $fileinfo) {
        $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
        $todo($fileinfo->getRealPath());
    }
    
    rmdir($zc_dmt_dir);
}

// Remove any temporary directories
$temp_dir = $upload_dir['basedir'] . '/zc_dmt_temp';
if (file_exists($temp_dir)) {
    // Recursively remove the directory
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($temp_dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    
    foreach ($files as $fileinfo) {
        $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
        $todo($fileinfo->getRealPath());
    }
    
    rmdir($temp_dir);
}

// Flush rewrite rules to remove any custom endpoints
flush_rewrite_rules();
?>
