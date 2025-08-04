<?php
/**
 * Debug Course Data Issues
 * 
 * This script specifically debugs course detail and student display issues
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('This script must be run from WordPress admin or through WP-CLI');
}

class TutorAdvancedTracking_CourseDataDebugger {
    
    public function run_debug() {
        echo "<h2>Course Data Debug</h2>";
        
        $course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;
        
        if (!$course_id) {
            echo "<p>Please add ?course_id=X to the URL to test specific course data</p>";
            $this->show_sample_courses();
            return;
        }
        
        echo "<h3>Debugging Course ID: $course_id</h3>";
        
        $this->check_course_exists($course_id);
        $this->check_course_post_type($course_id);
        $this->check_enrollment_data($course_id);
        $this->check_enrollment_statuses($course_id);
        $this->test_course_stats_queries($course_id);
        $this->show_sample_enrollments($course_id);
        $this->show_fixes_needed();
    }
    
    private function show_sample_courses() {
        echo "<h3>Available Courses</h3>";
        
        global $wpdb;
        
        // Check both possible post types
        $course_types = ['courses', 'course'];
        
        foreach ($course_types as $type) {
            $courses = $wpdb->get_results($wpdb->prepare(
                "SELECT ID, post_title, post_author FROM {$wpdb->posts} 
                 WHERE post_type = %s AND post_status = 'publish' 
                 LIMIT 10",
                $type
            ));
            
            if ($courses) {
                echo "<h4>Post Type: $type</h4>";
                echo "<ul>";
                foreach ($courses as $course) {
                    echo "<li><a href='?action=debug_course&course_id={$course->ID}'>{$course->post_title} (ID: {$course->ID})</a></li>";
                }
                echo "</ul>";
            }
        }
    }
    
    private function check_course_exists($course_id) {
        echo "<h4>Course Existence Check</h4>";
        
        $course = get_post($course_id);
        
        if (!$course) {
            echo "<p>❌ Course with ID $course_id does not exist</p>";
            return false;
        }
        
        echo "<p>✅ Course exists: <strong>{$course->post_title}</strong></p>";
        echo "<p>📝 Post Type: <strong>{$course->post_type}</strong></p>";
        echo "<p>👤 Author: <strong>{$course->post_author}</strong></p>";
        echo "<p>📅 Status: <strong>{$course->post_status}</strong></p>";
        
        return true;
    }
    
    private function check_course_post_type($course_id) {
        echo "<h4>Post Type Analysis</h4>";
        
        $course = get_post($course_id);
        
        if ($course->post_type === 'courses') {
            echo "<p>✅ Course has correct post_type: 'courses'</p>";
        } elseif ($course->post_type === 'course') {
            echo "<p>⚠️ Course has post_type: 'course' (singular)</p>";
            echo "<p>💡 Fix: Update class-course-stats.php line 35 to check for 'course' instead of 'courses'</p>";
        } else {
            echo "<p>❌ Course has unexpected post_type: {$course->post_type}</p>";
        }
    }
    
    private function check_enrollment_data($course_id) {
        echo "<h4>Enrollment Data Check</h4>";
        
        global $wpdb;
        
        $enrollments_table = $wpdb->prefix . 'tutor_enrollments';
        
        // Check if table exists
        if (!$wpdb->get_var("SHOW TABLES LIKE '$enrollments_table'")) {
            echo "<p>❌ Enrollments table does not exist: $enrollments_table</p>";
            return;
        }
        
        // Check total enrollments for this course
        $total_enrollments = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $enrollments_table WHERE course_id = %d",
            $course_id
        ));
        
        echo "<p>📊 Total Enrollments: <strong>$total_enrollments</strong></p>";
        
        if ($total_enrollments === '0') {
            echo "<p>❌ No enrollments found for this course</p>";
            $this->suggest_enrollment_fixes($course_id);
        }
    }
    
    private function check_enrollment_statuses($course_id) {
        echo "<h4>Enrollment Status Analysis</h4>";
        
        global $wpdb;
        
        $enrollments_table = $wpdb->prefix . 'tutor_enrollments';
        
        $statuses = $wpdb->get_results($wpdb->prepare(
            "SELECT status, COUNT(*) as count FROM $enrollments_table 
             WHERE course_id = %d GROUP BY status",
            $course_id
        ));
        
        if ($statuses) {
            echo "<p>📈 Enrollment Status Distribution:</p>";
            echo "<ul>";
            foreach ($statuses as $status) {
                echo "<li>{$status->status}: {$status->count} enrollments</li>";
            }
            echo "</ul>";
            
            // Check if plugin is filtering too restrictively
            $completed_only = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $enrollments_table 
                 WHERE course_id = %d AND status = 'completed'",
                $course_id
            ));
            
            echo "<p>🔍 Current Plugin Filter (status = 'completed'): <strong>$completed_only</strong> students</p>";
            
            if ($completed_only == 0) {
                echo "<p>❌ Plugin is filtering for 'completed' status but no enrollments have this status</p>";
                echo "<p>💡 Fix: Remove or modify the status filter in class-course-stats.php</p>";
            }
        } else {
            echo "<p>❌ No enrollment statuses found</p>";
        }
    }
    
    private function test_course_stats_queries($course_id) {
        echo "<h4>Testing Course Stats Queries</h4>";
        
        global $wpdb;
        
        $enrollments_table = $wpdb->prefix . 'tutor_enrollments';
        
        // Test current restrictive query
        $restrictive_query = $wpdb->prepare(
            "SELECT e.user_id, e.enrollment_date, u.display_name, u.user_email
             FROM $enrollments_table e
             JOIN {$wpdb->users} u ON e.user_id = u.ID
             WHERE e.course_id = %d AND e.status = 'completed'
             ORDER BY e.enrollment_date DESC",
            $course_id
        );
        
        $restrictive_results = $wpdb->get_results($restrictive_query);
        echo "<p>🔍 Current Restrictive Query Results: <strong>" . count($restrictive_results) . "</strong> students</p>";
        
        // Test improved query without status filter
        $improved_query = $wpdb->prepare(
            "SELECT e.user_id, e.enrollment_date, u.display_name, u.user_email, e.status
             FROM $enrollments_table e
             JOIN {$wpdb->users} u ON e.user_id = u.ID
             WHERE e.course_id = %d
             ORDER BY e.enrollment_date DESC",
            $course_id
        );
        
        $improved_results = $wpdb->get_results($improved_query);
        echo "<p>✅ Improved Query Results (no status filter): <strong>" . count($improved_results) . "</strong> students</p>";
        
        if (!empty($improved_results)) {
            echo "<p>📋 Sample Students:</p>";
            echo "<ul>";
            foreach (array_slice($improved_results, 0, 3) as $student) {
                echo "<li>{$student->display_name} ({$student->user_email}) - Status: {$student->status}</li>";
            }
            echo "</ul>";
        }
    }
    
    private function show_sample_enrollments($course_id) {
        echo "<h4>Sample Enrollment Data</h4>";
        
        global $wpdb;
        
        $enrollments_table = $wpdb->prefix . 'tutor_enrollments';
        
        $sample_data = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $enrollments_table WHERE course_id = %d LIMIT 3",
            $course_id
        ));
        
        if ($sample_data) {
            echo "<p>📊 Sample Enrollment Records:</p>";
            echo "<table border='1' cellpadding='5'>";
            echo "<tr><th>User ID</th><th>Status</th><th>Enrollment Date</th><th>Completion Date</th><th>Is Completed</th></tr>";
            
            foreach ($sample_data as $enrollment) {
                echo "<tr>";
                echo "<td>{$enrollment->user_id}</td>";
                echo "<td>{$enrollment->status}</td>";
                echo "<td>{$enrollment->enrollment_date}</td>";
                echo "<td>" . ($enrollment->completion_date ?? 'NULL') . "</td>";
                echo "<td>" . ($enrollment->is_completed ?? 'NULL') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p>❌ No enrollment data found</p>";
        }
    }
    
    private function suggest_enrollment_fixes($course_id) {
        echo "<h4>Suggested Fixes</h4>";
        
        global $wpdb;
        
        // Check if there are any students in the system
        $total_users = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->users}");
        echo "<p>👥 Total Users in System: <strong>$total_users</strong></p>";
        
        // Check if there are enrollments for other courses
        $enrollments_table = $wpdb->prefix . 'tutor_enrollments';
        $other_enrollments = $wpdb->get_var("SELECT COUNT(*) FROM $enrollments_table");
        echo "<p>📚 Total Enrollments (All Courses): <strong>$other_enrollments</strong></p>";
        
        if ($other_enrollments > 0) {
            echo "<p>💡 There are enrollments in the system, but not for this course</p>";
            echo "<p>🔧 Try using the test data generator to create sample enrollments</p>";
        } else {
            echo "<p>💡 No enrollments found in the system</p>";
            echo "<p>🔧 Use the test data generator to create sample data</p>";
        }
    }
    
    private function show_fixes_needed() {
        echo "<h3>Required Fixes</h3>";
        
        echo "<div style='background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 20px 0;'>";
        echo "<h4>⚠️ Critical Issues Found</h4>";
        echo "<ol>";
        echo "<li><strong>Status Filter Too Restrictive:</strong> Remove 'status = completed' filter from class-course-stats.php line 78</li>";
        echo "<li><strong>Course Post Type Check:</strong> Update class-course-stats.php line 35 to use dynamic post type detection</li>";
        echo "<li><strong>Stats Query Issues:</strong> Remove status filters from get_course_stats() method</li>";
        echo "</ol>";
        echo "</div>";
        
        echo "<div style='background: #d1ecf1; padding: 15px; border-left: 4px solid #0dcaf0; margin: 20px 0;'>";
        echo "<h4>🔧 Quick Fix Instructions</h4>";
        echo "<p>1. Edit <code>includes/class-course-stats.php</code></p>";
        echo "<p>2. Line 35: Change post_type check to use dynamic detection</p>";
        echo "<p>3. Line 78: Remove <code>AND e.status = 'completed'</code></p>";
        echo "<p>4. Lines 160 & 166: Remove status filters from stats calculations</p>";
        echo "</div>";
    }
}

// Usage
if (isset($_GET['action']) && $_GET['action'] === 'debug_course') {
    $debugger = new TutorAdvancedTracking_CourseDataDebugger();
    $debugger->run_debug();
} else {
    echo "<h2>Course Data Debugger</h2>";
    echo "<p>This tool debugs why users and progress aren't showing in course details.</p>";
    echo "<p><a href='?action=debug_course' class='button button-primary'>Debug Course Data</a></p>";
}
?>