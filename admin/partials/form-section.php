<?php
/**
 * ZC DMT Admin Form Section Partial
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Check if section data is provided
if (!isset($section_title) && !isset($section_content)) {
    return;
}
?>

<div class="zc-form-section">
    <?php if (!empty($section_title)) : ?>
        <h2><?php echo esc_html($section_title); ?></h2>
    <?php endif; ?>
    
    <?php if (!empty($section_content)) : ?>
        <?php echo $section_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    <?php endif; ?>
</div>
