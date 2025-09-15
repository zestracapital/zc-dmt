<?php
/**
 * ZC DMT Canada Open Data Source Class
 * Handles integration with Canadian Open Data Portal with ZIP processing
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class ZC_DMT_CanadaOpen_Source extends ZC_DMT_Base_Source {
    /**
     * Test connection to Canada Open Data
     */
    public function test_connection($config) {
        // Validate required fields
        if (empty($config['dataset_url'])) {
            return new WP_Error('missing_dataset_url', __('Canada Open Data dataset URL is required', 'zc-dmt'));
        }

        // Check if URL points to a ZIP file
        if (substr($config['dataset_url'], -4) !== '.zip') {
            return new WP_Error('invalid_url', __('Dataset URL must point to a ZIP file', 'zc-dmt'));
        }

        // Test download
        $response = wp_remote_head($config['dataset_url'], array('timeout' => 15));
        
        if (is_wp_error($response)) {
            return new WP_Error('connection_failed', sprintf(__('Connection failed: %s', 'zc-dmt'), $response->get_error_message()));
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return new WP_Error('http_error', sprintf(__('HTTP Error %d', 'zc-dmt'), $response_code));
        }

        return true;
    }

    /**
     * Fetch data from Canada Open Data (ZIP processing)
     */
    public function fetch_data($config, $date_range = null) {
        // Validate required fields
        if (empty($config['dataset_url'])) {
            return new WP_Error('missing_dataset_url', __('Canada Open Data dataset URL is required', 'zc-dmt'));
        }

        // Create temporary directory
        $temp_dir = $this->create_temp_directory();
        if (is_wp_error($temp_dir)) {
            return $temp_dir;
        }

        // Download ZIP file
        $zip_file = $temp_dir . '/dataset.zip';
        $download_result = $this->download_file($config['dataset_url'], $zip_file);
        
        if (is_wp_error($download_result)) {
            // Clean up
            $this->remove_temp_directory($temp_dir);
            return $download_result;
        }

        // Extract ZIP file
        $extract_result = $this->extract_zip($zip_file, $temp_dir);
        if (is_wp_error($extract_result)) {
            // Clean up
            $this->remove_temp_directory($temp_dir);
            return $extract_result;
        }

        // Find CSV files
        $csv_files = glob($temp_dir . '/*.csv');
        if (empty($csv_files)) {
            // Clean up
            $this->remove_temp_directory($temp_dir);
            return new WP_Error('no_csv_files', __('No CSV files found in ZIP archive', 'zc-dmt'));
        }

        // Process first CSV file (in a real implementation, you might want to process all or let user choose)
        $csv_file = $csv_files[0];
        $data_points = $this->parse_csv($csv_file, $date_range);
        
        // Clean up
        $this->remove_temp_directory($temp_dir);
        
        return $data_points;
    }

    /**
     * Create temporary directory
     */
    private function create_temp_directory() {
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/zc_dmt_temp_' . uniqid();
        
        if (!wp_mkdir_p($temp_dir)) {
            return new WP_Error('temp_dir_failed', __('Failed to create temporary directory', 'zc-dmt'));
        }
        
        return $temp_dir;
    }

    /**
     * Remove temporary directory
     */
    private function remove_temp_directory($temp_dir) {
        if (is_dir($temp_dir)) {
            $files = glob($temp_dir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($temp_dir);
        }
    }

    /**
     * Download file
     */
    private function download_file($url, $destination) {
        $response = wp_remote_get($url, array(
            'timeout' => 30,
            'stream' => true,
            'filename' => $destination
        ));
        
        if (is_wp_error($response)) {
            return new WP_Error('download_failed', sprintf(__('Failed to download file: %s', 'zc-dmt'), $response->get_error_message()));
        }

        $http_code = wp_remote_retrieve_response_code($response);
        if ($http_code !== 200) {
            return new WP_Error('http_error', sprintf(__('HTTP Error %d during download', 'zc-dmt'), $http_code));
        }

        if (!file_exists($destination)) {
            return new WP_Error('file_not_created', __('Downloaded file was not created', 'zc-dmt'));
        }

        return true;
    }

    /**
     * Extract ZIP file
     */
    private function extract_zip($zip_file, $destination) {
        if (!class_exists('ZipArchive')) {
            return new WP_Error('ziparchive_missing', __('PHP ZipArchive extension is required for ZIP processing', 'zc-dmt'));
        }

        $zip = new ZipArchive();
        $result = $zip->open($zip_file);
        
        if ($result !== true) {
            return new WP_Error('zip_open_failed', sprintf(__('Failed to open ZIP file. Error code: %d', 'zc-dmt'), $result));
        }

        $extract_result = $zip->extractTo($destination);
        $zip->close();
        
        if (!$extract_result) {
            return new WP_Error('zip_extract_failed', __('Failed to extract ZIP file', 'zc-dmt'));
        }

        return true;
    }

    /**
     * Parse CSV file
     */
    private function parse_csv($csv_file, $date_range = null) {
        if (!file_exists($csv_file)) {
            return new WP_Error('file_not_found', sprintf(__('CSV file not found: %s', 'zc-dmt'), $csv_file));
        }

        $handle = fopen($csv_file, 'r');
        if (!$handle) {
            return new WP_Error('file_open_failed', sprintf(__('Failed to open CSV file: %s', 'zc-dmt'), $csv_file));
        }

        // Read header
        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            return new WP_Error('empty_file', sprintf(__('CSV file is empty: %s', 'zc-dmt'), $csv_file));
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
            fclose($handle);
            return new WP_Error('missing_date_column', sprintf(__('Could not find date column in CSV: %s', 'zc-dmt'), $csv_file));
        }

        if ($value_col === null) {
            fclose($handle);
            return new WP_Error('missing_value_column', sprintf(__('Could not find value column in CSV: %s', 'zc-dmt'), $csv_file));
        }

        // Process rows
        $data_points = array();

        while (($row = fgetcsv($handle)) !== false) {
            // Skip empty rows
            if (empty(array_filter($row))) {
                continue;
            }

            // Get date and value
            $obs_date = isset($row[$date_col]) ? trim($row[$date_col]) : '';
            $value = isset($row[$value_col]) ? trim($row[$value_col]) : '';

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

            $data_points[] = array(
                'date' => $obs_date,
                'value' => $value
            );
        }

        fclose($handle);
        return $data_points;
    }
}
?>
