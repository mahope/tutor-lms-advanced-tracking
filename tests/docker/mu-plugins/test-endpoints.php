<?php
/**
 * TLAT Test Endpoints
 * Must-use plugin for compatibility testing
 */

add_action('rest_api_init', function() {
    register_rest_route('tlat-test/v1', '/status', [
        'methods'  => 'GET',
        'callback' => function() {
            // Ensure plugin functions available
            if (!function_exists('is_plugin_active')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            
            $tutor_free_active = is_plugin_active('tutor/tutor.php');
            $tutor_pro_active = is_plugin_active('tutor-pro/tutor-pro.php');
            
            return [
                'wordpress_version' => get_bloginfo('version'),
                'php_version'       => PHP_VERSION,
                'mysql_version'     => $GLOBALS['wpdb']->db_version(),
                'tlat_active'       => is_plugin_active('tutor-lms-advanced-tracking/tutor-lms-advanced-tracking.php'),
                'tlat_version'      => defined('TLAT_VERSION') ? TLAT_VERSION : null,
                'tutor_free_active' => $tutor_free_active,
                'tutor_pro_active'  => $tutor_pro_active,
                'tutor_version'     => defined('TUTOR_VERSION') ? TUTOR_VERSION : null,
                'tutor_classes'     => [
                    'Tutor'             => class_exists('TUTOR\Tutor'),
                    'TutorAdvancedTracking' => class_exists('TutorAdvancedTracking'),
                    'TLAT_Cache'        => class_exists('TutorAdvancedTracking_Cache'),
                ],
                'timestamp'         => current_time('c'),
            ];
        },
        'permission_callback' => '__return_true',
    ]);
});
