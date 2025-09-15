<?php
/**
 * ZC DMT Admin Calculation Form Partial
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
$default_formula = isset($form_data['formula']) ? $form_data['formula'] : '';
$default_output_type = isset($form_data['output_type']) ? $form_data['output_type'] : 'series';
$default_description = isset($form_data['description']) ? $form_data['description'] : '';

// Get indicators for formula builder
$all_indicators = array();
if (class_exists('ZC_DMT_Indicators')) {
    $indicators = new ZC_DMT_Indicators();
    $all_indicators = $indicators->get_indicators();
} else {
    $all_indicators = array();
}
?>

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
                <th scope="row"><?php _e('Description', 'zc-dmt'); ?></th>
                <td>
                    <textarea name="calculation_description" id="calculation_description" 
                              class="large-text" rows="3"><?php echo esc_textarea($default_description); ?></textarea>
                    <p class="description"><?php _e('A brief description of what this calculation does.', 'zc-dmt'); ?></p>
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
                    <?php if (!empty($all_indicators)) : ?>
                        <ul>
                            <?php foreach ($all_indicators as $indicator) : ?>
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
                          class="large-text code" rows="10"><?php echo esc_textarea($default_formula); ?></textarea>
                
                <p class="description"><?php _e('Click on a function to insert it into the formula. Click on an indicator to insert its slug.', 'zc-dmt'); ?></p>
            </div>
        </div>
    </div>
</div>
