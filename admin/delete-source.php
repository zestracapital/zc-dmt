<?php
/**
 * ZC DMT Delete Source Page
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Check user capabilities
if (!current_user_can('manage_options')) {
    return;
}

// Get source ID
$source_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$source_id) {
    wp_die(__('Invalid source ID.', 'zc-dmt'));
}

// Verify nonce
if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'zc_dmt_delete_source_' . $source_id)) {
    wp_die(__('Security check failed', 'zc-dmt'));
}

// Get source data
$source = null;
if (class_exists('ZC_DMT_Indicators')) {
    $indicators = new ZC_DMT_Indicators();
    $source = $indicators->get_indicator($source_id);
    
    if (is_wp_error($source)) {
        wp_die($source->get_error_message());
    }
} else {
    wp_die(__('Indicators class not found.', 'zc-dmt'));
}

// Handle deletion
if (isset($_POST['confirm_delete']) && isset($_POST['zc_dmt_delete_source_nonce'])) {
    // Verify nonce
    if (!wp_verify_nonce($_POST['zc_dmt_delete_source_nonce'], 'zc_dmt_delete_source')) {
        wp_die(__('Security check failed', 'zc-dmt'));
    }
    
    // Delete source (which is actually an indicator in our implementation)
    $result = $indicators->delete_indicator($source_id);
    
    if (is_wp_error($result)) {
        add_action('admin_notices', function() use ($result) {
            echo '<div class="notice notice-error"><p>' . esc_html($result->get_error_message()) . '</p></div>';
        });
    } else {
        // Redirect to sources list with success message
        wp_redirect(admin_url('admin.php?page=zc-dmt-data-sources&deleted=1'));
        exit;
    }
}
?>

<div class="wrap zc-dmt-delete-source">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <a href="<?php echo esc_url(admin_url('admin.php?page=zc-dmt-data-sources')); ?>" class="page-title-action">
        <?php _e('← Back to Sources', 'zc-dmt'); ?>
    </a>
    
    <?php settings_errors(); ?>
    
    <div class="zc-delete-confirmation">
        <h2><?php _e('Confirm Deletion', 'zc-dmt'); ?></h2>
        
        <p><?php _e('Are you sure you want to delete the following data source? This action cannot be undone and will permanently remove all associated data.', 'zc-dmt'); ?></p>
        
        <div class="zc-source-details">
            <table class="widefat">
                <tr>
                    <th><?php _e('Name', 'zc-dmt'); ?></th>
                    <td><?php echo esc_html($source->name); ?></td>
                </tr>
                <tr>
                    <th><?php _e('Slug', 'zc-dmt'); ?></th>
                    <td><?php echo esc_html($source->slug); ?></td>
                </tr>
                <tr>
                    <th><?php _e('Source Type', 'zc-dmt'); ?></th>
                    <td><?php echo esc_html($source->source); ?></td>
                </tr>
                <tr>
                    <th><?php _e('Description', 'zc-dmt'); ?></th>
                    <td><?php echo esc_html($source->description); ?></td>
                </tr>
            </table>
        </div>
        
        <form method="post" action="">
            <?php wp_nonce_field('zc_dmt_delete_source', 'zc_dmt_delete_source_nonce'); ?>
            
            <p class="submit">
                <input type="submit" name="confirm_delete" class="button button-primary button-large" 
                       value="<?php esc_attr_e('Yes, Delete This Source', 'zc-dmt'); ?>">
                <a href="<?php echo esc_url(admin_url('admin.php?page=zc-dmt-data-sources')); ?>" class="button">
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

.zc-source-details {
    margin: 20px 0;
}

.zc-source-details table {
    width: 100%;
}

.zc-source-details th {
    text-align: left;
    width: 150px;
    vertical-align: top;
    padding: 8px 10px 8px 0;
}

.zc-source-details td {
    padding: 8px 10px;
}
</style>
