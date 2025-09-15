<?php
/**
 * ZC DMT REST API Class
 * Handles all REST API endpoints for the plugin
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class ZC_DMT_REST_API {
    /**
     * Database instance
     */
    private $db;

    /**
     * Constructor
     */
    public function __construct() {
        $this->db = ZC_DMT_Database::get_instance();
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    /**
     * Register REST API routes
     */
    public function register_routes() {
        $namespace = 'zc-dmt/v1';

        // Get all indicators
        register_rest_route($namespace, '/indicators', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_indicators'),
                'permission_callback' => '__return_true',
                'args' => $this->get_indicators_args()
            )
        ));

        // Get indicator by ID
        register_rest_route($namespace, '/indicators/(?P<id>\d+)', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_indicator'),
                'permission_callback' => '__return_true',
                'args' => $this->get_indicator_args()
            )
        ));

        // Get indicator by slug
        register_rest_route($namespace, '/indicators/(?P<slug>[a-zA-Z0-9_-]+)', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_indicator_by_slug'),
                'permission_callback' => '__return_true',
                'args' => $this->get_indicator_args()
            )
        ));

        // Get indicator data
        register_rest_route($namespace, '/data/(?P<slug>[a-zA-Z0-9_-]+)', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_indicator_data'),
                'permission_callback' => array($this, 'validate_access_key'),
                'args' => $this->get_indicator_data_args()
            )
        ));

        // Get backup data
        register_rest_route($namespace, '/backup/(?P<slug>[a-zA-Z0-9_-]+)', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_backup_data'),
                'permission_callback' => array($this, 'validate_access_key'),
                'args' => $this->get_indicator_data_args()
            )
        ));

        // Validate API key
        register_rest_route($namespace, '/validate-key', array(
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'validate_key'),
                'permission_callback' => '__return_true',
                'args' => $this->validate_key_args()
            )
        ));

        // Get calculations
        register_rest_route($namespace, '/calculations', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_calculations'),
                'permission_callback' => '__return_true'
            )
        ));

        // Get calculation by ID
        register_rest_route($namespace, '/calculations/(?P<id>\d+)', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_calculation'),
                'permission_callback' => '__return_true',
                'args' => $this->get_calculation_args()
            )
        ));
    }

    /**
     * Get indicators arguments
     */
    public function get_indicators_args() {
        return array(
            'per_page' => array(
                'description' => __('Maximum number of indicators to retrieve.', 'zc-dmt'),
                'type' => 'integer',
                'default' => 10,
                'minimum' => 1,
                'maximum' => 100,
                'sanitize_callback' => 'absint',
                'validate_callback' => 'rest_validate_request_arg',
            ),
            'page' => array(
                'description' => __('Current page of the collection.', 'zc-dmt'),
                'type' => 'integer',
                'default' => 1,
                'sanitize_callback' => 'absint',
                'validate_callback' => 'rest_validate_request_arg',
            ),
        );
    }

    /**
     * Get indicator arguments
     */
    public function get_indicator_args() {
        return array(
            'context' => array(
                'description' => __('Scope under which the request is made.', 'zc-dmt'),
                'type' => 'string',
                'default' => 'view',
                'enum' => array('view', 'embed', 'edit'),
                'validate_callback' => 'rest_validate_request_arg',
            ),
        );
    }

    /**
     * Get indicator data arguments
     */
    public function get_indicator_data_args() {
        return array(
            'start_date' => array(
                'description' => __('Start date for data retrieval (YYYY-MM-DD).', 'zc-dmt'),
                'type' => 'string',
                'format' => 'date',
                'validate_callback' => 'rest_validate_request_arg',
            ),
            'end_date' => array(
                'description' => __('End date for data retrieval (YYYY-MM-DD).', 'zc-dmt'),
                'type' => 'string',
                'format' => 'date',
                'validate_callback' => 'rest_validate_request_arg',
            ),
            'limit' => array(
                'description' => __('Limit the number of data points returned.', 'zc-dmt'),
                'type' => 'integer',
                'default' => 1000,
                'minimum' => 1,
                'maximum' => 10000,
                'sanitize_callback' => 'absint',
                'validate_callback' => 'rest_validate_request_arg',
            ),
        );
    }

    /**
     * Validate key arguments
     */
    public function validate_key_args() {
        return array(
            'access_key' => array(
                'description' => __('API access key.', 'zc-dmt'),
                'type' => 'string',
                'required' => true,
                'validate_callback' => 'rest_validate_request_arg',
            ),
        );
    }

    /**
     * Get calculation arguments
     */
    public function get_calculation_args() {
        return array(
            'context' => array(
                'description' => __('Scope under which the request is made.', 'zc-dmt'),
                'type' => 'string',
                'default' => 'view',
                'enum' => array('view', 'embed', 'edit'),
                'validate_callback' => 'rest_validate_request_arg',
            ),
        );
    }

    /**
     * Get all indicators
     */
    public function get_indicators($request) {
        $params = $request->get_params();
        $per_page = isset($params['per_page']) ? $params['per_page'] : 10;
        $page = isset($params['page']) ? $params['page'] : 1;
        
        $offset = ($page - 1) * $per_page;
        
        // Get indicators from database
        $indicators = $this->db->get_all_indicators();
        
        // Apply pagination
        $paginated_indicators = array_slice($indicators, $offset, $per_page);
        
        $response = array(
            'indicators' => array(),
            'total' => count($indicators),
            'per_page' => $per_page,
            'current_page' => $page,
            'total_pages' => ceil(count($indicators) / $per_page)
        );
        
        foreach ($paginated_indicators as $indicator) {
            $response['indicators'][] = array(
                'id' => $indicator->id,
                'name' => $indicator->name,
                'slug' => $indicator->slug,
                'description' => $indicator->description,
                'source' => $indicator->source,
                'source_id' => $indicator->source_id,
                'unit' => $indicator->unit,
                'frequency' => $indicator->frequency,
                'last_updated' => $indicator->last_updated
            );
        }
        
        return rest_ensure_response($response);
    }

    /**
     * Get indicator by ID
     */
    public function get_indicator($request) {
        $params = $request->get_params();
        $id = isset($params['id']) ? $params['id'] : 0;
        
        if (empty($id)) {
            return new WP_Error('missing_id', __('Indicator ID is required.', 'zc-dmt'), array('status' => 400));
        }
        
        $indicator = $this->db->get_indicator_by_id($id);
        
        if (!$indicator) {
            return new WP_Error('indicator_not_found', __('Indicator not found.', 'zc-dmt'), array('status' => 404));
        }
        
        $response = array(
            'id' => $indicator->id,
            'name' => $indicator->name,
            'slug' => $indicator->slug,
            'description' => $indicator->description,
            'source' => $indicator->source,
            'source_id' => $indicator->source_id,
            'unit' => $indicator->unit,
            'frequency' => $indicator->frequency,
            'last_updated' => $indicator->last_updated
        );
        
        return rest_ensure_response($response);
    }

    /**
     * Get indicator by slug
     */
    public function get_indicator_by_slug($request) {
        $params = $request->get_params();
        $slug = isset($params['slug']) ? $params['slug'] : '';
        
        if (empty($slug)) {
            return new WP_Error('missing_slug', __('Indicator slug is required.', 'zc-dmt'), array('status' => 400));
        }
        
        $indicator = $this->db->get_indicator_by_slug($slug);
        
        if (!$indicator) {
            return new WP_Error('indicator_not_found', __('Indicator not found.', 'zc-dmt'), array('status' => 404));
        }
        
        $response = array(
            'id' => $indicator->id,
            'name' => $indicator->name,
            'slug' => $indicator->slug,
            'description' => $indicator->description,
            'source' => $indicator->source,
            'source_id' => $indicator->source_id,
            'unit' => $indicator->unit,
            'frequency' => $indicator->frequency,
            'last_updated' => $indicator->last_updated
        );
        
        return rest_ensure_response($response);
    }

    /**
     * Get indicator data
     */
    public function get_indicator_data($request) {
        $params = $request->get_params();
        $slug = isset($params['slug']) ? $params['slug'] : '';
        
        if (empty($slug)) {
            return new WP_Error('missing_slug', __('Indicator slug is required.', 'zc-dmt'), array('status' => 400));
        }
        
        // Get indicator
        $indicator = $this->db->get_indicator_by_slug($slug);
        
        if (!$indicator) {
            return new WP_Error('indicator_not_found', __('Indicator not found.', 'zc-dmt'), array('status' => 404));
        }
        
        // Get data points
        $args = array();
        if (isset($params['start_date'])) {
            $args['start_date'] = $params['start_date'];
        }
        if (isset($params['end_date'])) {
            $args['end_date'] = $params['end_date'];
        }
        if (isset($params['limit'])) {
            $args['limit'] = $params['limit'];
        }
        
        $data_points = $this->db->get_data_points($indicator->id, $args['limit'], $args['start_date'], $args['end_date']);
        
        // Format data for response
        $formatted_data = array();
        foreach ($data_points as $point) {
            $formatted_data[] = array(
                'date' => $point->date,
                'value' => floatval($point->value)
            );
        }
        
        $response = array(
            'indicator' => array(
                'id' => $indicator->id,
                'name' => $indicator->name,
                'slug' => $indicator->slug,
                'description' => $indicator->description,
                'source' => $indicator->source,
                'unit' => $indicator->unit
            ),
            'data' => $formatted_data
        );
        
        return rest_ensure_response($response);
    }

    /**
     * Get backup data
     */
    public function get_backup_data($request) {
        $params = $request->get_params();
        $slug = isset($params['slug']) ? $params['slug'] : '';
        
        if (empty($slug)) {
            return new WP_Error('missing_slug', __('Indicator slug is required.', 'zc-dmt'), array('status' => 400));
        }
        
        // Check if backup class exists
        if (!class_exists('ZC_DMT_Backup')) {
            return new WP_Error('backup_class_not_found', __('Backup class not found.', 'zc-dmt'), array('status' => 500));
        }
        
        // Initialize backup class
        $backup = new ZC_DMT_Backup();
        
        // Get backup data
        $backup_data = $backup->get_latest_backup_data($slug);
        
        if (is_wp_error($backup_data)) {
            return $backup_data;
        }
        
        return rest_ensure_response($backup_data);
    }

    /**
     * Validate API key
     */
    public function validate_key($request) {
        $params = $request->get_params();
        $access_key = isset($params['access_key']) ? $params['access_key'] : '';
        
        if (empty($access_key)) {
            return new WP_Error('missing_access_key', __('API key is required.', 'zc-dmt'), array('status' => 400));
        }
        
        // Check if security class exists
        if (!class_exists('ZC_DMT_Security')) {
            return new WP_Error('security_class_not_found', __('Security class not found.', 'zc-dmt'), array('status' => 500));
        }
        
        // Validate key
        $security = new ZC_DMT_Security();
        $is_valid = $security->validate_api_key($access_key);
        
        if (!$is_valid) {
            return new WP_Error('invalid_access_key', __('Invalid API key.', 'zc-dmt'), array('status' => 401));
        }
        
        return rest_ensure_response(array('valid' => true));
    }

    /**
     * Validate access key for protected endpoints
     */
    public function validate_access_key($request) {
        $access_key = $request->get_param('access_key');
        
        if (empty($access_key)) {
            // Check for access_key in headers
            $headers = $request->get_headers();
            if (isset($headers['access_key'])) {
                $access_key = $headers['access_key'][0];
            } elseif (isset($headers['Access-Key'])) {
                $access_key = $headers['Access-Key'][0];
            }
        }
        
        if (empty($access_key)) {
            return new WP_Error('missing_access_key', __('API key is required.', 'zc-dmt'), array('status' => 400));
        }
        
        // Check if security class exists
        if (!class_exists('ZC_DMT_Security')) {
            return new WP_Error('security_class_not_found', __('Security class not found.', 'zc-dmt'), array('status' => 500));
        }
        
        // Validate key
        $security = new ZC_DMT_Security();
        $is_valid = $security->validate_api_key($access_key);
        
        if (!$is_valid) {
            return new WP_Error('invalid_access_key', __('Invalid API key.', 'zc-dmt'), array('status' => 401));
        }
        
        // Update last used timestamp
        $key_record = $security->get_active_api_key(hash('sha256', $access_key));
        if ($key_record) {
            $security->update_api_key_last_used($key_record->id);
        }
        
        return true;
    }

    /**
     * Get all calculations
     */
    public function get_calculations($request) {
        // Check if calculations class exists
        if (!class_exists('ZC_DMT_Calculations')) {
            return new WP_Error('calculations_class_not_found', __('Calculations class not found.', 'zc-dmt'), array('status' => 500));
        }
        
        // Initialize calculations class
        $calculations = new ZC_DMT_Calculations();
        
        // Get calculations
        $all_calculations = $calculations->get_calculations();
        
        $response = array();
        foreach ($all_calculations as $calculation) {
            $response[] = array(
                'id' => $calculation->id,
                'name' => $calculation->name,
                'slug' => $calculation->slug,
                'description' => $calculation->description,
                'formula' => $calculation->formula,
                'last_calculated' => $calculation->last_calculated
            );
        }
        
        return rest_ensure_response($response);
    }

    /**
     * Get calculation by ID
     */
    public function get_calculation($request) {
        $params = $request->get_params();
        $id = isset($params['id']) ? $params['id'] : 0;
        
        if (empty($id)) {
            return new WP_Error('missing_id', __('Calculation ID is required.', 'zc-dmt'), array('status' => 400));
        }
        
        // Check if calculations class exists
        if (!class_exists('ZC_DMT_Calculations')) {
            return new WP_Error('calculations_class_not_found', __('Calculations class not found.', 'zc-dmt'), array('status' => 500));
        }
        
        // Initialize calculations class
        $calculations = new ZC_DMT_Calculations();
        
        // Get calculation
        $calculation = $calculations->get_calculation($id);
        
        if (is_wp_error($calculation)) {
            return $calculation;
        }
        
        $response = array(
            'id' => $calculation->id,
            'name' => $calculation->name,
            'slug' => $calculation->slug,
            'description' => $calculation->description,
            'formula' => $calculation->formula,
            'last_calculated' => $calculation->last_calculated
        );
        
        return rest_ensure_response($response);
    }
}
?>
