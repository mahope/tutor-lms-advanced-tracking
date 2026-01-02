<?php
/**
 * Test Enrollment Data
 * Run by going to any admin page and adding ?test_enrollment_data=1
 */

if (isset($_GET['test_enrollment_data']) && current_user_can('manage_options')) {
    add_action('admin_notices', function() {
        global $wpdb;
        
        echo '<div class="notice notice-info" style="max-width: none; padding: 20px;">';
        echo '<h2>🧪 Enrollment Data Test</h2>';
        
        // Step 1: Check tables
        echo '<h3>Step 1: Database Tables</h3>';
        $enrollment_table = $wpdb->prefix . 'tutor_enrollments';
        $exists = $wpdb->get_var("SHOW TABLES LIKE '$enrollment_table'") === $enrollment_table;
        echo "<p><strong>$enrollment_table:</strong> " . ($exists ? "✅ EXISTS" : "❌ MISSING") . "</p>";
        
        if (!$exists) {
            echo '<p><strong>ERROR:</strong> Enrollment table missing. Tutor LMS may not be properly installed.</p>';
            echo '</div>';
            return;
        }
        
        // Step 2: Raw enrollment data
        echo '<h3>Step 2: Raw Enrollment Data</h3>';
        $total_enrollments = $wpdb->get_var("SELECT COUNT(*) FROM $enrollment_table");
        echo "<p><strong>Total Enrollments:</strong> $total_enrollments</p>";
        
        if ($total_enrollments == 0) {
            echo '<p><strong>WARNING:</strong> No enrollments found. Users need to be enrolled in courses first.</p>';
        }
        
        // Show enrollment structure
        $columns = $wpdb->get_results("DESCRIBE $enrollment_table");
        echo '<p><strong>Table Structure:</strong></p>';
        echo '<ul>';
        foreach ($columns as $column) {
            echo '<li>' . $column->Field . ' (' . $column->Type . ')</li>';
        }
        echo '</ul>';
        
        // Sample enrollments
        if ($total_enrollments > 0) {
            $sample_enrollments = $wpdb->get_results("SELECT * FROM $enrollment_table LIMIT 3", ARRAY_A);
            echo '<p><strong>Sample Enrollments:</strong></p>';
            echo '<pre style="background: #f5f5f5; padding: 10px; font-size: 11px; max-height: 200px; overflow: auto;">';
            print_r($sample_enrollments);
            echo '</pre>';
        }
        
        // Step 3: Course data
        echo '<h3>Step 3: Course Data</h3>';
        $course_post_type = TutorAdvancedTracking_TutorIntegration::get_course_post_type();
        echo "<p><strong>Course Post Type:</strong> $course_post_type</p>";
        
        $course_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'",
            $course_post_type
        ));
        echo "<p><strong>Published Courses:</strong> $course_count</p>";
        
        if ($course_count > 0) {
            // Get course with most enrollments
            $popular_course = $wpdb->get_row("
                SELECT p.ID, p.post_title, COUNT(e.id) as enrollment_count
                FROM {$wpdb->posts} p
                LEFT JOIN $enrollment_table e ON p.ID = e.course_id
                WHERE p.post_type = '$course_post_type' AND p.post_status = 'publish'
                GROUP BY p.ID
                ORDER BY enrollment_count DESC
                LIMIT 1
            ");
            
            if ($popular_course) {
                echo '<p><strong>Most Popular Course:</strong> ' . $popular_course->post_title . ' (' . $popular_course->enrollment_count . ' enrollments)</p>';
                
                // Test our integration functions on this course
                echo '<h3>Step 4: Integration Function Test (Course: ' . $popular_course->ID . ')</h3>';
                
                try {
                    $enrollment_stats = TutorAdvancedTracking_TutorIntegration::get_course_enrollment_stats($popular_course->ID);
                    echo '<p><strong>Enrollment Stats:</strong></p>';
                    echo '<pre style="background: #f5f5f5; padding: 10px; font-size: 11px;">';
                    print_r($enrollment_stats);
                    echo '</pre>';
                    
                    $students = TutorAdvancedTracking_TutorIntegration::get_course_students($popular_course->ID);
                    echo '<p><strong>Students Retrieved:</strong> ' . count($students) . '</p>';
                    
                    if (!empty($students)) {
                        $first_student = $students[0];
                        $student_id = is_object($first_student) ? $first_student->ID : $first_student['ID'];
                        
                        $progress = TutorAdvancedTracking_TutorIntegration::get_user_course_progress($student_id, $popular_course->ID);
                        echo "<p><strong>Sample Student Progress:</strong> $progress%</p>";
                    }
                } catch (Exception $e) {
                    echo '<p><strong>❌ Integration Error:</strong> ' . $e->getMessage() . '</p>';
                }
                
                // Test cache system
                echo '<h3>Step 5: Cache System Test</h3>';
                try {
                    $cached_stats = TutorAdvancedTracking_Cache::get_course_stats($popular_course->ID);
                    echo '<p><strong>Cached Course Stats:</strong></p>';
                    echo '<pre style="background: #f5f5f5; padding: 10px; font-size: 11px;">';
                    print_r($cached_stats);
                    echo '</pre>';
                } catch (Exception $e) {
                    echo '<p><strong>❌ Cache Error:</strong> ' . $e->getMessage() . '</p>';
                }
            }
        }
        
        // Step 6: Tutor LMS Functions Test
        echo '<h3>Step 6: Tutor LMS Functions</h3>';
        if (function_exists('tutor_utils')) {
            echo '<p><strong>tutor_utils():</strong> ✅ Available</p>';
            
            if (isset($popular_course)) {
                try {
                    $tutor_enrolled = tutor_utils()->get_enrolled_users($popular_course->ID);
                    echo '<p><strong>tutor_utils()->get_enrolled_users():</strong> ' . count($tutor_enrolled) . ' students</p>';
                    
                    $tutor_count = tutor_utils()->count_enrolled_users($popular_course->ID);
                    echo '<p><strong>tutor_utils()->count_enrolled_users():</strong> ' . $tutor_count . ' students</p>';
                } catch (Exception $e) {
                    echo '<p><strong>❌ tutor_utils() error:</strong> ' . $e->getMessage() . '</p>';
                }
            }
        } else {
            echo '<p><strong>tutor_utils():</strong> ❌ Not Available</p>';
        }
        
        echo '</div>';
    });
}