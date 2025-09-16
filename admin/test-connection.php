<?php
/**
 * ZC DMT Test Connection Page
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Check user capabilities
if (!current_user_can('manage_options')) {
    return;
}

// Get source ID from URL
$source_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$source_id) {
    wp_die(__('Invalid source ID.', 'zc-dmt'));
}

// Verify nonce for security
if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'zc_dmt_test_connection_' . $source_id)) {
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

// Handle form submission for testing connection
if (isset($_POST['test_connection']) && isset($_POST['zc_dmt_test_connection_nonce'])) {
    // Verify nonce
    if (!wp_verify_nonce($_POST['zc_dmt_test_connection_nonce'], 'zc_dmt_test_connection')) {
        wp_die(__('Security check failed', 'zc-dmt'));
    }

    // Get source configuration
    $source_config = isset($source->source_config) ? $source->source_config : array();

    // Test connection using the Data Sources class
    if (class_exists('ZC_DMT_Data_Sources')) {
        $data_sources = new ZC_DMT_Data_Sources();
        
        try {
            $result = $data_sources->test_connection($source->source, $source_config);
            
            if (is_wp_error($result)) {
                add_action('admin_notices', function() use ($result) {
                    echo '<div class="notice notice-error"><p>' . esc_html($result->get_error_message()) . '</p></div>';
                });
            } else {
                // Connection successful
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-success"><p>' . esc_html__('Connection test successful!', 'zc-dmt') . '</p></div>';
                });
            }
        } catch (Exception $e) {
            add_action('admin_notices', function() use ($e) {
                echo '<div class="notice notice-error"><p>' . esc_html($e->getMessage()) . '</p></div>';
            });
        }
    } else {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>' . esc_html__('Data sources class not found.', 'zc-dmt') . '</p></div>';
        });
    }
}

// Set current values
$current_name = $source->name;
$current_slug = $source->slug;
$current_description = $source->description;
$current_source_type = $source->source;
$current_source_config = isset($source->source_config) ? $source->source_config : array();
$current_is_active = $source->is_active;

// Get available data source types
$source_types = array();
if (class_exists('ZC_DMT_Data_Sources')) {
    $data_sources = new ZC_DMT_Data_Sources();
    $source_types = $data_sources->get_sources();
}
?>

<div class="wrap zc-dmt-test-connection">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <a href="<?php echo esc_url(admin_url('admin.php?page=zc-dmt-data-sources')); ?>" class="page-title-action">
        <?php _e('← Back to Sources', 'zc-dmt'); ?>
    </a>
    
    <?php settings_errors(); ?>
    
    <div class="zc-test-connection-form">
        <!-- Source Details -->
        <div class="zc-form-section">
            <h2><?php _e('Test Connection to Source', 'zc-dmt'); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Name', 'zc-dmt'); ?></th>
                    <td><?php echo esc_html($current_name); ?></td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Slug', 'zc-dmt'); ?></th>
                    <td><?php echo esc_html($current_slug); ?></td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Description', 'zc-dmt'); ?></th>
                    <td><?php echo esc_html($current_description); ?></td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Source Type', 'zc-dmt'); ?></th>
                    <td><?php echo esc_html($current_source_type); ?></td>
                </tr>
            </table>
        </div>
        
        <!-- Source Configuration -->
        <div class="zc-form-section">
            <h2><?php _e('Source Configuration', 'zc-dmt'); ?></h2>
            
            <?php if (isset($source_types[$current_source_type]) && is_array($source_types[$current_source_type]['config_fields']) && !empty($source_types[$current_source_type]['config_fields'])) : ?>
                <div class="zc-config-fields">
                    <h3><?php _e('Settings', 'zc-dmt'); ?></h3>
                    
                    <table class="form-table">
                        <?php foreach ($source_types[$current_source_type]['config_fields'] as $field) : ?>
                            <tr>
                                <th scope="row"><?php echo esc_html(ucwords(str_replace('_', ' ', $field))); ?></th>
                                <td>
                                    <input type="text" 
                                           name="<?php echo esc_attr($current_source_type . '_' . $field); ?>" 
                                           id="<?php echo esc_attr($current_source_type . '_' . $field); ?>" 
                                           value="<?php echo isset($current_source_config[$field]) ? 
                                                       esc_attr($current_source_config[$field]) : ''; ?>" 
                                           class="regular-text">
                                    <p class="description">
                                        <?php printf(__('Enter the %s for this data source.', 'zc-dmt'), 
                                                       esc_html(ucwords(str_replace('_', ' ', $field)))); ?>
                                    </p>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            <?php else : ?>
                <p><?php _e('No configuration fields available for this source type.', 'zc-dmt'); ?></p>
            <?php endif; ?>
        </div>
        
        <!-- Test Connection Form -->
        <div class="zc-form-section">
            <h2><?php _e('Test Connection', 'zc-dmt'); ?></h2>
            
            <form method="post" action="">
                <?php wp_nonce_field('zc_dmt_test_connection', 'zc_dmt_test_connection_nonce'); ?>
                
                <p class="submit">
                    <input type="submit" name="test_connection" class="button button-primary" 
                           value="<?php esc_attr_e('Test Connection', 'zc-dmt'); ?>">
                </p>
            </form>
        </div>
    </div>
</div>

<style>
.zc-test-connection-form {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin: 20px 0;
}

.zc-form-section {
    margin-bottom: 30px;
}

.zc-form-section h2 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}
</style>
