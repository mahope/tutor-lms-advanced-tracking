<?php
/**
 * Interactive Charts and Visualizations for Advanced Tutor LMS Stats Dashboard
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class TutorAdvancedTracking_Charts {
    
    /**
     * Constructor
     */
    public function __construct() {
        // AJAX handlers for chart data
        add_action('wp_ajax_tutor_advanced_chart_data', array($this, 'handle_chart_data_ajax'));
        
        // Enqueue Chart.js
        add_action('wp_enqueue_scripts', array($this, 'enqueue_chart_scripts'));
        
        // Add charts to dashboard and course views
        add_action('tutor_advanced_tracking_dashboard_stats', array($this, 'add_dashboard_charts'));
        add_action('tutor_advanced_tracking_course_details_stats', array($this, 'add_course_charts'));
    }
    
    /**
     * Enqueue Chart.js scripts
     */
    public function enqueue_chart_scripts() {
        if (!$this->is_plugin_page()) {
            return;
        }
        
        // Chart.js from CDN
        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.js',
            array(),
            '4.4.0',
            true
        );
        
        // Our chart implementation
        wp_enqueue_script(
            'tutor-advanced-charts',
            TUTOR_ADVANCED_TRACKING_PLUGIN_URL . 'assets/js/charts.js',
            array('jquery', 'chartjs'),
            TUTOR_ADVANCED_TRACKING_VERSION,
            true
        );
        
        // Localize script for AJAX
        wp_localize_script('tutor-advanced-charts', 'tutorAdvancedCharts', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('tutor_advanced_charts_' . get_current_user_id()),
            'strings' => array(
                'loading' => __('Loading chart data...', 'tutor-lms-advanced-tracking'),
                'error' => __('Error loading chart data', 'tutor-lms-advanced-tracking'),
                'noData' => __('No data available', 'tutor-lms-advanced-tracking'),
                'noDataHint' => __('Data will appear once students start taking courses.', 'tutor-lms-advanced-tracking'),
                'retry' => __('Retry', 'tutor-lms-advanced-tracking')
            )
        ));
    }
    
    /**
     * Check if current page is plugin page
     */
    private function is_plugin_page() {
        global $post;
        return is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'tutor_advanced_stats');
    }
    
    /**
     * Handle chart data AJAX requests
     */
    public function handle_chart_data_ajax() {
        // Verify nonce and capabilities
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'tutor_advanced_charts_' . get_current_user_id())) {
            wp_send_json_error(__('Security check failed', 'tutor-lms-advanced-tracking'));
        }
        
        if (!current_user_can('manage_options') && !current_user_can('tutor_instructor')) {
            wp_send_json_error(__('Insufficient permissions', 'tutor-lms-advanced-tracking'));
        }
        
        // Rate limiting check
        if (!$this->check_chart_rate_limit()) {
            wp_send_json_error(__('Too many requests. Please wait before loading more charts.', 'tutor-lms-advanced-tracking'));
        }
        
        $chart_type = sanitize_text_field($_POST['chart_type'] ?? '');
        $course_id = intval($_POST['course_id'] ?? 0);
        $time_period = sanitize_text_field($_POST['time_period'] ?? '30');
        
        switch ($chart_type) {
            case 'enrollment_trend':
                $data = $this->get_enrollment_trend_data($time_period);
                break;
            case 'quiz_performance':
                $data = $this->get_quiz_performance_data($course_id, $time_period);
                break;
            case 'course_completion':
                $data = $this->get_course_completion_data($course_id);
                break;
            case 'student_activity':
                $data = $this->get_student_activity_data($course_id, $time_period);
                break;
            case 'progress_over_time':
                $data = $this->get_progress_over_time_data($course_id, $time_period);
                break;
            case 'engagement_heatmap':
                $data = $this->get_engagement_heatmap_data($course_id);
                break;
            default:
                wp_send_json_error(__('Invalid chart type', 'tutor-lms-advanced-tracking'));
        }
        
        wp_send_json_success($data);
    }
    
    /**
     * Add dashboard charts
     */
    public function add_dashboard_charts() {
        ?>
        <div class="charts-section">
            <h3><?php _e('Analytics Overview', 'tutor-lms-advanced-tracking'); ?></h3>
            
            <div class="chart-controls">
                <select id="dashboard-chart-period">
                    <option value="7"><?php _e('Last 7 days', 'tutor-lms-advanced-tracking'); ?></option>
                    <option value="30" selected><?php _e('Last 30 days', 'tutor-lms-advanced-tracking'); ?></option>
                    <option value="90"><?php _e('Last 90 days', 'tutor-lms-advanced-tracking'); ?></option>
                </select>
            </div>
            
            <div class="charts-grid">
                <div class="chart-container">
                    <h4><?php _e('Enrollment Trend', 'tutor-lms-advanced-tracking'); ?></h4>
                    <canvas id="enrollment-trend-chart" width="400" height="200"></canvas>
                </div>
                
                <div class="chart-container">
                    <h4><?php _e('Course Completions', 'tutor-lms-advanced-tracking'); ?></h4>
                    <canvas id="completion-chart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Add course-specific charts
     */
    public function add_course_charts($course_id) {
        ?>
        <div class="charts-section">
            <h3><?php _e('Course Analytics', 'tutor-lms-advanced-tracking'); ?></h3>
            
            <div class="chart-controls">
                <select id="course-chart-period" data-course-id="<?php echo intval($course_id); ?>">
                    <option value="7"><?php _e('Last 7 days', 'tutor-lms-advanced-tracking'); ?></option>
                    <option value="30" selected><?php _e('Last 30 days', 'tutor-lms-advanced-tracking'); ?></option>
                    <option value="90"><?php _e('Last 90 days', 'tutor-lms-advanced-tracking'); ?></option>
                </select>
            </div>
            
            <div class="charts-grid">
                <div class="chart-container">
                    <h4><?php _e('Student Progress Over Time', 'tutor-lms-advanced-tracking'); ?></h4>
                    <canvas id="progress-chart" width="400" height="200"></canvas>
                </div>
                
                <div class="chart-container">
                    <h4><?php _e('Quiz Performance Distribution', 'tutor-lms-advanced-tracking'); ?></h4>
                    <canvas id="quiz-performance-chart" width="400" height="200"></canvas>
                </div>
                
                <div class="chart-container">
                    <h4><?php _e('Student Activity Heatmap', 'tutor-lms-advanced-tracking'); ?></h4>
                    <canvas id="activity-heatmap-chart" width="400" height="200"></canvas>
                </div>
                
                <div class="chart-container">
                    <h4><?php _e('Lesson Completion Funnel', 'tutor-lms-advanced-tracking'); ?></h4>
                    <canvas id="completion-funnel-chart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get enrollment trend data
     */
    private function get_enrollment_trend_data($days = 30) {
        global $wpdb;
        
        $end_date = current_time('Y-m-d');
        $start_date = date('Y-m-d', strtotime("-{$days} days"));
        
        $data = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(enrollment_date) as date, COUNT(*) as enrollments
             FROM {$wpdb->prefix}tutor_enrollments
             WHERE DATE(enrollment_date) BETWEEN %s AND %s
             GROUP BY DATE(enrollment_date)
             ORDER BY date ASC",
            $start_date, $end_date
        ));
        
        // Fill in missing dates with zero values
        $labels = array();
        $values = array();
        $current_date = strtotime($start_date);
        $end_timestamp = strtotime($end_date);
        
        while ($current_date <= $end_timestamp) {
            $date_str = date('Y-m-d', $current_date);
            $labels[] = date('M j', $current_date);
            
            $found = false;
            foreach ($data as $row) {
                if ($row->date === $date_str) {
                    $values[] = intval($row->enrollments);
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                $values[] = 0;
            }
            
            $current_date = strtotime('+1 day', $current_date);
        }
        
        return array(
            'type' => 'line',
            'data' => array(
                'labels' => $labels,
                'datasets' => array(
                    array(
                        'label' => __('New Enrollments', 'tutor-lms-advanced-tracking'),
                        'data' => $values,
                        'borderColor' => '#007cba',
                        'backgroundColor' => 'rgba(0, 124, 186, 0.1)',
                        'tension' => 0.4,
                        'fill' => true
                    )
                )
            ),
            'options' => array(
                'responsive' => true,
                'scales' => array(
                    'y' => array(
                        'beginAtZero' => true,
                        'ticks' => array(
                            'stepSize' => 1
                        )
                    )
                )
            )
        );
    }
    
    /**
     * Get quiz performance data
     */
    private function get_quiz_performance_data($course_id = 0, $days = 30) {
        global $wpdb;
        
        $where_clause = '';
        $params = array();
        
        if ($course_id) {
            $where_clause = 'AND p.post_parent = %d';
            $params[] = $course_id;
        }
        
        $end_date = current_time('Y-m-d');
        $start_date = date('Y-m-d', strtotime("-{$days} days"));
        $params = array_merge(array($start_date, $end_date), $params);
        
        $data = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                CASE 
                    WHEN (qa.earned_marks / qa.total_marks * 100) >= 90 THEN '90-100%'
                    WHEN (qa.earned_marks / qa.total_marks * 100) >= 80 THEN '80-89%'
                    WHEN (qa.earned_marks / qa.total_marks * 100) >= 70 THEN '70-79%'
                    WHEN (qa.earned_marks / qa.total_marks * 100) >= 60 THEN '60-69%'
                    ELSE 'Below 60%'
                END as score_range,
                COUNT(*) as count
             FROM {$wpdb->prefix}tutor_quiz_attempts qa
             JOIN {$wpdb->posts} p ON qa.quiz_id = p.ID
             WHERE DATE(qa.attempt_started_at) BETWEEN %s AND %s
             AND qa.attempt_status = 'attempt_ended'
             AND qa.total_marks > 0
             {$where_clause}
             GROUP BY score_range
             ORDER BY 
                CASE score_range
                    WHEN '90-100%' THEN 1
                    WHEN '80-89%' THEN 2
                    WHEN '70-79%' THEN 3
                    WHEN '60-69%' THEN 4
                    ELSE 5
                END",
            $params
        ));
        
        $labels = array();
        $values = array();
        $colors = array('#28a745', '#20c997', '#ffc107', '#fd7e14', '#dc3545');
        
        foreach ($data as $row) {
            $labels[] = $row->score_range;
            $values[] = intval($row->count);
        }
        
        return array(
            'type' => 'doughnut',
            'data' => array(
                'labels' => $labels,
                'datasets' => array(
                    array(
                        'data' => $values,
                        'backgroundColor' => array_slice($colors, 0, count($values)),
                        'borderWidth' => 2,
                        'borderColor' => '#fff'
                    )
                )
            ),
            'options' => array(
                'responsive' => true,
                'plugins' => array(
                    'legend' => array(
                        'position' => 'bottom'
                    )
                )
            )
        );
    }
    
    /**
     * Get course completion data
     */
    private function get_course_completion_data($course_id) {
        global $wpdb;
        
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_enrolled,
                SUM(CASE WHEN completion_date IS NOT NULL OR is_completed = 1 THEN 1 ELSE 0 END) as completed
             FROM {$wpdb->prefix}tutor_enrollments
             WHERE course_id = %d",
            $course_id
        ));
        
        $completed = intval($stats->completed ?? 0);
        $in_progress = intval($stats->total_enrolled ?? 0) - $completed;
        
        return array(
            'type' => 'pie',
            'data' => array(
                'labels' => array(
                    __('Completed', 'tutor-lms-advanced-tracking'),
                    __('In Progress', 'tutor-lms-advanced-tracking')
                ),
                'datasets' => array(
                    array(
                        'data' => array($completed, $in_progress),
                        'backgroundColor' => array('#28a745', '#ffc107'),
                        'borderWidth' => 2,
                        'borderColor' => '#fff'
                    )
                )
            ),
            'options' => array(
                'responsive' => true,
                'plugins' => array(
                    'legend' => array(
                        'position' => 'bottom'
                    )
                )
            )
        );
    }
    
    /**
     * Get student activity data
     */
    private function get_student_activity_data($course_id, $days = 30) {
        global $wpdb;
        
        $end_date = current_time('Y-m-d');
        $start_date = date('Y-m-d', strtotime("-{$days} days"));
        
        // Get activity by day of week
        $data = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DAYNAME(qa.attempt_started_at) as day_name,
                DAYOFWEEK(qa.attempt_started_at) as day_num,
                COUNT(*) as activity_count
             FROM {$wpdb->prefix}tutor_quiz_attempts qa
             JOIN {$wpdb->posts} p ON qa.quiz_id = p.ID
             WHERE p.post_parent = %d
             AND DATE(qa.attempt_started_at) BETWEEN %s AND %s
             AND qa.attempt_status = 'attempt_ended'
             GROUP BY day_num, day_name
             ORDER BY day_num",
            $course_id, $start_date, $end_date
        ));
        
        $days_of_week = array('Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday');
        $labels = array();
        $values = array();
        
        foreach ($days_of_week as $day) {
            $labels[] = substr($day, 0, 3); // Mon, Tue, etc.
            $found = false;
            
            foreach ($data as $row) {
                if ($row->day_name === $day) {
                    $values[] = intval($row->activity_count);
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                $values[] = 0;
            }
        }
        
        return array(
            'type' => 'bar',
            'data' => array(
                'labels' => $labels,
                'datasets' => array(
                    array(
                        'label' => __('Quiz Attempts', 'tutor-lms-advanced-tracking'),
                        'data' => $values,
                        'backgroundColor' => '#007cba',
                        'borderRadius' => 4
                    )
                )
            ),
            'options' => array(
                'responsive' => true,
                'scales' => array(
                    'y' => array(
                        'beginAtZero' => true,
                        'ticks' => array(
                            'stepSize' => 1
                        )
                    )
                )
            )
        );
    }
    
    /**
     * Get progress over time data
     */
    private function get_progress_over_time_data($course_id, $days = 30) {
        global $wpdb;
        
        $end_date = current_time('Y-m-d');
        $start_date = date('Y-m-d', strtotime("-{$days} days"));
        
        // Get average progress over time (using enrollment dates as proxy)
        $data = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DATE(enrollment_date) as date,
                COUNT(*) as new_enrollments,
                (SELECT COUNT(*) FROM {$wpdb->prefix}tutor_enrollments e2 
                 WHERE e2.course_id = %d AND DATE(e2.enrollment_date) <= DATE(e1.enrollment_date)) as cumulative_enrollments
             FROM {$wpdb->prefix}tutor_enrollments e1
             WHERE course_id = %d
             AND DATE(enrollment_date) BETWEEN %s AND %s
             GROUP BY DATE(enrollment_date)
             ORDER BY date ASC",
            $course_id, $course_id, $start_date, $end_date
        ));
        
        $labels = array();
        $enrollments = array();
        $cumulative = array();
        
        foreach ($data as $row) {
            $labels[] = date('M j', strtotime($row->date));
            $enrollments[] = intval($row->new_enrollments);
            $cumulative[] = intval($row->cumulative_enrollments);
        }
        
        return array(
            'type' => 'line',
            'data' => array(
                'labels' => $labels,
                'datasets' => array(
                    array(
                        'label' => __('New Enrollments', 'tutor-lms-advanced-tracking'),
                        'data' => $enrollments,
                        'borderColor' => '#007cba',
                        'backgroundColor' => 'rgba(0, 124, 186, 0.1)',
                        'yAxisID' => 'y'
                    ),
                    array(
                        'label' => __('Total Enrolled', 'tutor-lms-advanced-tracking'),
                        'data' => $cumulative,
                        'borderColor' => '#28a745',
                        'backgroundColor' => 'rgba(40, 167, 69, 0.1)',
                        'yAxisID' => 'y1'
                    )
                )
            ),
            'options' => array(
                'responsive' => true,
                'scales' => array(
                    'y' => array(
                        'type' => 'linear',
                        'display' => true,
                        'position' => 'left',
                        'beginAtZero' => true
                    ),
                    'y1' => array(
                        'type' => 'linear',
                        'display' => true,
                        'position' => 'right',
                        'beginAtZero' => true,
                        'grid' => array(
                            'drawOnChartArea' => false
                        )
                    )
                )
            )
        );
    }
    
    /**
     * Get engagement heatmap data
     */
    private function get_engagement_heatmap_data($course_id) {
        global $wpdb;
        
        // Get activity by hour and day of week
        $data = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DAYOFWEEK(qa.attempt_started_at) as day_num,
                HOUR(qa.attempt_started_at) as hour_num,
                COUNT(*) as activity_count
             FROM {$wpdb->prefix}tutor_quiz_attempts qa
             JOIN {$wpdb->posts} p ON qa.quiz_id = p.ID
             WHERE p.post_parent = %d
             AND qa.attempt_status = 'attempt_ended'
             AND qa.attempt_started_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY day_num, hour_num
             ORDER BY day_num, hour_num",
            $course_id
        ));
        
        // Convert to matrix format for heatmap
        $matrix = array();
        $max_activity = 0;
        
        // Initialize matrix
        for ($day = 1; $day <= 7; $day++) {
            for ($hour = 0; $hour < 24; $hour++) {
                $matrix[$day][$hour] = 0;
            }
        }
        
        // Fill matrix with data
        foreach ($data as $row) {
            $activity = intval($row->activity_count);
            $matrix[$row->day_num][$row->hour_num] = $activity;
            $max_activity = max($max_activity, $activity);
        }
        
        // Convert to Chart.js format (simplified heatmap using bar chart)
        $labels = array();
        $values = array();
        $days = array('Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat');
        
        for ($day = 1; $day <= 7; $day++) {
            $day_total = array_sum($matrix[$day]);
            $labels[] = $days[$day - 1];
            $values[] = $day_total;
        }
        
        return array(
            'type' => 'bar',
            'data' => array(
                'labels' => $labels,
                'datasets' => array(
                    array(
                        'label' => __('Activity Level', 'tutor-lms-advanced-tracking'),
                        'data' => $values,
                        'backgroundColor' => array(
                            '#ff9999', '#ffcc99', '#ffff99', 
                            '#ccff99', '#99ffcc', '#99ccff', '#cc99ff'
                        ),
                        'borderRadius' => 4
                    )
                )
            ),
            'options' => array(
                'responsive' => true,
                'scales' => array(
                    'y' => array(
                        'beginAtZero' => true,
                        'title' => array(
                            'display' => true,
                            'text' => __('Quiz Attempts', 'tutor-lms-advanced-tracking')
                        )
                    )
                )
            )
        );
    }
    
    /**
     * Rate limiting check for chart requests
     */
    private function check_chart_rate_limit() {
        $user_id = get_current_user_id();
        $transient_key = 'tutor_charts_rate_limit_' . $user_id;
        $current_requests = get_transient($transient_key);
        
        if ($current_requests === false) {
            set_transient($transient_key, 1, 60); // 1 minute
            return true;
        }
        
        if ($current_requests >= 20) { // Max 20 chart requests per minute
            return false;
        }
        
        set_transient($transient_key, $current_requests + 1, 60);
        return true;
    }
}