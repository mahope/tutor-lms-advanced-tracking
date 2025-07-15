<?php
/**
 * Test Data Generator for Advanced Tutor LMS Stats Dashboard
 * 
 * IMPORTANT: This is a testing utility file. 
 * Only use this in development/testing environments.
 * DO NOT use this on production sites.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('This script must be run from WordPress admin or through WP-CLI');
}

class TutorAdvancedTracking_TestDataGenerator {
    
    public function generate_test_data() {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission to run this script.');
        }
        
        echo "<h2>Generating Test Data for Advanced Tutor LMS Stats Dashboard</h2>";
        
        // Create test users
        $this->create_test_users();
        
        // Create test courses
        $course_ids = $this->create_test_courses();
        
        // Create test enrollments
        $this->create_test_enrollments($course_ids);
        
        // Create test quiz attempts
        $this->create_test_quiz_attempts($course_ids);
        
        echo "<h3>✅ Test data generation completed!</h3>";
        echo "<p>You can now test the plugin with realistic data.</p>";
        echo "<p><strong>Next steps:</strong></p>";
        echo "<ul>";
        echo "<li>Create a page with the [tutor_advanced_stats] shortcode</li>";
        echo "<li>Visit the page to see the analytics dashboard</li>";
        echo "<li>Click 'View Details' on courses to see detailed analytics</li>";
        echo "<li>Click 'View Advanced Analytics' to see comprehensive insights</li>";
        echo "</ul>";
    }
    
    private function create_test_users() {
        echo "<h3>Creating Test Users...</h3>";
        
        // Create test instructor
        $instructor_id = wp_insert_user([
            'user_login' => 'test_instructor',
            'user_pass' => 'password123',
            'user_email' => 'instructor@test.com',
            'display_name' => 'Test Instructor',
            'first_name' => 'Test',
            'last_name' => 'Instructor',
            'role' => 'tutor_instructor'
        ]);
        
        if (is_wp_error($instructor_id)) {
            echo "<p>⚠️ Instructor creation failed or already exists</p>";
        } else {
            echo "<p>✅ Created test instructor (ID: $instructor_id)</p>";
        }
        
        // Create test students
        $student_names = [
            'Alice Johnson', 'Bob Smith', 'Carol Davis', 'David Wilson',
            'Emma Brown', 'Frank Miller', 'Grace Lee', 'Henry Taylor',
            'Ivy Chen', 'Jack Anderson', 'Kate Thompson', 'Liam Garcia',
            'Maya Patel', 'Nathan Clark', 'Olivia Rodriguez', 'Peter Kim',
            'Quinn O\'Connor', 'Rachel Moore', 'Sam Williams', 'Tina Zhang'
        ];
        
        $created_students = 0;
        foreach ($student_names as $index => $name) {
            $name_parts = explode(' ', $name);
            $first_name = $name_parts[0];
            $last_name = $name_parts[1] ?? '';
            $username = 'student' . ($index + 1);
            
            $user_id = wp_insert_user([
                'user_login' => $username,
                'user_pass' => 'password123',
                'user_email' => $username . '@test.com',
                'display_name' => $name,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'role' => 'subscriber'
            ]);
            
            if (!is_wp_error($user_id)) {
                $created_students++;
                
                // Add tutor student role if available
                if (function_exists('tutor_utils')) {
                    tutor_utils()->add_user_role($user_id, 'tutor_student');
                }
            }
        }
        
        echo "<p>✅ Created $created_students test students</p>";
    }
    
    private function create_test_courses() {
        echo "<h3>Creating Test Courses...</h3>";
        
        $instructor_id = get_user_by('login', 'test_instructor');
        $instructor_id = $instructor_id ? $instructor_id->ID : get_current_user_id();
        
        $courses = [
            [
                'title' => 'Introduction to Web Development',
                'content' => 'Learn the basics of HTML, CSS, and JavaScript in this comprehensive course.',
                'difficulty' => 'beginner'
            ],
            [
                'title' => 'Advanced PHP Programming',
                'content' => 'Master advanced PHP concepts including OOP, design patterns, and frameworks.',
                'difficulty' => 'advanced'
            ],
            [
                'title' => 'Digital Marketing Fundamentals',
                'content' => 'Understand the core principles of digital marketing and online advertising.',
                'difficulty' => 'intermediate'
            ],
            [
                'title' => 'Data Science with Python',
                'content' => 'Learn data analysis, visualization, and machine learning with Python.',
                'difficulty' => 'advanced'
            ]
        ];
        
        $course_ids = [];
        foreach ($courses as $course) {
            $course_id = wp_insert_post([
                'post_title' => $course['title'],
                'post_content' => $course['content'],
                'post_type' => 'courses',
                'post_status' => 'publish',
                'post_author' => $instructor_id
            ]);
            
            if (!is_wp_error($course_id)) {
                $course_ids[] = $course_id;
                
                // Add course meta
                update_post_meta($course_id, '_tutor_course_level', $course['difficulty']);
                update_post_meta($course_id, '_tutor_course_duration', rand(4, 12) . ' weeks');
                
                // Create lessons for the course
                $this->create_lessons_for_course($course_id);
                
                // Create quizzes for the course
                $this->create_quizzes_for_course($course_id);
                
                echo "<p>✅ Created course: {$course['title']} (ID: $course_id)</p>";
            }
        }
        
        return $course_ids;
    }
    
    private function create_lessons_for_course($course_id) {
        $lessons = [
            'Introduction and Setup',
            'Basic Concepts',
            'Intermediate Techniques',
            'Advanced Applications',
            'Project Work'
        ];
        
        foreach ($lessons as $index => $lesson_title) {
            wp_insert_post([
                'post_title' => $lesson_title,
                'post_content' => "This is the content for $lesson_title. It contains detailed explanations and examples.",
                'post_type' => 'lesson',
                'post_status' => 'publish',
                'post_parent' => $course_id,
                'menu_order' => $index + 1
            ]);
        }
    }
    
    private function create_quizzes_for_course($course_id) {
        $quizzes = [
            'Module 1 Quiz',
            'Module 2 Quiz',
            'Final Assessment'
        ];
        
        foreach ($quizzes as $index => $quiz_title) {
            $quiz_id = wp_insert_post([
                'post_title' => $quiz_title,
                'post_content' => "This is a quiz to test your knowledge of the course material.",
                'post_type' => 'tutor_quiz',
                'post_status' => 'publish',
                'post_parent' => $course_id,
                'menu_order' => $index + 1
            ]);
            
            if (!is_wp_error($quiz_id)) {
                // Add quiz questions
                $this->create_quiz_questions($quiz_id);
            }
        }
    }
    
    private function create_quiz_questions($quiz_id) {
        global $wpdb;
        
        $questions = [
            [
                'title' => 'What is the correct syntax for a PHP variable?',
                'type' => 'multiple_choice',
                'options' => ['$variable', 'variable', '%variable', '#variable'],
                'correct' => 0
            ],
            [
                'title' => 'Which HTML tag is used for the largest heading?',
                'type' => 'multiple_choice',
                'options' => ['<h6>', '<h1>', '<header>', '<heading>'],
                'correct' => 1
            ],
            [
                'title' => 'CSS stands for Cascading Style Sheets.',
                'type' => 'true_false',
                'options' => ['True', 'False'],
                'correct' => 0
            ]
        ];
        
        foreach ($questions as $question) {
            // Insert question into tutor_quiz_questions table
            $wpdb->insert(
                $wpdb->prefix . 'tutor_quiz_questions',
                [
                    'quiz_id' => $quiz_id,
                    'question_title' => $question['title'],
                    'question_type' => $question['type'],
                    'question_mark' => 10,
                    'question_settings' => serialize(['options' => $question['options']]),
                    'question_order' => 1
                ]
            );
        }
    }
    
    private function create_test_enrollments($course_ids) {
        echo "<h3>Creating Test Enrollments...</h3>";
        
        global $wpdb;
        
        $students = get_users(['role' => 'subscriber']);
        $enrollments_created = 0;
        
        foreach ($course_ids as $course_id) {
            // Enroll 60-80% of students in each course
            $enrollment_rate = rand(60, 80) / 100;
            $students_to_enroll = array_slice($students, 0, floor(count($students) * $enrollment_rate));
            
            foreach ($students_to_enroll as $student) {
                $enrollment_date = date('Y-m-d H:i:s', strtotime('-' . rand(1, 60) . ' days'));
                $is_completed = rand(0, 100) < 70; // 70% completion rate
                $completion_date = $is_completed ? date('Y-m-d H:i:s', strtotime($enrollment_date . ' +' . rand(1, 30) . ' days')) : null;
                
                $wpdb->insert(
                    $wpdb->prefix . 'tutor_enrollments',
                    [
                        'course_id' => $course_id,
                        'user_id' => $student->ID,
                        'enrollment_date' => $enrollment_date,
                        'completion_date' => $completion_date,
                        'is_completed' => $is_completed ? 1 : 0,
                        'status' => 'completed'
                    ]
                );
                
                if (!$wpdb->last_error) {
                    $enrollments_created++;
                }
            }
        }
        
        echo "<p>✅ Created $enrollments_created test enrollments</p>";
    }
    
    private function create_test_quiz_attempts($course_ids) {
        echo "<h3>Creating Test Quiz Attempts...</h3>";
        
        global $wpdb;
        
        $attempts_created = 0;
        
        foreach ($course_ids as $course_id) {
            // Get quizzes for this course
            $quizzes = get_posts([
                'post_type' => 'tutor_quiz',
                'post_parent' => $course_id,
                'post_status' => 'publish',
                'numberposts' => -1
            ]);
            
            // Get enrolled students
            $enrolled_students = $wpdb->get_results($wpdb->prepare(
                "SELECT user_id FROM {$wpdb->prefix}tutor_enrollments WHERE course_id = %d",
                $course_id
            ));
            
            foreach ($quizzes as $quiz) {
                foreach ($enrolled_students as $student) {
                    // 80% of students attempt each quiz
                    if (rand(0, 100) < 80) {
                        $attempt_date = date('Y-m-d H:i:s', strtotime('-' . rand(1, 30) . ' days'));
                        $total_marks = rand(50, 100);
                        $earned_marks = rand(floor($total_marks * 0.4), $total_marks); // 40-100% scores
                        
                        $wpdb->insert(
                            $wpdb->prefix . 'tutor_quiz_attempts',
                            [
                                'quiz_id' => $quiz->ID,
                                'user_id' => $student->user_id,
                                'total_marks' => $total_marks,
                                'earned_marks' => $earned_marks,
                                'attempt_started_at' => $attempt_date,
                                'attempt_ended_at' => date('Y-m-d H:i:s', strtotime($attempt_date . ' +' . rand(10, 60) . ' minutes')),
                                'attempt_status' => 'attempt_ended',
                                'is_manually_reviewed' => 0
                            ]
                        );
                        
                        if (!$wpdb->last_error) {
                            $attempts_created++;
                        }
                        
                        // Some students retry quizzes
                        if (rand(0, 100) < 30) {
                            $retry_date = date('Y-m-d H:i:s', strtotime($attempt_date . ' +' . rand(1, 5) . ' days'));
                            $retry_earned_marks = rand($earned_marks, $total_marks); // Usually improve on retry
                            
                            $wpdb->insert(
                                $wpdb->prefix . 'tutor_quiz_attempts',
                                [
                                    'quiz_id' => $quiz->ID,
                                    'user_id' => $student->user_id,
                                    'total_marks' => $total_marks,
                                    'earned_marks' => $retry_earned_marks,
                                    'attempt_started_at' => $retry_date,
                                    'attempt_ended_at' => date('Y-m-d H:i:s', strtotime($retry_date . ' +' . rand(10, 60) . ' minutes')),
                                    'attempt_status' => 'attempt_ended',
                                    'is_manually_reviewed' => 0
                                ]
                            );
                            
                            if (!$wpdb->last_error) {
                                $attempts_created++;
                            }
                        }
                    }
                }
            }
        }
        
        echo "<p>✅ Created $attempts_created test quiz attempts</p>";
    }
}

// Usage instructions
if (isset($_GET['action']) && $_GET['action'] === 'generate_test_data') {
    $generator = new TutorAdvancedTracking_TestDataGenerator();
    $generator->generate_test_data();
} else {
    echo "<h2>Test Data Generator</h2>";
    echo "<p>Click the button below to generate test data for the Advanced Tutor LMS Stats Dashboard plugin.</p>";
    echo "<p><strong>Warning:</strong> This will create test users, courses, enrollments, and quiz attempts. Only use this on development/testing sites.</p>";
    echo "<p><a href='?action=generate_test_data' class='button button-primary'>Generate Test Data</a></p>";
}
?>