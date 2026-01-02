<?php
/**
 * Admin System Info Template
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Gather system information
global $wpdb;

$system_info = array(
    'WordPress' => array(
        'Version' => get_bloginfo('version'),
        'Multisite' => is_multisite() ? 'Yes' : 'No',
        'Debug Mode' => defined('WP_DEBUG') && WP_DEBUG ? 'Enabled' : 'Disabled',
        'Memory Limit' => ini_get('memory_limit'),
        'Max Execution Time' => ini_get('max_execution_time') . 's',
        'Upload Max Size' => ini_get('upload_max_filesize'),
        'Post Max Size' => ini_get('post_max_size')
    ),
    'Server' => array(
        'PHP Version' => phpversion(),
        'Server Software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
        'MySQL Version' => $wpdb->db_version(),
        'cURL Version' => function_exists('curl_version') ? curl_version()['version'] : 'Not available',
        'OpenSSL Version' => defined('OPENSSL_VERSION_TEXT') ? OPENSSL_VERSION_TEXT : 'Not available',
        'User Agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
    ),
    'Tutor LMS' => array(
        'Active' => function_exists('tutor') ? 'Yes' : 'No',
        'Version' => defined('TUTOR_VERSION') ? TUTOR_VERSION : 'Unknown',
        'Course Post Type' => TutorAdvancedTracking_TutorIntegration::get_course_post_type(),
        'Lesson Post Type' => TutorAdvancedTracking_TutorIntegration::get_lesson_post_type(),
        'Quiz Post Type' => TutorAdvancedTracking_TutorIntegration::get_quiz_post_type()
    ),
    'Plugin' => array(
        'Version' => TUTOR_ADVANCED_TRACKING_VERSION,
        'Plugin Directory' => TUTOR_ADVANCED_TRACKING_PLUGIN_DIR,
        'Plugin URL' => TUTOR_ADVANCED_TRACKING_PLUGIN_URL,
        'Debug Mode' => TutorAdvancedTracking_Admin::get_options()['debug_mode'] ? 'Enabled' : 'Disabled',
        'Performance Monitoring' => TutorAdvancedTracking_Admin::get_options()['performance_monitoring'] ? 'Enabled' : 'Disabled',
        'Cache Duration' => TutorAdvancedTracking_Admin::get_options()['cache_duration'] . ' minutes'
    )
);

// Get active plugins
$active_plugins = get_option('active_plugins', array());
$plugin_details = array();

foreach ($active_plugins as $plugin) {
    $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin);
    if (!empty($plugin_data['Name'])) {
        $plugin_details[] = $plugin_data['Name'] . ' v' . $plugin_data['Version'];
    }
}

// Get theme information
$theme = wp_get_theme();
?>

<div class="tutor-advanced-admin wrap">
    <div class="tutor-admin-header">
        <div>
            <h1><?php _e('System Information', 'tutor-lms-advanced-tracking'); ?></h1>
            <p><?php _e('System configuration and compatibility information', 'tutor-lms-advanced-tracking'); ?></p>
        </div>
        <div class="tutor-admin-actions">
            <a href="<?php echo admin_url('admin.php?page=tutor-advanced-stats'); ?>" class="tutor-btn tutor-btn-secondary">
                <span class="dashicons dashicons-arrow-left-alt"></span>
                <?php _e('Back to Dashboard', 'tutor-lms-advanced-tracking'); ?>
            </a>
            <button class="tutor-btn tutor-btn-primary" id="copy-system-info">
                <span class="dashicons dashicons-clipboard"></span>
                <?php _e('Copy System Info', 'tutor-lms-advanced-tracking'); ?>
            </button>
        </div>
    </div>

    <!-- System Overview -->
    <div class="tutor-admin-card">
        <h3>
            <span class="dashicons dashicons-dashboard"></span>
            <?php _e('System Overview', 'tutor-lms-advanced-tracking'); ?>
        </h3>
        <div class="card-content">
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-number"><?php echo phpversion(); ?></div>
                    <div class="stat-label"><?php _e('PHP Version', 'tutor-lms-advanced-tracking'); ?></div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo get_bloginfo('version'); ?></div>
                    <div class="stat-label"><?php _e('WordPress', 'tutor-lms-advanced-tracking'); ?></div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo defined('TUTOR_VERSION') ? TUTOR_VERSION : 'N/A'; ?></div>
                    <div class="stat-label"><?php _e('Tutor LMS', 'tutor-lms-advanced-tracking'); ?></div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo TUTOR_ADVANCED_TRACKING_VERSION; ?></div>
                    <div class="stat-label"><?php _e('Plugin Version', 'tutor-lms-advanced-tracking'); ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed System Information -->
    <?php foreach ($system_info as $section => $info): ?>
        <div class="tutor-admin-card">
            <h3>
                <span class="dashicons dashicons-<?php echo $section === 'WordPress' ? 'wordpress' : ($section === 'Server' ? 'admin-settings' : ($section === 'Tutor LMS' ? 'book' : 'admin-plugins')); ?>"></span>
                <?php echo esc_html($section); ?> <?php _e('Information', 'tutor-lms-advanced-tracking'); ?>
            </h3>
            <div class="card-content">
                <table class="system-info-table">
                    <tbody>
                        <?php foreach ($info as $key => $value): ?>
                            <tr>
                                <td><strong><?php echo esc_html($key); ?></strong></td>
                                <td>
                                    <?php 
                                    // Add status indicators for certain values
                                    $status_class = '';
                                    if ($key === 'Active' || $key === 'Debug Mode' || $key === 'Performance Monitoring') {
                                        if ($value === 'Yes' || $value === 'Enabled') {
                                            $status_class = 'status-ok';
                                        } elseif ($value === 'No' || $value === 'Disabled') {
                                            $status_class = $key === 'Debug Mode' ? 'status-ok' : 'status-warning';
                                        }
                                    }
                                    ?>
                                    <span class="<?php echo $status_class; ?>">
                                        <?php echo esc_html($value); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endforeach; ?>

    <!-- Theme Information -->
    <div class="tutor-admin-card">
        <h3>
            <span class="dashicons dashicons-admin-appearance"></span>
            <?php _e('Theme Information', 'tutor-lms-advanced-tracking'); ?>
        </h3>
        <div class="card-content">
            <table class="system-info-table">
                <tbody>
                    <tr>
                        <td><strong><?php _e('Active Theme', 'tutor-lms-advanced-tracking'); ?></strong></td>
                        <td><?php echo esc_html($theme->get('Name')); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Version', 'tutor-lms-advanced-tracking'); ?></strong></td>
                        <td><?php echo esc_html($theme->get('Version')); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Author', 'tutor-lms-advanced-tracking'); ?></strong></td>
                        <td><?php echo esc_html($theme->get('Author')); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Child Theme', 'tutor-lms-advanced-tracking'); ?></strong></td>
                        <td><?php echo is_child_theme() ? __('Yes', 'tutor-lms-advanced-tracking') : __('No', 'tutor-lms-advanced-tracking'); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Active Plugins -->
    <div class="tutor-admin-card">
        <h3>
            <span class="dashicons dashicons-admin-plugins"></span>
            <?php _e('Active Plugins', 'tutor-lms-advanced-tracking'); ?>
            <span class="plugin-count">(<?php echo count($plugin_details); ?>)</span>
        </h3>
        <div class="card-content">
            <div class="plugins-list">
                <?php if (!empty($plugin_details)): ?>
                    <?php foreach ($plugin_details as $plugin): ?>
                        <div class="plugin-item">
                            <?php echo esc_html($plugin); ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p><?php _e('No active plugins found.', 'tutor-lms-advanced-tracking'); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Database Statistics -->
    <div class="tutor-admin-card">
        <h3>
            <span class="dashicons dashicons-database"></span>
            <?php _e('Database Statistics', 'tutor-lms-advanced-tracking'); ?>
        </h3>
        <div class="card-content">
            <?php
            // Get database statistics
            $db_stats = array();
            
            // WordPress tables
            $wp_tables = $wpdb->get_results("SHOW TABLE STATUS LIKE '{$wpdb->prefix}%'");
            $total_size = 0;
            $wp_table_count = 0;
            
            foreach ($wp_tables as $table) {
                $total_size += $table->Data_length + $table->Index_length;
                $wp_table_count++;
            }
            
            // Tutor LMS specific tables
            $tutor_tables = array('tutor_enrollments', 'tutor_quiz_attempts', 'tutor_lesson_activities');
            $tutor_records = 0;
            
            foreach ($tutor_tables as $table) {
                $full_table = $wpdb->prefix . $table;
                if (TutorAdvancedTracking_TutorIntegration::table_exists($full_table)) {
                    $count = $wpdb->get_var("SELECT COUNT(*) FROM $full_table");
                    $tutor_records += (int)$count;
                }
            }
            ?>
            
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-number"><?php echo number_format($wp_table_count); ?></div>
                    <div class="stat-label"><?php _e('Total Tables', 'tutor-lms-advanced-tracking'); ?></div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo size_format($total_size); ?></div>
                    <div class="stat-label"><?php _e('Database Size', 'tutor-lms-advanced-tracking'); ?></div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo number_format($tutor_records); ?></div>
                    <div class="stat-label"><?php _e('Tutor Records', 'tutor-lms-advanced-tracking'); ?></div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo $wpdb->db_version(); ?></div>
                    <div class="stat-label"><?php _e('MySQL Version', 'tutor-lms-advanced-tracking'); ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- System Requirements Check -->
    <div class="tutor-admin-card">
        <h3>
            <span class="dashicons dashicons-yes"></span>
            <?php _e('Requirements Check', 'tutor-lms-advanced-tracking'); ?>
        </h3>
        <div class="card-content">
            <?php
            $requirements = array(
                'PHP 7.4+' => version_compare(phpversion(), '7.4', '>='),
                'WordPress 5.0+' => version_compare(get_bloginfo('version'), '5.0', '>='),
                'Tutor LMS Active' => function_exists('tutor'),
                'MySQL 5.6+' => version_compare($wpdb->db_version(), '5.6', '>='),
                'cURL Extension' => function_exists('curl_init'),
                'JSON Extension' => function_exists('json_encode'),
                'Memory Limit (128MB+)' => (int)ini_get('memory_limit') >= 128 || ini_get('memory_limit') === '-1'
            );
            ?>
            
            <table class="system-info-table">
                <tbody>
                    <?php foreach ($requirements as $requirement => $met): ?>
                        <tr>
                            <td><strong><?php echo esc_html($requirement); ?></strong></td>
                            <td>
                                <span class="status <?php echo $met ? 'status-ok' : 'status-error'; ?>">
                                    <?php echo $met ? '✓ ' . __('Met', 'tutor-lms-advanced-tracking') : '✕ ' . __('Not Met', 'tutor-lms-advanced-tracking'); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Raw System Info (Hidden by default) -->
    <div class="tutor-admin-card">
        <h3>
            <span class="dashicons dashicons-editor-code"></span>
            <?php _e('Raw System Information', 'tutor-lms-advanced-tracking'); ?>
        </h3>
        <div class="card-content">
            <p><?php _e('Copy this information when requesting support:', 'tutor-lms-advanced-tracking'); ?></p>
            <textarea id="raw-system-info" readonly style="width: 100%; height: 300px; font-family: monospace; font-size: 12px; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
<?php
// Generate raw system info text
$raw_info = "=== SYSTEM INFORMATION ===\n";
$raw_info .= "Generated: " . current_time('Y-m-d H:i:s') . " UTC\n\n";

foreach ($system_info as $section => $info) {
    $raw_info .= "=== $section ===\n";
    foreach ($info as $key => $value) {
        $raw_info .= "$key: $value\n";
    }
    $raw_info .= "\n";
}

$raw_info .= "=== ACTIVE PLUGINS ===\n";
foreach ($plugin_details as $plugin) {
    $raw_info .= "$plugin\n";
}

$raw_info .= "\n=== THEME ===\n";
$raw_info .= "Name: " . $theme->get('Name') . "\n";
$raw_info .= "Version: " . $theme->get('Version') . "\n";
$raw_info .= "Author: " . $theme->get('Author') . "\n";
$raw_info .= "Child Theme: " . (is_child_theme() ? 'Yes' : 'No') . "\n";

echo esc_textarea($raw_info);
?>
            </textarea>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Copy system info to clipboard
    $('#copy-system-info').on('click', function(e) {
        e.preventDefault();
        
        var $textarea = $('#raw-system-info');
        $textarea.select();
        document.execCommand('copy');
        
        // Show success message
        $(this).text('<?php _e('Copied!', 'tutor-lms-advanced-tracking'); ?>').addClass('tutor-btn-success');
        
        setTimeout(function() {
            $('#copy-system-info').text('<?php _e('Copy System Info', 'tutor-lms-advanced-tracking'); ?>').removeClass('tutor-btn-success');
        }, 2000);
    });
});
</script>

<style>
.plugin-count {
    color: #666;
    font-weight: normal;
    font-size: 14px;
}

.plugins-list {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 10px;
    margin: 15px 0;
}

.plugin-item {
    padding: 8px 12px;
    background: #f8f9fa;
    border-radius: 4px;
    border: 1px solid #e9ecef;
    font-size: 13px;
}

#raw-system-info {
    resize: vertical;
    min-height: 200px;
}

.status-ok {
    color: #46b450;
}

.status-warning {
    color: #ffb900;
}

.status-error {
    color: #dc3232;
}
</style>