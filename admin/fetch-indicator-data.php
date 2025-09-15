<?php
/**
 * ZC DMT Fetch Indicator Data Page
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Check user capabilities
if (!current_user_can('manage_options')) {
    return;
}

// Get indicator ID
$indicator_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$indicator_id) {
    wp_die(__('Invalid indicator ID.', 'zc-dmt'));
}

// Verify nonce
if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'zc_dmt_fetch_data_' . $indicator_id)) {
    wp_die(__('Security check failed', 'zc-dmt'));
}

// Get indicator data
$indicator = null;
if (class_exists('ZC_DMT_Indicators')) {
    $indicators = new ZC_DMT_Indicators();
    $indicator = $indicators->get_indicator($indicator_id);
    
    if (is_wp_error($indicator)) {
        wp_die($indicator->get_error_message());
    }
} else {
    wp_die(__('Indicators class not found.', 'zc-dmt'));
}

// Handle form submissions for date range
$start_date = '';
$end_date = '';
if (isset($_POST['fetch_data']) && isset($_POST['zc_dmt_fetch_data_nonce'])) {
    // Verify nonce
    if (!wp_verify_nonce($_POST['zc_dmt_fetch_data_nonce'], 'zc_dmt_fetch_data')) {
        wp_die(__('Security check failed', 'zc-dmt'));
    }
    
    // Get date range
    $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
    $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : '';
    
    // Prepare date range array
    $date_range = array();
    if (!empty($start_date)) {
        $date_range['start_date'] = $start_date;
    }
    if (!empty($end_date)) {
        $date_range['end_date'] = $end_date;
    }
    
    // Fetch data
    if (class_exists('ZC_DMT_Data_Sources')) {
        $data_sources = new ZC_DMT_Data_Sources();
        
        // Get source configuration
        $source_config = isset($indicator->source_config) ? $indicator->source_config : array();
        
        // Fetch data from source
        $result = $data_sources->fetch_data($indicator->source, $source_config, $date_range);
        
        if (is_wp_error($result)) {
            add_action('admin_notices', function() use ($result) {
                echo '<div class="notice notice-error"><p>' . esc_html($result->get_error_message()) . '</p></div>';
            });
        } else {
            // Save data points
            $saved_count = 0;
            if (is_array($result) && !empty($result)) {
                foreach ($result as $data_point) {
                    if (isset($data_point['date']) && isset($data_point['value'])) {
                        $save_result = $indicators->add_data_point($indicator_id, $data_point['date'], $data_point['value']);
                        if (!is_wp_error($save_result)) {
                            $saved_count++;
                        }
                    }
                }
                
                // Update last updated timestamp
                $indicators->update_indicator($indicator_id, array('last_updated' => current_time('mysql')));
            }
            
            add_action('admin_notices', function() use ($result, $saved_count) {
                echo '<div class="notice notice-success"><p>' . 
                     sprintf(__('Data fetched successfully! %d points retrieved, %d points saved.', 'zc-dmt'), 
                             count($result), $saved_count) . 
                     '</p></div>';
            });
        }
    } else {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>' . esc_html__('Data sources class not found.', 'zc-dmt') . '</p></div>';
        });
    }
}

// Get current data points count
$data_points_count = 0;
if (class_exists('ZC_DMT_Database')) {
    $db = ZC_DMT_Database::get_instance();
    global $wpdb;
    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$db->data_points_table} WHERE indicator_id = %d",
        $indicator_id
    ));
    $data_points_count = $count ? (int)$count : 0;
}
?>

<div class="wrap zc-dmt-fetch-data">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <a href="<?php echo esc_url(admin_url('admin.php?page=zc-dmt-indicators')); ?>" class="page-title-action">
        <?php _e('← Back to Indicators', 'zc-dmt'); ?>
    </a>
    
    <?php settings_errors(); ?>
    
    <div class="zc-fetch-data-form">
        <!-- Indicator Info -->
        <div class="zc-form-section">
            <h2><?php _e('Indicator Information', 'zc-dmt'); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Name', 'zc-dmt'); ?></th>
                    <td><?php echo esc_html($indicator->name); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Slug', 'zc-dmt'); ?></th>
                    <td><?php echo esc_html($indicator->slug); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Source Type', 'zc-dmt'); ?></th>
                    <td><?php echo esc_html($indicator->source); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Current Data Points', 'zc-dmt'); ?></th>
                    <td><?php echo esc_html(number_format_i18n($data_points_count)); ?></td>
                </tr>
            </table>
        </div>
        
        <!-- Fetch Data Form -->
        <div class="zc-form-section">
            <h2><?php _e('Fetch Data', 'zc-dmt'); ?></h2>
            
            <form method="post" action="">
                <?php wp_nonce_field('zc_dmt_fetch_data', 'zc_dmt_fetch_data_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Date Range', 'zc-dmt'); ?></th>
                        <td>
                            <label for="start_date"><?php _e('Start Date:', 'zc-dmt'); ?></label>
                            <input type="date" name="start_date" id="start_date" value="<?php echo esc_attr($start_date); ?>">
                            
                            <label for="end_date" style="margin-left: 20px;"><?php _e('End Date:', 'zc-dmt'); ?></label>
                            <input type="date" name="end_date" id="end_date" value="<?php echo esc_attr($end_date); ?>">
                            
                            <p class="description"><?php _e('Leave blank to fetch all available data.', 'zc-dmt'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(__('Fetch Data', 'zc-dmt'), 'primary', 'fetch_data'); ?>
            </form>
        </div>
        
        <!-- Current Data -->
        <div class="zc-form-section">
            <h2><?php _e('Current Data', 'zc-dmt'); ?></h2>
            
            <?php
            // Get recent data points
            $recent_data = array();
            if (class_exists('ZC_DMT_Indicators')) {
                $recent_data = $indicators->get_data_points($indicator_id, array('limit' => 10));
            }
            ?>
            
            <?php if (!empty($recent_data)) : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Date', 'zc-dmt'); ?></th>
                            <th><?php _e('Value', 'zc-dmt'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_data as $point) : ?>
                            <tr>
                                <td><?php echo esc_html($point->date); ?></td>
                                <td><?php echo esc_html(number_format_i18n($point->value)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p><?php _e('No data points found for this indicator.', 'zc-dmt'); ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.zc-fetch-data-form {
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
