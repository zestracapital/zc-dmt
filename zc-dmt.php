<?php
/**
 * Plugin Name: Zestra Capital - Data Management Tool (DMT)
 * Plugin URI: https://client.zestracapital.com
 * Description: Pure data management system for economic indicators with Google Drive backup and API key security
 * Version: 2.0.0
 * Author: Zestra Capital
 * Text Domain: zc-dmt
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * License: GPL v2 or later
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define constants
define('ZC_DMT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ZC_DMT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ZC_DMT_VERSION', '2.0.0');

// Load required classes
require_once ZC_DMT_PLUGIN_DIR . 'includes/class-database.php';
require_once ZC_DMT_PLUGIN_DIR . 'includes/class-indicators.php';
require_once ZC_DMT_PLUGIN_DIR . 'includes/class-calculations.php';
require_once ZC_DMT_PLUGIN_DIR . 'includes/class-security.php';
require_once ZC_DMT_PLUGIN_DIR . 'includes/class-error-logger.php';
require_once ZC_DMT_PLUGIN_DIR . 'includes/class-csv-importer.php';
require_once ZC_DMT_PLUGIN_DIR . 'includes/class-backup.php';
require_once ZC_DMT_PLUGIN_DIR . 'includes/class-data-sources.php';

// Initialize plugin
class ZC_DMT {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Register admin menu
        add_action('admin_menu', array($this, 'register_admin_menu'));
        
        // Enqueue admin scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // Handle AJAX requests
        add_action('wp_enqueue_scripts', array($this, 'enqueue_public_scripts'));
        add_action('wp_head', array($this, 'add_rest_api_endpoint'));
    }

    public function register_admin_menu() {
        // Add main menu item
        add_menu_page(
            __('ZC DMT Dashboard', 'zc-dmt'),
            __('ZC DMT', 'zc-dmt'),
            'manage_options',
            'zc-dmt-dashboard',
            array($this, 'dashboard_page'),
            'dashicons-chart-bar',
            6
        );

        // Add sub-menu items
        add_submenu_page(
            'zc-dmt-dashboard',
            __('Dashboard', 'zc-dmt'),
            __('Dashboard', 'zc-dmt'),
            'manage_options',
            'zc-dmt-dashboard',
            array($this, 'dashboard_page')
        );

        // API Keys Management Page
        add_submenu_page(
            'zc-dmt-dashboard',
            __('API Keys', 'zc-dmt'),
            __('API Keys', 'zc-dmt'),
            'manage_options',
            'zc-dmt-api-management',
            array($this, 'api_management_page')
        );

        add_submenu_page(
            'zc-dmt-dashboard',
            __('Data Sources', 'zc-dmt'),
            __('Data Sources', 'zc-dmt'),
            'manage_options',
            'zc-dmt-data-sources',
            array($this, 'data_sources_page')
        );

        add_submenu_page(
            'zc-dmt-dashboard',
            __('Indicators', 'zc-dmt'),
            __('Indicators', 'zc-dmt'),
            'manage_options',
            'zc-dmt-indicators',
            array($this, 'indicators_page')
        );

        add_submenu_page(
            'zc-dmt-dashboard',
            __('Manual Calculations', 'zc-dmt'),
            __('Manual Calculations', 'zc-dmt'),
            'manage_options',
            'zc-dmt-calculations',
            array($this, 'calculations_page')
        );

        add_submenu_page(
            'zc-dmt-dashboard',
            __('Backup Settings', 'zc-dmt'),
            __('Backup Settings', 'zc-dmt'),
            'manage_options',
            'zc-dmt-backup',
            array($this, 'backup_settings_page')
        );

        add_submenu_page(
            'zc-dmt-dashboard',
            __('Error Logs', 'zc-dmt'),
            __('Error Logs', 'zc-dmt'),
            'manage_options',
            'zc-dmt-error-logs',
            array($this, 'error_logs_page')
        );

        add_submenu_page(
            'zc-dmt-dashboard',
            __('Settings', 'zc-dmt'),
            __('Settings', 'zc-dmt'),
            'manage_options',
            'zc-dmt-settings',
            array($this, 'settings_page')
        );
    }

    public function dashboard_page() {
        require_once ZC_DMT_PLUGIN_DIR . 'admin/dashboard.php';
    }

    public function api_management_page() {
        require_once ZC_DMT_PLUGIN_DIR . 'admin/api-management.php';
    }

    public function data_sources_page() {
        require_once ZC_DMT_PLUGIN_DIR . 'admin/data-sources.php';
    }

    public function indicators_page() {
        require_once ZC_DMT_PLUGIN_DIR . 'admin/indicators.php';
    }

    public function calculations_page() {
        require_once ZC_DMT_PLUGIN_DIR . 'admin/calculations.php';
    }

    public function backup_settings_page() {
        require_once ZC_DMT_PLUGIN_DIR . 'admin/backup-settings.php';
    }

    public function error_logs_page() {
        require_once ZC_DMT_PLUGIN_DIR . 'admin/error-logs.php';
    }

    public function settings_page() {
        require_once ZC_DMT_PLUGIN_DIR . 'admin/settings.php';
    }

    public function enqueue_admin_scripts($hook) {
        // Only enqueue scripts on our plugin pages
        $pages = array('zc-dmt-dashboard', 'zc-dmt-api-management', 'zc-dmt-data-sources', 'zc-dmt-indicators', 'zc-dmt-calculations', 'zc-dmt-backup', 'zc-dmt-error-logs', 'zc-dmt-settings');
        
        if (in_array($hook, $pages)) {
            wp_enqueue_style('zc-dmt-admin-css', ZC_DMT_PLUGIN_URL . 'assets/css/admin.css', array(), ZC_DMT_VERSION);
            wp_enqueue_script('zc-dmt-admin-js', ZC_DMT_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), ZC_DMT_VERSION, true);
            
            // Localize script with AJAX URL
            wp_localize_script('zc-dmt-admin-js', 'zc_dmt_admin', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'confirm_delete' => __('Are you sure you want to delete this item?', 'zc-dmt'),
                'saving' => __('Saving...', 'zc-dmt'),
                'load_preview' => __('Load Preview', 'zc-dmt'),
                'loading' => __('Loading...', 'zc-dmt')
            ));
        }
    }

    public function enqueue_public_scripts() {
        // Enqueue public scripts
        wp_enqueue_script('zc-dmt-public-js', ZC_DMT_PLUGIN_URL . 'assets/js/public.js', array('jquery'), ZC_DMT_VERSION, true);
    }

    public function add_rest_api_endpoint() {
        // Add REST API endpoint for charts
        add_action('rest_api_init', function() {
            register_rest_route('zc-dmt/v1', '/data/(?P<indicator_id>\d+)', array(
                'methods' => 'GET',
                'callback' => array($this, 'get_indicator_data'),
                'permission_callback' => array($this, 'check_api_key_permission')
            ));
        });
    }

    public function get_indicator_data($request) {
        $indicator_id = $request->get_param('indicator_id');
        $api_key = $request->get_header('X-ZC-DMT-API-Key');

        if (!$api_key || !$this->validate_api_key($api_key)) {
            return new WP_Error('invalid_api_key', 'Invalid API key', array('status' => 401));
        }

        if (!$indicator_id) {
            return new WP_Error('missing_indicator_id', 'Indicator ID is required', array('status' => 400));
        }

        // Get indicator data
        $data_points = ZC_DMT_Database::get_instance()->get_data_points($indicator_id);

        if (is_wp_error($data_points)) {
            return $data_points;
        }

        $response_data = array();
        foreach ($data_points as $point) {
            $response_data[] = array(
                'date' => $point->date,
                'value' => floatval($point->value)
            );
        }

        return rest_ensure_response($response_data);
    }

    public function check_api_key_permission($request) {
        $api_key = $request->get_header('X-ZC-DMT-API-Key');
        return $this->validate_api_key($api_key);
    }

    public function validate_api_key($api_key) {
        if (empty($api_key)) {
            return false;
        }

        // Check if API key exists and is active
        $security = ZC_DMT_Security::get_instance();
        $key = $security->get_api_key_by_hash($api_key);

        if (!$key || !$key->is_active) {
            return false;
        }

        return true;
    }
}

// Initialize the plugin
function zc_dmt_init() {
    ZC_DMT::get_instance();
}
add_action('plugins_loaded', 'zc_dmt_init');

// Register activation and deactivation hooks
function zc_dmt_activate() {
    // Create database tables on activation
    ZC_DMT_Database::get_instance()->create_tables();
}
register_activation_hook(__FILE__, 'zc_dmt_activate');

function zc_dmt_deactivate() {
    // Clear scheduled events on deactivation
    wp_clear_scheduled_hook('zc_dmt_scheduled_backup');
}
register_deactivation_hook(__FILE__, 'zc_dmt_deactivate');
