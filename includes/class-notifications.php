<?php
/**
 * Email Notification System for Advanced Tutor LMS Stats Dashboard
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class TutorAdvancedTracking_Notifications {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Schedule daily digest emails
        add_action('init', array($this, 'schedule_daily_digest'));
        add_action('tutor_advanced_tracking_daily_digest', array($this, 'send_daily_digest'));
        
        // Schedule weekly summary emails
        add_action('init', array($this, 'schedule_weekly_summary'));
        add_action('tutor_advanced_tracking_weekly_summary', array($this, 'send_weekly_summary'));
        
        // Hook into Tutor LMS events for real-time notifications
        add_action('tutor_after_enrolled', array($this, 'handle_new_enrollment'), 10, 2);
        add_action('tutor_lesson_completed_after', array($this, 'handle_lesson_completion'), 10, 2);
        add_action('tutor_quiz_finished', array($this, 'handle_quiz_completion'), 10, 1);
        
        // Admin settings for notifications
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        
        // AJAX handlers for notification preferences
        add_action('wp_ajax_tutor_advanced_toggle_notifications', array($this, 'handle_toggle_notifications_ajax'));
    }
    
    /**
     * Schedule daily digest emails
     */
    public function schedule_daily_digest() {
        if (!wp_next_scheduled('tutor_advanced_tracking_daily_digest')) {
            wp_schedule_event(strtotime('08:00:00'), 'daily', 'tutor_advanced_tracking_daily_digest');
        }
    }
    
    /**
     * Schedule weekly summary emails
     */
    public function schedule_weekly_summary() {
        if (!wp_next_scheduled('tutor_advanced_tracking_weekly_summary')) {
            // Schedule for Monday 8 AM
            $next_monday = strtotime('next monday 08:00:00');
            wp_schedule_event($next_monday, 'weekly', 'tutor_advanced_tracking_weekly_summary');
        }
    }
    
    /**
     * Send daily digest email
     */
    public function send_daily_digest() {
        $enabled = get_option('tutor_advanced_notifications_daily_digest', true);
        if (!$enabled) {
            return;
        }
        
        $recipients = $this->get_notification_recipients();
        if (empty($recipients)) {
            return;
        }
        
        $data = $this->get_daily_digest_data();
        
        foreach ($recipients as $recipient) {
            $this->send_daily_digest_email($recipient, $data);
        }
    }
    
    /**
     * Send weekly summary email
     */
    public function send_weekly_summary() {
        $enabled = get_option('tutor_advanced_notifications_weekly_summary', true);
        if (!$enabled) {
            return;
        }
        
        $recipients = $this->get_notification_recipients();
        if (empty($recipients)) {
            return;
        }
        
        $data = $this->get_weekly_summary_data();
        
        foreach ($recipients as $recipient) {
            $this->send_weekly_summary_email($recipient, $data);
        }
    }
    
    /**
     * Handle new enrollment
     */
    public function handle_new_enrollment($course_id, $user_id) {
        $enabled = get_option('tutor_advanced_notifications_new_enrollments', true);
        if (!$enabled) {
            return;
        }
        
        // Send notification to course instructor
        $course = get_post($course_id);
        if ($course) {
            $instructor = get_userdata($course->post_author);
            $student = get_userdata($user_id);
            
            if ($instructor && $student) {
                $this->send_new_enrollment_email($instructor, $student, $course);
            }
        }
    }
    
    /**
     * Handle lesson completion
     */
    public function handle_lesson_completion($lesson_id, $user_id) {
        $enabled = get_option('tutor_advanced_notifications_lesson_completions', false);
        if (!$enabled) {
            return;
        }
        
        $lesson = get_post($lesson_id);
        $course_id = $lesson->post_parent;
        $course = get_post($course_id);
        
        if ($course) {
            $instructor = get_userdata($course->post_author);
            $student = get_userdata($user_id);
            
            if ($instructor && $student) {
                $this->send_lesson_completion_email($instructor, $student, $lesson, $course);
            }
        }
    }
    
    /**
     * Handle quiz completion
     */
    public function handle_quiz_completion($quiz_attempt_id) {
        global $wpdb;
        
        $enabled_passed = get_option('tutor_advanced_notifications_quiz_passed', true);
        $enabled_failed = get_option('tutor_advanced_notifications_quiz_failed', true);
        
        if (!$enabled_passed && !$enabled_failed) {
            return;
        }
        
        // Get quiz attempt details
        $attempt = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}tutor_quiz_attempts WHERE attempt_id = %d",
            $quiz_attempt_id
        ));
        
        if (!$attempt) {
            return;
        }
        
        $quiz = get_post($attempt->quiz_id);
        $course_id = $quiz->post_parent;
        $course = get_post($course_id);
        
        if ($course) {
            $instructor = get_userdata($course->post_author);
            $student = get_userdata($attempt->user_id);
            
            if ($instructor && $student) {
                $score_percentage = $attempt->total_marks > 0 ? 
                    ($attempt->earned_marks / $attempt->total_marks) * 100 : 0;
                
                $passing_grade = get_post_meta($attempt->quiz_id, '_tutor_quiz_pass_mark', true) ?: 80;
                $passed = $score_percentage >= $passing_grade;
                
                if (($passed && $enabled_passed) || (!$passed && $enabled_failed)) {
                    $this->send_quiz_completion_email($instructor, $student, $quiz, $course, $attempt, $passed);
                }
            }
        }
    }
    
    /**
     * Get notification recipients (admins and instructors)
     */
    private function get_notification_recipients() {
        $recipients = array();
        
        // Get all admins
        $admins = get_users(array('role' => 'administrator'));
        foreach ($admins as $admin) {
            $enabled = get_user_meta($admin->ID, 'tutor_advanced_notifications_enabled', true);
            if ($enabled !== '0') {
                $recipients[] = $admin;
            }
        }
        
        // Get all instructors
        $instructors = get_users(array('role' => 'tutor_instructor'));
        foreach ($instructors as $instructor) {
            $enabled = get_user_meta($instructor->ID, 'tutor_advanced_notifications_enabled', true);
            if ($enabled !== '0') {
                $recipients[] = $instructor;
            }
        }
        
        return $recipients;
    }
    
    /**
     * Get daily digest data
     */
    private function get_daily_digest_data() {
        global $wpdb;
        
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $today = date('Y-m-d');
        
        // New enrollments yesterday
        $new_enrollments = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}tutor_enrollments 
             WHERE DATE(enrollment_date) = %s",
            $yesterday
        ));
        
        // Quiz attempts yesterday
        $quiz_attempts = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}tutor_quiz_attempts 
             WHERE DATE(attempt_started_at) = %s 
             AND attempt_status = 'attempt_ended'",
            $yesterday
        ));
        
        // Course completions yesterday
        $completions = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}tutor_enrollments 
             WHERE DATE(completion_date) = %s",
            $yesterday
        ));
        
        // Top performing courses yesterday
        $top_courses = $wpdb->get_results($wpdb->prepare(
            "SELECT p.post_title, COUNT(e.user_id) as new_students
             FROM {$wpdb->prefix}tutor_enrollments e
             JOIN {$wpdb->posts} p ON e.course_id = p.ID
             WHERE DATE(e.enrollment_date) = %s
             GROUP BY e.course_id
             ORDER BY new_students DESC
             LIMIT 5",
            $yesterday
        ));
        
        return array(
            'date' => $yesterday,
            'new_enrollments' => $new_enrollments,
            'quiz_attempts' => $quiz_attempts,
            'completions' => $completions,
            'top_courses' => $top_courses
        );
    }
    
    /**
     * Get weekly summary data
     */
    private function get_weekly_summary_data() {
        global $wpdb;
        
        $week_start = date('Y-m-d', strtotime('-7 days'));
        $today = date('Y-m-d');
        
        // Weekly statistics
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(DISTINCT e.user_id) as new_students,
                COUNT(DISTINCT e.course_id) as active_courses,
                COUNT(DISTINCT qa.attempt_id) as quiz_attempts,
                AVG(qa.earned_marks / qa.total_marks * 100) as avg_quiz_score
             FROM {$wpdb->prefix}tutor_enrollments e
             LEFT JOIN {$wpdb->prefix}tutor_quiz_attempts qa ON e.user_id = qa.user_id
             WHERE DATE(e.enrollment_date) BETWEEN %s AND %s
             OR DATE(qa.attempt_started_at) BETWEEN %s AND %s",
            $week_start, $today, $week_start, $today
        ));
        
        // Course engagement this week
        $course_engagement = $wpdb->get_results($wpdb->prepare(
            "SELECT p.post_title, COUNT(e.user_id) as enrollments, 
                    AVG(qa.earned_marks / qa.total_marks * 100) as avg_score
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->prefix}tutor_enrollments e ON p.ID = e.course_id
                AND DATE(e.enrollment_date) BETWEEN %s AND %s
             LEFT JOIN {$wpdb->prefix}tutor_quiz_attempts qa ON p.ID = qa.course_id
                AND DATE(qa.attempt_started_at) BETWEEN %s AND %s
             WHERE p.post_type = 'courses' AND p.post_status = 'publish'
             GROUP BY p.ID
             HAVING enrollments > 0 OR avg_score IS NOT NULL
             ORDER BY enrollments DESC, avg_score DESC
             LIMIT 10",
            $week_start, $today, $week_start, $today
        ));
        
        return array(
            'week_start' => $week_start,
            'week_end' => $today,
            'stats' => $stats,
            'course_engagement' => $course_engagement
        );
    }
    
    /**
     * Send daily digest email
     */
    private function send_daily_digest_email($recipient, $data) {
        $subject = sprintf(__('[%s] Daily Learning Activity Digest - %s', 'tutor-lms-advanced-tracking'), 
                          get_bloginfo('name'), 
                          date('F j, Y', strtotime($data['date'])));
        
        $message = $this->get_daily_digest_template($recipient, $data);
        
        $from_name = str_replace(array("\r", "\n"), '', get_bloginfo('name'));
        $from_email = sanitize_email(get_option('admin_email'));
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>'
        );
        
        wp_mail($recipient->user_email, $subject, $message, $headers);
    }
    
    /**
     * Send weekly summary email
     */
    private function send_weekly_summary_email($recipient, $data) {
        $subject = sprintf(__('[%s] Weekly Learning Summary - %s to %s', 'tutor-lms-advanced-tracking'), 
                          get_bloginfo('name'), 
                          date('M j', strtotime($data['week_start'])),
                          date('M j, Y', strtotime($data['week_end'])));
        
        $message = $this->get_weekly_summary_template($recipient, $data);
        
        $from_name = str_replace(array("\r", "\n"), '', get_bloginfo('name'));
        $from_email = sanitize_email(get_option('admin_email'));
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>'
        );
        
        wp_mail($recipient->user_email, $subject, $message, $headers);
    }
    
    /**
     * Send new enrollment email
     */
    private function send_new_enrollment_email($instructor, $student, $course) {
        $subject = sprintf(__('[%s] New student enrolled: %s', 'tutor-lms-advanced-tracking'), 
                          get_bloginfo('name'), 
                          $course->post_title);
        
        $message = sprintf(
            __('Hi %s,<br><br>A new student has enrolled in your course:<br><br><strong>Student:</strong> %s<br><strong>Course:</strong> %s<br><strong>Enrollment Date:</strong> %s<br><br>You can view the student\'s progress in your dashboard.<br><br>Best regards,<br>%s', 'tutor-lms-advanced-tracking'),
            $instructor->display_name,
            $student->display_name,
            $course->post_title,
            current_time('F j, Y \a\t g:i A'),
            get_bloginfo('name')
        );
        
        $from_name = str_replace(array("\r", "\n"), '', get_bloginfo('name'));
        $from_email = sanitize_email(get_option('admin_email'));
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>'
        );
        
        wp_mail($instructor->user_email, $subject, $message, $headers);
    }
    
    /**
     * Send lesson completion email
     */
    private function send_lesson_completion_email($instructor, $student, $lesson, $course) {
        $subject = sprintf(__('[%s] Lesson completed: %s', 'tutor-lms-advanced-tracking'), 
                          get_bloginfo('name'), 
                          $lesson->post_title);
        
        $message = sprintf(
            __('Hi %s,<br><br>A student has completed a lesson in your course:<br><br><strong>Student:</strong> %s<br><strong>Lesson:</strong> %s<br><strong>Course:</strong> %s<br><strong>Completion Date:</strong> %s<br><br>Great progress!<br><br>Best regards,<br>%s', 'tutor-lms-advanced-tracking'),
            $instructor->display_name,
            $student->display_name,
            $lesson->post_title,
            $course->post_title,
            current_time('F j, Y \a\t g:i A'),
            get_bloginfo('name')
        );
        
        $from_name = str_replace(array("\r", "\n"), '', get_bloginfo('name'));
        $from_email = sanitize_email(get_option('admin_email'));
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>'
        );
        
        wp_mail($instructor->user_email, $subject, $message, $headers);
    }
    
    /**
     * Send quiz completion email
     */
    private function send_quiz_completion_email($instructor, $student, $quiz, $course, $attempt, $passed) {
        $score_percentage = $attempt->total_marks > 0 ? 
            round(($attempt->earned_marks / $attempt->total_marks) * 100, 1) : 0;
        
        $status = $passed ? __('PASSED', 'tutor-lms-advanced-tracking') : __('FAILED', 'tutor-lms-advanced-tracking');
        $status_color = $passed ? '#28a745' : '#dc3545';
        
        $subject = sprintf(__('[%s] Quiz %s: %s', 'tutor-lms-advanced-tracking'), 
                          get_bloginfo('name'), 
                          $status,
                          $quiz->post_title);
        
        $message = sprintf(
            __('Hi %s,<br><br>A student has completed a quiz in your course:<br><br><strong>Student:</strong> %s<br><strong>Quiz:</strong> %s<br><strong>Course:</strong> %s<br><strong>Score:</strong> %s%% (%s/%s points)<br><strong>Status:</strong> <span style="color: %s; font-weight: bold;">%s</span><br><strong>Completion Date:</strong> %s<br><br>You can view detailed results in your dashboard.<br><br>Best regards,<br>%s', 'tutor-lms-advanced-tracking'),
            $instructor->display_name,
            $student->display_name,
            $quiz->post_title,
            $course->post_title,
            $score_percentage,
            $attempt->earned_marks,
            $attempt->total_marks,
            $status_color,
            $status,
            current_time('F j, Y \a\t g:i A'),
            get_bloginfo('name')
        );
        
        $from_name = str_replace(array("\r", "\n"), '', get_bloginfo('name'));
        $from_email = sanitize_email(get_option('admin_email'));
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>'
        );
        
        wp_mail($instructor->user_email, $subject, $message, $headers);
    }
    
    /**
     * Get daily digest email template
     */
    private function get_daily_digest_template($recipient, $data) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #007cba; color: white; padding: 20px; text-align: center; }
                .content { background: #f9f9f9; padding: 20px; }
                .stats { display: flex; justify-content: space-around; margin: 20px 0; }
                .stat-box { background: white; padding: 15px; border-radius: 5px; text-align: center; min-width: 100px; }
                .stat-number { font-size: 24px; font-weight: bold; color: #007cba; }
                .courses-list { background: white; padding: 15px; border-radius: 5px; margin-top: 20px; }
                .footer { text-align: center; color: #666; font-size: 12px; margin-top: 20px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1><?php echo sprintf(__('Daily Activity Digest - %s', 'tutor-lms-advanced-tracking'), date('F j, Y', strtotime($data['date']))); ?></h1>
                    <p><?php echo sprintf(__('Hello %s!', 'tutor-lms-advanced-tracking'), $recipient->display_name); ?></p>
                </div>
                
                <div class="content">
                    <h2><?php _e('Yesterday\'s Learning Activity', 'tutor-lms-advanced-tracking'); ?></h2>
                    
                    <div class="stats">
                        <div class="stat-box">
                            <div class="stat-number"><?php echo $data['new_enrollments']; ?></div>
                            <div><?php _e('New Enrollments', 'tutor-lms-advanced-tracking'); ?></div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-number"><?php echo $data['quiz_attempts']; ?></div>
                            <div><?php _e('Quiz Attempts', 'tutor-lms-advanced-tracking'); ?></div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-number"><?php echo $data['completions']; ?></div>
                            <div><?php _e('Course Completions', 'tutor-lms-advanced-tracking'); ?></div>
                        </div>
                    </div>
                    
                    <?php if (!empty($data['top_courses'])): ?>
                    <div class="courses-list">
                        <h3><?php _e('Most Popular Courses Yesterday', 'tutor-lms-advanced-tracking'); ?></h3>
                        <ul>
                            <?php foreach ($data['top_courses'] as $course): ?>
                            <li><strong><?php echo esc_html($course->post_title); ?></strong> - <?php echo $course->new_students; ?> <?php _e('new students', 'tutor-lms-advanced-tracking'); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="footer">
                    <p><?php _e('This is an automated email from Advanced Tutor LMS Stats Dashboard', 'tutor-lms-advanced-tracking'); ?></p>
                    <p><a href="<?php echo admin_url('admin.php?page=tutor-advanced-notifications'); ?>"><?php _e('Manage notification preferences', 'tutor-lms-advanced-tracking'); ?></a></p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get weekly summary email template
     */
    private function get_weekly_summary_template($recipient, $data) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #28a745; color: white; padding: 20px; text-align: center; }
                .content { background: #f9f9f9; padding: 20px; }
                .stats { display: flex; justify-content: space-around; margin: 20px 0; flex-wrap: wrap; }
                .stat-box { background: white; padding: 15px; border-radius: 5px; text-align: center; min-width: 120px; margin: 5px; }
                .stat-number { font-size: 20px; font-weight: bold; color: #28a745; }
                .courses-table { background: white; padding: 15px; border-radius: 5px; margin-top: 20px; width: 100%; }
                .courses-table table { width: 100%; border-collapse: collapse; }
                .courses-table th, .courses-table td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
                .courses-table th { background-color: #f5f5f5; }
                .footer { text-align: center; color: #666; font-size: 12px; margin-top: 20px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1><?php echo sprintf(__('Weekly Summary - %s to %s', 'tutor-lms-advanced-tracking'), 
                        date('M j', strtotime($data['week_start'])), 
                        date('M j, Y', strtotime($data['week_end']))); ?></h1>
                    <p><?php echo sprintf(__('Hello %s!', 'tutor-lms-advanced-tracking'), $recipient->display_name); ?></p>
                </div>
                
                <div class="content">
                    <h2><?php _e('This Week\'s Learning Overview', 'tutor-lms-advanced-tracking'); ?></h2>
                    
                    <div class="stats">
                        <div class="stat-box">
                            <div class="stat-number"><?php echo $data['stats']->new_students ?: 0; ?></div>
                            <div><?php _e('New Students', 'tutor-lms-advanced-tracking'); ?></div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-number"><?php echo $data['stats']->active_courses ?: 0; ?></div>
                            <div><?php _e('Active Courses', 'tutor-lms-advanced-tracking'); ?></div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-number"><?php echo $data['stats']->quiz_attempts ?: 0; ?></div>
                            <div><?php _e('Quiz Attempts', 'tutor-lms-advanced-tracking'); ?></div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-number"><?php echo $data['stats']->avg_quiz_score ? round($data['stats']->avg_quiz_score, 1) . '%' : 'N/A'; ?></div>
                            <div><?php _e('Avg. Quiz Score', 'tutor-lms-advanced-tracking'); ?></div>
                        </div>
                    </div>
                    
                    <?php if (!empty($data['course_engagement'])): ?>
                    <div class="courses-table">
                        <h3><?php _e('Course Engagement This Week', 'tutor-lms-advanced-tracking'); ?></h3>
                        <table>
                            <thead>
                                <tr>
                                    <th><?php _e('Course', 'tutor-lms-advanced-tracking'); ?></th>
                                    <th><?php _e('New Enrollments', 'tutor-lms-advanced-tracking'); ?></th>
                                    <th><?php _e('Avg. Quiz Score', 'tutor-lms-advanced-tracking'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($data['course_engagement'] as $course): ?>
                                <tr>
                                    <td><?php echo esc_html($course->post_title); ?></td>
                                    <td><?php echo $course->enrollments ?: 0; ?></td>
                                    <td><?php echo $course->avg_score ? round($course->avg_score, 1) . '%' : 'N/A'; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="footer">
                    <p><?php _e('This is an automated weekly summary from Advanced Tutor LMS Stats Dashboard', 'tutor-lms-advanced-tracking'); ?></p>
                    <p><a href="<?php echo admin_url('admin.php?page=tutor-advanced-notifications'); ?>"><?php _e('Manage notification preferences', 'tutor-lms-advanced-tracking'); ?></a></p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Add admin menu for notification settings
     */
    public function add_admin_menu() {
        add_submenu_page(
            'tutor-lms',
            __('Advanced Stats Notifications', 'tutor-lms-advanced-tracking'),
            __('Stats Notifications', 'tutor-lms-advanced-tracking'),
            'manage_options',
            'tutor-advanced-notifications',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('tutor_advanced_notifications', 'tutor_advanced_notifications_daily_digest');
        register_setting('tutor_advanced_notifications', 'tutor_advanced_notifications_weekly_summary');
        register_setting('tutor_advanced_notifications', 'tutor_advanced_notifications_new_enrollments');
        register_setting('tutor_advanced_notifications', 'tutor_advanced_notifications_lesson_completions');
        register_setting('tutor_advanced_notifications', 'tutor_advanced_notifications_quiz_passed');
        register_setting('tutor_advanced_notifications', 'tutor_advanced_notifications_quiz_failed');
    }
    
    /**
     * Admin page for notification settings
     */
    public function admin_page() {
        // Handle form submission with nonce verification
        if (isset($_POST['submit']) && isset($_POST['tutor_notifications_nonce'])) {
            // Verify nonce for security
            if (!wp_verify_nonce($_POST['tutor_notifications_nonce'], 'tutor_notifications_settings')) {
                wp_die(__('Security check failed. Please refresh the page and try again.', 'tutor-lms-advanced-tracking'));
            }

            // Verify user capability
            if (!current_user_can('manage_options')) {
                wp_die(__('You do not have permission to change these settings.', 'tutor-lms-advanced-tracking'));
            }

            update_option('tutor_advanced_notifications_daily_digest', isset($_POST['daily_digest']));
            update_option('tutor_advanced_notifications_weekly_summary', isset($_POST['weekly_summary']));
            update_option('tutor_advanced_notifications_new_enrollments', isset($_POST['new_enrollments']));
            update_option('tutor_advanced_notifications_lesson_completions', isset($_POST['lesson_completions']));
            update_option('tutor_advanced_notifications_quiz_passed', isset($_POST['quiz_passed']));
            update_option('tutor_advanced_notifications_quiz_failed', isset($_POST['quiz_failed']));

            echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved!', 'tutor-lms-advanced-tracking') . '</p></div>';
        }
        
        $daily_digest = get_option('tutor_advanced_notifications_daily_digest', true);
        $weekly_summary = get_option('tutor_advanced_notifications_weekly_summary', true);
        $new_enrollments = get_option('tutor_advanced_notifications_new_enrollments', true);
        $lesson_completions = get_option('tutor_advanced_notifications_lesson_completions', false);
        $quiz_passed = get_option('tutor_advanced_notifications_quiz_passed', true);      
        $quiz_failed = get_option('tutor_advanced_notifications_quiz_failed', true);
        ?>
        <div class="wrap">
            <h1><?php _e('Advanced Stats Notification Settings', 'tutor-lms-advanced-tracking'); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('tutor_notifications_settings', 'tutor_notifications_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Daily Digest Email', 'tutor-lms-advanced-tracking'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="daily_digest" value="1" <?php checked($daily_digest); ?>>
                                <?php _e('Send daily digest emails to administrators and instructors', 'tutor-lms-advanced-tracking'); ?>
                            </label>
                            <p class="description"><?php _e('Sent every day at 8:00 AM with yesterday\'s activity summary', 'tutor-lms-advanced-tracking'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Weekly Summary Email', 'tutor-lms-advanced-tracking'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="weekly_summary" value="1" <?php checked($weekly_summary); ?>>
                                <?php _e('Send weekly summary emails to administrators and instructors', 'tutor-lms-advanced-tracking'); ?>
                            </label>
                            <p class="description"><?php _e('Sent every Monday at 8:00 AM with the previous week\'s overview', 'tutor-lms-advanced-tracking'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('New Enrollment Notifications', 'tutor-lms-advanced-tracking'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="new_enrollments" value="1" <?php checked($new_enrollments); ?>>
                                <?php _e('Notify instructors when students enroll in their courses', 'tutor-lms-advanced-tracking'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Lesson Completion Notifications', 'tutor-lms-advanced-tracking'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="lesson_completions" value="1" <?php checked($lesson_completions); ?>>
                                <?php _e('Notify instructors when students complete lessons (can be high volume)', 'tutor-lms-advanced-tracking'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Quiz Pass Notifications', 'tutor-lms-advanced-tracking'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="quiz_passed" value="1" <?php checked($quiz_passed); ?>>
                                <?php _e('Notify instructors when students pass quizzes', 'tutor-lms-advanced-tracking'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Quiz Fail Notifications', 'tutor-lms-advanced-tracking'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="quiz_failed" value="1" <?php checked($quiz_failed); ?>>
                                <?php _e('Notify instructors when students fail quizzes', 'tutor-lms-advanced-tracking'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
            
            <h2><?php _e('Test Notifications', 'tutor-lms-advanced-tracking'); ?></h2>
            <p><?php _e('Send test emails to verify your notification settings:', 'tutor-lms-advanced-tracking'); ?></p>
            <button type="button" class="button" id="test-daily-digest"><?php _e('Send Test Daily Digest', 'tutor-lms-advanced-tracking'); ?></button>
            <button type="button" class="button" id="test-weekly-summary"><?php _e('Send Test Weekly Summary', 'tutor-lms-advanced-tracking'); ?></button>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#test-daily-digest').on('click', function() {
                if (confirm('<?php _e('Send test daily digest to your email?', 'tutor-lms-advanced-tracking'); ?>')) {
                    // Implementation for test email
                    alert('<?php _e('Test email functionality would be implemented here', 'tutor-lms-advanced-tracking'); ?>');
                }
            });
            
            $('#test-weekly-summary').on('click', function() {
                if (confirm('<?php _e('Send test weekly summary to your email?', 'tutor-lms-advanced-tracking'); ?>')) {
                    // Implementation for test email
                    alert('<?php _e('Test email functionality would be implemented here', 'tutor-lms-advanced-tracking'); ?>');
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Handle toggle notifications AJAX
     */
    public function handle_toggle_notifications_ajax() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'tutor_advanced_tracking_toggle_notifications')) {
            wp_die(__('Security check failed', 'tutor-lms-advanced-tracking'));
        }
        
        $user_id = get_current_user_id();
        $enabled = sanitize_text_field($_POST['enabled'] ?? '1');
        
        update_user_meta($user_id, 'tutor_advanced_notifications_enabled', $enabled);
        
        wp_send_json_success(array(
            'message' => $enabled === '1' ? 
                __('Notifications enabled', 'tutor-lms-advanced-tracking') : 
                __('Notifications disabled', 'tutor-lms-advanced-tracking')
        ));
    }
}