<?php
/**
 * ZC DMT Security Class
 * Handles API key generation, validation, and security functions
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class ZC_DMT_Security {
    /**
     * Instance of the class (for Singleton pattern)
     */
    private static $instance = null;

    /**
     * Database instance
     */
    private $db;

    /**
     * Get instance of the class (Singleton Pattern)
     * This ensures only one instance exists
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor - MUST BE PUBLIC to allow instantiation
     * Previously was private, causing the fatal error
     */
    public function __construct() {
        // Initialize database if available
        if (class_exists('ZC_DMT_Database')) {
            $this->db = ZC_DMT_Database::get_instance();
        }
    }

    /**
     * Generate a new API key
     */
    public function generate_api_key($name = '') {
        try {
            // Generate a random 32-character hex string
            $key = 'zc_' . bin2hex(random_bytes(16));
            $hash = hash('sha256', $key);
            
            // If no name provided, create a default one
            if (empty($name)) {
                $name = __('API Key - ', 'zc-dmt') . date('Y-m-d H:i:s');
            }
            
            // Insert key into database
            if ($this->db) {
                $result = $this->db->insert_api_key($name, $hash);
                
                if (is_wp_error($result)) {
                    return new WP_Error('key_generation_failed', __('Failed to generate API key.', 'zc-dmt'));
                }
                
                // Return both the key (for display) and its ID
                return array(
                    'key' => $key,
                    'id' => $result,
                    'name' => $name
                );
            } else {
                return new WP_Error('db_not_available', __('Database not available.', 'zc-dmt'));
            }
        } catch (Exception $e) {
            // Log error
            if (class_exists('ZC_DMT_Error_Logger')) {
                $logger = new ZC_DMT_Error_Logger();
                $logger->log('error', 'Security', 'generate_api_key', 'Failed to generate API key: ' . $e->getMessage());
            }
            
            return new WP_Error('key_generation_exception', __('Failed to generate API key.', 'zc-dmt'));
        }
    }

    /**
     * Get all API keys
     */
    public function get_all_keys() {
        if (!$this->db) {
            return array();
        }
        
        return $this->db->get_all_api_keys();
    }

    /**
     * Get active API key by hash
     */
    public function get_active_api_key($api_key_hash) {
        if (!$this->db) {
            return false;
        }
        
        return $this->db->get_active_api_key($api_key_hash);
    }

    /**
     * Validate an API key
     */
    public function validate_api_key($api_key) {
        // Hash the provided key
        $hash = hash('sha256', $api_key);
        
        // Check if key exists and is active
        $key_record = $this->get_active_api_key($hash);
        
        if ($key_record) {
            // Update last used timestamp
            $this->update_api_key_last_used($key_record->id);
            return true;
        }
        
        // Log unauthorized access attempt
        if (class_exists('ZC_DMT_Error_Logger')) {
            $logger = new ZC_DMT_Error_Logger();
            $logger->log('warning', 'Security', 'validate_api_key', sprintf(__('Invalid API key attempt: %s', 'zc-dmt'), substr($api_key, 0, 10) . '...'));
        }
        
        return false;
    }

    /**
     * Update API key last used timestamp
     */
    public function update_api_key_last_used($id) {
        if (!$this->db) {
            return false;
        }
        
        return $this->db->update_api_key_last_used($id);
    }

    /**
     * Revoke (deactivate) an API key
     */
    public function revoke_api_key($id) {
        if (!$this->db) {
            return new WP_Error('db_not_available', __('Database not available.', 'zc-dmt'));
        }
        
        $result = $this->db->revoke_api_key($id);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return true;
    }

    /**
     * Activate an API key
     */
    public function activate_api_key($id) {
        if (!$this->db) {
            return new WP_Error('db_not_available', __('Database not available.', 'zc-dmt'));
        }
        
        $result = $this->db->activate_api_key($id);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return true;
    }

    /**
     * Delete an API key (completely remove from database)
     */
    public function delete_api_key($id) {
        if (!$this->db) {
            return new WP_Error('db_not_available', __('Database not available.', 'zc-dmt'));
        }
        
        $result = $this->db->wpdb->delete(
            $this->db->api_keys_table,
            array('id' => $id),
            array('%d')
        );
        
        if ($result === false) {
            return new WP_Error('delete_key_failed', __('Failed to delete API key.', 'zc-dmt'));
        }
        
        return true;
    }

    /**
     * Create a default API key during plugin activation
     */
    public function create_default_key() {
        // Check if any keys already exist
        $existing_keys = $this->get_all_keys();
        
        if (empty($existing_keys)) {
            // Create a default key
            $result = $this->generate_api_key(__('Default API Key', 'zc-dmt'));
            
            if (is_wp_error($result)) {
                // Log error
                if (class_exists('ZC_DMT_Error_Logger')) {
                    $logger = new ZC_DMT_Error_Logger();
                    $logger->log('error', 'Security', 'create_default_key', 'Failed to create default API key: ' . $result->get_error_message());
                }
                
                return $result;
            }
            
            return $result;
        }
        
        return true;
    }
}
?>
