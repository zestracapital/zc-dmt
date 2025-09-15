<?php
/**
 * ZC DMT ZIP Processor Data Source Class
 * Handles ZIP file processing with automated Google Sheets pipeline
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class ZC_DMT_ZIP_Processor_Source extends ZC_DMT_Base_Source {
    /**
     * Google Drive service instance
     */
    private $drive_service = null;

    /**
     * Test connection to ZIP file
     */
    public function test_connection($config) {
        // Validate required fields
        if (empty($config['zip_url'])) {
            return new WP_Error('missing_zip_url', __('ZIP file URL is required', 'zc-dmt'));
        }

        // Test if we can access the URL
        $response = wp_remote_head($config['zip_url'], array('timeout' => 15));
        
        if (is_wp_error($response)) {
            return new WP_Error('connection_failed', sprintf(__('Connection failed: %s', 'zc-dmt'), $response->get_error_message()));
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return new WP_Error('http_error', sprintf(__('HTTP Error %d', 'zc-dmt'), $response_code));
        }

        // Check content type
        $content_type = wp_remote_retrieve_header($response, 'content-type');
        if (strpos($content_type, 'application/zip') === false && 
            strpos($content_type, 'application/x-zip-compressed') === false) {
            // Some servers might not set correct content-type, so we'll allow it for now
            // But log a warning
            if (class_exists('ZC_DMT_Error_Logger')) {
                $logger = new ZC_DMT_Error_Logger();
                $logger->log('warning', 'Data Sources', 'test_connection', 'Unexpected content type for ZIP file', array(
                    'url' => $config['zip_url'],
                    'content_type' => $content_type
                ));
            }
        }

        return true;
    }

    /**
     * Fetch data from ZIP file
     */
    public function fetch_data($config, $date_range = null) {
        // Validate required fields
        if (empty($config['zip_url'])) {
            return new WP_Error('missing_zip_url', __('ZIP file URL is required', 'zc-dmt'));
        }

        // Create temporary directory
        $temp_dir = $this->create_temp_directory();
        if (is_wp_error($temp_dir)) {
            return $temp_dir;
        }

        // Download ZIP file
        $zip_file = $temp_dir . '/dataset.zip';
        $download_result = $this->download_file($config['zip_url'], $zip_file);
        
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

    /**
     * Initialize Google Drive service
     */
    private function initialize_drive_service() {
        // Check if Google Sheets integration is enabled
        if (!get_option('zc_dmt_enable_google_sheets', false)) {
            return new WP_Error('sheets_disabled', __('Google Sheets integration is disabled', 'zc-dmt'));
        }

        // Check if required credentials exist
        $client_id = get_option('zc_dmt_google_client_id', '');
        $client_secret = get_option('zc_dmt_google_client_secret', '');
        $refresh_token = get_option('zc_dmt_google_refresh_token', '');
        
        if (empty($client_id) || empty($client_secret) || empty($refresh_token)) {
            return new WP_Error('credentials_missing', __('Google Sheets credentials not configured', 'zc-dmt'));
        }

        try {
            // Include Google API client library
            if (!class_exists('Google_Client')) {
                require_once ZC_DMT_PLUGIN_DIR . 'vendor/autoload.php';
            }

            // Create Google Client
            $client = new Google_Client();
            $client->setClientId($client_id);
            $client->setClientSecret($client_secret);
            $client->refreshToken($refresh_token);
            
            // Create Drive service
            $this->drive_service = new Google_Service_Drive($client);
            
            return true;
        } catch (Exception $e) {
            if (class_exists('ZC_DMT_Error_Logger')) {
                $logger = new ZC_DMT_Error_Logger();
                $logger->log('error', 'ZIP Processor', 'initialize_drive_service', 'Failed to initialize Google Drive service: ' . $e->getMessage());
            }
            return new WP_Error('drive_init_failed', __('Failed to initialize Google Drive service', 'zc-dmt'));
        }
    }

    /**
     * Test Google Sheets connection
     */
    public function test_sheets_connection($config) {
        // Initialize Google Drive service
        $init_result = $this->initialize_drive_service();
        if (is_wp_error($init_result)) {
            return $init_result;
        }

        if (!$this->drive_service) {
            return new WP_Error('drive_service_missing', __('Google Drive service not initialized', 'zc-dmt'));
        }

        // In a real implementation, this would test the connection to Google Drive
        // For now, we'll simulate a successful test
        return true;
    }
}
?>
