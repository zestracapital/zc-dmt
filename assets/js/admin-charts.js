/**
 * ZC DMT Admin Charts JavaScript
 * Handles chart preview functionality in the admin interface
 */

jQuery(document).ready(function($) {
    // Handle chart preview trigger
    $('.zc-chart-preview-trigger').on('click', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var indicatorId = button.data('indicator-id');
        var container = button.closest('.zc-chart-preview-container');
        var loading = container.find('.zc-chart-loading');
        var error = container.find('.zc-chart-error');
        var wrapper = container.find('.zc-chart-wrapper');
        var canvas = container.find('canvas');
        
        // Hide previous content and show loading
        wrapper.hide();
        error.hide();
        loading.show();
        button.prop('disabled', true).text('Loading...');
        
        // Make AJAX request
        $.ajax({
            url: zc_dmt_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'zc_dmt_preview_chart',
                indicator_id: indicatorId,
                nonce: zc_dmt_ajax.nonce
            },
            success: function(response) {
                loading.hide();
                button.prop('disabled', false).text('Load Preview');
                
                if (response.success) {
                    // Render chart
                    renderChart(canvas.attr('id'), response.data);
                    wrapper.show();
                } else {
                    error.find('p').text(response.data || 'Error loading chart data.');
                    error.show();
                }
            },
            error: function() {
                loading.hide();
                button.prop('disabled', false).text('Load Preview');
                error.find('p').text('AJAX request failed.');
                error.show();
            }
        });
    });
    
    // Function to render chart based on data
    function renderChart(canvasId, chartData) {
        var ctx = document.getElementById(canvasId).getContext('2d');
        
        // Destroy existing chart if it exists
        if (window.zcChartInstances && window.zcChartInstances[canvasId]) {
            window.zcChartInstances[canvasId].destroy();
        }
        
        // Create new chart
        var chart = new Chart(ctx, {
            type: 'line',
            data: chartData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        display: true,
                        title: {
                            display: true,
                            text: 'Date'
                        }
                    },
                    y: {
                        display: true,
                        title: {
                            display: true,
                            text: 'Value'
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                    }
                }
            }
        });
        
        // Store chart instance
        if (!window.zcChartInstances) {
            window.zcChartInstances = {};
        }
        window.zcChartInstances[canvasId] = chart;
    }
});
