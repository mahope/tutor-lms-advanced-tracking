<?php
/**
 * Optimized Course statistics class
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class TutorAdvancedTracking_CourseStats {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Constructor can be used for hooks if needed
    }
    
    /**
     * Get detailed course statistics
     */
    public function get_course_details($course_id) {
        global $wpdb;
        
        $course_id = (int) $course_id;
        
        // Check if course exists and user has permission
        if (!$this->can_view_course($course_id)) {
            return false;
        }
        
        // Get course basic info
        $course = get_post($course_id);
        if (!$course || !$this->is_course_post_type($course->post_type)) {
            return false;
        }
        
        // Get instructor info
        $instructor = get_userdata($course->post_author);
        
        $course_data = array(
            'id' => $course_id,
            'title' => $course->post_title,
            'instructor' => $instructor ? $instructor->display_name : 'Unknown',
            'students' => $this->get_course_students_using_integration($course_id),
            'quizzes' => $this->get_course_quizzes($course_id),
            'lessons' => $this->get_course_lessons($course_id),
            'stats' => $this->get_course_stats($course_id)
        );
        
        return $course_data;
    }
    
    /**
     * Check if current user can view course
     */
    private function can_view_course($course_id) {
        if (current_user_can('manage_options')) {
            return true;
        }
        
        if (current_user_can('tutor_instructor')) {
            $course = get_post($course_id);
            return $course && $course->post_author == get_current_user_id();
        }
        
        return false;
    }
    
    /**
     * Check if post type is a course post type using Tutor LMS integration
     */
    private function is_course_post_type($post_type) {
        return $post_type === TutorAdvancedTracking_TutorIntegration::get_course_post_type();
    }
    
    /**
     * Get the correct lesson post type using Tutor LMS integration
     */
    private function get_lesson_post_type() {
        return TutorAdvancedTracking_TutorIntegration::get_lesson_post_type();
    }
    
    /**
     * Get course students using Tutor LMS integration layer
     */
    private function get_course_students_using_integration($course_id) {
        $students = TutorAdvancedTracking_TutorIntegration::get_course_students($course_id);
        
        if (empty($students)) {
            return array();
        }
        
        $enhanced_students = array();
        foreach ($students as $student) {
            $user_id = is_object($student) ? $student->ID : $student['ID'];
            $user_data = is_object($student) ? $student : get_userdata($user_id);
            
            // Get enrollment date using WordPress meta
            $enrollment_date = get_user_meta($user_id, '_tutor_enrolled_' . $course_id, true);
            if (!$enrollment_date) {
                $enrollment_date = current_time('mysql'); // Fallback
            }
            
            // Get progress using integration layer
            $progress = TutorAdvancedTracking_Cache::get_user_course_progress($user_id, $course_id);
            
            // Get quiz average
            $quiz_average = $this->get_student_quiz_average($course_id, $user_id);
            
            // Get last activity
            $last_activity = $this->get_student_last_activity($course_id, $user_id);
            
            // Get completion status using WordPress meta
            $completion_status = $this->get_student_completion_status($course_id, $user_id);
            
            $enhanced_students[] = array(
                'id' => $user_id,
                'name' => $user_data->display_name,
                'email' => $user_data->user_email,
                'enrollment_date' => $enrollment_date,
                'progression' => $progress,
                'quiz_average' => $quiz_average,
                'last_activity' => $last_activity,
                'completion_status' => $completion_status
            );
        }
        
        return $enhanced_students;
    }
    
    /**
     * Get course students with their progress - OPTIMIZED VERSION (legacy)
     */
    private function get_course_students_optimized($course_id) {
        global $wpdb;
        
        $lesson_post_type = $this->get_lesson_post_type();
        
        // Single optimized query to get all student data at once
        $students_data = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                e.user_id,
                e.enrollment_date,
                u.display_name,
                u.user_email,
                -- Calculate progression
                (SELECT COUNT(DISTINCT la.lesson_id) 
                 FROM {$wpdb->prefix}tutor_lesson_activities la
                 WHERE la.user_id = e.user_id 
                 AND la.course_id = e.course_id 
                 AND la.activity_status = 'completed') as completed_lessons,
                -- Calculate quiz average
                IFNULL((SELECT AVG(qa.earned_marks / qa.total_marks * 100)
                 FROM {$wpdb->prefix}tutor_quiz_attempts qa
                 JOIN {$wpdb->posts} q ON qa.quiz_id = q.ID
                 WHERE qa.user_id = e.user_id 
                 AND q.post_parent = e.course_id
                 AND qa.attempt_status = 'attempt_ended'
                 AND qa.total_marks > 0), 0) as quiz_average,
                -- Get last activity
                GREATEST(
                    IFNULL((SELECT MAX(la.created_at) 
                     FROM {$wpdb->prefix}tutor_lesson_activities la
                     WHERE la.user_id = e.user_id 
                     AND la.course_id = e.course_id), e.enrollment_date),
                    IFNULL((SELECT MAX(qa.attempt_started_at)
                     FROM {$wpdb->prefix}tutor_quiz_attempts qa
                     JOIN {$wpdb->posts} q ON qa.quiz_id = q.ID
                     WHERE qa.user_id = e.user_id 
                     AND q.post_parent = e.course_id), e.enrollment_date)
                ) as last_activity,
                -- Check completion status
                CASE 
                    WHEN e.completion_date IS NOT NULL THEN 'Completed'
                    WHEN e.is_completed = 1 THEN 'Completed'
                    ELSE 'In Progress'
                END as completion_status
            FROM {$wpdb->prefix}tutor_enrollments e
            JOIN {$wpdb->users} u ON e.user_id = u.ID
            WHERE e.course_id = %d
            ORDER BY e.enrollment_date DESC",
            $course_id
        ));
        
        // Get total lessons for progression calculation
        $total_lessons = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} 
             WHERE post_type = %s AND post_parent = %d AND post_status = 'publish'",
            $lesson_post_type, $course_id
        ));
        
        $enhanced_students = array();
        foreach ($students_data as $student) {
            $progression = $total_lessons > 0 ? 
                round(($student->completed_lessons / $total_lessons) * 100, 1) : 0;
            
            $enhanced_students[] = array(
                'id' => $student->user_id,
                'name' => $student->display_name,
                'email' => $student->user_email,
                'enrollment_date' => $student->enrollment_date,
                'progression' => $progression,
                'quiz_average' => round($student->quiz_average, 1),
                'last_activity' => $student->last_activity,
                'completion_status' => $student->completion_status
            );
        }
        
        return $enhanced_students;
    }
    
    /**
     * Get course students with their progress - FALLBACK for missing tables
     */
    private function get_course_students($course_id) {
        global $wpdb;
        
        $students = $wpdb->get_results($wpdb->prepare(
            "SELECT e.user_id, e.enrollment_date, u.display_name, u.user_email,
                    e.completion_date, e.is_completed
             FROM {$wpdb->prefix}tutor_enrollments e
             JOIN {$wpdb->users} u ON e.user_id = u.ID
             WHERE e.course_id = %d
             ORDER BY e.enrollment_date DESC",
            $course_id
        ));
        
        $enhanced_students = array();
        foreach ($students as $student) {
            $enhanced_students[] = array(
                'id' => $student->user_id,
                'name' => $student->display_name,
                'email' => $student->user_email,
                'enrollment_date' => $student->enrollment_date,
                'progression' => $this->get_student_progression($course_id, $student->user_id),
                'quiz_average' => $this->get_student_quiz_average($course_id, $student->user_id),
                'last_activity' => $this->get_student_last_activity($course_id, $student->user_id),
                'completion_status' => $student->completion_date || $student->is_completed ? 'Completed' : 'In Progress'
            );
        }
        
        return $enhanced_students;
    }
    
    /**
     * Get course quizzes with statistics using Tutor LMS integration
     */
    private function get_course_quizzes($course_id) {
        $quizzes = TutorAdvancedTracking_TutorIntegration::get_course_quizzes($course_id);
        
        $quiz_data = array();
        foreach ($quizzes as $quiz) {
            $quiz_id = is_object($quiz) ? $quiz->ID : $quiz['ID'];
            $quiz_title = is_object($quiz) ? $quiz->post_title : $quiz['post_title'];
            
            $quiz_stats = $this->get_quiz_statistics($quiz_id);
            
            $quiz_data[] = array(
                'id' => $quiz_id,
                'title' => $quiz_title,
                'attempts_count' => $quiz_stats['attempts_count'],
                'average_score' => $quiz_stats['average_score'],
                'pass_rate' => $quiz_stats['pass_rate'],
                'questions' => $this->get_quiz_questions_stats($quiz_id)
            );
        }
        
        return $quiz_data;
    }
    
    /**
     * Get course lessons using Tutor LMS integration
     */
    private function get_course_lessons($course_id) {
        return TutorAdvancedTracking_TutorIntegration::get_course_lessons($course_id);
    }
    
    /**
     * Get overall course statistics using Tutor LMS integration
     */
    private function get_course_stats($course_id) {
        $enrollment_stats = TutorAdvancedTracking_TutorIntegration::get_course_enrollment_stats($course_id);
        $avg_completion_time = $this->get_average_completion_time($course_id);
        
        return array(
            'total_students' => $enrollment_stats['total_students'],
            'completed_students' => $enrollment_stats['completed_students'],
            'completion_rate' => $enrollment_stats['total_students'] > 0 ? 
                round(($enrollment_stats['completed_students'] / $enrollment_stats['total_students']) * 100, 1) : 0,
            'avg_completion_time' => $avg_completion_time
        );
    }
    
    /**
     * Get student progression percentage using Tutor LMS integration
     */
    private function get_student_progression($course_id, $user_id) {
        return TutorAdvancedTracking_TutorIntegration::get_user_course_progress($user_id, $course_id);
    }
    
    /**
     * Get student quiz average
     */
    private function get_student_quiz_average($course_id, $user_id) {
        global $wpdb;
        
        $avg_score = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(qa.earned_marks / qa.total_marks * 100) 
             FROM {$wpdb->prefix}tutor_quiz_attempts qa
             JOIN {$wpdb->posts} p ON qa.quiz_id = p.ID
             WHERE p.post_parent = %d 
             AND qa.user_id = %d
             AND qa.attempt_status = 'attempt_ended'
             AND qa.total_marks > 0",
            $course_id, $user_id
        ));
        
        return $avg_score ? round($avg_score, 1) : 0;
    }
    
    /**
     * Get student last activity
     */
    private function get_student_last_activity($course_id, $user_id) {
        global $wpdb;
        
        // Check multiple sources for last activity
        $last_quiz = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(qa.attempt_started_at) 
             FROM {$wpdb->prefix}tutor_quiz_attempts qa
             JOIN {$wpdb->posts} p ON qa.quiz_id = p.ID
             WHERE p.post_parent = %d AND qa.user_id = %d",
            $course_id, $user_id
        ));
        
        $last_lesson = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(created_at) 
             FROM {$wpdb->prefix}tutor_lesson_activities
             WHERE course_id = %d AND user_id = %d",
            $course_id, $user_id
        ));
        
        $dates = array_filter(array($last_quiz, $last_lesson));
        
        if (empty($dates)) {
            // Fall back to enrollment date
            $enrollment_date = $wpdb->get_var($wpdb->prepare(
                "SELECT enrollment_date FROM {$wpdb->prefix}tutor_enrollments
                 WHERE course_id = %d AND user_id = %d",
                $course_id, $user_id
            ));
            return $enrollment_date ?: 'Never';
        }
        
        return max($dates);
    }
    
    /**
     * Get student completion status using WordPress metadata
     */
    private function get_student_completion_status($course_id, $user_id) {
        // Check WordPress user meta first
        $is_completed = get_user_meta($user_id, '_tutor_course_completed_' . $course_id, true);
        if ($is_completed) {
            return 'Completed';
        }
        
        // Check course completion date
        $completion_date = get_user_meta($user_id, '_tutor_course_completion_date_' . $course_id, true);
        if ($completion_date) {
            return 'Completed';
        }
        
        // Fallback to database check if meta doesn't exist
        global $wpdb;
        $table_name = $wpdb->prefix . 'tutor_enrollments';
        
        if (TutorAdvancedTracking_TutorIntegration::table_exists($table_name)) {
            $status = $wpdb->get_row($wpdb->prepare(
                "SELECT is_completed, completion_date 
                 FROM {$table_name}
                 WHERE course_id = %d AND user_id = %d",
                $course_id, $user_id
            ));
            
            if ($status && ($status->is_completed || $status->completion_date)) {
                return 'Completed';
            }
        }
        
        return 'In Progress';
    }
    
    /**
     * Get quiz statistics
     */
    private function get_quiz_statistics($quiz_id) {
        global $wpdb;
        
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as attempts_count,
                AVG(earned_marks / total_marks * 100) as average_score,
                SUM(CASE WHEN earned_marks / total_marks * 100 >= 80 THEN 1 ELSE 0 END) as passed_count
             FROM {$wpdb->prefix}tutor_quiz_attempts
             WHERE quiz_id = %d 
             AND attempt_status = 'attempt_ended'
             AND total_marks > 0",
            $quiz_id
        ));
        
        $pass_rate = $stats->attempts_count > 0 ? 
            round(($stats->passed_count / $stats->attempts_count) * 100, 1) : 0;
        
        return array(
            'attempts_count' => (int) $stats->attempts_count,
            'average_score' => $stats->average_score ? round($stats->average_score, 1) : 0,
            'pass_rate' => $pass_rate
        );
    }
    
    /**
     * Get quiz questions statistics
     */
    private function get_quiz_questions_stats($quiz_id) {
        global $wpdb;
        
        // This would require specific quiz question tracking implementation
        // For now, return empty array
        return array();
    }
    
    /**
     * Get average completion time
     */
    private function get_average_completion_time($course_id) {
        global $wpdb;
        
        $avg_days = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(DATEDIFF(completion_date, enrollment_date))
             FROM {$wpdb->prefix}tutor_enrollments
             WHERE course_id = %d 
             AND completion_date IS NOT NULL",
            $course_id
        ));
        
        return $avg_days ? round($avg_days) : 0;
    }
}