/**
 * ZC DMT Data Sources JavaScript
 * Handles data source management functionality
 */

jQuery(document).ready(function($) {
    // Test Source Connection
    $('.test-source-connection').on('click', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var sourceId = button.data('source-id');
        var originalText = button.text();
        button.text(zc_dmt_sources.testing).prop('disabled', true);
        
        var data = {
            action: 'zc_dmt_test_source_connection',
            source_id: sourceId,
            nonce: zc_dmt_sources.nonce
        };
        
        $.post(zc_dmt_sources.ajax_url, data, function(response) {
            button.text(originalText).prop('disabled', false);
            
            if (response.success) {
                alert(zc_dmt_sources.connection_success);
            } else {
                alert(zc_dmt_sources.connection_failed + ': ' + response.data);
            }
        }).fail(function() {
            button.text(originalText).prop('disabled', false);
            alert(zc_dmt_sources.connection_failed);
        });
    });
    
    // Fetch Data from Source
    $('.fetch-source-data').on('click', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var sourceId = button.data('source-id');
        var originalText = button.text();
        button.text(zc_dmt_sources.fetching).prop('disabled', true);
        
        var data = {
            action: 'zc_dmt_fetch_source_data',
            source_id: sourceId,
            nonce: zc_dmt_sources.nonce
        };
        
        $.post(zc_dmt_sources.ajax_url, data, function(response) {
            button.text(originalText).prop('disabled', false);
            
            if (response.success) {
                alert(zc_dmt_sources.fetch_success.replace('%d', response.data.count));
                location.reload();
            } else {
                alert(zc_dmt_sources.fetch_failed + ': ' + response.data);
            }
        }).fail(function() {
            button.text(originalText).prop('disabled', false);
            alert(zc_dmt_sources.fetch_failed);
        });
    });
    
    // Handle Source Type Change
    $('#source_type').on('change', function() {
        var sourceType = $(this).val();
        var configContainer = $('#source-config-container');
        
        if (!sourceType) {
            configContainer.html('<p>' + zc_dmt_sources.select_source_type + '</p>');
            return;
        }
        
        // Show loading
        configContainer.html('<p>' + zc_dmt_sources.loading_config + '</p>');
        
        // In a full implementation, this would make an AJAX call to get source-specific fields
        // For now, we'll simulate with a timeout
        setTimeout(function() {
            configContainer.html('<p>' + zc_dmt_sources.config_fields_loaded + '</p>');
        }, 500);
    });
    
    // Auto-refresh source list
    $('.refresh-sources').on('click', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var originalText = button.text();
        button.text(zc_dmt_sources.refreshing).prop('disabled', true);
        
        var data = {
            action: 'zc_dmt_refresh_sources',
            nonce: zc_dmt_sources.nonce
        };
        
        $.post(zc_dmt_sources.ajax_url, data, function(response) {
            button.text(originalText).prop('disabled', false);
            
            if (response.success) {
                alert(zc_dmt_sources.refresh_success);
                location.reload();
            } else {
                alert(zc_dmt_sources.refresh_failed + ': ' + response.data);
            }
        }).fail(function() {
            button.text(originalText).prop('disabled', false);
            alert(zc_dmt_sources.refresh_failed);
        });
    });
    
    // Toggle source status (activate/deactivate)
    $('.toggle-source-status').on('click', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var sourceId = button.data('source-id');
        var currentStatus = button.data('status');
        var newStatus = (currentStatus === 'active') ? 'inactive' : 'active';
        var originalText = button.text();
        
        button.text(zc_dmt_sources.updating).prop('disabled', true);
        
        var data = {
            action: 'zc_dmt_toggle_source_status',
            source_id: sourceId,
            status: newStatus,
            nonce: zc_dmt_sources.nonce
        };
        
        $.post(zc_dmt_sources.ajax_url, data, function(response) {
            if (response.success) {
                // Update button
                button.data('status', newStatus);
                button.removeClass('button-primary').addClass(newStatus === 'active' ? 'button-secondary' : 'button-primary');
                button.text(newStatus === 'active' ? zc_dmt_sources.deactivate : zc_dmt_sources.activate);
                button.prop('disabled', false);
                
                // Update status display
                var statusElement = $('.source-status-' + sourceId);
                statusElement.removeClass('zc-status-active zc-status-inactive');
                statusElement.addClass(newStatus === 'active' ? 'zc-status-active' : 'zc-status-inactive');
                statusElement.text(newStatus === 'active' ? zc_dmt_sources.active : zc_dmt_sources.inactive);
            } else {
                button.text(originalText).prop('disabled', false);
                alert(zc_dmt_sources.update_failed + ': ' + response.data);
            }
        }).fail(function() {
            button.text(originalText).prop('disabled', false);
            alert(zc_dmt_sources.update_failed);
        });
    });
});
