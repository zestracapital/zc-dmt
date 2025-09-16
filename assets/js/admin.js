/**
 * ZC DMT Admin JavaScript
 * Handles admin interface functionality
 */

jQuery(document).ready(function($) {
    // --- ONLY run our custom scripts if we are on a ZC DMT page ---
    // Check if the body has a class indicating it's a DMT page
    // This is a common WordPress convention, or check for a specific element
    if (!$('body').hasClass('zc-dmt-admin-page') && $('#wpbody-content .wrap.zc-dmt').length === 0) {
        // If not on a DMT page, exit early to prevent conflicts
        // console.log('Not a DMT page, exiting JS early');
        return;
    }
    // console.log('Running ZC DMT specific JS');

    // Tab navigation
    $('.zc-tabs-nav a').on('click', function(e) {
        e.preventDefault();
        
        // Remove active classes
        $('.zc-tabs-nav .nav-tab').removeClass('nav-tab-active');
        $('.zc-tab-content').hide();
        
        // Add active class to clicked tab
        $(this).addClass('nav-tab-active');
        
        // Show corresponding content
        var target = $(this).attr('href');
        $(target).show();
    });
    
    // Auto-generate slug from name fields
    $('#indicator_name, #source_name, #zc_dmt_add_source #source_name_ajax, #calculation_name').on('blur', function() {
        var name = $(this).val();
        var baseId = $(this).attr('id');
        var slugId = baseId.replace('_name', '_slug');
        var slugField = $('#' + slugId);
        
        if (slugField.length && !slugField.val()) {
            var slug = name.toLowerCase()
                          .replace(/[^a-z0-9\s-]/g, '')
                          .replace(/\s+/g, '-')
                          .replace(/-+/g, '-')
                          .trim('-');
            slugField.val(slug);
        }
    });
    
    // Confirm deletions
    $('.zc-confirm-delete').on('click', function() {
        return confirm(zc_dmt_admin.confirm_delete);
    });
    
    // Handle source type change in indicator forms
    $('#source_type').on('change', function() {
        var sourceType = $(this).val();
        if (sourceType) {
            // In a full implementation, this would load source-specific fields via AJAX
            console.log('Source type changed to: ' + sourceType);
        }
    });
    
    // Toggle advanced settings
    $('.zc-toggle-advanced').on('click', function(e) {
        e.preventDefault();
        var target = $(this).data('target');
        $(target).slideToggle();
    });
    
    // Initialize tooltips
    $('.zc-tooltip').each(function() {
        var tooltipText = $(this).data('tooltip');
        if (tooltipText) {
            $(this).attr('title', tooltipText);
        }
    });
    
    // --- FIX: Scope the form submission handler to only DMT forms ---
    // Handle form submissions with loading states - ONLY for forms inside .zc-dmt-wrap or with a specific class
    $('.zc-dmt-wrap form, form.zc-dmt-form').on('submit', function() {
        var submitButton = $(this).find('input[type="submit"], button[type="submit"]');
        if (submitButton.length) {
            // Store original text
            if (!submitButton.data('original-text')) {
                submitButton.data('original-text', submitButton.val() || submitButton.text());
            }
            submitButton.prop('disabled', true).val(zc_dmt_admin.saving);
        }
    });
    
    // Handle dismissible notices
    $(document).on('click', '.notice.is-dismissible .notice-dismiss', function() {
        $(this).closest('.notice').fadeOut();
    });
    
    // Initialize any datepickers
    if ($.fn.datepicker) {
        $('.zc-datepicker').datepicker({
            dateFormat: 'yy-mm-dd'
        });
    }
    
    // Handle chart preview triggers (if present)
    $('.zc-chart-preview-trigger').on('click', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var indicatorId = button.data('indicator-id');
        
        // This would typically make an AJAX call to load chart data
        console.log('Loading chart preview for indicator ID: ' + indicatorId);
        
        // Show loading state
        button.prop('disabled', true).text(zc_dmt_admin.loading);
        
        // In a real implementation, you would:
        // 1. Make AJAX request to get chart data
        // 2. Render the chart in a container
        // 3. Handle success/error states
        
        // For now, just simulate
        setTimeout(function() {
            // Restore original text if it was stored
            var originalText = button.data('original-text') || zc_dmt_admin.load_preview;
            button.prop('disabled', false).text(originalText);
        }, 1000);
    });
});
