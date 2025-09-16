<?php
/**
 * ZC DMT Data Sources Page
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Check user capabilities
if (!current_user_can('manage_options')) {
    return;
}

// Get available data sources
$source_types = array();
if (class_exists('ZC_DMT_Data_Sources')) {
    $data_sources = new ZC_DMT_Data_Sources();
    $source_types = $data_sources->get_sources();
}

// Get configured indicators (which represent data sources)
$configured_sources = array();
if (class_exists('ZC_DMT_Indicators')) {
    $indicators = new ZC_DMT_Indicators();
    $configured_sources = $indicators->get_indicators();
}
?>

<div class="wrap zc-dmt-data-sources">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <?php settings_errors(); ?>
    
    <!-- Add New Source - Dynamic Form -->
    <div class="zc-add-source-section">
        <h2><?php esc_html_e('Add New Data Source', 'zc-dmt'); ?></h2>
        
        <div id="source-form-container">
            <!-- Initial empty state -->
            <p><?php esc_html_e('Select a source type below to configure it.', 'zc-dmt'); ?></p>
        </div>

        <!-- Source Type Selector -->
        <div class="zc-source-type-select">
            <label for="source_type"><?php esc_html_e('Source Type', 'zc-dmt'); ?></label>
            <select name="source_type" id="source_type" required>
                <option value=""><?php esc_html_e('Select Source Type', 'zc-dmt'); ?></option>
                <?php foreach ($source_types as $key => $source) : ?>
                    <option value="<?php echo esc_attr($key); ?>">
                        <?php echo esc_html($source['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="description"><?php esc_html_e('Select the type of data source you want to add', 'zc-dmt'); ?></p>
        </div>

        <!-- Submit Button (Hidden initially) -->
        <div id="submit-button-container" style="display: none; margin-top: 15px;">
            <button type="button" id="zc_dmt_add_source_button" class="button button-primary"><?php esc_html_e('Add Source', 'zc-dmt'); ?></button>
        </div>
    </div>
    
    <!-- Configured Data Sources -->
    <div class="zc-configured-sources-section">
        <h2><?php esc_html_e('Configured Data Sources', 'zc-dmt'); ?></h2>
        
        <?php if (!empty($configured_sources)) : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Name', 'zc-dmt'); ?></th>
                        <th><?php esc_html_e('Type', 'zc-dmt'); ?></th>
                        <th><?php esc_html_e('Status', 'zc-dmt'); ?></th>
                        <th><?php esc_html_e('Last Fetch', 'zc-dmt'); ?></th>
                        <th><?php esc_html_e('Actions', 'zc-dmt'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($configured_sources as $source) : ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($source->name); ?></strong>
                                <div class="row-actions">
                                    <span class="edit">
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=zc-dmt-add-source&id=' . $source->id)); ?>">
                                            <?php esc_html_e('Edit', 'zc-dmt'); ?>
                                        </a>
                                    </span>
                                    <span class="fetch">| 
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=zc-dmt-fetch-data&id=' . $source->id)); ?>">
                                            <?php esc_html_e('Fetch Data', 'zc-dmt'); ?>
                                        </a>
                                    </span>
                                    <span class="delete">| 
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=zc-dmt-delete-source&id=' . $source->id)); ?>" 
                                           onclick="return confirm('<?php esc_attr_e('Are you sure you want to delete this data source?', 'zc-dmt'); ?>')">
                                            <?php esc_html_e('Delete', 'zc-dmt'); ?>
                                        </a>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <?php 
                                if (isset($source_types[$source->source])) {
                                    echo esc_html($source_types[$source->source]['name']);
                                } else {
                                    echo esc_html($source->source);
                                }
                                ?>
                            </td>
                            <td>
                                <?php if ($source->is_active) : ?>
                                    <span class="zc-status-active"><?php esc_html_e('Active', 'zc-dmt'); ?></span>
                                <?php else : ?>
                                    <span class="zc-status-inactive"><?php esc_html_e('Inactive', 'zc-dmt'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                if (!empty($source->last_updated)) {
                                    echo esc_html($source->last_updated);
                                } else {
                                    esc_html_e('Never', 'zc-dmt');
                                }
                                ?>
                            </td>
                            <td>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=zc-dmt-test-connection&id=' . $source->id)); ?>" class="button button-small">
                                    <?php esc_html_e('Test Connection', 'zc-dmt'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <p><?php esc_html_e('No data sources configured yet.', 'zc-dmt'); ?></p>
        <?php endif; ?>
    </div>
    
    <!-- Supported Data Sources -->
    <div class="zc-supported-sources-section">
        <h2><?php esc_html_e('Supported Data Sources', 'zc-dmt'); ?></h2>
        
        <?php if (!empty($source_types)) : ?>
            <div class="zc-source-types-grid">
                <?php foreach ($source_types as $key => $source) : ?>
                    <div class="zc-source-type-card">
                        <h3><?php echo esc_html($source['name']); ?></h3>
                        <?php if (!empty($source['config_fields'])) : ?>
                            <p><strong><?php esc_html_e('Configuration fields:', 'zc-dmt'); ?></strong></p>
                            <ul>
                                <?php foreach ($source['config_fields'] as $field) : ?>
                                    <li><?php echo esc_html(ucwords(str_replace('_', ' ', $field))); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.zc-add-source-section,
.zc-configured-sources-section,
.zc-supported-sources-section {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin: 20px 0;
}

.zc-add-source-section h2,
.zc-configured-sources-section h2,
.zc-supported-sources-section h2 {
    margin-top: 0;
}

.zc-status-active {
    color: #00a32a;
    font-weight: bold;
}

.zc-status-inactive {
    color: #d63638;
    font-weight: bold;
}

.zc-source-types-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 20px;
}

.zc-source-type-card {
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 15px;
    background: #f9f9f9;
}

.zc-source-type-card h3 {
    margin-top: 0;
    color: #333;
}

.zc-source-type-card ul {
    list-style: disc;
    padding-left: 20px;
}

/* Ensure form table styles match the rest of the admin */
#source-form-container table.form-table {
    margin-top: 15px;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Listen for source type change
    $('#source_type').on('change', function() {
        var sourceType = $(this).val();
        
        if (!sourceType) {
            // Reset form if no type is selected
            $('#source-form-container').html('<p><?php esc_html_e('Select a source type below to configure it.', 'zc-dmt'); ?></p>');
            $('#submit-button-container').hide();
            return;
        }
        
        // Show loading indicator
        $('#source-form-container').html('<p><?php esc_html_e('Loading configuration...', 'zc-dmt'); ?></p>');
        $('#submit-button-container').show();

        // Fetch the source configuration via AJAX
        $.ajax({
            url: ajaxurl, // WordPress provides this global variable
            type: 'POST',
            data: {
                action: 'zc_dmt_get_source_config',
                source_type: sourceType,
                nonce: '<?php echo wp_create_nonce('zc_dmt_get_source_config_nonce'); ?>'
            },
            success: function(response) {
                // Update the container with the returned HTML
                $('#source-form-container').html(response.data.html);
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                $('#source-form-container').html('<p><?php esc_html_e('Error loading configuration. Please try again.', 'zc-dmt'); ?></p>');
            }
        });
    });

    // Handle form submission via AJAX
    $(document).on('click', '#zc_dmt_add_source_button', function(e) {
        e.preventDefault(); // Prevent default button action
        
        // Collect form data
        var formData = $('#source-form-container form').serialize();
        
        // Add action and nonce for the add-source handler
        formData += '&action=zc_dmt_add_source_from_data_sources_page';
        formData += '&nonce=<?php echo wp_create_nonce('zc_dmt_add_source_nonce'); ?>';

        // Disable button and show loading text
        var $button = $(this);
        var originalText = $button.text();
        $button.prop('disabled', true).text('<?php esc_html_e('Saving...', 'zc-dmt'); ?>');

        // Submit the form data via AJAX
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    // Redirect to sources list with success message
                    window.location.href = '<?php echo admin_url('admin.php?page=zc-dmt-data-sources&added=1'); ?>';
                } else {
                    alert('<?php esc_html_e('Error saving source:', 'zc-dmt'); ?> ' + (response.data.message || '<?php esc_html_e('Please check your inputs.', 'zc-dmt'); ?>'));
                    $button.prop('disabled', false).text(originalText);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Save Error:', status, error);
                alert('<?php esc_html_e('Error saving source. Please check your inputs.', 'zc-dmt'); ?>');
                $button.prop('disabled', false).text(originalText);
            }
        });
    });
});
</script>

<?php
// --- Add this function to your main plugin file (e.g., zc-dmt.php) ---
// This function handles the AJAX request to get the source configuration form
add_action('wp_ajax_zc_dmt_get_source_config', 'zc_dmt_ajax_get_source_config');
function zc_dmt_ajax_get_source_config() {
    // Check nonce for security
    check_ajax_referer('zc_dmt_get_source_config_nonce', 'nonce');

    // Check user capabilities
    if (!current_user_can('manage_options')) {
        wp_die(__('Unauthorized', 'zc-dmt'));
    }

    $source_type = isset($_POST['source_type']) ? sanitize_key($_POST['source_type']) : '';

    if (empty($source_type)) {
        wp_send_json_error(array('message' => __('Invalid source type.', 'zc-dmt')));
    }

    // Get available data source types
    if (!class_exists('ZC_DMT_Data_Sources')) {
        wp_send_json_error(array('message' => __('Data sources class not found.', 'zc-dmt')));
    }

    $data_sources = new ZC_DMT_Data_Sources();
    $source_info = $data_sources->get_source($source_type);

    if (!$source_info) {
        wp_send_json_error(array('message' => __('Source type not found.', 'zc-dmt')));
    }

    // --- Generate the HTML for the configuration form ---
    ob_start(); // Start output buffering

    // Hidden fields for source type and action
    echo '<input type="hidden" name="source_type" value="' . esc_attr($source_type) . '">';
    echo '<input type="hidden" name="zc_dmt_add_source_nonce" value="' . esc_attr(wp_create_nonce('zc_dmt_add_source')) . '">';

    // Source Details Section (Basic fields)
    echo '<div class="zc-form-section">';
    echo '<h3>' . sprintf(__('Add %s Source', 'zc-dmt'), esc_html($source_info['name'])) . '</h3>';
    echo '<table class="form-table">';

    echo '<tr>';
    echo '<th scope="row"><label for="source_name">' . __('Name', 'zc-dmt') . '</label></th>';
    echo '<td>';
    echo '<input type="text" name="source_name" id="source_name" value="" class="regular-text" required>';
    echo '<p class="description">' . __('Enter a descriptive name for this data source.', 'zc-dmt') . '</p>';
    echo '</td>';
    echo '</tr>';

    echo '<tr>';
    echo '<th scope="row"><label for="source_slug">' . __('Slug', 'zc-dmt') . '</label></th>';
    echo '<td>';
    echo '<input type="text" name="source_slug" id="source_slug" value="" class="regular-text">';
    echo '<p class="description">' . __('A unique identifier for this data source.', 'zc-dmt') . '</p>';
    echo '</td>';
    echo '</tr>';

    echo '<tr>';
    echo '<th scope="row"><label for="source_description">' . __('Description', 'zc-dmt') . '</label></th>';
    echo '<td>';
    echo '<textarea name="source_description" id="source_description" class="large-text" rows="3"></textarea>';
    echo '<p class="description">' . __('A brief description of this data source.', 'zc-dmt') . '</p>';
    echo '</td>';
    echo '</tr>';

    echo '<tr>';
    echo '<th scope="row">' . __('Active', 'zc-dmt') . '</th>';
    echo '<td>';
    echo '<label>';
    echo '<input type="checkbox" name="source_active" id="source_active" value="1" checked>';
    echo ' ' . __('Enable this data source', 'zc-dmt');
    echo '</label>';
    echo '<p class="description">' . __('Uncheck to disable this data source without deleting it.', 'zc-dmt') . '</p>';
    echo '</td>';
    echo '</tr>';

    echo '</table>';
    echo '</div>'; // .zc-form-section

    // Source Configuration Section (Dynamic fields)
    echo '<div class="zc-form-section">';
    echo '<h3>' . __('Source Configuration', 'zc-dmt') . '</h3>';

    if (isset($source_info['config_fields']) && is_array($source_info['config_fields']) && !empty($source_info['config_fields'])) {
        echo '<div class="zc-config-fields">';
        echo '<h4>' . __('Settings', 'zc-dmt') . '</h4>';
        echo '<table class="form-table">';

        foreach ($source_info['config_fields'] as $field) {
            echo '<tr>';
            echo '<th scope="row"><label for="' . esc_attr($source_type . '_' . $field) . '">' . esc_html(ucwords(str_replace('_', ' ', $field))) . '</label></th>';
            echo '<td>';
            echo '<input type="text" name="' . esc_attr($source_type . '_' . $field) . '" id="' . esc_attr($source_type . '_' . $field) . '" value="" class="regular-text">';
            echo '<p class="description">' . sprintf(__('Enter the %s for this data source.', 'zc-dmt'), esc_html(ucwords(str_replace('_', ' ', $field)))) . '</p>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</table>';
        echo '</div>'; // .zc-config-fields
    } else {
        echo '<p>' . __('No configuration fields available for this source type.', 'zc-dmt') . '</p>';
    }

    echo '</div>'; // .zc-form-section

    // Add the auto-slug script inline here as well for dynamic forms
    ?>
    <script>
    (function($) {
        // Re-attach the auto-generate slug listener for dynamically loaded forms
        $('#source_name').off('blur.zc_dmt').on('blur.zc_dmt', function() {
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
    })(jQuery);
    </script>
    <?php

    $form_html = ob_get_clean(); // Get the buffered content

    if ($form_html === false) {
        wp_send_json_error(array('message' => __('Failed to generate form HTML.', 'zc-dmt')));
    }

    wp_send_json_success(array('html' => $form_html));
}
// --- End of function to add to main plugin file ---
?>
