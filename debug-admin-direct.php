<?php
/**
 * Direct Admin Debug - No AJAX Required
 * Add ?direct_debug=1 to admin page to run this
 */

if (isset($_GET['direct_debug']) && current_user_can('manage_options')) {
    add_action('admin_notices', function() {
        global $wpdb;
        
        echo '<div class="notice notice-info is-dismissible" style="max-width: none; padding: 20px;">';
        echo '<h2>🔍 Direct Debug Output</h2>';
        
        // Test 1: Basic Plugin Status
        echo '<h3>1. Plugin Status</h3>';
        echo '<p><strong>Plugin Active:</strong> ' . (class_exists('TutorAdvancedTracking_Dashboard') ? '✅ YES' : '❌ NO') . '</p>';
        echo '<p><strong>Tutor LMS Active:</strong> ' . (function_exists('tutor') ? '✅ YES' : '❌ NO') . '</p>';
        echo '<p><strong>Tutor Version:</strong> ' . (defined('TUTOR_VERSION') ? TUTOR_VERSION : 'Unknown') . '</p>';
        
        // Test 2: Database Tables
        echo '<h3>2. Database Tables</h3>';
        $tables = array('tutor_enrollments', 'tutor_quiz_attempts', 'tutor_lesson_activities');
        
        foreach ($tables as $table) {
            $full_table = $wpdb->prefix . $table;
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$full_table'") === $full_table;
            $count = 0;
            
            if ($exists) {
                $count = $wpdb->get_var("SELECT COUNT(*) FROM $full_table");
            }
            
            echo "<p><strong>$table:</strong> " . ($exists ? "✅ EXISTS ($count records)" : "❌ MISSING") . "</p>";
        }
        
        // Test 3: Course Post Types
        echo '<h3>3. Course Post Types</h3>';
        $course_types = array('courses', 'course', 'tutor_course');
        
        foreach ($course_types as $post_type) {
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'",
                $post_type
            ));
            echo "<p><strong>$post_type:</strong> $count courses</p>";
        }
        
        // Test 4: Dashboard Class Test
        echo '<h3>4. Dashboard Test</h3>';
        try {
            $dashboard = new TutorAdvancedTracking_Dashboard();
            $courses = $dashboard->get_courses();
            echo '<p><strong>Courses from Dashboard:</strong> ' . count($courses) . '</p>';
            
            if (!empty($courses)) {
                $first_course = $courses[0];
                echo '<p><strong>Sample Course Data:</strong></p>';
                echo '<pre style="background: #f5f5f5; padding: 10px; font-size: 11px; max-height: 200px; overflow: auto;">';
                print_r($first_course);
                echo '</pre>';
            }
        } catch (Exception $e) {
            echo '<p><strong>❌ Dashboard Error:</strong> ' . $e->getMessage() . '</p>';
        }
        
        // Test 5: Integration Layer Test
        echo '<h3>5. Integration Layer Test</h3>';
        try {
            $all_courses = TutorAdvancedTracking_TutorIntegration::get_all_courses();
            echo '<p><strong>Integration Courses:</strong> ' . count($all_courses) . '</p>';
            
            if (!empty($all_courses)) {
                $sample_course_id = is_object($all_courses[0]) ? $all_courses[0]->ID : $all_courses[0]['id'];
                echo '<p><strong>Sample Course ID:</strong> ' . $sample_course_id . '</p>';
                
                $enrollment_stats = TutorAdvancedTracking_TutorIntegration::get_course_enrollment_stats($sample_course_id);
                echo '<p><strong>Sample Enrollment Stats:</strong></p>';
                echo '<pre style="background: #f5f5f5; padding: 10px; font-size: 11px;">';
                print_r($enrollment_stats);
                echo '</pre>';
                
                // Test students for this course
                $students = TutorAdvancedTracking_TutorIntegration::get_course_students($sample_course_id);
                echo '<p><strong>Students for Course ' . $sample_course_id . ':</strong> ' . count($students) . '</p>';
            }
        } catch (Exception $e) {
            echo '<p><strong>❌ Integration Error:</strong> ' . $e->getMessage() . '</p>';
        }
        
        // Test 6: Direct Database Queries
        echo '<h3>6. Direct Database Queries</h3>';
        $enrollment_table = $wpdb->prefix . 'tutor_enrollments';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$enrollment_table'") === $enrollment_table) {
            // Total enrollments
            $total_enrollments = $wpdb->get_var("SELECT COUNT(*) FROM $enrollment_table");
            echo "<p><strong>Total Enrollments:</strong> $total_enrollments</p>";
            
            // Sample enrollment
            $sample_enrollment = $wpdb->get_row("SELECT * FROM $enrollment_table LIMIT 1", ARRAY_A);
            if ($sample_enrollment) {
                echo '<p><strong>Sample Enrollment:</strong></p>';
                echo '<pre style="background: #f5f5f5; padding: 10px; font-size: 11px;">';
                print_r($sample_enrollment);
                echo '</pre>';
            }
            
            // Enrollments by course
            $enrollments_by_course = $wpdb->get_results("
                SELECT course_id, COUNT(*) as student_count 
                FROM $enrollment_table 
                GROUP BY course_id 
                ORDER BY student_count DESC 
                LIMIT 5
            ");
            
            if ($enrollments_by_course) {
                echo '<p><strong>Top 5 Courses by Enrollment:</strong></p>';
                echo '<pre style="background: #f5f5f5; padding: 10px; font-size: 11px;">';
                print_r($enrollments_by_course);
                echo '</pre>';
            }
        }
        
        // Test 7: Cache Test
        echo '<h3>7. Cache Test</h3>';
        try {
            // Test cache
            TutorAdvancedTracking_Cache::set('test_key', 'test_value', 'tutor_advanced_tracking', 60);
            $cached_value = TutorAdvancedTracking_Cache::get('test_key', 'tutor_advanced_tracking');
            echo '<p><strong>Cache Test:</strong> ' . ($cached_value === 'test_value' ? '✅ WORKING' : '❌ NOT WORKING') . '</p>';
        } catch (Exception $e) {
            echo '<p><strong>❌ Cache Error:</strong> ' . $e->getMessage() . '</p>';
        }
        
        // Test 8: Tutor LMS Functions
        echo '<h3>8. Tutor LMS Functions Test</h3>';
        if (function_exists('tutor_utils')) {
            echo '<p><strong>tutor_utils():</strong> ✅ Available</p>';
            
            // Test with first course if available
            if (!empty($all_courses)) {
                $test_course_id = is_object($all_courses[0]) ? $all_courses[0]->ID : $all_courses[0]['id'];
                
                try {
                    $enrolled_users = tutor_utils()->get_enrolled_users($test_course_id);
                    echo "<p><strong>Enrolled users for course $test_course_id:</strong> " . count($enrolled_users) . "</p>";
                } catch (Exception $e) {
                    echo "<p><strong>❌ tutor_utils() error:</strong> " . $e->getMessage() . "</p>";
                }
            }
        } else {
            echo '<p><strong>tutor_utils():</strong> ❌ Not Available</p>';
        }
        
        if (function_exists('tutils')) {
            echo '<p><strong>tutils():</strong> ✅ Available</p>';
        } else {
            echo '<p><strong>tutils():</strong> ❌ Not Available</p>';
        }
        
        echo '</div>';
    });
}