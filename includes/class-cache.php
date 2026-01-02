<?php
/**
 * WordPress-compliant caching layer for Advanced Tutor LMS Stats Dashboard
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class TutorAdvancedTracking_Cache {
    
    /**
     * Cache group for object caching
     */
    const CACHE_GROUP = 'tutor_advanced_tracking';
    
    /**
     * Default cache expiry (5 minutes)
     */
    const DEFAULT_EXPIRY = 300;
    
    /**
     * Long cache expiry (1 hour) for less frequently changing data
     */
    const LONG_EXPIRY = 3600;
    
    /**
     * Short cache expiry (1 minute) for frequently changing data
     */
    const SHORT_EXPIRY = 60;
    
    /**
     * Get cached data
     */
    public static function get($key, $group = self::CACHE_GROUP) {
        return wp_cache_get($key, $group);
    }
    
    /**
     * Set cached data
     */
    public static function set($key, $data, $group = self::CACHE_GROUP, $expiry = self::DEFAULT_EXPIRY) {
        return wp_cache_set($key, $data, $group, $expiry);
    }
    
    /**
     * Delete cached data
     */
    public static function delete($key, $group = self::CACHE_GROUP) {
        return wp_cache_delete($key, $group);
    }
    
    /**
     * Get course statistics with caching
     */
    public static function get_course_stats($course_id) {
        $cache_key = 'course_stats_' . $course_id;
        $cached = self::get($cache_key);
        
        if (false !== $cached) {
            return $cached;
        }
        
        // Generate stats using Tutor LMS integration
        $stats = self::generate_course_stats($course_id);
        
        // Cache for 5 minutes
        self::set($cache_key, $stats, self::CACHE_GROUP, self::DEFAULT_EXPIRY);
        
        return $stats;
    }
    
    /**
     * Get user course progress with caching
     */
    public static function get_user_course_progress($user_id, $course_id) {
        $cache_key = 'user_progress_' . $user_id . '_' . $course_id;
        $cached = self::get($cache_key);
        
        if (false !== $cached) {
            return $cached;
        }
        
        $progress = TutorAdvancedTracking_TutorIntegration::get_user_course_progress($user_id, $course_id);
        
        // Cache for 1 minute (progress can change quickly)
        self::set($cache_key, $progress, self::CACHE_GROUP, self::SHORT_EXPIRY);
        
        return $progress;
    }
    
    /**
     * Get course list with caching
     */
    public static function get_courses_list($user_id = 0, $is_admin = false) {
        $cache_key = 'courses_list_' . $user_id . '_' . ($is_admin ? 'admin' : 'instructor');
        $cached = self::get($cache_key);
        
        if (false !== $cached) {
            return $cached;
        }
        
        // Generate course list
        if ($is_admin) {
            $courses = TutorAdvancedTracking_TutorIntegration::get_all_courses();
        } else {
            $courses = TutorAdvancedTracking_TutorIntegration::get_user_courses($user_id);
        }
        
        // Enhance with statistics
        $enhanced_courses = array();
        foreach ($courses as $course) {
            $enhanced_courses[] = self::enhance_course_data($course);
        }
        
        // Cache for 5 minutes
        self::set($cache_key, $enhanced_courses, self::CACHE_GROUP, self::DEFAULT_EXPIRY);
        
        return $enhanced_courses;
    }
    
    /**
     * Clear all plugin caches
     */
    public static function flush_all() {
        // Since WordPress object cache doesn't have group flush,
        // we'll use transients as fallback for cache invalidation
        delete_transient('tutor_advanced_tracking_cache_version');
        set_transient('tutor_advanced_tracking_cache_version', time(), DAY_IN_SECONDS);
        
        // Also flush any transients we might have
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_tutor_advanced_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_tutor_advanced_%'");
    }
    
    /**
     * Clear course-specific caches
     */
    public static function clear_course_cache($course_id) {
        $keys_to_clear = array(
            'course_stats_' . $course_id,
            'course_students_' . $course_id,
            'course_lessons_' . $course_id,
            'course_quizzes_' . $course_id
        );
        
        foreach ($keys_to_clear as $key) {
            self::delete($key);
        }
        
        // Clear course lists (they include this course)
        self::clear_course_lists();
    }
    
    /**
     * Clear user-specific caches
     */
    public static function clear_user_cache($user_id) {
        // Clear user progress for all courses
        $courses = TutorAdvancedTracking_TutorIntegration::get_all_courses();
        foreach ($courses as $course) {
            self::delete('user_progress_' . $user_id . '_' . $course->ID);
        }
        
        // Clear instructor course lists
        self::delete('courses_list_' . $user_id . '_instructor');
    }
    
    /**
     * Clear all course lists
     */
    public static function clear_course_lists() {
        // Since we can't easily iterate all users, we'll use a cache version approach
        $current_version = get_transient('tutor_advanced_courses_version');
        set_transient('tutor_advanced_courses_version', time(), HOUR_IN_SECONDS);
    }
    
    /**
     * Get cache version for course lists
     */
    private static function get_courses_cache_version() {
        $version = get_transient('tutor_advanced_courses_version');
        if (false === $version) {
            $version = time();
            set_transient('tutor_advanced_courses_version', $version, HOUR_IN_SECONDS);
        }
        return $version;
    }
    
    /**
     * Generate course statistics using WordPress/Tutor LMS APIs
     */
    private static function generate_course_stats($course_id) {
        // Get basic course info
        $course = get_post($course_id);
        if (!$course) {
            return false;
        }
        
        // Get enrollment stats using integration layer with fallback
        try {
            $enrollment_stats = TutorAdvancedTracking_TutorIntegration::get_course_enrollment_stats($course_id);
        } catch (Exception $e) {
            error_log('Course stats error for course ' . $course_id . ': ' . $e->getMessage());
            $enrollment_stats = array(
                'total_students' => 0,
                'completed_students' => 0,
                'active_students' => 0
            );
        }
        
        // Ensure enrollment_stats is an array with required keys
        if (!is_array($enrollment_stats)) {
            $enrollment_stats = array(
                'total_students' => 0,
                'completed_students' => 0,
                'active_students' => 0
            );
        }
        
        // Get instructor info
        $instructor = get_userdata($course->post_author);
        
        // Calculate average quiz score with error handling
        try {
            $avg_quiz_score = self::calculate_average_quiz_score($course_id);
        } catch (Exception $e) {
            error_log('Quiz score calculation error for course ' . $course_id . ': ' . $e->getMessage());
            $avg_quiz_score = 0;
        }
        
        // Calculate average progression with error handling
        try {
            $avg_progression = self::calculate_average_progression($course_id);
        } catch (Exception $e) {
            error_log('Progression calculation error for course ' . $course_id . ': ' . $e->getMessage());
            $avg_progression = 0;
        }
        
        return array(
            'id' => $course_id,
            'title' => $course->post_title,
            'instructor' => $instructor ? $instructor->display_name : 'Unknown',
            'student_count' => isset($enrollment_stats['total_students']) ? (int)$enrollment_stats['total_students'] : 0,
            'completed_students' => isset($enrollment_stats['completed_students']) ? (int)$enrollment_stats['completed_students'] : 0,
            'completion_rate' => $enrollment_stats['total_students'] > 0 ? 
                round(($enrollment_stats['completed_students'] / $enrollment_stats['total_students']) * 100, 1) : 0,
            'avg_progression' => (float)$avg_progression,
            'avg_quiz_score' => (float)$avg_quiz_score,
            'status' => $course->post_status,
            'generated_at' => current_time('timestamp')
        );
    }
    
    /**
     * Enhance course data with statistics
     */
    private static function enhance_course_data($course) {
        if (is_object($course)) {
            $course_id = $course->ID;
        } else {
            $course_id = $course['id'];
        }
        
        return self::get_course_stats($course_id);
    }
    
    /**
     * Calculate average quiz score for a course
     */
    private static function calculate_average_quiz_score($course_id) {
        global $wpdb;
        
        $quiz_attempts_table = $wpdb->prefix . 'tutor_quiz_attempts';
        if (!TutorAdvancedTracking_TutorIntegration::table_exists($quiz_attempts_table)) {
            return 0;
        }
        
        $quiz_post_type = TutorAdvancedTracking_TutorIntegration::get_quiz_post_type();
        
        $avg_score = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(qa.earned_marks / qa.total_marks * 100) 
             FROM {$quiz_attempts_table} qa
             JOIN {$wpdb->posts} p ON qa.quiz_id = p.ID
             WHERE p.post_parent = %d 
             AND p.post_type = %s
             AND qa.attempt_status = 'attempt_ended'
             AND qa.total_marks > 0",
            $course_id, $quiz_post_type
        ));
        
        return $avg_score ? round($avg_score, 1) : 0;
    }
    
    /**
     * Calculate average progression for a course
     */
    private static function calculate_average_progression($course_id) {
        $students = TutorAdvancedTracking_TutorIntegration::get_course_students($course_id);
        if (empty($students)) {
            return 0;
        }
        
        $total_progress = 0;
        $student_count = count($students);
        $valid_progress_count = 0;
        
        foreach ($students as $student) {
            $user_id = is_object($student) ? $student->ID : (isset($student['ID']) ? $student['ID'] : $student);
            
            try {
                $progress = TutorAdvancedTracking_TutorIntegration::get_user_course_progress($user_id, $course_id);
                if ($progress !== false && $progress >= 0) {
                    $total_progress += $progress;
                    $valid_progress_count++;
                }
            } catch (Exception $e) {
                // Skip this student if there's an error
                continue;
            }
        }
        
        return $valid_progress_count > 0 ? round($total_progress / $valid_progress_count, 1) : 0;
    }
    
    /**
     * Hook into WordPress and Tutor LMS events to clear caches
     */
    public static function init_cache_hooks() {
        // Clear course cache when course is updated
        add_action('save_post', array(__CLASS__, 'on_course_updated'), 10, 1);
        
        // Clear cache when user enrolls in course
        add_action('tutor_after_enrolled', array(__CLASS__, 'on_user_enrolled'), 10, 2);
        
        // Clear cache when user completes lesson
        add_action('tutor_lesson_completed_after', array(__CLASS__, 'on_lesson_completed'), 10, 2);
        
        // Clear cache when quiz is completed
        add_action('tutor_quiz_finished', array(__CLASS__, 'on_quiz_completed'), 10, 1);
        
        // Clear all caches when plugin is activated/deactivated
        add_action('tutor_advanced_tracking_activated', array(__CLASS__, 'flush_all'));
        add_action('tutor_advanced_tracking_deactivated', array(__CLASS__, 'flush_all'));
    }
    
    /**
     * Handle course update
     */
    public static function on_course_updated($post_id) {
        $post_type = get_post_type($post_id);
        if ($post_type === TutorAdvancedTracking_TutorIntegration::get_course_post_type()) {
            self::clear_course_cache($post_id);
        }
    }
    
    /**
     * Handle user enrollment
     */
    public static function on_user_enrolled($course_id, $user_id) {
        self::clear_course_cache($course_id);
        self::clear_user_cache($user_id);
    }
    
    /**
     * Handle lesson completion
     */
    public static function on_lesson_completed($lesson_id, $user_id) {
        $course_id = wp_get_post_parent_id($lesson_id);
        if ($course_id) {
            self::clear_course_cache($course_id);
            self::clear_user_cache($user_id);
        }
    }
    
    /**
     * Handle quiz completion
     */
    public static function on_quiz_completed($quiz_attempt_id) {
        global $wpdb;
        
        $quiz_attempts_table = $wpdb->prefix . 'tutor_quiz_attempts';
        if (!TutorAdvancedTracking_TutorIntegration::table_exists($quiz_attempts_table)) {
            return;
        }
        
        $attempt = $wpdb->get_row($wpdb->prepare(
            "SELECT quiz_id, user_id FROM {$quiz_attempts_table} WHERE attempt_id = %d",
            $quiz_attempt_id
        ));
        
        if ($attempt) {
            $course_id = wp_get_post_parent_id($attempt->quiz_id);
            if ($course_id) {
                self::clear_course_cache($course_id);
                self::clear_user_cache($attempt->user_id);
            }
        }
    }
}