<?php
/**
 * Webhooks System for Zapier/Make Integration
 * 
 * Sends webhook notifications on key Tutor LMS events.
 * 
 * @package TutorLMS_Advanced_Tracking
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class TLAT_Webhooks {
    
    /** @var string Option key for webhooks */
    const OPTION_KEY = 'tlat_webhooks';
    
    /** @var string Option key for webhook logs */
    const LOG_KEY = 'tlat_webhook_logs';
    
    /** @var int Max log entries to keep */
    const MAX_LOGS = 100;
    
    /** @var array Available events */
    private static $available_events = array(
        'course_enrolled' => 'Student Enrolled in Course',
        'course_completed' => 'Student Completed Course',
        'lesson_completed' => 'Lesson Completed',
        'quiz_passed' => 'Quiz Passed',
        'quiz_failed' => 'Quiz Failed',
        'certificate_generated' => 'Certificate Generated',
        'instructor_registered' => 'New Instructor Registered',
    );
    
    /**
     * Initialize webhooks system
     */
    public static function init() {
        // Register admin page
        add_action('admin_menu', array(__CLASS__, 'add_admin_menu'));
        
        // Register AJAX handlers
        add_action('wp_ajax_tlat_save_webhook', array(__CLASS__, 'ajax_save_webhook'));
        add_action('wp_ajax_tlat_delete_webhook', array(__CLASS__, 'ajax_delete_webhook'));
        add_action('wp_ajax_tlat_test_webhook', array(__CLASS__, 'ajax_test_webhook'));
        add_action('wp_ajax_tlat_get_webhook_logs', array(__CLASS__, 'ajax_get_logs'));
        
        // Hook into Tutor LMS events
        self::register_event_hooks();
    }
    
    /**
     * Register event hooks
     */
    private static function register_event_hooks() {
        // Course enrollment
        add_action('tutor_after_enrolled', array(__CLASS__, 'on_course_enrolled'), 10, 3);
        
        // Course completion
        add_action('tutor_course_complete_after', array(__CLASS__, 'on_course_completed'), 10, 2);
        
        // Lesson completion
        add_action('tutor_lesson_completed_after', array(__CLASS__, 'on_lesson_completed'), 10, 2);
        
        // Quiz completion
        add_action('tutor_quiz/attempt_ended', array(__CLASS__, 'on_quiz_ended'), 10, 2);
        
        // Certificate generated
        add_action('tutor_certificate_generated', array(__CLASS__, 'on_certificate_generated'), 10, 3);
        
        // Instructor registration
        add_action('tutor_after_instructor_approved', array(__CLASS__, 'on_instructor_registered'), 10, 1);
    }
    
    /**
     * Add admin menu
     */
    public static function add_admin_menu() {
        add_submenu_page(
            'tutor-advanced-stats',
            __('Webhooks', 'tutor-lms-advanced-tracking'),
            __('Webhooks', 'tutor-lms-advanced-tracking'),
            'manage_options',
            'tutor-advanced-webhooks',
            array(__CLASS__, 'render_admin_page')
        );
    }
    
    /**
     * Get available events
     */
    public static function get_available_events() {
        return apply_filters('tlat_webhook_events', self::$available_events);
    }
    
    /**
     * Get all webhooks
     */
    public static function get_webhooks() {
        $webhooks = get_option(self::OPTION_KEY, array());
        return is_array($webhooks) ? $webhooks : array();
    }
    
    /**
     * Save webhook
     */
    public static function save_webhook($webhook) {
        $webhooks = self::get_webhooks();
        
        // Generate ID if new
        if (empty($webhook['id'])) {
            $webhook['id'] = wp_generate_uuid4();
            $webhook['created'] = current_time('mysql');
        }
        
        $webhook['updated'] = current_time('mysql');
        
        // Validate
        if (empty($webhook['url']) || !filter_var($webhook['url'], FILTER_VALIDATE_URL)) {
            return new WP_Error('invalid_url', __('Invalid webhook URL', 'tutor-lms-advanced-tracking'));
        }
        
        if (empty($webhook['events']) || !is_array($webhook['events'])) {
            return new WP_Error('no_events', __('At least one event must be selected', 'tutor-lms-advanced-tracking'));
        }
        
        // Generate secret if not set
        if (empty($webhook['secret'])) {
            $webhook['secret'] = wp_generate_password(32, false);
        }
        
        // Update or add
        $found = false;
        foreach ($webhooks as $i => $w) {
            if ($w['id'] === $webhook['id']) {
                $webhooks[$i] = $webhook;
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            $webhooks[] = $webhook;
        }
        
        update_option(self::OPTION_KEY, $webhooks);
        
        return $webhook;
    }
    
    /**
     * Delete webhook
     */
    public static function delete_webhook($id) {
        $webhooks = self::get_webhooks();
        
        foreach ($webhooks as $i => $webhook) {
            if ($webhook['id'] === $id) {
                unset($webhooks[$i]);
                update_option(self::OPTION_KEY, array_values($webhooks));
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Trigger webhooks for an event
     */
    public static function trigger($event, $data) {
        $webhooks = self::get_webhooks();
        
        foreach ($webhooks as $webhook) {
            // Check if webhook is enabled and subscribed to event
            if (empty($webhook['enabled']) || !in_array($event, $webhook['events'])) {
                continue;
            }
            
            // Send webhook
            self::send($webhook, $event, $data);
        }
    }
    
    /**
     * Send webhook request
     */
    private static function send($webhook, $event, $data) {
        $payload = array(
            'event' => $event,
            'event_label' => self::$available_events[$event] ?? $event,
            'timestamp' => current_time('c'),
            'site_url' => home_url(),
            'data' => $data,
        );
        
        $json = wp_json_encode($payload);
        
        // Generate signature
        $signature = hash_hmac('sha256', $json, $webhook['secret']);
        
        $response = wp_remote_post($webhook['url'], array(
            'timeout' => 15,
            'body' => $json,
            'headers' => array(
                'Content-Type' => 'application/json',
                'X-TLAT-Event' => $event,
                'X-TLAT-Signature' => $signature,
                'X-TLAT-Timestamp' => time(),
            ),
        ));
        
        // Log result
        self::log($webhook['id'], $event, $response);
        
        return $response;
    }
    
    /**
     * Log webhook delivery
     */
    private static function log($webhook_id, $event, $response) {
        $logs = get_option(self::LOG_KEY, array());
        
        $log = array(
            'webhook_id' => $webhook_id,
            'event' => $event,
            'timestamp' => current_time('mysql'),
            'success' => !is_wp_error($response) && wp_remote_retrieve_response_code($response) >= 200 && wp_remote_retrieve_response_code($response) < 300,
        );
        
        if (is_wp_error($response)) {
            $log['error'] = $response->get_error_message();
        } else {
            $log['status_code'] = wp_remote_retrieve_response_code($response);
        }
        
        array_unshift($logs, $log);
        
        // Keep only recent logs
        $logs = array_slice($logs, 0, self::MAX_LOGS);
        
        update_option(self::LOG_KEY, $logs);
    }
    
    /**
     * Get logs for a webhook
     */
    public static function get_logs($webhook_id = null, $limit = 20) {
        $logs = get_option(self::LOG_KEY, array());
        
        if ($webhook_id) {
            $logs = array_filter($logs, function($log) use ($webhook_id) {
                return $log['webhook_id'] === $webhook_id;
            });
        }
        
        return array_slice($logs, 0, $limit);
    }
    
    // =========================================================================
    // Event Handlers
    // =========================================================================
    
    /**
     * Handle course enrollment
     */
    public static function on_course_enrolled($course_id, $user_id, $enrollment_id = null) {
        $user = get_userdata($user_id);
        $course = get_post($course_id);
        
        if (!$user || !$course) return;
        
        self::trigger('course_enrolled', array(
            'user_id' => $user_id,
            'user_email' => $user->user_email,
            'user_name' => $user->display_name,
            'course_id' => $course_id,
            'course_title' => $course->post_title,
            'enrollment_id' => $enrollment_id,
            'enrolled_at' => current_time('c'),
        ));
    }
    
    /**
     * Handle course completion
     */
    public static function on_course_completed($course_id, $user_id) {
        $user = get_userdata($user_id);
        $course = get_post($course_id);
        
        if (!$user || !$course) return;
        
        // Get completion percentage
        $completion = 100;
        if (function_exists('tutor_utils')) {
            $completion = tutor_utils()->get_course_completed_percent($course_id, $user_id);
        }
        
        self::trigger('course_completed', array(
            'user_id' => $user_id,
            'user_email' => $user->user_email,
            'user_name' => $user->display_name,
            'course_id' => $course_id,
            'course_title' => $course->post_title,
            'completion_percentage' => $completion,
            'completed_at' => current_time('c'),
        ));
    }
    
    /**
     * Handle lesson completion
     */
    public static function on_lesson_completed($lesson_id, $user_id) {
        $user = get_userdata($user_id);
        $lesson = get_post($lesson_id);
        
        if (!$user || !$lesson) return;
        
        // Get course
        $course_id = wp_get_post_parent_id($lesson_id);
        $course = get_post($course_id);
        
        self::trigger('lesson_completed', array(
            'user_id' => $user_id,
            'user_email' => $user->user_email,
            'user_name' => $user->display_name,
            'lesson_id' => $lesson_id,
            'lesson_title' => $lesson->post_title,
            'course_id' => $course_id,
            'course_title' => $course ? $course->post_title : '',
            'completed_at' => current_time('c'),
        ));
    }
    
    /**
     * Handle quiz ended
     */
    public static function on_quiz_ended($attempt_id, $quiz_id) {
        global $wpdb;
        
        // Get attempt data
        $attempt = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}tutor_quiz_attempts WHERE attempt_id = %d",
            $attempt_id
        ));
        
        if (!$attempt) return;
        
        $user = get_userdata($attempt->user_id);
        $quiz = get_post($quiz_id);
        
        if (!$user || !$quiz) return;
        
        // Determine if passed or failed
        $pass_mark = get_post_meta($quiz_id, 'tutor_quiz_option_passing_grade', true);
        $pass_mark = $pass_mark ? floatval($pass_mark) : 80;
        $earned_percentage = floatval($attempt->earned_marks / max(1, $attempt->total_marks) * 100);
        $passed = $earned_percentage >= $pass_mark;
        
        $event = $passed ? 'quiz_passed' : 'quiz_failed';
        
        self::trigger($event, array(
            'user_id' => $attempt->user_id,
            'user_email' => $user->user_email,
            'user_name' => $user->display_name,
            'quiz_id' => $quiz_id,
            'quiz_title' => $quiz->post_title,
            'attempt_id' => $attempt_id,
            'total_marks' => $attempt->total_marks,
            'earned_marks' => $attempt->earned_marks,
            'percentage' => round($earned_percentage, 2),
            'pass_mark' => $pass_mark,
            'passed' => $passed,
            'attempt_ended_at' => $attempt->attempt_ended_at,
        ));
    }
    
    /**
     * Handle certificate generated
     */
    public static function on_certificate_generated($certificate_id, $course_id, $user_id) {
        $user = get_userdata($user_id);
        $course = get_post($course_id);
        
        if (!$user || !$course) return;
        
        self::trigger('certificate_generated', array(
            'user_id' => $user_id,
            'user_email' => $user->user_email,
            'user_name' => $user->display_name,
            'course_id' => $course_id,
            'course_title' => $course->post_title,
            'certificate_id' => $certificate_id,
            'generated_at' => current_time('c'),
        ));
    }
    
    /**
     * Handle instructor registered
     */
    public static function on_instructor_registered($user_id) {
        $user = get_userdata($user_id);
        
        if (!$user) return;
        
        self::trigger('instructor_registered', array(
            'user_id' => $user_id,
            'user_email' => $user->user_email,
            'user_name' => $user->display_name,
            'registered_at' => current_time('c'),
        ));
    }
    
    // =========================================================================
    // AJAX Handlers
    // =========================================================================
    
    /**
     * AJAX: Save webhook
     */
    public static function ajax_save_webhook() {
        check_ajax_referer('tlat_webhooks_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'tutor-lms-advanced-tracking'));
        }
        
        $webhook = array(
            'id' => sanitize_text_field($_POST['id'] ?? ''),
            'name' => sanitize_text_field($_POST['name'] ?? ''),
            'url' => esc_url_raw($_POST['url'] ?? ''),
            'events' => array_map('sanitize_text_field', $_POST['events'] ?? array()),
            'enabled' => !empty($_POST['enabled']),
            'secret' => sanitize_text_field($_POST['secret'] ?? ''),
        );
        
        $result = self::save_webhook($webhook);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * AJAX: Delete webhook
     */
    public static function ajax_delete_webhook() {
        check_ajax_referer('tlat_webhooks_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'tutor-lms-advanced-tracking'));
        }
        
        $id = sanitize_text_field($_POST['id'] ?? '');
        
        if (self::delete_webhook($id)) {
            wp_send_json_success();
        }
        
        wp_send_json_error(__('Webhook not found', 'tutor-lms-advanced-tracking'));
    }
    
    /**
     * AJAX: Test webhook
     */
    public static function ajax_test_webhook() {
        check_ajax_referer('tlat_webhooks_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'tutor-lms-advanced-tracking'));
        }
        
        $webhooks = self::get_webhooks();
        $id = sanitize_text_field($_POST['id'] ?? '');
        
        $webhook = null;
        foreach ($webhooks as $w) {
            if ($w['id'] === $id) {
                $webhook = $w;
                break;
            }
        }
        
        if (!$webhook) {
            wp_send_json_error(__('Webhook not found', 'tutor-lms-advanced-tracking'));
        }
        
        // Send test event
        $test_data = array(
            'test' => true,
            'message' => 'This is a test webhook from TLAT',
            'timestamp' => current_time('c'),
        );
        
        $response = self::send($webhook, 'test', $test_data);
        
        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        }
        
        $code = wp_remote_retrieve_response_code($response);
        
        if ($code >= 200 && $code < 300) {
            wp_send_json_success(array(
                'status_code' => $code,
                'message' => __('Webhook delivered successfully!', 'tutor-lms-advanced-tracking'),
            ));
        }
        
        wp_send_json_error(sprintf(
            __('Webhook failed with status code: %d', 'tutor-lms-advanced-tracking'),
            $code
        ));
    }
    
    /**
     * AJAX: Get logs
     */
    public static function ajax_get_logs() {
        check_ajax_referer('tlat_webhooks_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'tutor-lms-advanced-tracking'));
        }
        
        $webhook_id = sanitize_text_field($_POST['webhook_id'] ?? '');
        $logs = self::get_logs($webhook_id ?: null);
        
        wp_send_json_success($logs);
    }
    
    // =========================================================================
    // Admin Page
    // =========================================================================
    
    /**
     * Render admin page
     */
    public static function render_admin_page() {
        $webhooks = self::get_webhooks();
        $events = self::get_available_events();
        ?>
        <div class="wrap tlat-webhooks-page">
            <h1><?php _e('Webhooks', 'tutor-lms-advanced-tracking'); ?></h1>
            <p class="description">
                <?php _e('Send real-time notifications to Zapier, Make, or any webhook endpoint when events occur in your courses.', 'tutor-lms-advanced-tracking'); ?>
            </p>
            
            <style>
                .tlat-webhooks-page { max-width: 1200px; }
                .tlat-webhook-card { 
                    background: #fff; 
                    border: 1px solid #ccd0d4; 
                    border-radius: 4px;
                    padding: 20px; 
                    margin-bottom: 15px; 
                }
                .tlat-webhook-card.editing { border-color: #2271b1; }
                .tlat-webhook-header { 
                    display: flex; 
                    justify-content: space-between; 
                    align-items: center;
                    margin-bottom: 15px;
                }
                .tlat-webhook-name { font-size: 16px; font-weight: 600; }
                .tlat-webhook-status { 
                    padding: 3px 8px; 
                    border-radius: 3px; 
                    font-size: 12px;
                }
                .tlat-webhook-status.enabled { background: #d4edda; color: #155724; }
                .tlat-webhook-status.disabled { background: #f8d7da; color: #721c24; }
                .tlat-webhook-url { 
                    font-family: monospace; 
                    font-size: 13px; 
                    color: #666;
                    word-break: break-all;
                }
                .tlat-webhook-events { margin: 10px 0; }
                .tlat-webhook-events span {
                    display: inline-block;
                    background: #e9ecef;
                    padding: 2px 8px;
                    border-radius: 3px;
                    margin: 2px 4px 2px 0;
                    font-size: 12px;
                }
                .tlat-webhook-actions { margin-top: 15px; }
                .tlat-webhook-actions button { margin-right: 8px; }
                .tlat-webhook-form { display: none; margin-top: 15px; }
                .tlat-webhook-form.active { display: block; }
                .tlat-webhook-form table { width: 100%; }
                .tlat-webhook-form th { text-align: left; padding: 8px 0; width: 150px; }
                .tlat-webhook-form td { padding: 8px 0; }
                .tlat-webhook-form input[type="text"],
                .tlat-webhook-form input[type="url"] { width: 100%; max-width: 500px; }
                .tlat-events-checkboxes { max-height: 200px; overflow-y: auto; }
                .tlat-events-checkboxes label { display: block; margin: 5px 0; }
                .tlat-add-webhook { margin: 20px 0; }
                .tlat-logs-section { margin-top: 30px; }
                .tlat-logs-table { width: 100%; border-collapse: collapse; }
                .tlat-logs-table th, .tlat-logs-table td { 
                    padding: 10px; 
                    text-align: left; 
                    border-bottom: 1px solid #ddd;
                }
                .tlat-log-success { color: #155724; }
                .tlat-log-error { color: #721c24; }
                .tlat-secret-field { 
                    font-family: monospace; 
                    background: #f5f5f5;
                    padding: 5px;
                    border-radius: 3px;
                }
                .tlat-copy-btn {
                    cursor: pointer;
                    padding: 2px 8px;
                    font-size: 12px;
                }
            </style>
            
            <div class="tlat-add-webhook">
                <button type="button" class="button button-primary" id="tlat-add-webhook-btn">
                    <span class="dashicons dashicons-plus-alt" style="vertical-align: middle;"></span>
                    <?php _e('Add Webhook', 'tutor-lms-advanced-tracking'); ?>
                </button>
            </div>
            
            <div id="tlat-webhooks-list">
                <?php foreach ($webhooks as $webhook): ?>
                    <?php self::render_webhook_card($webhook, $events); ?>
                <?php endforeach; ?>
            </div>
            
            <!-- Template for new webhook -->
            <template id="tlat-webhook-template">
                <?php self::render_webhook_card(array(
                    'id' => '',
                    'name' => '',
                    'url' => '',
                    'events' => array(),
                    'enabled' => true,
                    'secret' => '',
                ), $events, true); ?>
            </template>
            
            <!-- Logs Section -->
            <div class="tlat-logs-section">
                <h2><?php _e('Delivery Logs', 'tutor-lms-advanced-tracking'); ?></h2>
                <table class="tlat-logs-table widefat">
                    <thead>
                        <tr>
                            <th><?php _e('Time', 'tutor-lms-advanced-tracking'); ?></th>
                            <th><?php _e('Event', 'tutor-lms-advanced-tracking'); ?></th>
                            <th><?php _e('Status', 'tutor-lms-advanced-tracking'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="tlat-logs-body">
                        <?php 
                        $logs = self::get_logs();
                        foreach ($logs as $log): 
                        ?>
                            <tr>
                                <td><?php echo esc_html($log['timestamp']); ?></td>
                                <td><?php echo esc_html($log['event']); ?></td>
                                <td class="<?php echo $log['success'] ? 'tlat-log-success' : 'tlat-log-error'; ?>">
                                    <?php 
                                    if ($log['success']) {
                                        echo '✓ ' . esc_html($log['status_code'] ?? '200');
                                    } else {
                                        echo '✗ ' . esc_html($log['error'] ?? $log['status_code'] ?? 'Failed');
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($logs)): ?>
                            <tr><td colspan="3"><?php _e('No webhook deliveries yet.', 'tutor-lms-advanced-tracking'); ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <script>
            jQuery(document).ready(function($) {
                var nonce = '<?php echo wp_create_nonce('tlat_webhooks_nonce'); ?>';
                
                // Add webhook
                $('#tlat-add-webhook-btn').on('click', function() {
                    var template = $('#tlat-webhook-template').html();
                    var $card = $(template);
                    $card.addClass('editing');
                    $card.find('.tlat-webhook-form').addClass('active');
                    $('#tlat-webhooks-list').prepend($card);
                    $card.find('input[name="name"]').focus();
                });
                
                // Edit webhook
                $(document).on('click', '.tlat-edit-btn', function() {
                    var $card = $(this).closest('.tlat-webhook-card');
                    $card.addClass('editing');
                    $card.find('.tlat-webhook-form').addClass('active');
                });
                
                // Cancel edit
                $(document).on('click', '.tlat-cancel-btn', function() {
                    var $card = $(this).closest('.tlat-webhook-card');
                    if (!$card.data('id')) {
                        $card.remove();
                    } else {
                        $card.removeClass('editing');
                        $card.find('.tlat-webhook-form').removeClass('active');
                    }
                });
                
                // Save webhook
                $(document).on('click', '.tlat-save-btn', function() {
                    var $btn = $(this);
                    var $card = $btn.closest('.tlat-webhook-card');
                    var $form = $card.find('.tlat-webhook-form');
                    
                    var events = [];
                    $form.find('input[name="events[]"]:checked').each(function() {
                        events.push($(this).val());
                    });
                    
                    var data = {
                        action: 'tlat_save_webhook',
                        nonce: nonce,
                        id: $card.data('id') || '',
                        name: $form.find('input[name="name"]').val(),
                        url: $form.find('input[name="url"]').val(),
                        events: events,
                        enabled: $form.find('input[name="enabled"]').is(':checked') ? 1 : 0,
                        secret: $form.find('input[name="secret"]').val()
                    };
                    
                    $btn.prop('disabled', true).text('<?php _e('Saving...', 'tutor-lms-advanced-tracking'); ?>');
                    
                    $.post(ajaxurl, data, function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert(response.data || 'Error saving webhook');
                            $btn.prop('disabled', false).text('<?php _e('Save', 'tutor-lms-advanced-tracking'); ?>');
                        }
                    });
                });
                
                // Delete webhook
                $(document).on('click', '.tlat-delete-btn', function() {
                    if (!confirm('<?php _e('Are you sure you want to delete this webhook?', 'tutor-lms-advanced-tracking'); ?>')) {
                        return;
                    }
                    
                    var $card = $(this).closest('.tlat-webhook-card');
                    var id = $card.data('id');
                    
                    if (!id) {
                        $card.remove();
                        return;
                    }
                    
                    $.post(ajaxurl, {
                        action: 'tlat_delete_webhook',
                        nonce: nonce,
                        id: id
                    }, function(response) {
                        if (response.success) {
                            $card.fadeOut(function() { $(this).remove(); });
                        } else {
                            alert(response.data || 'Error deleting webhook');
                        }
                    });
                });
                
                // Test webhook
                $(document).on('click', '.tlat-test-btn', function() {
                    var $btn = $(this);
                    var $card = $btn.closest('.tlat-webhook-card');
                    var id = $card.data('id');
                    
                    if (!id) {
                        alert('<?php _e('Please save the webhook first', 'tutor-lms-advanced-tracking'); ?>');
                        return;
                    }
                    
                    $btn.prop('disabled', true).text('<?php _e('Testing...', 'tutor-lms-advanced-tracking'); ?>');
                    
                    $.post(ajaxurl, {
                        action: 'tlat_test_webhook',
                        nonce: nonce,
                        id: id
                    }, function(response) {
                        if (response.success) {
                            alert(response.data.message);
                        } else {
                            alert('Error: ' + (response.data || 'Unknown error'));
                        }
                        $btn.prop('disabled', false).text('<?php _e('Test', 'tutor-lms-advanced-tracking'); ?>');
                    });
                });
                
                // Copy secret
                $(document).on('click', '.tlat-copy-secret', function() {
                    var secret = $(this).siblings('.tlat-secret-value').text();
                    navigator.clipboard.writeText(secret).then(function() {
                        alert('<?php _e('Secret copied to clipboard', 'tutor-lms-advanced-tracking'); ?>');
                    });
                });
            });
            </script>
        </div>
        <?php
    }
    
    /**
     * Render individual webhook card
     */
    private static function render_webhook_card($webhook, $events, $is_new = false) {
        $id = $webhook['id'] ?? '';
        $name = $webhook['name'] ?? __('New Webhook', 'tutor-lms-advanced-tracking');
        $url = $webhook['url'] ?? '';
        $enabled = $webhook['enabled'] ?? true;
        $selected_events = $webhook['events'] ?? array();
        $secret = $webhook['secret'] ?? '';
        ?>
        <div class="tlat-webhook-card" data-id="<?php echo esc_attr($id); ?>">
            <div class="tlat-webhook-header">
                <div>
                    <span class="tlat-webhook-name"><?php echo esc_html($name ?: __('New Webhook', 'tutor-lms-advanced-tracking')); ?></span>
                    <span class="tlat-webhook-status <?php echo $enabled ? 'enabled' : 'disabled'; ?>">
                        <?php echo $enabled ? __('Enabled', 'tutor-lms-advanced-tracking') : __('Disabled', 'tutor-lms-advanced-tracking'); ?>
                    </span>
                </div>
            </div>
            
            <?php if ($url): ?>
            <div class="tlat-webhook-url"><?php echo esc_html($url); ?></div>
            <?php endif; ?>
            
            <?php if (!empty($selected_events)): ?>
            <div class="tlat-webhook-events">
                <?php foreach ($selected_events as $event): ?>
                    <span><?php echo esc_html($events[$event] ?? $event); ?></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <div class="tlat-webhook-actions">
                <button type="button" class="button tlat-edit-btn"><?php _e('Edit', 'tutor-lms-advanced-tracking'); ?></button>
                <button type="button" class="button tlat-test-btn"><?php _e('Test', 'tutor-lms-advanced-tracking'); ?></button>
                <button type="button" class="button tlat-delete-btn" style="color: #a00;"><?php _e('Delete', 'tutor-lms-advanced-tracking'); ?></button>
            </div>
            
            <!-- Edit Form -->
            <div class="tlat-webhook-form <?php echo $is_new ? 'active' : ''; ?>">
                <table>
                    <tr>
                        <th><label for="webhook-name-<?php echo esc_attr($id); ?>"><?php _e('Name', 'tutor-lms-advanced-tracking'); ?></label></th>
                        <td>
                            <input type="text" name="name" id="webhook-name-<?php echo esc_attr($id); ?>" 
                                value="<?php echo esc_attr($name); ?>" 
                                placeholder="<?php _e('My Webhook', 'tutor-lms-advanced-tracking'); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="webhook-url-<?php echo esc_attr($id); ?>"><?php _e('URL', 'tutor-lms-advanced-tracking'); ?></label></th>
                        <td>
                            <input type="url" name="url" id="webhook-url-<?php echo esc_attr($id); ?>" 
                                value="<?php echo esc_attr($url); ?>" 
                                placeholder="https://hooks.zapier.com/..." required>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Events', 'tutor-lms-advanced-tracking'); ?></th>
                        <td>
                            <div class="tlat-events-checkboxes">
                                <?php foreach ($events as $event_key => $event_label): ?>
                                    <label>
                                        <input type="checkbox" name="events[]" value="<?php echo esc_attr($event_key); ?>"
                                            <?php checked(in_array($event_key, $selected_events)); ?>>
                                        <?php echo esc_html($event_label); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Enabled', 'tutor-lms-advanced-tracking'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="enabled" value="1" <?php checked($enabled); ?>>
                                <?php _e('Deliver webhook notifications', 'tutor-lms-advanced-tracking'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Secret', 'tutor-lms-advanced-tracking'); ?></th>
                        <td>
                            <?php if ($secret): ?>
                                <span class="tlat-secret-field">
                                    <code class="tlat-secret-value"><?php echo esc_html($secret); ?></code>
                                    <button type="button" class="button tlat-copy-secret tlat-copy-btn"><?php _e('Copy', 'tutor-lms-advanced-tracking'); ?></button>
                                </span>
                                <input type="hidden" name="secret" value="<?php echo esc_attr($secret); ?>">
                            <?php else: ?>
                                <input type="text" name="secret" value="" placeholder="<?php _e('Auto-generated on save', 'tutor-lms-advanced-tracking'); ?>">
                            <?php endif; ?>
                            <p class="description"><?php _e('Used to verify webhook authenticity (HMAC-SHA256)', 'tutor-lms-advanced-tracking'); ?></p>
                        </td>
                    </tr>
                </table>
                <p>
                    <button type="button" class="button button-primary tlat-save-btn"><?php _e('Save', 'tutor-lms-advanced-tracking'); ?></button>
                    <button type="button" class="button tlat-cancel-btn"><?php _e('Cancel', 'tutor-lms-advanced-tracking'); ?></button>
                </p>
            </div>
        </div>
        <?php
    }
}
