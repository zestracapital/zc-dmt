<?php
/**
 * ZC DMT Admin Chart Preview Script Partial
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Enqueue chart library if not already enqueued
$default_library = get_option('zc_dmt_chart_engine', 'chartjs');

if ($default_library === 'chartjs' && !wp_script_is('chartjs', 'enqueued')) {
    wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '3.9.1', true);
} elseif ($default_library === 'highcharts' && !wp_script_is('highcharts', 'enqueued')) {
    wp_enqueue_script('highcharts', 'https://code.highcharts.com/highcharts.js', array(), '10.3.3', true);
}

// Enqueue admin chart script if not already enqueued
if (!wp_script_is('zc-dmt-admin-charts', 'enqueued')) {
    wp_enqueue_script('zc-dmt-admin-charts', ZC_DMT_PLUGIN_URL . 'assets/js/admin-charts.js', array('jquery'), ZC_DMT_VERSION, true);
    
    // Localize script with AJAX URL and nonce
    wp_localize_script('zc-dmt-admin-charts', 'zc_dmt_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('zc_dmt_admin_charts')
    ));
}
?>
