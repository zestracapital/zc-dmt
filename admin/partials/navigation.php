<?php
/**
 * ZC DMT Admin Navigation Partial
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get current page
$current_page = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';

// Define menu items
$menu_items = array(
    'zc-dmt-dashboard' => array(
        'title' => __('Dashboard', 'zc-dmt'),
        'url' => admin_url('admin.php?page=zc-dmt-dashboard')
    ),
    'zc-dmt-data-sources' => array(
        'title' => __('Data Sources', 'zc-dmt'),
        'url' => admin_url('admin.php?page=zc-dmt-data-sources')
    ),
    'zc-dmt-indicators' => array(
        'title' => __('Indicators', 'zc-dmt'),
        'url' => admin_url('admin.php?page=zc-dmt-indicators')
    ),
    'zc-dmt-calculations' => array(
        'title' => __('Manual Calculations', 'zc-dmt'),
        'url' => admin_url('admin.php?page=zc-dmt-calculations')
    ),
    'zc-dmt-backup' => array(
        'title' => __('Backup Settings', 'zc-dmt'),
        'url' => admin_url('admin.php?page=zc-dmt-backup')
    ),
    'zc-dmt-error-logs' => array(
        'title' => __('Error Logs', 'zc-dmt'),
        'url' => admin_url('admin.php?page=zc-dmt-error-logs')
    ),
    'zc-dmt-settings' => array(
        'title' => __('Settings', 'zc-dmt'),
        'url' => admin_url('admin.php?page=zc-dmt-settings')
    )
);
?>

<nav class="zc-dmt-navigation">
    <ul class="zc-dmt-menu">
        <?php foreach ($menu_items as $page_slug => $item) : ?>
            <li class="zc-dmt-menu-item <?php echo $current_page === $page_slug ? 'active' : ''; ?>">
                <a href="<?php echo esc_url($item['url']); ?>"><?php echo esc_html($item['title']); ?></a>
            </li>
        <?php endforeach; ?>
    </ul>
</nav>
