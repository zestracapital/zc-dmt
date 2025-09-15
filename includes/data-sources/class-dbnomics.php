<?php
/**
 * ZC DMT DBnomics Data Source Class
 * Handles integration with DBnomics API
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class ZC_DMT_DBnomics_Source extends ZC_DMT_Base_Source {
    /**
     * DBnomics API base URL
     */
    private $api_base = 'https://api.db.nomics.world/v22/series';

    /**
     * Test connection to DBnomics API
     */
    public function test_connection($config) {
        // Validate required fields
        if (empty($config['provider_code'])) {
            return new WP_Error('missing_provider_code', __('DBnomics provider code is required', 'zc-dmt'));
        }
        
        if (empty($config['dataset_code'])) {
            return new WP_Error('missing_dataset_code', __('DBnomics dataset code is required', 'zc-dmt'));
        }
        
        if (empty($config['series_code'])) {
            return new WP_Error('missing_series_code', __('DBnomics series code is required', 'zc-dmt'));
        }

        // Make a test request to series endpoint
        $url = $this->api_base . '/' . $config['provider_code'] . '/' . $config['dataset_code'] . '/' . $config['series_code'];
        $params = array(
            'format' => 'json',
            'limit' => 1 // Just get one observation to test
        );
        $url = add_query_arg($params, $url);

        $response = wp_remote_get($url, array('timeout' => 15));
        
        if (is_wp_error($response)) {
            return new WP_Error('connection_failed', sprintf(__('Connection failed: %s', 'zc-dmt'), $response->get_error_message()));
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            return new WP_Error('http_error', sprintf(__('HTTP Error %d', 'zc-dmt'), $response_code));
        }

        $data = json_decode($body, true);
        
        if (isset($data['error'])) {
            return new WP_Error('api_error', sprintf(__('DBnomics API Error: %s', 'zc-dmt'), $data['error']));
        }

        if (!isset($data['series']) || !is_array($data['series'])) {
            return new WP_Error('invalid_response', __('Invalid API response format', 'zc-dmt'));
        }

        return true;
    }

    /**
     * Fetch data from DBnomics API
     */
    public function fetch_data($config, $date_range = null) {
        // Validate required fields
        if (empty($config['provider_code'])) {
            return new WP_Error('missing_provider_code', __('DBnomics provider code is required', 'zc-dmt'));
        }
        
        if (empty($config['dataset_code'])) {
            return new WP_Error('missing_dataset_code', __('DBnomics dataset code is required', 'zc-dmt'));
        }
        
        if (empty($config['series_code'])) {
            return new WP_Error('missing_series_code', __('DBnomics series code is required', 'zc-dmt'));
        }

        // Build request URL
        $url = $this->api_base . '/' . $config['provider_code'] . '/' . $config['dataset_code'] . '/' . $config['series_code'];
        $params = array(
            'format' => 'json'
        );

        // Add date range parameters if provided
        if ($date_range) {
            if (!empty($date_range['start_date'])) {
                $params['start_date'] = $date_range['start_date'];
            }
            if (!empty($date_range['end_date'])) {
                $params['end_date'] = $date_range['end_date'];
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
        
        if (isset($data['error'])) {
            return new WP_Error('api_error', sprintf(__('DBnomics API Error: %s', 'zc-dmt'), $data['error']));
        }

        if (!isset($data['series']) || !is_array($data['series']) || empty($data['series'])) {
            return new WP_Error('invalid_response', __('Invalid API response format for data', 'zc-dmt'));
        }

        // Process series data
        $series = $data['series'][0];
        $data_points = array();

        // Check if we have observations
        if (isset($series['period']) && isset($series['value']) && 
            is_array($series['period']) && is_array($series['value']) &&
            count($series['period']) === count($series['value'])) {
            
            // Combine periods and values
            for ($i = 0; $i < count($series['period']); $i++) {
                $period = $series['period'][$i];
                $value = $series['value'][$i];
                
                // Convert period to date
                $date = $this->convert_period_to_date($period, $series['frequency']);
                
                // Skip null values
                if ($value !== null) {
                    $data_points[] = array(
                        'date' => $date,
                        'value' => $value
                    );
                }
            }
        }

        return $data_points;
    }

    /**
     * Convert DBnomics period to date
     */
    private function convert_period_to_date($period, $frequency) {
        // Handle different frequency formats
        switch ($frequency) {
            case 'A': // Annual
                // Period format: "2020"
                if (preg_match('/^(\d{4})$/', $period, $matches)) {
                    return $matches[1] . '-12-31';
                }
                break;
                
            case 'S': // Semi-annual
                // Period format: "2020S1" or "2020S2"
                if (preg_match('/^(\d{4})S([12])$/', $period, $matches)) {
                    $year = $matches[1];
                    $semester = $matches[2];
                    if ($semester == 1) {
                        return $year . '-06-30';
                    } else {
                        return $year . '-12-31';
                    }
                }
                break;
                
            case 'Q': // Quarterly
                // Period format: "2020Q1"
                if (preg_match('/^(\d{4})Q([1-4])$/', $period, $matches)) {
                    $year = $matches[1];
                    $quarter = $matches[2];
                    $month = str_pad((($quarter - 1) * 3) + 3, 2, '0', STR_PAD_LEFT);
                    return $year . '-' . $month . '-01'; // First day of quarter end month
                }
                break;
                
            case 'M': // Monthly
                // Period format: "2020-01"
                if (preg_match('/^(\d{4})-(\d{2})$/', $period, $matches)) {
                    return $period . '-01';
                }
                break;
                
            case 'W': // Weekly
                // Period format: "2020-W01"
                if (preg_match('/^(\d{4})-W(\d{2})$/', $period, $matches)) {
                    $year = $matches[1];
                    $week = $matches[2];
                    // Get date of Sunday of that week
                    $timestamp = strtotime($year . 'W' . str_pad($week, 2, '0', STR_PAD_LEFT));
                    return date('Y-m-d', $timestamp);
                }
                break;
                
            case 'D': // Daily
                // Period format: "2020-01-01"
                if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $period, $matches)) {
                    return $period; // Already in correct format
                }
                break;
        }
        
        // If we can't parse it, return as is (might already be in correct format)
        return $period;
    }

    /**
     * Get series info from DBnomics API
     */
    public function get_series_info($config) {
        // Validate required fields
        if (empty($config['provider_code'])) {
            return new WP_Error('missing_provider_code', __('DBnomics provider code is required', 'zc-dmt'));
        }
        
        if (empty($config['dataset_code'])) {
            return new WP_Error('missing_dataset_code', __('DBnomics dataset code is required', 'zc-dmt'));
        }
        
        if (empty($config['series_code'])) {
            return new WP_Error('missing_series_code', __('DBnomics series code is required', 'zc-dmt'));
        }

        // Build request URL
        $url = $this->api_base . '/' . $config['provider_code'] . '/' . $config['dataset_code'] . '/' . $config['series_code'];
        $params = array(
            'format' => 'json'
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
        
        if (isset($data['error'])) {
            return new WP_Error('api_error', sprintf(__('DBnomics API Error: %s', 'zc-dmt'), $data['error']));
        }

        if (!isset($data['series']) || !is_array($data['series']) || empty($data['series'])) {
            return new WP_Error('invalid_response', __('Invalid API response format for series info', 'zc-dmt'));
        }

        return $data['series'][0];
    }

    /**
     * Search series in DBnomics
     */
    public function search_series($query, $limit = 20) {
        // Build request URL
        $url = 'https://api.db.nomics.world/v22/search';
        $params = array(
            'query' => $query,
            'limit' => $limit,
            'format' => 'json'
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
        
        if (isset($data['error'])) {
            return new WP_Error('api_error', sprintf(__('DBnomics API Error: %s', 'zc-dmt'), $data['error']));
        }

        if (!isset($data['series']) || !is_array($data['series'])) {
            return new WP_Error('invalid_response', __('Invalid API response format for search', 'zc-dmt'));
        }

        return $data['series'];
    }
}
?>
