/**
 * ZC DMT Importer JavaScript
 * Handles CSV and data import functionality
 */

jQuery(document).ready(function($) {
    // Tab navigation for importer
    $('.zc-tabs-nav a').on('click', function(e) {
        e.preventDefault();
        
        // Remove active classes
        $('.zc-tabs-nav .nav-tab').removeClass('nav-tab-active');
        $('.zc-importer-tab-content').hide();
        
        // Add active class to clicked tab
        $(this).addClass('nav-tab-active');
        
        // Show corresponding content
        var target = $(this).attr('href');
        $(target).show();
    });
    
    // Handle CSV file selection
    $('#csv_file_upload').on('change', function() {
        var fileName = $(this).val().split('\\').pop();
        if (fileName) {
            $('#import-csv-btn').prop('disabled', false);
        }
    });
    
    // Handle CSV import
    $('#import-csv-btn').on('click', function(e) {
        e.preventDefault();
        
        var fileInput = $('#csv_file_upload')[0];
        var indicatorId = $('#csv_indicator_id').val();
        
        if (!fileInput.files.length) {
            alert(zc_dmt_importer.select_file);
            return;
        }
        
        if (!indicatorId) {
            alert(zc_dmt_importer.select_indicator);
            return;
        }
        
        // Show progress bar
        $('.zc-import-progress').show();
        $('.zc-progress-fill').css('width', '0%');
        $('.zc-progress-text').text(zc_dmt_importer.starting_import);
        
        var file = fileInput.files[0];
        var formData = new FormData();
        formData.append('action', 'zc_dmt_import_csv');
        formData.append('csv_file', file);
        formData.append('indicator_id', indicatorId);
        formData.append('nonce', zc_dmt_importer.nonce);
        
        // Send AJAX request
        $.ajax({
            url: zc_dmt_importer.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            xhr: function() {
                var xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener("progress", function(evt) {
                    if (evt.lengthComputable) {
                        var percentComplete = evt.loaded / evt.total * 100;
                        $('.zc-progress-fill').css('width', percentComplete + '%');
                    }
                }, false);
                return xhr;
            },
            success: function(response) {
                if (response.success) {
                    $('.zc-progress-fill').css('width', '100%');
                    $('.zc-progress-text').text(zc_dmt_importer.import_complete);
                    alert(zc_dmt_importer.import_success
                        .replace('%d', response.data.processed)
                        .replace('%d', response.data.imported));
                } else {
                    $('.zc-progress-text').text(zc_dmt_importer.import_failed + ': ' + response.data);
                    alert(zc_dmt_importer.import_failed + ': ' + response.data);
                }
                
                // Hide progress after delay
                setTimeout(function() {
                    $('.zc-import-progress').hide();
                }, 3000);
            },
            error: function() {
                $('.zc-progress-text').text(zc_dmt_importer.import_failed);
                alert(zc_dmt_importer.import_failed);
                
                // Hide progress after delay
                setTimeout(function() {
                    $('.zc-import-progress').hide();
                }, 3000);
            }
        });
    });
    
    // Handle URL fetch
    $('#fetch-import-btn').on('click', function(e) {
        e.preventDefault();
        
        var url = $('#url_fetch_url').val();
        var indicatorId = $('#url_indicator_id').val();
        
        if (!url) {
            alert(zc_dmt_importer.enter_url);
            return;
        }
        
        if (!indicatorId) {
            alert(zc_dmt_importer.select_indicator);
            return;
        }
        
        // Show progress bar
        $('.zc-import-progress').show();
        $('.zc-progress-fill').css('width', '0%');
        $('.zc-progress-text').text(zc_dmt_importer.fetching_data);
        
        var data = {
            action: 'zc_dmt_fetch_url',
            url: url,
            indicator_id: indicatorId,
            nonce: zc_dmt_importer.nonce
        };
        
        // Send AJAX request
        $.post(zc_dmt_importer.ajax_url, data, function(response) {
            if (response.success) {
                $('.zc-progress-fill').css('width', '100%');
                $('.zc-progress-text').text(zc_dmt_importer.import_complete);
                alert(zc_dmt_importer.url_import_success
                    .replace('%d', response.data.processed)
                    .replace('%d', response.data.imported));
            } else {
                $('.zc-progress-text').text(zc_dmt_importer.import_failed + ': ' + response.data);
                alert(zc_dmt_importer.import_failed + ': ' + response.data);
            }
            
            // Hide progress after delay
            setTimeout(function() {
                $('.zc-import-progress').hide();
            }, 3000);
        }).fail(function() {
            $('.zc-progress-text').text(zc_dmt_importer.import_failed);
            alert(zc_dmt_importer.import_failed);
            
            // Hide progress after delay
            setTimeout(function() {
                $('.zc-import-progress').hide();
            }, 3000);
        });
    });
    
    // Parse CSV sample
    $('#parse-csv-sample').on('click', function(e) {
        e.preventDefault();
        
        var fileInput = $('#csv_file_upload')[0];
        
        if (!fileInput.files.length) {
            alert(zc_dmt_importer.select_file_first);
            return;
        }
        
        var file = fileInput.files[0];
        var formData = new FormData();
        formData.append('action', 'zc_dmt_parse_csv_sample');
        formData.append('csv_file', file);
        formData.append('nonce', zc_dmt_importer.nonce);
        
        // Send AJAX request
        $.ajax({
            url: zc_dmt_importer.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    // Display sample data
                    var sampleHtml = '<h4>' + zc_dmt_importer.csv_sample + '</h4>';
                    sampleHtml += '<table class="widefat"><thead><tr>';
                    
                    // Header row
                    $.each(response.data.header, function(index, value) {
                        sampleHtml += '<th>' + value + '</th>';
                    });
                    
                    sampleHtml += '</tr></thead><tbody>';
                    
                    // Data rows
                    $.each(response.data.sample_data, function(rowIndex, row) {
                        sampleHtml += '<tr>';
                        $.each(row, function(colIndex, value) {
                            sampleHtml += '<td>' + value + '</td>';
                        });
                        sampleHtml += '</tr>';
                    });
                    
                    sampleHtml += '</tbody></table>';
                    
                    $('#csv-sample-container').html(sampleHtml);
                } else {
                    alert(zc_dmt_importer.parse_failed + ': ' + response.data);
                }
            },
            error: function() {
                alert(zc_dmt_importer.parse_failed);
            }
        });
    });
});
