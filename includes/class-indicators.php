<?php
/**
 * ZC DMT Indicators Class
 * Handles indicator management and data operations
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class ZC_DMT_Indicators {
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
     * Add a new indicator
     */
    public function add_indicator($data) {
        // Validate required fields
        if (empty($data['name'])) {
            return new WP_Error('missing_name', __('Indicator name is required', 'zc-dmt'));
        }
        if (empty($data['source'])) {
            return new WP_Error('missing_source', __('Indicator source is required', 'zc-dmt'));
        }

        // Sanitize and prepare data
        $sanitized_data = array(
            'name' => sanitize_text_field($data['name']),
            'slug' => sanitize_key($data['slug'] ?? sanitize_title($data['name'])),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'source' => sanitize_text_field($data['source']),
            // Ensure source_config is serialized before saving
            'source_config' => maybe_serialize($data['source_config'] ?? array()),
            // Sanitize is_active as an integer (0 or 1)
            'is_active' => (int) ($data['is_active'] ?? 0),
            'last_updated' => current_time('mysql')
        );

        // Insert indicator into database
        $result = $this->db->insert_indicator($sanitized_data);

        if (is_wp_error($result)) {
            return $result;
        }

        // Log success
        if (class_exists('ZC_DMT_Error_Logger')) {
            $logger = new ZC_DMT_Error_Logger();
            $logger->log('info', 'Indicators', 'add_indicator', 'New indicator added', array('indicator_id' => $result));
        }

        // Trigger action
        do_action('zc_dmt_indicator_created', $result, $sanitized_data);

        return $result;
    }

    /**
     * Get all indicators
     */
    public function get_indicators($args = array()) {
        $defaults = array(
            'orderby' => 'name',
            'order' => 'ASC'
        );
        $args = wp_parse_args($args, $defaults);
        return $this->db->get_all_indicators();
    }

    /**
     * Get indicator by ID
     */
    public function get_indicator($id) {
        // Validate ID
        if (empty($id) || !is_numeric($id)) {
            return new WP_Error('invalid_id', __('Indicator ID is required', 'zc-dmt'));
        }

        $indicator = $this->db->get_indicator_by_id($id);

        if (!$indicator) {
            return new WP_Error('indicator_not_found', __('Indicator not found', 'zc-dmt'));
        }

        // --- Fix: Ensure source_config is unserialized ---
        if (isset($indicator->source_config) && is_string($indicator->source_config)) {
            $indicator->source_config = maybe_unserialize($indicator->source_config);
            // If unserialization fails or returns false, default to an empty array
            if ($indicator->source_config === false) {
                $indicator->source_config = array();
            }
        } elseif (!isset($indicator->source_config)) {
             // If source_config is not set in the DB result, initialize it
             $indicator->source_config = array();
        }
        // --- End of Fix ---

        // --- Fix: Ensure is_active is an integer (0 or 1) ---
        // Handle cases where is_active might be null or missing from the DB result
        if (!isset($indicator->is_active)) {
            $indicator->is_active = 0; // Default to inactive if not set
        } else {
            $indicator->is_active = (int) $indicator->is_active;
        }
        // --- End of Fix ---

        return $indicator;
    }

    /**
     * Get indicator by slug
     */
    public function get_indicator_by_slug($slug) {
        // Validate slug
        if (empty($slug)) {
            return new WP_Error('invalid_slug', __('Indicator slug is required', 'zc-dmt'));
        }

        $indicator = $this->db->get_indicator_by_slug($slug);

        if (!$indicator) {
            return new WP_Error('indicator_not_found', __('Indicator not found', 'zc-dmt'));
        }

        // --- Fix: Ensure source_config is unserialized (same as get_indicator) ---
        if (isset($indicator->source_config) && is_string($indicator->source_config)) {
            $indicator->source_config = maybe_unserialize($indicator->source_config);
            if ($indicator->source_config === false) {
                $indicator->source_config = array();
            }
        } elseif (!isset($indicator->source_config)) {
             $indicator->source_config = array();
        }
        // --- End of Fix ---

        // --- Fix: Ensure is_active is an integer (same as get_indicator) ---
        if (!isset($indicator->is_active)) {
            $indicator->is_active = 0;
        } else {
            $indicator->is_active = (int) $indicator->is_active;
        }
        // --- End of Fix ---

        return $indicator;
    }

    /**
     * Update an indicator
     */
    public function update_indicator($id, $data) {
        // Validate ID
        if (empty($id) || !is_numeric($id)) {
            return new WP_Error('invalid_id', __('Indicator ID is required', 'zc-dmt'));
        }

        // Sanitize and prepare data
        // Only update fields that are provided in $data
        $sanitized_data = array();
        if (isset($data['name'])) {
            $sanitized_data['name'] = sanitize_text_field($data['name']);
        }
        if (isset($data['slug'])) {
            $sanitized_data['slug'] = sanitize_key($data['slug']);
        }
        if (isset($data['description'])) {
            $sanitized_data['description'] = sanitize_textarea_field($data['description']);
        }
        if (isset($data['source'])) {
            $sanitized_data['source'] = sanitize_text_field($data['source']);
        }
        // Ensure source_config is serialized before saving, if provided
        if (array_key_exists('source_config', $data)) { // Use array_key_exists to allow empty arrays
            $sanitized_data['source_config'] = maybe_serialize($data['source_config']);
        }
        // Sanitize is_active as an integer (0 or 1), if provided
        if (array_key_exists('is_active', $data)) {
             $sanitized_data['is_active'] = (int) $data['is_active'];
        }
        // Always update the last_updated timestamp
        $sanitized_data['last_updated'] = current_time('mysql');


        // Update indicator in database
        $result = $this->db->update_indicator($id, $sanitized_data);

        if (is_wp_error($result)) {
            return $result;
        }

        // Log success
        if (class_exists('ZC_DMT_Error_Logger')) {
            $logger = new ZC_DMT_Error_Logger();
            $logger->log('info', 'Indicators', 'update_indicator', 'Indicator updated', array('indicator_id' => $id));
        }

        // Trigger action
        do_action('zc_dmt_indicator_updated', $id, $sanitized_data);

        return true;
    }

    /**
     * Delete an indicator
     */
    public function delete_indicator($id) {
        // Validate ID
        if (empty($id) || !is_numeric($id)) {
            return new WP_Error('invalid_id', __('Indicator ID is required', 'zc-dmt'));
        }

        // Delete indicator from database
        $result = $this->db->delete_indicator($id);

        if (is_wp_error($result)) {
            return $result;
        }

        // Log success
        if (class_exists('ZC_DMT_Error_Logger')) {
            $logger = new ZC_DMT_Error_Logger();
            $logger->log('info', 'Indicators', 'delete_indicator', 'Indicator deleted', array('indicator_id' => $id));
        }

        // Trigger action
        do_action('zc_dmt_indicator_deleted', $id);

        return true;
    }

    /**
     * Add a data point for an indicator
     */
    public function add_data_point($indicator_id, $date, $value) {
        // Validate indicator ID
        if (empty($indicator_id) || !is_numeric($indicator_id)) {
            return new WP_Error('invalid_indicator_id', __('Indicator ID is required', 'zc-dmt'));
        }

        // Validate date
        $date_obj = DateTime::createFromFormat('Y-m-d', $date);
        if (!$date_obj) {
            return new WP_Error('invalid_date', __('Invalid date format. Expected YYYY-MM-DD.', 'zc-dmt'));
        }

        // Validate value
        if (!is_numeric($value)) {
            return new WP_Error('invalid_value', __('Data point value must be numeric', 'zc-dmt'));
        }

        // Check if indicator exists
        $indicator = $this->get_indicator($indicator_id);
        if (is_wp_error($indicator)) {
            return $indicator;
        }

        // Insert data point into database
        $result = $this->db->insert_data_point($indicator_id, $date, $value);

        if (is_wp_error($result)) {
            return $result;
        }

        // Update last updated timestamp
        $this->db->update_indicator($indicator_id, array('last_updated' => current_time('mysql')));

        // Log success
        if (class_exists('ZC_DMT_Error_Logger')) {
            $logger = new ZC_DMT_Error_Logger();
            $logger->log('info', 'Indicators', 'add_data_point', 'Data point added', array('indicator_id' => $indicator_id, 'date' => $date, 'value' => $value));
        }

        return $result;
    }

    /**
     * Get data points for an indicator
     */
    public function get_data_points($indicator_id, $args = array()) {
        // Validate indicator ID
        if (empty($indicator_id) || !is_numeric($indicator_id)) {
            return new WP_Error('invalid_indicator_id', __('Indicator ID is required', 'zc-dmt'));
        }

        // Check if indicator exists
        $indicator = $this->get_indicator($indicator_id);
        if (is_wp_error($indicator)) {
            return $indicator;
        }

        $defaults = array(
            'limit' => null,
            'start_date' => null,
            'end_date' => null
        );
        $args = wp_parse_args($args, $defaults);

        return $this->db->get_data_points($indicator_id, $args['limit'], $args['start_date'], $args['end_date']);
    }

    /**
     * Get latest data point for an indicator
     */
    public function get_latest_data_point($indicator_id) {
        // Validate indicator ID
        if (empty($indicator_id) || !is_numeric($indicator_id)) {
            return new WP_Error('invalid_indicator_id', __('Indicator ID is required', 'zc-dmt'));
        }

        // Check if indicator exists
        $indicator = $this->get_indicator($indicator_id);
        if (is_wp_error($indicator)) {
            return $indicator;
        }

        return $this->db->get_latest_data_point($indicator_id);
    }
}
?>
