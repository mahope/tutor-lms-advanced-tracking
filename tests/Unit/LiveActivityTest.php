<?php
/**
 * Unit tests for Live Activity Feed
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Mock WordPress functions needed for tests
 */
if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce($action) {
        return 'mock_nonce_' . $action;
    }
}

if (!function_exists('wp_send_json_success')) {
    function wp_send_json_success($data = null) {
        echo json_encode(array('success' => true, 'data' => $data));
    }
}

if (!function_exists('wp_send_json_error')) {
    function wp_send_json_error($message = '', $data = null) {
        echo json_encode(array('success' => false, 'data' => $data, 'message' => $message));
    }
}

if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce($nonce, $action) {
        return $nonce === 'mock_nonce_' . $action;
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can($cap) {
        return true; // Allow all for testing
    }
}

if (!function_exists('is_user_logged_in')) {
    function is_user_logged_in() {
        return true;
    }
}

if (!function_exists('get_current_user_id')) {
    function get_current_user_id() {
        return 1;
    }
}

if (!function_exists('get_userdata')) {
    function get_userdata($id) {
        return (object) array(
            'ID' => $id,
            'display_name' => 'Test User ' . $id,
            'user_login' => 'testuser' . $id
        );
    }
}

if (!function_exists('get_post')) {
    function get_post($id) {
        if (empty($id)) {
            return null;
        }
        return (object) array(
            'ID' => $id,
            'post_title' => 'Test Post ' . $id,
            'post_type' => 'course'
        );
    }
}

if (!function_exists('wp_get_post_parent_id')) {
    function wp_get_post_parent_id($id) {
        return 0;
    }
}

if (!function_exists('date_i18n')) {
    function date_i18n($format, $timestamp = null) {
        return date($format, $timestamp ?: time());
    }
}

if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        return $default;
    }
}

class LiveActivityTest extends PHPUnit_Framework_TestCase {
    
    /**
     * Test instance creation
     */
    public function test_instance_creation() {
        $live_activity = new TutorAdvancedTracking_LiveActivity();
        $this->assertTrue($live_activity instanceof TutorAdvancedTracking_LiveActivity);
    }
    
    /**
     * Test adding and retrieving activities
     */
    public function test_add_and_get_activities() {
        $live_activity = new TutorAdvancedTracking_LiveActivity();
        
        // Clear any existing activities
        $live_activity->clear_activities();
        
        // Add test activities
        $activity1 = $live_activity->log_test_activity(
            1, // user_id
            'view_lesson', // action
            10, // course_id
            20 // lesson_id
        );
        
        $activity2 = $live_activity->log_test_activity(
            2,
            'complete_quiz',
            11,
            0
        );
        
        // Get activities
        $activities = $live_activity->get_activities(10);
        
        // Verify
        $this->assertCount(2, $activities);
        $this->assertEquals('view_lesson', $activities[0]['action']);
        $this->assertEquals('complete_quiz', $activities[1]['action']);
        
        // Clean up
        $live_activity->clear_activities();
    }
    
    /**
     * Test activity count limit
     */
    public function test_activity_limit() {
        $live_activity = new TutorAdvancedTracking_LiveActivity();
        
        // Clear any existing activities
        $live_activity->clear_activities();
        
        // Add more activities than the limit (100)
        for ($i = 0; $i < 105; $i++) {
            $live_activity->log_test_activity(
                $i,
                'view_lesson',
                10 + $i,
                0
            );
        }
        
        // Get activities - should be limited to 100
        $activities = $live_activity->get_activities(200);
        $this->assertLessThanOrEqual(100, count($activities));
        
        // Clean up
        $live_activity->clear_activities();
    }
    
    /**
     * Test clearing activities
     */
    public function test_clear_activities() {
        $live_activity = new TutorAdvancedTracking_LiveActivity();
        
        // Add some activities
        $live_activity->log_test_activity(1, 'view_lesson', 10, 20);
        $live_activity->log_test_activity(2, 'complete_quiz', 11, 0);
        
        // Verify they exist
        $activities = $live_activity->get_activities(10);
        $this->assertCount(2, $activities);
        
        // Clear
        $live_activity->clear_activities();
        
        // Verify they're gone
        $activities = $live_activity->get_activities(10);
        $this->assertCount(0, $activities);
    }
    
    /**
     * Test activity structure
     */
    public function test_activity_structure() {
        $live_activity = new TutorAdvancedTracking_LiveActivity();
        $live_activity->clear_activities();
        
        $activity = $live_activity->log_test_activity(1, 'view_lesson', 10, 20);
        
        // Verify required fields
        $this->assertArrayHasKey('id', $activity);
        $this->assertArrayHasKey('student_name', $activity);
        $this->assertArrayHasKey('action', $activity);
        $this->assertArrayHasKey('action_label', $activity);
        $this->assertArrayHasKey('action_icon', $activity);
        $this->assertArrayHasKey('course_id', $activity);
        $this->assertArrayHasKey('course_name', $activity);
        $this->assertArrayHasKey('lesson_id', $activity);
        $this->assertArrayHasKey('timestamp', $activity);
        
        // Clean up
        $live_activity->clear_activities();
    }
    
    /**
     * Test action labels
     */
    public function test_action_labels() {
        $live_activity = new TutorAdvancedTracking_LiveActivity();
        $live_activity->clear_activities();
        
        $actions = array(
            'view_lesson' => 'Viewed Lesson',
            'complete_lesson' => 'Completed Lesson',
            'complete_quiz' => 'Completed Quiz',
            'submit_assignment' => 'Submitted Assignment',
            'enroll_course' => 'Enrolled in Course',
            'complete_course' => 'Completed Course',
        );
        
        foreach ($actions as $action => $expected_label) {
            $activity = $live_activity->log_test_activity(1, $action, 10, 0);
            $this->assertEquals($expected_label, $activity['action_label']);
        }
        
        $live_activity->clear_activities();
    }
    
    /**
     * Test AJAX get activities endpoint
     */
    public function test_ajax_get_activities() {
        // Set up current user
        $this->factory->user->create(array('role' => 'administrator'));
        wp_set_current_user(1);
        
        $live_activity = new TutorAdvancedTracking_LiveActivity();
        $live_activity->clear_activities();
        
        // Add test activity
        $live_activity->log_test_activity(1, 'view_lesson', 10, 20);
        
        // Simulate AJAX request
        $_POST['nonce'] = wp_create_nonce('tlat_live_activity_nonce');
        $_POST['action'] = 'tlat_get_live_activities';
        $_POST['limit'] = 50;
        
        // Capture output
        ob_start();
        $live_activity->ajax_get_activities();
        $output = ob_get_clean();
        
        // Verify response is JSON
        $response = json_decode($output, true);
        $this->assertTrue($response['success']);
        $this->assertCount(1, $response['data']['activities']);
        
        // Clean up
        $live_activity->clear_activities();
    }
    
    /**
     * Test AJAX clear activities endpoint
     */
    public function test_ajax_clear_activities() {
        // Set up current user
        $this->factory->user->create(array('role' => 'administrator'));
        wp_set_current_user(1);
        
        $live_activity = new TutorAdvancedTracking_LiveActivity();
        $live_activity->clear_activities();
        
        // Add test activity
        $live_activity->log_test_activity(1, 'view_lesson', 10, 20);
        
        // Simulate AJAX request
        $_POST['nonce'] = wp_create_nonce('tlat_live_activity_nonce');
        $_POST['action'] = 'tlat_clear_activities';
        
        // Capture output
        ob_start();
        $live_activity->ajax_clear_activities();
        $output = ob_get_clean();
        
        // Verify response is JSON
        $response = json_decode($output, true);
        $this->assertTrue($response['success']);
        
        // Verify activities are cleared
        $activities = $live_activity->get_activities(10);
        $this->assertCount(0, $activities);
    }
    
    /**
     * Test nonce validation
     */
    public function test_nonce_validation() {
        $live_activity = new TutorAdvancedTracking_LiveActivity();
        
        // Set up current user
        $this->factory->user->create(array('role' => 'administrator'));
        wp_set_current_user(1);
        
        // Invalid nonce
        $_POST['nonce'] = 'invalid_nonce';
        $_POST['action'] = 'tlat_get_live_activities';
        
        ob_start();
        $live_activity->ajax_get_activities();
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
    }
}
