<?php
/**
 * ZC DMT Add Calculation Page
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Check user capabilities
if (!current_user_can('manage_options')) {
    return;
}

// Verify nonce for security
if (isset($_POST['zc_dmt_add_calculation_nonce']) && wp_verify_nonce($_POST['zc_dmt_add_calculation_nonce'], 'zc_dmt_add_calculation')) {
    // Process form submission
    $calculation_name = isset($_POST['calculation_name']) ? sanitize_text_field($_POST['calculation_name']) : '';
    $calculation_formula = isset($_POST['calculation_formula']) ? sanitize_textarea_field($_POST['calculation_formula']) : '';
    $output_type = isset($_POST['output_type']) ? sanitize_text_field($_POST['output_type']) : 'series';
    
    // Validate required fields
    if (empty($calculation_name)) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>' . esc_html__('Calculation name is required.', 'zc-dmt') . '</p></div>';
        });
    } elseif (empty($calculation_formula)) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>' . esc_html__('Calculation formula is required.', 'zc-dmt') . '</p></div>';
        });
    } else {
        // Prepare calculation data
        $calculation_data = array(
            'name' => $calculation_name,
            'formula' => $calculation_formula,
            'output_type' => $output_type
        );
        
        // Add calculation
        if (class_exists('ZC_DMT_Calculations')) {
            $calculations = new ZC_DMT_Calculations();
            $result = $calculations->add_calculation($calculation_data);
            
            if (is_wp_error($result)) {
                add_action('admin_notices', function() use ($result) {
                    echo '<div class="notice notice-error"><p>' . esc_html($result->get_error_message()) . '</p></div>';
                });
            } else {
                // Redirect to calculations list with success message
                wp_redirect(admin_url('admin.php?page=zc-dmt-calculations&added=1'));
                exit;
            }
        }
    }
    
    // Set default values from form submission
    $default_name = $calculation_name;
    $default_formula = $calculation_formula;
    $default_output_type = $output_type;
} else {
    // Default values
    $default_name = '';
    $default_formula = '';
    $default_output_type = 'series';
}

// Get indicators for formula builder
$indicators = array();
if (class_exists('ZC_DMT_Indicators')) {
    $indicators_obj = new ZC_DMT_Indicators();
    $indicators = $indicators_obj->get_indicators();
}
?>

<div class="wrap zc-dmt-add-calculation">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <a href="<?php echo esc_url(admin_url('admin.php?page=zc-dmt-calculations')); ?>" class="page-title-action">
        <?php _e('← Back to Calculations', 'zc-dmt'); ?>
    </a>
    
    <?php settings_errors(); ?>
    
    <form method="post" action="">
        <?php wp_nonce_field('zc_dmt_add_calculation', 'zc_dmt_add_calculation_nonce'); ?>
        
        <div class="zc-calculation-form">
            <!-- Calculation Details -->
            <div class="zc-form-section">
                <h2><?php _e('Calculation Details', 'zc-dmt'); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Name', 'zc-dmt'); ?></th>
                        <td>
                            <input type="text" name="calculation_name" id="calculation_name" 
                                   value="<?php echo esc_attr($default_name); ?>" class="regular-text" required>
                            <p class="description"><?php _e('Enter a descriptive name for this calculation.', 'zc-dmt'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Output Type', 'zc-dmt'); ?></th>
                        <td>
                            <select name="output_type" id="output_type">
                                <option value="single" <?php selected($default_output_type, 'single'); ?>>
                                    <?php _e('Single Value', 'zc-dmt'); ?>
                                </option>
                                <option value="series" <?php selected($default_output_type, 'series'); ?>>
                                    <?php _e('Time Series', 'zc-dmt'); ?>
                                </option>
                            </select>
                            <p class="description"><?php _e('Select whether this calculation produces a time series or a single value.', 'zc-dmt'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Formula Builder -->
            <div class="zc-form-section">
                <h2><?php _e('Formula Builder', 'zc-dmt'); ?></h2>
                
                <div class="zc-formula-builder">
                    <div class="zc-functions-panel">
                        <h3><?php _e('Functions', 'zc-dmt'); ?></h3>
                        
                        <div class="zc-function-categories">
                            <h4><?php _e('Basic', 'zc-dmt'); ?></h4>
                            <ul>
                                <li><button type="button" class="button" data-function="SUM(indicator)">SUM(indicator)</button></li>
                                <li><button type="button" class="button" data-function="AVG(indicator)">AVG(indicator)</button></li>
                                <li><button type="button" class="button" data-function="MIN(indicator)">MIN(indicator)</button></li>
                                <li><button type="button" class="button" data-function="MAX(indicator)">MAX(indicator)</button></li>
                                <li><button type="button" class="button" data-function="COUNT(indicator)">COUNT(indicator)</button></li>
                            </ul>
                            
                            <h4><?php _e('Technical', 'zc-dmt'); ?></h4>
                            <ul>
                                <li><button type="button" class="button" data-function="ROC(indicator, periods)">ROC(indicator, periods)</button></li>
                                <li><button type="button" class="button" data-function="MOMENTUM(indicator, periods)">MOMENTUM(indicator, periods)</button></li>
                                <li><button type="button" class="button" data-function="STOCHASTIC_OSCILLATOR(indicator, periods)">STOCHASTIC_OSCILLATOR(indicator, periods)</button></li>
                            </ul>
                            
                            <h4><?php _e('Advanced', 'zc-dmt'); ?></h4>
                            <ul>
                                <li><button type="button" class="button" data-function="ROLLING_CORRELATION(indicator1, indicator2, window)">ROLLING_CORRELATION(indicator1, indicator2, window)</button></li>
                                <li><button type="button" class="button" data-function="LINEAR_REGRESSION_SLOPE(indicator)">LINEAR_REGRESSION_SLOPE(indicator)</button></li>
                                <li><button type="button" class="button" data-function="R_SQUARED(indicator)">R_SQUARED(indicator)</button></li>
                            </ul>
                            
                            <h4><?php _e('Financial', 'zc-dmt'); ?></h4>
                            <ul>
                                <li><button type="button" class="button" data-function="SHARPE_RATIO(indicator, risk_free_rate)">SHARPE_RATIO(indicator, risk_free_rate)</button></li>
                                <li><button type="button" class="button" data-function="SORTINO_RATIO(indicator, risk_free_rate)">SORTINO_RATIO(indicator, risk_free_rate)</button></li>
                                <li><button type="button" class="button" data-function="MAX_DRAWDOWN(indicator)">MAX_DRAWDOWN(indicator)</button></li>
                            </ul>
                            
                            <h4><?php _e('Composite', 'zc-dmt'); ?></h4>
                            <ul>
                                <li><button type="button" class="button" data-function="WEIGHTED_INDEX(weights_string)">WEIGHTED_INDEX(weights_string)</button></li>
                                <li><button type="button" class="button" data-function="BOOLEAN_SIGNAL(condition_string)">BOOLEAN_SIGNAL(condition_string)</button></li>
                            </ul>
                            
                            <h4><?php _e('Seasonal', 'zc-dmt'); ?></h4>
                            <ul>
                                <li><button type="button" class="button" data-function="SEASONAL_ADJUSTMENT(indicator)">SEASONAL_ADJUSTMENT(indicator)</button></li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="zc-formula-panel">
                        <h3><?php _e('Formula', 'zc-dmt'); ?></h3>
                        
                        <div class="zc-indicators-list">
                            <h4><?php _e('Available Indicators', 'zc-dmt'); ?></h4>
                            <?php if (!empty($indicators)) : ?>
                                <ul>
                                    <?php foreach ($indicators as $indicator) : ?>
                                        <li>
                                            <button type="button" class="button" 
                                                    data-indicator="<?php echo esc_attr($indicator->slug); ?>">
                                                <?php echo esc_html($indicator->name); ?> (<?php echo esc_html($indicator->slug); ?>)
                                            </button>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else : ?>
                                <p><?php _e('No indicators available. Create indicators first to use in calculations.', 'zc-dmt'); ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <textarea name="calculation_formula" id="calculation_formula" 
                                  class="large-text code" rows="10" 
                                  placeholder="<?php _e('Enter your calculation formula here...', 'zc-dmt'); ?>"><?php echo esc_textarea($default_formula); ?></textarea>
                        
                        <p class="description"><?php _e('Click on a function to insert it into the formula. Click on an indicator to insert its slug.', 'zc-dmt'); ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <?php submit_button(__('Save Calculation', 'zc-dmt')); ?>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    // Insert function into formula
    $('.zc-function-categories button').on('click', function() {
        var func = $(this).data('function');
        var formula = $('#calculation_formula');
        var cursorPos = formula.prop('selectionStart');
        var text = formula.val();
        var newText = text.substring(0, cursorPos) + func + text.substring(cursorPos);
        formula.val(newText);
        formula.focus();
    });
    
    // Insert indicator into formula
    $('.zc-indicators-list button').on('click', function() {
        var indicator = $(this).data('indicator');
        var formula = $('#calculation_formula');
        var cursorPos = formula.prop('selectionStart');
        var text = formula.val();
        var newText = text.substring(0, cursorPos) + indicator + text.substring(cursorPos);
        formula.val(newText);
        formula.focus();
    });
});
</script>
