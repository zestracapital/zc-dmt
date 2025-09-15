<?php
/**
 * ZC DMT Admin Chart Preview AJAX Handler
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Check for AJAX request
if (!defined('DOING_AJAX') || !DOING_AJAX) {
    wp_die(__('This endpoint is for AJAX requests only.', 'zc-dmt'));
}

// Verify nonce
if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'zc_dmt_admin_charts')) {
    wp_die(__('Security check failed', 'zc-dmt'));
}

// Check user capabilities
if (!current_user_can('manage_options')) {
    wp_die(__('Insufficient permissions', 'zc-dmt'));
}

// Get indicator ID
$indicator_id = isset($_POST['indicator_id']) ? intval($_POST['indicator_id']) : 0;

if (!$indicator_id) {
    wp_send_json_error(__('Invalid indicator ID', 'zc-dmt'));
}

// Get indicator data
if (!class_exists('ZC_DMT_Indicators')) {
    wp_send_json_error(__('Indicators class not found', 'zc-dmt'));
}

$indicators = new ZC_DMT_Indicators();
$indicator = $indicators->get_indicator($indicator_id);

if (is_wp_error($indicator)) {
    wp_send_json_error($indicator->get_error_message());
}

// Get data points
$data_points = $indicators->get_data_points($indicator_id, array('limit' => 100));

if (is_wp_error($data_points)) {
    wp_send_json_error($data_points->get_error_message());
}

// Prepare chart data
$labels = array();
$values = array();

foreach ($data_points as $point) {
    $labels[] = $point->date;
    $values[] = floatval($point->value);
}

// Reverse arrays to show oldest first
$labels = array_reverse($labels);
$values = array_reverse($values);

// Prepare chart data structure
$chart_data = array(
    'labels' => $labels,
    'datasets' => array(
        array(
            'label' => $indicator->name,
            'data' => $values,
            'borderColor' => '#0073aa',
            'backgroundColor' => 'rgba(0, 115, 170, 0.1)',
            'fill' => false,
            'tension' => 0.4
        )
    )
);

wp_send_json_success($chart_data);
?>
