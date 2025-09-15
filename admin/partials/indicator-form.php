<?php
/**
 * ZC DMT Admin Indicator Form Partial
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
$default_source = isset($form_data['source']) ? $form_data['source'] : '';
$default_frequency = isset($form_data['frequency']) ? $form_data['frequency'] : 'daily';
$default_unit = isset($form_data['unit']) ? $form_data['unit'] : '';
$default_is_active = isset($form_data['is_active']) ? $form_data['is_active'] : 1;

// Get available data sources
$source_types = array();
if (class_exists('ZC_DMT_Data_Sources')) {
    $data_sources = new ZC_DMT_Data_Sources();
    $source_types = $data_sources->get_sources();
} else {
    $source_types = array();
}
?>

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
                                    <?php selected($default_source, $key); ?>>
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
                           value="<?php echo esc_attr($default_unit); ?>" class="regular-text">
                    <p class="description"><?php _e('The units of measurement for this indicator (e.g., %, USD, Index).', 'zc-dmt'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Active', 'zc-dmt'); ?></th>
                <td>
                    <input type="checkbox" name="is_active" id="is_active" 
                           value="1" <?php checked($default_is_active, 1); ?>>
                    <label for="is_active"><?php _e('Enable this indicator', 'zc-dmt'); ?></label>
                    <p class="description"><?php _e('Uncheck to disable this indicator without deleting it.', 'zc-dmt'); ?></p>
                </td>
            </tr>
        </table>
    </div>
</div>
