<?php
/**
 * Admin Settings and Dashboard Class
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class TutorAdvancedTracking_Admin {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_tutor_advanced_debug_action', array($this, 'handle_debug_actions'));
        add_action('wp_ajax_tutor_advanced_cache_action', array($this, 'handle_cache_actions'));
        add_action('admin_notices', array($this, 'show_admin_notices'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_extra_assets'));
    }

    public function enqueue_extra_assets(){
        wp_enqueue_script('tutor-advanced-extra', TUTOR_ADVANCED_TRACKING_PLUGIN_URL . 'assets/js/advanced-analytics-extra.js', array('jquery'), TUTOR_ADVANCED_TRACKING_VERSION, true);
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Main menu page
        add_menu_page(
            __('Advanced Tutor Stats', 'tutor-lms-advanced-tracking'),
            __('Tutor Stats', 'tutor-lms-advanced-tracking'),
            'manage_options',
            'tutor-advanced-stats',
            array($this, 'render_dashboard_page'),
            'dashicons-chart-bar',
            30
        );
        
        // Dashboard submenu
        add_submenu_page(
            'tutor-advanced-stats',
            __('Dashboard', 'tutor-lms-advanced-tracking'),
            __('Dashboard', 'tutor-lms-advanced-tracking'),
            'manage_options',
            'tutor-advanced-stats',
            array($this, 'render_dashboard_page')
        );
        
        // Settings submenu
        add_submenu_page(
            'tutor-advanced-stats',
            __('Settings', 'tutor-lms-advanced-tracking'),
            __('Settings', 'tutor-lms-advanced-tracking'),
            'manage_options',
            'tutor-advanced-stats-settings',
            array($this, 'render_settings_page')
        );
        
        // Debug Tools submenu
        add_submenu_page(
            'tutor-advanced-stats',
            __('Debug Tools', 'tutor-lms-advanced-tracking'),
            __('Debug Tools', 'tutor-lms-advanced-tracking'),
            'manage_options',
            'tutor-advanced-stats-debug',
            array($this, 'render_debug_page')
        );
        
        // System Info submenu
        add_submenu_page(
            'tutor-advanced-stats',
            __('System Info', 'tutor-lms-advanced-tracking'),
            __('System Info', 'tutor-lms-advanced-tracking'),
            'manage_options',
            'tutor-advanced-stats-system',
            array($this, 'render_system_info_page')
        );

        // Assignment Timeline submenu
        add_submenu_page(
            'tutor-advanced-stats',
            __('Assignment Timeline', 'tutor-lms-advanced-tracking'),
            __('Assignments', 'tutor-lms-advanced-tracking'),
            'manage_options',
            'tutor-advanced-stats-assignments',
            array($this, 'render_assignment_timeline_page')
        );
        
        // Engagement Analytics submenu
        add_submenu_page(
            'tutor-advanced-stats',
            __('Engagement Analytics', 'tutor-lms-advanced-tracking'),
            __('Engagement', 'tutor-lms-advanced-tracking'),
            'manage_options',
            'tutor-advanced-stats-engagement',
            array($this, 'render_engagement_page')
        );
        
        // Live Activity submenu
        add_submenu_page(
            'tutor-advanced-stats',
            __('Live Activity', 'tutor-lms-advanced-tracking'),
            __('Live Activity', 'tutor-lms-advanced-tracking'),
            'manage_options',
            'tutor-advanced-stats-live-activity',
            array($this, 'render_live_activity_page')
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('tutor_advanced_stats_settings', 'tutor_advanced_stats_options', array(
            'sanitize_callback' => array($this, 'sanitize_settings')
        ));
        
        // General Settings Section
        add_settings_section(
            'general_settings',
            __('General Settings', 'tutor-lms-advanced-tracking'),
            array($this, 'general_settings_callback'),
            'tutor_advanced_stats_settings'
        );
        
        // Cache Settings Section
        add_settings_section(
            'cache_settings',
            __('Cache Settings', 'tutor-lms-advanced-tracking'),
            array($this, 'cache_settings_callback'),
            'tutor_advanced_stats_settings'
        );
        
        // Debug Settings Section
        add_settings_section(
            'debug_settings',
            __('Debug Settings', 'tutor-lms-advanced-tracking'),
            array($this, 'debug_settings_callback'),
            'tutor_advanced_stats_settings'
        );
        
        // Add individual settings fields
        $this->add_settings_fields();
    }
    
    /**
     * Add settings fields
     */
    private function add_settings_fields() {
        // Enable/Disable Plugin
        add_settings_field(
            'enable_plugin',
            __('Enable Plugin', 'tutor-lms-advanced-tracking'),
            array($this, 'render_checkbox_field'),
            'tutor_advanced_stats_settings',
            'general_settings',
            array('field' => 'enable_plugin', 'description' => __('Enable or disable the plugin functionality', 'tutor-lms-advanced-tracking'))
        );
        
        // Dashboard Access Role
        add_settings_field(
            'dashboard_access_role',
            __('Dashboard Access', 'tutor-lms-advanced-tracking'),
            array($this, 'render_select_field'),
            'tutor_advanced_stats_settings',
            'general_settings',
            array(
                'field' => 'dashboard_access_role',
                'options' => array(
                    'administrator' => __('Administrators Only', 'tutor-lms-advanced-tracking'),
                    'instructor' => __('Administrators & Instructors', 'tutor-lms-advanced-tracking'),
                    'editor' => __('Editors and Above', 'tutor-lms-advanced-tracking')
                ),
                'description' => __('Who can access the frontend dashboard', 'tutor-lms-advanced-tracking')
            )
        );
        
        // Cache Duration
        add_settings_field(
            'cache_duration',
            __('Cache Duration (minutes)', 'tutor-lms-advanced-tracking'),
            array($this, 'render_number_field'),
            'tutor_advanced_stats_settings',
            'cache_settings',
            array(
                'field' => 'cache_duration',
                'min' => 1,
                'max' => 1440,
                'description' => __('How long to cache data before refreshing', 'tutor-lms-advanced-tracking')
            )
        );
        
        // Enable Debug Mode
        add_settings_field(
            'debug_mode',
            __('Debug Mode', 'tutor-lms-advanced-tracking'),
            array($this, 'render_checkbox_field'),
            'tutor_advanced_stats_settings',
            'debug_settings',
            array('field' => 'debug_mode', 'description' => __('Enable debug logging and error reporting', 'tutor-lms-advanced-tracking'))
        );
        
        // Enable Performance Monitoring
        add_settings_field(
            'performance_monitoring',
            __('Performance Monitoring', 'tutor-lms-advanced-tracking'),
            array($this, 'render_checkbox_field'),
            'tutor_advanced_stats_settings',
            'debug_settings',
            array('field' => 'performance_monitoring', 'description' => __('Track query performance and execution times', 'tutor-lms-advanced-tracking'))
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on our admin pages
        if (strpos($hook, 'tutor-advanced-stats') === false) {
            return;
        }
        
        wp_enqueue_style(
            'tutor-advanced-admin-style',
            TUTOR_ADVANCED_TRACKING_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            TUTOR_ADVANCED_TRACKING_VERSION
        );
        
        wp_enqueue_script(
            'tutor-advanced-admin-script',
            TUTOR_ADVANCED_TRACKING_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            TUTOR_ADVANCED_TRACKING_VERSION,
            true
        );
        
        wp_localize_script('tutor-advanced-admin-script', 'tutorAdvancedAdmin', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('tutor_advanced_admin_action'),
            'strings' => array(
                'confirm_cache_clear' => __('Are you sure you want to clear all cache?', 'tutor-lms-advanced-tracking'),
                'confirm_debug_run' => __('This may take a while. Continue?', 'tutor-lms-advanced-tracking'),
                'success' => __('Action completed successfully', 'tutor-lms-advanced-tracking'),
                'error' => __('An error occurred', 'tutor-lms-advanced-tracking')
            )
        ));
    }
    
    /**
     * Render dashboard page
     */
    public function render_dashboard_page() {
        $dashboard = new TutorAdvancedTracking_Dashboard();
        include TUTOR_ADVANCED_TRACKING_PLUGIN_DIR . 'templates/admin-dashboard.php';
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (isset($_GET['settings-updated'])) {
            add_settings_error('tutor_advanced_stats_messages', 'tutor_advanced_stats_message', 
                __('Settings saved successfully.', 'tutor-lms-advanced-tracking'), 'updated');
        }
        include TUTOR_ADVANCED_TRACKING_PLUGIN_DIR . 'templates/admin-settings.php';
    }
    
    /**
     * Render debug page
     */
    public function render_debug_page() {
        include TUTOR_ADVANCED_TRACKING_PLUGIN_DIR . 'templates/admin-debug.php';
    }
    
    /**
     * Render system info page
     */
    public function render_system_info_page() {
        include TUTOR_ADVANCED_TRACKING_PLUGIN_DIR . 'templates/admin-system-info.php';
    }

    /**
     * Render assignment timeline page
     */
    public function render_assignment_timeline_page() {
        // Enqueue assignment analytics scripts
        wp_enqueue_style(
            'tutor-advanced-assignment-analytics',
            TUTOR_ADVANCED_TRACKING_PLUGIN_URL . 'assets/css/assignment-analytics.css',
            array(),
            TUTOR_ADVANCED_TRACKING_VERSION
        );

        wp_enqueue_script(
            'tutor-advanced-assignment-analytics',
            TUTOR_ADVANCED_TRACKING_PLUGIN_URL . 'assets/js/assignment-analytics.js',
            array('jquery', 'tutor-advanced-charts'),
            TUTOR_ADVANCED_TRACKING_VERSION,
            true
        );

        wp_localize_script('tutor-advanced-assignment-analytics', 'tutor_advanced_vars', array(
            'api_url' => rest_url(),
            'nonce' => wp_create_nonce('wp_rest'),
            'strings' => array(
                'loading' => __('Loading...', 'tutor-lms-advanced-tracking'),
                'no_data' => __('No data available', 'tutor-lms-advanced-tracking'),
                'error' => __('An error occurred', 'tutor-lms-advanced-tracking')
            )
        ));

        include TUTOR_ADVANCED_TRACKING_PLUGIN_DIR . 'templates/admin-assignment-timeline.php';
    }
    
    /**
     * Settings section callbacks
     */
    public function general_settings_callback() {
        echo '<p>' . __('Configure general plugin settings.', 'tutor-lms-advanced-tracking') . '</p>';
    }
    
    public function cache_settings_callback() {
        echo '<p>' . __('Configure caching behavior for better performance.', 'tutor-lms-advanced-tracking') . '</p>';
    }
    
    public function debug_settings_callback() {
        echo '<p>' . __('Development and debugging options.', 'tutor-lms-advanced-tracking') . '</p>';
    }
    
    /**
     * Render form fields
     */
    public function render_checkbox_field($args) {
        $options = get_option('tutor_advanced_stats_options', array());
        $field = $args['field'];
        $value = isset($options[$field]) ? $options[$field] : false;
        ?>
        <label>
            <input type="checkbox" name="tutor_advanced_stats_options[<?php echo esc_attr($field); ?>]" 
                   value="1" <?php checked($value, 1); ?> />
            <?php echo esc_html($args['description']); ?>
        </label>
        <?php
    }
    
    public function render_select_field($args) {
        $options = get_option('tutor_advanced_stats_options', array());
        $field = $args['field'];
        $value = isset($options[$field]) ? $options[$field] : '';
        ?>
        <select name="tutor_advanced_stats_options[<?php echo esc_attr($field); ?>]">
            <?php foreach ($args['options'] as $option_value => $option_label): ?>
                <option value="<?php echo esc_attr($option_value); ?>" <?php selected($value, $option_value); ?>>
                    <?php echo esc_html($option_label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php
    }
    
    public function render_number_field($args) {
        $options = get_option('tutor_advanced_stats_options', array());
        $field = $args['field'];
        $value = isset($options[$field]) ? $options[$field] : 5;
        ?>
        <input type="number" name="tutor_advanced_stats_options[<?php echo esc_attr($field); ?>]" 
               value="<?php echo esc_attr($value); ?>" 
               min="<?php echo esc_attr($args['min']); ?>" 
               max="<?php echo esc_attr($args['max']); ?>" />
        <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php
    }
    
    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        if (isset($input['enable_plugin'])) {
            $sanitized['enable_plugin'] = (bool) $input['enable_plugin'];
        }
        
        if (isset($input['dashboard_access_role'])) {
            $allowed_roles = array('administrator', 'instructor', 'editor');
            $sanitized['dashboard_access_role'] = in_array($input['dashboard_access_role'], $allowed_roles) 
                ? $input['dashboard_access_role'] : 'administrator';
        }
        
        if (isset($input['cache_duration'])) {
            $sanitized['cache_duration'] = max(1, min(1440, (int) $input['cache_duration']));
        }
        
        if (isset($input['debug_mode'])) {
            $sanitized['debug_mode'] = (bool) $input['debug_mode'];
        }
        
        if (isset($input['performance_monitoring'])) {
            $sanitized['performance_monitoring'] = (bool) $input['performance_monitoring'];
        }
        
        return $sanitized;
    }
    
    /**
     * Handle debug actions via AJAX
     */
    public function handle_debug_actions() {
        if (!wp_verify_nonce($_POST['nonce'], 'tutor_advanced_admin_action')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $action = sanitize_text_field($_POST['debug_action']);
        
        switch ($action) {
            case 'run_data_diagnostic':
                $result = $this->run_data_diagnostic();
                break;
            case 'test_tutor_integration':
                $result = $this->test_tutor_integration();
                break;
            case 'validate_database':
                $result = $this->validate_database();
                break;
            default:
                $result = array('success' => false, 'message' => 'Unknown action');
        }
        
        wp_send_json($result);
    }
    
    /**
     * Handle cache actions via AJAX
     */
    public function handle_cache_actions() {
        if (!wp_verify_nonce($_POST['nonce'], 'tutor_advanced_admin_action')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $action = sanitize_text_field($_POST['cache_action']);
        
        switch ($action) {
            case 'clear_all':
                TutorAdvancedTracking_Cache::flush_all();
                $result = array('success' => true, 'message' => 'All cache cleared successfully');
                break;
            case 'clear_courses':
                $this->clear_courses_cache();
                $result = array('success' => true, 'message' => 'Course cache cleared successfully');
                break;
            default:
                $result = array('success' => false, 'message' => 'Unknown cache action');
        }
        
        wp_send_json($result);
    }
    
    /**
     * Show admin notices
     */
    public function show_admin_notices() {
        settings_errors('tutor_advanced_stats_messages');
    }
    
    /**
     * Debug methods
     */
    private function run_data_diagnostic() {
        global $wpdb;
        
        $diagnostic_data = array();
        
        try {
            // Test dashboard
            $dashboard = new TutorAdvancedTracking_Dashboard();
            $courses = $dashboard->get_courses();
            $diagnostic_data['courses_found'] = count($courses);
            $diagnostic_data['sample_course'] = !empty($courses) ? $courses[0] : null;
            
            // Test Tutor LMS functions
            $diagnostic_data['tutor_active'] = function_exists('tutor');
            $diagnostic_data['tutor_version'] = defined('TUTOR_VERSION') ? TUTOR_VERSION : 'Unknown';
            
            // Test database tables
            $tables = array('tutor_enrollments', 'tutor_quiz_attempts', 'tutor_lesson_activities');
            $table_data = array();
            
            foreach ($tables as $table) {
                $full_table = $wpdb->prefix . $table;
                $exists = TutorAdvancedTracking_TutorIntegration::table_exists($full_table);
                $count = 0;
                
                if ($exists) {
                    $count = $wpdb->get_var("SELECT COUNT(*) FROM $full_table");
                }
                
                $table_data[$table] = array(
                    'exists' => $exists,
                    'count' => (int)$count
                );
            }
            $diagnostic_data['database_tables'] = $table_data;
            
            // Test direct enrollment query
            $enrollment_table = $wpdb->prefix . 'tutor_enrollments';
            if (TutorAdvancedTracking_TutorIntegration::table_exists($enrollment_table)) {
                $total_enrollments = $wpdb->get_var("SELECT COUNT(*) FROM $enrollment_table");
                $diagnostic_data['total_enrollments_direct'] = (int)$total_enrollments;
                
                // Get sample enrollment
                $sample_enrollment = $wpdb->get_row("SELECT * FROM $enrollment_table LIMIT 1", ARRAY_A);
                $diagnostic_data['sample_enrollment'] = $sample_enrollment;
            }
            
            // Test course post types
            $course_post_types = array('courses', 'course', 'tutor_course');
            $course_counts = array();
            
            foreach ($course_post_types as $post_type) {
                $count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'",
                    $post_type
                ));
                $course_counts[$post_type] = (int)$count;
            }
            $diagnostic_data['course_post_types'] = $course_counts;
            
            // Test integration layer
            try {
                $integration_courses = TutorAdvancedTracking_TutorIntegration::get_all_courses();
                $diagnostic_data['integration_courses_count'] = count($integration_courses);
                
                if (!empty($integration_courses)) {
                    $first_course = $integration_courses[0];
                    $course_id = is_object($first_course) ? $first_course->ID : $first_course['id'];
                    
                    $enrollment_stats = TutorAdvancedTracking_TutorIntegration::get_course_enrollment_stats($course_id);
                    $diagnostic_data['sample_enrollment_stats'] = $enrollment_stats;
                }
            } catch (Exception $e) {
                $diagnostic_data['integration_error'] = $e->getMessage();
            }
            
            return array('success' => true, 'data' => $diagnostic_data);
            
        } catch (Exception $e) {
            return array(
                'success' => false, 
                'message' => 'Diagnostic failed: ' . $e->getMessage(),
                'data' => $diagnostic_data
            );
        }
    }
    
    private function test_tutor_integration() {
        $tests = array();
        
        $tests['course_post_type'] = TutorAdvancedTracking_TutorIntegration::get_course_post_type();
        $tests['lesson_post_type'] = TutorAdvancedTracking_TutorIntegration::get_lesson_post_type();
        $tests['quiz_post_type'] = TutorAdvancedTracking_TutorIntegration::get_quiz_post_type();
        
        $all_courses = TutorAdvancedTracking_TutorIntegration::get_all_courses();
        $tests['total_courses'] = count($all_courses);
        
        return array('success' => true, 'data' => $tests);
    }
    
    private function validate_database() {
        global $wpdb;
        
        $tables = array(
            'tutor_enrollments',
            'tutor_quiz_attempts',
            'tutor_lesson_activities'
        );
        
        $results = array();
        foreach ($tables as $table) {
            $full_table = $wpdb->prefix . $table;
            $exists = TutorAdvancedTracking_TutorIntegration::table_exists($full_table);
            $count = 0;
            
            if ($exists) {
                $count = $wpdb->get_var("SELECT COUNT(*) FROM $full_table");
            }
            
            $results[$table] = array(
                'exists' => $exists,
                'count' => $count
            );
        }
        
        return array('success' => true, 'data' => $results);
    }
    
    private function clear_courses_cache() {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_tutor_advanced_courses_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_tutor_advanced_courses_%'");
        
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group('tutor_advanced_tracking');
        }
    }
    
    /**
     * Get plugin options with defaults
     */
    public static function get_options() {
        $defaults = array(
            'enable_plugin' => true,
            'dashboard_access_role' => 'instructor',
            'cache_duration' => 5,
            'debug_mode' => false,
            'performance_monitoring' => false
        );
        
        return wp_parse_args(get_option('tutor_advanced_stats_options', array()), $defaults);
    }
    
    /**
     * Render engagement analytics page
     */
    public function render_engagement_page() {
        // Enqueue engagement assets
        wp_enqueue_style(
            'tutor-advanced-engagement',
            TUTOR_ADVANCED_TRACKING_PLUGIN_URL . 'assets/css/engagement.css',
            array(),
            TUTOR_ADVANCED_TRACKING_VERSION
        );
        
        wp_enqueue_script(
            'tutor-advanced-engagement',
            TUTOR_ADVANCED_TRACKING_PLUGIN_URL . 'assets/js/engagement.js',
            array('jquery', 'tutor-advanced-charts'),
            TUTOR_ADVANCED_TRACKING_VERSION,
            true
        );
        
        wp_localize_script('tutor-advanced-engagement', 'tutorAdvancedAdmin', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('tutor_advanced_engagement_' . get_current_user_id()),
            'strings' => array(
                'loading' => __('Loading...', 'tutor-lms-advanced-tracking'),
                'no_data' => __('No data available', 'tutor-lms-advanced-tracking'),
                'error' => __('An error occurred', 'tutor-lms-advanced-tracking')
            )
        ));
        
        // Render the page
        include TUTOR_ADVANCED_TRACKING_PLUGIN_DIR . 'templates/admin-engagement.php';
    }
    
    /**
     * Render live activity feed page
     */
    public function render_live_activity_page() {
        // Enqueue live activity assets
        wp_enqueue_style(
            'tutor-advanced-live-activity',
            TUTOR_ADVANCED_TRACKING_PLUGIN_URL . 'assets/css/live-activity.css',
            array(),
            TUTOR_ADVANCED_TRACKING_VERSION
        );
        
        // Note: JavaScript is inline in the template for simplicity
        
        // Render the page
        include TUTOR_ADVANCED_TRACKING_PLUGIN_DIR . 'templates/admin-live-activity.php';
    }
}