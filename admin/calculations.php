<?php
/**
 * ZC DMT Calculations Page
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Check user capabilities
if (!current_user_can('manage_options')) {
    return;
}

// Handle actions
$action = isset($_GET['action']) ? sanitize_key($_GET['action']) : '';
$calculation_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Handle execution
if ($action === 'execute' && $calculation_id > 0) {
    // Verify nonce
    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'zc_dmt_execute_calculation_' . $calculation_id)) {
        wp_die(__('Security check failed', 'zc-dmt'));
    }
    
    // Execute calculation
    if (class_exists('ZC_DMT_Calculations')) {
        $calculations = new ZC_DMT_Calculations();
        $result = $calculations->execute_calculation($calculation_id);
        
        if (is_wp_error($result)) {
            add_action('admin_notices', function() use ($result) {
                echo '<div class="notice notice-error"><p>' . esc_html($result->get_error_message()) . '</p></div>';
            });
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success"><p>' . esc_html__('Calculation executed successfully.', 'zc-dmt') . '</p></div>';
            });
        }
    }
}

// Handle deletion
if ($action === 'delete' && $calculation_id > 0) {
    // Verify nonce
    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'zc_dmt_delete_calculation_' . $calculation_id)) {
        wp_die(__('Security check failed', 'zc-dmt'));
    }
    
    // Delete calculation
    if (class_exists('ZC_DMT_Calculations')) {
        $calculations = new ZC_DMT_Calculations();
        $result = $calculations->delete_calculation($calculation_id);
        
        if (is_wp_error($result)) {
            add_action('admin_notices', function() use ($result) {
                echo '<div class="notice notice-error"><p>' . esc_html($result->get_error_message()) . '</p></div>';
            });
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success"><p>' . esc_html__('Calculation deleted successfully.', 'zc-dmt') . '</p></div>';
            });
        }
    }
}

// Get all calculations
$all_calculations = array();
if (class_exists('ZC_DMT_Calculations')) {
    $calculations = new ZC_DMT_Calculations();
    $all_calculations = $calculations->get_calculations();
}
?>

<div class="wrap zc-dmt-calculations">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <?php settings_errors(); ?>
    
    <div class="zc-calculations-header">
        <a href="<?php echo esc_url(admin_url('admin.php?page=zc-dmt-add-calculation')); ?>" class="page-title-action">
            <?php _e('Add New Calculation', 'zc-dmt'); ?>
        </a>
    </div>
    
    <?php if (!empty($all_calculations)) : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Name', 'zc-dmt'); ?></th>
                    <th><?php _e('Formula', 'zc-dmt'); ?></th>
                    <th><?php _e('Output Type', 'zc-dmt'); ?></th>
                    <th><?php _e('Last Updated', 'zc-dmt'); ?></th>
                    <th><?php _e('Actions', 'zc-dmt'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($all_calculations as $calculation) : ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($calculation->name); ?></strong>
                            <div class="row-actions">
                                <span class="edit">
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=zc-dmt-edit-calculation&id=' . $calculation->id)); ?>">
                                        <?php _e('Edit', 'zc-dmt'); ?>
                                    </a>
                                </span>
                                <span class="execute">| 
                                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=zc-dmt-calculations&action=execute&id=' . $calculation->id), 'zc_dmt_execute_calculation_' . $calculation->id)); ?>">
                                        <?php _e('Execute', 'zc-dmt'); ?>
                                    </a>
                                </span>
                                <span class="delete">| 
                                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=zc-dmt-calculations&action=delete&id=' . $calculation->id), 'zc_dmt_delete_calculation_' . $calculation->id)); ?>" 
                                       onclick="return confirm('<?php esc_attr_e('Are you sure you want to delete this calculation?', 'zc-dmt'); ?>')">
                                        <?php _e('Delete', 'zc-dmt'); ?>
                                    </a>
                                </span>
                            </div>
                        </td>
                        <td>
                            <code><?php echo esc_html($calculation->formula); ?></code>
                        </td>
                        <td>
                            <?php 
                            $output_types = array(
                                'single' => __('Single Value', 'zc-dmt'),
                                'series' => __('Time Series', 'zc-dmt')
                            );
                            echo isset($output_types[$calculation->output_type]) ? esc_html($output_types[$calculation->output_type]) : esc_html($calculation->output_type);
                            ?>
                        </td>
                        <td>
                            <?php echo esc_html($calculation->last_calculated ? $calculation->last_calculated : __('Never', 'zc-dmt')); ?>
                        </td>
                        <td>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=zc-dmt-edit-calculation&id=' . $calculation->id)); ?>" class="button button-small">
                                <?php _e('Configure', 'zc-dmt'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else : ?>
        <div class="notice notice-info">
            <p><?php _e('No calculations found. Click "Add New Calculation" to create your first calculation.', 'zc-dmt'); ?></p>
        </div>
    <?php endif; ?>
    
    <div class="zc-calculations-help">
        <h2><?php _e('Supported Functions', 'zc-dmt'); ?></h2>
        
        <div class="zc-functions-grid">
            <div class="zc-function-category">
                <h3><?php _e('Basic Functions', 'zc-dmt'); ?></h3>
                <ul>
                    <li><code>SUM(indicator)</code></li>
                    <li><code>AVG(indicator)</code></li>
                    <li><code>MIN(indicator)</code></li>
                    <li><code>MAX(indicator)</code></li>
                    <li><code>COUNT(indicator)</code></li>
                </ul>
            </div>
            
            <div class="zc-function-category">
                <h3><?php _e('Technical Functions', 'zc-dmt'); ?></h3>
                <ul>
                    <li><code>ROC(indicator, periods)</code></li>
                    <li><code>MOMENTUM(indicator, periods)</code></li>
                    <li><code>STOCHASTIC_OSCILLATOR(indicator, periods)</code></li>
                </ul>
            </div>
            
            <div class="zc-function-category">
                <h3><?php _e('Advanced Functions', 'zc-dmt'); ?></h3>
                <ul>
                    <li><code>ROLLING_CORRELATION(indicator1, indicator2, window)</code></li>
                    <li><code>LINEAR_REGRESSION_SLOPE(indicator)</code></li>
                    <li><code>R_SQUARED(indicator)</code></li>
                </ul>
            </div>
            
            <div class="zc-function-category">
                <h3><?php _e('Financial Functions', 'zc-dmt'); ?></h3>
                <ul>
                    <li><code>SHARPE_RATIO(indicator, risk_free_rate)</code></li>
                    <li><code>SORTINO_RATIO(indicator, risk_free_rate)</code></li>
                    <li><code>MAX_DRAWDOWN(indicator)</code></li>
                </ul>
            </div>
            
            <div class="zc-function-category">
                <h3><?php _e('Composite Functions', 'zc-dmt'); ?></h3>
                <ul>
                    <li><code>WEIGHTED_INDEX(weights_string)</code></li>
                    <li><code>BOOLEAN_SIGNAL(condition_string)</code></li>
                </ul>
            </div>
            
            <div class="zc-function-category">
                <h3><?php _e('Seasonal Functions', 'zc-dmt'); ?></h3>
                <ul>
                    <li><code>SEASONAL_ADJUSTMENT(indicator)</code></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<style>
.zc-calculations-header {
    margin: 20px 0;
}

.zc-calculations-help {
    margin-top: 30px;
    padding: 20px;
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.zc-functions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 20px;
    margin-top: 15px;
}

.zc-function-category h3 {
    margin-top: 0;
}

.zc-function-category ul {
    list-style: none;
    padding: 0;
}

.zc-function-category li {
    margin-bottom: 5px;
    font-family: monospace;
    background: #fff;
    padding: 5px 10px;
    border-radius: 3px;
    border: 1px solid #eee;
}
</style>
