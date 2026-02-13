<?php
/**
 * Student Analytics Class
 * 
 * Provides detailed per-student analytics including:
 * - Individual progress tracking
 * - Time spent per lesson/course
 * - Engagement scoring
 * - At-risk student identification
 * - Activity timeline
 * 
 * @package TutorAdvancedTracking
 * @since 1.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class TutorAdvancedTracking_StudentAnalytics {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'), 25);
        
        // Register REST API endpoints
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        
        // Track lesson start/end for time tracking
        add_action('tutor_lesson_started', array($this, 'track_lesson_start'), 10, 2);
        add_action('tutor_lesson_completed', array($this, 'track_lesson_complete'), 10, 2);
        
        // AJAX handlers
        add_action('wp_ajax_tlat_get_student_details', array($this, 'ajax_get_student_details'));
        add_action('wp_ajax_tlat_get_at_risk_students', array($this, 'ajax_get_at_risk_students'));
        add_action('wp_ajax_tlat_get_student_timeline', array($this, 'ajax_get_student_timeline'));
        add_action('wp_ajax_tlat_compare_students', array($this, 'ajax_compare_students'));
        add_action('wp_ajax_tlat_search_students', array($this, 'ajax_search_students'));
        
        // Enqueue scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    /**
     * Add admin menu pages
     */
    public function add_admin_menu() {
        add_submenu_page(
            'tutor-stats',
            __('Students', 'tutor-lms-advanced-tracking'),
            __('📊 Students', 'tutor-lms-advanced-tracking'),
            'manage_tutor',
            'tlat-students',
            array($this, 'render_students_page')
        );
        
        add_submenu_page(
            'tutor-stats',
            __('Compare Students', 'tutor-lms-advanced-tracking'),
            __('⚖️ Compare Students', 'tutor-lms-advanced-tracking'),
            'manage_tutor',
            'tlat-student-comparison',
            array($this, 'render_comparison_page')
        );
    }
    
    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts($hook) {
        if (strpos($hook, 'tlat-students') === false && strpos($hook, 'tlat-student-comparison') === false) {
            return;
        }
        
        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '4.4.0', true);
        wp_enqueue_script(
            'tlat-student-analytics',
            TLAT_PLUGIN_URL . 'assets/js/student-analytics.js',
            array('jquery', 'chart-js'),
            TLAT_VERSION,
            true
        );
        
        wp_localize_script('tlat-student-analytics', 'tlatStudentAnalytics', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('tlat_student_analytics'),
            'i18n' => array(
                'loading' => __('Loading...', 'tutor-lms-advanced-tracking'),
                'error' => __('Error loading data', 'tutor-lms-advanced-tracking'),
                'noData' => __('No data available', 'tutor-lms-advanced-tracking'),
            )
        ));
    }
    
    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        register_rest_route('tlat/v1', '/students', array(
            'methods' => 'GET',
            'callback' => array($this, 'api_get_students'),
            'permission_callback' => array($this, 'check_api_permission'),
        ));
        
        register_rest_route('tlat/v1', '/students/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'api_get_student'),
            'permission_callback' => array($this, 'check_api_permission'),
        ));
        
        register_rest_route('tlat/v1', '/students/at-risk', array(
            'methods' => 'GET',
            'callback' => array($this, 'api_get_at_risk_students'),
            'permission_callback' => array($this, 'check_api_permission'),
        ));
    }
    
    /**
     * Check API permission
     */
    public function check_api_permission() {
        return current_user_can('manage_tutor');
    }
    
    /**
     * Track lesson start time
     */
    public function track_lesson_start($lesson_id, $user_id) {
        $key = 'tlat_lesson_start_' . $lesson_id . '_' . $user_id;
        set_transient($key, time(), HOUR_IN_SECONDS);
    }
    
    /**
     * Track lesson completion and calculate time spent
     */
    public function track_lesson_complete($lesson_id, $user_id) {
        $key = 'tlat_lesson_start_' . $lesson_id . '_' . $user_id;
        $start_time = get_transient($key);
        
        if ($start_time) {
            $time_spent = time() - $start_time;
            
            // Store time spent in user meta
            $course_id = get_post_field('post_parent', $lesson_id);
            $meta_key = 'tlat_time_spent_' . $course_id;
            $current_time = (int) get_user_meta($user_id, $meta_key, true);
            update_user_meta($user_id, $meta_key, $current_time + $time_spent);
            
            // Store per-lesson time
            $lesson_meta_key = 'tlat_lesson_time_' . $lesson_id;
            $lesson_time = (int) get_user_meta($user_id, $lesson_meta_key, true);
            update_user_meta($user_id, $lesson_meta_key, $lesson_time + $time_spent);
            
            delete_transient($key);
        }
    }
    
    /**
     * Calculate engagement score for a student
     * 
     * Score based on:
     * - Completion rate (30%)
     * - Quiz performance (25%)
     * - Activity frequency (25%)
     * - Time spent (20%)
     * 
     * @param int $user_id
     * @param int|null $course_id Optional specific course
     * @return array
     */
    public function calculate_engagement_score($user_id, $course_id = null) {
        global $wpdb;
        
        $scores = array(
            'completion' => 0,
            'quiz' => 0,
            'activity' => 0,
            'time' => 0,
            'total' => 0,
        );
        
        // Get completion rate
        $completion_rate = $this->get_completion_rate($user_id, $course_id);
        $scores['completion'] = $completion_rate * 0.3;
        
        // Get quiz performance
        $quiz_score = $this->get_quiz_performance($user_id, $course_id);
        $scores['quiz'] = ($quiz_score / 100) * 0.25;
        
        // Get activity frequency (logins in last 30 days)
        $activity_score = $this->get_activity_score($user_id);
        $scores['activity'] = ($activity_score / 100) * 0.25;
        
        // Get time spent score
        $time_score = $this->get_time_score($user_id, $course_id);
        $scores['time'] = ($time_score / 100) * 0.20;
        
        // Calculate total (0-100)
        $scores['total'] = round(($scores['completion'] + $scores['quiz'] + $scores['activity'] + $scores['time']) * 100);
        
        return $scores;
    }
    
    /**
     * Get completion rate for a student
     */
    private function get_completion_rate($user_id, $course_id = null) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'tutor_enrollments';
        
        if ($course_id) {
            $completed = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}comments 
                WHERE user_id = %d AND comment_type = 'tutor_course_completed'
                AND comment_post_ID = %d",
                $user_id, $course_id
            ));
            return $completed > 0 ? 1.0 : 0.0;
        }
        
        // All courses
        $enrolled = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE user_id = %d",
            $user_id
        ));
        
        if ($enrolled === 0) return 0;
        
        $completed = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT comment_post_ID) FROM {$wpdb->prefix}comments 
            WHERE user_id = %d AND comment_type = 'tutor_course_completed'",
            $user_id
        ));
        
        return $completed / $enrolled;
    }
    
    /**
     * Get average quiz performance
     */
    private function get_quiz_performance($user_id, $course_id = null) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'tutor_quiz_attempts';
        
        $where = "user_id = %d AND attempt_status = 'attempt_ended'";
        $params = array($user_id);
        
        if ($course_id) {
            $where .= " AND quiz_id IN (SELECT ID FROM {$wpdb->posts} WHERE post_parent = %d)";
            $params[] = $course_id;
        }
        
        $avg_score = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(earned_marks / total_marks * 100) FROM {$table} WHERE {$where}",
            $params
        ));
        
        return $avg_score ? (float) $avg_score : 0;
    }
    
    /**
     * Get activity score based on login frequency
     */
    private function get_activity_score($user_id) {
        global $wpdb;
        
        // Count logins in last 30 days (using user meta or sessions)
        $last_login = get_user_meta($user_id, 'last_login', true);
        
        if (!$last_login) {
            return 0;
        }
        
        $days_since_login = (time() - strtotime($last_login)) / DAY_IN_SECONDS;
        
        if ($days_since_login <= 1) return 100;
        if ($days_since_login <= 3) return 80;
        if ($days_since_login <= 7) return 60;
        if ($days_since_login <= 14) return 40;
        if ($days_since_login <= 30) return 20;
        return 0;
    }
    
    /**
     * Get time score (compared to average)
     */
    private function get_time_score($user_id, $course_id = null) {
        global $wpdb;
        
        // Get user's total time spent
        $user_time = 0;
        $meta_keys = $wpdb->get_col($wpdb->prepare(
            "SELECT meta_key FROM {$wpdb->usermeta} 
            WHERE user_id = %d AND meta_key LIKE %s",
            $user_id, 'tlat_time_spent_%'
        ));
        
        foreach ($meta_keys as $key) {
            $user_time += (int) get_user_meta($user_id, $key, true);
        }
        
        if ($user_time === 0) return 0;
        
        // Get average time spent by all users
        $avg_time = $wpdb->get_var(
            "SELECT AVG(CAST(meta_value AS UNSIGNED)) FROM {$wpdb->usermeta} 
            WHERE meta_key LIKE 'tlat_time_spent_%'"
        );
        
        if (!$avg_time || $avg_time === 0) {
            return 50; // Default middle score if no comparison data
        }
        
        // Score relative to average (capped at 100)
        $ratio = $user_time / $avg_time;
        return min(100, $ratio * 50);
    }
    
    /**
     * Identify at-risk students
     * 
     * Criteria:
     * - No activity in X days
     * - Low engagement score
     * - Low quiz scores
     * - Stalled progress
     */
    public function get_at_risk_students($threshold_days = 14, $min_engagement = 30) {
        global $wpdb;
        
        // Get all enrolled students
        $students = $wpdb->get_results(
            "SELECT DISTINCT user_id FROM {$wpdb->prefix}tutor_enrollments"
        );
        
        $at_risk = array();
        
        foreach ($students as $student) {
            $user_id = $student->user_id;
            $user = get_userdata($user_id);
            
            if (!$user) continue;
            
            $reasons = array();
            
            // Check last activity
            $last_login = get_user_meta($user_id, 'last_login', true);
            $days_inactive = $last_login ? 
                (time() - strtotime($last_login)) / DAY_IN_SECONDS : 
                999;
            
            if ($days_inactive >= $threshold_days) {
                $reasons[] = sprintf(
                    __('No activity for %d days', 'tutor-lms-advanced-tracking'),
                    round($days_inactive)
                );
            }
            
            // Check engagement score
            $engagement = $this->calculate_engagement_score($user_id);
            if ($engagement['total'] < $min_engagement) {
                $reasons[] = sprintf(
                    __('Low engagement score (%d%%)', 'tutor-lms-advanced-tracking'),
                    $engagement['total']
                );
            }
            
            // Check for stalled progress (enrolled but no completions in 30+ days)
            $enrolled_count = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}tutor_enrollments WHERE user_id = %d",
                $user_id
            ));
            
            $recent_completions = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}comments 
                WHERE user_id = %d 
                AND comment_type = 'tutor_course_completed' 
                AND comment_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
                $user_id
            ));
            
            if ($enrolled_count > 0 && $recent_completions === 0 && $engagement['total'] < 50) {
                $reasons[] = __('Stalled progress', 'tutor-lms-advanced-tracking');
            }
            
            if (!empty($reasons)) {
                $at_risk[] = array(
                    'user_id' => $user_id,
                    'display_name' => $user->display_name,
                    'email' => $user->user_email,
                    'days_inactive' => round($days_inactive),
                    'engagement_score' => $engagement['total'],
                    'reasons' => $reasons,
                    'enrolled_courses' => $enrolled_count,
                );
            }
        }
        
        // Sort by engagement score (lowest first)
        usort($at_risk, function($a, $b) {
            return $a['engagement_score'] - $b['engagement_score'];
        });
        
        return $at_risk;
    }
    
    /**
     * Get student activity timeline
     */
    public function get_student_timeline($user_id, $limit = 50) {
        global $wpdb;
        
        $activities = array();
        
        // Get enrollments
        $enrollments = $wpdb->get_results($wpdb->prepare(
            "SELECT course_id, enrollment_date, status FROM {$wpdb->prefix}tutor_enrollments 
            WHERE user_id = %d ORDER BY enrollment_date DESC LIMIT %d",
            $user_id, $limit
        ));
        
        foreach ($enrollments as $e) {
            $course = get_post($e->course_id);
            $activities[] = array(
                'type' => 'enrollment',
                'icon' => '📚',
                'title' => sprintf(__('Enrolled in %s', 'tutor-lms-advanced-tracking'), $course ? $course->post_title : 'Unknown'),
                'timestamp' => $e->enrollment_date,
                'status' => $e->status,
            );
        }
        
        // Get lesson completions
        $lessons = $wpdb->get_results($wpdb->prepare(
            "SELECT comment_post_ID, comment_date FROM {$wpdb->prefix}comments 
            WHERE user_id = %d AND comment_type = 'tutor_lesson_completed' 
            ORDER BY comment_date DESC LIMIT %d",
            $user_id, $limit
        ));
        
        foreach ($lessons as $l) {
            $lesson = get_post($l->comment_post_ID);
            $activities[] = array(
                'type' => 'lesson_complete',
                'icon' => '✅',
                'title' => sprintf(__('Completed lesson: %s', 'tutor-lms-advanced-tracking'), $lesson ? $lesson->post_title : 'Unknown'),
                'timestamp' => $l->comment_date,
            );
        }
        
        // Get quiz attempts
        $quizzes = $wpdb->get_results($wpdb->prepare(
            "SELECT quiz_id, earned_marks, total_marks, attempt_started_at 
            FROM {$wpdb->prefix}tutor_quiz_attempts 
            WHERE user_id = %d AND attempt_status = 'attempt_ended'
            ORDER BY attempt_started_at DESC LIMIT %d",
            $user_id, $limit
        ));
        
        foreach ($quizzes as $q) {
            $quiz = get_post($q->quiz_id);
            $percentage = $q->total_marks > 0 ? round(($q->earned_marks / $q->total_marks) * 100) : 0;
            $activities[] = array(
                'type' => 'quiz_complete',
                'icon' => $percentage >= 70 ? '🏆' : '📝',
                'title' => sprintf(__('Quiz: %s (%d%%)', 'tutor-lms-advanced-tracking'), $quiz ? $quiz->post_title : 'Unknown', $percentage),
                'timestamp' => $q->attempt_started_at,
                'score' => $percentage,
            );
        }
        
        // Get course completions
        $completions = $wpdb->get_results($wpdb->prepare(
            "SELECT comment_post_ID, comment_date FROM {$wpdb->prefix}comments 
            WHERE user_id = %d AND comment_type = 'tutor_course_completed' 
            ORDER BY comment_date DESC LIMIT %d",
            $user_id, $limit
        ));
        
        foreach ($completions as $c) {
            $course = get_post($c->comment_post_ID);
            $activities[] = array(
                'type' => 'course_complete',
                'icon' => '🎓',
                'title' => sprintf(__('Completed course: %s', 'tutor-lms-advanced-tracking'), $course ? $course->post_title : 'Unknown'),
                'timestamp' => $c->comment_date,
            );
        }
        
        // Sort by timestamp (most recent first)
        usort($activities, function($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });
        
        return array_slice($activities, 0, $limit);
    }
    
    /**
     * Render students page
     */
    public function render_students_page() {
        $at_risk = $this->get_at_risk_students();
        $at_risk_count = count($at_risk);
        
        ?>
        <div class="wrap tlat-students-page">
            <h1><?php _e('Student Analytics', 'tutor-lms-advanced-tracking'); ?></h1>
            
            <!-- Stats Cards -->
            <div class="tlat-stats-cards" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">
                <div class="tlat-stat-card" style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <div style="font-size: 32px; font-weight: bold; color: #3b82f6;">
                        <?php echo esc_html($this->get_total_students()); ?>
                    </div>
                    <div style="color: #6b7280; margin-top: 5px;"><?php _e('Total Students', 'tutor-lms-advanced-tracking'); ?></div>
                </div>
                
                <div class="tlat-stat-card" style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <div style="font-size: 32px; font-weight: bold; color: #10b981;">
                        <?php echo esc_html($this->get_active_students_count()); ?>
                    </div>
                    <div style="color: #6b7280; margin-top: 5px;"><?php _e('Active (Last 7 days)', 'tutor-lms-advanced-tracking'); ?></div>
                </div>
                
                <div class="tlat-stat-card" style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <div style="font-size: 32px; font-weight: bold; color: <?php echo $at_risk_count > 0 ? '#ef4444' : '#10b981'; ?>;">
                        <?php echo esc_html($at_risk_count); ?>
                    </div>
                    <div style="color: #6b7280; margin-top: 5px;"><?php _e('At-Risk Students', 'tutor-lms-advanced-tracking'); ?></div>
                </div>
                
                <div class="tlat-stat-card" style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <div style="font-size: 32px; font-weight: bold; color: #8b5cf6;">
                        <?php echo esc_html($this->get_avg_engagement_score()); ?>%
                    </div>
                    <div style="color: #6b7280; margin-top: 5px;"><?php _e('Avg Engagement Score', 'tutor-lms-advanced-tracking'); ?></div>
                </div>
            </div>
            
            <!-- At-Risk Students Section -->
            <?php if ($at_risk_count > 0): ?>
            <div class="tlat-at-risk-section" style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
                <h2 style="color: #dc2626; margin-top: 0;">
                    ⚠️ <?php _e('At-Risk Students', 'tutor-lms-advanced-tracking'); ?>
                </h2>
                <p style="color: #7f1d1d;"><?php _e('These students may need attention based on their activity and engagement patterns.', 'tutor-lms-advanced-tracking'); ?></p>
                
                <table class="wp-list-table widefat fixed striped" style="background: white;">
                    <thead>
                        <tr>
                            <th><?php _e('Student', 'tutor-lms-advanced-tracking'); ?></th>
                            <th><?php _e('Email', 'tutor-lms-advanced-tracking'); ?></th>
                            <th><?php _e('Days Inactive', 'tutor-lms-advanced-tracking'); ?></th>
                            <th><?php _e('Engagement', 'tutor-lms-advanced-tracking'); ?></th>
                            <th><?php _e('Reasons', 'tutor-lms-advanced-tracking'); ?></th>
                            <th><?php _e('Actions', 'tutor-lms-advanced-tracking'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($at_risk, 0, 10) as $student): ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($student['display_name']); ?></strong>
                            </td>
                            <td>
                                <a href="mailto:<?php echo esc_attr($student['email']); ?>">
                                    <?php echo esc_html($student['email']); ?>
                                </a>
                            </td>
                            <td>
                                <span style="color: <?php echo $student['days_inactive'] > 30 ? '#dc2626' : '#f59e0b'; ?>">
                                    <?php echo esc_html($student['days_inactive']); ?> <?php _e('days', 'tutor-lms-advanced-tracking'); ?>
                                </span>
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <div style="width: 60px; height: 8px; background: #e5e7eb; border-radius: 4px; overflow: hidden;">
                                        <div style="width: <?php echo esc_attr($student['engagement_score']); ?>%; height: 100%; background: <?php echo $student['engagement_score'] < 30 ? '#ef4444' : ($student['engagement_score'] < 60 ? '#f59e0b' : '#10b981'); ?>;"></div>
                                    </div>
                                    <span><?php echo esc_html($student['engagement_score']); ?>%</span>
                                </div>
                            </td>
                            <td>
                                <?php foreach ($student['reasons'] as $reason): ?>
                                    <span style="display: inline-block; background: #fee2e2; color: #dc2626; padding: 2px 8px; border-radius: 4px; font-size: 12px; margin: 2px;">
                                        <?php echo esc_html($reason); ?>
                                    </span>
                                <?php endforeach; ?>
                            </td>
                            <td>
                                <button class="button button-small tlat-view-student" data-user-id="<?php echo esc_attr($student['user_id']); ?>">
                                    <?php _e('View Details', 'tutor-lms-advanced-tracking'); ?>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            
            <!-- All Students Search/Filter -->
            <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <h2 style="margin-top: 0;"><?php _e('All Students', 'tutor-lms-advanced-tracking'); ?></h2>
                
                <div style="display: flex; gap: 15px; margin-bottom: 20px;">
                    <input type="text" id="tlat-student-search" placeholder="<?php _e('Search by name or email...', 'tutor-lms-advanced-tracking'); ?>" style="flex: 1; padding: 8px 12px;">
                    <select id="tlat-course-filter">
                        <option value=""><?php _e('All Courses', 'tutor-lms-advanced-tracking'); ?></option>
                        <?php 
                        $courses = get_posts(array('post_type' => 'courses', 'posts_per_page' => -1));
                        foreach ($courses as $course): ?>
                            <option value="<?php echo esc_attr($course->ID); ?>"><?php echo esc_html($course->post_title); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select id="tlat-engagement-filter">
                        <option value=""><?php _e('All Engagement Levels', 'tutor-lms-advanced-tracking'); ?></option>
                        <option value="high"><?php _e('High (70%+)', 'tutor-lms-advanced-tracking'); ?></option>
                        <option value="medium"><?php _e('Medium (30-70%)', 'tutor-lms-advanced-tracking'); ?></option>
                        <option value="low"><?php _e('Low (<30%)', 'tutor-lms-advanced-tracking'); ?></option>
                    </select>
                </div>
                
                <div id="tlat-students-table-container">
                    <?php echo $this->render_students_table(); ?>
                </div>
            </div>
            
            <!-- Student Detail Modal -->
            <div id="tlat-student-modal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 100000;">
                <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; border-radius: 12px; width: 90%; max-width: 800px; max-height: 90vh; overflow: auto;">
                    <div style="padding: 20px; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center;">
                        <h3 id="tlat-modal-title" style="margin: 0;"><?php _e('Student Details', 'tutor-lms-advanced-tracking'); ?></h3>
                        <button id="tlat-close-modal" class="button">&times; <?php _e('Close', 'tutor-lms-advanced-tracking'); ?></button>
                    </div>
                    <div id="tlat-modal-content" style="padding: 20px;">
                        <!-- Content loaded via AJAX -->
                    </div>
                </div>
            </div>
        </div>
        
        <style>
            .tlat-students-page .wp-list-table th,
            .tlat-students-page .wp-list-table td {
                vertical-align: middle;
            }
            .tlat-view-student:hover {
                background: #2563eb !important;
                color: white !important;
            }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // View student details
            $('.tlat-view-student').on('click', function() {
                var userId = $(this).data('user-id');
                $('#tlat-student-modal').show();
                $('#tlat-modal-content').html('<div style="text-align: center; padding: 40px;"><span class="spinner is-active" style="float: none;"></span></div>');
                
                $.post(ajaxurl, {
                    action: 'tlat_get_student_details',
                    nonce: '<?php echo wp_create_nonce('tlat_student_analytics'); ?>',
                    user_id: userId
                }, function(response) {
                    if (response.success) {
                        $('#tlat-modal-content').html(response.data.html);
                        $('#tlat-modal-title').text(response.data.name);
                    } else {
                        $('#tlat-modal-content').html('<p style="color: red;"><?php _e('Error loading student data', 'tutor-lms-advanced-tracking'); ?></p>');
                    }
                });
            });
            
            // Close modal
            $('#tlat-close-modal, #tlat-student-modal').on('click', function(e) {
                if (e.target === this) {
                    $('#tlat-student-modal').hide();
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Render students table
     */
    private function render_students_table() {
        global $wpdb;
        
        $students = $wpdb->get_results(
            "SELECT DISTINCT e.user_id, u.display_name, u.user_email,
                    COUNT(DISTINCT e.course_id) as enrolled_courses
             FROM {$wpdb->prefix}tutor_enrollments e
             JOIN {$wpdb->users} u ON e.user_id = u.ID
             GROUP BY e.user_id
             ORDER BY u.display_name ASC
             LIMIT 100"
        );
        
        ob_start();
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Student', 'tutor-lms-advanced-tracking'); ?></th>
                    <th><?php _e('Email', 'tutor-lms-advanced-tracking'); ?></th>
                    <th><?php _e('Courses', 'tutor-lms-advanced-tracking'); ?></th>
                    <th><?php _e('Engagement', 'tutor-lms-advanced-tracking'); ?></th>
                    <th><?php _e('Last Active', 'tutor-lms-advanced-tracking'); ?></th>
                    <th><?php _e('Actions', 'tutor-lms-advanced-tracking'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($students as $student): 
                    $engagement = $this->calculate_engagement_score($student->user_id);
                    $last_login = get_user_meta($student->user_id, 'last_login', true);
                ?>
                <tr>
                    <td><strong><?php echo esc_html($student->display_name); ?></strong></td>
                    <td><?php echo esc_html($student->user_email); ?></td>
                    <td><?php echo esc_html($student->enrolled_courses); ?></td>
                    <td>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <div style="width: 60px; height: 8px; background: #e5e7eb; border-radius: 4px; overflow: hidden;">
                                <div style="width: <?php echo esc_attr($engagement['total']); ?>%; height: 100%; background: <?php echo $engagement['total'] < 30 ? '#ef4444' : ($engagement['total'] < 60 ? '#f59e0b' : '#10b981'); ?>;"></div>
                            </div>
                            <span><?php echo esc_html($engagement['total']); ?>%</span>
                        </div>
                    </td>
                    <td>
                        <?php 
                        if ($last_login) {
                            echo esc_html(human_time_diff(strtotime($last_login)) . ' ' . __('ago', 'tutor-lms-advanced-tracking'));
                        } else {
                            echo '—';
                        }
                        ?>
                    </td>
                    <td>
                        <button class="button button-small tlat-view-student" data-user-id="<?php echo esc_attr($student->user_id); ?>">
                            <?php _e('View', 'tutor-lms-advanced-tracking'); ?>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get total students count
     */
    private function get_total_students() {
        global $wpdb;
        return (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->prefix}tutor_enrollments"
        );
    }
    
    /**
     * Get active students count (last 7 days)
     */
    private function get_active_students_count() {
        global $wpdb;
        
        // This is a simplified count - in production, you'd track actual logins
        return (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->prefix}comments 
             WHERE comment_type IN ('tutor_lesson_completed', 'tutor_course_completed')
             AND comment_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
    }
    
    /**
     * Get average engagement score
     */
    private function get_avg_engagement_score() {
        global $wpdb;
        
        $students = $wpdb->get_col(
            "SELECT DISTINCT user_id FROM {$wpdb->prefix}tutor_enrollments LIMIT 100"
        );
        
        if (empty($students)) return 0;
        
        $total = 0;
        foreach ($students as $user_id) {
            $engagement = $this->calculate_engagement_score($user_id);
            $total += $engagement['total'];
        }
        
        return round($total / count($students));
    }
    
    /**
     * AJAX: Get student details
     */
    public function ajax_get_student_details() {
        check_ajax_referer('tlat_student_analytics', 'nonce');
        
        if (!current_user_can('manage_tutor')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }
        
        $user_id = intval($_POST['user_id']);
        $user = get_userdata($user_id);
        
        if (!$user) {
            wp_send_json_error(array('message' => 'User not found'));
        }
        
        $engagement = $this->calculate_engagement_score($user_id);
        $timeline = $this->get_student_timeline($user_id, 20);
        
        ob_start();
        ?>
        <div class="tlat-student-detail">
            <!-- Engagement Score -->
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 25px;">
                <div style="text-align: center; padding: 15px; background: #f8fafc; border-radius: 8px;">
                    <div style="font-size: 24px; font-weight: bold; color: <?php echo $engagement['total'] < 30 ? '#ef4444' : ($engagement['total'] < 60 ? '#f59e0b' : '#10b981'); ?>;">
                        <?php echo esc_html($engagement['total']); ?>%
                    </div>
                    <div style="color: #6b7280; font-size: 12px;"><?php _e('Overall', 'tutor-lms-advanced-tracking'); ?></div>
                </div>
                <div style="text-align: center; padding: 15px; background: #f8fafc; border-radius: 8px;">
                    <div style="font-size: 24px; font-weight: bold; color: #3b82f6;">
                        <?php echo round($engagement['completion'] / 0.3 * 100); ?>%
                    </div>
                    <div style="color: #6b7280; font-size: 12px;"><?php _e('Completion', 'tutor-lms-advanced-tracking'); ?></div>
                </div>
                <div style="text-align: center; padding: 15px; background: #f8fafc; border-radius: 8px;">
                    <div style="font-size: 24px; font-weight: bold; color: #8b5cf6;">
                        <?php echo round($engagement['quiz'] / 0.25 * 100); ?>%
                    </div>
                    <div style="color: #6b7280; font-size: 12px;"><?php _e('Quiz Score', 'tutor-lms-advanced-tracking'); ?></div>
                </div>
                <div style="text-align: center; padding: 15px; background: #f8fafc; border-radius: 8px;">
                    <div style="font-size: 24px; font-weight: bold; color: #f59e0b;">
                        <?php echo round($engagement['activity'] / 0.25 * 100); ?>%
                    </div>
                    <div style="color: #6b7280; font-size: 12px;"><?php _e('Activity', 'tutor-lms-advanced-tracking'); ?></div>
                </div>
            </div>
            
            <!-- Activity Timeline -->
            <h4 style="margin-bottom: 15px;"><?php _e('Recent Activity', 'tutor-lms-advanced-tracking'); ?></h4>
            <div style="max-height: 300px; overflow-y: auto;">
                <?php if (empty($timeline)): ?>
                    <p style="color: #6b7280; font-style: italic;"><?php _e('No activity recorded yet.', 'tutor-lms-advanced-tracking'); ?></p>
                <?php else: ?>
                    <?php foreach ($timeline as $activity): ?>
                    <div style="display: flex; gap: 12px; padding: 10px 0; border-bottom: 1px solid #e5e7eb;">
                        <div style="font-size: 20px;"><?php echo esc_html($activity['icon']); ?></div>
                        <div style="flex: 1;">
                            <div><?php echo esc_html($activity['title']); ?></div>
                            <div style="color: #6b7280; font-size: 12px;">
                                <?php echo esc_html(human_time_diff(strtotime($activity['timestamp'])) . ' ' . __('ago', 'tutor-lms-advanced-tracking')); ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
        $html = ob_get_clean();
        
        wp_send_json_success(array(
            'html' => $html,
            'name' => $user->display_name,
        ));
    }
    
    /**
     * REST API: Get students
     */
    public function api_get_students($request) {
        global $wpdb;
        
        $page = $request->get_param('page') ?: 1;
        $per_page = $request->get_param('per_page') ?: 20;
        $offset = ($page - 1) * $per_page;
        
        $students = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT e.user_id, u.display_name, u.user_email
             FROM {$wpdb->prefix}tutor_enrollments e
             JOIN {$wpdb->users} u ON e.user_id = u.ID
             ORDER BY u.display_name ASC
             LIMIT %d OFFSET %d",
            $per_page, $offset
        ));
        
        $data = array();
        foreach ($students as $student) {
            $engagement = $this->calculate_engagement_score($student->user_id);
            $data[] = array(
                'id' => $student->user_id,
                'name' => $student->display_name,
                'email' => $student->user_email,
                'engagement_score' => $engagement['total'],
                'engagement_breakdown' => $engagement,
            );
        }
        
        return rest_ensure_response($data);
    }
    
    /**
     * REST API: Get single student
     */
    public function api_get_student($request) {
        $user_id = $request->get_param('id');
        $user = get_userdata($user_id);
        
        if (!$user) {
            return new WP_Error('not_found', 'Student not found', array('status' => 404));
        }
        
        $engagement = $this->calculate_engagement_score($user_id);
        $timeline = $this->get_student_timeline($user_id, 50);
        
        return rest_ensure_response(array(
            'id' => $user_id,
            'name' => $user->display_name,
            'email' => $user->user_email,
            'engagement_score' => $engagement['total'],
            'engagement_breakdown' => $engagement,
            'timeline' => $timeline,
        ));
    }
    
    /**
     * REST API: Get at-risk students
     */
    public function api_get_at_risk_students($request) {
        $threshold_days = $request->get_param('threshold_days') ?: 14;
        $min_engagement = $request->get_param('min_engagement') ?: 30;
        
        $at_risk = $this->get_at_risk_students($threshold_days, $min_engagement);
        
        return rest_ensure_response($at_risk);
    }
    
    /**
     * Render student comparison page
     */
    public function render_comparison_page() {
        global $wpdb;
        
        // Get all enrolled students for the selector
        $students = $wpdb->get_results(
            "SELECT DISTINCT e.user_id, u.display_name, u.user_email
             FROM {$wpdb->prefix}tutor_enrollments e
             JOIN {$wpdb->users} u ON e.user_id = u.ID
             ORDER BY u.display_name ASC
             LIMIT 500"
        );
        
        // Get courses for filtering
        $courses = get_posts(array('post_type' => 'courses', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC'));
        
        ?>
        <div class="wrap tlat-comparison-page">
            <h1><?php _e('Compare Students', 'tutor-lms-advanced-tracking'); ?></h1>
            <p style="color: #6b7280; margin-bottom: 20px;">
                <?php _e('Select 2-5 students to compare their progression, engagement, and performance side-by-side.', 'tutor-lms-advanced-tracking'); ?>
            </p>
            
            <!-- Student Selection -->
            <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 20px;">
                <h3 style="margin-top: 0;"><?php _e('Select Students to Compare', 'tutor-lms-advanced-tracking'); ?></h3>
                
                <div style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
                    <div style="flex: 1; min-width: 300px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 500;">
                            <?php _e('Search & Select Students', 'tutor-lms-advanced-tracking'); ?>
                        </label>
                        <input type="text" id="tlat-student-search-input" placeholder="<?php _e('Type to search students...', 'tutor-lms-advanced-tracking'); ?>" style="width: 100%; padding: 10px;">
                        <div id="tlat-search-results" style="display: none; position: absolute; background: white; border: 1px solid #ddd; border-radius: 4px; max-height: 200px; overflow-y: auto; z-index: 1000; width: 300px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);"></div>
                    </div>
                    
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 500;">
                            <?php _e('Filter by Course', 'tutor-lms-advanced-tracking'); ?>
                        </label>
                        <select id="tlat-course-filter-comparison">
                            <option value=""><?php _e('All Courses', 'tutor-lms-advanced-tracking'); ?></option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?php echo esc_attr($course->ID); ?>"><?php echo esc_html($course->post_title); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <button id="tlat-compare-btn" class="button button-primary" disabled>
                        <?php _e('Compare Selected', 'tutor-lms-advanced-tracking'); ?>
                    </button>
                </div>
                
                <!-- Selected Students Tags -->
                <div id="tlat-selected-students" style="display: flex; flex-wrap: wrap; gap: 10px; margin-top: 15px; min-height: 40px; padding: 10px; background: #f8fafc; border-radius: 4px;">
                    <span style="color: #9ca3af; font-style: italic;"><?php _e('No students selected yet. Search and click to add.', 'tutor-lms-advanced-tracking'); ?></span>
                </div>
            </div>
            
            <!-- Comparison Results -->
            <div id="tlat-comparison-results" style="display: none;">
                <!-- Overview Cards -->
                <div id="tlat-comparison-cards" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px;"></div>
                
                <!-- Charts Section -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px;">
                    <!-- Radar Chart: Engagement Breakdown -->
                    <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                        <h3 style="margin-top: 0;"><?php _e('Engagement Comparison', 'tutor-lms-advanced-tracking'); ?></h3>
                        <canvas id="tlat-radar-chart" height="300"></canvas>
                    </div>
                    
                    <!-- Bar Chart: Quiz Performance -->
                    <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                        <h3 style="margin-top: 0;"><?php _e('Quiz Performance', 'tutor-lms-advanced-tracking'); ?></h3>
                        <canvas id="tlat-bar-chart" height="300"></canvas>
                    </div>
                </div>
                
                <!-- Progress Over Time -->
                <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-top: 20px;">
                    <h3 style="margin-top: 0;"><?php _e('Activity Over Time (Last 30 Days)', 'tutor-lms-advanced-tracking'); ?></h3>
                    <canvas id="tlat-line-chart" height="200"></canvas>
                </div>
                
                <!-- Detailed Comparison Table -->
                <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-top: 20px;">
                    <h3 style="margin-top: 0;"><?php _e('Detailed Metrics', 'tutor-lms-advanced-tracking'); ?></h3>
                    <div id="tlat-comparison-table"></div>
                </div>
            </div>
            
            <!-- Loading State -->
            <div id="tlat-comparison-loading" style="display: none; text-align: center; padding: 40px;">
                <span class="spinner is-active" style="float: none;"></span>
                <p><?php _e('Loading comparison data...', 'tutor-lms-advanced-tracking'); ?></p>
            </div>
        </div>
        
        <style>
            .tlat-comparison-page .tlat-student-tag {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 6px 12px;
                background: #3b82f6;
                color: white;
                border-radius: 20px;
                font-size: 13px;
            }
            .tlat-comparison-page .tlat-student-tag .remove {
                cursor: pointer;
                font-weight: bold;
                opacity: 0.8;
            }
            .tlat-comparison-page .tlat-student-tag .remove:hover {
                opacity: 1;
            }
            .tlat-comparison-page .tlat-search-item {
                padding: 10px 15px;
                cursor: pointer;
                border-bottom: 1px solid #eee;
            }
            .tlat-comparison-page .tlat-search-item:hover {
                background: #f3f4f6;
            }
            .tlat-comparison-page .tlat-search-item:last-child {
                border-bottom: none;
            }
            .tlat-comparison-page .tlat-search-item.disabled {
                opacity: 0.5;
                cursor: not-allowed;
                background: #f9fafb;
            }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            var selectedStudents = [];
            var radarChart = null;
            var barChart = null;
            var lineChart = null;
            var searchTimeout = null;
            
            // Student colors for charts
            var studentColors = [
                { bg: 'rgba(59, 130, 246, 0.2)', border: 'rgb(59, 130, 246)' },
                { bg: 'rgba(16, 185, 129, 0.2)', border: 'rgb(16, 185, 129)' },
                { bg: 'rgba(245, 158, 11, 0.2)', border: 'rgb(245, 158, 11)' },
                { bg: 'rgba(139, 92, 246, 0.2)', border: 'rgb(139, 92, 246)' },
                { bg: 'rgba(239, 68, 68, 0.2)', border: 'rgb(239, 68, 68)' }
            ];
            
            // Search students
            $('#tlat-student-search-input').on('input', function() {
                var query = $(this).val();
                clearTimeout(searchTimeout);
                
                if (query.length < 2) {
                    $('#tlat-search-results').hide();
                    return;
                }
                
                searchTimeout = setTimeout(function() {
                    $.post(ajaxurl, {
                        action: 'tlat_search_students',
                        nonce: '<?php echo wp_create_nonce('tlat_student_analytics'); ?>',
                        query: query,
                        course_id: $('#tlat-course-filter-comparison').val()
                    }, function(response) {
                        if (response.success && response.data.length > 0) {
                            var html = '';
                            response.data.forEach(function(student) {
                                var isSelected = selectedStudents.some(s => s.id == student.id);
                                html += '<div class="tlat-search-item' + (isSelected ? ' disabled' : '') + '" data-id="' + student.id + '" data-name="' + student.name + '">';
                                html += '<strong>' + student.name + '</strong><br>';
                                html += '<small style="color: #6b7280;">' + student.email + '</small>';
                                if (isSelected) html += ' <span style="color: #10b981;">✓ Selected</span>';
                                html += '</div>';
                            });
                            $('#tlat-search-results').html(html).show();
                        } else {
                            $('#tlat-search-results').html('<div style="padding: 15px; color: #6b7280;"><?php _e('No students found', 'tutor-lms-advanced-tracking'); ?></div>').show();
                        }
                    });
                }, 300);
            });
            
            // Click outside to close search results
            $(document).on('click', function(e) {
                if (!$(e.target).closest('#tlat-student-search-input, #tlat-search-results').length) {
                    $('#tlat-search-results').hide();
                }
            });
            
            // Select student from search
            $(document).on('click', '.tlat-search-item:not(.disabled)', function() {
                if (selectedStudents.length >= 5) {
                    alert('<?php _e('You can compare up to 5 students at a time.', 'tutor-lms-advanced-tracking'); ?>');
                    return;
                }
                
                var id = $(this).data('id');
                var name = $(this).data('name');
                
                selectedStudents.push({ id: id, name: name });
                updateSelectedStudentsUI();
                $('#tlat-search-results').hide();
                $('#tlat-student-search-input').val('');
            });
            
            // Remove selected student
            $(document).on('click', '.tlat-student-tag .remove', function() {
                var id = $(this).closest('.tlat-student-tag').data('id');
                selectedStudents = selectedStudents.filter(s => s.id != id);
                updateSelectedStudentsUI();
            });
            
            // Update UI for selected students
            function updateSelectedStudentsUI() {
                var container = $('#tlat-selected-students');
                
                if (selectedStudents.length === 0) {
                    container.html('<span style="color: #9ca3af; font-style: italic;"><?php _e('No students selected yet. Search and click to add.', 'tutor-lms-advanced-tracking'); ?></span>');
                    $('#tlat-compare-btn').prop('disabled', true);
                } else {
                    var html = '';
                    selectedStudents.forEach(function(student, index) {
                        html += '<span class="tlat-student-tag" data-id="' + student.id + '" style="background: ' + studentColors[index].border + ';">';
                        html += student.name;
                        html += '<span class="remove">×</span>';
                        html += '</span>';
                    });
                    container.html(html);
                    $('#tlat-compare-btn').prop('disabled', selectedStudents.length < 2);
                }
            }
            
            // Compare students
            $('#tlat-compare-btn').on('click', function() {
                if (selectedStudents.length < 2) return;
                
                $('#tlat-comparison-results').hide();
                $('#tlat-comparison-loading').show();
                
                var userIds = selectedStudents.map(s => s.id);
                
                $.post(ajaxurl, {
                    action: 'tlat_compare_students',
                    nonce: '<?php echo wp_create_nonce('tlat_student_analytics'); ?>',
                    user_ids: userIds,
                    course_id: $('#tlat-course-filter-comparison').val()
                }, function(response) {
                    $('#tlat-comparison-loading').hide();
                    
                    if (response.success) {
                        renderComparison(response.data);
                        $('#tlat-comparison-results').show();
                    } else {
                        alert(response.data.message || '<?php _e('Error loading comparison data', 'tutor-lms-advanced-tracking'); ?>');
                    }
                });
            });
            
            // Render comparison data
            function renderComparison(data) {
                // Overview cards
                var cardsHtml = '';
                data.students.forEach(function(student, index) {
                    var scoreColor = student.engagement.total < 30 ? '#ef4444' : (student.engagement.total < 60 ? '#f59e0b' : '#10b981');
                    cardsHtml += '<div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-top: 4px solid ' + studentColors[index].border + ';">';
                    cardsHtml += '<h4 style="margin: 0 0 15px 0;">' + student.name + '</h4>';
                    cardsHtml += '<div style="font-size: 32px; font-weight: bold; color: ' + scoreColor + ';">' + student.engagement.total + '%</div>';
                    cardsHtml += '<div style="color: #6b7280; font-size: 13px;"><?php _e('Engagement Score', 'tutor-lms-advanced-tracking'); ?></div>';
                    cardsHtml += '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 15px; font-size: 13px;">';
                    cardsHtml += '<div><span style="color: #6b7280;"><?php _e('Courses', 'tutor-lms-advanced-tracking'); ?>:</span> ' + student.enrolled_courses + '</div>';
                    cardsHtml += '<div><span style="color: #6b7280;"><?php _e('Completed', 'tutor-lms-advanced-tracking'); ?>:</span> ' + student.completed_courses + '</div>';
                    cardsHtml += '<div><span style="color: #6b7280;"><?php _e('Quizzes', 'tutor-lms-advanced-tracking'); ?>:</span> ' + student.quiz_count + '</div>';
                    cardsHtml += '<div><span style="color: #6b7280;"><?php _e('Avg Score', 'tutor-lms-advanced-tracking'); ?>:</span> ' + student.avg_quiz_score + '%</div>';
                    cardsHtml += '</div>';
                    cardsHtml += '</div>';
                });
                $('#tlat-comparison-cards').html(cardsHtml);
                
                // Radar chart - engagement breakdown
                var radarCtx = document.getElementById('tlat-radar-chart').getContext('2d');
                if (radarChart) radarChart.destroy();
                
                var radarDatasets = data.students.map(function(student, index) {
                    return {
                        label: student.name,
                        data: [
                            Math.round(student.engagement.completion / 0.3 * 100),
                            Math.round(student.engagement.quiz / 0.25 * 100),
                            Math.round(student.engagement.activity / 0.25 * 100),
                            Math.round(student.engagement.time / 0.20 * 100)
                        ],
                        backgroundColor: studentColors[index].bg,
                        borderColor: studentColors[index].border,
                        borderWidth: 2
                    };
                });
                
                radarChart = new Chart(radarCtx, {
                    type: 'radar',
                    data: {
                        labels: ['<?php _e('Completion', 'tutor-lms-advanced-tracking'); ?>', '<?php _e('Quiz Score', 'tutor-lms-advanced-tracking'); ?>', '<?php _e('Activity', 'tutor-lms-advanced-tracking'); ?>', '<?php _e('Time Spent', 'tutor-lms-advanced-tracking'); ?>'],
                        datasets: radarDatasets
                    },
                    options: {
                        scales: {
                            r: {
                                beginAtZero: true,
                                max: 100
                            }
                        }
                    }
                });
                
                // Bar chart - quiz performance
                var barCtx = document.getElementById('tlat-bar-chart').getContext('2d');
                if (barChart) barChart.destroy();
                
                var barDatasets = data.students.map(function(student, index) {
                    return {
                        label: student.name,
                        data: [student.avg_quiz_score, student.best_quiz_score, student.quiz_count * 10],
                        backgroundColor: studentColors[index].border
                    };
                });
                
                barChart = new Chart(barCtx, {
                    type: 'bar',
                    data: {
                        labels: ['<?php _e('Avg Score', 'tutor-lms-advanced-tracking'); ?>', '<?php _e('Best Score', 'tutor-lms-advanced-tracking'); ?>', '<?php _e('Attempts (×10)', 'tutor-lms-advanced-tracking'); ?>'],
                        datasets: barDatasets
                    },
                    options: {
                        scales: {
                            y: {
                                beginAtZero: true,
                                max: 100
                            }
                        }
                    }
                });
                
                // Line chart - activity over time
                var lineCtx = document.getElementById('tlat-line-chart').getContext('2d');
                if (lineChart) lineChart.destroy();
                
                var lineDatasets = data.students.map(function(student, index) {
                    return {
                        label: student.name,
                        data: student.activity_timeline,
                        borderColor: studentColors[index].border,
                        backgroundColor: studentColors[index].bg,
                        fill: false,
                        tension: 0.3
                    };
                });
                
                lineChart = new Chart(lineCtx, {
                    type: 'line',
                    data: {
                        labels: data.timeline_labels,
                        datasets: lineDatasets
                    },
                    options: {
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: '<?php _e('Activities', 'tutor-lms-advanced-tracking'); ?>'
                                }
                            }
                        }
                    }
                });
                
                // Detailed table
                var tableHtml = '<table class="wp-list-table widefat fixed striped">';
                tableHtml += '<thead><tr><th><?php _e('Metric', 'tutor-lms-advanced-tracking'); ?></th>';
                data.students.forEach(function(student, index) {
                    tableHtml += '<th style="border-top: 3px solid ' + studentColors[index].border + ';">' + student.name + '</th>';
                });
                tableHtml += '</tr></thead><tbody>';
                
                var metrics = [
                    { key: 'engagement.total', label: '<?php _e('Overall Engagement', 'tutor-lms-advanced-tracking'); ?>', suffix: '%' },
                    { key: 'enrolled_courses', label: '<?php _e('Enrolled Courses', 'tutor-lms-advanced-tracking'); ?>', suffix: '' },
                    { key: 'completed_courses', label: '<?php _e('Completed Courses', 'tutor-lms-advanced-tracking'); ?>', suffix: '' },
                    { key: 'lesson_completions', label: '<?php _e('Lessons Completed', 'tutor-lms-advanced-tracking'); ?>', suffix: '' },
                    { key: 'quiz_count', label: '<?php _e('Quiz Attempts', 'tutor-lms-advanced-tracking'); ?>', suffix: '' },
                    { key: 'avg_quiz_score', label: '<?php _e('Average Quiz Score', 'tutor-lms-advanced-tracking'); ?>', suffix: '%' },
                    { key: 'best_quiz_score', label: '<?php _e('Best Quiz Score', 'tutor-lms-advanced-tracking'); ?>', suffix: '%' },
                    { key: 'days_since_activity', label: '<?php _e('Days Since Last Activity', 'tutor-lms-advanced-tracking'); ?>', suffix: '' },
                    { key: 'total_time_spent', label: '<?php _e('Total Time Spent', 'tutor-lms-advanced-tracking'); ?>', suffix: '' }
                ];
                
                metrics.forEach(function(metric) {
                    tableHtml += '<tr><td><strong>' + metric.label + '</strong></td>';
                    data.students.forEach(function(student) {
                        var value = metric.key.includes('.') ? 
                            metric.key.split('.').reduce((o, k) => o[k], student) : 
                            student[metric.key];
                        tableHtml += '<td>' + value + metric.suffix + '</td>';
                    });
                    tableHtml += '</tr>';
                });
                
                tableHtml += '</tbody></table>';
                $('#tlat-comparison-table').html(tableHtml);
            }
        });
        </script>
        <?php
    }
    
    /**
     * AJAX: Search students
     */
    public function ajax_search_students() {
        check_ajax_referer('tlat_student_analytics', 'nonce');
        
        if (!current_user_can('manage_tutor')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }
        
        global $wpdb;
        
        $query = sanitize_text_field($_POST['query']);
        $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
        
        $search = '%' . $wpdb->esc_like($query) . '%';
        
        if ($course_id > 0) {
            $students = $wpdb->get_results($wpdb->prepare(
                "SELECT DISTINCT e.user_id as id, u.display_name as name, u.user_email as email
                 FROM {$wpdb->prefix}tutor_enrollments e
                 JOIN {$wpdb->users} u ON e.user_id = u.ID
                 WHERE e.course_id = %d
                 AND (u.display_name LIKE %s OR u.user_email LIKE %s)
                 ORDER BY u.display_name ASC
                 LIMIT 20",
                $course_id, $search, $search
            ));
        } else {
            $students = $wpdb->get_results($wpdb->prepare(
                "SELECT DISTINCT e.user_id as id, u.display_name as name, u.user_email as email
                 FROM {$wpdb->prefix}tutor_enrollments e
                 JOIN {$wpdb->users} u ON e.user_id = u.ID
                 WHERE u.display_name LIKE %s OR u.user_email LIKE %s
                 ORDER BY u.display_name ASC
                 LIMIT 20",
                $search, $search
            ));
        }
        
        wp_send_json_success($students);
    }
    
    /**
     * AJAX: Compare students
     */
    public function ajax_compare_students() {
        check_ajax_referer('tlat_student_analytics', 'nonce');
        
        if (!current_user_can('manage_tutor')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }
        
        global $wpdb;
        
        $user_ids = isset($_POST['user_ids']) ? array_map('intval', $_POST['user_ids']) : array();
        $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
        
        if (count($user_ids) < 2 || count($user_ids) > 5) {
            wp_send_json_error(array('message' => 'Please select 2-5 students to compare'));
        }
        
        $students_data = array();
        
        foreach ($user_ids as $user_id) {
            $user = get_userdata($user_id);
            if (!$user) continue;
            
            $engagement = $this->calculate_engagement_score($user_id, $course_id ?: null);
            
            // Get enrolled courses
            $enrolled_courses = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT course_id) FROM {$wpdb->prefix}tutor_enrollments WHERE user_id = %d",
                $user_id
            ));
            
            // Get completed courses
            $completed_courses = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT comment_post_ID) FROM {$wpdb->prefix}comments 
                 WHERE user_id = %d AND comment_type = 'tutor_course_completed'",
                $user_id
            ));
            
            // Get lesson completions
            $lesson_completions = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}comments 
                 WHERE user_id = %d AND comment_type = 'tutor_lesson_completed'",
                $user_id
            ));
            
            // Get quiz stats
            $quiz_stats = $wpdb->get_row($wpdb->prepare(
                "SELECT COUNT(*) as count, 
                        AVG(earned_marks / total_marks * 100) as avg_score,
                        MAX(earned_marks / total_marks * 100) as best_score
                 FROM {$wpdb->prefix}tutor_quiz_attempts 
                 WHERE user_id = %d AND attempt_status = 'attempt_ended' AND total_marks > 0",
                $user_id
            ));
            
            // Get last activity
            $last_activity = $wpdb->get_var($wpdb->prepare(
                "SELECT MAX(comment_date) FROM {$wpdb->prefix}comments 
                 WHERE user_id = %d AND comment_type IN ('tutor_lesson_completed', 'tutor_course_completed')",
                $user_id
            ));
            
            $days_since_activity = $last_activity ? 
                round((time() - strtotime($last_activity)) / DAY_IN_SECONDS) : 
                999;
            
            // Get total time spent
            $time_spent = 0;
            $meta_keys = $wpdb->get_col($wpdb->prepare(
                "SELECT meta_key FROM {$wpdb->usermeta} 
                 WHERE user_id = %d AND meta_key LIKE %s",
                $user_id, 'tlat_time_spent_%'
            ));
            foreach ($meta_keys as $key) {
                $time_spent += (int) get_user_meta($user_id, $key, true);
            }
            
            // Format time spent
            $hours = floor($time_spent / 3600);
            $minutes = floor(($time_spent % 3600) / 60);
            $time_formatted = $hours > 0 ? "{$hours}h {$minutes}m" : "{$minutes}m";
            
            // Get activity timeline (last 30 days)
            $activity_by_day = array_fill(0, 30, 0);
            $activities = $wpdb->get_results($wpdb->prepare(
                "SELECT DATE(comment_date) as activity_date, COUNT(*) as count
                 FROM {$wpdb->prefix}comments 
                 WHERE user_id = %d 
                 AND comment_type IN ('tutor_lesson_completed', 'tutor_course_completed')
                 AND comment_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                 GROUP BY DATE(comment_date)",
                $user_id
            ));
            
            foreach ($activities as $activity) {
                $day_index = 29 - floor((time() - strtotime($activity->activity_date)) / DAY_IN_SECONDS);
                if ($day_index >= 0 && $day_index < 30) {
                    $activity_by_day[$day_index] = (int) $activity->count;
                }
            }
            
            $students_data[] = array(
                'id' => $user_id,
                'name' => $user->display_name,
                'email' => $user->user_email,
                'engagement' => $engagement,
                'enrolled_courses' => $enrolled_courses,
                'completed_courses' => $completed_courses,
                'lesson_completions' => $lesson_completions,
                'quiz_count' => (int) $quiz_stats->count,
                'avg_quiz_score' => round((float) $quiz_stats->avg_score),
                'best_quiz_score' => round((float) $quiz_stats->best_score),
                'days_since_activity' => $days_since_activity === 999 ? '—' : $days_since_activity,
                'total_time_spent' => $time_formatted,
                'activity_timeline' => $activity_by_day,
            );
        }
        
        // Generate timeline labels (last 30 days)
        $timeline_labels = array();
        for ($i = 29; $i >= 0; $i--) {
            $timeline_labels[] = date('M j', strtotime("-{$i} days"));
        }
        
        wp_send_json_success(array(
            'students' => $students_data,
            'timeline_labels' => $timeline_labels,
        ));
    }
}

// Initialize
new TutorAdvancedTracking_StudentAnalytics();
