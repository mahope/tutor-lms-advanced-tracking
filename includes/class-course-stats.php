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
            
            $enrollment_record = TutorAdvancedTracking_TutorIntegration::get_enrollment_record($course_id, $user_id);

            $enrollment_date = '';
            if ($enrollment_record && !empty($enrollment_record->enrollment_date)) {
                $enrollment_date = TutorAdvancedTracking_TutorIntegration::format_enrollment_datetime($enrollment_record->enrollment_date);
            }

            if (!$enrollment_date) {
                $meta_enrollment = get_user_meta($user_id, '_tutor_enrolled_' . $course_id, true);
                if (!empty($meta_enrollment)) {
                    $enrollment_date = $meta_enrollment;
                }
            }

            if (!$enrollment_date) {
                $enrollment_date = current_time('mysql');
            }

            // Get progress using integration layer
            $progress = TutorAdvancedTracking_Cache::get_user_course_progress($user_id, $course_id);

            // Get quiz average
            $quiz_average = $this->get_student_quiz_average($course_id, $user_id);

            // Get last activity
            $last_activity = $this->get_student_last_activity($course_id, $user_id);

            // Get completion status
            $completion_status = $this->get_student_completion_status($course_id, $user_id, $enrollment_record);
            
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
        return $this->get_course_students_using_integration($course_id);
    }

    /**
     * Get course students with their progress - FALLBACK for missing tables
     */
    private function get_course_students($course_id) {
        return $this->get_course_students_using_integration($course_id);
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

        $last_quiz = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(qa.attempt_started_at) 
             FROM {$wpdb->prefix}tutor_quiz_attempts qa
             JOIN {$wpdb->posts} p ON qa.quiz_id = p.ID
             WHERE p.post_parent = %d AND qa.user_id = %d",
            $course_id,
            $user_id
        ));

        $lesson_activity_table = TutorAdvancedTracking_TutorIntegration::get_lesson_activity_table_name();
        $last_lesson = null;
        if ($lesson_activity_table) {
            $last_lesson = $wpdb->get_var($wpdb->prepare(
                "SELECT MAX(created_at) 
                 FROM {$lesson_activity_table}
                 WHERE course_id = %d AND user_id = %d",
                $course_id,
                $user_id
            ));
        }

        $dates = array_filter(array($last_quiz, $last_lesson));

        if (empty($dates)) {
            $enrollment_record = TutorAdvancedTracking_TutorIntegration::get_enrollment_record($course_id, $user_id);
            if ($enrollment_record && !empty($enrollment_record->enrollment_date)) {
                $enrollment_date = TutorAdvancedTracking_TutorIntegration::format_enrollment_datetime($enrollment_record->enrollment_date);
                return $enrollment_date ?: 'Never';
            }

            return 'Never';
        }

        $last_activity = max($dates);

        return TutorAdvancedTracking_TutorIntegration::format_enrollment_datetime($last_activity);
    }

    /**
     * Get student completion status using WordPress metadata
     */
    private function get_student_completion_status($course_id, $user_id, $enrollment_record = null) {
        // Check course completion date
        $completion_date = get_user_meta($user_id, '_tutor_course_completion_date_' . $course_id, true);
        if ($completion_date) {
            return 'Completed';
        }

        if (null === $enrollment_record) {
            $enrollment_record = TutorAdvancedTracking_TutorIntegration::get_enrollment_record($course_id, $user_id);
        }

        if ($enrollment_record && TutorAdvancedTracking_TutorIntegration::enrollment_is_completed($enrollment_record)) {
            return 'Completed';
        }

        return 'In Progress';
    }

    /**
     * Get average completion time
     */
    private function get_average_completion_time($course_id) {
        $table_name = TutorAdvancedTracking_TutorIntegration::get_enrollments_table_name();
        if (!$table_name) {
            return 0;
        }

        $columns = TutorAdvancedTracking_TutorIntegration::get_enrollment_columns_map();
        if (empty($columns['enrollment_date']) || empty($columns['completion_date']) || empty($columns['course_id'])) {
            return 0;
        }

        global $wpdb;

        $alias = 'e';
        $completion_column = $columns['completion_date'];
        $enrollment_column = $columns['enrollment_date'];
        $course_column = $columns['course_id'];

        $avg_days = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(DATEDIFF({$alias}.{$completion_column}, {$alias}.{$enrollment_column}))
             FROM {$table_name} {$alias}
             WHERE {$alias}.{$course_column} = %d
               AND {$alias}.{$completion_column} IS NOT NULL",
            $course_id
        ));

        return $avg_days ? round($avg_days) : 0;
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
}


