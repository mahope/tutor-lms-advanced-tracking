<?php
/**
 * TLAT Test Endpoints
 * Must-use plugin for compatibility testing
 */

add_action('rest_api_init', function() {
    register_rest_route('tlat-test/v1', '/status', [
        'methods'  => 'GET',
        'callback' => function() {
            return [
                'wordpress_version' => get_bloginfo('version'),
                'php_version'       => PHP_VERSION,
                'tlat_active'       => is_plugin_active('tutor-lms-advanced-tracking/tutor-lms-advanced-tracking.php'),
                'tutor_active'      => is_plugin_active('tutor/tutor.php') || is_plugin_active('tutor-pro/tutor-pro.php'),
                'timestamp'         => current_time('c'),
            ];
        },
        'permission_callback' => '__return_true',
    ]);
});
