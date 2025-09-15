<?php
/**
 * ZC DMT CSV Importer Class
 * Handles CSV file parsing and data import functionality
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class ZC_DMT_CSV_Importer {
    /**
     * Database instance
     */
    private $db;

    /**
     * Constructor
     */
    public function __construct() {
        $this->db = ZC_DMT_Database::get_instance();
    }

    /**
     * Import CSV file
     */
    public function import_csv($file, $indicator_id) {
        // Validate file
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return new WP_Error('invalid_file', __('Invalid file upload.', 'zc-dmt'));
        }

        // Check file size (limit to 10MB)
        if ($file['size'] > 10 * 1024 * 1024) {
            return new WP_Error('file_too_large', __('File size exceeds 10MB limit.', 'zc-dmt'));
        }

        // Get indicator
        $indicator = $this->db->get_indicator_by_id($indicator_id);
        if (!$indicator) {
            return new WP_Error('indicator_not_found', __('Indicator not found.', 'zc-dmt'));
        }

        // Open file
        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            return new WP_Error('file_open_failed', __('Failed to open uploaded file.', 'zc-dmt'));
        }

        // Read header
        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            return new WP_Error('invalid_csv', __('Invalid CSV file format.', 'zc-dmt'));
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
            return new WP_Error('missing_date_column', __('Could not find date column in CSV.', 'zc-dmt'));
        }

        if ($value_col === null) {
            fclose($handle);
            return new WP_Error('missing_value_column', __('Could not find value column in CSV.', 'zc-dmt'));
        }

        // Process rows
        $processed = 0;
        $imported = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $processed++;

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

            // Save data point
            $result = $this->db->insert_data_point($indicator_id, $obs_date, $value);
            if (!is_wp_error($result)) {
                $imported++;
            }
        }

        fclose($handle);

        // Log success
        if (class_exists('ZC_DMT_Error_Logger')) {
            $logger = new ZC_DMT_Error_Logger();
            $logger->log('info', 'Importer', 'import_csv', 'CSV import completed', array(
                'indicator_id' => $indicator_id,
                'indicator_name' => $indicator->name,
                'file_name' => $file['name'],
                'processed_rows' => $processed,
                'imported_rows' => $imported
            ));
        }

        return array(
            'processed' => $processed,
            'imported' => $imported
        );
    }

    /**
     * Import CSV from URL
     */
    public function import_url($url, $indicator_id) {
        // Validate URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return new WP_Error('invalid_url', __('Invalid URL provided.', 'zc-dmt'));
        }

        // Get indicator
        $indicator = $this->db->get_indicator_by_id($indicator_id);
        if (!$indicator) {
            return new WP_Error('indicator_not_found', __('Indicator not found.', 'zc-dmt'));
        }

        // Create temporary file
        $temp_file = wp_tempnam('zc_dmt_import');
        if (!$temp_file) {
            return new WP_Error('temp_file_failed', __('Failed to create temporary file.', 'zc-dmt'));
        }

        // Download file
        $response = wp_remote_get($url, array('timeout' => 30));
        
        if (is_wp_error($response)) {
            unlink($temp_file);
            return new WP_Error('download_failed', sprintf(__('Failed to download file: %s', 'zc-dmt'), $response->get_error_message()));
        }

        $http_code = wp_remote_retrieve_response_code($response);
        if ($http_code !== 200) {
            unlink($temp_file);
            return new WP_Error('http_error', sprintf(__('HTTP Error %d during download', 'zc-dmt'), $http_code));
        }

        $content = wp_remote_retrieve_body($response);
        
        // Check file size (limit to 10MB)
        if (strlen($content) > 10 * 1024 * 1024) {
            unlink($temp_file);
            return new WP_Error('file_too_large', __('Downloaded file exceeds 10MB limit.', 'zc-dmt'));
        }

        // Save content to temporary file
        file_put_contents($temp_file, $content);

        // Open file
        $handle = fopen($temp_file, 'r');
        if (!$handle) {
            unlink($temp_file);
            return new WP_Error('file_open_failed', __('Failed to open downloaded file.', 'zc-dmt'));
        }

        // Read header
        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            unlink($temp_file);
            return new WP_Error('invalid_csv', __('Invalid CSV file format.', 'zc-dmt'));
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
            unlink($temp_file);
            return new WP_Error('missing_date_column', __('Could not find date column in CSV.', 'zc-dmt'));
        }

        if ($value_col === null) {
            fclose($handle);
            unlink($temp_file);
            return new WP_Error('missing_value_column', __('Could not find value column in CSV.', 'zc-dmt'));
        }

        // Process rows
        $processed = 0;
        $imported = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $processed++;

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

            // Save data point
            $result = $this->db->insert_data_point($indicator_id, $obs_date, $value);
            if (!is_wp_error($result)) {
                $imported++;
            }
        }

        fclose($handle);
        unlink($temp_file);

        // Log success
        if (class_exists('ZC_DMT_Error_Logger')) {
            $logger = new ZC_DMT_Error_Logger();
            $logger->log('info', 'Importer', 'import_url', 'URL import completed', array(
                'indicator_id' => $indicator_id,
                'indicator_name' => $indicator->name,
                'url' => $url,
                'processed_rows' => $processed,
                'imported_rows' => $imported
            ));
        }

        return array(
            'processed' => $processed,
            'imported' => $imported
        );
    }

    /**
     * Parse CSV sample for column mapping
     */
    public function parse_csv_sample($file) {
        // Validate file
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return new WP_Error('invalid_file', __('Invalid file upload.', 'zc-dmt'));
        }

        // Check file size (limit to 10MB)
        if ($file['size'] > 10 * 1024 * 1024) {
            return new WP_Error('file_too_large', __('File size exceeds 10MB limit.', 'zc-dmt'));
        }

        // Open file
        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            return new WP_Error('file_open_failed', __('Failed to open uploaded file.', 'zc-dmt'));
        }

        // Read header
        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            return new WP_Error('invalid_csv', __('Invalid CSV file format.', 'zc-dmt'));
        }

        // Read first 5 data rows
        $sample_data = array();
        $row_count = 0;
        
        while (($row = fgetcsv($handle)) !== false && $row_count < 5) {
            $sample_data[] = $row;
            $row_count++;
        }

        fclose($handle);

        return array(
            'header' => $header,
            'sample_data' => $sample_data
        );
    }
}
?>
