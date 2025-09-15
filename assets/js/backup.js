/**
 * ZC DMT Backup JavaScript
 * Handles Google Drive backup functionality
 */

jQuery(document).ready(function($) {
    // Test Drive Connection
    $('#test-drive-connection').on('click', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var originalText = button.text();
        button.text(zc_dmt_backup.testing).prop('disabled', true);
        
        var data = {
            action: 'zc_dmt_test_drive_connection',
            nonce: zc_dmt_backup.nonce
        };
        
        $.post(zc_dmt_backup.ajax_url, data, function(response) {
            button.text(originalText).prop('disabled', false);
            
            if (response.success) {
                alert(zc_dmt_backup.connection_success);
            } else {
                alert(zc_dmt_backup.connection_failed + ': ' + response.data);
            }
        }).fail(function() {
            button.text(originalText).prop('disabled', false);
            alert(zc_dmt_backup.connection_failed);
        });
    });
    
    // Manual Backup
    $('.manual-backup-btn').on('click', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var indicatorId = button.data('indicator-id');
        var originalText = button.text();
        button.text(zc_dmt_backup.backing_up).prop('disabled', true);
        
        var data = {
            action: 'zc_dmt_manual_backup',
            indicator_id: indicatorId,
            nonce: zc_dmt_backup.nonce
        };
        
        $.post(zc_dmt_backup.ajax_url, data, function(response) {
            button.text(originalText).prop('disabled', false);
            
            if (response.success) {
                alert(zc_dmt_backup.backup_success);
                location.reload();
            } else {
                alert(zc_dmt_backup.backup_failed + ': ' + response.data);
            }
        }).fail(function() {
            button.text(originalText).prop('disabled', false);
            alert(zc_dmt_backup.backup_failed);
        });
    });
    
    // Schedule Backup
    $('#zc_dmt_backup_schedule').on('change', function() {
        var schedule = $(this).val();
        var retention = $('#zc_dmt_backup_retention').val();
        
        var data = {
            action: 'zc_dmt_schedule_backup',
            schedule: schedule,
            retention: retention,
            nonce: zc_dmt_backup.nonce
        };
        
        $.post(zc_dmt_backup.ajax_url, data, function(response) {
            if (response.success) {
                $('.backup-status').text(zc_dmt_backup.backup_scheduled + ': ' + schedule);
            } else {
                alert(zc_dmt_backup.schedule_failed + ': ' + response.data);
            }
        });
    });
    
    // Restore from Backup
    $('.restore-backup-btn').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm(zc_dmt_backup.confirm_restore)) {
            return;
        }
        
        var button = $(this);
        var fileId = button.data('file-id');
        var fileName = button.data('file-name');
        var originalText = button.text();
        button.text(zc_dmt_backup.restoring).prop('disabled', true);
        
        var data = {
            action: 'zc_dmt_restore_backup',
            file_id: fileId,
            file_name: fileName,
            nonce: zc_dmt_backup.nonce
        };
        
        $.post(zc_dmt_backup.ajax_url, data, function(response) {
            button.text(originalText).prop('disabled', false);
            
            if (response.success) {
                alert(zc_dmt_backup.restore_success);
                location.reload();
            } else {
                alert(zc_dmt_backup.restore_failed + ': ' + response.data);
            }
        }).fail(function() {
            button.text(originalText).prop('disabled', false);
            alert(zc_dmt_backup.restore_failed);
        });
    });
    
    // Delete Backup
    $('.delete-backup-btn').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm(zc_dmt_backup.confirm_delete)) {
            return;
        }
        
        var button = $(this);
        var fileId = button.data('file-id');
        var originalText = button.text();
        button.text(zc_dmt_backup.deleting).prop('disabled', true);
        
        var data = {
            action: 'zc_dmt_delete_backup',
            file_id: fileId,
            nonce: zc_dmt_backup.nonce
        };
        
        $.post(zc_dmt_backup.ajax_url, data, function(response) {
            button.text(originalText).prop('disabled', false);
            
            if (response.success) {
                alert(zc_dmt_backup.delete_success);
                button.closest('tr').fadeOut();
            } else {
                alert(zc_dmt_backup.delete_failed + ': ' + response.data);
            }
        }).fail(function() {
            button.text(originalText).prop('disabled', false);
            alert(zc_dmt_backup.delete_failed);
        });
    });
});
