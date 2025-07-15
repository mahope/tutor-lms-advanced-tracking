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
        add_action('wp_ajax_nopriv_tutor_advanced_search', array($this, 'handle_search'));
    }
    
    /**
     * Get courses for dashboard
     */
    public function get_courses() {
        global $wpdb;
        
        $current_user_id = get_current_user_id();
        $is_admin = current_user_can('manage_options');
        
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
        
        return $enhanced_courses;
    }
    
    /**
     * Enhance course data with statistics
     */
    private function enhance_course_data($course) {
        global $wpdb;
        
        $course_id = $course->ID;
        
        // Get student count
        $student_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}tutor_enrollments 
             WHERE course_id = %d AND status = 'completed'",
            $course_id
        ));
        
        // Get average progression
        $avg_progression = $this->get_average_progression($course_id);
        
        // Get average quiz score
        $avg_quiz_score = $this->get_average_quiz_score($course_id);
        
        // Get instructor name
        $instructor = get_userdata($course->post_author);
        
        return array(
            'id' => $course_id,
            'title' => $course->post_title,
            'instructor' => $instructor ? $instructor->display_name : 'Unknown',
            'student_count' => (int) $student_count,
            'avg_progression' => $avg_progression,
            'avg_quiz_score' => $avg_quiz_score,
            'status' => $course->post_status
        );
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
            $completed_lessons = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}tutor_quiz_attempts qa
                 JOIN {$wpdb->posts} p ON qa.quiz_id = p.ID
                 WHERE p.post_parent IN (" . implode(',', array_fill(0, count($lessons), '%d')) . ")
                 AND qa.user_id = %d
                 AND qa.attempt_status = 'attempt_ended'",
                array_merge(array_column($lessons, 'ID'), array($student->user_id))
            ));
            
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
                'email' => $user->user_email,
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
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'tutor_advanced_tracking_nonce')) {
            wp_die('Security check failed');
        }
        
        // Check permissions
        if (!is_user_logged_in() || !current_user_can('tutor_instructor')) {
            wp_die('Insufficient permissions');
        }
        
        $query = sanitize_text_field($_POST['query']);
        $type = sanitize_text_field($_POST['type']);
        
        $results = $this->search($query, $type);
        
        wp_send_json_success($results);
    }
}