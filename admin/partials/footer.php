<?php
/**
 * ZC DMT Admin Footer Partial
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="zc-dmt-admin-footer">
    <div class="zc-dmt-footer-content">
        <p>
            <?php 
            printf(
                __('ZC DMT Plugin v%s', 'zc-dmt'),
                esc_html(ZC_DMT_VERSION)
            ); 
            ?>
            | <?php _e('Developed by Zestra Capital', 'zc-dmt'); ?>
        </p>
    </div>
</div>
