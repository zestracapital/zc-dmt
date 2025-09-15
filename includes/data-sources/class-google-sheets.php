<?php
/**
 * ZC DMT Google Sheets Data Source Class
 * Handles integration with Google Sheets via export links
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class ZC_DMT_GoogleSheets_Source extends ZC_DMT_Base_Source {
    /**
     * Test connection to Google Sheets
     */
    public function test_connection($config) {
        // Validate required fields
        if (empty($config['sheet_url'])) {
            return new WP_Error('missing_sheet_url', __('Google Sheets URL is required', 'zc-dmt'));
        }

        // Convert to export URL if needed
        $export_url = $this->convert_to_export_url($config['sheet_url']);
        if (is_wp_error($export_url)) {
            return $export_url;
        }

        // Make a test request
        $response = wp_remote_head($export_url, array('timeout' => 15));
        
        if (is_wp_error($response)) {
            return new WP_Error('connection_failed', sprintf(__('Connection failed: %s', 'zc-dmt'), $response->get_error_message()));
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return new WP_Error('http_error', sprintf(__('HTTP Error %d', 'zc-dmt'), $response_code));
        }

        // Check content type
        $content_type = wp_remote_retrieve_header($response, 'content-type');
        if (strpos($content_type, 'text/csv') === false && 
            strpos($content_type, 'application/vnd.ms-excel') === false &&
            strpos($content_type, 'text/plain') === false) {
            // Some servers might not set correct content-type, so we'll allow it for now
            // But log a warning
            if (class_exists('ZC_DMT_Error_Logger')) {
                $logger = new ZC_DMT_Error_Logger();
                $logger->log('warning', 'Data Sources', 'test_connection', 'Unexpected content type for Google Sheets CSV', array(
                    'url' => $export_url,
                    'content_type' => $content_type
                ));
            }
        }

        return true;
    }

    /**
     * Fetch data from Google Sheets
     */
    public function fetch_data($config, $date_range = null) {
        // Validate required fields
        if (empty($config['sheet_url'])) {
            return new WP_Error('missing_sheet_url', __('Google Sheets URL is required', 'zc-dmt'));
        }

        // Convert to export URL
        $export_url = $this->convert_to_export_url($config['sheet_url']);
        if (is_wp_error($export_url)) {
            return $export_url;
        }

        // Make API request
        $response = wp_remote_get($export_url, array('timeout' => 30));
        
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
            return new WP_Error('no_data', __('No data returned from Google Sheets', 'zc-dmt'));
        }

        // Parse CSV data
        $lines = explode("\n", trim($body));
        
        // Check if we have enough lines (header + data)
        if (count($lines) < 2) {
            return new WP_Error('invalid_response', __('Invalid response format from Google Sheets', 'zc-dmt'));
        }

        // Read header
        $header = str_getcsv($lines[0]);
        if (!$header) {
            return new WP_Error('invalid_response', __('Invalid header in Google Sheets CSV', 'zc-dmt'));
        }

        // Find date and value columns
        $date_col = null;
        $value_col = null;

        foreach ($header as $index => $column) {
            $column = strtolower(trim($column));
            if (in_array($column, array('date', 'obs_date', 'observation_date', 'time'))) {
                $date_col = $index;
            } elseif (in_array($column, array('value', 'obs_value', 'observation_value'))) {
                $value_col = $index;
            }
        }

        if ($date_col === null) {
            return new WP_Error('missing_date_column', __('Could not find date column in Google Sheets', 'zc-dmt'));
        }

        if ($value_col === null) {
            return new WP_Error('missing_value_column', __('Could not find value column in Google Sheets', 'zc-dmt'));
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
            
            // Check if we have enough columns
            if (count($parts) <= max($date_col, $value_col)) {
                continue;
            }
            
            $obs_date = isset($parts[$date_col]) ? trim($parts[$date_col]) : '';
            $value = isset($parts[$value_col]) ? trim($parts[$value_col]) : '';

            // Validate date
            $date_obj = DateTime::createFromFormat('Y-m-d', $obs_date);
            if (!$date_obj || $date_obj->format('Y-m-d') !== $obs_date) {
                // Try other common formats
                $date_obj = DateTime::createFromFormat('m/d/Y', $obs_date);
                if (!$date_obj) {
                    $date_obj = DateTime::createFromFormat('d/m/Y', $obs_date);
                }
                if (!$date_obj) {
                    continue; // Skip invalid dates
                }
                $obs_date = $date_obj->format('Y-m-d');
            }

            // Validate value
            if (!is_numeric($value)) {
                continue; // Skip non-numeric values
            }

            // Apply date range filter if provided
            if ($date_range) {
                $date_timestamp = strtotime($obs_date);
                if (isset($date_range['start_date']) && $date_timestamp < strtotime($date_range['start_date'])) {
                    continue;
                }
                if (isset($date_range['end_date']) && $date_timestamp > strtotime($date_range['end_date'])) {
                    continue;
                }
            }

            $processed_data[] = array(
                'date' => $obs_date,
                'value' => floatval($value)
            );
        }

        return $processed_data;
    }

    /**
     * Convert Google Sheets URL to export URL
     */
    private function convert_to_export_url($sheet_url) {
        // Validate URL
        if (!filter_var($sheet_url, FILTER_VALIDATE_URL)) {
            return new WP_Error('invalid_url', __('Invalid Google Sheets URL format', 'zc-dmt'));
        }

        // Check if it's already an export URL
        if (strpos($sheet_url, '/export?') !== false) {
            return $sheet_url;
        }

        // Parse the URL
        $parsed_url = parse_url($sheet_url);
        
        // Check if it's a Google Sheets URL
        if (!isset($parsed_url['host']) || strpos($parsed_url['host'], 'docs.google.com') === false) {
            return new WP_Error('invalid_url', __('Invalid Google Sheets URL format', 'zc-dmt'));
        }

        // Extract spreadsheet ID
        if (!isset($parsed_url['path'])) {
            return new WP_Error('invalid_url', __('Invalid Google Sheets URL format', 'zc-dmt'));
        }

        // Match spreadsheet ID from URL patterns
        if (preg_match('/\/spreadsheets\/d\/([a-zA-Z0-9_-]+)/', $parsed_url['path'], $matches)) {
            $spreadsheet_id = $matches[1];
        } else {
            return new WP_Error('invalid_url', __('Could not extract spreadsheet ID from URL', 'zc-dmt'));
        }

        // Build export URL
        $export_url = 'https://docs.google.com/spreadsheets/d/' . $spreadsheet_id . '/export?format=csv';
        
        // Add sheet ID if present in original URL
        if (isset($parsed_url['query'])) {
            parse_str($parsed_url['query'], $query_params);
            if (isset($query_params['gid'])) {
                $export_url .= '&gid=' . $query_params['gid'];
            }
        }

        return $export_url;
    }

    /**
     * Get sheet info
     */
    public function get_sheet_info($config) {
        // Validate required fields
        if (empty($config['sheet_url'])) {
            return new WP_Error('missing_sheet_url', __('Google Sheets URL is required', 'zc-dmt'));
        }

        // For Google Sheets, we don't have a specific API endpoint for sheet info
        // We'll return basic info based on the URL
        return array(
            'url' => $config['sheet_url'],
            'source' => 'Google Sheets'
        );
    }

    /**
     * Search for sheets (not supported)
     */
    public function search_sheets($query, $limit = 10) {
        // Google Sheets doesn't have a public search API
        // This is a placeholder implementation
        return new WP_Error('not_supported', __('Sheet search is not supported for Google Sheets', 'zc-dmt'));
    }
}
?>
