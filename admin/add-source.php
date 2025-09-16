<?php
/**
 * ZC DMT Add/Edit Source Page
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Check user capabilities
if (!current_user_can('manage_options')) {
    return;
}

// --- Get parameters from URL ---
// 'type' is for adding a new source
// 'id' is for editing an existing indicator/source
$source_type = isset($_GET['type']) ? sanitize_key($_GET['type']) : '';
$indicator_id = isset($_GET['id']) ? absint($_GET['id']) : 0;

$is_editing = !empty($indicator_id);
$page_title = $is_editing ? __('Edit Data Source', 'zc-dmt') : __('Add New Data Source', 'zc-dmt');

// --- Load existing data if editing ---
$existing_data = null;
if ($is_editing) {
    if (class_exists('ZC_DMT_Indicators')) {
        $indicators = new ZC_DMT_Indicators();
        $existing_data = $indicators->get_indicator($indicator_id);

        if (!$existing_data) {
            wp_die(__('Indicator not found.', 'zc-dmt'));
        }
        // Set source_type from existing data for editing
        $source_type = $existing_data->source;
    } else {
        wp_die(__('Indicators class not found.', 'zc-dmt'));
    }
}

// --- Get source information ---
$source_info = null;
if (!empty($source_type) && class_exists('ZC_DMT_Data_Sources')) {
    $data_sources = new ZC_DMT_Data_Sources();
    $source_info = $data_sources->get_source($source_type);

    if (!$source_info) {
        if ($is_editing) {
             // If editing and source info is missing, it might be a custom/manual source
             // We can still proceed with the existing data
             $source_info = array(
                 'name' => $existing_data->source, // Fallback name
                 'config_fields' => array() // Assume no specific config fields
             );
             // Try to decode existing config if it exists
             $existing_config = maybe_unserialize($existing_data->source_config);
             if (is_array($existing_config)) {
                 // Guess config fields from existing data keys
                 $source_info['config_fields'] = array_keys($existing_config);
             }
        } else {
            wp_die(__('Invalid source type.', 'zc-dmt'));
        }
    }
} else if (!$is_editing) {
    wp_die(__('Invalid request. Source type is required for adding.', 'zc-dmt'));
}


// --- Handle form submissions ---
if (isset($_POST['submit']) && isset($_POST['zc_dmt_add_source_nonce'])) {
    // Verify nonce
    if (!wp_verify_nonce($_POST['zc_dmt_add_source_nonce'], 'zc_dmt_add_source')) {
        wp_die(__('Security check failed', 'zc-dmt'));
    }

    // Process form data
    $name = sanitize_text_field($_POST['source_name']);
    $slug = sanitize_key($_POST['source_slug']);
    $description = sanitize_textarea_field($_POST['source_description']);
    $is_active = isset($_POST['source_active']) ? 1 : 0;

    // Source configuration
    $source_config = array();
    if (isset($source_info['config_fields']) && is_array($source_info['config_fields'])) {
        foreach ($source_info['config_fields'] as $field) {
            if (isset($_POST[$source_type . '_' . $field])) {
                // Sanitize based on field name if needed, otherwise use text field sanitization
                $source_config[$field] = sanitize_text_field($_POST[$source_type . '_' . $field]);
            }
        }
    }

    // Prepare data for insertion/update
    $source_data = array(
        'name' => $name,
        'slug' => $slug,
        'description' => $description,
        'source' => $source_type, // This is crucial for both add and edit
        'source_config' => $source_config,
        'is_active' => $is_active
    );

    // Add or update source as indicator
    if (class_exists('ZC_DMT_Indicators')) {
        $indicators = new ZC_DMT_Indicators();
        if ($is_editing) {
            $result = $indicators->update_indicator($indicator_id, $source_data);
            $redirect_param = 'updated=1';
        } else {
            $result = $indicators->add_indicator($source_data);
            $redirect_param = 'added=1';
        }

        if (is_wp_error($result)) {
            add_action('admin_notices', function() use ($result) {
                echo '<div class="notice notice-error"><p>' . esc_html($result->get_error_message()) . '</p></div>';
            });
        } else {
            // Redirect to sources list with success message
            // Use the appropriate parameter based on action
            wp_redirect(admin_url('admin.php?page=zc-dmt-data-sources&' . $redirect_param));
            exit;
        }
    } else {
         wp_die(__('Indicators class not found.', 'zc-dmt'));
    }
}

// --- Set default values for form fields ---
$default_name = $existing_data ? $existing_data->name : '';
$default_slug = $existing_data ? $existing_data->slug : '';
$default_description = $existing_data ? $existing_data->description : '';
$default_is_active = $existing_data ? $existing_data->is_active : 1; // Default to active for new sources too

// Ensure $source_info is populated for display, even if it was guessed for editing
if (!$source_info) {
    $source_info = array('name' => $source_type, 'config_fields' => array());
}
?>

<div class="wrap zc-dmt-add-source">
    <h1><?php echo esc_html($page_title); ?></h1>

    <a href="<?php echo esc_url(admin_url('admin.php?page=zc-dmt-data-sources')); ?>" class="page-title-action">
        <?php _e('← Back to Sources', 'zc-dmt'); ?>
    </a>

    <?php settings_errors(); ?>

    <form method="post" action="">
        <?php wp_nonce_field('zc_dmt_add_source', 'zc_dmt_add_source_nonce'); ?>
        <!-- Hidden field to pass the source type for new additions -->
        <?php if (!$is_editing): ?>
            <input type="hidden" name="source_type" value="<?php echo esc_attr($source_type); ?>">
        <?php endif; ?>

        <div class="zc-source-form">
            <!-- Source Details -->
            <div class="zc-form-section">
                <h2><?php echo esc_html($is_editing ? sprintf(__('Edit %s Source', 'zc-dmt'), esc_html($source_info['name'])) : sprintf(__('Add %s Source', 'zc-dmt'), esc_html($source_info['name']))); ?></h2>

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Name', 'zc-dmt'); ?></th>
                        <td>
                            <input type="text" name="source_name" id="source_name"
                                   value="<?php echo esc_attr($default_name); ?>" class="regular-text" required>
                            <p class="description"><?php _e('Enter a descriptive name for this data source.', 'zc-dmt'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('Slug', 'zc-dmt'); ?></th>
                        <td>
                            <input type="text" name="source_slug" id="source_slug"
                                   value="<?php echo esc_attr($default_slug); ?>" class="regular-text" <?php echo $is_editing ? 'readonly' : ''; ?>>
                            <p class="description"><?php _e('A unique identifier for this data source.', 'zc-dmt'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('Description', 'zc-dmt'); ?></th>
                        <td>
                            <textarea name="source_description" id="source_description"
                                      class="large-text" rows="3"><?php echo esc_textarea($default_description); ?></textarea>
                            <p class="description"><?php _e('A brief description of this data source.', 'zc-dmt'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('Active', 'zc-dmt'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="source_active" id="source_active"
                                       value="1" <?php checked($default_is_active, 1); ?>>
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
                            <?php foreach ($source_info['config_fields'] as $field) :
                                // Get the existing value if editing
                                $field_value = '';
                                if ($existing_data) {
                                    $existing_config = maybe_unserialize($existing_data->source_config);
                                    if (is_array($existing_config) && isset($existing_config[$field])) {
                                        $field_value = $existing_config[$field];
                                    }
                                }
                                // Fallback to POST value if form was submitted but had errors
                                if (isset($_POST[$source_type . '_' . $field])) {
                                     $field_value = sanitize_text_field($_POST[$source_type . '_' . $field]);
                                }
                            ?>
                                <tr>
                                    <th scope="row"><?php echo esc_html(ucwords(str_replace('_', ' ', $field))); ?></th>
                                    <td>
                                        <input type="text"
                                               name="<?php echo esc_attr($source_type . '_' . $field); ?>"
                                               id="<?php echo esc_attr($source_type . '_' . $field); ?>"
                                               value="<?php echo esc_attr($field_value); ?>" class="regular-text">
                                        <p class="description">
                                            <?php printf(__('Enter the %s for this data source.', 'zc-dmt'),
                                                       esc_html(ucwords(str_replace('_', ' ', $field)))); ?>
                                        </p>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                <?php elseif($is_editing && !empty($existing_data->source_config)): ?>
                     <!-- Handle cases where config fields might not be defined but data exists (e.g., custom sources) -->
                     <?php
                     $existing_config = maybe_unserialize($existing_data->source_config);
                     if (is_array($existing_config)):
                     ?>
                     <div class="zc-config-fields">
                         <h3><?php _e('Settings', 'zc-dmt'); ?></h3>
                         <p><em><?php _e('Configuration fields for this source type are not explicitly defined. Showing existing values.', 'zc-dmt'); ?></em></p>
                         <table class="form-table">
                             <?php foreach ($existing_config as $key => $value) : ?>
                                 <tr>
                                     <th scope="row"><?php echo esc_html(ucwords(str_replace('_', ' ', $key))); ?></th>
                                     <td>
                                         <input type="text"
                                                name="<?php echo esc_attr($source_type . '_' . $key); ?>"
                                                id="<?php echo esc_attr($source_type . '_' . $key); ?>"
                                                value="<?php echo esc_attr($value); ?>" class="regular-text">
                                         <p class="description">
                                             <?php printf(__('Existing value for %s.', 'zc-dmt'),
                                                        esc_html(ucwords(str_replace('_', ' ', $key)))); ?>
                                         </p>
                                     </td>
                                 </tr>
                             <?php endforeach; ?>
                         </table>
                     </div>
                     <?php endif; ?>
                <?php else : ?>
                    <p><?php _e('No configuration fields available for this source type.', 'zc-dmt'); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <?php submit_button($is_editing ? __('Save Source', 'zc-dmt') : __('Add Source', 'zc-dmt')); ?>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    <?php if (!$is_editing): // Only auto-generate slug for new sources ?>
    // Auto-generate slug from name (only for new sources)
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
    <?php endif; ?>
});
</script>
