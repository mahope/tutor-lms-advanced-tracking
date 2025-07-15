<?php
/**
 * Course statistics class
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
        if (!$course || $course->post_type !== 'courses') {
            return false;
        }
        
        $course_data = array(
            'id' => $course_id,
            'title' => $course->post_title,
            'instructor' => get_userdata($course->post_author)->display_name,
            'students' => $this->get_course_students($course_id),
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
     * Get course students with their progress
     */
    private function get_course_students($course_id) {
        global $wpdb;
        
        $students = $wpdb->get_results($wpdb->prepare(
            "SELECT e.user_id, e.enrollment_date, u.display_name, u.user_email
             FROM {$wpdb->prefix}tutor_enrollments e
             JOIN {$wpdb->users} u ON e.user_id = u.ID
             WHERE e.course_id = %d AND e.status = 'completed'
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
                'completion_status' => $this->get_student_completion_status($course_id, $student->user_id)
            );
        }
        
        return $enhanced_students;
    }
    
    /**
     * Get course quizzes with statistics
     */
    private function get_course_quizzes($course_id) {
        global $wpdb;
        
        $quizzes = $wpdb->get_results($wpdb->prepare(
            "SELECT ID, post_title, post_content
             FROM {$wpdb->posts}
             WHERE post_type = 'tutor_quiz'
             AND post_parent = %d
             AND post_status = 'publish'
             ORDER BY menu_order ASC",
            $course_id
        ));
        
        $quiz_data = array();
        foreach ($quizzes as $quiz) {
            $quiz_stats = $this->get_quiz_statistics($quiz->ID);
            
            $quiz_data[] = array(
                'id' => $quiz->ID,
                'title' => $quiz->post_title,
                'attempts_count' => $quiz_stats['attempts_count'],
                'average_score' => $quiz_stats['average_score'],
                'pass_rate' => $quiz_stats['pass_rate'],
                'questions' => $this->get_quiz_questions_stats($quiz->ID)
            );
        }
        
        return $quiz_data;
    }
    
    /**
     * Get course lessons
     */
    private function get_course_lessons($course_id) {
        global $wpdb;
        
        $lessons = $wpdb->get_results($wpdb->prepare(
            "SELECT ID, post_title, menu_order
             FROM {$wpdb->posts}
             WHERE post_type = 'lesson'
             AND post_parent = %d
             AND post_status = 'publish'
             ORDER BY menu_order ASC",
            $course_id
        ));
        
        return $lessons;
    }
    
    /**
     * Get overall course statistics
     */
    private function get_course_stats($course_id) {
        global $wpdb;
        
        $total_students = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}tutor_enrollments 
             WHERE course_id = %d AND status = 'completed'",
            $course_id
        ));
        
        $completed_students = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}tutor_enrollments 
             WHERE course_id = %d AND status = 'completed'",
            $course_id
        ));
        
        $avg_completion_time = $this->get_average_completion_time($course_id);
        
        return array(
            'total_students' => (int) $total_students,
            'completed_students' => (int) $completed_students,
            'completion_rate' => $total_students > 0 ? round(($completed_students / $total_students) * 100, 1) : 0,
            'avg_completion_time' => $avg_completion_time
        );
    }
    
    /**
     * Get student progression percentage
     */
    private function get_student_progression($course_id, $user_id) {
        global $wpdb;
        
        // Get total lessons
        $total_lessons = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} 
             WHERE post_type = 'lesson' AND post_parent = %d AND post_status = 'publish'",
            $course_id
        ));
        
        if ($total_lessons == 0) {
            return 0;
        }
        
        // Get completed lessons (simplified - in real implementation, you'd track lesson completion)
        $completed_lessons = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT qa.quiz_id) FROM {$wpdb->prefix}tutor_quiz_attempts qa
             JOIN {$wpdb->posts} p ON qa.quiz_id = p.ID
             WHERE p.post_parent = %d AND qa.user_id = %d AND qa.attempt_status = 'attempt_ended'",
            $course_id, $user_id
        ));
        
        return round(($completed_lessons / $total_lessons) * 100, 1);
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
             WHERE p.post_parent = %d AND qa.user_id = %d 
             AND qa.attempt_status = 'attempt_ended' AND qa.total_marks > 0",
            $course_id, $user_id
        ));
        
        return $avg_score ? round($avg_score, 1) : 0;
    }
    
    /**
     * Get student last activity
     */
    private function get_student_last_activity($course_id, $user_id) {
        global $wpdb;
        
        $last_activity = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(qa.attempt_ended_at)
             FROM {$wpdb->prefix}tutor_quiz_attempts qa
             JOIN {$wpdb->posts} p ON qa.quiz_id = p.ID
             WHERE p.post_parent = %d AND qa.user_id = %d",
            $course_id, $user_id
        ));
        
        return $last_activity ? $last_activity : null;
    }
    
    /**
     * Get student completion status
     */
    private function get_student_completion_status($course_id, $user_id) {
        global $wpdb;
        
        $completion = $wpdb->get_row($wpdb->prepare(
            "SELECT completion_date, is_completed
             FROM {$wpdb->prefix}tutor_enrollments
             WHERE course_id = %d AND user_id = %d",
            $course_id, $user_id
        ));
        
        return array(
            'is_completed' => $completion ? (bool) $completion->is_completed : false,
            'completion_date' => $completion ? $completion->completion_date : null
        );
    }
    
    /**
     * Get quiz statistics
     */
    private function get_quiz_statistics($quiz_id) {
        global $wpdb;
        
        $attempts_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}tutor_quiz_attempts 
             WHERE quiz_id = %d AND attempt_status = 'attempt_ended'",
            $quiz_id
        ));
        
        $average_score = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(earned_marks / total_marks * 100)
             FROM {$wpdb->prefix}tutor_quiz_attempts
             WHERE quiz_id = %d AND attempt_status = 'attempt_ended' AND total_marks > 0",
            $quiz_id
        ));
        
        $pass_rate = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}tutor_quiz_attempts 
             WHERE quiz_id = %d AND attempt_status = 'attempt_ended' 
             AND (earned_marks / total_marks * 100) >= 70",
            $quiz_id
        ));
        
        return array(
            'attempts_count' => (int) $attempts_count,
            'average_score' => $average_score ? round($average_score, 1) : 0,
            'pass_rate' => $attempts_count > 0 ? round(($pass_rate / $attempts_count) * 100, 1) : 0
        );
    }
    
    /**
     * Get quiz questions statistics
     */
    private function get_quiz_questions_stats($quiz_id) {
        global $wpdb;
        
        $questions = $wpdb->get_results($wpdb->prepare(
            "SELECT ID, question_title, question_type
             FROM {$wpdb->prefix}tutor_quiz_questions
             WHERE quiz_id = %d
             ORDER BY question_order ASC",
            $quiz_id
        ));
        
        $question_stats = array();
        foreach ($questions as $question) {
            $correct_answers = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}tutor_quiz_question_answers
                 WHERE question_id = %d AND is_correct = 1",
                $question->ID
            ));
            
            $total_answers = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}tutor_quiz_question_answers
                 WHERE question_id = %d",
                $question->ID
            ));
            
            $question_stats[] = array(
                'id' => $question->ID,
                'title' => $question->question_title,
                'type' => $question->question_type,
                'correct_rate' => $total_answers > 0 ? round(($correct_answers / $total_answers) * 100, 1) : 0,
                'total_attempts' => (int) $total_answers
            );
        }
        
        return $question_stats;
    }
    
    /**
     * Get average completion time
     */
    private function get_average_completion_time($course_id) {
        global $wpdb;
        
        $avg_time = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(TIMESTAMPDIFF(DAY, enrollment_date, completion_date))
             FROM {$wpdb->prefix}tutor_enrollments
             WHERE course_id = %d AND completion_date IS NOT NULL",
            $course_id
        ));
        
        return $avg_time ? round($avg_time, 1) : 0;
    }
}