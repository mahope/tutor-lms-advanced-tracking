<?php
// Prevent direct access
if (!defined('ABSPATH')) { exit; }

class TutorAdvancedTracking_CohortAnalytics {
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_rest_routes']);
    }

    public function register_rest_routes() {
        register_rest_route('tutor-advanced/v1', '/cohorts', [
            'methods'  => 'GET',
            'callback' => [$this, 'get_cohort_summary'],
            'permission_callback' => function() {
                return current_user_can('manage_options') || current_user_can('tutor_instructor');
            }
        ]);
    }

    /**
     * Return cohort retention and completion metrics by enrollment month
     */
    public function get_cohort_summary( $request ) {
        global $wpdb;
        $user_id = get_current_user_id();
        $is_admin = current_user_can('manage_options');

        // Optional filter: instructor scope
        $instructor_course_ids = [];
        if (!$is_admin && current_user_can('tutor_instructor')) {
            $instructor_course_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} p WHERE p.post_type='courses' AND p.post_status='publish' AND p.post_author=%d",
                $user_id
            ));
            if (empty($instructor_course_ids)) {
                return rest_ensure_response(['cohorts' => []]);
            }
        }

        $course_filter_sql = '';
        if (!empty($instructor_course_ids)) {
            $in  = implode(',', array_map('intval', $instructor_course_ids));
            $course_filter_sql = " AND e.course_id IN ($in) ";
        }

        // Build cohorts by enrollment month
        $table_enroll = $wpdb->prefix . 'tutor_enrollments';
        $table_activities = $wpdb->prefix . 'tutor_lesson_activities';

        // Aggregate enrollments per month (last 24 months)
        $rows = $wpdb->get_results(
            "SELECT DATE_FORMAT(e.enrollment_date, '%Y-%m-01') AS cohort_month, COUNT(*) AS enrolled
               FROM {$table_enroll} e
              WHERE 1=1 {$course_filter_sql}
              GROUP BY cohort_month
              ORDER BY cohort_month DESC
              LIMIT 24"
        );

        $cohorts = [];
        foreach ($rows as $r) {
            $cohorts[$r->cohort_month] = [
                'month' => $r->cohort_month,
                'enrolled' => (int)$r->enrolled,
                'active_after_7d' => 0,
                'active_after_30d' => 0,
                'completed_any' => 0,
                'avg_time_to_first_lesson_min' => null,
            ];
        }

        if (empty($cohorts)) {
            return rest_ensure_response(['cohorts' => []]);
        }

        $months = array_keys($cohorts);
        $minMonth = min($months);
        $maxMonth = max($months);

        // Active after 7/30 days: users with any lesson activity within window after enrollment
        $rowsActive = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE_FORMAT(e.enrollment_date, '%%Y-%%m-01') AS cohort_month,
                    SUM(CASE WHEN a.activity_time BETWEEN e.enrollment_date AND DATE_ADD(e.enrollment_date, INTERVAL 7 DAY) THEN 1 ELSE 0 END > 0) AS active7,
                    SUM(CASE WHEN a.activity_time BETWEEN e.enrollment_date AND DATE_ADD(e.enrollment_date, INTERVAL 30 DAY) THEN 1 ELSE 0 END > 0) AS active30
               FROM {$table_enroll} e
               LEFT JOIN {$table_activities} a ON a.user_id = e.user_id AND a.course_id = e.course_id
              WHERE DATE_FORMAT(e.enrollment_date, '%%Y-%%m-01') BETWEEN %s AND %s {$course_filter_sql}
              GROUP BY cohort_month",
            $minMonth, $maxMonth
        ));
        foreach ($rowsActive as $r) {
            if (isset($cohorts[$r->cohort_month])) {
                $cohorts[$r->cohort_month]['active_after_7d'] = (int)$r->active7;
                $cohorts[$r->cohort_month]['active_after_30d'] = (int)$r->active30;
            }
        }

        // Completed any course (heuristic: progress 100 if table exists)
        $table_progress = $wpdb->prefix . 'tutor_course_progress';
        if ($this->table_exists($table_progress)) {
            $rowsCompleted = $wpdb->get_results($wpdb->prepare(
                "SELECT DATE_FORMAT(e.enrollment_date, '%%Y-%%m-01') AS cohort_month, COUNT(*) AS completed
                   FROM {$table_enroll} e
                   JOIN {$table_progress} cp ON cp.user_id = e.user_id AND cp.course_id = e.course_id AND cp.progress = 100
                  WHERE DATE_FORMAT(e.enrollment_date, '%%Y-%%m-01') BETWEEN %s AND %s {$course_filter_sql}
                  GROUP BY cohort_month",
                $minMonth, $maxMonth
            ));
            foreach ($rowsCompleted as $r) {
                if (isset($cohorts[$r->cohort_month])) {
                    $cohorts[$r->cohort_month]['completed_any'] = (int)$r->completed;
                }
            }
        }

        // Time to first lesson activity (minutes)
        $rowsTTF = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE_FORMAT(e.enrollment_date, '%%Y-%%m-01') AS cohort_month,
                    AVG(TIMESTAMPDIFF(MINUTE, e.enrollment_date, MIN(a.activity_time))) AS minutes
               FROM {$table_enroll} e
               JOIN {$table_activities} a ON a.user_id = e.user_id AND a.course_id = e.course_id
              WHERE DATE_FORMAT(e.enrollment_date, '%%Y-%%m-01') BETWEEN %s AND %s {$course_filter_sql}
              GROUP BY cohort_month",
            $minMonth, $maxMonth
        ));
        foreach ($rowsTTF as $r) {
            if (isset($cohorts[$r->cohort_month])) {
                $cohorts[$r->cohort_month]['avg_time_to_first_lesson_min'] = is_null($r->minutes) ? null : (float)round($r->minutes, 1);
            }
        }

        return rest_ensure_response(['cohorts' => array_values($cohorts)]);
    }

    private function table_exists($table) {
        global $wpdb;
        $like = $wpdb->esc_like($table);
        $found = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $like));
        return $found === $table;
    }
}
