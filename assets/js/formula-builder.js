/**
 * ZC DMT Formula Builder JavaScript
 * Handles formula builder functionality
 */

jQuery(document).ready(function($) {
    // Category selection
    $('.zc-category-btn').on('click', function() {
        // Remove active class from all buttons
        $('.zc-category-btn').removeClass('active');
        
        // Add active class to clicked button
        $(this).addClass('active');
        
        // Hide all function lists
        $('.zc-function-list').hide();
        
        // Show selected function list
        var category = $(this).data('category');
        $('#function-list-' + category).show();
    });
    
    // Insert function into formula
    $('.zc-function-item').on('click', function() {
        var func = $(this).data('function');
        var formula = $('#calculation_formula');
        var cursorPos = formula.prop('selectionStart');
        var text = formula.val();
        var newText = text.substring(0, cursorPos) + func + text.substring(cursorPos);
        formula.val(newText);
        formula.focus();
    });
    
    // Insert indicator into formula
    $('.zc-indicator-item').on('click', function() {
        var slug = $(this).data('slug');
        var formula = $('#calculation_formula');
        var cursorPos = formula.prop('selectionStart');
        var text = formula.val();
        var newText = text.substring(0, cursorPos) + slug + text.substring(cursorPos);
        formula.val(newText);
        formula.focus();
    });
    
    // Real-time formula validation
    $('#calculation_formula').on('input', function() {
        var formula = $(this).val();
        var validationMsg = $('#formula-validation');
        
        if (formula.length === 0) {
            validationMsg.removeClass('valid invalid').text('');
            return;
        }
        
        // Basic validation - check for balanced parentheses
        var openParen = (formula.match(/\(/g) || []).length;
        var closeParen = (formula.match(/\)/g) || []).length;
        
        if (openParen === closeParen) {
            validationMsg.removeClass('invalid').addClass('valid').text(zc_dmt_formula.valid_formula);
        } else {
            validationMsg.removeClass('valid').addClass('invalid').text(zc_dmt_formula.invalid_formula);
        }
    });
    
    // Auto-resize textarea
    $('#calculation_formula').on('input', function() {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';
    });
    
    // Toggle function palette visibility
    $('.toggle-function-palette').on('click', function(e) {
        e.preventDefault();
        $('.zc-function-palette').toggle();
        $(this).text($('.zc-function-palette').is(':visible') ? 
            zc_dmt_formula.hide_functions : zc_dmt_formula.show_functions);
    });
    
    // Toggle indicator list visibility
    $('.toggle-indicator-list').on('click', function(e) {
        e.preventDefault();
        $('.zc-indicator-list').toggle();
        $(this).text($('.zc-indicator-list').is(':visible') ? 
            zc_dmt_formula.hide_indicators : zc_dmt_formula.show_indicators);
    });
});
