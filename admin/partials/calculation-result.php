<?php
/**
 * ZC DMT Admin Calculation Result Partial
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Check if calculation data is provided
if (!isset($calculation) || !is_array($calculation)) {
    return;
}

$result = isset($calculation['result']) ? $calculation['result'] : null;
$output_type = isset($calculation['output_type']) ? $calculation['output_type'] : 'series';
?>

<div class="zc-calculation-result">
    <h3><?php _e('Calculation Result', 'zc-dmt'); ?></h3>
    
    <?php if ($result) : ?>
        <?php if ($output_type === 'single') : ?>
            <div class="zc-single-result">
                <p class="zc-result-value">
                    <?php
                    if (is_numeric($result)) {
                        echo esc_html(number_format_i18n($result, 4));
                    } else {
                        echo esc_html($result);
                    }
                    ?>
                </p>
            </div>
        <?php elseif ($output_type === 'series') : ?>
            <?php if (is_array($result) && !empty($result)) : ?>
                <div class="zc-series-result">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Date', 'zc-dmt'); ?></th>
                                <th><?php _e('Value', 'zc-dmt'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($result as $point) : ?>
                                <tr>
                                    <td><?php echo esc_html($point['date']); ?></td>
                                    <td><?php echo esc_html(number_format_i18n($point['value'], 4)); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else : ?>
                <p><?php _e('No data available for this calculation result.', 'zc-dmt'); ?></p>
            <?php endif; ?>
        <?php else : ?>
            <div class="zc-generic-result">
                <pre><?php echo esc_html(print_r($result, true)); ?></pre>
            </div>
        <?php endif; ?>
    <?php else : ?>
        <p><?php _e('No result available. Execute the calculation to generate results.', 'zc-dmt'); ?></p>
    <?php endif; ?>
</div>
