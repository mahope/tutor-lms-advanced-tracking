<?php
/**
 * Dashboard class
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class TutorAdvancedTracking_Dashboard {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_ajax_tutor_advanced_search', array($this, 'handle_search'));
        // Removed nopriv action for security - only logged-in users can search
        
        // Cache invalidation hooks
        add_action('tutor_after_enrolled', array($this, 'clear_course_cache'), 10, 2);
        add_action('tutor_quiz_finished', array($this, 'clear_course_cache_by_quiz'), 10, 1);
        add_action('save_post', array($this, 'clear_course_cache_on_save'), 10, 1);
    }
    
    /**
     * Get the correct course post type using Tutor LMS integration
     */
    private function get_course_post_type() {
        return TutorAdvancedTracking_TutorIntegration::get_course_post_type();
    }
    
    /**
     * Get the correct lesson post type using Tutor LMS integration
     */
    private function get_lesson_post_type() {
        return TutorAdvancedTracking_TutorIntegration::get_lesson_post_type();
    }
    
    /**
     * Get courses for dashboard using WordPress and Tutor LMS standards
     */
    public function get_courses() {
        $current_user_id = get_current_user_id();
        $is_admin = current_user_can('manage_options');
        
        // Use the new caching system
        return TutorAdvancedTracking_Cache::get_courses_list($current_user_id, $is_admin);
    }
    
    /**
     * Enhance course data with statistics (now handled by cache layer)
     * This method is kept for backward compatibility
     */
    private function enhance_course_data($course) {
        $course_id = is_object($course) ? $course->ID : $course['id'];
        return TutorAdvancedTracking_Cache::get_course_stats($course_id);
    }
    
    /**
     * Get average progression for a course (now handled by integration layer)
     * This method is kept for backward compatibility
     */
    private function get_average_progression($course_id) {
        $students = TutorAdvancedTracking_TutorIntegration::get_course_students($course_id);
        if (empty($students)) {
            return 0;
        }
        
        $total_progress = 0;
        $student_count = count($students);
        
        foreach ($students as $student) {
            $user_id = is_object($student) ? $student->ID : $student['ID'];
            $progress = TutorAdvancedTracking_TutorIntegration::get_user_course_progress($user_id, $course_id);
            $total_progress += $progress;
        }
        
        return $student_count > 0 ? round($total_progress / $student_count, 1) : 0;
    }
    
    /**
     * Get average quiz score for a course
     */
    private function get_average_quiz_score($course_id) {
        global $wpdb;
        
        $avg_score = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(qa.earned_marks / qa.total_marks * 100) 
             FROM {$wpdb->prefix}tutor_quiz_attempts qa
             JOIN {$wpdb->posts} p ON qa.quiz_id = p.ID
             WHERE p.post_parent = %d 
             AND qa.attempt_status = 'attempt_ended'
             AND qa.total_marks > 0",
            $course_id
        ));
        
        return $avg_score ? round($avg_score, 1) : 0;
    }
    
    /**
     * Search courses and users
     */
    public function search($query, $type = 'all') {
        global $wpdb;
        
        $results = array();
        
        if ($type === 'all' || $type === 'courses') {
            $results['courses'] = $this->search_courses($query);
        }
        
        if ($type === 'all' || $type === 'users') {
            $results['users'] = $this->search_users($query);
        }
        
        return $results;
    }
    
    /**
     * Search courses using WordPress post query
     */
    private function search_courses($query) {
        $current_user_id = get_current_user_id();
        $is_admin = current_user_can('manage_options');
        
        $search_args = array(
            'post_type' => TutorAdvancedTracking_TutorIntegration::get_course_post_type(),
            'post_status' => 'publish',
            's' => $query,
            'posts_per_page' => 20,
            'fields' => 'ids'
        );
        
        // Restrict to instructor's courses if not admin
        if (!$is_admin) {
            $search_args['author'] = $current_user_id;
        }
        
        $course_query = new WP_Query($search_args);
        $course_ids = $course_query->posts;
        
        $results = array();
        foreach ($course_ids as $course_id) {
            $course = get_post($course_id);
            $results[] = array(
                'id' => $course->ID,
                'title' => $course->post_title,
                'type' => 'course',
                'url' => add_query_arg(array('view' => 'course', 'course_id' => $course->ID), get_permalink())
            );
        }
        
        return $results;
    }
    
    /**
     * Search users
     */
    private function search_users($query) {
        $current_user_id = get_current_user_id();
        $is_admin = current_user_can('manage_options');
        
        $search_args = array(
            'search' => '*' . $query . '*',
            'search_columns' => array('display_name', 'user_email'),
            'fields' => array('ID', 'display_name', 'user_email'),
            'number' => 20
        );
        
        // For instructors, only show students enrolled in their courses
        if (!$is_admin) {
            $instructor_courses = TutorAdvancedTracking_TutorIntegration::get_user_courses($current_user_id);
            $enrolled_user_ids = array();
            
            foreach ($instructor_courses as $course) {
                $course_students = TutorAdvancedTracking_TutorIntegration::get_course_students($course->ID);
                foreach ($course_students as $student) {
                    $user_id = is_object($student) ? $student->ID : $student['ID'];
                    $enrolled_user_ids[] = $user_id;
                }
            }
            
            if (!empty($enrolled_user_ids)) {
                $search_args['include'] = array_unique($enrolled_user_ids);
            } else {
                return array(); // No students to show
            }
        }
        
        $user_query = new WP_User_Query($search_args);
        $users = $user_query->get_results();
        
        $results = array();
        foreach ($users as $user) {
            $results[] = array(
                'id' => $user->ID,
                'name' => $user->display_name,
                'email' => $this->mask_email($user->user_email),
                'type' => 'user',
                'url' => add_query_arg(array('view' => 'user', 'user_id' => $user->ID), get_permalink())
            );
        }
        
        return $results;
    }
    
    /**
     * Handle AJAX search
     */
    public function handle_search() {
        // Verify nonce with specific name
        if (!wp_verify_nonce($_POST['nonce'], 'tutor_advanced_tracking_search_' . get_current_user_id())) {
            wp_die('Security check failed');
        }
        
        // Check permissions
        if (!is_user_logged_in() || !$this->user_can_search()) {
            wp_die('Insufficient permissions');
        }
        
        // Enhanced input validation
        $query = sanitize_text_field($_POST['query']);
        $type = sanitize_text_field($_POST['type']);
        
        // Validate query length
        if (strlen($query) < 2 || strlen($query) > 100) {
            wp_send_json_error('Invalid query length');
        }
        
        // Validate search type
        $allowed_types = array('all', 'courses', 'users');
        if (!in_array($type, $allowed_types)) {
            wp_send_json_error('Invalid search type');
        }
        
        // Rate limiting check
        if (!$this->check_rate_limit()) {
            wp_send_json_error('Too many requests. Please wait before searching again.');
        }
        
        $results = $this->search($query, $type);
        
        wp_send_json_success($results);
    }
    
    /**
     * Check if user can perform search
     */
    private function user_can_search() {
        return current_user_can('manage_options') || 
               current_user_can('tutor_instructor') || 
               current_user_can('read_private_posts');
    }
    
    /**
     * Simple rate limiting for search
     */
    private function check_rate_limit() {
        $user_id = get_current_user_id();
        $transient_key = 'tutor_search_rate_limit_' . $user_id;
        $current_requests = get_transient($transient_key);
        
        if ($current_requests === false) {
            set_transient($transient_key, 1, 60); // 1 minute
            return true;
        }
        
        if ($current_requests >= 10) { // Max 10 requests per minute
            return false;
        }
        
        set_transient($transient_key, $current_requests + 1, 60);
        return true;
    }
    
    /**
     * Mask email addresses for non-admin users
     */
    private function mask_email($email) {
        if (current_user_can('manage_options')) {
            return $email;
        }
        
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return $email; // Return original if invalid email format
        }
        
        $username = $parts[0];
        $domain = $parts[1];
        
        // Show first 2 chars and last char of username
        $username_length = strlen($username);
        if ($username_length <= 3) {
            $masked_username = substr($username, 0, 1) . '***';
        } else {
            $masked_username = substr($username, 0, 2) . '***' . substr($username, -1);
        }
        
        return $masked_username . '@' . $domain;
    }
    
    /**
     * Clear course cache when enrollment changes
     */
    public function clear_course_cache($course_id, $user_id) {
        $this->clear_course_cache_by_id($course_id);
    }
    
    /**
     * Clear course cache when quiz is finished
     */
    public function clear_course_cache_by_quiz($quiz_id) {
        $course_id = wp_get_post_parent_id($quiz_id);
        if ($course_id) {
            $this->clear_course_cache_by_id($course_id);
        }
    }
    
    /**
     * Clear course cache when post is saved
     */
    public function clear_course_cache_on_save($post_id) {
        $post_type = get_post_type($post_id);
        if ($post_type === 'courses') {
            $this->clear_course_cache_by_id($post_id);
        }
    }
    
    /**
     * Clear specific course cache
     */
    private function clear_course_cache_by_id($course_id) {
        delete_transient('tutor_course_stats_' . $course_id);
        
        // Clear all user course lists
        global $wpdb;
        $users = $wpdb->get_col("SELECT DISTINCT post_author FROM {$wpdb->posts} WHERE post_type = 'courses'");
        foreach ($users as $user_id) {
            delete_transient('tutor_advanced_courses_' . $user_id . '_admin');
            delete_transient('tutor_advanced_courses_' . $user_id . '_instructor');
        }
    }
}