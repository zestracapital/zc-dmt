<?php
/**
 * ZC DMT Admin Backup History Partial
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Check if indicator ID is provided
if (!isset($indicator_id)) {
    return;
}

// Get backup history
$backup_history = array();
if (class_exists('ZC_DMT_Database')) {
    $db = ZC_DMT_Database::get_instance();
    $backup_history = $db->get_backup_history($indicator_id, 10);
} else {
    echo '<p>' . esc_html__('Database class not found.', 'zc-dmt') . '</p>';
    return;
}
?>

<div class="zc-backup-history-section">
    <h3><?php _e('Backup History', 'zc-dmt'); ?></h3>
    
    <?php if (!empty($backup_history)) : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Date', 'zc-dmt'); ?></th>
                    <th><?php _e('Status', 'zc-dmt'); ?></th>
                    <th><?php _e('File Size', 'zc-dmt'); ?></th>
                    <th><?php _e('Actions', 'zc-dmt'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($backup_history as $backup) : ?>
                    <tr>
                        <td><?php echo esc_html($backup->created_at); ?></td>
                        <td>
                            <?php 
                            $status_labels = array(
                                'pending' => __('Pending', 'zc-dmt'),
                                'completed' => __('Completed', 'zc-dmt'),
                                'failed' => __('Failed', 'zc-dmt')
                            );
                            echo isset($status_labels[$backup->status]) ? 
                                esc_html($status_labels[$backup->status]) : 
                                esc_html(ucfirst($backup->status));
                            ?>
                        </td>
                        <td>
                            <?php 
                            if (!empty($backup->size)) {
                                echo esc_html(size_format($backup->size));
                            } else {
                                _e('N/A', 'zc-dmt');
                            }
                            ?>
                        </td>
                        <td>
                            <?php if (!empty($backup->drive_file_id)) : ?>
                                <a href="https://drive.google.com/file/d/<?php echo esc_attr($backup->drive_file_id); ?>/view" target="_blank" class="button button-small">
                                    <?php _e('View in Drive', 'zc-dmt'); ?>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else : ?>
        <p><?php _e('No backup history found for this indicator.', 'zc-dmt'); ?></p>
    <?php endif; ?>
</div>
