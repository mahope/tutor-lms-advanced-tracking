<?php
/**
 * Plugin Name: Advanced Tutor LMS Stats Dashboard
 * Plugin URI: https://github.com/madsholst/tutor-lms-advanced-tracking
 * Description: Extends Tutor LMS with advanced statistics and detailed insights into course and user data. Requires Tutor LMS 2.0.0 or higher.
 * Version: 1.0.0
 * Author: Mads Holst Jensen
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: tutor-lms-advanced-tracking
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.3
 * Requires PHP: 7.4
 * Requires Plugins: tutor
 * Network: false
 * Update URI: https://github.com/madsholst/tutor-lms-advanced-tracking
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('TUTOR_ADVANCED_TRACKING_VERSION', '1.0.0');
define('TUTOR_ADVANCED_TRACKING_PLUGIN_FILE', __FILE__);
define('TUTOR_ADVANCED_TRACKING_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TUTOR_ADVANCED_TRACKING_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Main plugin class
 */
class TutorAdvancedTracking {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('plugins_loaded', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Check for plugin updates
        add_action('admin_init', array($this, 'check_plugin_update'));
        
        // Monitor Tutor LMS status
        add_action('admin_init', array($this, 'monitor_tutor_lms_status'));
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Check if Tutor LMS is active
        if (!$this->is_tutor_lms_active()) {
            add_action('admin_notices', array($this, 'tutor_lms_missing_notice'));
            return;
        }
        
        // Load plugin files
        if (!$this->load_files()) {
            return; // Exit if files couldn't be loaded
        }
        
        // Initialize cache hooks
        TutorAdvancedTracking_Cache::init_cache_hooks();
        
        // Ensure legacy Tutor LMS tables are mapped across versions
        TutorAdvancedTracking_TutorIntegration::ensure_table_alias_filter();
        
        // Initialize components
        $this->init_components();
        
        // Load textdomain
        load_plugin_textdomain('tutor-lms-advanced-tracking', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Load debug tools in admin
        if (is_admin()) {
            include_once TUTOR_ADVANCED_TRACKING_PLUGIN_DIR . 'debug-admin-direct.php';
            include_once TUTOR_ADVANCED_TRACKING_PLUGIN_DIR . 'test-enrollment-data.php';
        }
    }
    
    /**
     * Load required files
     */
    private function load_files() {
        $required_files
        [] = 'includes/class-events-db.php';
        [] = 'includes/class-cli.php';
        
        foreach ($required_files as $file) {
            $filepath = TUTOR_ADVANCED_TRACKING_PLUGIN_DIR . $file;
            if (!file_exists($filepath)) {
                add_action('admin_notices', function() use ($file) {
                    echo '<div class="notice notice-error"><p>';
                    echo sprintf(__('Advanced Tutor LMS Stats Dashboard: Required file missing: %s', 'tutor-lms-advanced-tracking'), $file);
                    echo '</p></div>';
                });
                return false;
            }
            require_once $filepath;
        }
        
        return true;
    }
    
    /**
     * Initialize components
     */
    private function init_components() {
        $components = array(
            'TutorAdvancedTracking_Shortcode',
            'TutorAdvancedTracking_Dashboard', 
            'TutorAdvancedTracking_CourseStats',
            'TutorAdvancedTracking_UserStats',
            'TutorAdvancedTracking_AdvancedAnalytics',
            'TutorAdvancedTracking_Export',
            'TutorAdvancedTracking_Notifications',
            'TutorAdvancedTracking_Charts',
            'TutorAdvancedTracking_API',
            'TutorAdvancedTracking_Admin',
            'TutorAdvancedTracking_Engagement',
            'TutorAdvancedTracking_CohortAnalytics'
        );
        
        foreach ($components as $component) {
            try {
                if (class_exists($component)) {
                    new $component();
                } else {
                    error_log("Advanced Tutor LMS Stats Dashboard: Class $component not found");
                }
            } catch (Exception $e) {
                error_log("Advanced Tutor LMS Stats Dashboard: Error initializing $component: " . $e->getMessage());
                add_action('admin_notices', function() use ($component, $e) {
                    echo '<div class="notice notice-error"><p>';
                    echo sprintf(__('Advanced Tutor LMS Stats Dashboard: Error initializing %s. Check error logs.', 'tutor-lms-advanced-tracking'), $component);
                    echo '</p></div>';
                });
            }
        }
    }
    
    /**
     * Check if Tutor LMS is active
     */
    private function is_tutor_lms_active() {
        return function_exists('tutor') || class_exists('TUTOR\Tutor');
    }
    
    /**
     * Check if Tutor LMS version is compatible
     */
    private function is_tutor_lms_version_compatible() {
        if (!$this->is_tutor_lms_active()) {
            return false;
        }
        
        // Get Tutor LMS version
        $tutor_version = '';
        if (defined('TUTOR_VERSION')) {
            $tutor_version = TUTOR_VERSION;
        } elseif (function_exists('tutor')) {
            $tutor_data = get_plugin_data(WP_PLUGIN_DIR . '/tutor/tutor.php');
            $tutor_version = $tutor_data['Version'] ?? '';
        }
        
        // Check if version is 2.0.0 or higher
        return version_compare($tutor_version, '2.0.0', '>=');
    }
    
    /**
     * Display notice when Tutor LMS is missing
     */
    public function tutor_lms_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p><?php _e('Advanced Tutor LMS Stats Dashboard requires Tutor LMS plugin to be installed and activated.', 'tutor-lms-advanced-tracking'); ?></p>
        </div>
        <?php
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Check if Tutor LMS is active before activation
        if (!$this->is_tutor_lms_active()) {
            wp_die(
                __('Advanced Tutor LMS Stats Dashboard requires Tutor LMS plugin to be installed and activated.', 'tutor-lms-advanced-tracking'),
                __('Plugin Activation Error', 'tutor-lms-advanced-tracking'),
                array('back_link' => true)
            );
        }
        
        // Check minimum Tutor LMS version
        if (!$this->is_tutor_lms_version_compatible()) {
            wp_die(
                __('Advanced Tutor LMS Stats Dashboard requires Tutor LMS version 2.0.0 or higher.', 'tutor-lms-advanced-tracking'),
                __('Plugin Activation Error', 'tutor-lms-advanced-tracking'),
                array('back_link' => true)
            );
        }
        
        // Create any necessary database tables or options
        $this

        // Ensure custom events tables exist
        if (!class_exists('TutorAdvancedTracking_EventsDB')) {
            require_once TUTOR_ADVANCED_TRACKING_PLUGIN_DIR . 'includes/class-events-db.php';
        }
        TutorAdvancedTracking_EventsDB::install();
        
        // Set plugin version for updates
        update_option('tutor_advanced_tracking_version', TUTOR_ADVANCED_TRACKING_VERSION);
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clean up any temporary data
        flush_rewrite_rules();
    }
    
    /**
     * Create database tables if needed
     */
    private function create_database_tables() {
        global $wpdb;
        
        // For now, we'll use existing WordPress and Tutor LMS tables
        // Add useful database indexes for performance optimization
        $this->add_performance_indexes();
    }
    
    /**
     * Add database indexes for better performance
     */
    private function add_performance_indexes() {
        global $wpdb;
        
        // Add indexes to improve query performance (if they don't exist)
        $indexes = array(
            // Tutor enrollments table
            "CREATE INDEX IF NOT EXISTS idx_tutor_enrollments_course_user 
             ON {$wpdb->prefix}tutor_enrollments (course_id, user_id)",
            "CREATE INDEX IF NOT EXISTS idx_tutor_enrollments_date 
             ON {$wpdb->prefix}tutor_enrollments (enrollment_date)",
            
            // Tutor quiz attempts table  
            "CREATE INDEX IF NOT EXISTS idx_tutor_quiz_attempts_user_course 
             ON {$wpdb->prefix}tutor_quiz_attempts (user_id, quiz_id)",
            "CREATE INDEX IF NOT EXISTS idx_tutor_quiz_attempts_date 
             ON {$wpdb->prefix}tutor_quiz_attempts (attempt_started_at)",
            
            // Tutor lesson activities table
            "CREATE INDEX IF NOT EXISTS idx_tutor_lesson_activities_user_course 
             ON {$wpdb->prefix}tutor_lesson_activities (user_id, course_id)",
            "CREATE INDEX IF NOT EXISTS idx_tutor_lesson_activities_lesson 
             ON {$wpdb->prefix}tutor_lesson_activities (lesson_id, activity_status)"
        );
        
        foreach ($indexes as $sql) {
            // Only attempt to add if table exists
            $table_name = $this->extract_table_name($sql);
            if ($this->table_exists($table_name)) {
                $wpdb->query($sql);
            }
        }
    }
    
    /**
     * Extract table name from CREATE INDEX SQL
     */
    private function extract_table_name($sql) {
        if (preg_match('/ON\s+(\S+)\s+/i', $sql, $matches)) {
            return $matches[1];
        }
        return '';
    }
    
    /**
     * Check if table exists
     */
    private function table_exists($table_name) {
        global $wpdb;
        $result = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));
        return $result === $table_name;
    }
    
    /**
     * Check for plugin updates
     */
    public function check_plugin_update() {
        $current_version = get_option('tutor_advanced_tracking_version', '0.0.0');
        
        if (version_compare($current_version, TUTOR_ADVANCED_TRACKING_VERSION, '<')) {
            $this->run_plugin_update($current_version);
        }
    }
    
    /**
     * Run plugin update procedures
     */
    private function run_plugin_update($old_version) {
        // Perform version-specific updates
        if (version_compare($old_version, '1.0.0', '<')) {
            // Update to version 1.0.0
            $this->create_database_tables();
        }
        
        // Update version in database
        update_option('tutor_advanced_tracking_version', TUTOR_ADVANCED_TRACKING_VERSION);
        
        // Clear any cached data after update
        $this->clear_plugin_cache();
        
        // Log successful update
        error_log("Advanced Tutor LMS Stats Dashboard updated from {$old_version} to " . TUTOR_ADVANCED_TRACKING_VERSION);
    }
    
    /**
     * Monitor Tutor LMS status and deactivate if necessary
     */
    public function monitor_tutor_lms_status() {
        // Only check on admin pages to avoid performance issues
        if (!is_admin()) {
            return;
        }
        
        // Skip check during plugin activation/deactivation
        if (isset($_GET['action']) && in_array($_GET['action'], array('activate', 'deactivate'))) {
            return;
        }
        
        // If Tutor LMS is not active, deactivate this plugin
        if (!$this->is_tutor_lms_active()) {
            deactivate_plugins(plugin_basename(__FILE__));
            add_action('admin_notices', function() {
                ?>
                <div class="notice notice-error">
                    <p><?php _e('Advanced Tutor LMS Stats Dashboard has been deactivated because Tutor LMS is no longer active.', 'tutor-lms-advanced-tracking'); ?></p>
                </div>
                <?php
            });
        }
    }
    
    /**
     * Clear plugin cache
     */
    private function clear_plugin_cache() {
        // Clear WordPress transients
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_tutor_advanced_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_tutor_advanced_%'");
        
        // Clear WordPress object cache if available
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
    }
}

// Initialize the plugin
new TutorAdvancedTracking();