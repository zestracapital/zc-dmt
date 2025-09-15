<?php
/**
 * ZC DMT Admin Form Row Partial
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Check if row data is provided
if (!isset($row_data)) {
    return;
}

$row = $row_data;
?>

<tr>
    <?php if (!empty($row['th'])) : ?>
        <th scope="row"><?php echo esc_html($row['th']); ?></th>
    <?php endif; ?>
    
    <td>
        <?php if (!empty($row['td'])) : ?>
            <?php echo $row['td']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <?php endif; ?>
        
        <?php if (!empty($row['description'])) : ?>
            <p class="description"><?php echo esc_html($row['description']); ?></p>
        <?php endif; ?>
    </td>
</tr>
