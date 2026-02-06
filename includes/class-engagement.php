<?php
// Prevent direct access
if (!defined('ABSPATH')) { exit; }

class TutorAdvancedTracking_Engagement {
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_rest_routes']);
    }

    public function register_rest_routes() {
        register_rest_route('tutor-advanced/v1', '/engagement', [
            'methods'  => 'GET',
            'callback' => [$this, 'get_engagement_overview'],
            'permission_callback' => function() {
                return current_user_can('manage_options') || current_user_can('tutor_instructor');
            }
        ]);
    }

    public function get_engagement_overview($request) {
        global $wpdb;
        $user_id = get_current_user_id();
        $is_admin = current_user_can('manage_options');

        $instructor_course_ids = [];
        if (!$is_admin && current_user_can('tutor_instructor')) {
            $instructor_course_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} p WHERE p.post_type='courses' AND p.post_status='publish' AND p.post_author=%d",
                $user_id
            ));
        }

        $course_filter_sql = '';
        if (!empty($instructor_course_ids)) {
            $in  = implode(',', array_map('intval', $instructor_course_ids));
            $course_filter_sql = " AND e.course_id IN ($in) ";
        }

        $enroll = $wpdb->prefix . 'tutor_enrollments';
        $act    = $wpdb->prefix . 'tutor_lesson_activities';

        // Last active buckets (simple overview)
        $lastActive = $wpdb->get_row(
            "SELECT 
                SUM(CASE WHEN a.activity_time >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS active_7d,
                SUM(CASE WHEN a.activity_time >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS active_30d
             FROM {$act} a"
        );

        // Engagement by weekday/hour heatmap
        $heatmap = $wpdb->get_results(
            "SELECT DAYOFWEEK(a.activity_time) AS dow, HOUR(a.activity_time) AS hr, COUNT(*) AS cnt
               FROM {$act} a
               JOIN {$enroll} e ON e.user_id = a.user_id AND e.course_id = a.course_id
              WHERE 1=1 {$course_filter_sql}
              GROUP BY dow, hr"
        );

        return rest_ensure_response([
            'active_7d' => (int)($lastActive->active_7d ?? 0),
            'active_30d' => (int)($lastActive->active_30d ?? 0),
            'heatmap' => array_map(function($r){ return ['dow'=>(int)$r->dow,'hour'=>(int)$r->hr,'count'=>(int)$r->cnt]; }, $heatmap)
        ]);
    }
}
