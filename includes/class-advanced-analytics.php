<?php
/**
 * Advanced Analytics class for sophisticated course and student metrics
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class TutorAdvancedTracking_AdvancedAnalytics {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Initialize advanced analytics
    }
    
    /**
     * Get comprehensive course analytics
     */
    public function get_course_analytics($course_id) {
        $course_id = (int) $course_id;
        
        // Check cache first
        $cache_key = 'tutor_advanced_analytics_' . $course_id;
        $cached_data = get_transient($cache_key);
        
        if ($cached_data !== false) {
            return $cached_data;
        }
        
        $analytics = array(
            'completion_funnel' => $this->get_completion_funnel($course_id),
            'engagement_metrics' => $this->get_engagement_metrics($course_id),
            'time_analytics' => $this->get_time_analytics($course_id),
            'difficulty_analysis' => $this->get_difficulty_analysis($course_id),
            'dropout_patterns' => $this->get_dropout_patterns($course_id),
            'peak_activity' => $this->get_peak_activity_times($course_id),
            'lesson_progression' => $this->get_lesson_progression($course_id),
            'quiz_performance_deep' => $this->get_quiz_performance_analysis($course_id),
            'student_cohorts' => $this->get_student_cohorts($course_id),
            'predictive_metrics' => $this->get_predictive_metrics($course_id)
        );
        
        // Cache for 15 minutes
        set_transient($cache_key, $analytics, 900);
        
        return $analytics;
    }
    
    /**
     * Get course completion funnel analysis
     */
    private function get_completion_funnel($course_id) {
        global $wpdb;

        // Use integration layer to get the correct post types
        $lesson_post_type = TutorAdvancedTracking_TutorIntegration::get_lesson_post_type();

        // Get course structure
        $lessons = $wpdb->get_results($wpdb->prepare(
            "SELECT ID, post_title, menu_order FROM {$wpdb->posts}
             WHERE post_type = %s AND post_parent = %d AND post_status = 'publish'
             ORDER BY menu_order ASC",
            $lesson_post_type, $course_id
        ));

        $quizzes = $wpdb->get_results($wpdb->prepare(
            "SELECT ID, post_title, menu_order FROM {$wpdb->posts}
             WHERE post_type = 'tutor_quiz' AND post_parent = %d AND post_status = 'publish'
             ORDER BY menu_order ASC",
            $course_id
        ));

        // Count all active enrollments (not cancelled)
        $total_enrolled = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}tutor_enrollments
             WHERE course_id = %d AND (status IS NULL OR status != 'cancelled')",
            $course_id
        ));
        
        $funnel_data = array();
        
        // Enrollment step
        $funnel_data[] = array(
            'step' => 'Enrolled',
            'count' => (int) $total_enrolled,
            'percentage' => 100,
            'drop_rate' => 0
        );
        
        // First lesson access
        $first_lesson_access = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT e.user_id) FROM {$wpdb->prefix}tutor_enrollments e
             JOIN {$wpdb->prefix}tutor_quiz_attempts qa ON e.user_id = qa.user_id
             WHERE e.course_id = %d AND (e.status IS NULL OR e.status != 'cancelled')",
            $course_id
        ));
        
        $first_lesson_percentage = $total_enrolled > 0 ? ($first_lesson_access / $total_enrolled) * 100 : 0;
        $funnel_data[] = array(
            'step' => 'Started Course',
            'count' => (int) $first_lesson_access,
            'percentage' => round($first_lesson_percentage, 1),
            'drop_rate' => round(100 - $first_lesson_percentage, 1)
        );
        
        // Mid-course progress (50% completion)
        $mid_progress = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT qa.user_id) FROM {$wpdb->prefix}tutor_quiz_attempts qa
             JOIN {$wpdb->posts} p ON qa.quiz_id = p.ID
             WHERE p.post_parent = %d AND qa.attempt_status = 'attempt_ended'
             GROUP BY qa.user_id
             HAVING COUNT(DISTINCT qa.quiz_id) >= %d",
            $course_id, max(1, floor(count($quizzes) / 2))
        ));
        
        $mid_percentage = $total_enrolled > 0 ? ($mid_progress / $total_enrolled) * 100 : 0;
        $funnel_data[] = array(
            'step' => 'Mid-Course',
            'count' => (int) $mid_progress,
            'percentage' => round($mid_percentage, 1),
            'drop_rate' => round($first_lesson_percentage - $mid_percentage, 1)
        );
        
        // Course completion
        $completed = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}tutor_enrollments
             WHERE course_id = %d AND (is_completed = 1 OR completion_date IS NOT NULL)",
            $course_id
        ));
        
        $completed_percentage = $total_enrolled > 0 ? ($completed / $total_enrolled) * 100 : 0;
        $funnel_data[] = array(
            'step' => 'Completed',
            'count' => (int) $completed,
            'percentage' => round($completed_percentage, 1),
            'drop_rate' => round($mid_percentage - $completed_percentage, 1)
        );
        
        return $funnel_data;
    }
    
    /**
     * Get engagement metrics
     */
    private function get_engagement_metrics($course_id) {
        global $wpdb;
        
        // Average session duration (estimated from quiz attempts)
        $avg_session_duration = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(TIMESTAMPDIFF(MINUTE, attempt_started_at, attempt_ended_at)) 
             FROM {$wpdb->prefix}tutor_quiz_attempts qa
             JOIN {$wpdb->posts} p ON qa.quiz_id = p.ID
             WHERE p.post_parent = %d AND qa.attempt_status = 'attempt_ended'
             AND attempt_started_at IS NOT NULL AND attempt_ended_at IS NOT NULL",
            $course_id
        ));
        
        // Retry rates
        $retry_rates = $wpdb->get_results($wpdb->prepare(
            "SELECT qa.user_id, COUNT(*) as attempts, qa.quiz_id
             FROM {$wpdb->prefix}tutor_quiz_attempts qa
             JOIN {$wpdb->posts} p ON qa.quiz_id = p.ID
             WHERE p.post_parent = %d AND qa.attempt_status = 'attempt_ended'
             GROUP BY qa.user_id, qa.quiz_id
             HAVING attempts > 1",
            $course_id
        ));
        
        $total_students = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}tutor_enrollments
             WHERE course_id = %d AND (status IS NULL OR status != 'cancelled')",
            $course_id
        ));
        
        $retry_percentage = $total_students > 0 ? (count($retry_rates) / $total_students) * 100 : 0;
        
        // Engagement score calculation
        $engagement_factors = array(
            'completion_rate' => $this->get_completion_rate($course_id),
            'average_score' => $this->get_average_quiz_score($course_id),
            'retry_rate' => $retry_percentage,
            'session_duration' => $avg_session_duration ?? 0
        );
        
        $engagement_score = $this->calculate_engagement_score($engagement_factors);
        
        return array(
            'avg_session_duration' => round($avg_session_duration ?? 0, 1),
            'retry_percentage' => round($retry_percentage, 1),
            'engagement_score' => $engagement_score,
            'engagement_factors' => $engagement_factors,
            'engagement_level' => $this->get_engagement_level($engagement_score)
        );
    }
    
    /**
     * Get time-based analytics
     */
    private function get_time_analytics($course_id) {
        global $wpdb;
        
        // Daily activity pattern
        $daily_activity = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DAYNAME(attempt_started_at) as day_name,
                DAYOFWEEK(attempt_started_at) as day_number,
                COUNT(*) as activity_count
             FROM {$wpdb->prefix}tutor_quiz_attempts qa
             JOIN {$wpdb->posts} p ON qa.quiz_id = p.ID
             WHERE p.post_parent = %d AND qa.attempt_status = 'attempt_ended'
             AND attempt_started_at IS NOT NULL
             GROUP BY DAYOFWEEK(attempt_started_at), DAYNAME(attempt_started_at)
             ORDER BY day_number",
            $course_id
        ));
        
        // Hourly activity pattern
        $hourly_activity = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                HOUR(attempt_started_at) as hour,
                COUNT(*) as activity_count
             FROM {$wpdb->prefix}tutor_quiz_attempts qa
             JOIN {$wpdb->posts} p ON qa.quiz_id = p.ID
             WHERE p.post_parent = %d AND qa.attempt_status = 'attempt_ended'
             AND attempt_started_at IS NOT NULL
             GROUP BY HOUR(attempt_started_at)
             ORDER BY hour",
            $course_id
        ));
        
        // Monthly progression
        $monthly_progress = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DATE_FORMAT(attempt_started_at, '%%Y-%%m') as month,
                COUNT(DISTINCT qa.user_id) as active_students,
                COUNT(*) as total_attempts
             FROM {$wpdb->prefix}tutor_quiz_attempts qa
             JOIN {$wpdb->posts} p ON qa.quiz_id = p.ID
             WHERE p.post_parent = %d AND qa.attempt_status = 'attempt_ended'
             AND attempt_started_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
             GROUP BY DATE_FORMAT(attempt_started_at, '%%Y-%%m')
             ORDER BY month",
            $course_id
        ));
        
        // Average time to completion
        $avg_completion_time = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(DATEDIFF(completion_date, enrollment_date))
             FROM {$wpdb->prefix}tutor_enrollments
             WHERE course_id = %d AND completion_date IS NOT NULL",
            $course_id
        ));
        
        return array(
            'daily_activity' => $daily_activity,
            'hourly_activity' => $hourly_activity,
            'monthly_progress' => $monthly_progress,
            'avg_completion_time' => round($avg_completion_time ?? 0, 1),
            'peak_day' => $this->get_peak_day($daily_activity),
            'peak_hour' => $this->get_peak_hour($hourly_activity)
        );
    }
    
    /**
     * Get difficulty analysis
     */
    private function get_difficulty_analysis($course_id) {
        global $wpdb;
        
        $quiz_difficulty = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                p.ID,
                p.post_title,
                AVG(qa.earned_marks / qa.total_marks * 100) as avg_score,
                COUNT(qa.attempt_id) as total_attempts,
                COUNT(DISTINCT qa.user_id) as unique_students,
                AVG(TIMESTAMPDIFF(MINUTE, qa.attempt_started_at, qa.attempt_ended_at)) as avg_duration
             FROM {$wpdb->posts} p
             JOIN {$wpdb->prefix}tutor_quiz_attempts qa ON p.ID = qa.quiz_id
             WHERE p.post_parent = %d AND p.post_type = 'tutor_quiz' AND p.post_status = 'publish'
             AND qa.attempt_status = 'attempt_ended' AND qa.total_marks > 0
             GROUP BY p.ID, p.post_title
             ORDER BY avg_score ASC",
            $course_id
        ));
        
        $difficulty_scores = array();
        foreach ($quiz_difficulty as $quiz) {
            $difficulty_score = $this->calculate_difficulty_score(
                $quiz->avg_score,
                $quiz->total_attempts / max(1, $quiz->unique_students), // retry rate
                $quiz->avg_duration
            );
            
            $difficulty_scores[] = array(
                'id' => $quiz->ID,
                'title' => $quiz->post_title,
                'avg_score' => round($quiz->avg_score, 1),
                'retry_rate' => round($quiz->total_attempts / max(1, $quiz->unique_students), 2),
                'avg_duration' => round($quiz->avg_duration ?? 0, 1),
                'difficulty_score' => $difficulty_score,
                'difficulty_level' => $this->get_difficulty_level($difficulty_score)
            );
        }
        
        return $difficulty_scores;
    }
    
    /**
     * Get dropout patterns
     */
    private function get_dropout_patterns($course_id) {
        global $wpdb;
        
        // Students who never started
        $never_started = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}tutor_enrollments e
             LEFT JOIN {$wpdb->prefix}tutor_quiz_attempts qa ON e.user_id = qa.user_id
             WHERE e.course_id = %d AND (e.status IS NULL OR e.status != 'cancelled') AND qa.attempt_id IS NULL",
            $course_id
        ));

        // Students who started but didn't complete first quiz
        $started_not_completed = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT e.user_id) FROM {$wpdb->prefix}tutor_enrollments e
             JOIN {$wpdb->prefix}tutor_quiz_attempts qa ON e.user_id = qa.user_id
             JOIN {$wpdb->posts} p ON qa.quiz_id = p.ID
             WHERE e.course_id = %d AND (e.status IS NULL OR e.status != 'cancelled') AND e.is_completed = 0
             AND p.post_parent = %d",
            $course_id, $course_id
        ));
        
        // Early dropouts (left after first quiz)
        $early_dropouts = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT qa.user_id) FROM {$wpdb->prefix}tutor_quiz_attempts qa
             JOIN {$wpdb->posts} p ON qa.quiz_id = p.ID
             WHERE p.post_parent = %d AND qa.attempt_status = 'attempt_ended'
             GROUP BY qa.user_id
             HAVING COUNT(DISTINCT qa.quiz_id) = 1",
            $course_id
        ));
        
        $total_enrolled = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}tutor_enrollments
             WHERE course_id = %d AND (status IS NULL OR status != 'cancelled')",
            $course_id
        ));

        return array(
            'never_started' => (int) $never_started,
            'started_not_completed' => (int) $started_not_completed,
            'early_dropouts' => (int) $early_dropouts,
            'total_enrolled' => (int) $total_enrolled,
            'dropout_rate' => $total_enrolled > 0 ? round((($never_started + $started_not_completed + $early_dropouts) / $total_enrolled) * 100, 1) : 0,
            'retention_strategies' => $this->get_retention_strategies($course_id)
        );
    }
    
    /**
     * Get peak activity times
     */
    private function get_peak_activity_times($course_id) {
        global $wpdb;
        
        $peak_times = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                HOUR(attempt_started_at) as hour,
                DAYNAME(attempt_started_at) as day_name,
                COUNT(*) as activity_count
             FROM {$wpdb->prefix}tutor_quiz_attempts qa
             JOIN {$wpdb->posts} p ON qa.quiz_id = p.ID
             WHERE p.post_parent = %d AND qa.attempt_status = 'attempt_ended'
             AND attempt_started_at IS NOT NULL
             GROUP BY HOUR(attempt_started_at), DAYNAME(attempt_started_at)
             ORDER BY activity_count DESC
             LIMIT 10",
            $course_id
        ));
        
        return array(
            'peak_times' => $peak_times,
            'recommendations' => $this->get_timing_recommendations($peak_times)
        );
    }
    
    /**
     * Get detailed lesson progression
     */
    private function get_lesson_progression($course_id) {
        global $wpdb;

        // Use integration layer to get the correct lesson post type
        $lesson_post_type = TutorAdvancedTracking_TutorIntegration::get_lesson_post_type();

        $lessons = $wpdb->get_results($wpdb->prepare(
            "SELECT ID, post_title, menu_order FROM {$wpdb->posts}
             WHERE post_type = %s AND post_parent = %d AND post_status = 'publish'
             ORDER BY menu_order ASC",
            $lesson_post_type, $course_id
        ));
        
        $progression_data = array();
        foreach ($lessons as $lesson) {
            // This would need to be adapted based on how lesson completion is tracked in Tutor LMS
            $progression_data[] = array(
                'lesson_id' => $lesson->ID,
                'title' => $lesson->post_title,
                'order' => $lesson->menu_order,
                'completion_rate' => $this->get_lesson_completion_rate($course_id, $lesson->ID),
                'avg_time_spent' => $this->get_avg_lesson_time($course_id, $lesson->ID),
                'drop_off_rate' => $this->get_lesson_drop_off_rate($course_id, $lesson->ID)
            );
        }
        
        return $progression_data;
    }
    
    /**
     * Get advanced quiz performance analysis
     */
    private function get_quiz_performance_analysis($course_id) {
        global $wpdb;
        
        // Question-level analysis
        $question_analysis = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                qq.ID,
                qq.question_title,
                qq.question_type,
                COUNT(qqa.question_id) as total_answers,
                SUM(CASE WHEN qqa.is_correct = 1 THEN 1 ELSE 0 END) as correct_answers,
                AVG(CASE WHEN qqa.is_correct = 1 THEN 1 ELSE 0 END) * 100 as success_rate
             FROM {$wpdb->prefix}tutor_quiz_questions qq
             JOIN {$wpdb->posts} p ON qq.quiz_id = p.ID
             LEFT JOIN {$wpdb->prefix}tutor_quiz_question_answers qqa ON qq.ID = qqa.question_id
             WHERE p.post_parent = %d AND p.post_type = 'tutor_quiz'
             GROUP BY qq.ID, qq.question_title, qq.question_type
             ORDER BY success_rate ASC",
            $course_id
        ));
        
        // Common wrong answers
        $common_mistakes = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                qq.question_title,
                qqa.given_answer,
                COUNT(*) as frequency
             FROM {$wpdb->prefix}tutor_quiz_questions qq
             JOIN {$wpdb->posts} p ON qq.quiz_id = p.ID
             JOIN {$wpdb->prefix}tutor_quiz_question_answers qqa ON qq.ID = qqa.question_id
             WHERE p.post_parent = %d AND qqa.is_correct = 0
             GROUP BY qq.question_title, qqa.given_answer
             ORDER BY frequency DESC
             LIMIT 20",
            $course_id
        ));
        
        return array(
            'question_analysis' => $question_analysis,
            'common_mistakes' => $common_mistakes,
            'improvement_suggestions' => $this->get_quiz_improvement_suggestions($question_analysis)
        );
    }
    
    /**
     * Get student cohorts analysis
     */
    private function get_student_cohorts($course_id) {
        global $wpdb;
        
        $cohorts = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DATE_FORMAT(enrollment_date, '%%Y-%%m') as cohort_month,
                COUNT(*) as total_students,
                SUM(CASE WHEN is_completed = 1 THEN 1 ELSE 0 END) as completed_students,
                AVG(CASE WHEN completion_date IS NOT NULL THEN DATEDIFF(completion_date, enrollment_date) END) as avg_completion_days
             FROM {$wpdb->prefix}tutor_enrollments
             WHERE course_id = %d AND (status IS NULL OR status != 'cancelled')
             AND enrollment_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
             GROUP BY DATE_FORMAT(enrollment_date, '%%Y-%%m')
             ORDER BY cohort_month DESC",
            $course_id
        ));
        
        $cohort_analysis = array();
        foreach ($cohorts as $cohort) {
            $completion_rate = $cohort->total_students > 0 ? ($cohort->completed_students / $cohort->total_students) * 100 : 0;
            
            $cohort_analysis[] = array(
                'month' => $cohort->cohort_month,
                'total_students' => (int) $cohort->total_students,
                'completed_students' => (int) $cohort->completed_students,
                'completion_rate' => round($completion_rate, 1),
                'avg_completion_days' => round($cohort->avg_completion_days ?? 0, 1),
                'performance_trend' => $this->get_cohort_trend($cohort->cohort_month, $completion_rate)
            );
        }
        
        return $cohort_analysis;
    }
    
    /**
     * Get predictive metrics
     */
    private function get_predictive_metrics($course_id) {
        global $wpdb;
        
        // At-risk students identification
        $at_risk_students = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                e.user_id,
                u.display_name,
                u.user_email,
                DATEDIFF(NOW(), MAX(qa.attempt_started_at)) as days_since_last_activity,
                AVG(qa.earned_marks / qa.total_marks * 100) as avg_score,
                COUNT(DISTINCT qa.quiz_id) as quizzes_attempted
             FROM {$wpdb->prefix}tutor_enrollments e
             JOIN {$wpdb->users} u ON e.user_id = u.ID
             LEFT JOIN {$wpdb->prefix}tutor_quiz_attempts qa ON e.user_id = qa.user_id
             LEFT JOIN {$wpdb->posts} p ON qa.quiz_id = p.ID AND p.post_parent = e.course_id
             WHERE e.course_id = %d AND (e.status IS NULL OR e.status != 'cancelled') AND e.is_completed = 0
             GROUP BY e.user_id, u.display_name, u.user_email
             HAVING days_since_last_activity > 7 OR avg_score < 60 OR quizzes_attempted < 2
             ORDER BY days_since_last_activity DESC",
            $course_id
        ));
        
        // Completion probability
        $completion_probabilities = array();
        foreach ($at_risk_students as $student) {
            $risk_score = $this->calculate_risk_score($student);
            $completion_probabilities[] = array(
                'user_id' => $student->user_id,
                'name' => $student->display_name,
                'email' => $student->user_email,
                'risk_score' => $risk_score,
                'completion_probability' => 100 - $risk_score,
                'recommended_actions' => $this->get_intervention_recommendations($risk_score, $student)
            );
        }
        
        return array(
            'at_risk_students' => $completion_probabilities,
            'risk_distribution' => $this->get_risk_distribution($completion_probabilities),
            'intervention_priorities' => $this->get_intervention_priorities($completion_probabilities)
        );
    }
    
    // Helper methods for calculations
    
    private function calculate_engagement_score($factors) {
        $score = 0;
        $score += $factors['completion_rate'] * 0.3;
        $score += $factors['average_score'] * 0.25;
        $score += min(100, $factors['session_duration'] * 2) * 0.25;
        $score += (100 - min(100, $factors['retry_rate'])) * 0.2;
        
        return round($score, 1);
    }
    
    private function get_engagement_level($score) {
        if ($score >= 80) return 'Excellent';
        if ($score >= 60) return 'Good';
        if ($score >= 40) return 'Average';
        if ($score >= 20) return 'Poor';
        return 'Critical';
    }
    
    private function calculate_difficulty_score($avg_score, $retry_rate, $avg_duration) {
        $score = 0;
        $score += (100 - $avg_score) * 0.4;
        $score += min(100, ($retry_rate - 1) * 20) * 0.3;
        $score += min(100, $avg_duration / 2) * 0.3;
        
        return round($score, 1);
    }
    
    private function get_difficulty_level($score) {
        if ($score >= 80) return 'Very Hard';
        if ($score >= 60) return 'Hard';
        if ($score >= 40) return 'Medium';
        if ($score >= 20) return 'Easy';
        return 'Very Easy';
    }
    
    private function calculate_risk_score($student) {
        $score = 0;
        
        // Days since last activity (higher = more risk)
        $score += min(50, $student->days_since_last_activity * 2);
        
        // Low average score
        if ($student->avg_score < 60) {
            $score += (60 - $student->avg_score) * 0.5;
        }
        
        // Few quizzes attempted
        if ($student->quizzes_attempted < 3) {
            $score += (3 - $student->quizzes_attempted) * 10;
        }
        
        return min(100, round($score, 1));
    }
    
    // Additional helper methods would be implemented here...
    
    private function get_completion_rate($course_id) {
        global $wpdb;

        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}tutor_enrollments
             WHERE course_id = %d AND (status IS NULL OR status != 'cancelled')",
            $course_id
        ));

        $completed = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}tutor_enrollments
             WHERE course_id = %d AND (is_completed = 1 OR completion_date IS NOT NULL)",
            $course_id
        ));

        return $total > 0 ? ($completed / $total) * 100 : 0;
    }
    
    private function get_average_quiz_score($course_id) {
        global $wpdb;
        
        $avg_score = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(qa.earned_marks / qa.total_marks * 100) 
             FROM {$wpdb->prefix}tutor_quiz_attempts qa
             JOIN {$wpdb->posts} p ON qa.quiz_id = p.ID
             WHERE p.post_parent = %d AND qa.attempt_status = 'attempt_ended' AND qa.total_marks > 0",
            $course_id
        ));
        
        return $avg_score ? round($avg_score, 1) : 0;
    }
    
    private function get_peak_day($daily_activity) {
        $max_activity = 0;
        $peak_day = '';
        
        foreach ($daily_activity as $day) {
            if ($day->activity_count > $max_activity) {
                $max_activity = $day->activity_count;
                $peak_day = $day->day_name;
            }
        }
        
        return $peak_day;
    }
    
    private function get_peak_hour($hourly_activity) {
        $max_activity = 0;
        $peak_hour = 0;
        
        foreach ($hourly_activity as $hour) {
            if ($hour->activity_count > $max_activity) {
                $max_activity = $hour->activity_count;
                $peak_hour = $hour->hour;
            }
        }
        
        return $peak_hour;
    }
    
    private function get_lesson_completion_rate($course_id, $lesson_id) {
        // Placeholder - would need to implement based on Tutor LMS lesson tracking
        return rand(60, 95);
    }
    
    private function get_avg_lesson_time($course_id, $lesson_id) {
        // Placeholder - would need to implement based on Tutor LMS lesson tracking
        return rand(10, 45);
    }
    
    private function get_lesson_drop_off_rate($course_id, $lesson_id) {
        // Placeholder - would need to implement based on Tutor LMS lesson tracking
        return rand(5, 25);
    }
    
    private function get_retention_strategies($course_id) {
        return array(
            'Send reminder emails to inactive students',
            'Provide additional support for difficult concepts',
            'Create engaging introductory content',
            'Implement gamification elements',
            'Offer flexible scheduling options'
        );
    }
    
    private function get_timing_recommendations($peak_times) {
        return array(
            'Schedule live sessions during peak hours',
            'Send notifications during high-activity periods',
            'Plan maintenance during low-activity times',
            'Optimize server resources for peak periods'
        );
    }
    
    private function get_quiz_improvement_suggestions($question_analysis) {
        return array(
            'Review questions with low success rates',
            'Provide additional explanations for difficult concepts',
            'Consider splitting complex questions',
            'Add more practice opportunities',
            'Implement adaptive questioning'
        );
    }
    
    private function get_cohort_trend($month, $completion_rate) {
        // Simplified trend analysis - would need more sophisticated implementation
        return $completion_rate > 70 ? 'improving' : ($completion_rate > 50 ? 'stable' : 'declining');
    }
    
    private function get_intervention_recommendations($risk_score, $student) {
        $recommendations = array();
        
        if ($risk_score > 70) {
            $recommendations[] = 'Immediate personal outreach required';
            $recommendations[] = 'Schedule one-on-one session';
        } elseif ($risk_score > 50) {
            $recommendations[] = 'Send personalized encouragement email';
            $recommendations[] = 'Provide additional resources';
        } else {
            $recommendations[] = 'Monitor progress closely';
            $recommendations[] = 'Send gentle reminder';
        }
        
        return $recommendations;
    }
    
    private function get_risk_distribution($probabilities) {
        $distribution = array('high' => 0, 'medium' => 0, 'low' => 0);
        
        foreach ($probabilities as $prob) {
            if ($prob['risk_score'] > 70) {
                $distribution['high']++;
            } elseif ($prob['risk_score'] > 40) {
                $distribution['medium']++;
            } else {
                $distribution['low']++;
            }
        }
        
        return $distribution;
    }
    
    private function get_intervention_priorities($probabilities) {
        $priorities = array();
        
        foreach ($probabilities as $prob) {
            if ($prob['risk_score'] > 70) {
                $priorities[] = array(
                    'student' => $prob['name'],
                    'priority' => 'High',
                    'action' => 'Immediate intervention required'
                );
            }
        }
        
        return array_slice($priorities, 0, 10); // Top 10 priorities
    }
}