<?php
/**
 * ZC DMT World Bank Data Source Class
 * Handles integration with World Bank API
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class ZC_DMT_WorldBank_Source extends ZC_DMT_Base_Source {
    /**
     * World Bank API base URL
     */
    private $api_base = 'http://api.worldbank.org/v2/country/';

    /**
     * Test connection to World Bank API
     */
    public function test_connection($config) {
        // Validate required fields
        if (empty($config['indicator_code'])) {
            return new WP_Error('missing_indicator_code', __('World Bank indicator code is required', 'zc-dmt'));
        }

        // Use USA as default country for testing
        $country_code = !empty($config['country_code']) ? $config['country_code'] : 'US';

        // Make a test request to indicators endpoint
        $url = $this->api_base . $country_code . '/indicator/' . $config['indicator_code'];
        $params = array(
            'format' => 'json',
            'per_page' => 1 // Just get one observation to test
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
        
        // World Bank API returns an array with metadata and data
        if (!is_array($data) || count($data) < 2) {
            return new WP_Error('invalid_response', __('Invalid API response format', 'zc-dmt'));
        }

        // Check if we got an error message
        if (isset($data[0]['message'])) {
            $message = isset($data[0]['message'][0]['key']) ? $data[0]['message'][0]['key'] : 'Unknown error';
            return new WP_Error('api_error', sprintf(__('World Bank API Error: %s', 'zc-dmt'), $message));
        }

        return true;
    }

    /**
     * Fetch data from World Bank API
     */
    public function fetch_data($config, $date_range = null) {
        // Validate required fields
        if (empty($config['indicator_code'])) {
            return new WP_Error('missing_indicator_code', __('World Bank indicator code is required', 'zc-dmt'));
        }

        // Use all countries if no country code specified
        $country_code = !empty($config['country_code']) ? $config['country_code'] : 'all';

        // Build request URL
        $url = $this->api_base . $country_code . '/indicator/' . $config['indicator_code'];
        $params = array(
            'format' => 'json',
            'per_page' => 1000, // Get more data points
            'date' => 'all' // Get all available dates
        );

        // Add date range parameters if provided
        if ($date_range) {
            $date_param = '';
            if (!empty($date_range['start_date'])) {
                $date_param .= date('Y', strtotime($date_range['start_date']));
            }
            if (!empty($date_range['end_date'])) {
                if (!empty($date_param)) {
                    $date_param .= ':';
                }
                $date_param .= date('Y', strtotime($date_range['end_date']));
            }
            if (!empty($date_param)) {
                $params['date'] = $date_param;
            }
        }

        $url = add_query_arg($params, $url);

        // Make API request
        $response = wp_remote_get($url, array('timeout' => 30));
        
        if (is_wp_error($response)) {
            return new WP_Error('fetch_failed', sprintf(__('Failed to fetch  %s', 'zc-dmt'), $response->get_error_message()));
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            return new WP_Error('http_error', sprintf(__('HTTP Error %d: %s', 'zc-dmt'), $response_code, $body));
        }

        $data = json_decode($body, true);
        
        // World Bank API returns an array with metadata and data
        if (!is_array($data) || count($data) < 2) {
            return new WP_Error('invalid_response', __('Invalid API response format', 'zc-dmt'));
        }

        // Check if we got an error message
        if (isset($data[0]['message'])) {
            $message = isset($data[0]['message'][0]['key']) ? $data[0]['message'][0]['key'] : 'Unknown error';
            return new WP_Error('api_error', sprintf(__('World Bank API Error: %s', 'zc-dmt'), $message));
        }

        // Get the actual data (second element in array)
        $observations = $data[1];
        
        if (!is_array($observations)) {
            return new WP_Error('invalid_response', __('Invalid API response format for data', 'zc-dmt'));
        }

        // Process observations
        $processed_data = array();
        
        foreach ($observations as $observation) {
            // Skip observations without required fields
            if (!isset($observation['date']) || !isset($observation['value']) || $observation['value'] === null) {
                continue;
            }
            
            // Convert value to float
            $value = floatval($observation['value']);
            
            // Skip if value is not finite
            if (!is_finite($value)) {
                continue;
            }
            
            // World Bank provides yearly data, so we'll set to last day of year
            $obs_date = $observation['date'] . '-12-31';
            
            $processed_data[] = array(
                'date' => $obs_date,
                'value' => $value
            );
        }

        return $processed_data;
    }

    /**
     * Get indicator info from World Bank API
     */
    public function get_indicator_info($config) {
        // Validate required fields
        if (empty($config['indicator_code'])) {
            return new WP_Error('missing_indicator_code', __('World Bank indicator code is required', 'zc-dmt'));
        }

        // Build request URL
        $url = 'http://api.worldbank.org/v2/indicator/' . $config['indicator_code'];
        $params = array(
            'format' => 'json'
        );
        $url = add_query_arg($params, $url);

        // Make API request
        $response = wp_remote_get($url, array('timeout' => 15));
        
        if (is_wp_error($response)) {
            return new WP_Error('fetch_failed', sprintf(__('Failed to fetch indicator info: %s', 'zc-dmt'), $response->get_error_message()));
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            return new WP_Error('http_error', sprintf(__('HTTP Error %d', 'zc-dmt'), $response_code));
        }

        $data = json_decode($body, true);
        
        // World Bank API returns an array with metadata and data
        if (!is_array($data) || count($data) < 2) {
            return new WP_Error('invalid_response', __('Invalid API response format', 'zc-dmt'));
        }

        // Check if we got an error message
        if (isset($data[0]['message'])) {
            $message = isset($data[0]['message'][0]['key']) ? $data[0]['message'][0]['key'] : 'Unknown error';
            return new WP_Error('api_error', sprintf(__('World Bank API Error: %s', 'zc-dmt'), $message));
        }

        // Get the actual data (second element in array)
        $indicators = $data[1];
        
        if (!is_array($indicators) || empty($indicators)) {
            return new WP_Error('invalid_response', __('No indicator information found', 'zc-dmt'));
        }

        return $indicators[0];
    }

    /**
     * Get available countries from World Bank API
     */
    public function get_countries() {
        // Build request URL
        $url = 'http://api.worldbank.org/v2/country';
        $params = array(
            'format' => 'json',
            'per_page' => 300 // Get reasonable number of countries
        );
        $url = add_query_arg($params, $url);

        // Make API request
        $response = wp_remote_get($url, array('timeout' => 15));
        
        if (is_wp_error($response)) {
            return new WP_Error('fetch_failed', sprintf(__('Failed to fetch countries: %s', 'zc-dmt'), $response->get_error_message()));
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            return new WP_Error('http_error', sprintf(__('HTTP Error %d', 'zc-dmt'), $response_code));
        }

        $data = json_decode($body, true);
        
        // World Bank API returns an array with metadata and data
        if (!is_array($data) || count($data) < 2) {
            return new WP_Error('invalid_response', __('Invalid API response format for countries', 'zc-dmt'));
        }

        // Check if we got an error message
        if (isset($data[0]['message'])) {
            $message = isset($data[0]['message'][0]['key']) ? $data[0]['message'][0]['key'] : 'Unknown error';
            return new WP_Error('api_error', sprintf(__('World Bank API Error: %s', 'zc-dmt'), $message));
        }

        // Get the actual data (second element in array)
        $countries = $data[1];
        
        if (!is_array($countries)) {
            return new WP_Error('invalid_response', __('Invalid API response format for countries data', 'zc-dmt'));
        }

        return $countries;
    }
}
?>
