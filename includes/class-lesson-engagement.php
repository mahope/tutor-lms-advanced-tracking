<?php
/**
 * Lesson Engagement Score Analytics
 * 
 * Beregner engagement score (0-100) for hver lesson baseret på:
 * - Tid brugt vs forventet tid
 * - Video watch completion percentage
 * - Quiz completion/score
 * - Assignment submission
 * - Repeat visits
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class TutorAdvancedTracking_Lesson_Engagement {

    /**
     * Database table name for lesson engagement tracking
     */
    private $lesson_engagement_table;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->lesson_engagement_table = $wpdb->prefix . 'tlat_lesson_engagement';

        // Initialize database table
        add_action('plugins_loaded', array($this, 'init_db_table'), 10);

        // Track lesson views
        add_action('tutor_lesson_start_before', array($this, 'track_lesson_view'), 10, 2);
        
        // Track lesson completion
        add_action('tutor_lesson/complete_before', array($this, 'track_lesson_complete'), 10, 2);
        
        // Track video progress
        add_action('tutor_after_watch_video', array($this, 'track_video_progress'), 10, 3);
        
        // Track quiz completion
        add_action('tutor_quiz/attempt/submitted', array($this, 'track_quiz_completion'), 10, 2);
        
        // Track assignment submission
        add_action('tutor_assignment/submitted/after', array($this, 'track_assignment_submission'), 10, 2);
        
        // Admin menu
        add_action('tutor_admin_register', array($this, 'register_admin_menu'), 20);
        
        // AJAX handlers
        add_action('wp_ajax_tutor_advanced_lesson_engagement_data', array($this, 'handle_ajax_engagement_data'));
        add_action('wp_ajax_tutor_advanced_lesson_engagement_detail', array($this, 'handle_ajax_engagement_detail'));
    }

    /**
     * Initialize database table
     */
    public function init_db_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->lesson_engagement_table} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            lesson_id mediumint(9) NOT NULL,
            user_id mediumint(9) NOT NULL,
            course_id mediumint(9) NOT NULL,
            time_spent_seconds int(11) DEFAULT 0,
            video_watch_percent decimal(5,2) DEFAULT 0,
            video_watch_seconds int(11) DEFAULT 0,
            quiz_completed tinyint(1) DEFAULT 0,
            quiz_score decimal(5,2) DEFAULT 0,
            assignment_submitted tinyint(1) DEFAULT 0,
            assignment_grade decimal(5,2) DEFAULT 0,
            repeat_visits int(11) DEFAULT 0,
            engagement_score decimal(5,2) DEFAULT 0,
            last_activity datetime DEFAULT CURRENT_TIMESTAMP,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY lesson_id (lesson_id),
            KEY user_id (user_id),
            KEY course_id (course_id)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Track lesson view
     */
    public function track_lesson_view($lesson_id, $course_id) {
        if (!is_user_logged_in()) {
            return;
        }

        $user_id = get_current_user_id();
        
        // Check if this is a repeat visit
        $repeat_count = $this->get_repeat_visit_count($lesson_id, $user_id);
        
        // Update or create engagement record
        $this->update_engagement_record(array(
            'lesson_id' => $lesson_id,
            'user_id' => $user_id,
            'course_id' => $course_id,
            'repeat_visits' => $repeat_count + 1,
        ));
    }

    /**
     * Track lesson completion
     */
    public function track_lesson_complete($lesson_id, $course_id) {
        if (!is_user_logged_in()) {
            return;
        }

        $user_id = get_current_user_id();
        
        $this->update_engagement_record(array(
            'lesson_id' => $lesson_id,
            'user_id' => $user_id,
            'course_id' => $course_id,
        ));
        
        // Calculate and update final engagement score
        $this->calculate_and_save_score($lesson_id, $user_id);
    }

    /**
     * Track video progress
     */
    public function track_video_progress($lesson_id, $video_duration_seconds, $watch_percentage) {
        if (!is_user_logged_in()) {
            return;
        }

        $user_id = get_current_user_id();
        
        $this->update_engagement_record(array(
            'lesson_id' => $lesson_id,
            'user_id' => $user_id,
            'video_watch_percent' => $watch_percentage,
            'video_watch_seconds' => ($watch_percentage / 100) * $video_duration_seconds,
        ));
        
        // Calculate engagement score if video is complete
        if ($watch_percentage >= 90) {
            $course_id = get_post_field('post_parent', $lesson_id);
            $this->calculate_and_save_score($lesson_id, $user_id);
        }
    }

    /**
     * Track quiz completion
     */
    public function track_quiz_completion($attempt_id, $attempt_info) {
        if (!is_user_logged_in()) {
            return;
        }

        $quiz_id = $attempt_info['quiz_id'] ?? 0;
        $lesson_id = get_post_meta($quiz_id, '_tutor_quiz_lesson_id', true);
        
        if (!$lesson_id) {
            return;
        }

        $user_id = get_current_user_id();
        $course_id = get_post_field('post_parent', $lesson_id);
        
        // Get quiz score
        $earned_marks = $attempt_info['earned_marks'] ?? 0;
        $total_marks = $attempt_info['total_marks'] ?? 100;
        $score = ($total_marks > 0) ? ($earned_marks / $total_marks) * 100 : 0;

        $this->update_engagement_record(array(
            'lesson_id' => $lesson_id,
            'user_id' => $user_id,
            'course_id' => $course_id,
            'quiz_completed' => 1,
            'quiz_score' => $score,
        ));
        
        $this->calculate_and_save_score($lesson_id, $user_id);
    }

    /**
     * Track assignment submission
     */
    public function track_assignment_submission($assignment_id, $course_id) {
        if (!is_user_logged_in()) {
            return;
        }

        $user_id = get_current_user_id();
        $lesson_id = get_post_meta($assignment_id, '_tutor_assignment_lesson_id', true);
        
        if (!$lesson_id) {
            return;
        }

        $grade = get_post_meta($assignment_id, '_tutor_assignment_grade', true);
        $grade_value = is_numeric($grade) ? floatval($grade) : 0;

        $this->update_engagement_record(array(
            'lesson_id' => $lesson_id,
            'user_id' => $user_id,
            'course_id' => $course_id,
            'assignment_submitted' => 1,
            'assignment_grade' => $grade_value,
        ));
        
        $this->calculate_and_save_score($lesson_id, $user_id);
    }

    /**
     * Update or create engagement record
     */
    private function update_engagement_record($data) {
        global $wpdb;
        
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->lesson_engagement_table} 
             WHERE lesson_id = %d AND user_id = %d",
            $data['lesson_id'],
            $data['user_id']
        ));

        if ($existing) {
            $update_data = array();
            foreach ($data as $key => $value) {
                if ($key !== 'lesson_id' && $key !== 'user_id') {
                    $update_data[$key] = $value;
                }
            }
            $update_data['last_activity'] = current_time('mysql');
            
            if (!empty($update_data)) {
                $wpdb->update(
                    $this->lesson_engagement_table,
                    $update_data,
                    array('lesson_id' => $data['lesson_id'], 'user_id' => $data['user_id'])
                );
            }
        } else {
            $data['last_activity'] = current_time('mysql');
            $data['created_at'] = current_time('mysql');
            $wpdb->insert($this->lesson_engagement_table, $data);
        }
    }

    /**
     * Get repeat visit count
     */
    private function get_repeat_visit_count($lesson_id, $user_id) {
        global $wpdb;
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT repeat_visits FROM {$this->lesson_engagement_table} 
             WHERE lesson_id = %d AND user_id = %d",
            $lesson_id,
            $user_id
        ));
        
        return $count ? intval($count) : 0;
    }

    /**
     * Calculate and save engagement score
     */
    private function calculate_and_save_score($lesson_id, $user_id) {
        $score = $this->calculate_engagement_score($lesson_id, $user_id);
        
        global $wpdb;
        $wpdb->update(
            $this->lesson_engagement_table,
            array('engagement_score' => $score),
            array('lesson_id' => $lesson_id, 'user_id' => $user_id)
        );
        
        return $score;
    }

    /**
     * Calculate engagement score for a lesson
     * 
     * Score components:
     * - Time spent: 30% weight (bonus for spending expected time or more)
     * - Video completion: 25% weight (if video exists)
     * - Quiz score: 25% weight (if quiz exists)
     * - Assignment: 20% weight (if assignment exists)
     * - Repeat visits: +5 bonus for each repeat beyond first
     * 
     * @return float Engagement score 0-100
     */
    public function calculate_engagement_score($lesson_id, $user_id) {
        global $wpdb;
        
        $record = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->lesson_engagement_table} 
             WHERE lesson_id = %d AND user_id = %d",
            $lesson_id,
            $user_id
        ));
        
        if (!$record) {
            return 0;
        }
        
        $score = 0;
        $lesson_type = get_post_meta($lesson_id, '_tutor_lesson_type', true);
        
        // Time spent score (30% weight)
        $expected_time = get_post_meta($lesson_id, '_tutor_lesson_duration', true);
        $expected_seconds = $this->parse_duration_to_seconds($expected_time);
        
        if ($expected_seconds > 0 && $record->time_spent_seconds > 0) {
            $time_ratio = min($record->time_spent_seconds / $expected_seconds, 1.5); // Cap at 150%
            $time_score = $time_ratio * 30;
        } elseif ($record->time_spent_seconds > 0) {
            // If no expected time, give partial credit
            $time_score = min($record->time_spent_seconds / 300, 30); // 5 min max for no-duration lessons
        } else {
            $time_score = 0;
        }
        $score += $time_score;
        
        // Video completion score (25% weight)
        if ($lesson_type === 'video' && $record->video_watch_percent > 0) {
            $video_score = ($record->video_watch_percent / 100) * 25;
            $score += $video_score;
        }
        
        // Quiz score (25% weight)
        if ($record->quiz_completed && $record->quiz_score > 0) {
            $quiz_score = ($record->quiz_score / 100) * 25;
            $score += $quiz_score;
        }
        
        // Assignment score (20% weight)
        if ($record->assignment_submitted) {
            if ($record->assignment_grade > 0) {
                $assignment_score = ($record->assignment_grade / 100) * 20;
            } else {
                $assignment_score = 15; // Submitted but not graded yet
            }
            $score += $assignment_score;
        }
        
        // Repeat visit bonus (+5 per repeat, max 10)
        if ($record->repeat_visits > 1) {
            $repeat_bonus = min(($record->repeat_visits - 1) * 5, 10);
            $score += $repeat_bonus;
        }
        
        // Cap score at 100
        return min(round($score, 2), 100);
    }

    /**
     * Parse duration string to seconds
     */
    private function parse_duration_to_seconds($duration) {
        if (empty($duration)) {
            return 0;
        }
        
        // Tutor LMS duration format: "1h 30m" or "45m" or "3600s"
        $duration = trim($duration);
        $seconds = 0;
        
        // Parse hours
        if (preg_match('/(\d+)h/i', $duration, $matches)) {
            $seconds += intval($matches[1]) * 3600;
        }
        
        // Parse minutes
        if (preg_match('/(\d+)m(?![a-z])/i', $duration, $matches)) {
            $seconds += intval($matches[1]) * 60;
        }
        
        // Parse seconds
        if (preg_match('/(\d+)s(?![a-z])/i', $duration, $matches)) {
            $seconds += intval($matches[1]);
        }
        
        return $seconds;
    }

    /**
     * Get engagement score for a single lesson
     */
    public function get_lesson_engagement_score($lesson_id, $user_id = null) {
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }
        
        return $this->calculate_engagement_score($lesson_id, $user_id);
    }

    /**
     * Get engagement data for all lessons in a course
     */
    public function get_all_lessons_engagement($course_id, $limit = 50, $offset = 0) {
        global $wpdb;
        
        $lessons = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                p.ID as lesson_id,
                p.post_title as lesson_title,
                pm.meta_value as lesson_type,
                COUNT(DISTINCT e.user_id) as total_students,
                AVG(e.engagement_score) as avg_score,
                AVG(e.time_spent_seconds) as avg_time,
                AVG(e.video_watch_percent) as avg_video,
                SUM(CASE WHEN e.quiz_completed = 1 THEN 1 ELSE 0 END) as quiz_completions,
                SUM(CASE WHEN e.assignment_submitted = 1 THEN 1 ELSE 0 END) as assignment_subs
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_tutor_lesson_type'
            LEFT JOIN {$this->lesson_engagement_table} e ON p.ID = e.lesson_id
            WHERE p.post_parent = %d 
            AND p.post_type = ' lessons'
            AND p.post_status = 'publish'
            GROUP BY p.ID
            ORDER BY avg_score DESC
            LIMIT %d OFFSET %d",
            $course_id,
            $limit,
            $offset
        ));
        
        return $lessons;
    }

    /**
     * Register admin menu
     */
    public function register_admin_menu() {
        add_submenu_page(
            'tutor',
            __('Lesson Engagement', 'tutor-advanced-tracking'),
            __('Lesson Engagement', 'tutor-advanced-tracking'),
            'manage_options',
            'tutor-advanced-lesson-engagement',
            array($this, 'render_admin_page')
        );
    }

    /**
     * Render admin page
     */
    public function render_admin_page() {
        $course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;
        $lesson_id = isset($_GET['lesson_id']) ? intval($_GET['lesson_id']) : 0;
        
        ?>
        <div class="wrap tutor-wrap">
            <h1>
                <?php _e('Lesson Engagement Analytics', 'tutor-advanced-tracking'); ?>
                <a href="?page=tutor-advanced-lesson-engagement" class="page-title-action">
                    <?php _e('← Back to Courses', 'tutor-advanced-tracking'); ?>
                </a>
            </h1>
            
            <?php if ($course_id && $lesson_id): ?>
                <?php $this->render_lesson_detail($course_id, $lesson_id); ?>
            <?php elseif ($course_id): ?>
                <?php $this->render_course_lessons($course_id); ?>
            <?php else: ?>
                <?php $this->render_course_list(); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render course list
     */
    private function render_course_list() {
        $courses = get_posts(array(
            'post_type' => 'courses',
            'posts_per_page' => 20,
            'post_status' => 'publish',
        ));
        
        ?>
        <div class="card" style="max-width: 800px;">
            <h2><?php _e('Select a Course', 'tutor-advanced-tracking'); ?></h2>
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Course', 'tutor-advanced-tracking'); ?></th>
                        <th><?php _e('Students', 'tutor-advanced-tracking'); ?></th>
                        <th><?php _e('Avg Engagement', 'tutor-advanced-tracking'); ?></th>
                        <th><?php _e('Actions', 'tutor-advanced-tracking'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($courses as $course): ?>
                        <?php 
                        $stats = $this->get_course_engagement_stats($course->ID);
                        ?>
                        <tr>
                            <td><?php echo esc_html($course->post_title); ?></td>
                            <td><?php echo esc_html($stats['students']); ?></td>
                            <td>
                                <?php $this->render_score_badge($stats['avg_score']); ?>
                            </td>
                            <td>
                                <a href="?page=tutor-advanced-lesson-engagement&course_id=<?php echo $course->ID; ?>" 
                                   class="button button-secondary">
                                    <?php _e('View Lessons', 'tutor-advanced-tracking'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Get course engagement stats
     */
    private function get_course_engagement_stats($course_id) {
        global $wpdb;
        
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(DISTINCT user_id) as students,
                AVG(engagement_score) as avg_score
            FROM {$this->lesson_engagement_table}
            WHERE course_id = %d",
            $course_id
        ));
        
        return array(
            'students' => $stats->students ?: 0,
            'avg_score' => $stats->avg_score ?: 0,
        );
    }

    /**
     * Render course lessons view
     */
    private function render_course_lessons($course_id) {
        $lessons = $this->get_all_lessons_engagement($course_id);
        $course = get_post($course_id);
        
        ?>
        <div class="card" style="max-width: 1200px;">
            <h2><?php printf(__('Engagement for: %s', 'tutor-advanced-tracking'), esc_html($course->post_title)); ?></h2>
            
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Lesson', 'tutor-advanced-tracking'); ?></th>
                        <th><?php _e('Type', 'tutor-advanced-tracking'); ?></th>
                        <th><?php _e('Students', 'tutor-advanced-tracking'); ?></th>
                        <th><?php _e('Avg Score', 'tutor-advanced-tracking'); ?></th>
                        <th><?php _e('Avg Time', 'tutor-advanced-tracking'); ?></th>
                        <th><?php _e('Video %', 'tutor-advanced-tracking'); ?></th>
                        <th><?php _e('Quiz', 'tutor-advanced-tracking'); ?></th>
                        <th><?php _e('Assignment', 'tutor-advanced-tracking'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lessons as $lesson): ?>
                        <tr>
                            <td>
                                <a href="?page=tutor-advanced-lesson-engagement&course_id=<?php echo $course_id; ?>&lesson_id=<?php echo $lesson->lesson_id; ?>">
                                    <?php echo esc_html($lesson->lesson_title); ?>
                                </a>
                            </td>
                            <td><?php echo esc_html(ucfirst($lesson->lesson_type ?: 'text')); ?></td>
                            <td><?php echo esc_html($lesson->total_students); ?></td>
                            <td><?php $this->render_score_badge($lesson->avg_score); ?></td>
                            <td><?php echo $this->format_time($lesson->avg_time); ?></td>
                            <td><?php echo $lesson->avg_video ? round($lesson->avg_video, 1) . '%' : '-'; ?></td>
                            <td><?php echo $lesson->quiz_completions ?: '-'; ?></td>
                            <td><?php echo $lesson->assignment_subs ?: '-'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Render lesson detail
     */
    private function render_lesson_detail($course_id, $lesson_id) {
        $lesson = get_post($lesson_id);
        global $wpdb;
        
        // Get all student engagement for this lesson
        $students = $wpdb->get_results($wpdb->prepare(
            "SELECT e.*, u.display_name, u.user_email
             FROM {$this->lesson_engagement_table} e
             LEFT JOIN {$wpdb->users} u ON e.user_id = u.ID
             WHERE e.lesson_id = %d
             ORDER BY e.engagement_score DESC",
            $lesson_id
        ));
        
        ?>
        <div class="card" style="max-width: 1200px;">
            <h2><?php printf(__('Lesson: %s', 'tutor-advanced-tracking'), esc_html($lesson->post_title)); ?></h2>
            
            <div style="display: flex; gap: 20px; margin-bottom: 20px;">
                <div style="flex: 1; padding: 15px; background: #f0f0f1; border-radius: 8px;">
                    <strong><?php _e('Total Students', 'tutor-advanced-tracking'); ?></strong><br>
                    <?php echo count($students); ?>
                </div>
                <div style="flex: 1; padding: 15px; background: #f0f0f1; border-radius: 8px;">
                    <strong><?php _e('Avg Score', 'tutor-advanced-tracking'); ?></strong><br>
                    <?php $avg = array_sum(array_column((array)$students, 'engagement_score')) / max(count($students), 1); ?>
                    <?php $this->render_score_badge($avg); ?>
                </div>
                <div style="flex: 1; padding: 15px; background: #f0f0f1; border-radius: 8px;">
                    <strong><?php _e('Completed', 'tutor-advanced-tracking'); ?></strong><br>
                    <?php echo count(array_filter((array)$students, function($s) { return $s->engagement_score >= 80; })); ?>
                </div>
            </div>
            
            <h3><?php _e('Student Engagement', 'tutor-advanced-tracking'); ?></h3>
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Student', 'tutor-advanced-tracking'); ?></th>
                        <th><?php _e('Score', 'tutor-advanced-tracking'); ?></th>
                        <th><?php _e('Time Spent', 'tutor-advanced-tracking'); ?></th>
                        <th><?php _e('Video', 'tutor-advanced-tracking'); ?></th>
                        <th><?php _e('Quiz', 'tutor-advanced-tracking'); ?></th>
                        <th><?php _e('Assignment', 'tutor-advanced-tracking'); ?></th>
                        <th><?php _e('Visits', 'tutor-advanced-tracking'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $student): ?>
                        <tr>
                            <td><?php echo esc_html($student->display_name); ?></td>
                            <td><?php $this->render_score_badge($student->engagement_score); ?></td>
                            <td><?php echo $this->format_time($student->time_spent_seconds); ?></td>
                            <td><?php echo $student->video_watch_percent ? round($student->video_watch_percent, 1) . '%' : '-'; ?></td>
                            <td><?php echo $student->quiz_completed ? round($student->quiz_score, 1) . '%' : '-'; ?></td>
                            <td><?php echo $student->assignment_submitted ? ($student->assignment_grade ? round($student->assignment_grade, 1) . '%' : '✓') : '-'; ?></td>
                            <td><?php echo $student->repeat_visits; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Render score as color-coded badge
     */
    private function render_score_badge($score) {
        $score = floatval($score);
        $color = '#22c55e'; // Green
        $bg_color = '#dcfce7';
        
        if ($score < 40) {
            $color = '#ef4444'; // Red
            $bg_color = '#fee2e2';
        } elseif ($score < 70) {
            $color = '#f59e0b'; // Yellow
            $bg_color = '#fef3c7';
        }
        
        echo sprintf(
            '<span style="background: %s; color: %s; padding: 4px 8px; border-radius: 4px; font-weight: bold;">%s</span>',
            $bg_color,
            $color,
            round($score, 1) . '%'
        );
    }

    /**
     * Format time in seconds to human readable
     */
    private function format_time($seconds) {
        if (!$seconds) return '-';
        
        $seconds = intval($seconds);
        
        if ($seconds < 60) {
            return $seconds . 's';
        } elseif ($seconds < 3600) {
            return round($seconds / 60) . 'm';
        } else {
            $hours = floor($seconds / 3600);
            $mins = round(($seconds % 3600) / 60);
            return $hours . 'h ' . $mins . 'm';
        }
    }

    /**
     * AJAX handler for engagement data
     */
    public function handle_ajax_engagement_data() {
        check_ajax_referer('tutor_advanced_tracking', 'nonce');
        
        $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
        
        if (!$course_id) {
            wp_send_json_error('Invalid course ID');
        }
        
        $lessons = $this->get_all_lessons_engagement($course_id);
        wp_send_json_success($lessons);
    }

    /**
     * AJAX handler for engagement detail
     */
    public function handle_ajax_engagement_detail() {
        check_ajax_referer('tutor_advanced_tracking', 'nonce');
        
        $lesson_id = isset($_POST['lesson_id']) ? intval($_POST['lesson_id']) : 0;
        
        if (!$lesson_id) {
            wp_send_json_error('Invalid lesson ID');
        }
        
        global $wpdb;
        
        $students = $wpdb->get_results($wpdb->prepare(
            "SELECT e.*, u.display_name, u.user_email
             FROM {$this->lesson_engagement_table} e
             LEFT JOIN {$wpdb->users} u ON e.user_id = u.ID
             WHERE e.lesson_id = %d
             ORDER BY e.engagement_score DESC",
            $lesson_id
        ));
        
        wp_send_json_success($students);
    }
}

// Initialize
new TutorAdvancedTracking_Lesson_Engagement();
