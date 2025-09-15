<?php
/**
 * ZC DMT Importer Page
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
if (isset($_POST['import_type']) && isset($_POST['zc_dmt_importer_nonce'])) {
    // Verify nonce
    if (!wp_verify_nonce($_POST['zc_dmt_importer_nonce'], 'zc_dmt_importer')) {
        wp_die(__('Security check failed', 'zc-dmt'));
    }
    
    $import_type = sanitize_key($_POST['import_type']);
    
    if ($import_type === 'csv_upload') {
        // Handle CSV upload
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['csv_file'];
            $indicator_id = intval($_POST['csv_indicator_id']);
            
            // Process CSV import
            if (class_exists('ZC_DMT_CSV_Importer')) {
                $importer = new ZC_DMT_CSV_Importer();
                $result = $importer->import_csv($file, $indicator_id);
                
                if (is_wp_error($result)) {
                    add_action('admin_notices', function() use ($result) {
                        echo '<div class="notice notice-error"><p>' . esc_html($result->get_error_message()) . '</p></div>';
                    });
                } else {
                    add_action('admin_notices', function() use ($result) {
                        echo '<div class="notice notice-success"><p>' . 
                             sprintf(__('CSV import completed successfully! %d rows processed, %d rows imported.', 'zc-dmt'), 
                                     $result['processed'], $result['imported']) . 
                             '</p></div>';
                    });
                }
            }
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>' . esc_html__('Please select a CSV file to import.', 'zc-dmt') . '</p></div>';
            });
        }
    } elseif ($import_type === 'url_fetch') {
        // Handle URL fetch
        $url = esc_url_raw($_POST['url_fetch_url']);
        $indicator_id = intval($_POST['url_indicator_id']);
        
        if (!empty($url)) {
            // Process URL import
            if (class_exists('ZC_DMT_CSV_Importer')) {
                $importer = new ZC_DMT_CSV_Importer();
                $result = $importer->import_url($url, $indicator_id);
                
                if (is_wp_error($result)) {
                    add_action('admin_notices', function() use ($result) {
                        echo '<div class="notice notice-error"><p>' . esc_html($result->get_error_message()) . '</p></div>';
                    });
                } else {
                    add_action('admin_notices', function() use ($result) {
                        echo '<div class="notice notice-success"><p>' . 
                             sprintf(__('URL import completed successfully! %d rows processed, %d rows imported.', 'zc-dmt'), 
                                     $result['processed'], $result['imported']) . 
                             '</p></div>';
                    });
                }
            }
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>' . esc_html__('Please enter a URL to import.', 'zc-dmt') . '</p></div>';
            });
        }
    }
}

// Get indicators for dropdowns
$all_indicators = array();
if (class_exists('ZC_DMT_Indicators')) {
    $indicators = new ZC_DMT_Indicators();
    $all_indicators = $indicators->get_indicators();
}
?>

<div class="wrap zc-dmt-importer">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <?php settings_errors(); ?>
    
    <div class="zc-importer-tabs">
        <ul class="zc-tabs-nav">
            <li><a href="#csv-upload" class="nav-tab nav-tab-active"><?php _e('CSV Upload', 'zc-dmt'); ?></a></li>
            <li><a href="#url-fetch" class="nav-tab"><?php _e('URL Fetch', 'zc-dmt'); ?></a></li>
        </ul>
        
        <!-- CSV Upload Tab -->
        <div id="csv-upload" class="zc-importer-tab-content">
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('zc_dmt_importer', 'zc_dmt_importer_nonce'); ?>
                <input type="hidden" name="import_type" value="csv_upload">
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('CSV File', 'zc-dmt'); ?></th>
                        <td>
                            <input type="file" name="csv_file" id="csv_file_upload" accept=".csv">
                            <p class="description"><?php _e('Select a CSV file to import.', 'zc-dmt'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Indicator', 'zc-dmt'); ?></th>
                        <td>
                            <select name="csv_indicator_id" required>
                                <option value=""><?php _e('Select Indicator', 'zc-dmt'); ?></option>
                                <?php foreach ($all_indicators as $indicator) : ?>
                                    <option value="<?php echo esc_attr($indicator->id); ?>">
                                        <?php echo esc_html($indicator->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php _e('Select the indicator to import data for.', 'zc-dmt'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(__('Import CSV', 'zc-dmt'), 'primary', 'import-csv-btn', false, array('disabled' => 'disabled')); ?>
            </form>
        </div>
        
        <!-- URL Fetch Tab -->
        <div id="url-fetch" class="zc-importer-tab-content" style="display:none;">
            <form method="post">
                <?php wp_nonce_field('zc_dmt_importer', 'zc_dmt_importer_nonce'); ?>
                <input type="hidden" name="import_type" value="url_fetch">
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('URL', 'zc-dmt'); ?></th>
                        <td>
                            <input type="url" name="url_fetch_url" id="url_fetch_url" class="regular-text" placeholder="https://example.com/data.csv">
                            <p class="description"><?php _e('Enter the URL of the CSV file to import.', 'zc-dmt'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Indicator', 'zc-dmt'); ?></th>
                        <td>
                            <select name="url_indicator_id" required>
                                <option value=""><?php _e('Select Indicator', 'zc-dmt'); ?></option>
                                <?php foreach ($all_indicators as $indicator) : ?>
                                    <option value="<?php echo esc_attr($indicator->id); ?>">
                                        <?php echo esc_html($indicator->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php _e('Select the indicator to import data for.', 'zc-dmt'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(__('Fetch and Import', 'zc-dmt'), 'primary', 'fetch-import-btn'); ?>
            </form>
        </div>
    </div>
    
    <!-- Import Progress -->
    <div class="zc-import-progress" style="display:none;">
        <h2><?php _e('Import Progress', 'zc-dmt'); ?></h2>
        <div class="zc-progress-bar">
            <div class="zc-progress-fill"></div>
        </div>
        <p class="zc-progress-text"><?php _e('Starting import...', 'zc-dmt'); ?></p>
    </div>
</div>

<style>
.zc-importer-tabs {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin: 20px 0;
}

.zc-tabs-nav {
    margin: 0 0 20px;
    padding: 0;
    list-style: none;
    border-bottom: 1px solid #ccc;
}

.zc-tabs-nav .nav-tab {
    border: 1px solid #ccc;
    border-bottom: none;
    border-radius: 4px 4px 0 0;
    background: #f1f1f1;
    margin-bottom: -1px;
    padding: 8px 12px;
    text-decoration: none;
    display: inline-block;
}

.zc-tabs-nav .nav-tab.nav-tab-active {
    background: #fff;
    border-bottom: 1px solid #fff;
    color: #000;
}

.zc-importer-tab-content {
    display: none;
}

.zc-importer-tab-content:first-child {
    display: block;
}

.zc-progress-bar {
    width: 100%;
    height: 20px;
    background: #f1f1f1;
    border-radius: 4px;
    overflow: hidden;
    margin: 10px 0;
}

.zc-progress-fill {
    height: 100%;
    background: #0073aa;
    width: 0%;
    transition: width 0.3s ease;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Tab switching
    $('.zc-importer-tabs .nav-tab').on('click', function(e) {
        e.preventDefault();
        
        // Remove active classes
        $('.zc-importer-tabs .nav-tab').removeClass('nav-tab-active');
        $('.zc-importer-tab-content').hide();
        
        // Add active class to clicked tab
        $(this).addClass('nav-tab-active');
        
        // Show corresponding content
        var target = $(this).attr('href');
        $(target).show();
    });
    
    // File upload change handler
    $('#csv_file_upload').on('change', function() {
        var fileName = $(this).val().split('\\').pop();
        if (fileName) {
            // Enable import button
            $('#import-csv-btn').prop('disabled', false);
        }
    });
});
</script>
