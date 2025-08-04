<?php
/**
 * Debug Script for Advanced Tutor LMS Stats Dashboard
 * 
 * This script diagnoses data display issues - add to WordPress admin or run via WP-CLI
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('This script must be run from WordPress admin or through WP-CLI');
}

class TutorAdvancedTracking_DataDebugger {
    
    public function run_debug() {
        echo "<h2>Advanced Tutor LMS Stats Dashboard - Data Debug</h2>";
        
        $this->check_basic_wordpress_data();
        $this->check_tutor_lms_installation();
        $this->check_database_tables();
        $this->check_post_types();
        $this->check_enrollment_data();
        $this->check_quiz_data();
        $this->check_shortcode_parameters();
        $this->test_course_queries();
        $this->display_recommendations();
    }
    
    private function check_basic_wordpress_data() {
        echo "<h3>📊 Basic WordPress Data</h3>";
        
        global $wpdb;
        
        // Check total users
        $total_users = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->users}");
        echo "<p>✅ Total WordPress Users: <strong>$total_users</strong></p>";
        
        // Check user roles
        $user_roles = $wpdb->get_results("
            SELECT meta_value, COUNT(*) as count 
            FROM {$wpdb->usermeta} 
            WHERE meta_key = 'wp_capabilities' 
            GROUP BY meta_value
        ");
        
        echo "<p>👥 User Roles Distribution:</p>";
        echo "<ul>";
        foreach ($user_roles as $role) {
            $capabilities = unserialize($role->meta_value);
            $role_name = is_array($capabilities) ? implode(', ', array_keys($capabilities)) : 'Unknown';
            echo "<li>$role_name: {$role->count} users</li>";
        }
        echo "</ul>";
        
        // Check posts
        $total_posts = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts}");
        echo "<p>📄 Total Posts: <strong>$total_posts</strong></p>";
    }
    
    private function check_tutor_lms_installation() {
        echo "<h3>🎓 Tutor LMS Installation</h3>";
        
        // Check if Tutor LMS is active
        if (is_plugin_active('tutor/tutor.php')) {
            echo "<p>✅ Tutor LMS (Free) is active</p>";
        } else {
            echo "<p>❌ Tutor LMS (Free) is not active</p>";
        }
        
        if (is_plugin_active('tutor-pro/tutor-pro.php')) {
            echo "<p>✅ Tutor LMS Pro is active</p>";
        } else {
            echo "<p>❌ Tutor LMS Pro is not active</p>";
        }
        
        // Check if tutor() function exists
        if (function_exists('tutor')) {
            echo "<p>✅ Tutor functions are available</p>";
            
            // Get Tutor version
            if (defined('TUTOR_VERSION')) {
                echo "<p>📦 Tutor Version: " . TUTOR_VERSION . "</p>";
            }
        } else {
            echo "<p>❌ Tutor functions are not available</p>";
        }
    }
    
    private function check_database_tables() {
        echo "<h3>🗄️ Database Tables</h3>";
        
        global $wpdb;
        
        $required_tables = [
            'tutor_enrollments',
            'tutor_quiz_attempts',
            'tutor_quiz_questions',
            'tutor_quiz_question_answers'
        ];
        
        foreach ($required_tables as $table) {
            $table_name = $wpdb->prefix . $table;
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
            
            if ($exists) {
                $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
                echo "<p>✅ $table_name exists with <strong>$count</strong> records</p>";
                
                // Show table structure for key tables
                if ($table === 'tutor_enrollments') {
                    $columns = $wpdb->get_results("DESCRIBE $table_name");
                    echo "<details><summary>Table structure for $table_name</summary><ul>";
                    foreach ($columns as $col) {
                        echo "<li>{$col->Field} ({$col->Type})</li>";
                    }
                    echo "</ul></details>";
                }
            } else {
                echo "<p>❌ $table_name does not exist</p>";
            }
        }
    }
    
    private function check_post_types() {
        echo "<h3>📚 Post Types</h3>";
        
        global $wpdb;
        
        // Check for course post type
        $course_post_types = $wpdb->get_results("
            SELECT post_type, COUNT(*) as count 
            FROM {$wpdb->posts} 
            WHERE post_type LIKE '%course%' 
            GROUP BY post_type
        ");
        
        if ($course_post_types) {
            echo "<p>📖 Course-related post types:</p>";
            echo "<ul>";
            foreach ($course_post_types as $type) {
                echo "<li><strong>{$type->post_type}</strong>: {$type->count} posts</li>";
            }
            echo "</ul>";
        } else {
            echo "<p>❌ No course post types found</p>";
        }
        
        // Check for lesson post type
        $lesson_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'lesson'");
        echo "<p>📄 Lessons: <strong>$lesson_count</strong></p>";
        
        // Check for quiz post type
        $quiz_post_types = $wpdb->get_results("
            SELECT post_type, COUNT(*) as count 
            FROM {$wpdb->posts} 
            WHERE post_type LIKE '%quiz%' 
            GROUP BY post_type
        ");
        
        if ($quiz_post_types) {
            echo "<p>🧩 Quiz-related post types:</p>";
            echo "<ul>";
            foreach ($quiz_post_types as $type) {
                echo "<li><strong>{$type->post_type}</strong>: {$type->count} posts</li>";
            }
            echo "</ul>";
        } else {
            echo "<p>❌ No quiz post types found</p>";
        }
    }
    
    private function check_enrollment_data() {
        echo "<h3>👨‍🎓 Enrollment Data</h3>";
        
        global $wpdb;
        
        $enrollments_table = $wpdb->prefix . 'tutor_enrollments';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$enrollments_table'")) {
            // Check total enrollments
            $total_enrollments = $wpdb->get_var("SELECT COUNT(*) FROM $enrollments_table");
            echo "<p>📊 Total Enrollments: <strong>$total_enrollments</strong></p>";
            
            // Check enrollment statuses
            $statuses = $wpdb->get_results("
                SELECT status, COUNT(*) as count 
                FROM $enrollments_table 
                GROUP BY status
            ");
            
            if ($statuses) {
                echo "<p>📈 Enrollment Statuses:</p>";
                echo "<ul>";
                foreach ($statuses as $status) {
                    echo "<li>{$status->status}: {$status->count} enrollments</li>";
                }
                echo "</ul>";
            }
            
            // Check sample enrollment data
            $sample_enrollments = $wpdb->get_results("
                SELECT course_id, user_id, status, enrollment_date 
                FROM $enrollments_table 
                LIMIT 5
            ");
            
            if ($sample_enrollments) {
                echo "<p>📋 Sample Enrollments:</p>";
                echo "<ul>";
                foreach ($sample_enrollments as $enrollment) {
                    echo "<li>Course ID: {$enrollment->course_id}, User ID: {$enrollment->user_id}, Status: {$enrollment->status}</li>";
                }
                echo "</ul>";
            }
        } else {
            echo "<p>❌ Enrollments table not found</p>";
        }
    }
    
    private function check_quiz_data() {
        echo "<h3>🧩 Quiz Data</h3>";
        
        global $wpdb;
        
        $quiz_attempts_table = $wpdb->prefix . 'tutor_quiz_attempts';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$quiz_attempts_table'")) {
            $total_attempts = $wpdb->get_var("SELECT COUNT(*) FROM $quiz_attempts_table");
            echo "<p>🎯 Total Quiz Attempts: <strong>$total_attempts</strong></p>";
            
            // Check attempt statuses
            $statuses = $wpdb->get_results("
                SELECT attempt_status, COUNT(*) as count 
                FROM $quiz_attempts_table 
                GROUP BY attempt_status
            ");
            
            if ($statuses) {
                echo "<p>📊 Attempt Statuses:</p>";
                echo "<ul>";
                foreach ($statuses as $status) {
                    echo "<li>{$status->attempt_status}: {$status->count} attempts</li>";
                }
                echo "</ul>";
            }
        } else {
            echo "<p>❌ Quiz attempts table not found</p>";
        }
    }
    
    private function check_shortcode_parameters() {
        echo "<h3>🔗 Shortcode Parameters</h3>";
        
        // Check current URL parameters
        $view = $_GET['view'] ?? 'dashboard';
        $course_id = $_GET['course_id'] ?? 0;
        $user_id = $_GET['user_id'] ?? 0;
        
        echo "<p>📍 Current URL Parameters:</p>";
        echo "<ul>";
        echo "<li>View: <strong>$view</strong></li>";
        echo "<li>Course ID: <strong>$course_id</strong></li>";
        echo "<li>User ID: <strong>$user_id</strong></li>";
        echo "</ul>";
        
        // Check if shortcode page exists
        global $post;
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'tutor_advanced_stats')) {
            echo "<p>✅ Shortcode found on current page</p>";
        } else {
            echo "<p>⚠️ Shortcode not found on current page</p>";
        }
    }
    
    private function test_course_queries() {
        echo "<h3>🔍 Testing Course Queries</h3>";
        
        global $wpdb;
        
        // Test the exact query from the dashboard class
        $sql = "SELECT p.ID, p.post_title, p.post_status, p.post_author
                FROM {$wpdb->posts} p
                WHERE p.post_type = 'courses'
                AND p.post_status = 'publish'";
        
        $courses = $wpdb->get_results($sql);
        
        echo "<p>🎯 Dashboard Query Result: <strong>" . count($courses) . "</strong> courses found</p>";
        
        if (!empty($courses)) {
            echo "<p>📚 Sample Courses:</p>";
            echo "<ul>";
            foreach (array_slice($courses, 0, 3) as $course) {
                echo "<li>ID: {$course->ID}, Title: {$course->post_title}, Author: {$course->post_author}</li>";
            }
            echo "</ul>";
        }
        
        // Test alternative post type
        $alt_sql = "SELECT p.ID, p.post_title, p.post_status, p.post_author
                    FROM {$wpdb->posts} p
                    WHERE p.post_type = 'course'
                    AND p.post_status = 'publish'";
        
        $alt_courses = $wpdb->get_results($alt_sql);
        echo "<p>🔄 Alternative Query (post_type = 'course'): <strong>" . count($alt_courses) . "</strong> courses found</p>";
        
        // Test enrollment query for a sample course
        if (!empty($courses)) {
            $sample_course_id = $courses[0]->ID;
            $enrollments_table = $wpdb->prefix . 'tutor_enrollments';
            
            if ($wpdb->get_var("SHOW TABLES LIKE '$enrollments_table'")) {
                $enrollment_count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(DISTINCT user_id) FROM $enrollments_table WHERE course_id = %d",
                    $sample_course_id
                ));
                
                echo "<p>👥 Sample Course ({$courses[0]->post_title}) Enrollments: <strong>$enrollment_count</strong></p>";
            }
        }
    }
    
    private function display_recommendations() {
        echo "<h3>💡 Recommendations</h3>";
        
        global $wpdb;
        
        // Check what the correct post type should be
        $course_post_types = $wpdb->get_results("
            SELECT post_type, COUNT(*) as count 
            FROM {$wpdb->posts} 
            WHERE post_type LIKE '%course%' 
            GROUP BY post_type
            ORDER BY count DESC
        ");
        
        if ($course_post_types) {
            $main_course_type = $course_post_types[0];
            
            if ($main_course_type->post_type !== 'courses') {
                echo "<div style='background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 20px 0;'>";
                echo "<h4>⚠️ Course Post Type Mismatch</h4>";
                echo "<p>The plugin is looking for post_type = 'courses', but your system has <strong>{$main_course_type->count}</strong> posts with post_type = '<strong>{$main_course_type->post_type}</strong>'</p>";
                echo "<p><strong>Fix:</strong> Update the course query in class-dashboard.php line 46 from 'courses' to '{$main_course_type->post_type}'</p>";
                echo "</div>";
            }
        }
        
        // Check enrollment table
        $enrollments_table = $wpdb->prefix . 'tutor_enrollments';
        if (!$wpdb->get_var("SHOW TABLES LIKE '$enrollments_table'")) {
            echo "<div style='background: #f8d7da; padding: 15px; border-left: 4px solid #dc3545; margin: 20px 0;'>";
            echo "<h4>❌ Missing Enrollment Table</h4>";
            echo "<p>The tutor_enrollments table is missing. This is required for the plugin to work.</p>";
            echo "<p><strong>Fix:</strong> Ensure Tutor LMS is properly installed and activated</p>";
            echo "</div>";
        }
        
        // Check URL parameter handling
        if (!isset($_GET['view']) || !isset($_GET['course_id'])) {
            echo "<div style='background: #d1ecf1; padding: 15px; border-left: 4px solid #0dcaf0; margin: 20px 0;'>";
            echo "<h4>🔗 URL Parameter Issue</h4>";
            echo "<p>The shortcode is not receiving URL parameters properly. This affects course clicking.</p>";
            echo "<p><strong>Fix:</strong> The shortcode should check \$_GET parameters instead of shortcode attributes for view navigation</p>";
            echo "</div>";
        }
    }
}

// Usage
if (isset($_GET['action']) && $_GET['action'] === 'debug_data') {
    $debugger = new TutorAdvancedTracking_DataDebugger();
    $debugger->run_debug();
} else {
    echo "<h2>Data Debugger</h2>";
    echo "<p>This tool will help diagnose why the plugin shows zero users and why course clicks don't work.</p>";
    echo "<p><a href='?action=debug_data' class='button button-primary'>Run Data Debug</a></p>";
}
?>