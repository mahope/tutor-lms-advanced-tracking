<?php
/**
 * Export functionality for Advanced Tutor LMS Stats Dashboard
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class TutorAdvancedTracking_Export {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_ajax_tutor_advanced_export_csv', array($this, 'handle_csv_export'));
        add_action('wp_ajax_tutor_advanced_export_pdf', array($this, 'handle_pdf_export'));
        
        // Add export buttons to dashboard
        add_action('tutor_advanced_tracking_dashboard_header', array($this, 'add_export_buttons'));
    }
    
    /**
     * Add export buttons to dashboard header
     */
    public function add_export_buttons() {
        ?>
        <div class="export-buttons">
            <button type="button" class="btn btn-secondary export-csv" data-type="courses">
                <i class="fas fa-download"></i> <?php _e('Export Courses CSV', 'tutor-lms-advanced-tracking'); ?>
            </button>
            <button type="button" class="btn btn-secondary export-csv" data-type="students">
                <i class="fas fa-download"></i> <?php _e('Export Students CSV', 'tutor-lms-advanced-tracking'); ?>
            </button>
            <button type="button" class="btn btn-secondary export-pdf" data-type="report">
                <i class="fas fa-file-pdf"></i> <?php _e('Export PDF Report', 'tutor-lms-advanced-tracking'); ?>
            </button>
        </div>
        <?php
    }
    
    /**
     * Handle CSV export AJAX request
     */
    public function handle_csv_export() {
        // Verify nonce and capabilities
        if (!$this->verify_export_request()) {
            wp_die(__('Unauthorized access', 'tutor-lms-advanced-tracking'), '', array('response' => 403));
        }
        
        // Rate limiting check
        if (!$this->check_export_rate_limit()) {
            wp_die(__('Too many export requests. Please wait before requesting another export.', 'tutor-lms-advanced-tracking'), '', array('response' => 429));
        }
        
        $export_type = sanitize_text_field($_POST['export_type'] ?? 'courses');
        $course_id = intval($_POST['course_id'] ?? 0);
        
        switch ($export_type) {
            case 'courses':
                $this->export_courses_csv();
                break;
            case 'students':
                $this->export_students_csv($course_id);
                break;
            case 'course_details':
                $this->export_course_details_csv($course_id);
                break;
            case 'quiz_results':
                $this->export_quiz_results_csv($course_id);
                break;
            default:
                wp_die(__('Invalid export type', 'tutor-lms-advanced-tracking'), '', array('response' => 400));
        }
    }
    
    /**
     * Handle PDF export AJAX request
     */
    public function handle_pdf_export() {
        // Verify nonce and capabilities
        if (!$this->verify_export_request()) {
            wp_die(__('Unauthorized access', 'tutor-lms-advanced-tracking'), '', array('response' => 403));
        }
        
        // Rate limiting check
        if (!$this->check_export_rate_limit()) {
            wp_die(__('Too many export requests. Please wait before requesting another export.', 'tutor-lms-advanced-tracking'), '', array('response' => 429));
        }
        
        $export_type = sanitize_text_field($_POST['export_type'] ?? 'report');
        $course_id = intval($_POST['course_id'] ?? 0);
        
        switch ($export_type) {
            case 'report':
                $this->export_dashboard_pdf();
                break;
            case 'course_report':
                $this->export_course_pdf($course_id);
                break;
            case 'student_report':
                $user_id = intval($_POST['user_id'] ?? 0);
                $this->export_student_pdf($user_id, $course_id);
                break;
            default:
                wp_die(__('Invalid export type', 'tutor-lms-advanced-tracking'), '', array('response' => 400));
        }
    }
    
    /**
     * Sanitize filename for safe download
     */
    private function sanitize_filename($filename) {
        // Remove any characters that could be harmful in headers
        $safe_filename = preg_replace('/[^a-zA-Z0-9\-_\.]/', '', $filename);
        // Ensure it's not empty and has a reasonable length
        if (empty($safe_filename) || strlen($safe_filename) > 200) {
            $safe_filename = 'export-' . date('Y-m-d-H-i-s') . '.csv';
        }
        return $safe_filename;
    }
    
    /**
     * Verify export request
     */
    private function verify_export_request() {
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'tutor_advanced_tracking_export_' . get_current_user_id())) {
            return false;
        }
        
        // Check capabilities
        if (!current_user_can('manage_options') && !current_user_can('tutor_instructor')) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Export courses overview to CSV
     */
    private function export_courses_csv() {
        $dashboard = new TutorAdvancedTracking_Dashboard();
        $courses = $dashboard->get_courses();
        
        // Set headers for CSV download
        $filename = 'tutor-courses-overview-' . date('Y-m-d-H-i-s') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $this->sanitize_filename($filename) . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Create output stream
        $output = fopen('php://output', 'w');
        
        // Add BOM for UTF-8 Excel compatibility
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // CSV Headers
        fputcsv($output, array(
            __('Course ID', 'tutor-lms-advanced-tracking'),
            __('Course Title', 'tutor-lms-advanced-tracking'),
            __('Instructor', 'tutor-lms-advanced-tracking'),
            __('Total Students', 'tutor-lms-advanced-tracking'),
            __('Average Progression (%)', 'tutor-lms-advanced-tracking'),
            __('Average Quiz Score (%)', 'tutor-lms-advanced-tracking'),
            __('Export Date', 'tutor-lms-advanced-tracking')
        ));
        
        // Add course data
        foreach ($courses as $course) {
            fputcsv($output, array(
                $course['id'],
                $course['title'],
                $course['instructor'],
                $course['student_count'],
                $course['avg_progression'],
                $course['avg_quiz_score'],
                current_time('Y-m-d H:i:s')
            ));
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Export students data to CSV
     */
    private function export_students_csv($course_id = 0) {
        global $wpdb;
        
        $filename = 'tutor-students-' . ($course_id ? 'course-' . $course_id . '-' : '') . date('Y-m-d-H-i-s') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $this->sanitize_filename($filename) . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // CSV Headers
        fputcsv($output, array(
            __('Student ID', 'tutor-lms-advanced-tracking'),
            __('Student Name', 'tutor-lms-advanced-tracking'),
            __('Email', 'tutor-lms-advanced-tracking'),
            __('Course Title', 'tutor-lms-advanced-tracking'),
            __('Enrollment Date', 'tutor-lms-advanced-tracking'),
            __('Progression (%)', 'tutor-lms-advanced-tracking'),
            __('Quiz Average (%)', 'tutor-lms-advanced-tracking'),
            __('Completion Status', 'tutor-lms-advanced-tracking'),
            __('Last Activity', 'tutor-lms-advanced-tracking'),
            __('Export Date', 'tutor-lms-advanced-tracking')
        ));
        
        // Get student data
        if ($course_id) {
            $course_stats = new TutorAdvancedTracking_CourseStats();
            $course_data = $course_stats->get_course_details($course_id);
            
            if ($course_data && isset($course_data['students'])) {
                foreach ($course_data['students'] as $student) {
                    fputcsv($output, array(
                        $student['id'],
                        $student['name'],
                        $this->mask_email($student['email']),
                        $course_data['title'],
                        $student['enrollment_date'],
                        $student['progression'],
                        $student['quiz_average'],
                        $student['completion_status'],
                        $student['last_activity'],
                        current_time('Y-m-d H:i:s')
                    ));
                }
            }
        } else {
            // Export all students across all courses (admin only)
            if (!current_user_can('manage_options')) {
                wp_die(__('Unauthorized access', 'tutor-lms-advanced-tracking'), '', array('response' => 403));
            }
            
            $dashboard = new TutorAdvancedTracking_Dashboard();
            $courses = $dashboard->get_courses();
            
            foreach ($courses as $course) {
                $course_stats = new TutorAdvancedTracking_CourseStats();
                $course_data = $course_stats->get_course_details($course['id']);
                
                if ($course_data && isset($course_data['students'])) {
                    foreach ($course_data['students'] as $student) {
                        fputcsv($output, array(
                            $student['id'],
                            $student['name'],
                            $this->mask_email($student['email']),
                            $course_data['title'],
                            $student['enrollment_date'],
                            $student['progression'],
                            $student['quiz_average'],
                            $student['completion_status'],
                            $student['last_activity'],
                            current_time('Y-m-d H:i:s')
                        ));
                    }
                }
            }
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Export course details to CSV
     */
    private function export_course_details_csv($course_id) {
        if (!$course_id) {
            wp_die(__('Invalid course ID', 'tutor-lms-advanced-tracking'), '', array('response' => 400));
        }
        
        $course_stats = new TutorAdvancedTracking_CourseStats();
        $course_data = $course_stats->get_course_details($course_id);
        
        if (!$course_data) {
            wp_die(__('Course not found or access denied', 'tutor-lms-advanced-tracking'), '', array('response' => 404));
        }
        
        $filename = 'course-' . $course_id . '-details-' . date('Y-m-d-H-i-s') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $this->sanitize_filename($filename) . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Course overview section
        fputcsv($output, array(__('COURSE OVERVIEW', 'tutor-lms-advanced-tracking')));
        fputcsv($output, array(__('Course Title', 'tutor-lms-advanced-tracking'), $course_data['title']));
        fputcsv($output, array(__('Instructor', 'tutor-lms-advanced-tracking'), $course_data['instructor']));
        fputcsv($output, array(__('Total Students', 'tutor-lms-advanced-tracking'), $course_data['stats']['total_students']));
        fputcsv($output, array(__('Completed Students', 'tutor-lms-advanced-tracking'), $course_data['stats']['completed_students']));
        fputcsv($output, array(__('Completion Rate', 'tutor-lms-advanced-tracking'), $course_data['stats']['completion_rate'] . '%'));
        fputcsv($output, array()); // Empty row
        
        // Students section
        fputcsv($output, array(__('STUDENT DETAILS', 'tutor-lms-advanced-tracking')));
        fputcsv($output, array(
            __('Student Name', 'tutor-lms-advanced-tracking'),
            __('Email', 'tutor-lms-advanced-tracking'),
            __('Enrollment Date', 'tutor-lms-advanced-tracking'),
            __('Progression (%)', 'tutor-lms-advanced-tracking'),
            __('Quiz Average (%)', 'tutor-lms-advanced-tracking'),
            __('Completion Status', 'tutor-lms-advanced-tracking'),
            __('Last Activity', 'tutor-lms-advanced-tracking')
        ));
        
        foreach ($course_data['students'] as $student) {
            fputcsv($output, array(
                $student['name'],
                $this->mask_email($student['email']),
                $student['enrollment_date'],
                $student['progression'],
                $student['quiz_average'],
                $student['completion_status'],
                $student['last_activity']
            ));
        }
        
        fputcsv($output, array()); // Empty row
        
        // Quiz section
        if (!empty($course_data['quizzes'])) {
            fputcsv($output, array(__('QUIZ STATISTICS', 'tutor-lms-advanced-tracking')));
            fputcsv($output, array(
                __('Quiz Title', 'tutor-lms-advanced-tracking'),
                __('Total Attempts', 'tutor-lms-advanced-tracking'),
                __('Average Score (%)', 'tutor-lms-advanced-tracking'),
                __('Pass Rate (%)', 'tutor-lms-advanced-tracking')
            ));
            
            foreach ($course_data['quizzes'] as $quiz) {
                fputcsv($output, array(
                    $quiz['title'],
                    $quiz['attempts_count'],
                    $quiz['average_score'],
                    $quiz['pass_rate']
                ));
            }
        }
        
        fputcsv($output, array()); // Empty row
        fputcsv($output, array(__('Export Date', 'tutor-lms-advanced-tracking'), current_time('Y-m-d H:i:s')));
        
        fclose($output);
        exit;
    }
    
    /**
     * Export quiz results to CSV
     */
    private function export_quiz_results_csv($course_id) {
        global $wpdb;
        
        if (!$course_id) {
            wp_die(__('Invalid course ID', 'tutor-lms-advanced-tracking'), '', array('response' => 400));
        }
        
        // Get quiz results
        $quiz_results = $wpdb->get_results($wpdb->prepare(
            "SELECT qa.*, u.display_name, u.user_email, p.post_title as quiz_title
             FROM {$wpdb->prefix}tutor_quiz_attempts qa
             JOIN {$wpdb->users} u ON qa.user_id = u.ID
             JOIN {$wpdb->posts} p ON qa.quiz_id = p.ID
             WHERE p.post_parent = %d
             AND qa.attempt_status = 'attempt_ended'
             ORDER BY qa.attempt_started_at DESC",
            $course_id
        ));
        
        $filename = 'quiz-results-course-' . $course_id . '-' . date('Y-m-d-H-i-s') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $this->sanitize_filename($filename) . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // CSV Headers
        fputcsv($output, array(
            __('Student Name', 'tutor-lms-advanced-tracking'),
            __('Email', 'tutor-lms-advanced-tracking'),
            __('Quiz Title', 'tutor-lms-advanced-tracking'),
            __('Attempt Date', 'tutor-lms-advanced-tracking'),
            __('Score (%)', 'tutor-lms-advanced-tracking'),
            __('Points Earned', 'tutor-lms-advanced-tracking'),
            __('Total Points', 'tutor-lms-advanced-tracking'),
            __('Time Taken (minutes)', 'tutor-lms-advanced-tracking'),
            __('Status', 'tutor-lms-advanced-tracking')
        ));
        
        foreach ($quiz_results as $result) {
            $score_percentage = $result->total_marks > 0 ? round(($result->earned_marks / $result->total_marks) * 100, 2) : 0;
            $time_taken = $result->attempt_ended_at && $result->attempt_started_at ? 
                round((strtotime($result->attempt_ended_at) - strtotime($result->attempt_started_at)) / 60, 2) : 0;
            
            fputcsv($output, array(
                $result->display_name,
                $this->mask_email($result->user_email),
                $result->quiz_title,
                $result->attempt_started_at,
                $score_percentage,
                $result->earned_marks,
                $result->total_marks,
                $time_taken,
                $result->attempt_status
            ));
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Export dashboard overview as PDF
     */
    private function export_dashboard_pdf() {
        // Check if we have a PDF library available
        if (!$this->is_pdf_available()) {
            wp_die(__('PDF export not available. Please install a PDF library.', 'tutor-lms-advanced-tracking'), '', array('response' => 500));
        }
        
        $dashboard = new TutorAdvancedTracking_Dashboard();
        $courses = $dashboard->get_courses();
        
        // Generate HTML content for PDF
        $html = $this->generate_dashboard_pdf_html($courses);
        
        // Convert to PDF and output
        $this->convert_html_to_pdf($html, 'tutor-dashboard-report-' . date('Y-m-d-H-i-s') . '.pdf');
    }
    
    /**
     * Export course report as PDF
     */
    private function export_course_pdf($course_id) {
        if (!$course_id) {
            wp_die(__('Invalid course ID', 'tutor-lms-advanced-tracking'), '', array('response' => 400));
        }
        
        if (!$this->is_pdf_available()) {
            wp_die(__('PDF export not available. Please install a PDF library.', 'tutor-lms-advanced-tracking'), '', array('response' => 500));
        }
        
        $course_stats = new TutorAdvancedTracking_CourseStats();
        $course_data = $course_stats->get_course_details($course_id);
        
        if (!$course_data) {
            wp_die(__('Course not found or access denied', 'tutor-lms-advanced-tracking'), '', array('response' => 404));
        }
        
        // Generate HTML content for PDF
        $html = $this->generate_course_pdf_html($course_data);
        
        // Convert to PDF and output
        $this->convert_html_to_pdf($html, 'course-' . $course_id . '-report-' . date('Y-m-d-H-i-s') . '.pdf');
    }
    
    /**
     * Check if PDF generation is available
     */
    private function is_pdf_available() {
        // Check for TCPDF or DomPDF
        return class_exists('TCPDF') || class_exists('Dompdf\Dompdf');
    }
    
    /**
     * Generate HTML for dashboard PDF
     */
    private function generate_dashboard_pdf_html($courses) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title><?php _e('Tutor LMS Dashboard Report', 'tutor-lms-advanced-tracking'); ?></title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { text-align: center; margin-bottom: 30px; }
                .stats-overview { display: flex; justify-content: space-around; margin-bottom: 30px; }
                .stat-box { text-align: center; padding: 20px; border: 1px solid #ddd; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f5f5f5; font-weight: bold; }
                .footer { margin-top: 30px; text-align: center; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1><?php _e('Advanced Tutor LMS Dashboard Report', 'tutor-lms-advanced-tracking'); ?></h1>
                <p><?php echo sprintf(__('Generated on %s', 'tutor-lms-advanced-tracking'), current_time('F j, Y \a\t g:i A')); ?></p>
            </div>
            
            <div class="stats-overview">
                <div class="stat-box">
                    <h3><?php _e('Total Courses', 'tutor-lms-advanced-tracking'); ?></h3>
                    <p><?php echo count($courses); ?></p>
                </div>
                <div class="stat-box">
                    <h3><?php _e('Total Students', 'tutor-lms-advanced-tracking'); ?></h3>
                    <p><?php echo array_sum(array_column($courses, 'student_count')); ?></p>
                </div>
                <div class="stat-box">
                    <h3><?php _e('Avg. Progression', 'tutor-lms-advanced-tracking'); ?></h3>
                    <p><?php echo count($courses) > 0 ? round(array_sum(array_column($courses, 'avg_progression')) / count($courses), 1) : 0; ?>%</p>
                </div>
            </div>
            
            <h2><?php _e('Course Details', 'tutor-lms-advanced-tracking'); ?></h2>
            <table>
                <thead>
                    <tr>
                        <th><?php _e('Course', 'tutor-lms-advanced-tracking'); ?></th>
                        <th><?php _e('Instructor', 'tutor-lms-advanced-tracking'); ?></th>
                        <th><?php _e('Students', 'tutor-lms-advanced-tracking'); ?></th>
                        <th><?php _e('Avg. Progression', 'tutor-lms-advanced-tracking'); ?></th>
                        <th><?php _e('Avg. Quiz Score', 'tutor-lms-advanced-tracking'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($courses as $course): ?>
                    <tr>
                        <td><?php echo esc_html($course['title']); ?></td>
                        <td><?php echo esc_html($course['instructor']); ?></td>
                        <td><?php echo esc_html($course['student_count']); ?></td>
                        <td><?php echo esc_html($course['avg_progression']); ?>%</td>
                        <td><?php echo esc_html($course['avg_quiz_score']); ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div class="footer">
                <p><?php _e('This report was generated by Advanced Tutor LMS Stats Dashboard', 'tutor-lms-advanced-tracking'); ?></p>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Generate HTML for course PDF
     */
    private function generate_course_pdf_html($course_data) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title><?php echo sprintf(__('Course Report: %s', 'tutor-lms-advanced-tracking'), $course_data['title']); ?></title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { text-align: center; margin-bottom: 30px; }
                .stats-overview { display: flex; justify-content: space-around; margin-bottom: 30px; }
                .stat-box { text-align: center; padding: 15px; border: 1px solid #ddd; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                th, td { border: 1px solid #ddd; padding: 6px; text-align: left; font-size: 12px; }
                th { background-color: #f5f5f5; font-weight: bold; }
                .section-title { margin-top: 30px; margin-bottom: 15px; color: #333; }
                .footer { margin-top: 30px; text-align: center; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1><?php echo sprintf(__('Course Report: %s', 'tutor-lms-advanced-tracking'), esc_html($course_data['title'])); ?></h1>
                <p><?php echo sprintf(__('Instructor: %s', 'tutor-lms-advanced-tracking'), esc_html($course_data['instructor'])); ?></p>
                <p><?php echo sprintf(__('Generated on %s', 'tutor-lms-advanced-tracking'), current_time('F j, Y \a\t g:i A')); ?></p>
            </div>
            
            <div class="stats-overview">
                <div class="stat-box">
                    <h3><?php _e('Total Students', 'tutor-lms-advanced-tracking'); ?></h3>
                    <p><?php echo esc_html($course_data['stats']['total_students']); ?></p>
                </div>
                <div class="stat-box">
                    <h3><?php _e('Completed', 'tutor-lms-advanced-tracking'); ?></h3>
                    <p><?php echo esc_html($course_data['stats']['completed_students']); ?></p>
                </div>
                <div class="stat-box">
                    <h3><?php _e('Completion Rate', 'tutor-lms-advanced-tracking'); ?></h3>
                    <p><?php echo esc_html($course_data['stats']['completion_rate']); ?>%</p>
                </div>
            </div>
            
            <h2 class="section-title"><?php _e('Student Progress', 'tutor-lms-advanced-tracking'); ?></h2>
            <table>
                <thead>
                    <tr>
                        <th><?php _e('Student', 'tutor-lms-advanced-tracking'); ?></th>
                        <th><?php _e('Enrollment Date', 'tutor-lms-advanced-tracking'); ?></th>
                        <th><?php _e('Progression', 'tutor-lms-advanced-tracking'); ?></th>
                        <th><?php _e('Quiz Average', 'tutor-lms-advanced-tracking'); ?></th>
                        <th><?php _e('Status', 'tutor-lms-advanced-tracking'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($course_data['students'] as $student): ?>
                    <tr>
                        <td><?php echo esc_html($student['name']); ?></td>
                        <td><?php echo esc_html($student['enrollment_date']); ?></td>
                        <td><?php echo esc_html($student['progression']); ?>%</td>
                        <td><?php echo esc_html($student['quiz_average']); ?>%</td>
                        <td><?php echo esc_html($student['completion_status']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if (!empty($course_data['quizzes'])): ?>
            <h2 class="section-title"><?php _e('Quiz Statistics', 'tutor-lms-advanced-tracking'); ?></h2>
            <table>
                <thead>
                    <tr>
                        <th><?php _e('Quiz', 'tutor-lms-advanced-tracking'); ?></th>
                        <th><?php _e('Attempts', 'tutor-lms-advanced-tracking'); ?></th>
                        <th><?php _e('Average Score', 'tutor-lms-advanced-tracking'); ?></th>
                        <th><?php _e('Pass Rate', 'tutor-lms-advanced-tracking'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($course_data['quizzes'] as $quiz): ?>
                    <tr>
                        <td><?php echo esc_html($quiz['title']); ?></td>
                        <td><?php echo esc_html($quiz['attempts_count']); ?></td>
                        <td><?php echo esc_html($quiz['average_score']); ?>%</td>
                        <td><?php echo esc_html($quiz['pass_rate']); ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
            
            <div class="footer">
                <p><?php _e('This report was generated by Advanced Tutor LMS Stats Dashboard', 'tutor-lms-advanced-tracking'); ?></p>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Convert HTML to PDF using available library
     */
    private function convert_html_to_pdf($html, $filename) {
        if (class_exists('TCPDF')) {
            $this->convert_with_tcpdf($html, $filename);
        } elseif (class_exists('Dompdf\Dompdf')) {
            $this->convert_with_dompdf($html, $filename);
        } else {
            // Fallback: simple HTML output with print styles
            header('Content-type: text/html');
            header('Content-Disposition: attachment; filename="' . str_replace('.pdf', '.html', $filename) . '"');
            echo $html;
            exit;
        }
    }
    
    /**
     * Convert HTML to PDF using TCPDF
     */
    private function convert_with_tcpdf($html, $filename) {
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        // Set document information
        $pdf->SetCreator('Advanced Tutor LMS Stats');
        $pdf->SetAuthor(get_bloginfo('name'));
        $pdf->SetTitle('Tutor LMS Report');
        
        // Set margins
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(TRUE, 15);
        
        // Add page
        $pdf->AddPage();
        
        // Write HTML
        $pdf->writeHTML($html, true, false, true, false, '');
        
        // Output PDF
        $pdf->Output($filename, 'D');
        exit;
    }
    
    /**
     * Convert HTML to PDF using DomPDF
     */
    private function convert_with_dompdf($html, $filename) {
        $dompdf = new Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        // Output PDF
        $dompdf->stream($filename, array('Attachment' => true));
        exit;
    }
    
    /**
     * Mask email for privacy (non-admin users)
     */
    private function mask_email($email) {
        if (current_user_can('manage_options')) {
            return $email;
        }
        
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return $email;
        }
        
        $username = $parts[0];
        $domain = $parts[1];
        
        if (strlen($username) <= 2) {
            return $email;
        }
        
        $masked_username = substr($username, 0, 2) . str_repeat('*', strlen($username) - 2);
        return $masked_username . '@' . $domain;
    }
    
    /**
     * Rate limiting check for export requests
     */
    private function check_export_rate_limit() {
        $user_id = get_current_user_id();
        $transient_key = 'tutor_export_rate_limit_' . $user_id;
        $current_requests = get_transient($transient_key);
        
        if ($current_requests === false) {
            set_transient($transient_key, 1, 300); // 5 minutes
            return true;
        }
        
        if ($current_requests >= 5) { // Max 5 export requests per 5 minutes
            return false;
        }
        
        set_transient($transient_key, $current_requests + 1, 300);
        return true;
    }
}