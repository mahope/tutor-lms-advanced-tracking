<?php
/**
 * TLAT Test Setup - MU Plugin
 * 
 * Auto-activates TLAT plugin and creates test data for compatibility testing.
 * This file is loaded automatically by WordPress.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Auto-activate our plugin after WordPress is loaded
 */
add_action('plugins_loaded', function() {
    // Ensure plugin functions are available
    if (!function_exists('is_plugin_active')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    
    // Check if our plugin exists but isn't active
    $plugin = 'tutor-lms-advanced-tracking/tutor-lms-advanced-tracking.php';
    
    if (!is_plugin_active($plugin) && file_exists(WP_PLUGIN_DIR . '/' . $plugin)) {
        activate_plugin($plugin);
    }
}, 1);

/**
 * Add admin notice about test environment
 */
add_action('admin_notices', function() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    $wp_version = get_bloginfo('version');
    $php_version = phpversion();
    $tutor_active = class_exists('TUTOR\Tutor') || defined('TUTOR_VERSION');
    
    echo '<div class="notice notice-info">';
    echo '<p><strong>TLAT Test Environment</strong></p>';
    echo '<p>WordPress: ' . esc_html($wp_version) . ' | PHP: ' . esc_html($php_version) . '</p>';
    echo '<p>Tutor LMS: ' . ($tutor_active ? '<span style="color:green">✓ Active</span>' : '<span style="color:red">✗ Not Active</span>') . '</p>';
    echo '</div>';
});

/**
 * Create test data endpoint for automation
 */
add_action('rest_api_init', function() {
    register_rest_route('tlat-test/v1', '/status', [
        'methods' => 'GET',
        'callback' => function() {
            return [
                'wordpress_version' => get_bloginfo('version'),
                'php_version' => phpversion(),
                'tlat_active' => class_exists('TLAT_License_Validator') || defined('TLAT_VERSION'),
                'tutor_lms_active' => class_exists('TUTOR\Tutor') || defined('TUTOR_VERSION'),
                'tutor_lms_version' => defined('TUTOR_VERSION') ? TUTOR_VERSION : null,
                'mysql_version' => $GLOBALS['wpdb']->db_version(),
                'timestamp' => current_time('mysql'),
            ];
        },
        'permission_callback' => '__return_true',
    ]);
});
