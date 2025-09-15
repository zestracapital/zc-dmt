<?php
/**
 * ZC DMT Delete Indicator Page
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Check user capabilities
if (!current_user_can('manage_options')) {
    return;
}

// Get indicator ID
$indicator_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$indicator_id) {
    wp_die(__('Invalid indicator ID.', 'zc-dmt'));
}

// Verify nonce
if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'zc_dmt_delete_indicator_' . $indicator_id)) {
    wp_die(__('Security check failed', 'zc-dmt'));
}

// Get indicator data
$indicator = null;
if (class_exists('ZC_DMT_Indicators')) {
    $indicators = new ZC_DMT_Indicators();
    $indicator = $indicators->get_indicator($indicator_id);
    
    if (is_wp_error($indicator)) {
        wp_die($indicator->get_error_message());
    }
} else {
    wp_die(__('Indicators class not found.', 'zc-dmt'));
}

// Handle deletion
if (isset($_POST['confirm_delete']) && isset($_POST['zc_dmt_delete_indicator_nonce'])) {
    // Verify nonce
    if (!wp_verify_nonce($_POST['zc_dmt_delete_indicator_nonce'], 'zc_dmt_delete_indicator')) {
        wp_die(__('Security check failed', 'zc-dmt'));
    }
    
    // Delete indicator
    $result = $indicators->delete_indicator($indicator_id);
    
    if (is_wp_error($result)) {
        add_action('admin_notices', function() use ($result) {
            echo '<div class="notice notice-error"><p>' . esc_html($result->get_error_message()) . '</p></div>';
        });
    } else {
        // Redirect to indicators list with success message
        wp_redirect(admin_url('admin.php?page=zc-dmt-indicators&deleted=1'));
        exit;
    }
}
?>

<div class="wrap zc-dmt-delete-indicator">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <a href="<?php echo esc_url(admin_url('admin.php?page=zc-dmt-indicators')); ?>" class="page-title-action">
        <?php _e('← Back to Indicators', 'zc-dmt'); ?>
    </a>
    
    <?php settings_errors(); ?>
    
    <div class="zc-delete-confirmation">
        <h2><?php _e('Confirm Deletion', 'zc-dmt'); ?></h2>
        
        <p><?php _e('Are you sure you want to delete the following indicator? This action cannot be undone and will permanently remove all associated data.', 'zc-dmt'); ?></p>
        
        <div class="zc-indicator-details">
            <table class="widefat">
                <tr>
                    <th><?php _e('Name', 'zc-dmt'); ?></th>
                    <td><?php echo esc_html($indicator->name); ?></td>
                </tr>
                <tr>
                    <th><?php _e('Slug', 'zc-dmt'); ?></th>
                    <td><?php echo esc_html($indicator->slug); ?></td>
                </tr>
                <tr>
                    <th><?php _e('Source', 'zc-dmt'); ?></th>
                    <td><?php echo esc_html($indicator->source); ?></td>
                </tr>
                <tr>
                    <th><?php _e('Description', 'zc-dmt'); ?></th>
                    <td><?php echo esc_html($indicator->description); ?></td>
                </tr>
                <tr>
                    <th><?php _e('Frequency', 'zc-dmt'); ?></th>
                    <td><?php echo esc_html($indicator->frequency); ?></td>
                </tr>
                <tr>
                    <th><?php _e('Unit', 'zc-dmt'); ?></th>
                    <td><?php echo esc_html($indicator->unit); ?></td>
                </tr>
            </table>
        </div>
        
        <form method="post" action="">
            <?php wp_nonce_field('zc_dmt_delete_indicator', 'zc_dmt_delete_indicator_nonce'); ?>
            
            <p class="submit">
                <input type="submit" name="confirm_delete" class="button button-primary button-large" 
                       value="<?php esc_attr_e('Yes, Delete This Indicator', 'zc-dmt'); ?>">
                <a href="<?php echo esc_url(admin_url('admin.php?page=zc-dmt-indicators')); ?>" class="button">
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

.zc-indicator-details {
    margin: 20px 0;
}

.zc-indicator-details table {
    width: 100%;
}

.zc-indicator-details th {
    text-align: left;
    width: 150px;
    vertical-align: top;
    padding: 8px 10px 8px 0;
}

.zc-indicator-details td {
    padding: 8px 10px;
}
</style>
