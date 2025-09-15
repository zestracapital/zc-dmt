<?php
/**
 * ZC DMT Admin Data Points Partial
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Check if indicator ID is provided
if (!isset($indicator_id)) {
    return;
}

// Pagination variables
$page = isset($_GET['data_page']) ? max(1, intval($_GET['data_page'])) : 1;
$per_page = 50;
$offset = ($page - 1) * $per_page;

// Get data points
$data_points = array();
$total_points = 0;

if (class_exists('ZC_DMT_Indicators')) {
    $indicators = new ZC_DMT_Indicators();
    
    // Get total count
    $all_data_points = $indicators->get_data_points($indicator_id);
    if (!is_wp_error($all_data_points)) {
        $total_points = count($all_data_points);
    }
    
    // Get paginated data
    $data_points = $indicators->get_data_points($indicator_id, array(
        'limit' => $per_page,
        'offset' => $offset
    ));
    
    if (is_wp_error($data_points)) {
        echo '<p>' . esc_html__('Error retrieving data points.', 'zc-dmt') . '</p>';
        return;
    }
} else {
    echo '<p>' . esc_html__('Indicators class not found.', 'zc-dmt') . '</p>';
    return;
}

$total_pages = ceil($total_points / $per_page);
?>

<div class="zc-data-points-section">
    <h3><?php _e('Data Points', 'zc-dmt'); ?></h3>
    
    <?php if (!empty($data_points)) : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Date', 'zc-dmt'); ?></th>
                    <th><?php _e('Value', 'zc-dmt'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data_points as $point) : ?>
                    <tr>
                        <td><?php echo esc_html($point->date); ?></td>
                        <td><?php echo esc_html(number_format_i18n($point->value, 4)); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php if ($total_pages > 1) : ?>
            <div class="tablenav">
                <div class="tablenav-pages">
                    <span class="displaying-num">
                        <?php printf(
                            _n('%s item', '%s items', $total_points, 'zc-dmt'),
                            number_format_i18n($total_points)
                        ); ?>
                    </span>
                    
                    <span class="pagination-links">
                        <?php if ($page > 1) : ?>
                            <a class="prev-page" href="<?php echo esc_url(add_query_arg('data_page', ($page - 1))); ?>">
                                <span aria-hidden="true">‹</span>
                            </a>
                        <?php endif; ?>
                        
                        <span class="paging-input">
                            <span class="tablenav-paging-text">
                                <?php printf(
                                    __('Page %1$s of %2$s', 'zc-dmt'),
                                    number_format_i18n($page),
                                    number_format_i18n($total_pages)
                                ); ?>
                            </span>
                        </span>
                        
                        <?php if ($page < $total_pages) : ?>
                            <a class="next-page" href="<?php echo esc_url(add_query_arg('data_page', ($page + 1))); ?>">
                                <span aria-hidden="true">›</span>
                            </a>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
        <?php endif; ?>
    <?php else : ?>
        <p><?php _e('No data points found for this indicator.', 'zc-dmt'); ?></p>
    <?php endif; ?>
</div>
