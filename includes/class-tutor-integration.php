<?php
/**
 * Tutor LMS Integration Layer
 * Provides standardized access to Tutor LMS data using proper APIs
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class TutorAdvancedTracking_TutorIntegration {
    
    /**
     * Get course post type using Tutor LMS API
     */
    public static function get_course_post_type() {
        if (function_exists('tutor') && isset(tutor()->course_post_type)) {
            return tutor()->course_post_type;
        }
        
        // Fallback check for different Tutor LMS versions
        if (defined('TUTOR_COURSE_POST_TYPE')) {
            return TUTOR_COURSE_POST_TYPE;
        }
        
        return 'courses'; // Default fallback
    }
    
    /**
     * Get lesson post type using Tutor LMS API
     */
    public static function get_lesson_post_type() {
        if (function_exists('tutor') && isset(tutor()->lesson_post_type)) {
            return tutor()->lesson_post_type;
        }
        
        if (defined('TUTOR_LESSON_POST_TYPE')) {
            return TUTOR_LESSON_POST_TYPE;
        }
        
        return 'tutor_lesson'; // Default fallback
    }
    
    /**
     * Get quiz post type using Tutor LMS API
     */
    public static function get_quiz_post_type() {
        if (function_exists('tutor') && isset(tutor()->quiz_post_type)) {
            return tutor()->quiz_post_type;
        }
        
        if (defined('TUTOR_QUIZ_POST_TYPE')) {
            return TUTOR_QUIZ_POST_TYPE;
        }
        
        return 'tutor_quiz'; // Default fallback
    }
    
    /**
     * Get enrolled students for a course using Tutor LMS API
     */
    public static function get_course_students($course_id) {
        // Try Tutor LMS helper function first
        if (function_exists('tutor_utils')) {
            $students = tutor_utils()->get_enrolled_users($course_id);
            if (!empty($students)) {
                return $students;
            }
        }
        
        // Try alternative Tutor LMS function
        if (function_exists('tutils')) {
            $students = tutils()->get_enrolled_users($course_id);
            if (!empty($students)) {
                return $students;
            }
        }
        
        // Fallback to WordPress user query with enrollment meta
        return self::get_course_students_fallback($course_id);
    }
    
    /**
     * Get user's course progress using Tutor LMS API
     */
    public static function get_user_course_progress($user_id, $course_id) {
        // Try Tutor LMS helper function
        if (function_exists('tutor_utils')) {
            $progress = tutor_utils()->get_course_completed_percent($course_id, $user_id);
            if ($progress !== false) {
                return round($progress, 1);
            }
        }
        
        // Try alternative function
        if (function_exists('tutils')) {
            $progress = tutils()->get_course_completed_percent($course_id, $user_id);
            if ($progress !== false) {
                return round($progress, 1);
            }
        }
        
        // Fallback calculation
        return self::calculate_progress_fallback($user_id, $course_id);
    }
    
    /**
     * Get course lessons using Tutor LMS API
     */
    public static function get_course_lessons($course_id) {
        // Try Tutor LMS content function
        if (function_exists('tutor_utils')) {
            $lessons = tutor_utils()->get_course_contents_by_type($course_id, self::get_lesson_post_type());
            if (!empty($lessons)) {
                return $lessons;
            }
        }
        
        // Fallback to WordPress post query
        return get_posts(array(
            'post_type' => self::get_lesson_post_type(),
            'post_parent' => $course_id,
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'menu_order',
            'order' => 'ASC'
        ));
    }
    
    /**
     * Get course quizzes using Tutor LMS API
     */
    public static function get_course_quizzes($course_id) {
        // Try Tutor LMS content function
        if (function_exists('tutor_utils')) {
            $quizzes = tutor_utils()->get_course_contents_by_type($course_id, self::get_quiz_post_type());
            if (!empty($quizzes)) {
                return $quizzes;
            }
        }
        
        // Fallback to WordPress post query
        return get_posts(array(
            'post_type' => self::get_quiz_post_type(),
            'post_parent' => $course_id,
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'menu_order',
            'order' => 'ASC'
        ));
    }
    
    /**
     * Check if user is enrolled in course
     */
    public static function is_user_enrolled($user_id, $course_id) {
        // Try Tutor LMS function
        if (function_exists('tutor_utils')) {
            return tutor_utils()->is_enrolled($course_id, $user_id);
        }
        
        // Fallback check enrollment meta
        $enrolled_courses = get_user_meta($user_id, '_tutor_enrolled_course_ids', true);
        return is_array($enrolled_courses) && in_array($course_id, $enrolled_courses);
    }
    
    /**
     * Get user's quiz attempts for a course
     */
    public static function get_user_quiz_attempts($user_id, $course_id) {
        global $wpdb;
        
        // This requires database query as Tutor LMS doesn't have a direct API for this
        // But we'll make it more robust with table existence checks
        $table_name = $wpdb->prefix . 'tutor_quiz_attempts';
        
        if (!self::table_exists($table_name)) {
            return array();
        }
        
        $quiz_post_type = self::get_quiz_post_type();
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT qa.*, p.post_title as quiz_title
             FROM {$table_name} qa
             JOIN {$wpdb->posts} p ON qa.quiz_id = p.ID
             WHERE qa.user_id = %d 
             AND p.post_parent = %d
             AND p.post_type = %s
             AND qa.attempt_status = 'attempt_ended'
             ORDER BY qa.attempt_started_at DESC",
            $user_id, $course_id, $quiz_post_type
        ));
    }
    
    /**
     * Get course enrollment statistics
     */
    public static function get_course_enrollment_stats($course_id) {
        // Try Tutor LMS function first
        if (function_exists('tutor_utils')) {
            $total_students = tutor_utils()->count_enrolled_users($course_id);
            if ($total_students !== false) {
                return array(
                    'total_students' => $total_students,
                    'completed_students' => self::count_completed_students($course_id)
                );
            }
        }
        
        // Fallback to database query with safety checks
        return self::get_enrollment_stats_fallback($course_id);
    }
    
    /**
     * Fallback method to get course students using WordPress API
     */
    private static function get_course_students_fallback($course_id) {
        global $wpdb;
        
        // Check if enrollment table exists
        $table_name = $wpdb->prefix . 'tutor_enrollments';
        if (!self::table_exists($table_name)) {
            return array();
        }
        
        // Get enrolled user IDs first
        $user_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT user_id FROM {$table_name} WHERE course_id = %d",
            $course_id
        ));
        
        if (empty($user_ids)) {
            return array();
        }
        
        // Use WordPress user query
        $user_query = new WP_User_Query(array(
            'include' => $user_ids,
            'fields' => array('ID', 'display_name', 'user_email')
        ));
        
        return $user_query->get_results();
    }
    
    /**
     * Fallback method to calculate user progress
     */
    private static function calculate_progress_fallback($user_id, $course_id) {
        $lessons = self::get_course_lessons($course_id);
        if (empty($lessons)) {
            return 0;
        }
        
        $total_lessons = count($lessons);
        $completed_lessons = 0;
        
        foreach ($lessons as $lesson) {
            $is_completed = get_user_meta($user_id, '_tutor_lesson_completed_' . $lesson->ID, true);
            if ($is_completed) {
                $completed_lessons++;
            }
        }
        
        return $total_lessons > 0 ? round(($completed_lessons / $total_lessons) * 100, 1) : 0;
    }
    
    /**
     * Count completed students for a course
     */
    private static function count_completed_students($course_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'tutor_enrollments';
        if (!self::table_exists($table_name)) {
            return 0;
        }
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} 
             WHERE course_id = %d 
             AND (is_completed = 1 OR completion_date IS NOT NULL)",
            $course_id
        ));
    }
    
    /**
     * Fallback enrollment stats
     */
    private static function get_enrollment_stats_fallback($course_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'tutor_enrollments';
        if (!self::table_exists($table_name)) {
            return array('total_students' => 0, 'completed_students' => 0);
        }
        
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_students,
                SUM(CASE WHEN is_completed = 1 OR completion_date IS NOT NULL THEN 1 ELSE 0 END) as completed_students
             FROM {$table_name}
             WHERE course_id = %d",
            $course_id
        ));
        
        return array(
            'total_students' => (int) ($stats->total_students ?? 0),
            'completed_students' => (int) ($stats->completed_students ?? 0)
        );
    }
    
    /**
     * Check if database table exists
     */
    public static function table_exists($table_name) {
        global $wpdb;
        $result = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));
        return $result === $table_name;
    }
    
    /**
     * Get all courses using WordPress API
     */
    public static function get_all_courses($args = array()) {
        $defaults = array(
            'post_type' => self::get_course_post_type(),
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        return get_posts($args);
    }
    
    /**
     * Get courses for current user (instructor filter)
     */
    public static function get_user_courses($user_id) {
        return self::get_all_courses(array(
            'author' => $user_id
        ));
    }
}