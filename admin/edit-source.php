<?php
/**
 * ZC DMT Edit Source Page
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

// Get source data (sources are stored as indicators in our implementation)
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

// Handle form submissions
if (isset($_POST['submit']) && isset($_POST['zc_dmt_edit_source_nonce'])) {
    // Verify nonce
    if (!wp_verify_nonce($_POST['zc_dmt_edit_source_nonce'], 'zc_dmt_edit_source')) {
        wp_die(__('Security check failed', 'zc-dmt'));
    }
    
    // Process form data
    $name = sanitize_text_field($_POST['source_name']);
    $slug = sanitize_key($_POST['source_slug']);
    $description = sanitize_textarea_field($_POST['source_description']);
    $is_active = isset($_POST['source_active']) ? 1 : 0;
    
    // Source configuration
    $source_config = array();
    $source_type = $source->source;
    
    if (class_exists('ZC_DMT_Data_Sources')) {
        $data_sources = new ZC_DMT_Data_Sources();
        $source_info = $data_sources->get_source($source_type);
        
        if (isset($source_info['config_fields']) && is_array($source_info['config_fields'])) {
            foreach ($source_info['config_fields'] as $field) {
                if (isset($_POST[$source_type . '_' . $field])) {
                    $source_config[$field] = sanitize_text_field($_POST[$source_type . '_' . $field]);
                }
            }
        }
    }
    
    // Prepare data for update
    $source_data = array(
        'name' => $name,
        'slug' => $slug,
        'description' => $description,
        'source_config' => $source_config,
        'is_active' => $is_active
    );
    
    // Update source (which is actually an indicator in our implementation)
    $result = $indicators->update_indicator($source_id, $source_data);
    
    if (is_wp_error($result)) {
        add_action('admin_notices', function() use ($result) {
            echo '<div class="notice notice-error"><p>' . esc_html($result->get_error_message()) . '</p></div>';
        });
    } else {
        // Redirect to sources list with success message
        wp_redirect(admin_url('admin.php?page=zc-dmt-data-sources&updated=1'));
        exit;
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
$source_info = array();
if (class_exists('ZC_DMT_Data_Sources')) {
    $data_sources = new ZC_DMT_Data_Sources();
    $source_types = $data_sources->get_sources();
    $source_info = $data_sources->get_source($current_source_type);
}
?>

<div class="wrap zc-dmt-edit-source">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <a href="<?php echo esc_url(admin_url('admin.php?page=zc-dmt-data-sources')); ?>" class="page-title-action">
        <?php _e('← Back to Sources', 'zc-dmt'); ?>
    </a>
    
    <?php settings_errors(); ?>
    
    <form method="post" action="">
        <?php wp_nonce_field('zc_dmt_edit_source', 'zc_dmt_edit_source_nonce'); ?>
        <input type="hidden" name="source_id" value="<?php echo esc_attr($source_id); ?>">
        
        <div class="zc-source-form">
            <!-- Source Details -->
            <div class="zc-form-section">
                <h2><?php _e('Edit Source', 'zc-dmt'); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Name', 'zc-dmt'); ?></th>
                        <td>
                            <input type="text" name="source_name" id="source_name" 
                                   value="<?php echo esc_attr($current_name); ?>" class="regular-text" required>
                            <p class="description"><?php _e('Enter a descriptive name for this data source.', 'zc-dmt'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Slug', 'zc-dmt'); ?></th>
                        <td>
                            <input type="text" name="source_slug" id="source_slug" 
                                   value="<?php echo esc_attr($current_slug); ?>" class="regular-text">
                            <p class="description"><?php _e('A unique identifier for this data source.', 'zc-dmt'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Description', 'zc-dmt'); ?></th>
                        <td>
                            <textarea name="source_description" id="source_description" 
                                      class="large-text" rows="3"><?php echo esc_textarea($current_description); ?></textarea>
                            <p class="description"><?php _e('A brief description of this data source.', 'zc-dmt'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Active', 'zc-dmt'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="source_active" id="source_active" 
                                       value="1" <?php checked($current_is_active, 1); ?>>
                                <?php _e('Enable this data source', 'zc-dmt'); ?>
                            </label>
                            <p class="description"><?php _e('Uncheck to disable this data source without deleting it.', 'zc-dmt'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Source Configuration -->
            <div class="zc-form-section">
                <h2><?php _e('Source Configuration', 'zc-dmt'); ?></h2>
                
                <?php if (isset($source_info['config_fields']) && is_array($source_info['config_fields']) && !empty($source_info['config_fields'])) : ?>
                    <div class="zc-config-fields">
                        <h3><?php _e('Settings', 'zc-dmt'); ?></h3>
                        
                        <table class="form-table">
                            <?php foreach ($source_info['config_fields'] as $field) : ?>
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
        </div>
        
        <?php submit_button(__('Update Source', 'zc-dmt')); ?>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    // Auto-generate slug from name
    $('#source_name').on('blur', function() {
        var name = $(this).val();
        var slug = name.toLowerCase()
                       .replace(/[^a-z0-9\s-]/g, '')
                       .replace(/\s+/g, '-')
                       .replace(/-+/g, '-')
                       .trim('-');
        if (!$('#source_slug').val()) {
            $('#source_slug').val(slug);
        }
    });
});
</script>
