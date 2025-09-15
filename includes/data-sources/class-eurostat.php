<?php
/**
 * ZC DMT Eurostat Data Source Class
 * Handles integration with Eurostat API
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class ZC_DMT_Eurostat_Source extends ZC_DMT_Base_Source {
    /**
     * Eurostat API base URL
     */
    private $api_base = 'https://ec.europa.eu/eurostat/api/dissemination/statistics/1.0/data/';

    /**
     * Test connection to Eurostat API
     */
    public function test_connection($config) {
        // Validate required fields
        if (empty($config['dataset_code'])) {
            return new WP_Error('missing_dataset_code', __('Eurostat dataset code is required', 'zc-dmt'));
        }

        // Make a test request to dataset endpoint
        $url = $this->api_base . $config['dataset_code'];
        $params = array(
            'format' => 'json',
            'lang' => 'en'
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
        
        if (isset($data['error'])) {
            return new WP_Error('api_error', sprintf(__('Eurostat API Error: %s', 'zc-dmt'), $data['error']));
        }

        return true;
    }

    /**
     * Fetch data from Eurostat API
     */
    public function fetch_data($config, $date_range = null) {
        // Validate required fields
        if (empty($config['dataset_code'])) {
            return new WP_Error('missing_dataset_code', __('Eurostat dataset code is required', 'zc-dmt'));
        }

        // Build request URL
        $url = $this->api_base . $config['dataset_code'];
        $params = array(
            'format' => 'json',
            'lang' => 'en'
        );

        // Add indicator code if provided
        if (!empty($config['indicator_code'])) {
            // In Eurostat API, filters are added as parameters
            $params['indic'] = $config['indicator_code'];
        }

        // Add country code if provided
        if (!empty($config['country_code'])) {
            $params['geo'] = $config['country_code'];
        }

        // Add date range if provided
        if ($date_range) {
            if (!empty($date_range['start_date'])) {
                // Convert date to Eurostat format (YYYY)
                $start_year = date('Y', strtotime($date_range['start_date']));
                if (empty($params['time'])) {
                    $params['time'] = '';
                }
                $params['time'] .= $start_year;
            }
            if (!empty($date_range['end_date'])) {
                // Convert date to Eurostat format (YYYY)
                $end_year = date('Y', strtotime($date_range['end_date']));
                if (empty($params['time'])) {
                    $params['time'] = '';
                } else {
                    $params['time'] .= ',';
                }
                $params['time'] .= $end_year;
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
            return new WP_Error('api_error', sprintf(__('Eurostat API Error: %s', 'zc-dmt'), $data['error']));
        }

        // Process data
        $data_points = $this->process_eurostat_data($data);
        
        return $data_points;
    }

    /**
     * Process Eurostat API response data
     */
    private function process_eurostat_data($data) {
        // Check if we have the required data structure
        if (!isset($data['dimension']['time']) || !isset($data['value'])) {
            return new WP_Error('invalid_response', __('Invalid API response format', 'zc-dmt'));
        }

        $time_dimension = $data['dimension']['time'];
        $values = $data['value'];
        $data_points = array();

        // Process each value
        foreach ($values as $key => $value) {
            // The key is a combination of dimension indices
            // We need to parse it to get the time dimension index
            $indices = explode(',', $key);
            
            // Find time dimension index (this is a simplified approach)
            // In a real implementation, you would need to map all dimensions correctly
            if (isset($indices[0])) {
                $time_index = $indices[0]; // Assuming time is the first dimension
                
                // Get the actual time value
                if (isset($time_dimension['category']['index']) && is_array($time_dimension['category']['index'])) {
                    $time_categories = array_flip($time_dimension['category']['index']);
                    if (isset($time_categories[$time_index])) {
                        $time_period = $time_categories[$time_index];
                        
                        // Convert time period to date
                        $date = $this->convert_eurostat_date($time_period);
                        
                        $data_points[] = array(
                            'date' => $date,
                            'value' => $value
                        );
                    }
                }
            }
        }

        return $data_points;
    }

    /**
     * Convert Eurostat time period to date
     */
    private function convert_eurostat_date($eurostat_date) {
        // Handle different Eurostat date formats
        // Annual: "2020"
        if (preg_match('/^(\d{4})$/', $eurostat_date, $matches)) {
            return $matches[1] . '-12-31';
        }
        
        // Quarterly: "2020Q1"
        if (preg_match('/^(\d{4})Q([1-4])$/', $eurostat_date, $matches)) {
            $year = $matches[1];
            $quarter = $matches[2];
            $month = str_pad((($quarter - 1) * 3) + 3, 2, '0', STR_PAD_LEFT);
            return $year . '-' . $month . '-01'; // First day of quarter end month
        }
        
        // Monthly: "2020M01"
        if (preg_match('/^(\d{4})M(\d{2})$/', $eurostat_date, $matches)) {
            $year = $matches[1];
            $month = $matches[2];
            return $year . '-' . $month . '-01';
        }
        
        // If we can't parse it, return as is
        return $eurostat_date;
    }

    /**
     * Get dataset info from Eurostat API
     */
    public function get_dataset_info($config) {
        // Validate required fields
        if (empty($config['dataset_code'])) {
            return new WP_Error('missing_dataset_code', __('Eurostat dataset code is required', 'zc-dmt'));
        }

        // Build request URL for dataset metadata
        $url = 'https://ec.europa.eu/eurostat/api/dissemination/statistics/1.0/dataflow/ESTAT/' . $config['dataset_code'];
        $params = array(
            'format' => 'json',
            'lang' => 'en'
        );
        $url = add_query_arg($params, $url);

        // Make API request
        $response = wp_remote_get($url, array('timeout' => 15));
        
        if (is_wp_error($response)) {
            return new WP_Error('fetch_failed', sprintf(__('Failed to fetch dataset info: %s', 'zc-dmt'), $response->get_error_message()));
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            return new WP_Error('http_error', sprintf(__('HTTP Error %d', 'zc-dmt'), $response_code));
        }

        $data = json_decode($body, true);
        
        if (isset($data['error'])) {
            return new WP_Error('api_error', sprintf(__('Eurostat API Error: %s', 'zc-dmt'), $data['error']));
        }

        return $data;
    }

    /**
     * Search datasets in Eurostat
     */
    public function search_datasets($query, $limit = 20) {
        // Build request URL for search
        $url = 'https://ec.europa.eu/eurostat/api/dissemination/statistics/1.0/search/';
        $params = array(
            'format' => 'json',
            'lang' => 'en',
            'search' => $query,
            'limit' => $limit
        );
        $url = add_query_arg($params, $url);

        // Make API request
        $response = wp_remote_get($url, array('timeout' => 15));
        
        if (is_wp_error($response)) {
            return new WP_Error('search_failed', sprintf(__('Failed to search datasets: %s', 'zc-dmt'), $response->get_error_message()));
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            return new WP_Error('http_error', sprintf(__('HTTP Error %d', 'zc-dmt'), $response_code));
        }

        $data = json_decode($body, true);
        
        if (isset($data['error'])) {
            return new WP_Error('api_error', sprintf(__('Eurostat API Error: %s', 'zc-dmt'), $data['error']));
        }

        return $data;
    }
}
?>
