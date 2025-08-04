<?php
/**
 * Test Script for Course Data Fixes
 * 
 * This script tests if the enrollment and progress fixes are working
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('This script must be run from WordPress admin or through WP-CLI');
}

class TutorAdvancedTracking_TestFixes {
    
    public function run_tests() {
        echo "<h2>Testing Course Data Fixes</h2>";
        
        $this->test_dashboard_courses();
        $this->test_course_details();
        $this->show_sample_course_data();
    }
    
    private function test_dashboard_courses() {
        echo "<h3>Testing Dashboard Course Data</h3>";
        
        // Test the dashboard class
        $dashboard = new TutorAdvancedTracking_Dashboard();
        $courses = $dashboard->get_courses();
        
        echo "<p>📊 Dashboard found <strong>" . count($courses) . "</strong> courses</p>";
        
        if (!empty($courses)) {
            echo "<p>✅ Sample courses:</p>";
            echo "<ul>";
            foreach (array_slice($courses, 0, 3) as $course) {
                echo "<li><strong>{$course['title']}</strong> - {$course['student_count']} students</li>";
            }
            echo "</ul>";
        } else {
            echo "<p>❌ No courses found in dashboard</p>";
        }
    }
    
    private function test_course_details() {
        echo "<h3>Testing Course Details</h3>";
        
        // Get a sample course
        global $wpdb;
        
        $course_post_types = ['courses', 'course'];
        $sample_course_id = null;
        
        foreach ($course_post_types as $type) {
            $course_id = $wpdb->get_var($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish' LIMIT 1",
                $type
            ));
            
            if ($course_id) {
                $sample_course_id = $course_id;
                break;
            }
        }
        
        if (!$sample_course_id) {
            echo "<p>❌ No sample course found for testing</p>";
            return;
        }
        
        echo "<p>🎯 Testing with Course ID: <strong>$sample_course_id</strong></p>";
        
        // Test course stats
        $course_stats = new TutorAdvancedTracking_CourseStats();
        $course_data = $course_stats->get_course_details($sample_course_id);
        
        if ($course_data) {
            echo "<p>✅ Course details loaded successfully</p>";
            echo "<p>📚 Course: <strong>{$course_data['title']}</strong></p>";
            echo "<p>👨‍🏫 Instructor: <strong>{$course_data['instructor']}</strong></p>";
            echo "<p>👥 Students: <strong>" . count($course_data['students']) . "</strong></p>";
            echo "<p>📊 Stats: {$course_data['stats']['total_students']} total, {$course_data['stats']['completed_students']} completed</p>";
            
            if (!empty($course_data['students'])) {
                echo "<p>✅ Sample students:</p>";
                echo "<ul>";
                foreach (array_slice($course_data['students'], 0, 3) as $student) {
                    echo "<li><strong>{$student['name']}</strong> - {$student['progression']}% progress</li>";
                }
                echo "</ul>";
            } else {
                echo "<p>⚠️ No students found for this course</p>";
            }
        } else {
            echo "<p>❌ Course details could not be loaded</p>";
        }
    }
    
    private function show_sample_course_data() {
        echo "<h3>Sample Course Data</h3>";
        
        global $wpdb;
        
        // Show enrollment data
        $enrollments_table = $wpdb->prefix . 'tutor_enrollments';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$enrollments_table'")) {
            $sample_enrollments = $wpdb->get_results("SELECT * FROM $enrollments_table LIMIT 5");
            
            if ($sample_enrollments) {
                echo "<p>📋 Sample Enrollment Data:</p>";
                echo "<table border='1' style='border-collapse: collapse;'>";
                echo "<tr><th>Course ID</th><th>User ID</th><th>Status</th><th>Enrollment Date</th></tr>";
                
                foreach ($sample_enrollments as $enrollment) {
                    echo "<tr>";
                    echo "<td>{$enrollment->course_id}</td>";
                    echo "<td>{$enrollment->user_id}</td>";
                    echo "<td>{$enrollment->status}</td>";
                    echo "<td>{$enrollment->enrollment_date}</td>";
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "<p>❌ No enrollment data found</p>";
            }
        } else {
            echo "<p>❌ Enrollment table not found</p>";
        }
    }
}

// Usage
if (isset($_GET['action']) && $_GET['action'] === 'test_fixes') {
    $tester = new TutorAdvancedTracking_TestFixes();
    $tester->run_tests();
} else {
    echo "<h2>Test Fixes</h2>";
    echo "<p>This script tests if the course data fixes are working correctly.</p>";
    echo "<p><a href='?action=test_fixes' class='button button-primary'>Run Tests</a></p>";
}
?>