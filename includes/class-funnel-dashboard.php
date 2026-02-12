<?php
/**
 * Funnel Dashboard - Course enrollment to completion funnel with drop-off analysis
 *
 * @package TutorAdvancedTracking
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class TutorAdvancedTracking_Funnel_Dashboard {
    
    /**
     * Funnel stages
     */
    const STAGE_ENROLLED = 'enrolled';
    const STAGE_STARTED = 'started';
    const STAGE_IN_PROGRESS = 'in_progress';
    const STAGE_COMPLETED = 'completed';
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_ajax_tlat_get_funnel_data', array($this, 'ajax_get_funnel_data'));
        add_action('wp_ajax_tlat_get_dropoff_analysis', array($this, 'ajax_get_dropoff_analysis'));
        add_action('admin_menu', array($this, 'add_menu_page'), 20);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }
    
    /**
     * Add submenu page
     */
    public function add_menu_page() {
        add_submenu_page(
            'tutor-advanced-tracking',
            __('Course Funnel', 'tutor-advanced-tracking'),
            __('Funnel Analysis', 'tutor-advanced-tracking'),
            'manage_options',
            'tlat-funnel',
            array($this, 'render_page')
        );
    }
    
    /**
     * Enqueue assets
     */
    public function enqueue_assets($hook) {
        if (strpos($hook, 'tlat-funnel') === false) {
            return;
        }
        
        wp_enqueue_script('chart-js', TLAT_PLUGIN_URL . 'assets/js/chart.min.js', array(), '4.4.1', true);
        wp_enqueue_script(
            'tlat-funnel',
            TLAT_PLUGIN_URL . 'assets/js/funnel-dashboard.js',
            array('jquery', 'chart-js'),
            TLAT_VERSION,
            true
        );
        wp_localize_script('tlat-funnel', 'tlatFunnel', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('tlat_funnel_nonce'),
            'i18n' => array(
                'enrolled' => __('Enrolled', 'tutor-advanced-tracking'),
                'started' => __('Started', 'tutor-advanced-tracking'),
                'inProgress' => __('In Progress', 'tutor-advanced-tracking'),
                'completed' => __('Completed', 'tutor-advanced-tracking'),
                'dropoffRate' => __('Drop-off Rate', 'tutor-advanced-tracking'),
                'conversionRate' => __('Conversion Rate', 'tutor-advanced-tracking'),
            )
        ));
        
        wp_enqueue_style(
            'tlat-funnel',
            TLAT_PLUGIN_URL . 'assets/css/funnel-dashboard.css',
            array(),
            TLAT_VERSION
        );
    }
    
    /**
     * Render the funnel dashboard page
     */
    public function render_page() {
        $courses = $this->get_courses_list();
        ?>
        <div class="wrap tlat-funnel-wrap">
            <h1><?php _e('Course Funnel Analysis', 'tutor-advanced-tracking'); ?></h1>
            
            <div class="tlat-funnel-filters">
                <select id="tlat-course-select" class="tlat-select">
                    <option value="all"><?php _e('All Courses', 'tutor-advanced-tracking'); ?></option>
                    <?php foreach ($courses as $course): ?>
                        <option value="<?php echo esc_attr($course->ID); ?>">
                            <?php echo esc_html($course->post_title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <select id="tlat-period-select" class="tlat-select">
                    <option value="7"><?php _e('Last 7 days', 'tutor-advanced-tracking'); ?></option>
                    <option value="30" selected><?php _e('Last 30 days', 'tutor-advanced-tracking'); ?></option>
                    <option value="90"><?php _e('Last 90 days', 'tutor-advanced-tracking'); ?></option>
                    <option value="365"><?php _e('Last year', 'tutor-advanced-tracking'); ?></option>
                    <option value="all"><?php _e('All time', 'tutor-advanced-tracking'); ?></option>
                </select>
                
                <button id="tlat-refresh-funnel" class="button button-primary">
                    <?php _e('Refresh', 'tutor-advanced-tracking'); ?>
                </button>
            </div>
            
            <div class="tlat-funnel-grid">
                <div class="tlat-funnel-chart-container">
                    <h2><?php _e('Enrollment Funnel', 'tutor-advanced-tracking'); ?></h2>
                    <canvas id="tlat-funnel-chart"></canvas>
                </div>
                
                <div class="tlat-funnel-stats">
                    <h2><?php _e('Funnel Metrics', 'tutor-advanced-tracking'); ?></h2>
                    <div id="tlat-funnel-metrics"></div>
                </div>
            </div>
            
            <div class="tlat-dropoff-section">
                <h2><?php _e('Drop-off Analysis', 'tutor-advanced-tracking'); ?></h2>
                <div id="tlat-dropoff-analysis"></div>
            </div>
            
            <div class="tlat-recommendations-section">
                <h2><?php _e('Recommendations', 'tutor-advanced-tracking'); ?></h2>
                <div id="tlat-recommendations"></div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get list of courses
     */
    private function get_courses_list() {
        return get_posts(array(
            'post_type' => TutorAdvancedTracking_TutorIntegration::get_course_post_type(),
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ));
    }
    
    /**
     * AJAX handler: Get funnel data
     */
    public function ajax_get_funnel_data() {
        check_ajax_referer('tlat_funnel_nonce', 'nonce');
        
        if (!current_user_can('manage_options') && !current_user_can('tutor_instructor')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $course_id = isset($_POST['course_id']) ? sanitize_text_field($_POST['course_id']) : 'all';
        $days = isset($_POST['days']) ? absint($_POST['days']) : 30;
        
        $funnel_data = $this->calculate_funnel_data($course_id, $days);
        
        wp_send_json_success($funnel_data);
    }
    
    /**
     * AJAX handler: Get drop-off analysis
     */
    public function ajax_get_dropoff_analysis() {
        check_ajax_referer('tlat_funnel_nonce', 'nonce');
        
        if (!current_user_can('manage_options') && !current_user_can('tutor_instructor')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $course_id = isset($_POST['course_id']) ? sanitize_text_field($_POST['course_id']) : 'all';
        $days = isset($_POST['days']) ? absint($_POST['days']) : 30;
        
        $dropoff_data = $this->analyze_dropoff($course_id, $days);
        
        wp_send_json_success($dropoff_data);
    }
    
    /**
     * Calculate funnel data for a course or all courses
     *
     * @param string|int $course_id Course ID or 'all'
     * @param int $days Number of days to look back
     * @return array Funnel data
     */
    public function calculate_funnel_data($course_id = 'all', $days = 30) {
        global $wpdb;
        
        $date_filter = $days !== 'all' ? $wpdb->prepare(
            "AND e.post_date >= DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ) : '';
        
        $course_filter = $course_id !== 'all' ? $wpdb->prepare(
            "AND e.post_parent = %d",
            intval($course_id)
        ) : '';
        
        // Get enrolled count (users with enrollment records)
        $enrolled = $this->get_enrolled_count($course_id, $days);
        
        // Get started count (users who viewed at least one lesson)
        $started = $this->get_started_count($course_id, $days);
        
        // Get in_progress count (users with 25-99% progress)
        $in_progress = $this->get_in_progress_count($course_id, $days);
        
        // Get completed count
        $completed = $this->get_completed_count($course_id, $days);
        
        // Calculate rates
        $enrolled_to_started = $enrolled > 0 ? round(($started / $enrolled) * 100, 1) : 0;
        $started_to_progress = $started > 0 ? round(($in_progress / $started) * 100, 1) : 0;
        $progress_to_complete = $in_progress > 0 ? round(($completed / $in_progress) * 100, 1) : 0;
        $overall_conversion = $enrolled > 0 ? round(($completed / $enrolled) * 100, 1) : 0;
        
        return array(
            'stages' => array(
                array(
                    'name' => self::STAGE_ENROLLED,
                    'count' => $enrolled,
                    'percentage' => 100,
                ),
                array(
                    'name' => self::STAGE_STARTED,
                    'count' => $started,
                    'percentage' => $enrolled_to_started,
                    'dropoff' => $enrolled - $started,
                    'dropoff_rate' => $enrolled > 0 ? round((($enrolled - $started) / $enrolled) * 100, 1) : 0,
                ),
                array(
                    'name' => self::STAGE_IN_PROGRESS,
                    'count' => $in_progress,
                    'percentage' => $started > 0 ? round(($in_progress / $enrolled) * 100, 1) : 0,
                    'dropoff' => $started - $in_progress,
                    'dropoff_rate' => $started > 0 ? round((($started - $in_progress) / $started) * 100, 1) : 0,
                ),
                array(
                    'name' => self::STAGE_COMPLETED,
                    'count' => $completed,
                    'percentage' => $enrolled > 0 ? round(($completed / $enrolled) * 100, 1) : 0,
                    'dropoff' => $in_progress - $completed,
                    'dropoff_rate' => $in_progress > 0 ? round((($in_progress - $completed) / $in_progress) * 100, 1) : 0,
                ),
            ),
            'metrics' => array(
                'total_enrolled' => $enrolled,
                'total_completed' => $completed,
                'overall_conversion_rate' => $overall_conversion,
                'avg_time_to_complete' => $this->get_avg_completion_time($course_id, $days),
                'median_progress' => $this->get_median_progress($course_id, $days),
            ),
            'course_id' => $course_id,
            'period_days' => $days,
        );
    }
    
    /**
     * Get enrolled student count
     */
    private function get_enrolled_count($course_id, $days) {
        global $wpdb;
        
        $date_filter = $days !== 'all' && $days > 0 ? $wpdb->prepare(
            "AND post_date >= DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ) : '';
        
        $course_filter = $course_id !== 'all' ? $wpdb->prepare(
            "AND post_parent = %d",
            intval($course_id)
        ) : '';
        
        $sql = "SELECT COUNT(DISTINCT post_author) 
                FROM {$wpdb->posts} 
                WHERE post_type = 'tutor_enrolled'
                AND post_status = 'completed'
                {$course_filter}
                {$date_filter}";
        
        return (int) $wpdb->get_var($sql);
    }
    
    /**
     * Get count of students who started (viewed at least one lesson)
     */
    private function get_started_count($course_id, $days) {
        global $wpdb;
        
        // Check if our events table exists
        $table_name = $wpdb->prefix . 'tlat_events';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
            // Fallback: estimate from Tutor LMS data
            return $this->estimate_started_count($course_id, $days);
        }
        
        $date_filter = $days !== 'all' && $days > 0 ? $wpdb->prepare(
            "AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ) : '';
        
        $course_filter = $course_id !== 'all' ? $wpdb->prepare(
            "AND course_id = %d",
            intval($course_id)
        ) : '';
        
        $sql = "SELECT COUNT(DISTINCT user_id) 
                FROM {$table_name} 
                WHERE event_type = 'lesson_view'
                {$course_filter}
                {$date_filter}";
        
        return (int) $wpdb->get_var($sql);
    }
    
    /**
     * Estimate started count from Tutor LMS data
     */
    private function estimate_started_count($course_id, $days) {
        global $wpdb;
        
        $date_filter = $days !== 'all' && $days > 0 ? $wpdb->prepare(
            "AND e.post_date >= DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ) : '';
        
        $course_filter = $course_id !== 'all' ? $wpdb->prepare(
            "AND e.post_parent = %d",
            intval($course_id)
        ) : '';
        
        // Count enrolled users who have any progress > 0
        $sql = "SELECT COUNT(DISTINCT e.post_author)
                FROM {$wpdb->posts} e
                JOIN {$wpdb->commentmeta} cm ON cm.comment_id IN (
                    SELECT comment_ID FROM {$wpdb->comments} 
                    WHERE user_id = e.post_author 
                    AND comment_type = 'tutor_lesson_completed'
                )
                WHERE e.post_type = 'tutor_enrolled'
                AND e.post_status = 'completed'
                {$course_filter}
                {$date_filter}";
        
        $result = (int) $wpdb->get_var($sql);
        
        // If no data from comments, estimate as 70% of enrolled
        if ($result === 0) {
            $enrolled = $this->get_enrolled_count($course_id, $days);
            return (int) round($enrolled * 0.7);
        }
        
        return $result;
    }
    
    /**
     * Get count of students in progress (25-99%)
     */
    private function get_in_progress_count($course_id, $days) {
        global $wpdb;
        
        $date_filter = $days !== 'all' && $days > 0 ? $wpdb->prepare(
            "AND e.post_date >= DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ) : '';
        
        $course_filter = $course_id !== 'all' ? $wpdb->prepare(
            "AND e.post_parent = %d",
            intval($course_id)
        ) : '';
        
        // Get all enrolled users for the period
        $enrolled_sql = "SELECT DISTINCT e.post_author, e.post_parent
                        FROM {$wpdb->posts} e
                        WHERE e.post_type = 'tutor_enrolled'
                        AND e.post_status = 'completed'
                        {$course_filter}
                        {$date_filter}";
        
        $enrollments = $wpdb->get_results($enrolled_sql);
        
        $in_progress_count = 0;
        foreach ($enrollments as $enrollment) {
            $progress = TutorAdvancedTracking_TutorIntegration::get_user_course_progress(
                $enrollment->post_author,
                $enrollment->post_parent
            );
            
            if ($progress >= 25 && $progress < 100) {
                $in_progress_count++;
            }
        }
        
        return $in_progress_count;
    }
    
    /**
     * Get count of students who completed
     */
    private function get_completed_count($course_id, $days) {
        global $wpdb;
        
        $date_filter = $days !== 'all' && $days > 0 ? $wpdb->prepare(
            "AND comment_date >= DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ) : '';
        
        $course_filter = $course_id !== 'all' ? $wpdb->prepare(
            "AND comment_post_ID = %d",
            intval($course_id)
        ) : '';
        
        $sql = "SELECT COUNT(DISTINCT user_id)
                FROM {$wpdb->comments}
                WHERE comment_type = 'course_completed'
                AND comment_approved = 'approved'
                {$course_filter}
                {$date_filter}";
        
        return (int) $wpdb->get_var($sql);
    }
    
    /**
     * Get average completion time in days
     */
    private function get_avg_completion_time($course_id, $days) {
        global $wpdb;
        
        $course_filter = $course_id !== 'all' ? $wpdb->prepare(
            "AND c.comment_post_ID = %d",
            intval($course_id)
        ) : '';
        
        $date_filter = $days !== 'all' && $days > 0 ? $wpdb->prepare(
            "AND c.comment_date >= DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ) : '';
        
        $sql = "SELECT AVG(DATEDIFF(c.comment_date, e.post_date)) as avg_days
                FROM {$wpdb->comments} c
                JOIN {$wpdb->posts} e ON e.post_author = c.user_id 
                    AND e.post_parent = c.comment_post_ID
                    AND e.post_type = 'tutor_enrolled'
                    AND e.post_status = 'completed'
                WHERE c.comment_type = 'course_completed'
                AND c.comment_approved = 'approved'
                {$course_filter}
                {$date_filter}";
        
        $result = $wpdb->get_var($sql);
        
        return $result !== null ? round(floatval($result), 1) : null;
    }
    
    /**
     * Get median progress percentage
     */
    private function get_median_progress($course_id, $days) {
        global $wpdb;
        
        $date_filter = $days !== 'all' && $days > 0 ? $wpdb->prepare(
            "AND e.post_date >= DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ) : '';
        
        $course_filter = $course_id !== 'all' ? $wpdb->prepare(
            "AND e.post_parent = %d",
            intval($course_id)
        ) : '';
        
        // Get all progress values
        $sql = "SELECT DISTINCT e.post_author, e.post_parent
                FROM {$wpdb->posts} e
                WHERE e.post_type = 'tutor_enrolled'
                AND e.post_status = 'completed'
                {$course_filter}
                {$date_filter}";
        
        $enrollments = $wpdb->get_results($sql);
        
        $progress_values = array();
        foreach ($enrollments as $enrollment) {
            $progress = TutorAdvancedTracking_TutorIntegration::get_user_course_progress(
                $enrollment->post_author,
                $enrollment->post_parent
            );
            $progress_values[] = $progress;
        }
        
        if (empty($progress_values)) {
            return 0;
        }
        
        sort($progress_values);
        $count = count($progress_values);
        $middle = floor($count / 2);
        
        if ($count % 2 === 0) {
            return round(($progress_values[$middle - 1] + $progress_values[$middle]) / 2, 1);
        }
        
        return round($progress_values[$middle], 1);
    }
    
    /**
     * Analyze drop-off points
     */
    public function analyze_dropoff($course_id, $days) {
        global $wpdb;
        
        $analysis = array(
            'critical_lessons' => array(),
            'common_exit_points' => array(),
            'recommendations' => array(),
        );
        
        // Get courses to analyze
        if ($course_id === 'all') {
            $courses = $this->get_courses_list();
        } else {
            $course = get_post($course_id);
            $courses = $course ? array($course) : array();
        }
        
        foreach ($courses as $course) {
            $course_analysis = $this->analyze_course_dropoff($course->ID, $days);
            
            if (!empty($course_analysis['critical_lessons'])) {
                $analysis['critical_lessons'] = array_merge(
                    $analysis['critical_lessons'],
                    $course_analysis['critical_lessons']
                );
            }
            
            if (!empty($course_analysis['exit_points'])) {
                $analysis['common_exit_points'] = array_merge(
                    $analysis['common_exit_points'],
                    $course_analysis['exit_points']
                );
            }
        }
        
        // Sort by drop-off rate
        usort($analysis['critical_lessons'], function($a, $b) {
            return $b['dropoff_rate'] <=> $a['dropoff_rate'];
        });
        
        // Limit to top 10
        $analysis['critical_lessons'] = array_slice($analysis['critical_lessons'], 0, 10);
        
        // Generate recommendations
        $analysis['recommendations'] = $this->generate_recommendations($analysis);
        
        return $analysis;
    }
    
    /**
     * Analyze drop-off for a specific course
     */
    private function analyze_course_dropoff($course_id, $days) {
        global $wpdb;
        
        $result = array(
            'critical_lessons' => array(),
            'exit_points' => array(),
        );
        
        // Get lessons in order
        $lessons = get_posts(array(
            'post_type' => TutorAdvancedTracking_TutorIntegration::get_lesson_post_type(),
            'post_parent' => $course_id,
            'posts_per_page' => -1,
            'orderby' => 'menu_order',
            'order' => 'ASC',
        ));
        
        if (empty($lessons)) {
            return $result;
        }
        
        $prev_viewers = null;
        $lesson_index = 0;
        
        foreach ($lessons as $lesson) {
            $viewers = $this->get_lesson_viewers($lesson->ID, $days);
            
            if ($prev_viewers !== null && $prev_viewers > 0) {
                $dropoff = $prev_viewers - $viewers;
                $dropoff_rate = round(($dropoff / $prev_viewers) * 100, 1);
                
                // Flag as critical if dropoff > 30%
                if ($dropoff_rate > 30) {
                    $result['critical_lessons'][] = array(
                        'course_id' => $course_id,
                        'course_title' => get_the_title($course_id),
                        'lesson_id' => $lesson->ID,
                        'lesson_title' => $lesson->post_title,
                        'lesson_index' => $lesson_index,
                        'viewers' => $viewers,
                        'previous_viewers' => $prev_viewers,
                        'dropoff' => $dropoff,
                        'dropoff_rate' => $dropoff_rate,
                    );
                }
            }
            
            $prev_viewers = $viewers;
            $lesson_index++;
        }
        
        return $result;
    }
    
    /**
     * Get number of unique viewers for a lesson
     */
    private function get_lesson_viewers($lesson_id, $days) {
        global $wpdb;
        
        // Check for our events table first
        $table_name = $wpdb->prefix . 'tlat_events';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
            $date_filter = $days !== 'all' && $days > 0 ? $wpdb->prepare(
                "AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            ) : '';
            
            $sql = $wpdb->prepare(
                "SELECT COUNT(DISTINCT user_id) 
                 FROM {$table_name}
                 WHERE event_type = 'lesson_view'
                 AND lesson_id = %d
                 {$date_filter}",
                $lesson_id
            );
            
            return (int) $wpdb->get_var($sql);
        }
        
        // Fallback: use Tutor LMS lesson completion data
        $date_filter = $days !== 'all' && $days > 0 ? $wpdb->prepare(
            "AND comment_date >= DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ) : '';
        
        $sql = $wpdb->prepare(
            "SELECT COUNT(DISTINCT user_id)
             FROM {$wpdb->comments}
             WHERE comment_post_ID = %d
             AND comment_type = 'tutor_lesson_completed'
             {$date_filter}",
            $lesson_id
        );
        
        return (int) $wpdb->get_var($sql);
    }
    
    /**
     * Generate actionable recommendations
     */
    private function generate_recommendations($analysis) {
        $recommendations = array();
        
        // Check for early drop-off
        $early_dropoffs = array_filter($analysis['critical_lessons'], function($l) {
            return $l['lesson_index'] <= 2;
        });
        
        if (!empty($early_dropoffs)) {
            $recommendations[] = array(
                'type' => 'warning',
                'title' => __('High early drop-off detected', 'tutor-advanced-tracking'),
                'message' => sprintf(
                    __('%d course(s) show high drop-off in the first 3 lessons. Consider improving onboarding content or reducing initial lesson complexity.', 'tutor-advanced-tracking'),
                    count($early_dropoffs)
                ),
                'action' => __('Review first lessons', 'tutor-advanced-tracking'),
            );
        }
        
        // Check for consistent high drop-off
        if (count($analysis['critical_lessons']) > 5) {
            $recommendations[] = array(
                'type' => 'info',
                'title' => __('Multiple high drop-off points', 'tutor-advanced-tracking'),
                'message' => __('Several lessons show significant student drop-off. Consider reviewing lesson length, difficulty, and engagement elements.', 'tutor-advanced-tracking'),
                'action' => __('Audit course structure', 'tutor-advanced-tracking'),
            );
        }
        
        // No critical issues
        if (empty($recommendations)) {
            $recommendations[] = array(
                'type' => 'success',
                'title' => __('Funnel looks healthy', 'tutor-advanced-tracking'),
                'message' => __('No critical drop-off points detected. Keep monitoring for changes.', 'tutor-advanced-tracking'),
                'action' => null,
            );
        }
        
        return $recommendations;
    }
}
