<?php
/**
 * ZC DMT Admin Chart Preview Wrapper Partial
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Check if indicator ID is provided
if (!isset($indicator_id)) {
    return;
}

// Generate unique ID for chart container
$chart_id = 'zc-chart-preview-' . uniqid();

// Get default chart library
$default_library = get_option('zc_dmt_default_chart_library', 'chartjs');

// Enqueue chart library if not already enqueued
if ($default_library === 'chartjs' && !wp_script_is('chartjs', 'enqueued')) {
    wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '3.9.1', true);
} elseif ($default_library === 'highcharts' && !wp_script_is('highcharts', 'enqueued')) {
    wp_enqueue_script('highcharts', 'https://code.highcharts.com/highcharts.js', array(), '10.3.3', true);
}

// Enqueue admin chart script if not already enqueued
if (!wp_script_is('zc-dmt-admin-charts', 'enqueued')) {
    wp_enqueue_script('zc-dmt-admin-charts', ZC_DMT_PLUGIN_URL . 'assets/js/admin-charts.js', array('jquery'), ZC_DMT_VERSION, true);
    
    // Localize script with AJAX URL
    wp_localize_script('zc-dmt-admin-charts', 'zc_dmt_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('zc_dmt_admin_charts')
    ));
}
?>

<div class="zc-chart-preview-container">
    <div class="zc-chart-preview-header">
        <h3><?php _e('Chart Preview', 'zc-dmt'); ?></h3>
        <button type="button" class="button zc-chart-preview-trigger" data-indicator-id="<?php echo esc_attr($indicator_id); ?>">
            <?php _e('Load Preview', 'zc-dmt'); ?>
        </button>
    </div>
    
    <div class="zc-chart-preview-content" style="margin-top: 15px;">
        <div class="zc-chart-wrapper" style="height: 300px; display: none;">
            <canvas id="<?php echo esc_attr($chart_id); ?>"></canvas>
        </div>
        
        <div class="zc-chart-loading" style="display: none; text-align: center; padding: 20px;">
            <p><?php _e('Loading chart data...', 'zc-dmt'); ?></p>
        </div>
        
        <div class="zc-chart-error" style="display: none; padding: 20px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; color: #721c24;">
            <p><?php _e('Error loading chart. Please try again.', 'zc-dmt'); ?></p>
        </div>
    </div>
</div>
