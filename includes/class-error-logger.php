<?php
/**
 * ZC DMT Error Logger Class
 * Handles error logging, storage, and email alerts
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class ZC_DMT_Error_Logger {
    /**
     * Database instance
     */
    private $db;

    /**
     * Constructor
     */
    public function __construct() {
        // Only initialize database if we're not in early loading stages
        if (class_exists('ZC_DMT_Database')) {
            $this->db = ZC_DMT_Database::get_instance();
        }
    }

    /**
     * Log an error message
     */
    public function log($level, $module, $action, $message, $context = array()) {
        // Validate log level
        $valid_levels = array('info', 'warning', 'error', 'critical');
        if (!in_array($level, $valid_levels)) {
            $level = 'info'; // Default to info if invalid level
        }

        // Prepare context data
        $context_data = array(
            'module' => $module,
            'action' => $action,
            'context' => $context,
            'timestamp' => current_time('mysql')
        );

        // Log to database if available
        if ($this->db) {
            try {
                $this->db->log_error($level, $module, $action, $message, $context);
            } catch (Exception $e) {
                // If database logging fails, fallback to file logging
                error_log(sprintf(
                    '[ZC DMT] [%s] [%s:%s] %s - Context: %s - DB Error: %s',
                    strtoupper($level),
                    $module,
                    $action,
                    $message,
                    json_encode($context),
                    $e->getMessage()
                ));
            }
        } else {
            // Fallback to WordPress error log
            error_log(sprintf(
                '[ZC DMT] [%s] [%s:%s] %s - Context: %s',
                strtoupper($level),
                $module,
                $action,
                $message,
                json_encode($context)
            ));
        }

        // Send email alert for critical errors if enabled
        if ($level === 'critical' && get_option('zc_dmt_email_alerts_enabled', 0)) {
            $this->send_alert($level, $module, $action, $message, $context);
        }
    }

    /**
     * Log an info message
     */
    public function info($module, $action, $message, $context = array()) {
        $this->log('info', $module, $action, $message, $context);
    }

    /**
     * Log a warning message
     */
    public function warning($module, $action, $message, $context = array()) {
        $this->log('warning', $module, $action, $message, $context);
    }

    /**
     * Log an error message
     */
    public function error($module, $action, $message, $context = array()) {
        $this->log('error', $module, $action, $message, $context);
    }

    /**
     * Log a critical error message
     */
    public function critical($module, $action, $message, $context = array()) {
        $this->log('critical', $module, $action, $message, $context);
    }

    /**
     * Send email alert for critical errors
     */
    private function send_alert($level, $module, $action, $message, $context = array()) {
        $to = get_option('zc_dmt_alert_email', get_option('admin_email'));
        $subject = sprintf(__('ZC DMT Critical Error Alert - %s', 'zc-dmt'), $module);
        
        $message_body = sprintf(
            __("A critical error occurred in the ZC DMT plugin:\n\nModule: %s\nAction: %s\nLevel: %s\nMessage: %s\nTime: %s\n\nContext:\n%s", 'zc-dmt'),
            $module,
            $action,
            strtoupper($level),
            $message,
            current_time('mysql'),
            print_r($context, true)
        );
        
        $headers = array('Content-Type: text/plain; charset=UTF-8');
        
        wp_mail($to, $subject, $message_body, $headers);
    }

    /**
     * Get recent error logs
     */
    public function get_recent_logs($limit = 50) {
        if (!$this->db) {
            return array();
        }
        
        return $this->db->get_error_logs(array('limit' => $limit));
    }

    /**
     * Get error log counts by level
     */
    public function get_log_level_counts() {
        if (!$this->db) {
            return array(
                'info' => 0,
                'warning' => 0,
                'error' => 0,
                'critical' => 0
            );
        }
        
        return $this->db->get_error_log_counts();
    }

    /**
     * Clear all error logs
     */
    public function clear_logs() {
        if (!$this->db) {
            return false;
        }
        
        return $this->db->clear_error_logs();
    }

    /**
     * Get logs with filtering options
     */
    public function get_logs($args = array()) {
        if (!$this->db) {
            return array();
        }
        
        return $this->db->get_error_logs($args);
    }
}
?>
