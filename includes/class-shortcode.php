<?php
/**
 * Shortcode handler class
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class TutorAdvancedTracking_Shortcode {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'register_shortcode'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    /**
     * Register the shortcode
     */
    public function register_shortcode() {
        add_shortcode('tutor_advanced_stats', array($this, 'render_shortcode'));
    }
    
    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts() {
        if ($this->is_shortcode_page()) {
            wp_enqueue_style(
                'tutor-advanced-tracking-style',
                TUTOR_ADVANCED_TRACKING_PLUGIN_URL . 'assets/css/dashboard.css',
                array(),
                TUTOR_ADVANCED_TRACKING_VERSION
            );
            
            wp_enqueue_script(
                'tutor-advanced-tracking-script',
                TUTOR_ADVANCED_TRACKING_PLUGIN_URL . 'assets/js/dashboard.js',
                array('jquery'),
                TUTOR_ADVANCED_TRACKING_VERSION,
                true
            );
            
            // Localize script for AJAX
            wp_localize_script('tutor-advanced-tracking-script', 'tutorAdvancedTracking', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('tutor_advanced_tracking_search_' . get_current_user_id())
            ));
        }
    }
    
    /**
     * Check if current page contains our shortcode
     */
    private function is_shortcode_page() {
        global $post;
        return is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'tutor_advanced_stats');
    }
    
    /**
     * Render the shortcode
     */
    public function render_shortcode($atts) {
        // Check if user is logged in
        if (!is_user_logged_in()) {
            return '<div class="tutor-advanced-tracking-error">' . 
                   __('You must be logged in to view this content.', 'tutor-lms-advanced-tracking') . 
                   '</div>';
        }
        
        // Check user capabilities
        if (!$this->user_can_view_stats()) {
            return '<div class="tutor-advanced-tracking-error">' . 
                   __('You do not have permission to view this content.', 'tutor-lms-advanced-tracking') . 
                   '</div>';
        }
        
        // Parse attributes
        $atts = shortcode_atts(array(
            'view' => 'dashboard',
            'course_id' => 0,
            'user_id' => 0
        ), $atts);
        
        // Start output buffering
        ob_start();
        
        // Render based on view type
        switch ($atts['view']) {
            case 'course':
                $this->render_course_view($atts['course_id']);
                break;
            case 'user':
                $this->render_user_view($atts['user_id']);
                break;
            default:
                $this->render_dashboard_view();
                break;
        }
        
        return ob_get_clean();
    }
    
    /**
     * Check if current user can view stats
     */
    private function user_can_view_stats() {
        return current_user_can('manage_options') || current_user_can('tutor_instructor');
    }
    
    /**
     * Render dashboard view
     */
    private function render_dashboard_view() {
        $dashboard = new TutorAdvancedTracking_Dashboard();
        include TUTOR_ADVANCED_TRACKING_PLUGIN_DIR . 'templates/dashboard.php';
    }
    
    /**
     * Render course view
     */
    private function render_course_view($course_id) {
        $course_stats = new TutorAdvancedTracking_CourseStats();
        $course_data = $course_stats->get_course_details($course_id);
        
        if (!$course_data) {
            echo '<div class="tutor-advanced-tracking-error">' . 
                 __('Course not found or you do not have permission to view it.', 'tutor-lms-advanced-tracking') . 
                 '</div>';
            return;
        }
        
        include TUTOR_ADVANCED_TRACKING_PLUGIN_DIR . 'templates/course-details.php';
    }
    
    /**
     * Render user view
     */
    private function render_user_view($user_id) {
        $user_stats = new TutorAdvancedTracking_UserStats();
        $user_data = $user_stats->get_user_details($user_id);
        
        if (!$user_data) {
            echo '<div class="tutor-advanced-tracking-error">' . 
                 __('User not found or you do not have permission to view their data.', 'tutor-lms-advanced-tracking') . 
                 '</div>';
            return;
        }
        
        include TUTOR_ADVANCED_TRACKING_PLUGIN_DIR . 'templates/user-details.php';
    }
}