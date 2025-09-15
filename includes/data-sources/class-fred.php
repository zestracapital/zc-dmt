<?php
/**
 * ZC DMT FRED Data Source Class
 * Handles integration with Federal Reserve Economic Data API
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class ZC_DMT_FRED_Source extends ZC_DMT_Base_Source {
    /**
     * FRED API base URL
     */
    private $api_base = 'https://api.stlouisfed.org/fred/';

    /**
     * Test connection to FRED API
     */
    public function test_connection($config) {
        // Validate required fields
        if (empty($config['api_key'])) {
            return new WP_Error('missing_api_key', __('FRED API key is required', 'zc-dmt'));
        }
        
        if (empty($config['series_id'])) {
            return new WP_Error('missing_series_id', __('FRED series ID is required', 'zc-dmt'));
        }

        // Make a test request to series observation endpoint
        $url = $this->api_base . 'series/observations';
        $params = array(
            'series_id' => $config['series_id'],
            'api_key' => $config['api_key'],
            'file_type' => 'json',
            'limit' => 1 // Just get one observation to test
        );
        $url = add_query_arg($params, $url);

        $response = wp_remote_get($url, array('timeout' => 15));
        
        if (is_wp_error($response)) {
            return new WP_Error('connection_failed', sprintf(__('Connection failed: %s', 'zc-dmt'), $response->get_error_message()));
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return new WP_Error('http_error', sprintf(__('HTTP Error %d', 'zc-dmt'), $response_code));
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['error_code'])) {
            return new WP_Error('api_error', sprintf(__('FRED API Error: %s', 'zc-dmt'), $data['error_message']));
        }

        if (!isset($data['observations'])) {
            return new WP_Error('invalid_response', __('Invalid API response format', 'zc-dmt'));
        }

        return true;
    }

    /**
     * Fetch data from FRED API
     */
    public function fetch_data($config, $date_range = null) {
        // Validate required fields
        if (empty($config['api_key'])) {
            return new WP_Error('missing_api_key', __('FRED API key is required', 'zc-dmt'));
        }
        
        if (empty($config['series_id'])) {
            return new WP_Error('missing_series_id', __('FRED series ID is required', 'zc-dmt'));
        }

        // Build request URL
        $url = $this->api_base . 'series/observations';
        $params = array(
            'series_id' => $config['series_id'],
            'api_key' => $config['api_key'],
            'file_type' => 'json',
            'sort_order' => 'asc'
        );

        // Add date range parameters if provided
        if ($date_range) {
            if (!empty($date_range['start_date'])) {
                $params['observation_start'] = $date_range['start_date'];
            }
            if (!empty($date_range['end_date'])) {
                $params['observation_end'] = $date_range['end_date'];
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

        $data = json_decode($body, true);
        
        if (isset($data['error_code'])) {
            return new WP_Error('api_error', sprintf(__('FRED API Error: %s', 'zc-dmt'), $data['error_message']));
        }

        if (!isset($data['observations']) || !is_array($data['observations'])) {
            return new WP_Error('invalid_response', __('Invalid API response format for data', 'zc-dmt'));
        }

        // Process observations
        $processed_data = array();
        
        foreach ($data['observations'] as $observation) {
            // Skip observations with '.' or null values
            if (!isset($observation['value']) || $observation['value'] === '.' || $observation['value'] === null) {
                continue;
            }
            
            // Convert value to float
            $value = floatval($observation['value']);
            
            // Skip non-finite values
            if (!is_finite($value)) {
                continue;
            }
            
            $processed_data[] = array(
                'date' => $observation['date'],
                'value' => $value
            );
        }

        return $processed_data;
    }

    /**
     * Get series info from FRED API
     */
    public function get_series_info($config) {
        // Validate required fields
        if (empty($config['api_key'])) {
            return new WP_Error('missing_api_key', __('FRED API key is required', 'zc-dmt'));
        }
        
        if (empty($config['series_id'])) {
            return new WP_Error('missing_series_id', __('FRED series ID is required', 'zc-dmt'));
        }

        // Build request URL
        $url = $this->api_base . 'series';
        $params = array(
            'series_id' => $config['series_id'],
            'api_key' => $config['api_key'],
            'file_type' => 'json'
        );
        $url = add_query_arg($params, $url);

        // Make API request
        $response = wp_remote_get($url, array('timeout' => 15));
        
        if (is_wp_error($response)) {
            return new WP_Error('fetch_failed', sprintf(__('Failed to fetch series info: %s', 'zc-dmt'), $response->get_error_message()));
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            return new WP_Error('http_error', sprintf(__('HTTP Error %d', 'zc-dmt'), $response_code));
        }

        $data = json_decode($body, true);
        
        if (isset($data['error_code'])) {
            return new WP_Error('api_error', sprintf(__('FRED API Error: %s', 'zc-dmt'), $data['error_message']));
        }

        if (!isset($data['seriess']) || !is_array($data['seriess']) || empty($data['seriess'])) {
            return new WP_Error('invalid_response', __('Invalid API response format for series info', 'zc-dmt'));
        }

        return $data['seriess'][0];
    }

    /**
     * Search series in FRED
     */
    public function search_series($query, $limit = 20) {
        // Validate API key
        $api_key = get_option('zc_dmt_fred_api_key', '');
        if (empty($api_key)) {
            return new WP_Error('missing_api_key', __('FRED API key is required for search', 'zc-dmt'));
        }

        // Build request URL
        $url = $this->api_base . 'series/search';
        $params = array(
            'search_text' => $query,
            'api_key' => $api_key,
            'file_type' => 'json',
            'limit' => $limit
        );
        $url = add_query_arg($params, $url);

        // Make API request
        $response = wp_remote_get($url, array('timeout' => 15));
        
        if (is_wp_error($response)) {
            return new WP_Error('search_failed', sprintf(__('Failed to search series: %s', 'zc-dmt'), $response->get_error_message()));
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            return new WP_Error('http_error', sprintf(__('HTTP Error %d', 'zc-dmt'), $response_code));
        }

        $data = json_decode($body, true);
        
        if (isset($data['error_code'])) {
            return new WP_Error('api_error', sprintf(__('FRED API Error: %s', 'zc-dmt'), $data['error_message']));
        }

        if (!isset($data['seriess']) || !is_array($data['seriess'])) {
            return new WP_Error('invalid_response', __('Invalid API response format for search', 'zc-dmt'));
        }

        return $data['seriess'];
    }
}
?>
