<?php
/**
 * Assignment Analytics Class
 * Tracks and displays assignment submission timelines
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class TutorAdvancedTracking_AssignmentAnalytics {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('wp_ajax_tlat_get_assignment_timeline', [$this, 'ajax_get_timeline_data']);
    }

    /**
     * Get the assignments table name
     */
    public static function get_assignments_table() {
        global $wpdb;
        return $wpdb->prefix . 'tutor_assignments';
    }

    /**
     * Get the assignment submissions table name
     */
    public static function get_submissions_table() {
        global $wpdb;
        return $wpdb->prefix . 'tutor_assignment_submissions';
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        register_rest_route('tutor-advanced/v1', '/assignments/timeline', [
            'methods' => 'GET',
            'callback' => [$this, 'get_submission_timeline'],
            'permission_callback' => function() {
                return current_user_can('manage_options') || current_user_can('tutor_instructor');
            }
        ]);

        register_rest_route('tutor-advanced/v1', '/assignments/overview', [
            'methods' => 'GET',
            'callback' => [$this, 'get_assignments_overview'],
            'permission_callback' => function() {
                return current_user_can('manage_options') || current_user_can('tutor_instructor');
            }
        ]);

        register_rest_route('tutor-advanced/v1', '/assignments/by-course', [
            'methods' => 'GET',
            'callback' => [$this, 'get_assignments_by_course'],
            'permission_callback' => function() {
                return current_user_can('manage_options') || current_user_can('tutor_instructor');
            }
        ]);
    }

    /**
     * Get submission timeline data
     */
    public function get_submission_timeline($request) {
        global $wpdb;

        $params = $request->get_params();
        $course_id = isset($params['course_id']) ? intval($params['course_id']) : 0;
        $start_date = isset($params['start_date']) ? sanitize_text_field($params['start_date']) : date('Y-m-d', strtotime('-30 days'));
        $end_date = isset($params['end_date']) ? sanitize_text_field($params['end_date']) : date('Y-m-d');
        $group_by = isset($params['group_by']) ? sanitize_text_field($params['group_by']) : 'day';

        $user_id = get_current_user_id();
        $is_admin = current_user_can('manage_options');

        // Get course IDs for instructor
        $course_ids = [];
        if (!$is_admin && current_user_can('tutor_instructor')) {
            $course_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_type='courses' AND post_status='publish' AND post_author=%d",
                $user_id
            ));
        }

        $submissions_table = self::get_submissions_table();
        $assignments_table = self::get_assignments_table();

        $where_clause = " WHERE s.submitted_at BETWEEN %s AND %s";
        $query_params = [$start_date . ' 00:00:00', $end_date . ' 23:59:59'];

        if ($course_id > 0) {
            $where_clause .= " AND a.course_id = %d";
            $query_params[] = $course_id;
        } elseif (!empty($course_ids)) {
            $where_clause .= " AND a.course_id IN (" . implode(',', array_map('intval', $course_ids)) . ")";
        }

        // Get submission counts by date
        $date_format = $group_by === 'hour' ? '%Y-%m-%d %H:00:00' : ($group_by === 'week' ? '%Y-%u %W' : '%Y-%m-%d');
        
        $query = $wpdb->prepare("
            SELECT 
                DATE_FORMAT(s.submitted_at, '%Y-%m-%d') as date_key,
                COUNT(*) as submission_count,
                COUNT(DISTINCT s.user_id) as unique_submitters,
                AVG(TIMESTAMPDIFF(MINUTE, a.evaluating, s.submitted_at)) as avg_days_to_submit
            FROM {$submissions_table} s
            JOIN {$assignments_table} a ON a.ID = s.assignment_id
            {$where_clause}
            GROUP BY DATE(s.submitted_at)
            ORDER BY s.submitted_at ASC
        ", $query_params);

        $timeline_data = $wpdb->get_results($query);

        // Get status breakdown
        $status_query = $wpdb->prepare("
            SELECT 
                s.status,
                COUNT(*) as count,
                COUNT(DISTINCT s.user_id) as unique_users
            FROM {$submissions_table} s
            JOIN {$assignments_table} a ON a.ID = s.assignment_id
            {$where_clause}
            GROUP BY s.status
        ", $query_params);

        $status_data = $wpdb->get_results($status_query);

        // Get recent submissions
        $recent_query = $wpdb->prepare("
            SELECT 
                s.id,
                s.assignment_id,
                s.user_id,
                s.submitted_at,
                s.status,
                a.course_id,
                p.post_title as assignment_title,
                u.display_name as student_name
            FROM {$submissions_table} s
            JOIN {$assignments_table} a ON a.ID = s.assignment_id
            LEFT JOIN {$wpdb->users} u ON u.ID = s.user_id
            LEFT JOIN {$wpdb->posts} p ON p.ID = a.ID
            {$where_clause}
            ORDER BY s.submitted_at DESC
            LIMIT 20
        ", $query_params);

        $recent_submissions = $wpdb->get_results($recent_query);

        return rest_ensure_response([
            'timeline' => array_map(function($row) {
                return [
                    'date' => $row->date_key,
                    'submissions' => intval($row->submission_count),
                    'unique_submitters' => intval($row->unique_submitters),
                ];
            }, $timeline_data),
            'status_breakdown' => array_map(function($row) {
                return [
                    'status' => $row->status,
                    'count' => intval($row->count),
                    'unique_users' => intval($row->unique_users),
                ];
            }, $status_data),
            'recent_submissions' => array_map(function($row) {
                return [
                    'id' => intval($row->id),
                    'assignment_id' => intval($row->assignment_id),
                    'assignment_title' => $row->assignment_title,
                    'student_id' => intval($row->user_id),
                    'student_name' => $row->student_name,
                    'submitted_at' => $row->submitted_at,
                    'status' => $row->status,
                ];
            }, $recent_submissions),
        ]);
    }

    /**
     * Get assignments overview
     */
    public function get_assignments_overview($request) {
        global $wpdb;

        $user_id = get_current_user_id();
        $is_admin = current_user_can('manage_options');

        $assignments_table = self::get_assignments_table();
        $submissions_table = self::get_submissions_table();

        // Get course IDs for instructor
        $course_ids = [];
        if (!$is_admin && current_user_can('tutor_instructor')) {
            $course_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_type='courses' AND post_status='publish' AND post_author=%d",
                $user_id
            ));
        }

        $course_filter = '';
        $params = [];
        if (!empty($course_ids)) {
            $course_filter = "WHERE course_id IN (" . implode(',', array_map('intval', $course_ids)) . ")";
        }

        // Total assignments
        $total_assignments = $wpdb->get_var("SELECT COUNT(*) FROM {$assignments_table} {$course_filter}");

        // Assignments with submissions
        $with_submissions = $wpdb->get_var("
            SELECT COUNT(DISTINCT a.ID) 
            FROM {$assignments_table} a
            INNER JOIN {$submissions_table} s ON s.assignment_id = a.ID
            {$course_filter}
        ");

        // Total submissions
        $total_submissions = $wpdb->get_var("SELECT COUNT(*) FROM {$submissions_table} s WHERE EXISTS (SELECT 1 FROM {$assignments_table} a WHERE a.ID = s.assignment_id {$course_filter})");

        // Pending reviews
        $pending_reviews = $wpdb->get_var("SELECT COUNT(*) FROM {$submissions_table} s WHERE s.status = 'pending' AND EXISTS (SELECT 1 FROM {$assignments_table} a WHERE a.ID = s.assignment_id {$course_filter})");

        // Pass/Fail stats
        $pass_count = $wpdb->get_var("SELECT COUNT(*) FROM {$submissions_table} s WHERE s.status = 'passed' AND EXISTS (SELECT 1 FROM {$assignments_table} a WHERE a.ID = s.assignment_id {$course_filter})");
        $fail_count = $wpdb->get_var("SELECT COUNT(*) FROM {$submissions_table} s WHERE s.status = 'failed' AND EXISTS (SELECT 1 FROM {$assignments_table} a WHERE a.ID = s.assignment_id {$course_filter})");

        // Average completion time
        $avg_completion = $wpdb->get_var("
            SELECT AVG(submission_count) FROM (
                SELECT COUNT(*) as submission_count
                FROM {$submissions_table} s
                WHERE EXISTS (SELECT 1 FROM {$assignments_table} a WHERE a.ID = s.assignment_id {$course_filter})
                GROUP BY s.user_id, s.assignment_id
            as sub
        ");

        return rest_ensure_response([
            'total_assignments' => intval($total_assignments),
            'assignments_with_submissions' => intval($with_submissions),
            'total_submissions' => intval($total_submissions),
            'pending_reviews' => intval($pending_reviews),
            'pass_count' => intval($pass_count),
            'fail_count' => intval($fail_count),
            'pass_rate' => $total_submissions > 0 ? round(($pass_count / $total_submissions) * 100, 1) : 0,
        ]);
    }

    /**
     * Get assignments grouped by course
     */
    public function get_assignments_by_course($request) {
        global $wpdb;

        $user_id = get_current_user_id();
        $is_admin = current_user_can('manage_options');

        $assignments_table = self::get_assignments_table();
        $submissions_table = self::get_submissions_table();

        $course_where = '';
        if (!$is_admin && current_user_can('tutor_instructor')) {
            $course_where = $wpdb->prepare("AND p.post_author = %d", $user_id);
        }

        $query = $wpdb->prepare("
            SELECT 
                p.ID as course_id,
                p.post_title as course_title,
                COUNT(DISTINCT a.ID) as assignment_count,
                COUNT(s.id) as submission_count,
                COUNT(DISTINCT s.user_id) as unique_submitters,
                AVG(CASE WHEN s.status = 'pending' THEN 1 ELSE 0 END) as pending_rate
            FROM {$wpdb->posts} p
            LEFT JOIN {$assignments_table} a ON a.course_id = p.ID
            LEFT JOIN {$submissions_table} s ON s.assignment_id = a.ID
            WHERE p.post_type = 'courses' 
            AND p.post_status = 'publish'
            {$course_where}
            GROUP BY p.ID
            ORDER BY submission_count DESC
        ", []);

        $results = $wpdb->get_results($query);

        return rest_ensure_response(array_map(function($row) {
            return [
                'course_id' => intval($row->course_id),
                'course_title' => $row->course_title,
                'assignment_count' => intval($row->assignment_count),
                'submission_count' => intval($row->submission_count),
                'unique_submitters' => intval($row->unique_submitters),
                'completion_rate' => $row->assignment_count > 0 ? round(($row->submission_count / $row->assignment_count) * 100, 1) : 0,
            ];
        }, $results));
    }
    
    /**
     * Add admin menu item
     */
    public function add_admin_menu() {
        add_submenu_page(
            'tutor-advanced-stats',
            __('Assignment Timeline', 'tutor-lms-advanced-tracking'),
            __('Assignments Timeline', 'tutor-lms-advanced-tracking'),
            'manage_options',
            'tutor-advanced-stats-assignments',
            [$this, 'render_timeline_page']
        );
    }
    
    /**
     * AJAX handler for timeline data
     */
    public function ajax_get_timeline_data() {
        check_ajax_referer('tutor_advanced_admin_action', 'nonce');
        
        if (!current_user_can('manage_options') && !current_user_can('tutor_instructor')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $course_id = isset($_POST['course_id']) ? absint($_POST['course_id']) : 0;
        $date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : date('Y-m-d', strtotime('-30 days'));
        $date_to = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : date('Y-m-d');
        $group_by = isset($_POST['group_by']) ? sanitize_text_field($_POST['group_by']) : 'day';
        
        $request = new WP_REST_Request('GET');
        $request->set_param('course_id', $course_id);
        $request->set_param('start_date', $date_from);
        $request->set_param('end_date', $date_to);
        $request->set_param('group_by', $group_by);
        
        $response = $this->get_submission_timeline($request);
        
        // Also get overview stats
        $overview_response = $this->get_assignments_overview(new WP_REST_Request('GET'));
        
        $data = $response->get_data();
        $overview = $overview_response->get_data();
        
        wp_send_json([
            'summary' => $data['timeline'],
            'submissions' => $data['recent_submissions'],
            'overview' => $overview
        ]);
    }
    
    /**
     * Render the timeline page
     */
    public function render_timeline_page() {
        global $wpdb;
        
        $user_id = get_current_user_id();
        $is_admin = current_user_can('manage_options');
        
        // Get all courses with assignments
        $course_where = '';
        if (!$is_admin && current_user_can('tutor_instructor')) {
            $course_where = $wpdb->prepare("AND p.post_author = %d", $user_id);
        }
        
        $courses = $wpdb->get_results("
            SELECT DISTINCT p.ID as course_id, p.post_title as course_name
            FROM {$wpdb->posts} p
            JOIN {$this->get_assignments_table()} a ON p.ID = a.course_id
            WHERE p.post_status = 'publish' {$course_where}
            ORDER BY p.post_title
        ");
        
        // Get all assignments
        $assignments = [];
        if (!empty($courses)) {
            $course_ids = wp_list_pluck($courses, 'course_id');
            $assignments = $wpdb->get_results("
                SELECT ID as assignment_id, post_title as title, course_id
                FROM {$this->get_assignments_table()}
                WHERE course_id IN (" . implode(',', array_map('absint', $course_ids)) . ")
                ORDER BY post_title
            ");
        }
        
        ?>
        <div class="tutor-advanced-admin wrap">
            <div class="tutor-admin-header">
                <div>
                    <h1>
                        <?php _e('Assignment Submission Timeline', 'tutor-lms-advanced-tracking'); ?>
                        <span class="version">v<?php echo TUTOR_ADVANCED_TRACKING_VERSION; ?></span>
                    </h1>
                    <p><?php _e('Track and analyze assignment submissions over time', 'tutor-lms-advanced-tracking'); ?></p>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="tutor-admin-filters">
                <div class="filter-group">
                    <label for="filter-course"><?php _e('Course:', 'tutor-lms-advanced-tracking'); ?></label>
                    <select id="filter-course">
                        <option value=""><?php _e('All Courses', 'tutor-lms-advanced-tracking'); ?></option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?php echo $course->course_id; ?>"><?php echo esc_html($course->course_name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="filter-group-by"><?php _e('Group By:', 'tutor-lms-advanced-tracking'); ?></label>
                    <select id="filter-group-by">
                        <option value="hour"><?php _e('Hour', 'tutor-lms-advanced-tracking'); ?></option>
                        <option value="day" selected><?php _e('Day', 'tutor-lms-advanced-tracking'); ?></option>
                        <option value="week"><?php _e('Week', 'tutor-lms-advanced-tracking'); ?></option>
                        <option value="month"><?php _e('Month', 'tutor-lms-advanced-tracking'); ?></option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="filter-date-from"><?php _e('From:', 'tutor-lms-advanced-tracking'); ?></label>
                    <input type="date" id="filter-date-from" value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>">
                </div>
                
                <div class="filter-group">
                    <label for="filter-date-to"><?php _e('To:', 'tutor-lms-advanced-tracking'); ?></label>
                    <input type="date" id="filter-date-to" value="<?php echo date('Y-m-d'); ?>">
                </div>
                
                <button class="tutor-btn tutor-btn-primary" id="apply-filters">
                    <?php _e('Apply Filters', 'tutor-lms-advanced-tracking'); ?>
                </button>
            </div>
            
            <!-- Stats Overview -->
            <div class="stats-grid" id="stats-overview">
                <div class="stat-item">
                    <div class="stat-number" id="stat-total-assignments">-</div>
                    <div class="stat-label"><?php _e('Total Assignments', 'tutor-lms-advanced-tracking'); ?></div>
                </div>
                <div class="stat-item">
                    <div class="stat-number" id="stat-total-submissions">-</div>
                    <div class="stat-label"><?php _e('Total Submissions', 'tutor-lms-advanced-tracking'); ?></div>
                </div>
                <div class="stat-item">
                    <div class="stat-number" id="stat-pending">-</div>
                    <div class="stat-label"><?php _e('Pending Review', 'tutor-lms-advanced-tracking'); ?></div>
                </div>
                <div class="stat-item">
                    <div class="stat-number" id="stat-pass-rate">-</div>
                    <div class="stat-label"><?php _e('Pass Rate', 'tutor-lms-advanced-tracking'); ?></div>
                </div>
            </div>
            
            <!-- Timeline Chart -->
            <div class="tutor-admin-card">
                <h3>
                    <span class="dashicons dashicons-chart-line"></span>
                    <?php _e('Submission Timeline', 'tutor-lms-advanced-tracking'); ?>
                </h3>
                <div class="card-content">
                    <canvas id="timeline-chart" height="300"></canvas>
                </div>
            </div>
            
            <!-- Recent Submissions -->
            <div class="tutor-admin-card">
                <h3>
                    <span class="dashicons dashicons-list-view"></span>
                    <?php _e('Recent Submissions', 'tutor-lms-advanced-tracking'); ?>
                </h3>
                <div class="card-content">
                    <table class="wp-list-table widefat fixed striped" id="recent-submissions-table">
                        <thead>
                            <tr>
                                <th><?php _e('Student', 'tutor-lms-advanced-tracking'); ?></th>
                                <th><?php _e('Assignment', 'tutor-lms-advanced-tracking'); ?></th>
                                <th><?php _e('Submitted', 'tutor-lms-advanced-tracking'); ?></th>
                                <th><?php _e('Status', 'tutor-lms-advanced-tracking'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="recent-submissions-body">
                            <tr>
                                <td colspan="4" style="text-align: center;">
                                    <?php _e('Loading submissions...', 'tutor-lms-advanced-tracking'); ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            var timelineChart = null;
            
            function loadTimelineData() {
                var courseId = $('#filter-course').val();
                var dateFrom = $('#filter-date-from').val();
                var dateTo = $('#filter-date-to').val();
                var groupBy = $('#filter-group-by').val();
                
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'tlat_get_assignment_timeline',
                        nonce: '<?php echo wp_create_nonce('tutor_advanced_admin_action'); ?>',
                        course_id: courseId,
                        date_from: dateFrom,
                        date_to: dateTo,
                        group_by: groupBy
                    },
                    success: function(response) {
                        if (response.overview) {
                            updateOverview(response.overview);
                        }
                        if (response.summary) {
                            updateChart(response.summary);
                        }
                        if (response.submissions) {
                            updateRecentSubmissions(response.submissions);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', error);
                    }
                });
            }
            
            function updateOverview(overview) {
                $('#stat-total-assignments').text(overview.total_assignments || 0);
                $('#stat-total-submissions').text(overview.total_submissions || 0);
                $('#stat-pending').text(overview.pending_reviews || 0);
                $('#stat-pass-rate').text((overview.pass_rate || 0) + '%');
            }
            
            function updateChart(summaryData) {
                var ctx = document.getElementById('timeline-chart').getContext('2d');
                
                var labels = [];
                var submissionData = [];
                
                if (summaryData && summaryData.length > 0) {
                    summaryData.forEach(function(item) {
                        labels.push(item.date);
                        submissionData.push(item.submissions);
                    });
                }
                
                if (timelineChart) {
                    timelineChart.destroy();
                }
                
                timelineChart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: '<?php _e('Submissions', 'tutor-lms-advanced-tracking'); ?>',
                            data: submissionData,
                            borderColor: '#3498db',
                            backgroundColor: 'rgba(52, 152, 219, 0.1)',
                            fill: true,
                            tension: 0.4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'top'
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1
                                }
                            }
                        }
                    }
                });
            }
            
            function updateRecentSubmissions(submissions) {
                var tbody = $('#recent-submissions-body');
                
                if (!submissions || submissions.length === 0) {
                    tbody.html('<tr><td colspan="4" style="text-align: center;"><?php _e('No submissions found', 'tutor-lms-advanced-tracking'); ?></td></tr>');
                    return;
                }
                
                var html = '';
                submissions.forEach(function(sub) {
                    var statusBadge = '';
                    if (sub.status === 'pending') {
                        statusBadge = '<span class="status-badge status-draft"><?php _e('Pending', 'tutor-lms-advanced-tracking'); ?></span>';
                    } else if (sub.status === 'passed') {
                        statusBadge = '<span class="status-badge status-publish"><?php _e('Passed', 'tutor-lms-advanced-tracking'); ?></span>';
                    } else if (sub.status === 'failed') {
                        statusBadge = '<span class="status-badge status-failed"><?php _e('Failed', 'tutor-lms-advanced-tracking'); ?></span>';
                    } else {
                        statusBadge = '<span class="status-badge">' + sub.status + '</span>';
                    }
                    
                    html += '<tr>';
                    html += '<td>' + (sub.student_name || 'Unknown') + '</td>';
                    html += '<td>' + (sub.assignment_title || 'Unknown') + '</td>';
                    html += '<td>' + new Date(sub.submitted_at).toLocaleString() + '</td>';
                    html += '<td>' + statusBadge + '</td>';
                    html += '</tr>';
                });
                
                tbody.html(html);
            }
            
            // Apply filters button
            $('#apply-filters').on('click', loadTimelineData);
            
            // Initial load
            loadTimelineData();
        });
        </script>
        
        <style>
        .tutor-admin-filters {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            padding: 20px;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            margin-bottom: 20px;
            align-items: flex-end;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .filter-group label {
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
        }
        
        .filter-group select,
        .filter-group input {
            min-width: 150px;
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 14px;
        }
        
        #timeline-chart {
            width: 100%;
            max-height: 400px;
        }
        
        .status-badge {
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-badge.status-publish {
            background: #d4edda;
            color: #155724;
        }
        
        .status-badge.status-draft {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-badge.status-failed {
            background: #f8d7da;
            color: #721c24;
        }
        </style>
        <?php
    }
}
