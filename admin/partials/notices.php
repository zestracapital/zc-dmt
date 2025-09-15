<?php
/**
 * ZC DMT Admin Notices Partial
 * Common notices for admin pages
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Display WordPress admin notices
settings_errors('zc_dmt_notices');

// Display custom notices if any
$custom_notices = get_transient('zc_dmt_admin_notices');
if ($custom_notices && is_array($custom_notices)) {
    foreach ($custom_notices as $notice) {
        echo '<div class="notice notice-' . esc_attr($notice['type']) . ' is-dismissible">';
        echo '<p>' . esc_html($notice['message']) . '</p>';
        echo '</div>';
    }
    delete_transient('zc_dmt_admin_notices');
}
?>
