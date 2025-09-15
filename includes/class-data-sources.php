<?php
/**
 * ZC DMT Data Sources Class
 * Handles integration with multiple external data sources
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class ZC_DMT_Data_Sources {
    /**
     * Registered data sources
     */
    private $sources = array();

    /**
     * Constructor
     */
    public function __construct() {
        $this->register_sources();
    }

    /**
     * Register all supported data sources
     */
    private function register_sources() {
        $this->sources = array(
            'fred' => array(
                'name' => __('FRED (Federal Reserve Economic Data)', 'zc-dmt'),
                'class' => 'ZC_DMT_FRED_Source',
                'config_fields' => array('api_key', 'series_id')
            ),
            'eurostat' => array(
                'name' => __('Eurostat', 'zc-dmt'),
                'class' => 'ZC_DMT_Eurostat_Source',
                'config_fields' => array('dataset_code', 'indicator_code')
            ),
            'worldbank' => array(
                'name' => __('World Bank', 'zc-dmt'),
                'class' => 'ZC_DMT_WorldBank_Source',
                'config_fields' => array('indicator_code', 'country_code')
            ),
            'oecd' => array(
                'name' => __('OECD', 'zc-dmt'),
                'class' => 'ZC_DMT_OECD_Source',
                'config_fields' => array('dataset_id', 'indicator_code')
            ),
            'dbnomics' => array(
                'name' => __('DBnomics', 'zc-dmt'),
                'class' => 'ZC_DMT_DBnomics_Source',
                'config_fields' => array('provider_code', 'dataset_code', 'series_code')
            ),
            'yahoo_finance' => array(
                'name' => __('Yahoo Finance', 'zc-dmt'),
                'class' => 'ZC_DMT_YahooFinance_Source',
                'config_fields' => array('symbol')
            ),
            'google_finance' => array(
                'name' => __('Google Finance', 'zc-dmt'),
                'class' => 'ZC_DMT_GoogleFinance_Source',
                'config_fields' => array('symbol', 'api_key')
            ),
            'canada_open' => array(
                'name' => __('Canadian Open Data Portal', 'zc-dmt'),
                'class' => 'ZC_DMT_CanadaOpen_Source',
                'config_fields' => array('dataset_url')
            ),
            'google_sheets' => array(
                'name' => __('Google Sheets', 'zc-dmt'),
                'class' => 'ZC_DMT_GoogleSheets_Source',
                'config_fields' => array('sheet_url', 'sheet_id')
            ),
            'zip_processor' => array(
                'name' => __('ZIP File Processing', 'zc-dmt'),
                'class' => 'ZC_DMT_ZIP_Processor_Source',
                'config_fields' => array('zip_url', 'extraction_rules')
            )
        );
    }

    /**
     * Get all registered sources
     */
    public function get_sources() {
        return $this->sources;
    }

    /**
     * Get source by type
     */
    public function get_source($type) {
        if (isset($this->sources[$type])) {
            return $this->sources[$type];
        }
        return null;
    }

    /**
     * Get source name by type
     */
    public function get_source_name($type) {
        if (isset($this->sources[$type])) {
            return $this->sources[$type]['name'];
        }
        return $type;
    }

    /**
     * Test connection to a data source
     */
    public function test_connection($source_type, $config) {
        // Validate source type
        if (!isset($this->sources[$source_type])) {
            return new WP_Error('invalid_source', __('Invalid data source type', 'zc-dmt'));
        }

        // Get source class
        $source_class = $this->sources[$source_type]['class'];

        // Check if class exists
        if (!class_exists($source_class)) {
            // Try to load the class file
            $class_file = ZC_DMT_PLUGIN_DIR . 'includes/data-sources/class-' . 
                         str_replace('_', '-', strtolower($source_type)) . '.php';
            if (file_exists($class_file)) {
                require_once $class_file;
            }
        }

        // Check again if class exists
        if (!class_exists($source_class)) {
            return new WP_Error('class_not_found', sprintf(__('Data source class %s not found', 'zc-dmt'), $source_class));
        }

        // Create instance and test connection
        $source_instance = new $source_class();
        
        if (!method_exists($source_instance, 'test_connection')) {
            return new WP_Error('method_not_found', sprintf(__('test_connection method not found in %s', 'zc-dmt'), $source_class));
        }

        return $source_instance->test_connection($config);
    }

    /**
     * Fetch data from a data source
     */
    public function fetch_data($source_type, $config, $date_range = null) {
        // Validate source type
        if (!isset($this->sources[$source_type])) {
            return new WP_Error('invalid_source', __('Invalid data source type', 'zc-dmt'));
        }

        // Get source class
        $source_class = $this->sources[$source_type]['class'];

        // Check if class exists
        if (!class_exists($source_class)) {
            // Try to load the class file
            $class_file = ZC_DMT_PLUGIN_DIR . 'includes/data-sources/class-' . 
                         str_replace('_', '-', strtolower($source_type)) . '.php';
            if (file_exists($class_file)) {
                require_once $class_file;
            }
        }

        // Check again if class exists
        if (!class_exists($source_class)) {
            return new WP_Error('class_not_found', sprintf(__('Data source class %s not found', 'zc-dmt'), $source_class));
        }

        // Create instance and fetch data
        $source_instance = new $source_class();
        
        if (!method_exists($source_instance, 'fetch_data')) {
            return new WP_Error('method_not_found', sprintf(__('fetch_data method not found in %s', 'zc-dmt'), $source_class));
        }

        return $source_instance->fetch_data($config, $date_range);
    }

    /**
     * Register a new data source
     */
    public function register_source($type, $name, $class, $config_fields = array()) {
        $this->sources[$type] = array(
            'name' => $name,
            'class' => $class,
            'config_fields' => $config_fields
        );
    }

    /**
     * Unregister a data source
     */
    public function unregister_source($type) {
        if (isset($this->sources[$type])) {
            unset($this->sources[$type]);
        }
    }
}
?>
