<?php
/**
 * ZC DMT OECD Data Source Class
 * Handles integration with OECD API
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class ZC_DMT_OECD_Source extends ZC_DMT_Base_Source {
    /**
     * OECD API base URL
     */
    private $api_base = 'https://stats.oecd.org/SDMX-JSON/data/';

    /**
     * Test connection to OECD API
     */
    public function test_connection($config) {
        // Validate required fields
        if (empty($config['dataset_id'])) {
            return new WP_Error('missing_dataset_id', __('OECD dataset ID is required', 'zc-dmt'));
        }
        
        if (empty($config['indicator_code'])) {
            return new WP_Error('missing_indicator_code', __('OECD indicator code is required', 'zc-dmt'));
        }

        // Make a test request to dataset endpoint
        $url = $this->api_base . $config['dataset_id'];
        $params = array(
            'format' => 'json',
            'detail' => 'dataonly'
        );
        
        // Add indicator filter
        $url .= '/' . $config['indicator_code'] . '.';
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
        
        if (!isset($data['dataSets'])) {
            return new WP_Error('invalid_response', __('Invalid API response format', 'zc-dmt'));
        }

        return true;
    }

    /**
     * Fetch data from OECD API
     */
    public function fetch_data($config, $date_range = null) {
        // Validate required fields
        if (empty($config['dataset_id'])) {
            return new WP_Error('missing_dataset_id', __('OECD dataset ID is required', 'zc-dmt'));
        }
        
        if (empty($config['indicator_code'])) {
            return new WP_Error('missing_indicator_code', __('OECD indicator code is required', 'zc-dmt'));
        }

        // Build request URL
        $url = $this->api_base . $config['dataset_id'];
        $params = array(
            'format' => 'json',
            'detail' => 'dataonly'
        );
        
        // Add indicator filter
        $url .= '/' . $config['indicator_code'] . '.';

        // Add date range parameters if provided
        if (!empty($date_range['start_date']) || !empty($date_range['end_date'])) {
            $time_filter = array();
            if (!empty($date_range['start_date'])) {
                $time_filter[] = 'from=' . date('Y', strtotime($date_range['start_date']));
            }
            if (!empty($date_range['end_date'])) {
                $time_filter[] = 'to=' . date('Y', strtotime($date_range['end_date']));
            }
            if (!empty($time_filter)) {
                $params['time'] = implode(',', $time_filter);
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
        
        if (!isset($data['dataSets']) || !is_array($data['dataSets'])) {
            return new WP_Error('invalid_response', __('Invalid API response format for data', 'zc-dmt'));
        }

        // Process data
        $data_points = $this->process_oecd_data($data);
        
        return $data_points;
    }

    /**
     * Process OECD API response data
     */
    private function process_oecd_data($data) {
        // Check if we have the required data structure
        if (!isset($data['dataSets'][0]['series']) || 
            !isset($data['structure']['dimensions']['observation'][0]['values'])) {
            return new WP_Error('invalid_response', __('Invalid API response structure', 'zc-dmt'));
        }

        $series = $data['dataSets'][0]['series'];
        $time_dimension = $data['structure']['dimensions']['observation'][0]['values'];
        $data_points = array();

        // Process each series
        foreach ($series as $series_key => $series_data) {
            // Extract time series data
            if (isset($series_data['observations'])) {
                foreach ($series_data['observations'] as $time_index => $observation) {
                    // The observation is an array where the first element is the value
                    $value = isset($observation[0]) ? $observation[0] : null;
                    
                    // Skip null values
                    if ($value === null) {
                        continue;
                    }
                    
                    // Get the time period
                    if (isset($time_dimension[$time_index]['id'])) {
                        $time_period = $time_dimension[$time_index]['id'];
                        
                        // Convert time period to date (assuming annual data)
                        $date = $this->convert_oecd_date($time_period);
                        
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
     * Convert OECD time period to date
     */
    private function convert_oecd_date($oecd_date) {
        // Handle different OECD date formats
        // Annual: "2020"
        if (preg_match('/^(\d{4})$/', $oecd_date, $matches)) {
            return $matches[1] . '-12-31';
        }
        
        // Quarterly: "2020-Q1"
        if (preg_match('/^(\d{4})-Q([1-4])$/', $oecd_date, $matches)) {
            $year = $matches[1];
            $quarter = $matches[2];
            $month = str_pad((($quarter - 1) * 3) + 3, 2, '0', STR_PAD_LEFT);
            return $year . '-' . $month . '-01'; // First day of quarter end month
        }
        
        // Monthly: "2020-01"
        if (preg_match('/^(\d{4})-(\d{2})$/', $oecd_date, $matches)) {
            $year = $matches[1];
            $month = $matches[2];
            return $year . '-' . $month . '-01';
        }
        
        // If we can't parse it, return as is
        return $oecd_date;
    }

    /**
     * Get dataset info from OECD API
     */
    public function get_dataset_info($config) {
        // Validate required fields
        if (empty($config['dataset_id'])) {
            return new WP_Error('missing_dataset_id', __('OECD dataset ID is required', 'zc-dmt'));
        }

        // Build request URL for dataset metadata
        $url = 'https://stats.oecd.org/SDMX-JSON/dataflow/all/' . $config['dataset_id'];
        $params = array(
            'format' => 'json'
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
        
        if (!isset($data['dataflows']) || !is_array($data['dataflows'])) {
            return new WP_Error('invalid_response', __('Invalid API response format for dataset info', 'zc-dmt'));
        }

        return $data['dataflows'][0];
    }

    /**
     * Search datasets in OECD
     */
    public function search_datasets($query, $limit = 20) {
        // Build request URL for search
        $url = 'https://stats.oecd.org/SDMX-JSON/dataflow/all';
        $params = array(
            'format' => 'json'
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
        
        if (!isset($data['dataflows']) || !is_array($data['dataflows'])) {
            return new WP_Error('invalid_response', __('Invalid API response format for search', 'zc-dmt'));
        }

        // Filter results based on query
        $results = array();
        $count = 0;
        
        foreach ($data['dataflows'] as $dataset) {
            if ($count >= $limit) {
                break;
            }
            
            // Check if query matches dataset name or description
            $name = isset($dataset['name']['en']) ? $dataset['name']['en'] : '';
            $description = isset($dataset['description']['en']) ? $dataset['description']['en'] : '';
            
            if (stripos($name, $query) !== false || stripos($description, $query) !== false) {
                $results[] = $dataset;
                $count++;
            }
        }

        return $results;
    }
}
?>
