<?php
/**
 * ZC DMT Add Indicator Page
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Check user capabilities
if (!current_user_can('manage_options')) {
    return;
}

// Verify nonce for security
if (isset($_POST['zc_dmt_add_indicator_nonce']) && wp_verify_nonce($_POST['zc_dmt_add_indicator_nonce'], 'zc_dmt_add_indicator')) {
    // Process form submission
    $indicator_name = isset($_POST['indicator_name']) ? sanitize_text_field($_POST['indicator_name']) : '';
    $indicator_slug = isset($_POST['indicator_slug']) ? sanitize_key($_POST['indicator_slug']) : '';
    $indicator_description = isset($_POST['indicator_description']) ? sanitize_textarea_field($_POST['indicator_description']) : '';
    $source_type = isset($_POST['source_type']) ? sanitize_text_field($_POST['source_type']) : '';
    $frequency = isset($_POST['frequency']) ? sanitize_text_field($_POST['frequency']) : 'daily';
    $units = isset($_POST['units']) ? sanitize_text_field($_POST['units']) : '';
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Source configuration fields (will vary by source type)
    $source_config = array();
    if (!empty($_POST['source_config']) && is_array($_POST['source_config'])) {
        foreach ($_POST['source_config'] as $key => $value) {
            $source_config[sanitize_key($key)] = sanitize_text_field($value);
        }
    }
    
    // Validate required fields
    if (empty($indicator_name)) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>' . esc_html__('Indicator name is required.', 'zc-dmt') . '</p></div>';
        });
    } elseif (empty($source_type)) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>' . esc_html__('Source type is required.', 'zc-dmt') . '</p></div>';
        });
    } else {
        // Prepare indicator data
        $indicator_data = array(
            'name' => $indicator_name,
            'slug' => $indicator_slug,
            'description' => $indicator_description,
            'source' => $source_type,
            'source_config' => $source_config,
            'frequency' => $frequency,
            'unit' => $units,
            'is_active' => $is_active
        );
        
        // Add indicator
        if (class_exists('ZC_DMT_Indicators')) {
            $indicators = new ZC_DMT_Indicators();
            $result = $indicators->add_indicator($indicator_data);
            
            if (is_wp_error($result)) {
                add_action('admin_notices', function() use ($result) {
                    echo '<div class="notice notice-error"><p>' . esc_html($result->get_error_message()) . '</p></div>';
                });
            } else {
                // Redirect to indicators list with success message
                wp_redirect(admin_url('admin.php?page=zc-dmt-indicators&added=1'));
                exit;
            }
        }
    }
    
    // Set default values from form submission
    $default_name = $indicator_name;
    $default_slug = $indicator_slug;
    $default_description = $indicator_description;
    $default_source_type = $source_type;
    $default_frequency = $frequency;
    $default_units = $units;
    $default_is_active = $is_active;
    $default_source_config = $source_config;
} else {
    // Default values
    $default_name = '';
    $default_slug = '';
    $default_description = '';
    $default_source_type = '';
    $default_frequency = 'daily';
    $default_units = '';
    $default_is_active = 1;
    $default_source_config = array();
}

// Get available data sources
$source_types = array();
if (class_exists('ZC_DMT_Data_Sources')) {
    $data_sources = new ZC_DMT_Data_Sources();
    $source_types = $data_sources->get_sources();
}
?>

<div class="wrap zc-dmt-add-indicator">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <a href="<?php echo esc_url(admin_url('admin.php?page=zc-dmt-indicators')); ?>" class="page-title-action">
        <?php _e('← Back to Indicators', 'zc-dmt'); ?>
    </a>
    
    <?php settings_errors(); ?>
    
    <form method="post" action="">
        <?php wp_nonce_field('zc_dmt_add_indicator', 'zc_dmt_add_indicator_nonce'); ?>
        
        <div class="zc-indicator-form">
            <!-- Indicator Details -->
            <div class="zc-form-section">
                <h2><?php _e('Indicator Details', 'zc-dmt'); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Name', 'zc-dmt'); ?></th>
                        <td>
                            <input type="text" name="indicator_name" id="indicator_name" 
                                   value="<?php echo esc_attr($default_name); ?>" class="regular-text" required>
                            <p class="description"><?php _e('Enter a descriptive name for this indicator.', 'zc-dmt'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Slug', 'zc-dmt'); ?></th>
                        <td>
                            <input type="text" name="indicator_slug" id="indicator_slug" 
                                   value="<?php echo esc_attr($default_slug); ?>" class="regular-text">
                            <p class="description"><?php _e('A unique identifier for this indicator. Used in shortcodes.', 'zc-dmt'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Description', 'zc-dmt'); ?></th>
                        <td>
                            <textarea name="indicator_description" id="indicator_description" 
                                      class="large-text" rows="3"><?php echo esc_textarea($default_description); ?></textarea>
                            <p class="description"><?php _e('A brief description of this indicator.', 'zc-dmt'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Source Type', 'zc-dmt'); ?></th>
                        <td>
                            <select name="source_type" id="source_type" required>
                                <option value=""><?php _e('Select Source Type', 'zc-dmt'); ?></option>
                                <?php foreach ($source_types as $key => $source) : ?>
                                    <option value="<?php echo esc_attr($key); ?>" 
                                            <?php selected($default_source_type, $key); ?>>
                                        <?php echo esc_html($source['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php _e('Select the data source for this indicator.', 'zc-dmt'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Frequency', 'zc-dmt'); ?></th>
                        <td>
                            <select name="frequency" id="frequency">
                                <option value="daily" <?php selected($default_frequency, 'daily'); ?>>
                                    <?php _e('Daily', 'zc-dmt'); ?>
                                </option>
                                <option value="weekly" <?php selected($default_frequency, 'weekly'); ?>>
                                    <?php _e('Weekly', 'zc-dmt'); ?>
                                </option>
                                <option value="monthly" <?php selected($default_frequency, 'monthly'); ?>>
                                    <?php _e('Monthly', 'zc-dmt'); ?>
                                </option>
                                <option value="quarterly" <?php selected($default_frequency, 'quarterly'); ?>>
                                    <?php _e('Quarterly', 'zc-dmt'); ?>
                                </option>
                                <option value="yearly" <?php selected($default_frequency, 'yearly'); ?>>
                                    <?php _e('Yearly', 'zc-dmt'); ?>
                                </option>
                            </select>
                            <p class="description"><?php _e('How often this indicator is updated.', 'zc-dmt'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Units', 'zc-dmt'); ?></th>
                        <td>
                            <input type="text" name="units" id="units" 
                                   value="<?php echo esc_attr($default_units); ?>" class="regular-text">
                            <p class="description"><?php _e('The units of measurement for this indicator (e.g., %, USD, Index).', 'zc-dmt'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Active', 'zc-dmt'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="is_active" id="is_active" 
                                       value="1" <?php checked($default_is_active, 1); ?>>
                                <?php _e('Enable this indicator', 'zc-dmt'); ?>
                            </label>
                            <p class="description"><?php _e('Uncheck to disable this indicator without deleting it.', 'zc-dmt'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Source Configuration -->
            <div class="zc-form-section">
                <h2><?php _e('Source Configuration', 'zc-dmt'); ?></h2>
                
                <div class="zc-source-config" id="source-config-container">
                    <?php if (!empty($default_source_type) && isset($source_types[$default_source_type])) : ?>
                        <?php 
                        $config_fields = isset($source_types[$default_source_type]['config_fields']) ? 
                                         $source_types[$default_source_type]['config_fields'] : array();
                        if (!empty($config_fields)) : ?>
                            <table class="form-table">
                                <?php foreach ($config_fields as $field) : ?>
                                    <tr>
                                        <th scope="row"><?php echo esc_html(ucwords(str_replace('_', ' ', $field))); ?></th>
                                        <td>
                                            <input type="text" 
                                                   name="source_config[<?php echo esc_attr($field); ?>]" 
                                                   id="source_config_<?php echo esc_attr($field); ?>" 
                                                   value="<?php echo isset($default_source_config[$field]) ? 
                                                           esc_attr($default_source_config[$field]) : ''; ?>" 
                                                   class="regular-text">
                                            <p class="description">
                                                <?php printf(__('Enter the %s for this data source.', 'zc-dmt'), 
                                                           esc_html(ucwords(str_replace('_', ' ', $field)))); ?>
                                            </p>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </table>
                        <?php else : ?>
                            <p><?php _e('No additional configuration required for this source type.', 'zc-dmt'); ?></p>
                        <?php endif; ?>
                    <?php else : ?>
                        <p><?php _e('Select a source type to configure settings.', 'zc-dmt'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <?php submit_button(__('Save Indicator', 'zc-dmt')); ?>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    // Handle source type change
    $('#source_type').on('change', function() {
        var sourceType = $(this).val();
        var nonce = '<?php echo esc_js(wp_create_nonce('zc_dmt_source_config')); ?>';
        
        if (sourceType) {
            // In a real implementation, you would make an AJAX call to get the source configuration fields
            // For now, we'll just show a message
            $('#source-config-container').html('<p><?php _e('Loading configuration fields...', 'zc-dmt'); ?></p>');
            
            // Simulate loading (in a real implementation, this would be an AJAX call)
            setTimeout(function() {
                $('#source-config-container').html('<p><?php _e('Configuration fields would be loaded here based on the selected source type.', 'zc-dmt'); ?></p>');
            }, 500);
        } else {
            $('#source-config-container').html('<p><?php _e('Select a source type to configure settings.', 'zc-dmt'); ?></p>');
        }
    });
    
    // Auto-generate slug from name
    $('#indicator_name').on('blur', function() {
        var name = $(this).val();
        var slug = name.toLowerCase()
                       .replace(/[^a-z0-9\s-]/g, '')
                       .replace(/\s+/g, '-')
                       .replace(/-+/g, '-')
                       .trim('-');
        if (!$('#indicator_slug').val()) {
            $('#indicator_slug').val(slug);
        }
    });
});
</script>
