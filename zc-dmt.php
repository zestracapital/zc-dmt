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
// require_once ZC_DMT_PLUGIN_DIR . 'includes/class-csv-importer.php'; // Commented out as it's not in the guide structure
require_once ZC_DMT_PLUGIN_DIR . 'includes/class-backup.php';
require_once ZC_DMT_PLUGIN_DIR . 'includes/class-data-sources.php';
// Note: class-rest-api.php is not explicitly loaded here but might be included by other classes if needed.

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
        
        // Handle loading of admin pages
        add_action('admin_init', array($this, 'handle_admin_pages'));

        // Enqueue admin scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // Handle AJAX requests for Data Sources page
        add_action('wp_ajax_zc_dmt_get_source_config', array($this, 'ajax_get_source_config'));
		// If you want non-logged-in users to potentially access this (unlikely for admin), you'd use:
		// add_action('wp_ajax_nopriv_zc_dmt_get_source_config', array($this, 'ajax_get_source_config')); 

        // Handle form submission via AJAX from data-sources.php page
        add_action('wp_ajax_zc_dmt_add_source_from_data_sources_page', array($this, 'ajax_add_source_from_data_sources_page'));

        // --- Potentially add REST API endpoints here or in a dedicated class ---
        // The current code adds one endpoint, but the guide suggests a full REST API class.
        // add_action('rest_api_init', array($this, 'register_rest_routes')); // Example if using a method in this class
    }

    /**
     * Handles all admin page requests by checking capabilities and including the correct file.
     * This fixes the "You are not allowed to access this page" errors and potential admin bar conflicts.
     */
    public function handle_admin_pages() {
        // Get the current page slug from the query
        $page = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';

        // Define an array of valid page slugs and their corresponding files
        // This list should match the files you have in your /admin directory
        $pages = array(
            // Main pages from the menu
            'zc-dmt-dashboard' => 'dashboard.php',
            // 'zc-dmt-api-management' => 'api-management.php', // Uncomment if this file exists
            'zc-dmt-data-sources' => 'data-sources.php',
            'zc-dmt-indicators' => 'indicators.php',
            'zc-dmt-calculations' => 'calculations.php',
            'zc-dmt-backup' => 'backup-settings.php',
            'zc-dmt-error-logs' => 'error-logs.php',
            'zc-dmt-settings' => 'settings.php',
            
            // Specific action pages for Data Sources and Indicators
            'zc-dmt-add-source' => 'add-source.php',
            // 'zc-dmt-edit-source' => 'edit-source.php', // Add if this file exists
            // 'zc-dmt-delete-source' => 'delete-source.php', // Add if this file exists
            'zc-dmt-fetch-data' => 'fetch-indicator-data.php', // Assuming this file exists
            // 'zc-dmt-test-connection' => 'test-connection.php', // Add if this file exists
            
            // Specific action pages for Indicators
            'zc-dmt-add-indicator' => 'add-indicator.php', // Assuming this file exists
            // 'zc-dmt-edit-indicator' => 'edit-indicator.php', // Add if this file exists
            // 'zc-dmt-delete-indicator' => 'delete-indicator.php', // Add if this file exists
            
            // Specific action pages for Calculations
            // 'zc-dmt-add-calculation' => 'add-calculation.php', // Add if this file exists
            // 'zc-dmt-edit-calculation' => 'edit-calculation.php', // Add if this file exists
            // 'zc-dmt-delete-calculation' => 'delete-calculation.php', // Add if this file exists
            // 'zc-dmt-execute-calculation' => 'execute-calculation.php', // Add if this file exists
            
            // Importer page
            // 'zc-dmt-importer' => 'importer.php', // Add if this file exists
        );

        // Check if the requested page is valid and the file exists
        if (isset($pages[$page]) && file_exists(ZC_DMT_PLUGIN_DIR . 'admin/' . $pages[$page])) {
            // Check if the user has the required capability (this check is also often inside the individual files)
            if (!current_user_can('manage_options')) {
                 // Using wp_die is standard for WordPress admin access errors
                wp_die(__('You do not have sufficient permissions to access this page.', 'zc-dmt'));
            }

            // Include the corresponding PHP file from the admin directory
            require_once ZC_DMT_PLUGIN_DIR . 'admin/' . $pages[$page];
            // Exit to prevent the default WordPress admin page loading
            exit; 
        }
        // If the page is not in our list or the file doesn't exist, 
        // WordPress will handle it normally (likely showing a "Cannot load ..." message)
        // This ensures other admin pages (Posts, Pages, etc.) are unaffected.
    }


    // --- Add this method for the new AJAX handler ---
    /**
     * Handles the AJAX request from data-sources.php to get the configuration form for a selected source type.
     * Action: wp_ajax_zc_dmt_get_source_config
     */
    public function ajax_get_source_config() {
        // Check nonce for security
        check_ajax_referer('zc_dmt_get_source_config_nonce', 'nonce');

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'zc-dmt')));
        }

        $source_type = isset($_POST['source_type']) ? sanitize_key($_POST['source_type']) : '';

        if (empty($source_type)) {
            wp_send_json_error(array('message' => __('Invalid source type.', 'zc-dmt')));
        }

        // Get available data source types
        if (!class_exists('ZC_DMT_Data_Sources')) {
            wp_send_json_error(array('message' => __('Data sources class not found.', 'zc-dmt')));
        }

        $data_sources = new ZC_DMT_Data_Sources();
        $source_info = $data_sources->get_source($source_type);

        if (!$source_info) {
            // Handle case where source might be custom/unknown but editing an existing one
            // For adding, this is an error. For editing, logic is in add-source.php.
            // Here, assume it's for adding, so it's an error.
            wp_send_json_error(array('message' => __('Source type not found.', 'zc-dmt')));
        }

        // --- Generate the HTML for the configuration form ---
        ob_start(); // Start output buffering

        // Hidden fields for source type and action
        echo '<form method="post" action="">'; // Wrap in a form for submission handling
        echo '<input type="hidden" name="source_type" value="' . esc_attr($source_type) . '">';
        echo '<input type="hidden" name="zc_dmt_add_source_nonce" value="' . esc_attr(wp_create_nonce('zc_dmt_add_source')) . '">';

        // Source Details Section (Basic fields)
        echo '<div class="zc-form-section">';
        // Removed h3 as it's handled in the main page now
        echo '<table class="form-table">';

        echo '<tr>';
        echo '<th scope="row"><label for="source_name_ajax">' . __('Name', 'zc-dmt') . '</label></th>';
        echo '<td>';
        echo '<input type="text" name="source_name" id="source_name_ajax" value="" class="regular-text" required>';
        echo '<p class="description">' . __('Enter a descriptive name for this data source.', 'zc-dmt') . '</p>';
        echo '</td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th scope="row"><label for="source_slug_ajax">' . __('Slug', 'zc-dmt') . '</label></th>';
        echo '<td>';
        echo '<input type="text" name="source_slug" id="source_slug_ajax" value="" class="regular-text">';
        echo '<p class="description">' . __('A unique identifier for this data source.', 'zc-dmt') . '</p>';
        echo '</td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th scope="row"><label for="source_description_ajax">' . __('Description', 'zc-dmt') . '</label></th>';
        echo '<td>';
        echo '<textarea name="source_description" id="source_description_ajax" class="large-text" rows="3"></textarea>';
        echo '<p class="description">' . __('A brief description of this data source.', 'zc-dmt') . '</p>';
        echo '</td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th scope="row">' . __('Active', 'zc-dmt') . '</th>';
        echo '<td>';
        echo '<label>';
        echo '<input type="checkbox" name="source_active" id="source_active_ajax" value="1" checked>';
        echo ' ' . __('Enable this data source', 'zc-dmt');
        echo '</label>';
        echo '<p class="description">' . __('Uncheck to disable this data source without deleting it.', 'zc-dmt') . '</p>';
        echo '</td>';
        echo '</tr>';

        echo '</table>';
        echo '</div>'; // .zc-form-section

        // Source Configuration Section (Dynamic fields)
        echo '<div class="zc-form-section">';
        // Removed h3 as it's handled in the main page now

        if (isset($source_info['config_fields']) && is_array($source_info['config_fields']) && !empty($source_info['config_fields'])) {
            echo '<div class="zc-config-fields">';
            // Removed h4 as it's handled in the main page now
            echo '<table class="form-table">';

            foreach ($source_info['config_fields'] as $field) {
                echo '<tr>';
                echo '<th scope="row"><label for="' . esc_attr($source_type . '_' . $field . '_ajax') . '">' . esc_html(ucwords(str_replace('_', ' ', $field))) . '</label></th>';
                echo '<td>';
                echo '<input type="text" name="' . esc_attr($source_type . '_' . $field) . '" id="' . esc_attr($source_type . '_' . $field . '_ajax') . '" value="" class="regular-text">';
                echo '<p class="description">' . sprintf(__('Enter the %s for this data source.', 'zc-dmt'), esc_html(ucwords(str_replace('_', ' ', $field)))) . '</p>';
                echo '</td>';
                echo '</tr>';
            }

            echo '</table>';
            echo '</div>'; // .zc-config-fields
        } else {
            echo '<p>' . __('No configuration fields available for this source type.', 'zc-dmt') . '</p>';
        }

        echo '</div>'; // .zc-form-section
        echo '</form>'; // Close the form wrapper

        // Add the auto-slug script inline here as well for dynamically loaded forms
        ?>
        <script>
        (function($) {
            // Re-attach the auto-generate slug listener for dynamically loaded forms
            // Use unique IDs to avoid conflicts if multiple instances could exist (unlikely here)
            $('#source_name_ajax').off('blur.zc_dmt_ajax').on('blur.zc_dmt_ajax', function() {
                var name = $(this).val();
                var slug = name.toLowerCase()
                               .replace(/[^a-z0-9\s-]/g, '')
                               .replace(/\s+/g, '-')
                               .replace(/-+/g, '-')
                               .trim('-');
                if (!$('#source_slug_ajax').val()) {
                    $('#source_slug_ajax').val(slug);
                }
            });
        })(jQuery);
        </script>
        <?php

        $form_html = ob_get_clean(); // Get the buffered content

        if ($form_html === false) {
            wp_send_json_error(array('message' => __('Failed to generate form HTML.', 'zc-dmt')));
        }

        wp_send_json_success(array('html' => $form_html));
    }

    // --- Add this method for handling the AJAX form submission from data-sources.php ---
    /**
     * Handles the AJAX form submission from the dynamic form on data-sources.php.
     * Action: wp_ajax_zc_dmt_add_source_from_data_sources_page
     */
    public function ajax_add_source_from_data_sources_page() {
        // Check nonce for security
        check_ajax_referer('zc_dmt_add_source_nonce', 'nonce'); // Use the same nonce as add-source.php

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'zc-dmt')));
        }

        // --- Replicate logic from add-source.php form processing ---
        // Get data from POST (sent via AJAX serialize)
        $source_type = isset($_POST['source_type']) ? sanitize_key($_POST['source_type']) : '';
        // Verify nonce (already checked above via check_ajax_referer, but form also sends it)
        $nonce = isset($_POST['zc_dmt_add_source_nonce']) ? $_POST['zc_dmt_add_source_nonce'] : '';

        if ( empty($source_type) || !wp_verify_nonce($nonce, 'zc_dmt_add_source') ) {
             wp_send_json_error(array('message' => __('Security check failed or source type missing.', 'zc-dmt')));
        }

        // Process form data
        $name = isset($_POST['source_name']) ? sanitize_text_field($_POST['source_name']) : '';
        $slug = isset($_POST['source_slug']) ? sanitize_key($_POST['source_slug']) : '';
        $description = isset($_POST['source_description']) ? sanitize_textarea_field($_POST['source_description']) : '';
        $is_active = isset($_POST['source_active']) ? 1 : 0;

        // Source configuration
        $source_config = array();
        // We need to know the config fields for the selected source type again
        if (!empty($source_type) && class_exists('ZC_DMT_Data_Sources')) {
            $data_sources = new ZC_DMT_Data_Sources();
            $source_info = $data_sources->get_source($source_type);
            if ($source_info && isset($source_info['config_fields']) && is_array($source_info['config_fields'])) {
                foreach ($source_info['config_fields'] as $field) {
                    if (isset($_POST[$source_type . '_' . $field])) {
                        $source_config[$field] = sanitize_text_field($_POST[$source_type . '_' . $field]);
                    }
                }
            }
        }

        // Prepare data for insertion as an indicator
        $source_data = array(
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'source' => $source_type, // Crucial: Use the selected source type
            'source_config' => maybe_serialize($source_config), // Ensure it's serialized like in add-source.php
            'is_active' => $is_active
        );

        // Add source as indicator
        if (class_exists('ZC_DMT_Indicators')) {
            $indicators = new ZC_DMT_Indicators();
            $result = $indicators->add_indicator($source_data);

            if (is_wp_error($result)) {
                wp_send_json_error(array('message' => $result->get_error_message()));
            } else {
                // Success: Indicate success. The JS will handle the redirect.
                wp_send_json_success(array('message' => __('Source added successfully.', 'zc-dmt')));
            }
        } else {
             wp_send_json_error(array('message' => __('Indicators class not found.', 'zc-dmt')));
        }
        // --- End of logic replication ---
    }


    // --- End of new methods ---

    public function register_admin_menu() {
        // Add main menu item
        add_menu_page(
            __('ZC DMT Dashboard', 'zc-dmt'),
            __('ZC DMT', 'zc-dmt'),
            'manage_options',
            'zc-dmt-dashboard',
            '', // Callback is now handled by handle_admin_pages
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
            '' // Callback is now handled by handle_admin_pages
        );

        // API Keys Management Page (Note: This page file is not in your initial structure)
        // add_submenu_page(
        //     'zc-dmt-dashboard',
        //     __('API Keys', 'zc-dmt'),
        //     __('API Keys', 'zc-dmt'),
        //     'manage_options',
        //     'zc-dmt-api-management',
        //     '' // Callback is now handled by handle_admin_pages
        // );

        add_submenu_page(
            'zc-dmt-dashboard',
            __('Data Sources', 'zc-dmt'),
            __('Data Sources', 'zc-dmt'),
            'manage_options',
            'zc-dmt-data-sources',
            '' // Callback is now handled by handle_admin_pages
        );

        add_submenu_page(
            'zc-dmt-dashboard',
            __('Indicators', 'zc-dmt'),
            __('Indicators', 'zc-dmt'),
            'manage_options',
            'zc-dmt-indicators',
            '' // Callback is now handled by handle_admin_pages
        );

        add_submenu_page(
            'zc-dmt-dashboard',
            __('Manual Calculations', 'zc-dmt'),
            __('Manual Calculations', 'zc-dmt'),
            'manage_options',
            'zc-dmt-calculations',
            '' // Callback is now handled by handle_admin_pages
        );

        add_submenu_page(
            'zc-dmt-dashboard',
            __('Backup Settings', 'zc-dmt'),
            __('Backup Settings', 'zc-dmt'),
            'manage_options',
            'zc-dmt-backup',
            '' // Callback is now handled by handle_admin_pages
        );

        add_submenu_page(
            'zc-dmt-dashboard',
            __('Error Logs', 'zc-dmt'),
            __('Error Logs', 'zc-dmt'),
            'manage_options',
            'zc-dmt-error-logs',
            '' // Callback is now handled by handle_admin_pages
        );

        add_submenu_page(
            'zc-dmt-dashboard',
            __('Settings', 'zc-dmt'),
            __('Settings', 'zc-dmt'),
            'manage_options',
            'zc-dmt-settings',
            '' // Callback is now handled by handle_admin_pages
        );
    }

    // These methods are no longer needed for page loading as handle_admin_pages takes over.
    // They can be removed or kept empty.
    public function dashboard_page() {
        // Handled by handle_admin_pages
    }

    // public function api_management_page() { // Commented out as the file might not exist yet
    //     // Handled by handle_admin_pages
    // }

    public function data_sources_page() {
        // Handled by handle_admin_pages
    }

    public function indicators_page() {
        // Handled by handle_admin_pages
    }

    public function calculations_page() {
        // Handled by handle_admin_pages
    }

    public function backup_settings_page() {
        // Handled by handle_admin_pages
    }

    public function error_logs_page() {
        // Handled by handle_admin_pages
    }

    public function settings_page() {
        // Handled by handle_admin_pages
    }

    public function enqueue_admin_scripts($hook) {
        // --- CRITICAL FIX: Prevent loading scripts on non-plugin pages to avoid admin bar conflicts ---
        
        // Get the current page slug from the query, which is more reliable for our custom pages
        $current_page = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';

        // Define the list of page slugs where our scripts/styles should be loaded
        // This should ideally match the keys in the $pages array in handle_admin_pages
        $plugin_pages = array(
            'zc-dmt-dashboard',
            'zc-dmt-data-sources',
            'zc-dmt-indicators',
            'zc-dmt-calculations',
            'zc-dmt-backup',
            'zc-dmt-error-logs',
            'zc-dmt-settings',
            'zc-dmt-add-source',
            'zc-dmt-fetch-data'
            // Add other specific pages handled by handle_admin_pages if needed
        );

        // --- Only proceed if it's one of our plugin's pages ---
        if (!in_array($current_page, $plugin_pages)) {
            return; // Exit early if not a DMT page. This prevents conflicts on other admin screens.
        }

        // --- Enqueue styles and scripts ---
        // Use the plugin's version for cache busting
        $version = ZC_DMT_VERSION;

        // Enqueue CSS
        wp_enqueue_style('zc-dmt-admin-css', ZC_DMT_PLUGIN_URL . 'assets/css/admin.css', array(), $version);

        // Enqueue JavaScript - IMPORTANT: Make sure 'jquery' is a dependency
        // Pass 'true' for $in_footer to load script in the footer, often better for performance and avoiding conflicts
        wp_enqueue_script('zc-dmt-admin-js', ZC_DMT_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), $version, true);

        // --- Localize script with necessary data ---
        // This makes PHP variables available to the JavaScript code
        wp_localize_script('zc-dmt-admin-js', 'zc_dmt_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'confirm_delete' => __('Are you sure you want to delete this item?', 'zc-dmt'),
            'saving' => __('Saving...', 'zc-dmt'),
            'load_preview' => __('Load Preview', 'zc-dmt'),
            'loading' => __('Loading...', 'zc-dmt'),
            // Nonces for security
            'get_source_config_nonce' => wp_create_nonce('zc_dmt_get_source_config_nonce'),
            'add_source_nonce' => wp_create_nonce('zc_dmt_add_source_nonce')
        ));
    }

    public function enqueue_public_scripts() {
        // Enqueue public scripts if needed
        // wp_enqueue_script('zc-dmt-public-js', ZC_DMT_PLUGIN_URL . 'assets/js/public.js', array('jquery'), ZC_DMT_VERSION, true);
    }

    // --- Optional: If you move REST API logic here or create a method for it ---
    // public function register_rest_routes() {
    //     // Implementation based on class-rest-api.php requirements
    // }
    // --- End Optional ---
}

// Initialize the plugin
function zc_dmt_init() {
    ZC_DMT::get_instance();
}
add_action('plugins_loaded', 'zc_dmt_init');

// Register activation and deactivation hooks
function zc_dmt_activate() {
    // Create database tables on activation
    if (class_exists('ZC_DMT_Database')) {
         ZC_DMT_Database::get_instance()->create_tables();
    } else {
        // Handle error or ensure class is loaded
        error_log('ZC DMT: Database class not found during activation.');
    }
   
}
register_activation_hook(__FILE__, 'zc_dmt_activate');

function zc_dmt_deactivate() {
    // Clear scheduled events on deactivation
    wp_clear_scheduled_hook('zc_dmt_scheduled_backup');
}
register_deactivation_hook(__FILE__, 'zc_dmt_deactivate');
