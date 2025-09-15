<?php
/**
 * ZC DMT Database Class
 * Handles all database operations for the plugin
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class ZC_DMT_Database {
    /**
     * Instance of the class
     */
    private static $instance = null;

    /**
     * WordPress database object
     */
    private $wpdb;

    /**
     * Table names
     */
    public $indicators_table;
    public $data_points_table;
    public $calculations_table;
    public $api_keys_table;
    public $error_logs_table;
    public $backup_history_table;

    /**
     * Get instance of the class (Singleton Pattern)
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        
        // Define table names with prefix
        $this->indicators_table = $this->wpdb->prefix . 'zc_dmt_indicators';
        $this->data_points_table = $this->wpdb->prefix . 'zc_dmt_data_points';
        $this->calculations_table = $this->wpdb->prefix . 'zc_dmt_calculations';
        $this->api_keys_table = $this->wpdb->prefix . 'zc_dmt_api_keys';
        $this->error_logs_table = $this->wpdb->prefix . 'zc_dmt_error_logs';
        $this->backup_history_table = $this->wpdb->prefix . 'zc_dmt_backup_history';
    }

    /**
     * Create all plugin tables
     */
    public function create_tables() {
        $charset_collate = $this->wpdb->get_charset_collate();

        // Indicators table
        $indicators_sql = "CREATE TABLE {$this->indicators_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(100) NOT NULL,
            description TEXT,
            source VARCHAR(100),
            source_id VARCHAR(100),
            unit VARCHAR(50),
            frequency VARCHAR(20),
            last_updated DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug)
        ) $charset_collate;";

        // Data points table
        $data_points_sql = "CREATE TABLE {$this->data_points_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            indicator_id BIGINT(20) UNSIGNED NOT NULL,
            date DATE NOT NULL,
            value DECIMAL(20, 8) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY indicator_date (indicator_id, date),
            KEY date (date),
            CONSTRAINT fk_data_points_indicator FOREIGN KEY (indicator_id) REFERENCES {$this->indicators_table}(id) ON DELETE CASCADE
        ) $charset_collate;";

        // Calculations table
        $calculations_sql = "CREATE TABLE {$this->calculations_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(100) NOT NULL,
            description TEXT,
            formula TEXT NOT NULL,
            dependencies TEXT,
            last_calculated DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug)
        ) $charset_collate;";

        // API keys table
        $api_keys_sql = "CREATE TABLE {$this->api_keys_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            api_key_hash VARCHAR(255) NOT NULL,
            is_active TINYINT(1) DEFAULT 1,
            last_used DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY api_key_hash (api_key_hash)
        ) $charset_collate;";

        // Error logs table
        $error_logs_sql = "CREATE TABLE {$this->error_logs_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            level VARCHAR(20) NOT NULL,
            module VARCHAR(50) NOT NULL,
            action VARCHAR(100) NOT NULL,
            message TEXT NOT NULL,
            context LONGTEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY level (level),
            KEY module (module),
            KEY created_at (created_at)
        ) $charset_collate;";

        // Backup history table
        $backup_history_sql = "CREATE TABLE {$this->backup_history_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            indicator_id BIGINT(20) UNSIGNED,
            file_path VARCHAR(500),
            drive_file_id VARCHAR(255),
            status VARCHAR(20) DEFAULT 'pending',
            size BIGINT(20) UNSIGNED,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            completed_at DATETIME,
            PRIMARY KEY (id),
            KEY indicator_id (indicator_id),
            KEY status (status),
            CONSTRAINT fk_backup_history_indicator FOREIGN KEY (indicator_id) REFERENCES {$this->indicators_table}(id) ON DELETE CASCADE
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        dbDelta($indicators_sql);
        dbDelta($data_points_sql);
        dbDelta($calculations_sql);
        dbDelta($api_keys_sql);
        dbDelta($error_logs_sql);
        dbDelta($backup_history_sql);
    }

    /**
     * Drop all plugin tables
     */
    public function drop_tables() {
        $tables = array(
            $this->backup_history_table,
            $this->error_logs_table,
            $this->api_keys_table,
            $this->calculations_table,
            $this->data_points_table,
            $this->indicators_table
        );

        foreach ($tables as $table) {
            $this->wpdb->query("DROP TABLE IF EXISTS $table");
        }
    }

    /**
     * Insert a new indicator
     */
    public function insert_indicator($data) {
        $result = $this->wpdb->insert(
            $this->indicators_table,
            array(
                'name' => sanitize_text_field($data['name']),
                'slug' => sanitize_key($data['slug']),
                'description' => sanitize_textarea_field($data['description']),
                'source' => sanitize_text_field($data['source']),
                'source_id' => sanitize_text_field($data['source_id']),
                'unit' => sanitize_text_field($data['unit']),
                'frequency' => sanitize_text_field($data['frequency']),
                'last_updated' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );

        if ($result === false) {
            return new WP_Error('insert_indicator_failed', __('Failed to insert indicator.', 'zc-dmt'));
        }

        return $this->wpdb->insert_id;
    }

    /**
     * Get all indicators
     */
    public function get_all_indicators() {
        return $this->wpdb->get_results("SELECT * FROM {$this->indicators_table} ORDER BY name ASC");
    }

    /**
     * Get indicator by ID
     */
    public function get_indicator_by_id($id) {
        return $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->indicators_table} WHERE id = %d", $id)
        );
    }

    /**
     * Get indicator by slug
     */
    public function get_indicator_by_slug($slug) {
        return $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->indicators_table} WHERE slug = %s", $slug)
        );
    }

    /**
     * Update an indicator
     */
    public function update_indicator($id, $data) {
        $result = $this->wpdb->update(
            $this->indicators_table,
            array(
                'name' => sanitize_text_field($data['name']),
                'slug' => sanitize_key($data['slug']),
                'description' => sanitize_textarea_field($data['description']),
                'source' => sanitize_text_field($data['source']),
                'source_id' => sanitize_text_field($data['source_id']),
                'unit' => sanitize_text_field($data['unit']),
                'frequency' => sanitize_text_field($data['frequency']),
                'updated_at' => current_time('mysql')
            ),
            array('id' => $id),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'),
            array('%d')
        );

        if ($result === false) {
            return new WP_Error('update_indicator_failed', __('Failed to update indicator.', 'zc-dmt'));
        }

        return true;
    }

    /**
     * Delete an indicator
     */
    public function delete_indicator($id) {
        $result = $this->wpdb->delete(
            $this->indicators_table,
            array('id' => $id),
            array('%d')
        );

        if ($result === false) {
            return new WP_Error('delete_indicator_failed', __('Failed to delete indicator.', 'zc-dmt'));
        }

        return true;
    }

    /**
     * Insert a data point
     */
    public function insert_data_point($indicator_id, $date, $value) {
        $result = $this->wpdb->insert(
            $this->data_points_table,
            array(
                'indicator_id' => $indicator_id,
                'date' => $date,
                'value' => $value
            ),
            array('%d', '%s', '%f')
        );

        if ($result === false) {
            return new WP_Error('insert_data_point_failed', __('Failed to insert data point.', 'zc-dmt'));
        }

        return $this->wpdb->insert_id;
    }

    /**
     * Get data points for an indicator
     */
    public function get_data_points($indicator_id, $limit = null, $start_date = null, $end_date = null) {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->data_points_table} WHERE indicator_id = %d",
            $indicator_id
        );

        $params = array($indicator_id);

        if ($start_date) {
            $sql .= " AND date >= %s";
            $params[] = $start_date;
        }

        if ($end_date) {
            $sql .= " AND date <= %s";
            $params[] = $end_date;
        }

        $sql .= " ORDER BY date DESC";

        if ($limit) {
            $sql .= " LIMIT %d";
            $params[] = $limit;
        }

        return $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $params)
        );
    }

    /**
     * Get latest data point for an indicator
     */
    public function get_latest_data_point($indicator_id) {
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->data_points_table} WHERE indicator_id = %d ORDER BY date DESC LIMIT 1",
                $indicator_id
            )
        );
    }

    /**
     * Insert a calculation
     */
    public function insert_calculation($data) {
        $result = $this->wpdb->insert(
            $this->calculations_table,
            array(
                'name' => sanitize_text_field($data['name']),
                'slug' => sanitize_key($data['slug']),
                'description' => sanitize_textarea_field($data['description']),
                'formula' => sanitize_textarea_field($data['formula']),
                'dependencies' => maybe_serialize($data['dependencies'])
            ),
            array('%s', '%s', '%s', '%s', '%s')
        );

        if ($result === false) {
            return new WP_Error('insert_calculation_failed', __('Failed to insert calculation.', 'zc-dmt'));
        }

        return $this->wpdb->insert_id;
    }

    /**
     * Get all calculations
     */
    public function get_all_calculations() {
        $calculations = $this->wpdb->get_results("SELECT * FROM {$this->calculations_table} ORDER BY name ASC");
        
        // Unserialize dependencies
        foreach ($calculations as &$calculation) {
            $calculation->dependencies = maybe_unserialize($calculation->dependencies);
        }
        
        return $calculations;
    }

    /**
     * Get calculation by ID
     */
    public function get_calculation_by_id($id) {
        $calculation = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->calculations_table} WHERE id = %d", $id)
        );
        
        if ($calculation) {
            $calculation->dependencies = maybe_unserialize($calculation->dependencies);
        }
        
        return $calculation;
    }

    /**
     * Get calculation by slug
     */
    public function get_calculation_by_slug($slug) {
        $calculation = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->calculations_table} WHERE slug = %s", $slug)
        );
        
        if ($calculation) {
            $calculation->dependencies = maybe_unserialize($calculation->dependencies);
        }
        
        return $calculation;
    }

    /**
     * Update a calculation
     */
    public function update_calculation($id, $data) {
        $result = $this->wpdb->update(
            $this->calculations_table,
            array(
                'name' => sanitize_text_field($data['name']),
                'slug' => sanitize_key($data['slug']),
                'description' => sanitize_textarea_field($data['description']),
                'formula' => sanitize_textarea_field($data['formula']),
                'dependencies' => maybe_serialize($data['dependencies']),
                'updated_at' => current_time('mysql')
            ),
            array('id' => $id),
            array('%s', '%s', '%s', '%s', '%s', '%s'),
            array('%d')
        );

        if ($result === false) {
            return new WP_Error('update_calculation_failed', __('Failed to update calculation.', 'zc-dmt'));
        }

        return true;
    }

    /**
     * Delete a calculation
     */
    public function delete_calculation($id) {
        $result = $this->wpdb->delete(
            $this->calculations_table,
            array('id' => $id),
            array('%d')
        );

        if ($result === false) {
            return new WP_Error('delete_calculation_failed', __('Failed to delete calculation.', 'zc-dmt'));
        }

        return true;
    }

    /**
     * Insert an API key
     */
    public function insert_api_key($name, $api_key_hash) {
        $result = $this->wpdb->insert(
            $this->api_keys_table,
            array(
                'name' => sanitize_text_field($name),
                'api_key_hash' => $api_key_hash,
                'is_active' => 1,
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%d', '%s')
        );

        if ($result === false) {
            return new WP_Error('insert_api_key_failed', __('Failed to insert API key.', 'zc-dmt'));
        }

        return $this->wpdb->insert_id;
    }

    /**
     * Get all API keys
     */
    public function get_all_api_keys() {
        return $this->wpdb->get_results("SELECT * FROM {$this->api_keys_table} ORDER BY created_at DESC");
    }

    /**
     * Get active API key by hash
     */
    public function get_active_api_key($api_key_hash) {
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->api_keys_table} WHERE api_key_hash = %s AND is_active = 1",
                $api_key_hash
            )
        );
    }

    /**
     * Update API key last used timestamp
     */
    public function update_api_key_last_used($id) {
        $result = $this->wpdb->update(
            $this->api_keys_table,
            array('last_used' => current_time('mysql')),
            array('id' => $id),
            array('%s'),
            array('%d')
        );

        if ($result === false) {
            return new WP_Error('update_api_key_failed', __('Failed to update API key.', 'zc-dmt'));
        }

        return true;
    }

    /**
     * Revoke (deactivate) an API key
     */
    public function revoke_api_key($id) {
        $result = $this->wpdb->update(
            $this->api_keys_table,
            array('is_active' => 0),
            array('id' => $id),
            array('%d'),
            array('%d')
        );

        if ($result === false) {
            return new WP_Error('revoke_api_key_failed', __('Failed to revoke API key.', 'zc-dmt'));
        }

        return true;
    }

    /**
     * Activate an API key
     */
    public function activate_api_key($id) {
        $result = $this->wpdb->update(
            $this->api_keys_table,
            array('is_active' => 1),
            array('id' => $id),
            array('%d'),
            array('%d')
        );

        if ($result === false) {
            return new WP_Error('activate_api_key_failed', __('Failed to activate API key.', 'zc-dmt'));
        }

        return true;
    }

    /**
     * Log an error
     */
    public function log_error($level, $module, $action, $message, $context = null) {
        $result = $this->wpdb->insert(
            $this->error_logs_table,
            array(
                'level' => sanitize_text_field($level),
                'module' => sanitize_text_field($module),
                'action' => sanitize_text_field($action),
                'message' => sanitize_textarea_field($message),
                'context' => maybe_serialize($context),
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s')
        );

        if ($result === false) {
            // If logging fails, we can't do much but return an error
            return new WP_Error('log_error_failed', __('Failed to log error.', 'zc-dmt'));
        }

        return $this->wpdb->insert_id;
    }

    /**
     * Get error logs
     */
    public function get_error_logs($args = array()) {
        $defaults = array(
            'limit' => 50,
            'offset' => 0,
            'level' => '',
            'module' => '',
            'order_by' => 'created_at',
            'order' => 'DESC'
        );

        $args = wp_parse_args($args, $defaults);

        $sql = "SELECT * FROM {$this->error_logs_table}";
        $where_conditions = array();
        $params = array();

        if (!empty($args['level'])) {
            $where_conditions[] = "level = %s";
            $params[] = $args['level'];
        }

        if (!empty($args['module'])) {
            $where_conditions[] = "module = %s";
            $params[] = $args['module'];
        }

        if (!empty($where_conditions)) {
            $sql .= " WHERE " . implode(" AND ", $where_conditions);
        }

        $sql .= " ORDER BY {$args['order_by']} {$args['order']} LIMIT %d OFFSET %d";
        $params[] = $args['limit'];
        $params[] = $args['offset'];

        return $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $params)
        );
    }

    /**
     * Get error log counts by level
     */
    public function get_error_log_counts() {
        $results = $this->wpdb->get_results(
            "SELECT level, COUNT(*) as count FROM {$this->error_logs_table} GROUP BY level"
        );

        $counts = array(
            'info' => 0,
            'warning' => 0,
            'error' => 0,
            'critical' => 0
        );

        foreach ($results as $row) {
            $counts[$row->level] = (int)$row->count;
        }

        return $counts;
    }

    /**
     * Clear error logs
     */
    public function clear_error_logs() {
        return $this->wpdb->query("DELETE FROM {$this->error_logs_table}");
    }

    /**
     * Insert backup history record
     */
    public function insert_backup_history($indicator_id, $file_path, $drive_file_id = null) {
        $result = $this->wpdb->insert(
            $this->backup_history_table,
            array(
                'indicator_id' => $indicator_id,
                'file_path' => $file_path,
                'drive_file_id' => $drive_file_id,
                'status' => 'pending',
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%s')
        );

        if ($result === false) {
            return new WP_Error('insert_backup_history_failed', __('Failed to insert backup history record.', 'zc-dmt'));
        }

        return $this->wpdb->insert_id;
    }

    /**
     * Update backup history record
     */
    public function update_backup_history($id, $data) {
        $result = $this->wpdb->update(
            $this->backup_history_table,
            $data,
            array('id' => $id),
            array('%s', '%s', '%s', '%d', '%s'),
            array('%d')
        );

        if ($result === false) {
            return new WP_Error('update_backup_history_failed', __('Failed to update backup history record.', 'zc-dmt'));
        }

        return true;
    }

    /**
     * Get backup history
     */
    public function get_backup_history($indicator_id = null, $limit = 50) {
        $sql = "SELECT bh.*, i.name as indicator_name FROM {$this->backup_history_table} bh";
        
        if ($indicator_id) {
            $sql .= " WHERE bh.indicator_id = %d";
            $params = array($indicator_id);
        } else {
            $sql .= " LEFT JOIN {$this->indicators_table} i ON bh.indicator_id = i.id";
            $params = array();
        }
        
        $sql .= " ORDER BY bh.created_at DESC LIMIT %d";
        $params[] = $limit;

        return $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $params)
        );
    }

    /**
     * Get pending backups
     */
    public function get_pending_backups() {
        return $this->wpdb->get_results(
            "SELECT * FROM {$this->backup_history_table} WHERE status = 'pending'"
        );
    }
}
?>
