<?php
/**
 * User statistics class
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class TutorAdvancedTracking_UserStats {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Constructor can be used for hooks if needed
    }
    
    /**
     * Get detailed user statistics
     */
    public function get_user_details($user_id) {
        global $wpdb;
        
        $user_id = (int) $user_id;
        
        // Check if user exists and current user has permission
        if (!$this->can_view_user($user_id)) {
            return false;
        }
        
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }
        
        $user_data = array(
            'id' => $user_id,
            'name' => $user->display_name,
            'email' => $user->user_email,
            'registration_date' => $user->user_registered,
            'courses' => $this->get_user_courses($user_id),
            'overall_stats' => $this->get_user_overall_stats($user_id),
            'quiz_performance' => $this->get_user_quiz_performance($user_id),
            'activity_status' => $this->get_user_activity_status($user_id),
            'certificates' => $this->get_user_certificates($user_id),
            'problem_areas' => $this->get_user_problem_areas($user_id)
        );
        
        return $user_data;
    }
    
    /**
     * Check if current user can view this user's data
     */
    private function can_view_user($user_id) {
        if (current_user_can('manage_options')) {
            return true;
        }
        
        if (current_user_can('tutor_instructor')) {
            // Check if user is enrolled in any of the instructor's courses
            global $wpdb;
            $instructor_id = get_current_user_id();
            
            $enrolled = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}tutor_enrollments e
                 JOIN {$wpdb->posts} p ON e.course_id = p.ID
                 WHERE e.user_id = %d AND p.post_author = %d",
                $user_id, $instructor_id
            ));
            
            return $enrolled > 0;
        }
        
        return false;
    }
    
    /**
     * Get user's enrolled courses with progress
     */
    private function get_user_courses($user_id) {
        global $wpdb;
        
        $courses = $wpdb->get_results($wpdb->prepare(
            "SELECT e.course_id, e.enrollment_date, e.completion_date, e.is_completed,
                    p.post_title, p.post_author
             FROM {$wpdb->prefix}tutor_enrollments e
             JOIN {$wpdb->posts} p ON e.course_id = p.ID
             WHERE e.user_id = %d AND e.status = 'completed'
             ORDER BY e.enrollment_date DESC",
            $user_id
        ));
        
        $course_data = array();
        foreach ($courses as $course) {
            $instructor = get_userdata($course->post_author);
            
            $course_data[] = array(
                'id' => $course->course_id,
                'title' => $course->post_title,
                'instructor' => $instructor ? $instructor->display_name : 'Unknown',
                'enrollment_date' => $course->enrollment_date,
                'completion_date' => $course->completion_date,
                'is_completed' => (bool) $course->is_completed,
                'progression' => $this->get_course_progression($course->course_id, $user_id),
                'quiz_average' => $this->get_course_quiz_average($course->course_id, $user_id),
                'last_activity' => $this->get_course_last_activity($course->course_id, $user_id)
            );
        }
        
        return $course_data;
    }
    
    /**
     * Get user's overall statistics
     */
    private function get_user_overall_stats($user_id) {
        global $wpdb;
        
        $total_courses = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}tutor_enrollments 
             WHERE user_id = %d AND status = 'completed'",
            $user_id
        ));
        
        $completed_courses = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}tutor_enrollments 
             WHERE user_id = %d AND status = 'completed' AND is_completed = 1",
            $user_id
        ));
        
        $total_quizzes = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}tutor_quiz_attempts 
             WHERE user_id = %d AND attempt_status = 'attempt_ended'",
            $user_id
        ));
        
        $avg_quiz_score = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(earned_marks / total_marks * 100)
             FROM {$wpdb->prefix}tutor_quiz_attempts
             WHERE user_id = %d AND attempt_status = 'attempt_ended' AND total_marks > 0",
            $user_id
        ));
        
        return array(
            'total_courses' => (int) $total_courses,
            'completed_courses' => (int) $completed_courses,
            'in_progress_courses' => $total_courses - $completed_courses,
            'completion_rate' => $total_courses > 0 ? round(($completed_courses / $total_courses) * 100, 1) : 0,
            'total_quizzes' => (int) $total_quizzes,
            'avg_quiz_score' => $avg_quiz_score ? round($avg_quiz_score, 1) : 0
        );
    }
    
    /**
     * Get user's quiz performance details
     */
    private function get_user_quiz_performance($user_id) {
        global $wpdb;
        
        $quiz_attempts = $wpdb->get_results($wpdb->prepare(
            "SELECT qa.quiz_id, qa.earned_marks, qa.total_marks, qa.attempt_ended_at,
                    p.post_title as quiz_title, cp.post_title as course_title
             FROM {$wpdb->prefix}tutor_quiz_attempts qa
             JOIN {$wpdb->posts} p ON qa.quiz_id = p.ID
             JOIN {$wpdb->posts} cp ON p.post_parent = cp.ID
             WHERE qa.user_id = %d AND qa.attempt_status = 'attempt_ended'
             ORDER BY qa.attempt_ended_at DESC",
            $user_id
        ));
        
        $performance_data = array();
        foreach ($quiz_attempts as $attempt) {
            $score = $attempt->total_marks > 0 ? round(($attempt->earned_marks / $attempt->total_marks) * 100, 1) : 0;
            
            $performance_data[] = array(
                'quiz_id' => $attempt->quiz_id,
                'quiz_title' => $attempt->quiz_title,
                'course_title' => $attempt->course_title,
                'score' => $score,
                'earned_marks' => $attempt->earned_marks,
                'total_marks' => $attempt->total_marks,
                'attempt_date' => $attempt->attempt_ended_at,
                'passed' => $score >= 70
            );
        }
        
        return $performance_data;
    }
    
    /**
     * Get user's activity status
     */
    private function get_user_activity_status($user_id) {
        global $wpdb;
        
        $last_activity = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(attempt_ended_at) FROM {$wpdb->prefix}tutor_quiz_attempts 
             WHERE user_id = %d",
            $user_id
        ));
        
        $days_since_activity = null;
        $is_inactive = false;
        
        if ($last_activity) {
            $last_activity_date = new DateTime($last_activity);
            $now = new DateTime();
            $days_since_activity = $now->diff($last_activity_date)->days;
            $is_inactive = $days_since_activity > 7;
        }
        
        return array(
            'last_activity' => $last_activity,
            'days_since_activity' => $days_since_activity,
            'is_inactive' => $is_inactive,
            'activity_level' => $this->get_activity_level($days_since_activity)
        );
    }
    
    /**
     * Get user's certificates
     */
    private function get_user_certificates($user_id) {
        global $wpdb;
        
        // This would depend on how certificates are stored in Tutor LMS
        // For now, we'll return a placeholder
        $certificates = $wpdb->get_results($wpdb->prepare(
            "SELECT e.course_id, e.completion_date, p.post_title
             FROM {$wpdb->prefix}tutor_enrollments e
             JOIN {$wpdb->posts} p ON e.course_id = p.ID
             WHERE e.user_id = %d AND e.is_completed = 1 AND e.completion_date IS NOT NULL",
            $user_id
        ));
        
        $certificate_data = array();
        foreach ($certificates as $cert) {
            $certificate_data[] = array(
                'course_id' => $cert->course_id,
                'course_title' => $cert->post_title,
                'completion_date' => $cert->completion_date,
                'certificate_url' => '' // Would need to be implemented based on Tutor LMS certificate system
            );
        }
        
        return $certificate_data;
    }
    
    /**
     * Get user's problem areas
     */
    private function get_user_problem_areas($user_id) {
        global $wpdb;
        
        // Find quiz questions where user consistently gets wrong answers
        $problem_questions = $wpdb->get_results($wpdb->prepare(
            "SELECT qa.question_id, q.question_title, COUNT(*) as wrong_count,
                    p.post_title as quiz_title, cp.post_title as course_title
             FROM {$wpdb->prefix}tutor_quiz_question_answers qa
             JOIN {$wpdb->prefix}tutor_quiz_questions q ON qa.question_id = q.ID
             JOIN {$wpdb->prefix}tutor_quiz_attempts qat ON qa.quiz_attempt_id = qat.attempt_id
             JOIN {$wpdb->posts} p ON q.quiz_id = p.ID
             JOIN {$wpdb->posts} cp ON p.post_parent = cp.ID
             WHERE qat.user_id = %d AND qa.is_correct = 0
             GROUP BY qa.question_id
             HAVING wrong_count >= 2
             ORDER BY wrong_count DESC",
            $user_id
        ));
        
        $problem_areas = array();
        foreach ($problem_questions as $question) {
            $problem_areas[] = array(
                'question_id' => $question->question_id,
                'question_title' => $question->question_title,
                'quiz_title' => $question->quiz_title,
                'course_title' => $question->course_title,
                'wrong_count' => $question->wrong_count
            );
        }
        
        return $problem_areas;
    }
    
    /**
     * Get course progression for specific user
     */
    private function get_course_progression($course_id, $user_id) {
        global $wpdb;
        
        $total_lessons = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} 
             WHERE post_type = 'lesson' AND post_parent = %d AND post_status = 'publish'",
            $course_id
        ));
        
        if ($total_lessons == 0) {
            return 0;
        }
        
        // Simplified progression based on quiz attempts
        $completed_activities = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT qa.quiz_id) FROM {$wpdb->prefix}tutor_quiz_attempts qa
             JOIN {$wpdb->posts} p ON qa.quiz_id = p.ID
             WHERE p.post_parent = %d AND qa.user_id = %d AND qa.attempt_status = 'attempt_ended'",
            $course_id, $user_id
        ));
        
        return round(($completed_activities / $total_lessons) * 100, 1);
    }
    
    /**
     * Get course quiz average for specific user
     */
    private function get_course_quiz_average($course_id, $user_id) {
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
     * Get course last activity for specific user
     */
    private function get_course_last_activity($course_id, $user_id) {
        global $wpdb;
        
        $last_activity = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(qa.attempt_ended_at)
             FROM {$wpdb->prefix}tutor_quiz_attempts qa
             JOIN {$wpdb->posts} p ON qa.quiz_id = p.ID
             WHERE p.post_parent = %d AND qa.user_id = %d",
            $course_id, $user_id
        ));
        
        return $last_activity;
    }
    
    /**
     * Get activity level based on days since last activity
     */
    private function get_activity_level($days_since_activity) {
        if ($days_since_activity === null) {
            return 'unknown';
        }
        
        if ($days_since_activity <= 1) {
            return 'very_active';
        } elseif ($days_since_activity <= 3) {
            return 'active';
        } elseif ($days_since_activity <= 7) {
            return 'moderate';
        } elseif ($days_since_activity <= 30) {
            return 'low';
        } else {
            return 'inactive';
        }
    }
}