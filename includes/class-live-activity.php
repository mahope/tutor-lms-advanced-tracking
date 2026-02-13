<?php
/**
 * Live Student Activity Feed
 * Real-time activity tracking using WordPress transients
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class TutorAdvancedTracking_LiveActivity {
    
    /**
     * Transient key prefix for activities
     */
    const TRANSIENT_PREFIX = 'tlat_live_activities_';
    
    /**
     * Maximum number of activities to store
     */
    const MAX_ACTIVITIES = 100;
    
    /**
     * Transient expiration in seconds (15 minutes)
     */
    const TRANSIENT_EXPIRATION = 900;
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_ajax_tlat_get_live_activities', array($this, 'ajax_get_activities'));
        add_action('wp_ajax_nopriv_tlat_get_live_activities', array($this, 'ajax_get_activities'));
        add_action('wp_ajax_tlat_clear_activities', array($this, 'ajax_clear_activities'));
        
        // Hook into Tutor LMS events to track activities
        add_action('tutor_lesson_viewed', array($this, 'track_lesson_view'), 10, 2);
        add_action('tutor_quiz_finished', array($this, 'track_quiz_complete'), 10, 2);
        add_action('tutor_assignment_submitted', array($this, 'track_assignment_submit'), 10, 2);
        add_action('tutor_course_enrolled_after', array($this, 'track_enrollment'), 10, 2);
        add_action('tutor_course_completed', array($this, 'track_course_complete'), 10, 2);
        add_action('tutor_lesson_completed', array($this, 'track_lesson_complete'), 10, 2);
    }
    
    /**
     * Get the transient key based on a unique identifier
     */
    private function get_transient_key() {
        return self::TRANSIENT_PREFIX . 'feed';
    }
    
    /**
     * Get all activities from transient
     */
    public function get_activities($limit = 50) {
        $activities = get_transient($this->get_transient_key());
        
        if ($activities === false) {
            return array();
        }
        
        // Sort by timestamp descending (newest first)
        usort($activities, function($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });
        
        return array_slice($activities, 0, $limit);
    }
    
    /**
     * Add a new activity to the transient
     */
    public function add_activity($data) {
        $activities = get_transient($this->get_transient_key());
        
        if ($activities === false) {
            $activities = array();
        }
        
        // Create activity entry
        $activity = array(
            'id' => uniqid('activity_', true),
            'student_name' => $this->get_student_name($data['user_id'] ?? 0),
            'student_id' => $data['user_id'] ?? 0,
            'action' => $data['action'] ?? 'unknown',
            'action_label' => $this->get_action_label($data['action'] ?? 'unknown'),
            'action_icon' => $this->get_action_icon($data['action'] ?? 'unknown'),
            'course_id' => $data['course_id'] ?? 0,
            'course_name' => $this->get_course_name($data['course_id'] ?? 0),
            'lesson_id' => $data['lesson_id'] ?? 0,
            'lesson_name' => $this->get_lesson_name($data['lesson_id'] ?? 0),
            'timestamp' => current_time('mysql'),
            'timestamp_formatted' => $this->format_timestamp(current_time('mysql')),
            'meta' => $data['meta'] ?? array()
        );
        
        // Add to beginning of array
        array_unshift($activities, $activity);
        
        // Limit the number of activities
        if (count($activities) > self::MAX_ACTIVITIES) {
            $activities = array_slice($activities, 0, self::MAX_ACTIVITIES);
        }
        
        // Save back to transient
        set_transient($this->get_transient_key(), $activities, self::TRANSIENT_EXPIRATION);
        
        return $activity;
    }
    
    /**
     * Get student name from user ID
     */
    private function get_student_name($user_id) {
        if (empty($user_id)) {
            return __('Unknown Student', 'tutor-lms-advanced-tracking');
        }
        
        $user = get_userdata($user_id);
        if (!$user) {
            return __('Unknown Student', 'tutor-lms-advanced-tracking');
        }
        
        return $user->display_name ?: $user->user_login;
    }
    
    /**
     * Get course name from course ID
     */
    private function get_course_name($course_id) {
        if (empty($course_id)) {
            return __('Unknown Course', 'tutor-lms-advanced-tracking');
        }
        
        $course = get_post($course_id);
        if (!$course) {
            return __('Unknown Course', 'tutor-lms-advanced-tracking');
        }
        
        return $course->post_title;
    }
    
    /**
     * Get lesson name from lesson ID
     */
    private function get_lesson_name($lesson_id) {
        if (empty($lesson_id)) {
            return '';
        }
        
        $lesson = get_post($lesson_id);
        if (!$lesson) {
            return '';
        }
        
        return $lesson->post_title;
    }
    
    /**
     * Get human-readable action label
     */
    private function get_action_label($action) {
        $labels = array(
            'view_lesson' => __('Viewed Lesson', 'tutor-lms-advanced-tracking'),
            'complete_lesson' => __('Completed Lesson', 'tutor-lms-advanced-tracking'),
            'complete_quiz' => __('Completed Quiz', 'tutor-lms-advanced-tracking'),
            'submit_assignment' => __('Submitted Assignment', 'tutor-lms-advanced-tracking'),
            'enroll_course' => __('Enrolled in Course', 'tutor-lms-advanced-tracking'),
            'complete_course' => __('Completed Course', 'tutor-lms-advanced-tracking'),
            'start_quiz' => __('Started Quiz', 'tutor-lms-advanced-tracking'),
            'login' => __('Logged In', 'tutor-lms-advanced-tracking'),
            'watch_video' => __('Watched Video', 'tutor-lms-advanced-tracking'),
        );
        
        return $labels[$action] ?? ucfirst(str_replace('_', ' ', $action));
    }
    
    /**
     * Get icon class for action
     */
    private function get_action_icon($action) {
        $icons = array(
            'view_lesson' => 'dashicons-welcome-learn-more',
            'complete_lesson' => 'dashicons-yes-alt',
            'complete_quiz' => 'dashicons-clipboard',
            'submit_assignment' => 'dashicons-pressthis',
            'enroll_course' => 'dashicons-plus',
            'complete_course' => 'dashicons-awards',
            'start_quiz' => 'dashicons-clock',
            'login' => 'dashicons-admin-user',
            'watch_video' => 'dashicons-video-alt2',
        );
        
        return $icons[$action] ?? 'dashicons-info';
    }
    
    /**
     * Format timestamp for display
     */
    private function format_timestamp($mysql_timestamp) {
        $timestamp = strtotime($mysql_timestamp);
        $now = current_time('timestamp');
        $diff = $now - $timestamp;
        
        if ($diff < 60) {
            return __('Just now', 'tutor-lms-advanced-tracking');
        } elseif ($diff < 3600) {
            $minutes = floor($diff / 60);
            return sprintf(_n('%d minute ago', '%d minutes ago', $minutes, 'tutor-lms-advanced-tracking'), $minutes);
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return sprintf(_n('%d hour ago', '%d hours ago', $hours, 'tutor-lms-advanced-tracking'), $hours);
        } else {
            return date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $timestamp);
        }
    }
    
    /**
     * AJAX handler for getting activities
     */
    public function ajax_get_activities() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'tlat_live_activity_nonce')) {
            wp_send_json_error('Invalid nonce');
        }
        
        // Check permissions
        if (!is_user_logged_in() || !current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 50;
        $activities = $this->get_activities($limit);
        
        wp_send_json_success(array(
            'activities' => $activities,
            'count' => count($activities),
            'timestamp' => current_time('mysql')
        ));
    }
    
    /**
     * AJAX handler for clearing activities
     */
    public function ajax_clear_activities() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'tlat_live_activity_nonce')) {
            wp_send_json_error('Invalid nonce');
        }
        
        // Check permissions
        if (!is_user_logged_in() || !current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $this->clear_activities();
        
        wp_send_json_success(array(
            'message' => 'Activities cleared successfully'
        ));
    }
    
    /**
     * Track lesson views
     */
    public function track_lesson_view($lesson_id, $course_id) {
        $this->add_activity(array(
            'user_id' => get_current_user_id(),
            'action' => 'view_lesson',
            'course_id' => $course_id,
            'lesson_id' => $lesson_id,
            'meta' => array(
                'lesson_title' => get_the_title($lesson_id)
            )
        ));
    }
    
    /**
     * Track lesson completion
     */
    public function track_lesson_complete($lesson_id, $course_id) {
        $this->add_activity(array(
            'user_id' => get_current_user_id(),
            'action' => 'complete_lesson',
            'course_id' => $course_id,
            'lesson_id' => $lesson_id,
            'meta' => array(
                'lesson_title' => get_the_title($lesson_id)
            )
        ));
    }
    
    /**
     * Track quiz completion
     */
    public function track_quiz_complete($quiz_id, $user_id) {
        $course_id = wp_get_post_parent_id($quiz_id);
        
        $this->add_activity(array(
            'user_id' => $user_id,
            'action' => 'complete_quiz',
            'course_id' => $course_id,
            'lesson_id' => $quiz_id,
            'meta' => array(
                'quiz_title' => get_the_title($quiz_id)
            )
        ));
    }
    
    /**
     * Track assignment submission
     */
    public function track_assignment_submit($assignment_id, $user_id) {
        $course_id = wp_get_post_parent_id($assignment_id);
        
        $this->add_activity(array(
            'user_id' => $user_id,
            'action' => 'submit_assignment',
            'course_id' => $course_id,
            'lesson_id' => $assignment_id,
            'meta' => array(
                'assignment_title' => get_the_title($assignment_id)
            )
        ));
    }
    
    /**
     * Track course enrollment
     */
    public function track_enrollment($course_id, $user_id) {
        $this->add_activity(array(
            'user_id' => $user_id,
            'action' => 'enroll_course',
            'course_id' => $course_id,
            'meta' => array(
                'course_title' => get_the_title($course_id)
            )
        ));
    }
    
    /**
     * Track course completion
     */
    public function track_course_complete($course_id, $user_id) {
        $this->add_activity(array(
            'user_id' => $user_id,
            'action' => 'complete_course',
            'course_id' => $course_id,
            'meta' => array(
                'course_title' => get_the_title($course_id)
            )
        ));
    }
    
    /**
     * Clear all activities (for testing/admin purposes)
     */
    public function clear_activities() {
        delete_transient($this->get_transient_key());
    }
    
    /**
     * Manually add an activity (for testing/debugging)
     */
    public function log_test_activity($user_id, $action, $course_id, $lesson_id = 0) {
        return $this->add_activity(array(
            'user_id' => $user_id,
            'action' => $action,
            'course_id' => $course_id,
            'lesson_id' => $lesson_id
        ));
    }
}
