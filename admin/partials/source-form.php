<?php
/**
 * ZC DMT Admin Source Form Partial
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Check if form data is provided
if (!isset($form_data)) {
    return;
}

// Set default values
$default_name = isset($form_data['name']) ? $form_data['name'] : '';
$default_slug = isset($form_data['slug']) ? $form_data['slug'] : '';
$default_description = isset($form_data['description']) ? $form_data['description'] : '';
$default_source_type = isset($form_data['source_type']) ? $form_data['source_type'] : '';
$default_is_active = isset($form_data['is_active']) ? $form_data['is_active'] : 1;
$default_source_config = isset($form_data['source_config']) ? $form_data['source_config'] : array();

// Get available data source types
$source_types = array();
if (class_exists('ZC_DMT_Data_Sources')) {
    $data_sources = new ZC_DMT_Data_Sources();
    $source_types = $data_sources->get_sources();
} else {
    $source_types = array();
}
?>

<div class="zc-source-form">
    <!-- Source Details -->
    <div class="zc-form-section">
        <h2><?php _e('Source Details', 'zc-dmt'); ?></h2>
        
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
                           value="<?php echo esc_attr($default_slug); ?>" class="regular-text">
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
                    <p class="description"><?php _e('Select the type of data source.', 'zc-dmt'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Active', 'zc-dmt'); ?></th>
                <td>
                    <input type="checkbox" name="source_active" id="source_active" 
                           value="1" <?php checked($default_is_active, 1); ?>>
                    <label for="source_active"><?php _e('Enable this data source', 'zc-dmt'); ?></label>
                    <p class="description"><?php _e('Uncheck to disable this data source without deleting it.', 'zc-dmt'); ?></p>
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
                                           name="<?php echo esc_attr($field); ?>" 
                                           id="<?php echo esc_attr($field); ?>" 
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
