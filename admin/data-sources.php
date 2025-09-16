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

// Handle form submissions
if (isset($_POST['action']) && $_POST['action'] === 'add_source' && 
    isset($_POST['zc_dmt_add_source_nonce']) && 
    wp_verify_nonce($_POST['zc_dmt_add_source_nonce'], 'zc_dmt_add_source')) {
    
    // Process adding new source
    $source_type = isset($_POST['source_type']) ? sanitize_key($_POST['source_type']) : '';
    
    if (!empty($source_type)) {
        // Redirect to add source page with selected type
        wp_redirect(admin_url('admin.php?page=zc-dmt-add-source&type=' . $source_type));
        exit;
    }
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
    
    <!-- Add New Source -->
    <div class="zc-add-source-section">
        <h2><?php esc_html_e('Add New Data Source', 'zc-dmt'); ?></h2>
        
        <form method="post" action="">
            <?php wp_nonce_field('zc_dmt_add_source', 'zc_dmt_add_source_nonce'); ?>
            <input type="hidden" name="action" value="add_source">
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Source Type', 'zc-dmt'); ?></th>
                    <td>
                        <select name="source_type" required>
                            <option value=""><?php esc_html_e('Select Source Type', 'zc-dmt'); ?></option>
                            <?php foreach ($source_types as $key => $source) : ?>
                                <option value="<?php echo esc_attr($key); ?>">
                                    <?php echo esc_html($source['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php esc_html_e('Select the type of data source you want to add', 'zc-dmt'); ?></p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(__('Add Source', 'zc-dmt'), 'primary', 'add_source'); ?>
        </form>
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
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=zc-dmt-edit-indicator&id=' . $source->id)); ?>">
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
</style>
