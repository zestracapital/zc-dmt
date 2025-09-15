<?php
/**
 * ZC DMT Calculations Class
 * Handles manual calculations and formula processing
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class ZC_DMT_Calculations {
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
     * Add a new calculation
     */
    public function add_calculation($data) {
        // Validate required fields
        if (empty($data['name'])) {
            return new WP_Error('missing_name', __('Calculation name is required', 'zc-dmt'));
        }
        
        if (empty($data['formula'])) {
            return new WP_Error('missing_formula', __('Calculation formula is required', 'zc-dmt'));
        }

        // Sanitize data
        $sanitized_data = array(
            'name' => sanitize_text_field($data['name']),
            'slug' => sanitize_key($data['name']), // Generate slug from name
            'description' => sanitize_textarea_field(isset($data['description']) ? $data['description'] : ''),
            'formula' => sanitize_textarea_field($data['formula']),
            'dependencies' => isset($data['dependencies']) ? $data['dependencies'] : array()
        );

        // Insert calculation into database
        $result = $this->db->insert_calculation($sanitized_data);
        
        if (is_wp_error($result)) {
            return $result;
        }

        // Log success
        if (class_exists('ZC_DMT_Error_Logger')) {
            $logger = new ZC_DMT_Error_Logger();
            $logger->log('info', 'Calculations', 'add_calculation', 'New calculation added', array('calculation_id' => $result));
        }

        return $result;
    }

    /**
     * Get all calculations
     */
    public function get_calculations() {
        return $this->db->get_all_calculations();
    }

    /**
     * Get calculation by ID
     */
    public function get_calculation($id) {
        // Validate ID
        if (empty($id) || !is_numeric($id)) {
            return new WP_Error('invalid_id', __('Calculation ID is required', 'zc-dmt'));
        }

        $calculation = $this->db->get_calculation_by_id($id);
        
        if (!$calculation) {
            return new WP_Error('calculation_not_found', __('Calculation not found', 'zc-dmt'));
        }

        return $calculation;
    }

    /**
     * Update a calculation
     */
    public function update_calculation($id, $data) {
        // Validate ID
        if (empty($id) || !is_numeric($id)) {
            return new WP_Error('invalid_id', __('Calculation ID is required', 'zc-dmt'));
        }

        // Validate required fields
        if (empty($data['name'])) {
            return new WP_Error('missing_name', __('Calculation name is required', 'zc-dmt'));
        }
        
        if (empty($data['formula'])) {
            return new WP_Error('missing_formula', __('Calculation formula is required', 'zc-dmt'));
        }

        // Sanitize data
        $sanitized_data = array(
            'name' => sanitize_text_field($data['name']),
            'slug' => sanitize_key($data['name']), // Generate slug from name
            'description' => sanitize_textarea_field(isset($data['description']) ? $data['description'] : ''),
            'formula' => sanitize_textarea_field($data['formula']),
            'dependencies' => isset($data['dependencies']) ? $data['dependencies'] : array()
        );

        // Update calculation in database
        $result = $this->db->update_calculation($id, $sanitized_data);
        
        if (is_wp_error($result)) {
            return $result;
        }

        // Log success
        if (class_exists('ZC_DMT_Error_Logger')) {
            $logger = new ZC_DMT_Error_Logger();
            $logger->log('info', 'Calculations', 'update_calculation', 'Calculation updated', array('calculation_id' => $id));
        }

        return true;
    }

    /**
     * Delete a calculation
     */
    public function delete_calculation($id) {
        // Validate ID
        if (empty($id) || !is_numeric($id)) {
            return new WP_Error('invalid_id', __('Calculation ID is required', 'zc-dmt'));
        }

        // Delete calculation from database
        $result = $this->db->delete_calculation($id);
        
        if (is_wp_error($result)) {
            return $result;
        }

        // Log success
        if (class_exists('ZC_DMT_Error_Logger')) {
            $logger = new ZC_DMT_Error_Logger();
            $logger->log('info', 'Calculations', 'delete_calculation', 'Calculation deleted', array('calculation_id' => $id));
        }

        return true;
    }

    /**
     * Execute a calculation
     */
    public function execute_calculation($id) {
        // Get calculation
        $calculation = $this->get_calculation($id);
        
        if (is_wp_error($calculation)) {
            return $calculation;
        }

        try {
            // Parse and evaluate formula
            $result = $this->evaluate_formula($calculation->formula);
            
            // Update last calculated timestamp
            $this->db->update_calculation($id, array('last_calculated' => current_time('mysql')));
            
            // Log success
            if (class_exists('ZC_DMT_Error_Logger')) {
                $logger = new ZC_DMT_Error_Logger();
                $logger->log('info', 'Calculations', 'execute_calculation', 'Calculation executed successfully', array('calculation_id' => $id));
            }
            
            return $result;
        } catch (Exception $e) {
            // Log error
            if (class_exists('ZC_DMT_Error_Logger')) {
                $logger = new ZC_DMT_Error_Logger();
                $logger->log('error', 'Calculations', 'execute_calculation', 'Calculation execution failed: ' . $e->getMessage(), array('calculation_id' => $id));
            }
            
            return new WP_Error('calculation_failed', __('Calculation execution failed: ', 'zc-dmt') . $e->getMessage());
        }
    }

    /**
     * Evaluate a calculation formula
     */
    private function evaluate_formula($formula) {
        // This is a simplified formula evaluator
        // In a real implementation, this would be much more complex
        // and would need to parse and evaluate the formula safely
        
        // For now, we'll just return a sample result
        // A real implementation would:
        // 1. Parse the formula to identify functions and indicators
        // 2. Fetch required data points for indicators
        // 3. Apply the specified functions
        // 4. Return the calculated result
        
        // Example of what a real implementation might do:
        // $parsed = $this->parse_formula($formula);
        // $data = $this->fetch_data($parsed['indicators']);
        // $result = $this->apply_functions($parsed['functions'], $data);
        // return $result;
        
        return array(
            'sample_date' => date('Y-m-d'),
            'sample_value' => rand(100, 1000) / 100
        );
    }

    /**
     * Parse a formula to identify components
     */
    private function parse_formula($formula) {
        // This would parse the formula string to identify:
        // - Indicator slugs (e.g., gdp_growth_rate)
        // - Functions (e.g., SUM, AVG, ROC)
        // - Parameters for functions
        // - Mathematical operators
        
        // For now, return a placeholder structure
        return array(
            'indicators' => array(),
            'functions' => array(),
            'operators' => array()
        );
    }

    /**
     * Fetch data for indicators
     */
    private function fetch_data($indicators) {
        // This would fetch data points for the specified indicators
        // from the database or external sources
        
        // For now, return placeholder data
        return array();
    }

    /**
     * Apply functions to data
     */
    private function apply_functions($functions, $data) {
        // This would apply the specified functions to the data
        // and return the calculated results
        
        // For now, return a placeholder result
        return array();
    }
}
?>
