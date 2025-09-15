<?php
/**
 * ZC DMT Execute Calculation Page
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Check user capabilities
if (!current_user_can('manage_options')) {
    return;
}

// Get calculation ID
$calculation_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$calculation_id) {
    wp_die(__('Invalid calculation ID.', 'zc-dmt'));
}

// Verify nonce
if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'zc_dmt_execute_calculation_' . $calculation_id)) {
    wp_die(__('Security check failed', 'zc-dmt'));
}

// Get calculation data
$calculation = null;
if (class_exists('ZC_DMT_Calculations')) {
    $calculations = new ZC_DMT_Calculations();
    $calculation = $calculations->get_calculation($calculation_id);
    
    if (is_wp_error($calculation)) {
        wp_die($calculation->get_error_message());
    }
} else {
    wp_die(__('Calculations class not found.', 'zc-dmt'));
}

// Execute calculation
$result = $calculations->execute_calculation($calculation_id);

if (is_wp_error($result)) {
    add_action('admin_notices', function() use ($result) {
        echo '<div class="notice notice-error"><p>' . esc_html($result->get_error_message()) . '</p></div>';
    });
} else {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-success"><p>' . esc_html__('Calculation executed successfully.', 'zc-dmt') . '</p></div>';
    });
}

// Redirect to calculations list
wp_redirect(admin_url('admin.php?page=zc-dmt-calculations'));
exit;
?>
