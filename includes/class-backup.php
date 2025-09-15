<?php
/**
 * ZC DMT Backup Class
 * Handles Google Drive backup integration and fallback data retrieval
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class ZC_DMT_Backup {
    /**
     * Google Drive service instance
     */
    private $drive_service = null;

    /**
     * Backup folder ID
     */
    private $backup_folder_id = null;

    /**
     * Constructor
     */
    public function __construct() {
        // Initialize backup settings
        $this->backup_folder_id = get_option('zc_dmt_google_drive_folder_id', '');
        
        // Initialize Google Drive service if credentials exist
        $this->initialize_drive_service();
    }

    /**
     * Initialize Google Drive service
     */
    private function initialize_drive_service() {
        // Check if required credentials exist
        $client_id = get_option('zc_dmt_google_client_id', '');
        $client_secret = get_option('zc_dmt_google_client_secret', '');
        $refresh_token = get_option('zc_dmt_google_refresh_token', '');
        
        if (empty($client_id) || empty($client_secret) || empty($refresh_token)) {
            return false;
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
                $logger->log('error', 'Backup', 'initialize_drive_service', 'Failed to initialize Google Drive service: ' . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Test Google Drive connection
     */
    public function test_connection() {
        // Check if backup is enabled
        if (!get_option('zc_dmt_enable_drive_backup', false)) {
            return new WP_Error('backup_disabled', __('Google Drive backup is disabled', 'zc-dmt'));
        }

        // Check if Drive service is initialized
        if (!$this->drive_service) {
            return new WP_Error('drive_not_initialized', __('Google Drive service not initialized', 'zc-dmt'));
        }

        try {
            // Try to get about information
            $about = $this->drive_service->about->get(array('fields' => 'user'));
            return array(
                'success' => true,
                'user' => $about->user->displayName,
                'email' => $about->user->emailAddress
            );
        } catch (Exception $e) {
            return new WP_Error('connection_failed', __('Failed to connect to Google Drive: ', 'zc-dmt') . $e->getMessage());
        }
    }

    /**
     * Create backup folder if it doesn't exist
     */
    private function create_backup_folder() {
        // If folder ID is already set, verify it exists
        if (!empty($this->backup_folder_id)) {
            try {
                $folder = $this->drive_service->files->get($this->backup_folder_id, array('fields' => 'id,name'));
                return $folder->id;
            } catch (Exception $e) {
                // Folder doesn't exist, create a new one
                $this->backup_folder_id = '';
            }
        }

        // Create new folder
        try {
            $fileMetadata = new Google_Service_Drive_DriveFile(array(
                'name' => 'ZC DMT Backups',
                'mimeType' => 'application/vnd.google-apps.folder'
            ));

            $folder = $this->drive_service->files->create($fileMetadata, array(
                'fields' => 'id'
            ));

            // Save folder ID to options
            update_option('zc_dmt_google_drive_folder_id', $folder->id);
            $this->backup_folder_id = $folder->id;

            return $folder->id;
        } catch (Exception $e) {
            if (class_exists('ZC_DMT_Error_Logger')) {
                $logger = new ZC_DMT_Error_Logger();
                $logger->log('error', 'Backup', 'create_backup_folder', 'Failed to create backup folder: ' . $e->getMessage());
            }
            return new WP_Error('folder_creation_failed', __('Failed to create backup folder', 'zc-dmt'));
        }
    }

    /**
     * Create backup for an indicator
     */
    public function create_backup($indicator_id) {
        // Check if backup is enabled
        if (!get_option('zc_dmt_enable_drive_backup', false)) {
            return new WP_Error('backup_disabled', __('Google Drive backup is disabled', 'zc-dmt'));
        }

        // Check if Drive service is initialized
        if (!$this->drive_service) {
            return new WP_Error('drive_not_initialized', __('Google Drive service not initialized', 'zc-dmt'));
        }

        // Get database instance
        if (!class_exists('ZC_DMT_Database')) {
            return new WP_Error('database_class_missing', __('Database class not found', 'zc-dmt'));
        }
        
        $db = ZC_DMT_Database::get_instance();

        // Get indicator data
        $indicator = $db->get_indicator_by_id($indicator_id);
        if (!$indicator) {
            return new WP_Error('indicator_not_found', __('Indicator not found', 'zc-dmt'));
        }

        // Get data points
        $data_points = $db->get_data_points($indicator_id);
        if (empty($data_points)) {
            return new WP_Error('no_data_points', __('No data points found for indicator', 'zc-dmt'));
        }

        // Create CSV content
        $csv_content = "Date,Value\n";
        foreach ($data_points as $point) {
            $csv_content .= $point->date . ',' . $point->value . "\n";
        }

        // Create temporary file
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/zc_dmt_backups';
        
        // Create directory if it doesn't exist
        if (!file_exists($temp_dir)) {
            wp_mkdir_p($temp_dir);
        }

        $filename = 'indicator_' . $indicator->slug . '_' . date('Y-m-d_H-i-s') . '.csv';
        $filepath = $temp_dir . '/' . $filename;
        
        // Write CSV to file
        $result = file_put_contents($filepath, $csv_content);
        if ($result === false) {
            return new WP_Error('file_write_failed', __('Failed to create backup file', 'zc-dmt'));
        }

        // Get file size
        $file_size = filesize($filepath);

        // Create backup folder if needed
        $folder_id = $this->create_backup_folder();
        if (is_wp_error($folder_id)) {
            // Clean up temporary file
            unlink($filepath);
            return $folder_id;
        }

        // Upload to Google Drive
        try {
            $fileMetadata = new Google_Service_Drive_DriveFile(array(
                'name' => $filename,
                'parents' => array($folder_id)
            ));

            $content = file_get_contents($filepath);
            $file = $this->drive_service->files->create($fileMetadata, array(
                'data' => $content,
                'mimeType' => 'text/csv',
                'uploadType' => 'multipart',
                'fields' => 'id'
            ));

            // Clean up temporary file
            unlink($filepath);

            // Save backup history
            $history_data = array(
                'indicator_id' => $indicator_id,
                'file_path' => $filepath,
                'drive_file_id' => $file->id,
                'size' => $file_size,
                'status' => 'completed',
                'completed_at' => current_time('mysql')
            );
            
            $result = $db->insert_backup_history($indicator_id, $filepath, $file->id);
            if (is_wp_error($result)) {
                return $result;
            }

            // Log success
            if (class_exists('ZC_DMT_Error_Logger')) {
                $logger = new ZC_DMT_Error_Logger();
                $logger->log('info', 'Backup', 'create_backup', 'Backup created successfully', array(
                    'indicator_id' => $indicator_id,
                    'filename' => $filename,
                    'file_size' => $file_size,
                    'drive_file_id' => $file->id
                ));
            }

            return array(
                'success' => true,
                'file_id' => $file->id,
                'filename' => $filename,
                'size' => $file_size
            );
        } catch (Exception $e) {
            // Clean up temporary file
            if (file_exists($filepath)) {
                unlink($filepath);
            }

            // Log error
            if (class_exists('ZC_DMT_Error_Logger')) {
                $logger = new ZC_DMT_Error_Logger();
                $logger->log('error', 'Backup', 'create_backup', 'Failed to upload backup to Google Drive: ' . $e->getMessage());
            }

            return new WP_Error('upload_failed', __('Failed to upload backup to Google Drive: ', 'zc-dmt') . $e->getMessage());
        }
    }

    /**
     * Get latest backup data for indicator
     */
    public function get_latest_backup_data($indicator_slug) {
        // Check if backup is enabled
        if (!get_option('zc_dmt_enable_drive_backup', false)) {
            return new WP_Error('backup_disabled', __('Google Drive backup is disabled', 'zc-dmt'));
        }

        // Get database instance
        if (!class_exists('ZC_DMT_Database')) {
            return new WP_Error('database_class_missing', __('Database class not found', 'zc-dmt'));
        }
        
        $db = ZC_DMT_Database::get_instance();

        // Get indicator
        $indicator = $db->get_indicator_by_slug($indicator_slug);
        if (!$indicator) {
            return new WP_Error('indicator_not_found', __('Indicator not found', 'zc-dmt'));
        }

        // Get latest backup history
        $backup_history = $db->get_backup_history($indicator->id, 1);
        if (empty($backup_history)) {
            return new WP_Error('no_backup_found', __('No backup found for this indicator', 'zc-dmt'));
        }

        $latest_backup = $backup_history[0];

        // If we have a Drive file ID, try to download from Drive
        if (!empty($latest_backup->drive_file_id) && $this->drive_service) {
            try {
                $content = $this->drive_service->files->get($latest_backup->drive_file_id, array(
                    'alt' => 'media'
                ));
                
                // Parse CSV content
                $lines = explode("\n", $content->getBody()->getContents());
                array_shift($lines); // Remove header
                
                $data_points = array();
                foreach ($lines as $line) {
                    if (!empty($line)) {
                        $parts = explode(',', $line);
                        if (count($parts) == 2) {
                            $data_points[] = array(
                                'date' => trim($parts[0]),
                                'value' => floatval(trim($parts[1]))
                            );
                        }
                    }
                }
                
                return array(
                    'indicator' => $indicator,
                    'data_points' => $data_points
                );
            } catch (Exception $e) {
                // Fall back to local file if Drive download fails
                if (file_exists($latest_backup->file_path)) {
                    $content = file_get_contents($latest_backup->file_path);
                    $lines = explode("\n", $content);
                    array_shift($lines); // Remove header
                    
                    $data_points = array();
                    foreach ($lines as $line) {
                        if (!empty($line)) {
                            $parts = explode(',', $line);
                            if (count($parts) == 2) {
                                $data_points[] = array(
                                    'date' => trim($parts[0]),
                                    'value' => floatval(trim($parts[1]))
                                );
                            }
                        }
                    }
                    
                    return array(
                        'indicator' => $indicator,
                        'data_points' => $data_points
                    );
                }
                
                return new WP_Error('download_failed', __('Failed to download backup from Google Drive', 'zc-dmt'));
            }
        } 
        // If we don't have Drive file ID or service, try local file
        elseif (file_exists($latest_backup->file_path)) {
            $content = file_get_contents($latest_backup->file_path);
            $lines = explode("\n", $content);
            array_shift($lines); // Remove header
            
            $data_points = array();
            foreach ($lines as $line) {
                if (!empty($line)) {
                    $parts = explode(',', $line);
                    if (count($parts) == 2) {
                        $data_points[] = array(
                            'date' => trim($parts[0]),
                            'value' => floatval(trim($parts[1]))
                        );
                    }
                }
            }
            
            return array(
                'indicator' => $indicator,
                'data_points' => $data_points
            );
        }

        return new WP_Error('backup_not_found', __('Backup data not available', 'zc-dmt'));
    }

    /**
     * Schedule backups
     */
    public function schedule_backups() {
        // Check if backup is enabled
        if (!get_option('zc_dmt_enable_drive_backup', false)) {
            // Clear any existing scheduled events
            wp_clear_scheduled_hook('zc_dmt_scheduled_backup');
            return;
        }

        // Get backup schedule
        $schedule = get_option('zc_dmt_backup_schedule', 'daily');
        
        // Clear any existing scheduled events
        wp_clear_scheduled_hook('zc_dmt_scheduled_backup');
        
        // Schedule new event based on setting
        switch ($schedule) {
            case 'hourly':
                wp_schedule_event(time(), 'hourly', 'zc_dmt_scheduled_backup');
                break;
            case 'twicedaily':
                wp_schedule_event(time(), 'twicedaily', 'zc_dmt_scheduled_backup');
                break;
            case 'daily':
            default:
                wp_schedule_event(time(), 'daily', 'zc_dmt_scheduled_backup');
                break;
        }
    }

    /**
     * Run scheduled backup
     */
    public function run_scheduled_backup() {
        // Get database instance
        if (!class_exists('ZC_DMT_Database')) {
            return;
        }
        
        $db = ZC_DMT_Database::get_instance();

        // Get all indicators
        $indicators = $db->get_all_indicators();
        
        // Create backup for each indicator
        foreach ($indicators as $indicator) {
            $this->create_backup($indicator->id);
        }
    }

    /**
     * Clean up old backups based on retention setting
     */
    public function cleanup_old_backups() {
        // Get retention setting (number of backups to keep)
        $retention = get_option('zc_dmt_backup_retention', 30);
        
        // Get database instance
        if (!class_exists('ZC_DMT_Database')) {
            return;
        }
        
        $db = ZC_DMT_Database::get_instance();

        // Get all indicators
        $indicators = $db->get_all_indicators();
        
        foreach ($indicators as $indicator) {
            // Get backup history for this indicator
            $backup_history = $db->get_backup_history($indicator->id);
            
            // If we have more backups than retention setting, delete oldest ones
            if (count($backup_history) > $retention) {
                // Sort by date (oldest first)
                usort($backup_history, function($a, $b) {
                    return strtotime($a->created_at) - strtotime($b->created_at);
                });
                
                // Delete oldest backups
                $to_delete = array_slice($backup_history, 0, count($backup_history) - $retention);
                
                foreach ($to_delete as $backup) {
                    // Delete from Google Drive if file ID exists
                    if (!empty($backup->drive_file_id) && $this->drive_service) {
                        try {
                            $this->drive_service->files->delete($backup->drive_file_id);
                        } catch (Exception $e) {
                            // Log error but continue
                            if (class_exists('ZC_DMT_Error_Logger')) {
                                $logger = new ZC_DMT_Error_Logger();
                                $logger->log('warning', 'Backup', 'cleanup_old_backups', 'Failed to delete backup from Google Drive: ' . $e->getMessage());
                            }
                        }
                    }
                    
                    // Delete local file if it exists
                    if (!empty($backup->file_path) && file_exists($backup->file_path)) {
                        unlink($backup->file_path);
                    }
                    
                    // Delete from database
                    $db->wpdb->delete(
                        $db->backup_history_table,
                        array('id' => $backup->id),
                        array('%d')
                    );
                }
            }
        }
    }
}
?>
