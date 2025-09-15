<?php
/**
 * ZC DMT Google Finance Data Source Class
 * Handles integration with Google Finance for historical data
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class ZC_DMT_GoogleFinance_Source extends ZC_DMT_Base_Source {
    /**
     * Google Finance base URL for historical data
     */
    private $base_url = 'https://finance.google.com/finance/historical';

    /**
     * Test connection to Google Finance
     */
    public function test_connection($config) {
        // Validate required fields
        if (empty($config['symbol'])) {
            return new WP_Error('missing_symbol', __('Google Finance symbol is required', 'zc-dmt'));
        }

        // Build request URL
        $url = $this->base_url;
        $params = array(
            'q' => $config['symbol'],
            'output' => 'csv'
        );
        $url = add_query_arg($params, $url);

        // Make a test request
        $response = wp_remote_get($url, array('timeout' => 15));
        
        if (is_wp_error($response)) {
            return new WP_Error('connection_failed', sprintf(__('Connection failed: %s', 'zc-dmt'), $response->get_error_message()));
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return new WP_Error('http_error', sprintf(__('HTTP Error %d', 'zc-dmt'), $response_code));
        }

        $body = wp_remote_retrieve_body($response);
        
        // Check if response contains valid CSV data
        if (strpos($body, 'Date,Open,High,Low,Close,Volume') === false) {
            return new WP_Error('invalid_response', __('Invalid response format from Google Finance', 'zc-dmt'));
        }

        return true;
    }

    /**
     * Fetch data from Google Finance
     */
    public function fetch_data($config, $date_range = null) {
        // Validate required fields
        if (empty($config['symbol'])) {
            return new WP_Error('missing_symbol', __('Google Finance symbol is required', 'zc-dmt'));
        }

        // Build request URL
        $url = $this->base_url;
        $params = array(
            'q' => $config['symbol'],
            'output' => 'csv'
        );

        // Add date range parameters if provided
        if ($date_range) {
            if (!empty($date_range['start_date'])) {
                $params['startdate'] = $date_range['start_date'];
            }
            if (!empty($date_range['end_date'])) {
                $params['enddate'] = $date_range['end_date'];
            }
        }

        $url = add_query_arg($params, $url);

        // Make API request
        $response = wp_remote_get($url, array('timeout' => 30));
        
        if (is_wp_error($response)) {
            return new WP_Error('fetch_failed', sprintf(__('Failed to fetch data: %s', 'zc-dmt'), $response->get_error_message()));
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            return new WP_Error('http_error', sprintf(__('HTTP Error %d: %s', 'zc-dmt'), $response_code, $body));
        }

        // Check if response contains data
        if (empty($body)) {
            return new WP_Error('no_data', __('No data returned from Google Finance', 'zc-dmt'));
        }

        // Parse CSV data
        $lines = explode("\n", trim($body));
        
        // Check if we have enough lines (header + data)
        if (count($lines) < 2) {
            return new WP_Error('invalid_response', __('Invalid response format from Google Finance', 'zc-dmt'));
        }

        // Process data (skip header line)
        $processed_data = array();
        
        for ($i = 1; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            
            // Skip empty lines
            if (empty($line)) {
                continue;
            }
            
            // Parse CSV line
            $parts = str_getcsv($line);
            
            // Expected format: Date,Open,High,Low,Close,Volume
            // We want the Close price as the value
            if (count($parts) >= 5) {
                $date = $parts[0];
                $close_price = $parts[4];
                
                // Validate date
                $timestamp = strtotime($date);
                if ($timestamp === false) {
                    continue;
                }
                $obs_date = date('Y-m-d', $timestamp);
                
                // Validate price
                if ($close_price === 'null' || !is_numeric($close_price)) {
                    continue;
                }
                
                $value = floatval($close_price);
                
                // Skip if value is not finite
                if (!is_finite($value)) {
                    continue;
                }
                
                $processed_data[] = array(
                    'date' => $obs_date,
                    'value' => $value
                );
            }
        }

        return $processed_data;
    }

    /**
     * Get symbol info from Google Finance
     */
    public function get_symbol_info($config) {
        // Validate required fields
        if (empty($config['symbol'])) {
            return new WP_Error('missing_symbol', __('Google Finance symbol is required', 'zc-dmt'));
        }

        // For Google Finance, we don't have a specific API endpoint for symbol info
        // We'll return basic info based on the symbol
        return array(
            'symbol' => $config['symbol'],
            'name' => $config['symbol'], // In a real implementation, you might fetch the actual company name
            'source' => 'Google Finance'
        );
    }

    /**
     * Search for symbols on Google Finance
     */
    public function search_symbols($query, $limit = 10) {
        // Google Finance doesn't have a public search API
        // This is a placeholder implementation
        return new WP_Error('not_supported', __('Symbol search is not supported for Google Finance', 'zc-dmt'));
    }
}
?>
