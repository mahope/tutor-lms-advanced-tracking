<?php
/**
 * Video Analytics Class
 *
 * Provides video watch completion tracking including:
 * - Play/pause/ended/timeupdate event tracking via JS
 * - Per-video completion rates and watch heatmaps
 * - Drop-off point analysis
 * - Per-student video engagement
 * - Support for YouTube, Vimeo and HTML5 video
 *
 * @package TutorAdvancedTracking
 * @since 1.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class TutorAdvancedTracking_VideoAnalytics {

    /** @var string DB table name (set in constructor) */
    private $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'tlat_video_progress';

        // Admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'), 27);

        // REST API
        add_action('rest_api_init', array($this, 'register_rest_routes'));

        // AJAX handlers (admin)
        add_action('wp_ajax_tlat_get_video_details', array($this, 'ajax_get_video_details'));
        add_action('wp_ajax_tlat_get_video_students', array($this, 'ajax_get_video_students'));

        // AJAX handler for progress tracking (frontend, logged-in users)
        add_action('wp_ajax_tlat_track_video_progress', array($this, 'ajax_track_video_progress'));

        // Enqueue frontend tracker on Tutor lesson pages
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_tracker'));

        // Enqueue admin scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    // -------------------------------------------------------------------------
    // Admin Menu
    // -------------------------------------------------------------------------

    public function add_admin_menu() {
        add_submenu_page(
            'tutor-stats',
            __('Video Analytics', 'tutor-lms-advanced-tracking'),
            __('🎬 Videos', 'tutor-lms-advanced-tracking'),
            'manage_tutor',
            'tlat-videos',
            array($this, 'render_videos_page')
        );
    }

    // -------------------------------------------------------------------------
    // Asset Enqueuing
    // -------------------------------------------------------------------------

    /**
     * Enqueue the frontend video tracker on lesson pages.
     */
    public function enqueue_frontend_tracker() {
        // Only load when viewing a Tutor LMS lesson
        if (!is_singular()) {
            return;
        }
        $post_type = get_post_type();
        $lesson_types = array('lesson', 'lessons', 'tutor_lesson');
        if (!in_array($post_type, $lesson_types, true)) {
            return;
        }

        // YouTube iframe API (loaded conditionally by tracker)
        wp_register_script('youtube-iframe-api', 'https://www.youtube.com/iframe_api', array(), null, true);

        // Vimeo Player SDK
        wp_register_script('vimeo-player', 'https://player.vimeo.com/api/player.js', array(), '2.20.0', true);

        wp_enqueue_script(
            'tlat-video-tracker',
            TLAT_PLUGIN_URL . 'assets/js/video-tracker.js',
            array('jquery'),
            TLAT_VERSION,
            true
        );

        $lesson_id = get_the_ID();
        $course_id = (int) get_post_field('post_parent', $lesson_id);
        // Walk up if the parent is a topic, not a course
        if ($course_id && get_post_type($course_id) !== 'courses') {
            $course_id = (int) get_post_field('post_parent', $course_id);
        }

        wp_localize_script('tlat-video-tracker', 'tlatVideoTracker', array(
            'ajaxUrl'   => admin_url('admin-ajax.php'),
            'restUrl'   => rest_url('tlat/v1/videos/progress'),
            'nonce'     => wp_create_nonce('tlat_video_tracking'),
            'restNonce' => wp_create_nonce('wp_rest'),
            'lessonId'  => (int) $lesson_id,
            'courseId'   => $course_id,
            'userId'    => get_current_user_id(),
            'interval'  => 5, // seconds between progress updates
        ));
    }

    /**
     * Enqueue admin scripts on the Video Analytics page.
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'tlat-videos') === false) {
            return;
        }

        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '4.4.0', true);
        wp_enqueue_script(
            'tlat-video-analytics',
            TLAT_PLUGIN_URL . 'assets/js/video-analytics.js',
            array('jquery', 'chart-js'),
            TLAT_VERSION,
            true
        );

        wp_localize_script('tlat-video-analytics', 'tlatVideoAnalytics', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('tlat_video_analytics'),
            'i18n'    => array(
                'loading'       => __('Loading...', 'tutor-lms-advanced-tracking'),
                'error'         => __('Error loading data', 'tutor-lms-advanced-tracking'),
                'noData'        => __('No data available', 'tutor-lms-advanced-tracking'),
                'students'      => __('Students', 'tutor-lms-advanced-tracking'),
                'completion'    => __('Completion %', 'tutor-lms-advanced-tracking'),
                'watchTime'     => __('Watch Time (s)', 'tutor-lms-advanced-tracking'),
                'dropOff'       => __('Drop-off %', 'tutor-lms-advanced-tracking'),
                'segment'       => __('Segment', 'tutor-lms-advanced-tracking'),
                'viewers'       => __('Viewers', 'tutor-lms-advanced-tracking'),
            ),
        ));
    }

    // -------------------------------------------------------------------------
    // REST API
    // -------------------------------------------------------------------------

    public function register_rest_routes() {
        // Save progress (frontend, authenticated)
        register_rest_route('tlat/v1', '/videos/progress', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'api_save_progress'),
            'permission_callback' => function () {
                return is_user_logged_in();
            },
            'args' => array(
                'video_url'      => array('required' => true, 'sanitize_callback' => 'esc_url_raw'),
                'video_provider' => array('required' => true, 'sanitize_callback' => 'sanitize_text_field'),
                'lesson_id'      => array('required' => true, 'sanitize_callback' => 'absint'),
                'course_id'      => array('required' => true, 'sanitize_callback' => 'absint'),
                'duration'       => array('required' => true, 'sanitize_callback' => 'absint'),
                'current_time'   => array('required' => true, 'sanitize_callback' => 'absint'),
                'segments'       => array('required' => false, 'sanitize_callback' => 'sanitize_text_field'),
                'event'          => array('required' => true, 'sanitize_callback' => 'sanitize_text_field'),
            ),
        ));

        // List all tracked videos (admin)
        register_rest_route('tlat/v1', '/videos', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'api_get_videos'),
            'permission_callback' => function () {
                return current_user_can('manage_tutor');
            },
        ));

        // Single video stats (admin)
        register_rest_route('tlat/v1', '/videos/(?P<id>\d+)', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'api_get_video'),
            'permission_callback' => function () {
                return current_user_can('manage_tutor');
            },
        ));

        // Video heatmap (admin)
        register_rest_route('tlat/v1', '/videos/(?P<id>\d+)/heatmap', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'api_get_video_heatmap'),
            'permission_callback' => function () {
                return current_user_can('manage_tutor');
            },
        ));

        // Per-student video engagement (admin)
        register_rest_route('tlat/v1', '/videos/(?P<id>\d+)/students', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'api_get_video_students'),
            'permission_callback' => function () {
                return current_user_can('manage_tutor');
            },
        ));
    }

    // -------------------------------------------------------------------------
    // REST Callbacks
    // -------------------------------------------------------------------------

    /**
     * Save / update video watch progress from the frontend tracker.
     */
    public function api_save_progress($request) {
        $user_id        = get_current_user_id();
        $video_url      = $request->get_param('video_url');
        $video_provider = $request->get_param('video_provider');
        $lesson_id      = $request->get_param('lesson_id');
        $course_id      = $request->get_param('course_id');
        $duration       = $request->get_param('duration');
        $current_time   = $request->get_param('current_time');
        $segments_json  = $request->get_param('segments');
        $event          = $request->get_param('event');

        if (!$user_id || !$video_url || !$duration) {
            return new WP_Error('invalid_data', __('Missing required fields', 'tutor-lms-advanced-tracking'), array('status' => 400));
        }

        $result = $this->upsert_progress($user_id, $video_url, $video_provider, $lesson_id, $course_id, $duration, $current_time, $segments_json, $event);

        if (is_wp_error($result)) {
            return $result;
        }

        return rest_ensure_response(array('success' => true, 'completion_pct' => $result));
    }

    public function api_get_videos($request) {
        return rest_ensure_response($this->get_videos_overview());
    }

    public function api_get_video($request) {
        $id = (int) $request['id'];
        return rest_ensure_response($this->get_video_stats($id));
    }

    public function api_get_video_heatmap($request) {
        $id = (int) $request['id'];
        return rest_ensure_response($this->get_watch_heatmap($id));
    }

    public function api_get_video_students($request) {
        $id = (int) $request['id'];
        return rest_ensure_response($this->get_student_engagement($id));
    }

    // -------------------------------------------------------------------------
    // AJAX Handlers (admin)
    // -------------------------------------------------------------------------

    public function ajax_track_video_progress() {
        check_ajax_referer('tlat_video_tracking', 'nonce');

        $user_id        = get_current_user_id();
        $video_url      = isset($_POST['video_url']) ? esc_url_raw(wp_unslash($_POST['video_url'])) : '';
        $video_provider = isset($_POST['video_provider']) ? sanitize_text_field(wp_unslash($_POST['video_provider'])) : 'html5';
        $lesson_id      = isset($_POST['lesson_id']) ? absint($_POST['lesson_id']) : 0;
        $course_id      = isset($_POST['course_id']) ? absint($_POST['course_id']) : 0;
        $duration       = isset($_POST['duration']) ? absint($_POST['duration']) : 0;
        $current_time   = isset($_POST['current_time']) ? absint($_POST['current_time']) : 0;
        $segments_json  = isset($_POST['segments']) ? sanitize_text_field(wp_unslash($_POST['segments'])) : '';
        $event          = isset($_POST['event']) ? sanitize_text_field(wp_unslash($_POST['event'])) : 'timeupdate';

        if (!$user_id || !$video_url || !$duration) {
            wp_send_json_error(array('message' => 'Missing required fields'));
        }

        $result = $this->upsert_progress($user_id, $video_url, $video_provider, $lesson_id, $course_id, $duration, $current_time, $segments_json, $event);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array('completion_pct' => $result));
    }

    public function ajax_get_video_details() {
        check_ajax_referer('tlat_video_analytics', 'nonce');

        if (!current_user_can('manage_tutor')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $video_url = isset($_POST['video_url']) ? esc_url_raw(wp_unslash($_POST['video_url'])) : '';
        $lesson_id = isset($_POST['lesson_id']) ? absint($_POST['lesson_id']) : 0;

        if (!$video_url && !$lesson_id) {
            wp_send_json_error(array('message' => 'Video URL or lesson ID required'));
        }

        $stats   = $this->get_video_stats_by_url($video_url, $lesson_id);
        $heatmap = $this->get_watch_heatmap_by_url($video_url, $lesson_id);

        // Build HTML for the modal
        ob_start();
        $this->render_video_detail_html($stats, $heatmap);
        $html = ob_get_clean();

        wp_send_json_success(array(
            'html'    => $html,
            'title'   => $stats['title'] ?? __('Video Analysis', 'tutor-lms-advanced-tracking'),
            'heatmap' => $heatmap,
            'stats'   => $stats,
        ));
    }

    public function ajax_get_video_students() {
        check_ajax_referer('tlat_video_analytics', 'nonce');

        if (!current_user_can('manage_tutor')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $video_url = isset($_POST['video_url']) ? esc_url_raw(wp_unslash($_POST['video_url'])) : '';
        $lesson_id = isset($_POST['lesson_id']) ? absint($_POST['lesson_id']) : 0;

        $students = $this->get_student_engagement_by_url($video_url, $lesson_id);
        wp_send_json_success(array('students' => $students));
    }

    // -------------------------------------------------------------------------
    // Data Methods
    // -------------------------------------------------------------------------

    /**
     * Insert or update a user's video progress row.
     *
     * @return float|WP_Error Completion percentage on success.
     */
    private function upsert_progress($user_id, $video_url, $provider, $lesson_id, $course_id, $duration, $current_time, $segments_json, $event) {
        global $wpdb;

        $now = current_time('mysql');

        // Fetch existing row
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id, watched_seconds, max_position, completion_pct, play_count, segments_watched
             FROM {$this->table}
             WHERE user_id = %d AND video_url = %s AND lesson_id = %d",
            $user_id, $video_url, $lesson_id
        ));

        // Merge segment data
        $new_segments = $this->merge_segments($existing ? $existing->segments_watched : '', $segments_json);

        // Calculate watched seconds from merged segments
        $watched_seconds = $this->calculate_watched_seconds($new_segments);
        if ($existing) {
            $watched_seconds = max((int) $existing->watched_seconds, $watched_seconds);
        }

        $max_position = max($current_time, $existing ? (int) $existing->max_position : 0);
        $completion   = $duration > 0 ? min(100.0, round(($watched_seconds / $duration) * 100, 2)) : 0.0;

        // Increment play count only on 'play' events
        $play_inc = ($event === 'play') ? 1 : 0;

        if ($existing) {
            $wpdb->update(
                $this->table,
                array(
                    'duration'         => $duration,
                    'watched_seconds'  => $watched_seconds,
                    'max_position'     => $max_position,
                    'completion_pct'   => $completion,
                    'play_count'       => (int) $existing->play_count + $play_inc,
                    'segments_watched' => $new_segments,
                    'updated_at'       => $now,
                    'course_id'        => $course_id,
                    'video_provider'   => $provider,
                ),
                array('id' => $existing->id),
                array('%d', '%d', '%d', '%f', '%d', '%s', '%s', '%d', '%s'),
                array('%d')
            );
        } else {
            $wpdb->insert(
                $this->table,
                array(
                    'user_id'          => $user_id,
                    'video_url'        => $video_url,
                    'video_provider'   => $provider,
                    'lesson_id'        => $lesson_id,
                    'course_id'        => $course_id,
                    'duration'         => $duration,
                    'watched_seconds'  => $watched_seconds,
                    'max_position'     => $max_position,
                    'completion_pct'   => $completion,
                    'play_count'       => 1,
                    'segments_watched' => $new_segments,
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ),
                array('%d', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%f', '%d', '%s', '%s', '%s')
            );
        }

        return $completion;
    }

    /**
     * Merge old and new segment JSON strings into a unified set of watched ranges.
     * Segments format: JSON array of [start, end] pairs, e.g. [[0,30],[45,90]].
     */
    private function merge_segments($old_json, $new_json) {
        $old = !empty($old_json) ? json_decode($old_json, true) : array();
        $new = !empty($new_json) ? json_decode($new_json, true) : array();

        if (!is_array($old)) $old = array();
        if (!is_array($new)) $new = array();

        $all = array_merge($old, $new);

        if (empty($all)) {
            return '[]';
        }

        // Sort by start time
        usort($all, function ($a, $b) {
            return ($a[0] ?? 0) - ($b[0] ?? 0);
        });

        // Merge overlapping intervals
        $merged = array($all[0]);
        for ($i = 1; $i < count($all); $i++) {
            $last = &$merged[count($merged) - 1];
            if ($all[$i][0] <= $last[1] + 1) {
                $last[1] = max($last[1], $all[$i][1]);
            } else {
                $merged[] = $all[$i];
            }
        }

        return wp_json_encode($merged);
    }

    /**
     * Calculate total unique watched seconds from merged segments.
     */
    private function calculate_watched_seconds($segments_json) {
        $segments = json_decode($segments_json, true);
        if (!is_array($segments)) {
            return 0;
        }
        $total = 0;
        foreach ($segments as $seg) {
            if (is_array($seg) && count($seg) >= 2) {
                $total += max(0, $seg[1] - $seg[0]);
            }
        }
        return $total;
    }

    /**
     * Get overview of all tracked videos grouped by unique video_url + lesson_id.
     */
    public function get_videos_overview() {
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT
                video_url,
                video_provider,
                lesson_id,
                course_id,
                MAX(duration) as duration,
                COUNT(DISTINCT user_id) as viewers,
                AVG(completion_pct) as avg_completion,
                MAX(completion_pct) as max_completion,
                SUM(play_count) as total_plays,
                AVG(watched_seconds) as avg_watch_time
            FROM {$this->table}
            GROUP BY video_url, lesson_id
            ORDER BY viewers DESC"
        );

        $data = array();
        foreach ($rows as $row) {
            $lesson_title = $row->lesson_id ? get_the_title($row->lesson_id) : '';
            $course_title = $row->course_id ? get_the_title($row->course_id) : '';

            $data[] = array(
                'video_url'      => $row->video_url,
                'video_provider' => $row->video_provider,
                'lesson_id'      => (int) $row->lesson_id,
                'lesson_title'   => $lesson_title ?: __('Unknown Lesson', 'tutor-lms-advanced-tracking'),
                'course_id'      => (int) $row->course_id,
                'course_title'   => $course_title ?: __('Unknown Course', 'tutor-lms-advanced-tracking'),
                'duration'       => (int) $row->duration,
                'viewers'        => (int) $row->viewers,
                'avg_completion'  => round((float) $row->avg_completion, 1),
                'max_completion'  => round((float) $row->max_completion, 1),
                'total_plays'    => (int) $row->total_plays,
                'avg_watch_time' => (int) $row->avg_watch_time,
            );
        }

        return $data;
    }

    /**
     * Get detailed stats for a single video row by its primary key.
     */
    public function get_video_stats($id) {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT video_url, lesson_id FROM {$this->table} WHERE id = %d", $id
        ));

        if (!$row) {
            return array();
        }

        return $this->get_video_stats_by_url($row->video_url, $row->lesson_id);
    }

    /**
     * Get aggregate stats for a specific video URL + lesson.
     */
    public function get_video_stats_by_url($video_url, $lesson_id = 0) {
        global $wpdb;

        $where = $wpdb->prepare("video_url = %s", $video_url);
        if ($lesson_id) {
            $where .= $wpdb->prepare(" AND lesson_id = %d", $lesson_id);
        }

        $stats = $wpdb->get_row(
            "SELECT
                MAX(duration) as duration,
                COUNT(DISTINCT user_id) as viewers,
                AVG(completion_pct) as avg_completion,
                MAX(completion_pct) as max_completion,
                MIN(completion_pct) as min_completion,
                SUM(play_count) as total_plays,
                AVG(watched_seconds) as avg_watch_time,
                MAX(watched_seconds) as max_watch_time,
                video_provider,
                course_id,
                lesson_id
            FROM {$this->table}
            WHERE {$where}
            GROUP BY video_url"
        );

        if (!$stats) {
            return array();
        }

        $lesson_title = $stats->lesson_id ? get_the_title($stats->lesson_id) : '';
        $course_title = $stats->course_id ? get_the_title($stats->course_id) : '';

        // Completion distribution buckets
        $completion_dist = $wpdb->get_results(
            "SELECT
                CASE
                    WHEN completion_pct = 0 THEN '0%'
                    WHEN completion_pct <= 25 THEN '1-25%'
                    WHEN completion_pct <= 50 THEN '26-50%'
                    WHEN completion_pct <= 75 THEN '51-75%'
                    WHEN completion_pct < 100 THEN '76-99%'
                    ELSE '100%'
                END as bucket,
                COUNT(*) as count
            FROM {$this->table}
            WHERE {$where}
            GROUP BY bucket
            ORDER BY FIELD(bucket, '0%', '1-25%', '26-50%', '51-75%', '76-99%', '100%')"
        );

        $buckets = array('0%', '1-25%', '26-50%', '51-75%', '76-99%', '100%');
        $bucket_counts = array_fill_keys($buckets, 0);
        foreach ($completion_dist as $d) {
            $bucket_counts[$d->bucket] = (int) $d->count;
        }

        return array(
            'title'           => $lesson_title ?: $video_url,
            'video_url'       => $video_url,
            'video_provider'  => $stats->video_provider,
            'lesson_id'       => (int) $stats->lesson_id,
            'lesson_title'    => $lesson_title,
            'course_id'       => (int) $stats->course_id,
            'course_title'    => $course_title,
            'duration'        => (int) $stats->duration,
            'viewers'         => (int) $stats->viewers,
            'avg_completion'  => round((float) $stats->avg_completion, 1),
            'max_completion'  => round((float) $stats->max_completion, 1),
            'min_completion'  => round((float) $stats->min_completion, 1),
            'total_plays'     => (int) $stats->total_plays,
            'avg_watch_time'  => (int) $stats->avg_watch_time,
            'max_watch_time'  => (int) $stats->max_watch_time,
            'completion_dist' => array(
                'buckets' => $buckets,
                'counts'  => array_values($bucket_counts),
            ),
        );
    }

    /**
     * Generate a watch heatmap: divide video into N segments and count how many
     * users watched each segment. Useful for identifying drop-off points.
     */
    public function get_watch_heatmap($id) {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT video_url, lesson_id FROM {$this->table} WHERE id = %d", $id
        ));

        if (!$row) {
            return array();
        }

        return $this->get_watch_heatmap_by_url($row->video_url, $row->lesson_id);
    }

    /**
     * Build heatmap by video URL + lesson.
     */
    public function get_watch_heatmap_by_url($video_url, $lesson_id = 0) {
        global $wpdb;

        $where = $wpdb->prepare("video_url = %s", $video_url);
        if ($lesson_id) {
            $where .= $wpdb->prepare(" AND lesson_id = %d", $lesson_id);
        }

        // Get max duration and all segment data
        $max_duration = (int) $wpdb->get_var(
            "SELECT MAX(duration) FROM {$this->table} WHERE {$where}"
        );

        if ($max_duration <= 0) {
            return array('segments' => array(), 'duration' => 0, 'total_viewers' => 0);
        }

        $total_viewers = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT user_id) FROM {$this->table} WHERE {$where}"
        );

        $all_segments_raw = $wpdb->get_col(
            "SELECT segments_watched FROM {$this->table} WHERE {$where} AND segments_watched IS NOT NULL AND segments_watched != '[]'"
        );

        // Divide video into 20 equal segments
        $num_bins = 20;
        $bin_size = max(1, (int) ceil($max_duration / $num_bins));
        $bins     = array_fill(0, $num_bins, 0);

        foreach ($all_segments_raw as $seg_json) {
            $segments = json_decode($seg_json, true);
            if (!is_array($segments)) continue;

            foreach ($segments as $seg) {
                if (!is_array($seg) || count($seg) < 2) continue;
                $start = max(0, (int) $seg[0]);
                $end   = min($max_duration, (int) $seg[1]);

                // Increment each bin this segment touches
                $start_bin = (int) floor($start / $bin_size);
                $end_bin   = min($num_bins - 1, (int) floor($end / $bin_size));

                for ($b = $start_bin; $b <= $end_bin; $b++) {
                    $bins[$b]++;
                }
            }
        }

        // Build labelled segments
        $segments_data = array();
        for ($i = 0; $i < $num_bins; $i++) {
            $seg_start = $i * $bin_size;
            $seg_end   = min($max_duration, ($i + 1) * $bin_size);
            $segments_data[] = array(
                'label'   => $this->format_time($seg_start) . '-' . $this->format_time($seg_end),
                'start'   => $seg_start,
                'end'     => $seg_end,
                'viewers' => $bins[$i],
                'pct'     => $total_viewers > 0 ? round(($bins[$i] / $total_viewers) * 100, 1) : 0,
            );
        }

        // Identify drop-off points (where viewership drops > 20% from previous segment)
        $drop_offs = array();
        for ($i = 1; $i < count($segments_data); $i++) {
            $prev = $segments_data[$i - 1]['viewers'];
            $curr = $segments_data[$i]['viewers'];
            if ($prev > 0) {
                $drop = round((($prev - $curr) / $prev) * 100, 1);
                if ($drop > 20) {
                    $drop_offs[] = array(
                        'position'   => $segments_data[$i]['start'],
                        'label'      => $segments_data[$i]['label'],
                        'drop_pct'   => $drop,
                        'from_count' => $prev,
                        'to_count'   => $curr,
                    );
                }
            }
        }

        return array(
            'segments'      => $segments_data,
            'duration'      => $max_duration,
            'total_viewers' => $total_viewers,
            'drop_offs'     => $drop_offs,
        );
    }

    /**
     * Get per-student engagement for a specific video.
     */
    public function get_student_engagement($id) {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT video_url, lesson_id FROM {$this->table} WHERE id = %d", $id
        ));

        if (!$row) {
            return array();
        }

        return $this->get_student_engagement_by_url($row->video_url, $row->lesson_id);
    }

    /**
     * Get per-student engagement by video URL + lesson.
     */
    public function get_student_engagement_by_url($video_url, $lesson_id = 0) {
        global $wpdb;

        $where = $wpdb->prepare("video_url = %s", $video_url);
        if ($lesson_id) {
            $where .= $wpdb->prepare(" AND lesson_id = %d", $lesson_id);
        }

        $rows = $wpdb->get_results(
            "SELECT user_id, watched_seconds, max_position, completion_pct, play_count, duration, updated_at
             FROM {$this->table}
             WHERE {$where}
             ORDER BY completion_pct DESC"
        );

        $data = array();
        foreach ($rows as $row) {
            $user = get_userdata($row->user_id);
            $data[] = array(
                'user_id'        => (int) $row->user_id,
                'display_name'   => $user ? $user->display_name : __('Unknown', 'tutor-lms-advanced-tracking'),
                'email'          => $user ? $user->user_email : '',
                'watched_seconds' => (int) $row->watched_seconds,
                'max_position'   => (int) $row->max_position,
                'completion_pct' => round((float) $row->completion_pct, 1),
                'play_count'     => (int) $row->play_count,
                'duration'       => (int) $row->duration,
                'last_watched'   => $row->updated_at,
            );
        }

        return $data;
    }

    // -------------------------------------------------------------------------
    // Helper Methods
    // -------------------------------------------------------------------------

    /**
     * Format seconds to MM:SS string.
     */
    private function format_time($seconds) {
        $m = (int) floor($seconds / 60);
        $s = $seconds % 60;
        return sprintf('%d:%02d', $m, $s);
    }

    /**
     * Format seconds to a human-readable string.
     */
    private function format_duration($seconds) {
        if ($seconds < 60) {
            return $seconds . 's';
        }
        if ($seconds < 3600) {
            return sprintf('%dm %ds', floor($seconds / 60), $seconds % 60);
        }
        return sprintf('%dh %dm', floor($seconds / 3600), floor(($seconds % 3600) / 60));
    }

    // -------------------------------------------------------------------------
    // Render: Video Detail HTML (used inside AJAX modal)
    // -------------------------------------------------------------------------

    private function render_video_detail_html($stats, $heatmap) {
        if (empty($stats)) {
            echo '<p>' . esc_html__('No data available for this video.', 'tutor-lms-advanced-tracking') . '</p>';
            return;
        }
        ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 15px; margin-bottom: 25px;">
            <div style="text-align: center; padding: 10px;">
                <div style="font-size: 24px; font-weight: bold; color: #3b82f6;"><?php echo esc_html($stats['viewers']); ?></div>
                <div style="color: #6b7280; font-size: 12px;"><?php _e('Viewers', 'tutor-lms-advanced-tracking'); ?></div>
            </div>
            <div style="text-align: center; padding: 10px;">
                <div style="font-size: 24px; font-weight: bold; color: #10b981;"><?php echo esc_html($stats['avg_completion']); ?>%</div>
                <div style="color: #6b7280; font-size: 12px;"><?php _e('Avg Completion', 'tutor-lms-advanced-tracking'); ?></div>
            </div>
            <div style="text-align: center; padding: 10px;">
                <div style="font-size: 24px; font-weight: bold; color: #8b5cf6;"><?php echo esc_html($stats['total_plays']); ?></div>
                <div style="color: #6b7280; font-size: 12px;"><?php _e('Total Plays', 'tutor-lms-advanced-tracking'); ?></div>
            </div>
            <div style="text-align: center; padding: 10px;">
                <div style="font-size: 24px; font-weight: bold; color: #f59e0b;"><?php echo esc_html($this->format_duration($stats['avg_watch_time'])); ?></div>
                <div style="color: #6b7280; font-size: 12px;"><?php _e('Avg Watch Time', 'tutor-lms-advanced-tracking'); ?></div>
            </div>
        </div>

        <!-- Completion Distribution -->
        <h4 style="margin: 0 0 10px;"><?php _e('Completion Distribution', 'tutor-lms-advanced-tracking'); ?></h4>
        <canvas id="tlat-video-completion-dist-chart" height="200"></canvas>

        <!-- Watch Heatmap -->
        <h4 style="margin: 25px 0 10px;"><?php _e('Watch Heatmap', 'tutor-lms-advanced-tracking'); ?></h4>
        <p style="color: #6b7280; font-size: 12px; margin: 0 0 10px;">
            <?php _e('Shows which parts of the video are watched most. Red = high viewership, blue = low.', 'tutor-lms-advanced-tracking'); ?>
        </p>
        <canvas id="tlat-video-heatmap-chart" height="120"></canvas>

        <?php if (!empty($heatmap['drop_offs'])): ?>
        <!-- Drop-off Points -->
        <h4 style="margin: 25px 0 10px;"><?php _e('Drop-off Points', 'tutor-lms-advanced-tracking'); ?></h4>
        <table class="wp-list-table widefat fixed striped" style="margin-bottom: 10px;">
            <thead>
                <tr>
                    <th><?php _e('Position', 'tutor-lms-advanced-tracking'); ?></th>
                    <th><?php _e('Drop %', 'tutor-lms-advanced-tracking'); ?></th>
                    <th><?php _e('Viewers Before', 'tutor-lms-advanced-tracking'); ?></th>
                    <th><?php _e('Viewers After', 'tutor-lms-advanced-tracking'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($heatmap['drop_offs'] as $drop): ?>
                <tr>
                    <td><?php echo esc_html($drop['label']); ?></td>
                    <td><span style="color: #ef4444; font-weight: 600;">-<?php echo esc_html($drop['drop_pct']); ?>%</span></td>
                    <td><?php echo esc_html($drop['from_count']); ?></td>
                    <td><?php echo esc_html($drop['to_count']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <!-- Per-student engagement loaded via button click -->
        <h4 style="margin: 25px 0 10px;"><?php _e('Student Engagement', 'tutor-lms-advanced-tracking'); ?></h4>
        <div id="tlat-video-students-container">
            <button class="button" id="tlat-load-video-students"><?php _e('Load Student Data', 'tutor-lms-advanced-tracking'); ?></button>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Render: Main Admin Page
    // -------------------------------------------------------------------------

    public function render_videos_page() {
        $videos = $this->get_videos_overview();

        // Overview stats
        $total_videos    = count($videos);
        $total_viewers   = $total_videos > 0 ? array_sum(array_column($videos, 'viewers')) : 0;
        $avg_completion  = $total_videos > 0 ? round(array_sum(array_column($videos, 'avg_completion')) / $total_videos, 1) : 0;
        $total_plays     = $total_videos > 0 ? array_sum(array_column($videos, 'total_plays')) : 0;

        ?>
        <div class="wrap tlat-videos-page">
            <h1><?php _e('Video Analytics', 'tutor-lms-advanced-tracking'); ?></h1>
            <p style="color: #6b7280; margin-top: -5px;">
                <?php _e('Track video watch completion rates across all courses. Videos are automatically tracked when students watch them.', 'tutor-lms-advanced-tracking'); ?>
            </p>

            <!-- Stats Cards -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">
                <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <div style="font-size: 32px; font-weight: bold; color: #3b82f6;"><?php echo esc_html($total_videos); ?></div>
                    <div style="color: #6b7280; margin-top: 5px;"><?php _e('Tracked Videos', 'tutor-lms-advanced-tracking'); ?></div>
                </div>

                <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <div style="font-size: 32px; font-weight: bold; color: #8b5cf6;"><?php echo esc_html($total_viewers); ?></div>
                    <div style="color: #6b7280; margin-top: 5px;"><?php _e('Total Viewers', 'tutor-lms-advanced-tracking'); ?></div>
                </div>

                <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <div style="font-size: 32px; font-weight: bold; color: #10b981;"><?php echo esc_html($avg_completion); ?>%</div>
                    <div style="color: #6b7280; margin-top: 5px;"><?php _e('Avg Completion Rate', 'tutor-lms-advanced-tracking'); ?></div>
                </div>

                <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <div style="font-size: 32px; font-weight: bold; color: #f59e0b;"><?php echo esc_html($total_plays); ?></div>
                    <div style="color: #6b7280; margin-top: 5px;"><?php _e('Total Plays', 'tutor-lms-advanced-tracking'); ?></div>
                </div>
            </div>

            <?php if ($total_videos > 0): ?>
            <!-- Completion Overview Chart -->
            <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 20px;">
                <h2 style="margin-top: 0;"><?php _e('Completion Rate by Video', 'tutor-lms-advanced-tracking'); ?></h2>
                <canvas id="tlat-video-overview-chart" height="300"></canvas>
            </div>
            <?php endif; ?>

            <!-- Videos Table -->
            <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <h2 style="margin-top: 0;"><?php _e('All Tracked Videos', 'tutor-lms-advanced-tracking'); ?></h2>

                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Video / Lesson', 'tutor-lms-advanced-tracking'); ?></th>
                            <th><?php _e('Course', 'tutor-lms-advanced-tracking'); ?></th>
                            <th><?php _e('Provider', 'tutor-lms-advanced-tracking'); ?></th>
                            <th><?php _e('Duration', 'tutor-lms-advanced-tracking'); ?></th>
                            <th><?php _e('Viewers', 'tutor-lms-advanced-tracking'); ?></th>
                            <th><?php _e('Avg Completion', 'tutor-lms-advanced-tracking'); ?></th>
                            <th><?php _e('Total Plays', 'tutor-lms-advanced-tracking'); ?></th>
                            <th><?php _e('Actions', 'tutor-lms-advanced-tracking'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($videos as $video): ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($video['lesson_title']); ?></strong>
                                <div style="font-size: 11px; color: #9ca3af; max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    <?php echo esc_html($video['video_url']); ?>
                                </div>
                            </td>
                            <td><?php echo esc_html($video['course_title']); ?></td>
                            <td>
                                <?php
                                $provider_icons = array('youtube' => '▶️', 'vimeo' => '🎬', 'html5' => '🎞️');
                                $icon = $provider_icons[$video['video_provider']] ?? '🎞️';
                                ?>
                                <span style="display: inline-block; background: #f3f4f6; padding: 2px 8px; border-radius: 4px; font-size: 12px;">
                                    <?php echo $icon; ?> <?php echo esc_html(ucfirst($video['video_provider'])); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html($this->format_duration($video['duration'])); ?></td>
                            <td><?php echo esc_html($video['viewers']); ?></td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <div style="width: 60px; height: 8px; background: #e5e7eb; border-radius: 4px; overflow: hidden;">
                                        <div style="width: <?php echo esc_attr(min(100, $video['avg_completion'])); ?>%; height: 100%; background: <?php echo $video['avg_completion'] < 30 ? '#ef4444' : ($video['avg_completion'] < 60 ? '#f59e0b' : '#10b981'); ?>;"></div>
                                    </div>
                                    <span><?php echo esc_html($video['avg_completion']); ?>%</span>
                                </div>
                            </td>
                            <td><?php echo esc_html($video['total_plays']); ?></td>
                            <td>
                                <button class="button button-small tlat-view-video"
                                    data-video-url="<?php echo esc_attr($video['video_url']); ?>"
                                    data-lesson-id="<?php echo esc_attr($video['lesson_id']); ?>">
                                    <?php _e('Analyze', 'tutor-lms-advanced-tracking'); ?>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>

                        <?php if (empty($videos)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 40px; color: #6b7280;">
                                <?php _e('No video data yet. Videos will be tracked automatically when students watch lesson videos.', 'tutor-lms-advanced-tracking'); ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Video Detail Modal -->
            <div id="tlat-video-modal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 100000;">
                <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; border-radius: 12px; width: 90%; max-width: 900px; max-height: 90vh; overflow: auto;">
                    <div style="padding: 20px; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; background: white; z-index: 1; border-radius: 12px 12px 0 0;">
                        <h3 id="tlat-video-modal-title" style="margin: 0;"><?php _e('Video Analysis', 'tutor-lms-advanced-tracking'); ?></h3>
                        <button id="tlat-close-video-modal" class="button">&times; <?php _e('Close', 'tutor-lms-advanced-tracking'); ?></button>
                    </div>
                    <div id="tlat-video-modal-content" style="padding: 20px;">
                        <!-- Content loaded via AJAX -->
                    </div>
                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            <?php if ($total_videos > 0): ?>
            // Render overview chart
            var overviewCtx = document.getElementById('tlat-video-overview-chart');
            if (overviewCtx) {
                var videoData = <?php echo wp_json_encode(array_slice($videos, 0, 20)); ?>;
                var labels = videoData.map(function(v) {
                    var title = v.lesson_title || 'Video';
                    return title.length > 30 ? title.substr(0, 27) + '...' : title;
                });
                var completionData = videoData.map(function(v) { return v.avg_completion; });
                var bgColors = completionData.map(function(c) {
                    if (c < 30) return '#ef4444';
                    if (c < 60) return '#f59e0b';
                    return '#10b981';
                });

                new Chart(overviewCtx.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: '<?php echo esc_js(__('Avg Completion %', 'tutor-lms-advanced-tracking')); ?>',
                            data: completionData,
                            backgroundColor: bgColors,
                            borderRadius: 4,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            y: { beginAtZero: true, max: 100, ticks: { callback: function(v) { return v + '%'; } } },
                            x: { ticks: { maxRotation: 45, minRotation: 0 } }
                        }
                    }
                });
            }
            <?php endif; ?>

            // Modal open/close
            $(document).on('click', '.tlat-view-video', function() {
                var videoUrl = $(this).data('video-url');
                var lessonId = $(this).data('lesson-id');
                $('#tlat-video-modal').show();
                $('#tlat-video-modal-content').html('<div style="text-align: center; padding: 40px;"><span class="spinner is-active" style="float: none;"></span></div>');

                $.post(ajaxurl, {
                    action: 'tlat_get_video_details',
                    nonce: '<?php echo wp_create_nonce('tlat_video_analytics'); ?>',
                    video_url: videoUrl,
                    lesson_id: lessonId
                }, function(response) {
                    if (response.success) {
                        var d = response.data;
                        $('#tlat-video-modal-content').html(d.html);
                        $('#tlat-video-modal-title').text(d.title);

                        // Render completion distribution chart
                        if (d.stats && d.stats.completion_dist) {
                            var distCtx = document.getElementById('tlat-video-completion-dist-chart');
                            if (distCtx) {
                                new Chart(distCtx.getContext('2d'), {
                                    type: 'bar',
                                    data: {
                                        labels: d.stats.completion_dist.buckets,
                                        datasets: [{
                                            label: '<?php echo esc_js(__('Students', 'tutor-lms-advanced-tracking')); ?>',
                                            data: d.stats.completion_dist.counts,
                                            backgroundColor: '#3b82f6',
                                            borderRadius: 4,
                                        }]
                                    },
                                    options: {
                                        responsive: true,
                                        plugins: { legend: { display: false } },
                                        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
                                    }
                                });
                            }
                        }

                        // Render heatmap chart
                        if (d.heatmap && d.heatmap.segments && d.heatmap.segments.length > 0) {
                            var heatCtx = document.getElementById('tlat-video-heatmap-chart');
                            if (heatCtx) {
                                var segs = d.heatmap.segments;
                                var heatLabels = segs.map(function(s) { return s.label; });
                                var heatValues = segs.map(function(s) { return s.pct; });
                                var heatColors = heatValues.map(function(v) {
                                    // Blue to red gradient
                                    var ratio = v / 100;
                                    var r = Math.round(59 + (239 - 59) * (1 - ratio));
                                    var g = Math.round(130 + (68 - 130) * (1 - ratio));
                                    var b = Math.round(246 + (68 - 246) * (1 - ratio));
                                    return 'rgba(' + r + ',' + g + ',' + b + ', 0.8)';
                                });

                                new Chart(heatCtx.getContext('2d'), {
                                    type: 'bar',
                                    data: {
                                        labels: heatLabels,
                                        datasets: [{
                                            label: '<?php echo esc_js(__('Viewership %', 'tutor-lms-advanced-tracking')); ?>',
                                            data: heatValues,
                                            backgroundColor: heatColors,
                                            borderRadius: 2,
                                        }]
                                    },
                                    options: {
                                        responsive: true,
                                        plugins: { legend: { display: false } },
                                        scales: {
                                            y: { beginAtZero: true, max: 100, ticks: { callback: function(v) { return v + '%'; } } },
                                            x: { ticks: { maxRotation: 45, font: { size: 10 } } }
                                        }
                                    }
                                });
                            }
                        }

                        // Load students button
                        $('#tlat-load-video-students').on('click', function() {
                            var btn = $(this);
                            btn.prop('disabled', true).text('<?php echo esc_js(__('Loading...', 'tutor-lms-advanced-tracking')); ?>');

                            $.post(ajaxurl, {
                                action: 'tlat_get_video_students',
                                nonce: '<?php echo wp_create_nonce('tlat_video_analytics'); ?>',
                                video_url: videoUrl,
                                lesson_id: lessonId
                            }, function(res) {
                                if (res.success && res.data.students.length > 0) {
                                    var html = '<table class="wp-list-table widefat fixed striped"><thead><tr>';
                                    html += '<th><?php echo esc_js(__('Student', 'tutor-lms-advanced-tracking')); ?></th>';
                                    html += '<th><?php echo esc_js(__('Completion', 'tutor-lms-advanced-tracking')); ?></th>';
                                    html += '<th><?php echo esc_js(__('Watch Time', 'tutor-lms-advanced-tracking')); ?></th>';
                                    html += '<th><?php echo esc_js(__('Plays', 'tutor-lms-advanced-tracking')); ?></th>';
                                    html += '<th><?php echo esc_js(__('Last Watched', 'tutor-lms-advanced-tracking')); ?></th>';
                                    html += '</tr></thead><tbody>';

                                    res.data.students.forEach(function(s) {
                                        var compColor = s.completion_pct < 30 ? '#ef4444' : (s.completion_pct < 60 ? '#f59e0b' : '#10b981');
                                        html += '<tr>';
                                        html += '<td>' + s.display_name + '</td>';
                                        html += '<td><span style="color:' + compColor + '; font-weight:600;">' + s.completion_pct + '%</span></td>';
                                        html += '<td>' + Math.round(s.watched_seconds / 60) + 'm ' + (s.watched_seconds % 60) + 's</td>';
                                        html += '<td>' + s.play_count + '</td>';
                                        html += '<td>' + s.last_watched + '</td>';
                                        html += '</tr>';
                                    });
                                    html += '</tbody></table>';
                                    $('#tlat-video-students-container').html(html);
                                } else {
                                    $('#tlat-video-students-container').html('<p style="color:#6b7280;"><?php echo esc_js(__('No student data available.', 'tutor-lms-advanced-tracking')); ?></p>');
                                }
                            });
                        });
                    } else {
                        $('#tlat-video-modal-content').html('<p style="color: #ef4444;"><?php echo esc_js(__('Error loading video data.', 'tutor-lms-advanced-tracking')); ?></p>');
                    }
                });
            });

            $('#tlat-close-video-modal, #tlat-video-modal').on('click', function(e) {
                if (e.target === this) {
                    $('#tlat-video-modal').hide();
                }
            });
        });
        </script>
        <?php
    }
}
