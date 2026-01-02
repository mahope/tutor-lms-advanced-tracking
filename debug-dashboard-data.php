<?php
/**
 * Debug Dashboard Data Issues
 * Add ?debug_dashboard=1 to any page to run this
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('This script must be run from WordPress admin');
}

if (isset($_GET['debug_dashboard']) && current_user_can('manage_options')) {
    add_action('wp_footer', function() {
        echo '<div style="background: #fff; padding: 20px; margin: 20px; border: 2px solid #ccc; position: relative; z-index: 9999;">';
        echo '<h2>🔍 Dashboard Data Debug</h2>';
        
        // Test 1: Check if classes exist
        echo '<h3>1. Class Existence Check</h3>';
        $classes = [
            'TutorAdvancedTracking_Dashboard',
            'TutorAdvancedTracking_Cache',
            'TutorAdvancedTracking_TutorIntegration'
        ];
        
        foreach ($classes as $class) {
            echo '<p>' . $class . ': ' . (class_exists($class) ? '✅ EXISTS' : '❌ MISSING') . '</p>';
        }
        
        // Test 2: Get courses directly
        echo '<h3>2. Direct Course Query</h3>';
        global $wpdb;
        
        $course_post_types = ['courses', 'course'];
        foreach ($course_post_types as $post_type) {
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'",
                $post_type
            ));
            echo '<p>Post type "' . $post_type . '": ' . $count . ' courses</p>';
        }
        
        // Test 3: Check enrollment table
        echo '<h3>3. Enrollment Table Check</h3>';
        $enrollment_table = $wpdb->prefix . 'tutor_enrollments';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$enrollment_table'") === $enrollment_table;
        echo '<p>Table ' . $enrollment_table . ': ' . ($table_exists ? '✅ EXISTS' : '❌ MISSING') . '</p>';
        
        if ($table_exists) {
            $enrollment_count = $wpdb->get_var("SELECT COUNT(*) FROM $enrollment_table");
            echo '<p>Total enrollments: ' . $enrollment_count . '</p>';
        }
        
        // Test 4: Test dashboard class
        echo '<h3>4. Dashboard Class Test</h3>';
        try {
            $dashboard = new TutorAdvancedTracking_Dashboard();
            $courses = $dashboard->get_courses();
            echo '<p>Courses found: ' . count($courses) . '</p>';
            
            if (!empty($courses)) {
                $first_course = $courses[0];
                echo '<p>Sample course data:</p>';
                echo '<pre style="background: #f5f5f5; padding: 10px; overflow: auto;">';
                print_r($first_course);
                echo '</pre>';
            }
        } catch (Exception $e) {
            echo '<p>❌ Error: ' . $e->getMessage() . '</p>';
        }
        
        // Test 5: Test integration layer
        echo '<h3>5. Integration Layer Test</h3>';
        if (class_exists('TutorAdvancedTracking_TutorIntegration')) {
            try {
                $all_courses = TutorAdvancedTracking_TutorIntegration::get_all_courses();
                echo '<p>Integration layer courses: ' . count($all_courses) . '</p>';
                
                if (!empty($all_courses)) {
                    $sample_course_id = is_object($all_courses[0]) ? $all_courses[0]->ID : $all_courses[0]['id'];
                    $enrollment_stats = TutorAdvancedTracking_TutorIntegration::get_course_enrollment_stats($sample_course_id);
                    echo '<p>Sample enrollment stats:</p>';
                    echo '<pre style="background: #f5f5f5; padding: 10px; overflow: auto;">';
                    print_r($enrollment_stats);
                    echo '</pre>';
                }
            } catch (Exception $e) {
                echo '<p>❌ Integration error: ' . $e->getMessage() . '</p>';
            }
        }
        
        echo '</div>';
    });
}