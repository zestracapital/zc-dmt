<?php
/**
 * ZC DMT Yahoo Finance Data Source Class
 * Handles integration with Yahoo Finance for historical data
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class ZC_DMT_YahooFinance_Source extends ZC_DMT_Base_Source {
    /**
     * Yahoo Finance base URL for historical data
     */
    private $base_url = 'https://query1.finance.yahoo.com/v7/finance/download/';

    /**
     * Test connection to Yahoo Finance
     */
    public function test_connection($config) {
        // Validate required fields
        if (empty($config['symbol'])) {
            return new WP_Error('missing_symbol', __('Yahoo Finance symbol is required', 'zc-dmt'));
        }

        // Make a test request for a small date range
        $params = array(
            'period1' => strtotime('-7 days'),
            'period2' => time(),
            'interval' => '1d',
            'events' => 'history',
        );
        
        $url = $this->base_url . $config['symbol'];
        $url = add_query_arg($params, $url);

        // Add a user agent to avoid blocking
        $args = array(
            'timeout' => 15,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
        );

        $response = wp_remote_get($url, $args);
        
        if (is_wp_error($response)) {
            return new WP_Error('connection_failed', sprintf(__('Connection failed: %s', 'zc-dmt'), $response->get_error_message()));
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return new WP_Error('http_error', sprintf(__('HTTP Error %d', 'zc-dmt'), $response_code));
        }

        $body = wp_remote_retrieve_body($response);
        
        // Check if response contains valid CSV data
        if (strpos($body, 'Date,Open,High,Low,Close') === false) {
            return new WP_Error('invalid_response', __('Invalid response format from Yahoo Finance', 'zc-dmt'));
        }

        return true;
    }

    /**
     * Fetch data from Yahoo Finance
     */
    public function fetch_data($config, $date_range = null) {
        // Validate required fields
        if (empty($config['symbol'])) {
            return new WP_Error('missing_symbol', __('Yahoo Finance symbol is required', 'zc-dmt'));
        }

        // Determine date range
        $period2 = time(); // End date is today
        $period1 = strtotime('-10 years'); // Default start date is 10 years ago

        // Override with provided date range if available
        if (!empty($date_range['start_date'])) {
            $period1 = strtotime($date_range['start_date']);
        }
        if (!empty($date_range['end_date'])) {
            $period2 = strtotime($date_range['end_date']);
        }

        // Validate dates
        if ($period1 === false || $period2 === false) {
            return new WP_Error('invalid_date', __('Invalid date format provided', 'zc-dmt'));
        }

        if ($period1 >= $period2) {
            return new WP_Error('date_error', __('Start date must be before end date', 'zc-dmt'));
        }

        // Build request URL
        $url = $this->base_url . $config['symbol'];
        $params = array(
            'period1' => $period1,
            'period2' => $period2,
            'interval' => '1d',
            'events' => 'history',
        );
        $url = add_query_arg($params, $url);

        // Add a user agent to avoid blocking
        $args = array(
            'timeout' => 30,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
        );

        // Make API request
        $response = wp_remote_get($url, $args);
        
        if (is_wp_error($response)) {
            return new WP_Error('fetch_failed', sprintf(__('Failed to fetch  %s', 'zc-dmt'), $response->get_error_message()));
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            return new WP_Error('http_error', sprintf(__('HTTP Error %d: %s', 'zc-dmt'), $response_code, $body));
        }

        // Check if response contains data
        if (empty($body)) {
            return new WP_Error('no_data', __('No data returned from Yahoo Finance', 'zc-dmt'));
        }

        // Parse CSV data
        $lines = explode("\n", trim($body));
        
        // Check if we have enough lines (header + data)
        if (count($lines) < 2) {
            return new WP_Error('invalid_response', __('Invalid response format from Yahoo Finance', 'zc-dmt'));
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
            
            // Expected format: Date,Open,High,Low,Close,Adj Close,Volume
            // We want the Adj Close price as the value
            if (count($parts) >= 6) {
                $date = $parts[0];
                $adj_close = $parts[5];
                
                // Validate date
                $timestamp = strtotime($date);
                if ($timestamp === false) {
                    continue;
                }
                $obs_date = date('Y-m-d', $timestamp);
                
                // Validate price
                if ($adj_close === 'null' || !is_numeric($adj_close)) {
                    continue;
                }
                
                $value = floatval($adj_close);
                
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
     * Get symbol info from Yahoo Finance
     */
    public function get_symbol_info($config) {
        // Validate required fields
        if (empty($config['symbol'])) {
            return new WP_Error('missing_symbol', __('Yahoo Finance symbol is required', 'zc-dmt'));
        }

        // Build request URL for quote summary
        $url = 'https://query1.finance.yahoo.com/v10/finance/quoteSummary/' . $config['symbol'];
        $params = array(
            'modules' => 'price,summaryProfile'
        );
        $url = add_query_arg($params, $url);

        // Add a user agent to avoid blocking
        $args = array(
            'timeout' => 15,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
        );

        // Make API request
        $response = wp_remote_get($url, $args);
        
        if (is_wp_error($response)) {
            return new WP_Error('fetch_failed', sprintf(__('Failed to fetch symbol info: %s', 'zc-dmt'), $response->get_error_message()));
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            return new WP_Error('http_error', sprintf(__('HTTP Error %d', 'zc-dmt'), $response_code));
        }

        $data = json_decode($body, true);
        
        if (!isset($data['quoteSummary']['result']) || !is_array($data['quoteSummary']['result'])) {
            return new WP_Error('invalid_response', __('Invalid API response format for symbol info', 'zc-dmt'));
        }

        if (empty($data['quoteSummary']['result'])) {
            return new WP_Error('symbol_not_found', __('Symbol not found', 'zc-dmt'));
        }

        return $data['quoteSummary']['result'][0];
    }

    /**
     * Search for symbols on Yahoo Finance
     */
    public function search_symbols($query, $limit = 10) {
        // Build request URL for search
        $url = 'https://query2.finance.yahoo.com/v1/finance/search';
        $params = array(
            'q' => $query,
            'quotesCount' => $limit,
            'newsCount' => 0,
            'enableFuzzyQuery' => 'false'
        );
        $url = add_query_arg($params, $url);

        // Add a user agent to avoid blocking
        $args = array(
            'timeout' => 15,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
        );

        // Make API request
        $response = wp_remote_get($url, $args);
        
        if (is_wp_error($response)) {
            return new WP_Error('search_failed', sprintf(__('Failed to search symbols: %s', 'zc-dmt'), $response->get_error_message()));
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            return new WP_Error('http_error', sprintf(__('HTTP Error %d', 'zc-dmt'), $response_code));
        }

        $data = json_decode($body, true);
        
        if (!isset($data['quotes']) || !is_array($data['quotes'])) {
            return new WP_Error('invalid_response', __('Invalid API response format for symbol search', 'zc-dmt'));
        }

        return $data['quotes'];
    }
}
?>
