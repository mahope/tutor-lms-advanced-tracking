<?php
/**
 * Plugin Name: Advanced Tutor LMS Stats Dashboard
 * Plugin URI: https://github.com/madsholst/tutor-lms-advanced-tracking
 * Description: Extends Tutor LMS Pro with advanced statistics and detailed insights into course and user data.
 * Version: 1.0.0
 * Author: Mads Holst Jensen
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: tutor-lms-advanced-tracking
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.3
 * Requires PHP: 7.4
 * Network: false
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
        $this->load_files();
        
        // Initialize components
        $this->init_components();
        
        // Load textdomain
        load_plugin_textdomain('tutor-lms-advanced-tracking', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    /**
     * Load required files
     */
    private function load_files() {
        require_once TUTOR_ADVANCED_TRACKING_PLUGIN_DIR . 'includes/class-shortcode.php';
        require_once TUTOR_ADVANCED_TRACKING_PLUGIN_DIR . 'includes/class-dashboard.php';
        require_once TUTOR_ADVANCED_TRACKING_PLUGIN_DIR . 'includes/class-course-stats.php';
        require_once TUTOR_ADVANCED_TRACKING_PLUGIN_DIR . 'includes/class-user-stats.php';
    }
    
    /**
     * Initialize components
     */
    private function init_components() {
        new TutorAdvancedTracking_Shortcode();
        new TutorAdvancedTracking_Dashboard();
        new TutorAdvancedTracking_CourseStats();
        new TutorAdvancedTracking_UserStats();
    }
    
    /**
     * Check if Tutor LMS is active
     */
    private function is_tutor_lms_active() {
        return function_exists('tutor') || class_exists('TUTOR\Tutor');
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
        // Create any necessary database tables or options
        $this->create_database_tables();
        
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
        // For now, we'll use existing WordPress and Tutor LMS tables
        // Future versions might need custom tables for advanced analytics
    }
}

// Initialize the plugin
new TutorAdvancedTracking();