<?php
/**
 * ZC DMT Admin Header Partial
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="zc-dmt-admin-header">
    <div class="zc-dmt-branding">
        <h1>
            <?php echo esc_html(get_admin_page_title()); ?>
            <span class="zc-dmt-version">v<?php echo esc_html(ZC_DMT_VERSION); ?></span>
        </h1>
    </div>
    
    <div class="zc-dmt-header-actions">
        <?php if (isset($header_actions) && is_array($header_actions)) : ?>
            <?php foreach ($header_actions as $action) : ?>
                <a href="<?php echo esc_url($action['url']); ?>" 
                   class="button <?php echo esc_attr(isset($action['class']) ? $action['class'] : ''); ?>">
                    <?php echo esc_html($action['label']); ?>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
