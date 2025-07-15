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
     * Get courses for dashboard
     */
    public function get_courses() {
        global $wpdb;
        
        $current_user_id = get_current_user_id();
        $is_admin = current_user_can('manage_options');
        
        // Try to get from cache first
        $cache_key = 'tutor_advanced_courses_' . $current_user_id . '_' . ($is_admin ? 'admin' : 'instructor');
        $cached_courses = get_transient($cache_key);
        
        if ($cached_courses !== false) {
            return $cached_courses;
        }
        
        // Base query
        $sql = "SELECT p.ID, p.post_title, p.post_status, p.post_author
                FROM {$wpdb->posts} p
                WHERE p.post_type = 'courses'
                AND p.post_status = 'publish'";
        
        // Restrict to instructor's courses if not admin
        if (!$is_admin) {
            $sql .= " AND p.post_author = %d";
            $courses = $wpdb->get_results($wpdb->prepare($sql, $current_user_id));
        } else {
            $courses = $wpdb->get_results($sql);
        }
        
        // Enhance course data with statistics
        $enhanced_courses = array();
        foreach ($courses as $course) {
            $enhanced_courses[] = $this->enhance_course_data($course);
        }
        
        // Cache for 5 minutes
        set_transient($cache_key, $enhanced_courses, 300);
        
        return $enhanced_courses;
    }
    
    /**
     * Enhance course data with statistics
     */
    private function enhance_course_data($course) {
        global $wpdb;
        
        $course_id = $course->ID;
        
        // Try to get from cache first
        $cache_key = 'tutor_course_stats_' . $course_id;
        $cached_stats = get_transient($cache_key);
        
        if ($cached_stats !== false) {
            $cached_stats['id'] = $course_id;
            $cached_stats['title'] = $course->post_title;
            $cached_stats['status'] = $course->post_status;
            return $cached_stats;
        }
        
        // Get student count, average progression, and quiz score in one optimized query
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(DISTINCT e.user_id) as student_count,
                AVG(qa.earned_marks / qa.total_marks * 100) as avg_quiz_score
             FROM {$wpdb->prefix}tutor_enrollments e
             LEFT JOIN {$wpdb->prefix}tutor_quiz_attempts qa ON e.user_id = qa.user_id
             LEFT JOIN {$wpdb->posts} p ON qa.quiz_id = p.ID AND p.post_parent = e.course_id
             WHERE e.course_id = %d AND e.status = 'completed'
             AND (qa.attempt_status = 'attempt_ended' OR qa.attempt_status IS NULL)
             AND (qa.total_marks > 0 OR qa.total_marks IS NULL)",
            $course_id
        ));
        
        // Get average progression (simplified calculation)
        $avg_progression = $this->get_average_progression($course_id);
        
        // Get instructor name
        $instructor = get_userdata($course->post_author);
        
        $course_data = array(
            'id' => $course_id,
            'title' => $course->post_title,
            'instructor' => $instructor ? $instructor->display_name : 'Unknown',
            'student_count' => (int) ($stats->student_count ?? 0),
            'avg_progression' => $avg_progression,
            'avg_quiz_score' => $stats->avg_quiz_score ? round($stats->avg_quiz_score, 1) : 0,
            'status' => $course->post_status
        );
        
        // Cache for 10 minutes
        set_transient($cache_key, $course_data, 600);
        
        return $course_data;
    }
    
    /**
     * Get average progression for a course
     */
    private function get_average_progression($course_id) {
        global $wpdb;
        
        // Get course lessons
        $lessons = $wpdb->get_results($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} 
             WHERE post_type = 'lesson' 
             AND post_parent = %d 
             AND post_status = 'publish'",
            $course_id
        ));
        
        if (empty($lessons)) {
            return 0;
        }
        
        $total_lessons = count($lessons);
        
        // Get enrolled students
        $students = $wpdb->get_results($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->prefix}tutor_enrollments 
             WHERE course_id = %d AND status = 'completed'",
            $course_id
        ));
        
        if (empty($students)) {
            return 0;
        }
        
        $total_progression = 0;
        $student_count = count($students);
        
        foreach ($students as $student) {
            $lesson_ids = array_map('intval', array_column($lessons, 'ID'));
            if (empty($lesson_ids)) {
                $completed_lessons = 0;
            } else {
                $placeholders = implode(',', array_fill(0, count($lesson_ids), '%d'));
                $sql = "SELECT COUNT(*) FROM {$wpdb->prefix}tutor_quiz_attempts qa
                        JOIN {$wpdb->posts} p ON qa.quiz_id = p.ID
                        WHERE p.post_parent IN ($placeholders)
                        AND qa.user_id = %d
                        AND qa.attempt_status = 'attempt_ended'";
                $params = array_merge($lesson_ids, array(intval($student->user_id)));
                $completed_lessons = $wpdb->get_var($wpdb->prepare($sql, $params));
            }
            
            $progression = $total_lessons > 0 ? ($completed_lessons / $total_lessons) * 100 : 0;
            $total_progression += $progression;
        }
        
        return $student_count > 0 ? round($total_progression / $student_count, 1) : 0;
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
     * Search courses
     */
    private function search_courses($query) {
        global $wpdb;
        
        $current_user_id = get_current_user_id();
        $is_admin = current_user_can('manage_options');
        
        $sql = "SELECT p.ID, p.post_title, p.post_author
                FROM {$wpdb->posts} p
                WHERE p.post_type = 'courses'
                AND p.post_status = 'publish'
                AND p.post_title LIKE %s";
        
        $params = array('%' . $query . '%');
        
        if (!$is_admin) {
            $sql .= " AND p.post_author = %d";
            $params[] = $current_user_id;
        }
        
        $courses = $wpdb->get_results($wpdb->prepare($sql, $params));
        
        $results = array();
        foreach ($courses as $course) {
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
        global $wpdb;
        
        $current_user_id = get_current_user_id();
        $is_admin = current_user_can('manage_options');
        
        $sql = "SELECT DISTINCT u.ID, u.display_name, u.user_email
                FROM {$wpdb->users} u";
        
        if (!$is_admin) {
            // For instructors, only show students enrolled in their courses
            $sql .= " JOIN {$wpdb->prefix}tutor_enrollments e ON u.ID = e.user_id
                      JOIN {$wpdb->posts} p ON e.course_id = p.ID
                      WHERE p.post_author = %d";
            $params = array($current_user_id);
        } else {
            $sql .= " WHERE 1=1";
            $params = array();
        }
        
        $sql .= " AND (u.display_name LIKE %s OR u.user_email LIKE %s)";
        $params[] = '%' . $query . '%';
        $params[] = '%' . $query . '%';
        
        $users = $wpdb->get_results($wpdb->prepare($sql, $params));
        
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