<?php
/**
 * REST API Endpoints for Advanced Tutor LMS Stats Dashboard
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class TutorAdvancedTracking_API {
    
    /**
     * API namespace
     */
    const NAMESPACE = 'tutor-advanced/v1';
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }
    
    /**
     * Register REST API routes
     */
    public function register_routes() {
        // Dashboard statistics
        register_rest_route(self::NAMESPACE, '/dashboard/stats', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_dashboard_stats'),
            'permission_callback' => array($this, 'check_dashboard_permissions'),
            'args' => array(
                'time_period' => array(
                    'default' => '30',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => array($this, 'validate_time_period')
                )
            )
        ));
        
        // Course list
        register_rest_route(self::NAMESPACE, '/courses', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_courses'),
            'permission_callback' => array($this, 'check_dashboard_permissions'),
            'args' => array(
                'page' => array(
                    'default' => 1,
                    'sanitize_callback' => 'absint',
                    'validate_callback' => array($this, 'validate_positive_integer')
                ),
                'per_page' => array(
                    'default' => 10,
                    'sanitize_callback' => 'absint',
                    'validate_callback' => array($this, 'validate_per_page')
                ),
                'search' => array(
                    'default' => '',
                    'sanitize_callback' => 'sanitize_text_field'
                )
            )
        ));
        
        // Course details
        register_rest_route(self::NAMESPACE, '/courses/(?P<id>\d+)', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_course_details'),
            'permission_callback' => array($this, 'check_course_permissions'),
            'args' => array(
                'id' => array(
                    'validate_callback' => array($this, 'validate_positive_integer')
                )
            )
        ));
        
        // Course students
        register_rest_route(self::NAMESPACE, '/courses/(?P<id>\d+)/students', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_course_students'),
            'permission_callback' => array($this, 'check_course_permissions'),
            'args' => array(
                'id' => array(
                    'validate_callback' => array($this, 'validate_positive_integer')
                ),
                'page' => array(
                    'default' => 1,
                    'sanitize_callback' => 'absint',
                    'validate_callback' => array($this, 'validate_positive_integer')
                ),
                'per_page' => array(
                    'default' => 20,
                    'sanitize_callback' => 'absint',
                    'validate_callback' => array($this, 'validate_per_page')
                )
            )
        ));
        
        // Course analytics
        register_rest_route(self::NAMESPACE, '/courses/(?P<id>\d+)/analytics', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_course_analytics'),
            'permission_callback' => array($this, 'check_course_permissions'),
            'args' => array(
                'id' => array(
                    'validate_callback' => array($this, 'validate_positive_integer')
                ),
                'time_period' => array(
                    'default' => '30',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => array($this, 'validate_time_period')
                )
            )
        ));
        
        // User statistics
        register_rest_route(self::NAMESPACE, '/users/(?P<id>\d+)', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_user_stats'),
            'permission_callback' => array($this, 'check_user_permissions'),
            'args' => array(
                'id' => array(
                    'validate_callback' => array($this, 'validate_positive_integer')
                )
            )
        ));
        
        // Search endpoint
        register_rest_route(self::NAMESPACE, '/search', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'search'),
            'permission_callback' => array($this, 'check_dashboard_permissions'),
            'args' => array(
                'query' => array(
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => array($this, 'validate_search_query')
                ),
                'type' => array(
                    'default' => 'all',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => array($this, 'validate_search_type')
                ),
                'limit' => array(
                    'default' => 10,
                    'sanitize_callback' => 'absint',
                    'validate_callback' => array($this, 'validate_per_page')
                )
            )
        ));
        
        // Export endpoints
        register_rest_route(self::NAMESPACE, '/export/courses', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'export_courses_json'),
            'permission_callback' => array($this, 'check_dashboard_permissions')
        ));
        
        register_rest_route(self::NAMESPACE, '/export/courses/(?P<id>\d+)', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'export_course_json'),
            'permission_callback' => array($this, 'check_course_permissions'),
            'args' => array(
                'id' => array(
                    'validate_callback' => array($this, 'validate_positive_integer')
                )
            )
        ));
        
        // Webhook endpoints for external integrations
        register_rest_route(self::NAMESPACE, '/webhooks/enrollment', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'handle_enrollment_webhook'),
            'permission_callback' => array($this, 'check_webhook_permissions'),
            'args' => array(
                'course_id' => array(
                    'required' => true,
                    'validate_callback' => array($this, 'validate_positive_integer')
                ),
                'user_id' => array(
                    'required' => true,
                    'validate_callback' => array($this, 'validate_positive_integer')
                ),
                'enrollment_date' => array(
                    'sanitize_callback' => 'sanitize_text_field'
                )
            )
        ));
    }
    
    /**
     * Get dashboard statistics
     */
    public function get_dashboard_stats($request) {
        $time_period = $request->get_param('time_period');
        
        $dashboard = new TutorAdvancedTracking_Dashboard();
        $courses = $dashboard->get_courses();
        
        $stats = array(
            'total_courses' => count($courses),
            'total_students' => array_sum(array_column($courses, 'student_count')),
            'avg_progression' => $courses ? round(array_sum(array_column($courses, 'avg_progression')) / count($courses), 1) : 0,
            'avg_quiz_score' => $courses ? round(array_sum(array_column($courses, 'avg_quiz_score')) / count($courses), 1) : 0,
            'time_period' => $time_period,
            'generated_at' => current_time('c')
        );
        
        // Add trend data if time period is specified
        if ($time_period && $time_period !== '0') {
            $stats['trends'] = $this->get_trend_data($time_period);
        }
        
        return rest_ensure_response($stats);
    }
    
    /**
     * Get courses with pagination
     */
    public function get_courses($request) {
        $page = $request->get_param('page');
        $per_page = $request->get_param('per_page');
        $search = $request->get_param('search');
        
        $dashboard = new TutorAdvancedTracking_Dashboard();
        $all_courses = $dashboard->get_courses();
        
        // Apply search filter
        if (!empty($search)) {
            $all_courses = array_filter($all_courses, function($course) use ($search) {
                return stripos($course['title'], $search) !== false || 
                       stripos($course['instructor'], $search) !== false;
            });
        }
        
        // Apply pagination
        $total_courses = count($all_courses);
        $offset = ($page - 1) * $per_page;
        $courses = array_slice($all_courses, $offset, $per_page);
        
        $response = array(
            'courses' => $courses,
            'pagination' => array(
                'page' => $page,
                'per_page' => $per_page,
                'total' => $total_courses,
                'total_pages' => ceil($total_courses / $per_page)
            ),
            'generated_at' => current_time('c')
        );
        
        return rest_ensure_response($response);
    }
    
    /**
     * Get course details
     */
    public function get_course_details($request) {
        $course_id = $request->get_param('id');
        
        $course_stats = new TutorAdvancedTracking_CourseStats();
        $course_data = $course_stats->get_course_details($course_id);
        
        if (!$course_data) {
            return new WP_Error('course_not_found', 'Course not found or access denied', array('status' => 404));
        }
        
        $course_data['generated_at'] = current_time('c');
        
        return rest_ensure_response($course_data);
    }
    
    /**
     * Get course students with pagination
     */
    public function get_course_students($request) {
        $course_id = $request->get_param('id');
        $page = $request->get_param('page');
        $per_page = $request->get_param('per_page');
        
        $course_stats = new TutorAdvancedTracking_CourseStats();
        $course_data = $course_stats->get_course_details($course_id);
        
        if (!$course_data) {
            return new WP_Error('course_not_found', 'Course not found or access denied', array('status' => 404));
        }
        
        $all_students = $course_data['students'] ?? array();
        
        // Apply pagination
        $total_students = count($all_students);
        $offset = ($page - 1) * $per_page;
        $students = array_slice($all_students, $offset, $per_page);
        
        $response = array(
            'course_id' => $course_id,
            'course_title' => $course_data['title'],
            'students' => $students,
            'pagination' => array(
                'page' => $page,
                'per_page' => $per_page,
                'total' => $total_students,
                'total_pages' => ceil($total_students / $per_page)
            ),
            'generated_at' => current_time('c')
        );
        
        return rest_ensure_response($response);
    }
    
    /**
     * Get course analytics
     */
    public function get_course_analytics($request) {
        $course_id = $request->get_param('id');
        $time_period = $request->get_param('time_period');
        
        $analytics = new TutorAdvancedTracking_AdvancedAnalytics();
        $analytics_data = $analytics->get_course_analytics($course_id);
        
        if (!$analytics_data) {
            return new WP_Error('course_not_found', 'Course not found or access denied', array('status' => 404));
        }
        
        $analytics_data['time_period'] = $time_period;
        $analytics_data['generated_at'] = current_time('c');
        
        return rest_ensure_response($analytics_data);
    }
    
    /**
     * Get user statistics
     */
    public function get_user_stats($request) {
        $user_id = $request->get_param('id');
        
        $user_stats = new TutorAdvancedTracking_UserStats();
        $user_data = $user_stats->get_user_details($user_id);
        
        if (!$user_data) {
            return new WP_Error('user_not_found', 'User not found or access denied', array('status' => 404));
        }
        
        $user_data['generated_at'] = current_time('c');
        
        return rest_ensure_response($user_data);
    }
    
    /**
     * Search courses and users
     */
    public function search($request) {
        $query = $request->get_param('query');
        $type = $request->get_param('type');
        $limit = $request->get_param('limit');
        
        $dashboard = new TutorAdvancedTracking_Dashboard();
        $results = $dashboard->search($query, $type);
        
        // Apply limit to results
        if (isset($results['courses'])) {
            $results['courses'] = array_slice($results['courses'], 0, $limit);
        }
        if (isset($results['users'])) {
            $results['users'] = array_slice($results['users'], 0, $limit);
        }
        
        $response = array(
            'query' => $query,
            'type' => $type,
            'results' => $results,
            'generated_at' => current_time('c')
        );
        
        return rest_ensure_response($response);
    }
    
    /**
     * Export courses as JSON
     */
    public function export_courses_json($request) {
        $dashboard = new TutorAdvancedTracking_Dashboard();
        $courses = $dashboard->get_courses();
        
        $export_data = array(
            'export_type' => 'courses',
            'export_date' => current_time('c'),
            'total_courses' => count($courses),
            'courses' => $courses
        );
        
        return rest_ensure_response($export_data);
    }
    
    /**
     * Export course details as JSON
     */
    public function export_course_json($request) {
        $course_id = $request->get_param('id');
        
        $course_stats = new TutorAdvancedTracking_CourseStats();
        $course_data = $course_stats->get_course_details($course_id);
        
        if (!$course_data) {
            return new WP_Error('course_not_found', 'Course not found or access denied', array('status' => 404));
        }
        
        $export_data = array(
            'export_type' => 'course_details',
            'export_date' => current_time('c'),
            'course' => $course_data
        );
        
        return rest_ensure_response($export_data);
    }
    
    /**
     * Handle enrollment webhook
     */
    public function handle_enrollment_webhook($request) {
        $course_id = $request->get_param('course_id');
        $user_id = $request->get_param('user_id');
        $enrollment_date = $request->get_param('enrollment_date') ?: current_time('mysql');
        
        // Validate that course and user exist
        $course = get_post($course_id);
        $user = get_user_by('id', $user_id);
        
        if (!$course || !$user) {
            return new WP_Error('invalid_data', 'Invalid course ID or user ID', array('status' => 400));
        }
        
        // Log the webhook for external systems
        do_action('tutor_advanced_tracking_webhook_enrollment', $course_id, $user_id, $enrollment_date);
        
        $response = array(
            'success' => true,
            'message' => 'Enrollment webhook processed',
            'course_id' => $course_id,
            'user_id' => $user_id,
            'enrollment_date' => $enrollment_date,
            'processed_at' => current_time('c')
        );
        
        return rest_ensure_response($response);
    }
    
    /**
     * Get trend data for dashboard
     */
    private function get_trend_data($days) {
        global $wpdb;
        
        $end_date = current_time('Y-m-d');
        $start_date = date('Y-m-d', strtotime("-{$days} days"));
        
        $enrollment_trend = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(enrollment_date) as date, COUNT(*) as count
             FROM {$wpdb->prefix}tutor_enrollments
             WHERE DATE(enrollment_date) BETWEEN %s AND %s
             GROUP BY DATE(enrollment_date)
             ORDER BY date ASC",
            $start_date, $end_date
        ));
        
        $quiz_trend = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(attempt_started_at) as date, COUNT(*) as count
             FROM {$wpdb->prefix}tutor_quiz_attempts
             WHERE DATE(attempt_started_at) BETWEEN %s AND %s
             AND attempt_status = 'attempt_ended'
             GROUP BY DATE(attempt_started_at)
             ORDER BY date ASC",
            $start_date, $end_date
        ));
        
        return array(
            'enrollments' => $enrollment_trend,
            'quiz_attempts' => $quiz_trend,
            'time_period' => $days,
            'start_date' => $start_date,
            'end_date' => $end_date
        );
    }
    
    /**
     * Check dashboard permissions
     */
    public function check_dashboard_permissions($request) {
        if (!is_user_logged_in()) {
            return false;
        }
        
        // Rate limiting check
        if (!$this->check_api_rate_limit()) {
            return new WP_Error('rate_limit_exceeded', 'Too many API requests. Please slow down.', array('status' => 429));
        }
        
        return current_user_can('manage_options') || current_user_can('tutor_instructor');
    }
    
    /**
     * Check course permissions
     */
    public function check_course_permissions($request) {
        if (!is_user_logged_in()) {
            return false;
        }
        
        if (current_user_can('manage_options')) {
            return true;
        }
        
        if (current_user_can('tutor_instructor')) {
            $course_id = $request->get_param('id');
            $course = get_post($course_id);
            return $course && $course->post_author == get_current_user_id();
        }
        
        return false;
    }
    
    /**
     * Check user permissions
     */
    public function check_user_permissions($request) {
        if (!is_user_logged_in()) {
            return false;
        }
        
        if (current_user_can('manage_options')) {
            return true;
        }
        
        $user_id = $request->get_param('id');
        return $user_id == get_current_user_id();
    }
    
    /**
     * Check webhook permissions
     */
    public function check_webhook_permissions($request) {
        // Check for API key or other authentication method
        $api_key = $request->get_header('X-API-Key');
        $stored_key = get_option('tutor_advanced_tracking_api_key');
        
        if (!$stored_key) {
            return false;
        }
        
        return hash_equals($stored_key, $api_key);
    }
    
    /**
     * Validation callbacks
     */
    
    public function validate_positive_integer($param, $request, $key) {
        return is_numeric($param) && $param > 0;
    }
    
    public function validate_per_page($param, $request, $key) {
        return is_numeric($param) && $param > 0 && $param <= 100;
    }
    
    public function validate_time_period($param, $request, $key) {
        return in_array($param, array('7', '30', '90', '365'));
    }
    
    public function validate_search_query($param, $request, $key) {
        return strlen($param) >= 2 && strlen($param) <= 100;
    }
    
    public function validate_search_type($param, $request, $key) {
        return in_array($param, array('all', 'courses', 'users'));
    }
    
    /**
     * Rate limiting check for API requests
     */
    private function check_api_rate_limit() {
        $user_id = get_current_user_id();
        $transient_key = 'tutor_api_rate_limit_' . $user_id;
        $current_requests = get_transient($transient_key);
        
        if ($current_requests === false) {
            set_transient($transient_key, 1, 60); // 1 minute
            return true;
        }
        
        if ($current_requests >= 100) { // Max 100 API requests per minute
            return false;
        }
        
        set_transient($transient_key, $current_requests + 1, 60);
        return true;
    }
}