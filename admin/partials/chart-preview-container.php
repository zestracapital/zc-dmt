<?php
/**
 * ZC DMT Admin Chart Preview Container Partial
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
