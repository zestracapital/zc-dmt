<?php
/**
 * ZC DMT Indicators Page
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Check user capabilities
if (!current_user_can('manage_options')) {
    return;
}

// Handle actions
$action = isset($_GET['action']) ? sanitize_key($_GET['action']) : '';
$indicator_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Handle activation/deactivation
if (in_array($action, array('activate', 'deactivate')) && $indicator_id > 0) {
    // Verify nonce
    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'zc_dmt_' . $action . '_indicator_' . $indicator_id)) {
        wp_die(__('Security check failed', 'zc-dmt'));
    }
    
    // Update indicator status
    if (class_exists('ZC_DMT_Indicators')) {
        $indicators = new ZC_DMT_Indicators();
        $indicator = $indicators->get_indicator($indicator_id);
        
        if (!is_wp_error($indicator)) {
            $update_data = array(
                'is_active' => ($action === 'activate') ? 1 : 0
            );
            
            $result = $indicators->update_indicator($indicator_id, $update_data);
            
            if (is_wp_error($result)) {
                add_action('admin_notices', function() use ($result) {
                    echo '<div class="notice notice-error"><p>' . esc_html($result->get_error_message()) . '</p></div>';
                });
            } else {
                add_action('admin_notices', function() use ($action) {
                    $message = ($action === 'activate') ? 
                        __('Indicator activated successfully.', 'zc-dmt') : 
                        __('Indicator deactivated successfully.', 'zc-dmt');
                    echo '<div class="notice notice-success"><p>' . esc_html($message) . '</p></div>';
                });
            }
        }
    }
}

// Get all indicators
$all_indicators = array();
if (class_exists('ZC_DMT_Indicators')) {
    $indicators = new ZC_DMT_Indicators();
    $all_indicators = $indicators->get_indicators();
} else {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>' . esc_html__('Indicators class not found.', 'zc-dmt') . '</p></div>';
    });
}

// Get data sources for display
$source_types = array();
if (class_exists('ZC_DMT_Data_Sources')) {
    $data_sources = new ZC_DMT_Data_Sources();
    $source_types = $data_sources->get_sources();
} else {
    $source_types = array();
}
?>

<div class="wrap zc-dmt-indicators">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <?php settings_errors(); ?>
    
    <div class="zc-indicators-header">
        <a href="<?php echo esc_url(admin_url('admin.php?page=zc-dmt-add-indicator')); ?>" class="page-title-action">
            <?php _e('Add New Indicator', 'zc-dmt'); ?>
        </a>
    </div>
    
    <?php if (!empty($all_indicators)) : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Name', 'zc-dmt'); ?></th>
                    <th><?php _e('Slug', 'zc-dmt'); ?></th>
                    <th><?php _e('Source', 'zc-dmt'); ?></th>
                    <th><?php _e('Frequency', 'zc-dmt'); ?></th>
                    <th><?php _e('Status', 'zc-dmt'); ?></th>
                    <th><?php _e('Last Updated', 'zc-dmt'); ?></th>
                    <th><?php _e('Actions', 'zc-dmt'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($all_indicators as $indicator) : ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($indicator->name); ?></strong>
                            <div class="row-actions">
                                <span class="edit">
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=zc-dmt-edit-indicator&id=' . $indicator->id)); ?>">
                                        <?php _e('Edit', 'zc-dmt'); ?>
                                    </a>
                                </span>
                                <span class="fetch">| 
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=zc-dmt-fetch-data&id=' . $indicator->id)); ?>">
                                        <?php _e('Fetch Data', 'zc-dmt'); ?>
                                    </a>
                                </span>
                                <span class="delete">| 
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=zc-dmt-delete-indicator&id=' . $indicator->id)); ?>" 
                                       onclick="return confirm('<?php esc_attr_e('Are you sure you want to delete this indicator?', 'zc-dmt'); ?>')">
                                        <?php _e('Delete', 'zc-dmt'); ?>
                                    </a>
                                </span>
                            </div>
                        </td>
                        <td><?php echo esc_html($indicator->slug); ?></td>
                        <td>
                            <?php 
                            if (isset($source_types[$indicator->source])) {
                                echo esc_html($source_types[$indicator->source]['name']);
                            } else {
                                echo esc_html($indicator->source);
                            }
                            ?>
                        </td>
                        <td><?php echo esc_html(ucfirst($indicator->frequency)); ?></td>
                        <td>
                            <?php if ($indicator->is_active) : ?>
                                <span class="zc-status-active"><?php _e('Active', 'zc-dmt'); ?></span>
                            <?php else : ?>
                                <span class="zc-status-inactive"><?php _e('Inactive', 'zc-dmt'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                            if (!empty($indicator->last_updated)) {
                                echo esc_html($indicator->last_updated);
                            } else {
                                _e('Never', 'zc-dmt');
                            }
                            ?>
                        </td>
                        <td>
                            <?php if ($indicator->is_active) : ?>
                                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=zc-dmt-indicators&action=deactivate&id=' . $indicator->id), 'zc_dmt_deactivate_indicator_' . $indicator->id)); ?>" class="button button-small">
                                    <?php _e('Deactivate', 'zc-dmt'); ?>
                                </a>
                            <?php else : ?>
                                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=zc-dmt-indicators&action=activate&id=' . $indicator->id), 'zc_dmt_activate_indicator_' . $indicator->id)); ?>" class="button button-small">
                                    <?php _e('Activate', 'zc-dmt'); ?>
                                </a>
                            <?php endif; ?>
                            
                            <a href="<?php echo esc_url(admin_url('admin.php?page=zc-dmt-data-points&indicator_id=' . $indicator->id)); ?>" class="button button-small">
                                <?php _e('View Data', 'zc-dmt'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else : ?>
        <div class="notice notice-info">
            <p><?php _e('No indicators found. Click "Add New Indicator" to create your first data indicator.', 'zc-dmt'); ?></p>
        </div>
    <?php endif; ?>
</div>

<style>
.zc-indicators-header {
    margin: 20px 0;
}

.zc-status-active {
    color: #00a32a;
    font-weight: bold;
}

.zc-status-inactive {
    color: #d63638;
    font-weight: bold;
}
</style>
