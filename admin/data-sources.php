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
                                <?php 
                                // --- Fix for Undefined property warning ---
                                // Safely check if the is_active property exists and is true
                                $is_active = isset($source->is_active) ? (int)$source->is_active : 0;
                                
                                if ($is_active === 1) : ?>
                                    <span class="zc-status-active"><?php esc_html_e('Active', 'zc-dmt'); ?></span>
                                <?php else : ?>
                                    <span class="zc-status-inactive"><?php esc_html_e('Inactive', 'zc-dmt'); ?></span>
                                <?php endif; ?>
                                <!-- --- End of fix --- -->
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
                if (response.success) {
                    // Update the container with the returned HTML
                    $('#source-form-container').html(response.data.html);
                } else {
                    $('#source-form-container').html('<p><?php esc_html_e('Error loading configuration:', 'zc-dmt'); ?> ' + (response.data.message || '<?php esc_html_e('Please try again.', 'zc-dmt'); ?>') + '</p>');
                }
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
