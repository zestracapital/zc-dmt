<?php
/**
 * ZC DMT Delete Calculation Page
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
if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'zc_dmt_delete_calculation_' . $calculation_id)) {
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

// Handle deletion
if (isset($_POST['confirm_delete']) && isset($_POST['zc_dmt_delete_calculation_nonce'])) {
    // Verify nonce
    if (!wp_verify_nonce($_POST['zc_dmt_delete_calculation_nonce'], 'zc_dmt_delete_calculation')) {
        wp_die(__('Security check failed', 'zc-dmt'));
    }
    
    // Delete calculation
    $result = $calculations->delete_calculation($calculation_id);
    
    if (is_wp_error($result)) {
        add_action('admin_notices', function() use ($result) {
            echo '<div class="notice notice-error"><p>' . esc_html($result->get_error_message()) . '</p></div>';
        });
    } else {
        // Redirect to calculations list with success message
        wp_redirect(admin_url('admin.php?page=zc-dmt-calculations&deleted=1'));
        exit;
    }
}
?>

<div class="wrap zc-dmt-delete-calculation">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <a href="<?php echo esc_url(admin_url('admin.php?page=zc-dmt-calculations')); ?>" class="page-title-action">
        <?php _e('← Back to Calculations', 'zc-dmt'); ?>
    </a>
    
    <?php settings_errors(); ?>
    
    <div class="zc-delete-confirmation">
        <h2><?php _e('Confirm Deletion', 'zc-dmt'); ?></h2>
        
        <p><?php _e('Are you sure you want to delete the following calculation?', 'zc-dmt'); ?></p>
        
        <div class="zc-calculation-details">
            <table class="widefat">
                <tr>
                    <th><?php _e('Name', 'zc-dmt'); ?></th>
                    <td><?php echo esc_html($calculation->name); ?></td>
                </tr>
                <tr>
                    <th><?php _e('Formula', 'zc-dmt'); ?></th>
                    <td><code><?php echo esc_html($calculation->formula); ?></code></td>
                </tr>
                <tr>
                    <th><?php _e('Description', 'zc-dmt'); ?></th>
                    <td><?php echo esc_html($calculation->description); ?></td>
                </tr>
            </table>
        </div>
        
        <form method="post" action="">
            <?php wp_nonce_field('zc_dmt_delete_calculation', 'zc_dmt_delete_calculation_nonce'); ?>
            
            <p class="submit">
                <input type="submit" name="confirm_delete" class="button button-primary button-large" 
                       value="<?php esc_attr_e('Yes, Delete This Calculation', 'zc-dmt'); ?>">
                <a href="<?php echo esc_url(admin_url('admin.php?page=zc-dmt-calculations')); ?>" class="button">
                    <?php _e('Cancel', 'zc-dmt'); ?>
                </a>
            </p>
        </form>
    </div>
</div>

<style>
.zc-delete-confirmation {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-left: 4px solid #d63638;
    padding: 20px;
    margin: 20px 0;
}

.zc-calculation-details {
    margin: 20px 0;
}

.zc-calculation-details table {
    width: 100%;
}

.zc-calculation-details th {
    text-align: left;
    width: 150px;
    vertical-align: top;
    padding: 8px 10px 8px 0;
}

.zc-calculation-details td {
    padding: 8px 10px;
}
</style>
