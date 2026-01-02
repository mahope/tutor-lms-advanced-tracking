<?php
/**
 * Admin Debug Tools Template
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="tutor-advanced-admin wrap">
    <div class="tutor-admin-header">
        <div>
            <h1><?php _e('Debug Tools', 'tutor-lms-advanced-tracking'); ?></h1>
            <p><?php _e('Diagnostic tools and troubleshooting utilities', 'tutor-lms-advanced-tracking'); ?></p>
        </div>
        <div class="tutor-admin-actions">
            <a href="<?php echo admin_url('admin.php?page=tutor-advanced-stats'); ?>" class="tutor-btn tutor-btn-secondary">
                <span class="dashicons dashicons-arrow-left-alt"></span>
                <?php _e('Back to Dashboard', 'tutor-lms-advanced-tracking'); ?>
            </a>
            <button class="tutor-btn tutor-btn-danger clear-log-btn">
                <span class="dashicons dashicons-trash"></span>
                <?php _e('Clear All Logs', 'tutor-lms-advanced-tracking'); ?>
            </button>
        </div>
    </div>

    <div class="debug-tools">
        <!-- Data Diagnostics -->
        <div class="debug-section">
            <h3>
                <span class="dashicons dashicons-search"></span>
                <?php _e('Data Diagnostics', 'tutor-lms-advanced-tracking'); ?>
            </h3>
            <p><?php _e('Test data retrieval and validation', 'tutor-lms-advanced-tracking'); ?></p>
            
            <div class="debug-actions">
                <button class="tutor-btn tutor-btn-primary debug-action-btn" 
                        data-action="run_data_diagnostic" 
                        data-output="data-diagnostic-output">
                    <span class="dashicons dashicons-analytics"></span>
                    <?php _e('Run Data Diagnostic', 'tutor-lms-advanced-tracking'); ?>
                </button>
                
                <button class="tutor-btn tutor-btn-secondary debug-action-btn" 
                        data-action="test_tutor_integration" 
                        data-output="integration-test-output">
                    <span class="dashicons dashicons-admin-plugins"></span>
                    <?php _e('Test Tutor Integration', 'tutor-lms-advanced-tracking'); ?>
                </button>
                
                <button class="tutor-btn tutor-btn-secondary debug-action-btn" 
                        data-action="validate_database" 
                        data-output="database-validation-output">
                    <span class="dashicons dashicons-database-view"></span>
                    <?php _e('Validate Database', 'tutor-lms-advanced-tracking'); ?>
                </button>
            </div>
            
            <div id="data-diagnostic-output" class="debug-output"></div>
            <div id="integration-test-output" class="debug-output"></div>
            <div id="database-validation-output" class="debug-output"></div>
        </div>

        <!-- Cache Management -->
        <div class="debug-section">
            <h3>
                <span class="dashicons dashicons-performance"></span>
                <?php _e('Cache Management', 'tutor-lms-advanced-tracking'); ?>
            </h3>
            <p><?php _e('Clear and manage plugin caches', 'tutor-lms-advanced-tracking'); ?></p>
            
            <div class="cache-management">
                <div class="cache-stats">
                    <?php
                    // Get cache statistics
                    global $wpdb;
                    $cache_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_tutor_advanced_%'");
                    $cache_size = $wpdb->get_var("SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE option_name LIKE '_transient_tutor_advanced_%'");
                    ?>
                    
                    <div class="cache-stat">
                        <div class="stat-number"><?php echo (int)$cache_count; ?></div>
                        <div class="stat-label"><?php _e('Cached Items', 'tutor-lms-advanced-tracking'); ?></div>
                    </div>
                    
                    <div class="cache-stat">
                        <div class="stat-number"><?php echo size_format((int)$cache_size); ?></div>
                        <div class="stat-label"><?php _e('Cache Size', 'tutor-lms-advanced-tracking'); ?></div>
                    </div>
                    
                    <div class="cache-stat">
                        <div class="stat-number"><?php echo TutorAdvancedTracking_Admin::get_options()['cache_duration']; ?>m</div>
                        <div class="stat-label"><?php _e('Cache Duration', 'tutor-lms-advanced-tracking'); ?></div>
                    </div>
                </div>
                
                <div class="cache-actions">
                    <button class="tutor-btn tutor-btn-primary cache-action-btn" data-action="clear_all">
                        <span class="dashicons dashicons-update"></span>
                        <?php _e('Clear All Cache', 'tutor-lms-advanced-tracking'); ?>
                    </button>
                    
                    <button class="tutor-btn tutor-btn-secondary cache-action-btn" data-action="clear_courses">
                        <span class="dashicons dashicons-book"></span>
                        <?php _e('Clear Course Cache', 'tutor-lms-advanced-tracking'); ?>
                    </button>
                    
                    <button class="tutor-btn tutor-btn-secondary refresh-data-btn" data-type="all">
                        <span class="dashicons dashicons-database-view"></span>
                        <?php _e('Refresh All Data', 'tutor-lms-advanced-tracking'); ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Database Information -->
        <div class="debug-section">
            <h3>
                <span class="dashicons dashicons-database"></span>
                <?php _e('Database Information', 'tutor-lms-advanced-tracking'); ?>
            </h3>
            <p><?php _e('Database tables and connection status', 'tutor-lms-advanced-tracking'); ?></p>
            
            <?php
            // Check database tables
            $tables_to_check = array(
                'tutor_enrollments' => __('Course Enrollments', 'tutor-lms-advanced-tracking'),
                'tutor_quiz_attempts' => __('Quiz Attempts', 'tutor-lms-advanced-tracking'),
                'tutor_lesson_activities' => __('Lesson Activities', 'tutor-lms-advanced-tracking'),
                'tutor_course_completed' => __('Course Completions', 'tutor-lms-advanced-tracking')
            );
            ?>
            
            <table class="system-info-table">
                <thead>
                    <tr>
                        <th><?php _e('Table', 'tutor-lms-advanced-tracking'); ?></th>
                        <th><?php _e('Status', 'tutor-lms-advanced-tracking'); ?></th>
                        <th><?php _e('Records', 'tutor-lms-advanced-tracking'); ?></th>
                        <th><?php _e('Size', 'tutor-lms-advanced-tracking'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tables_to_check as $table => $description): ?>
                        <?php
                        $full_table = $wpdb->prefix . $table;
                        $exists = TutorAdvancedTracking_TutorIntegration::table_exists($full_table);
                        $count = 0;
                        $size = 0;
                        
                        if ($exists) {
                            $count = $wpdb->get_var("SELECT COUNT(*) FROM $full_table");
                            $size_result = $wpdb->get_row("SELECT 
                                ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb 
                                FROM information_schema.tables 
                                WHERE table_schema = DATABASE() AND table_name = '$full_table'");
                            $size = $size_result ? $size_result->size_mb : 0;
                        }
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($description); ?></strong>
                                <br><small><code><?php echo esc_html($full_table); ?></code></small>
                            </td>
                            <td>
                                <span class="status <?php echo $exists ? 'status-ok' : 'status-error'; ?>">
                                    <?php echo $exists ? __('Exists', 'tutor-lms-advanced-tracking') : __('Missing', 'tutor-lms-advanced-tracking'); ?>
                                </span>
                            </td>
                            <td><?php echo $exists ? number_format($count) : '-'; ?></td>
                            <td><?php echo $exists && $size > 0 ? $size . ' MB' : '-'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Performance Monitoring -->
        <div class="debug-section">
            <h3>
                <span class="dashicons dashicons-chart-line"></span>
                <?php _e('Performance Monitoring', 'tutor-lms-advanced-tracking'); ?>
            </h3>
            <p><?php _e('Track plugin performance and identify bottlenecks', 'tutor-lms-advanced-tracking'); ?></p>
            
            <?php
            $options = TutorAdvancedTracking_Admin::get_options();
            if ($options['performance_monitoring']):
            ?>
                <div class="performance-stats">
                    <!-- This would show actual performance data if monitoring is enabled -->
                    <p><?php _e('Performance monitoring is enabled. Check your error logs for detailed timing information.', 'tutor-lms-advanced-tracking'); ?></p>
                    
                    <div class="debug-actions">
                        <button class="tutor-btn tutor-btn-secondary" onclick="window.open('<?php echo admin_url('admin.php?page=tutor-advanced-stats-system'); ?>')">
                            <span class="dashicons dashicons-chart-bar"></span>
                            <?php _e('View System Info', 'tutor-lms-advanced-tracking'); ?>
                        </button>
                    </div>
                </div>
            <?php else: ?>
                <div class="tutor-alert tutor-alert-info">
                    <?php _e('Performance monitoring is disabled. Enable it in Settings to track query performance.', 'tutor-lms-advanced-tracking'); ?>
                    <a href="<?php echo admin_url('admin.php?page=tutor-advanced-stats-settings'); ?>" class="tutor-btn tutor-btn-primary" style="margin-left: 10px;">
                        <?php _e('Enable Monitoring', 'tutor-lms-advanced-tracking'); ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Error Logs -->
        <div class="debug-section">
            <h3>
                <span class="dashicons dashicons-warning"></span>
                <?php _e('Recent Errors', 'tutor-lms-advanced-tracking'); ?>
            </h3>
            <p><?php _e('Recent plugin errors and warnings', 'tutor-lms-advanced-tracking'); ?></p>
            
            <?php
            // Check for recent errors in the error log
            $error_log_path = ini_get('error_log');
            $recent_errors = array();
            
            if ($error_log_path && file_exists($error_log_path) && is_readable($error_log_path)) {
                $log_lines = file($error_log_path);
                $tutor_errors = array();
                
                // Get last 50 lines and filter for our plugin
                foreach (array_slice($log_lines, -50) as $line) {
                    if (strpos($line, 'Advanced Tutor LMS Stats Dashboard') !== false || 
                        strpos($line, 'tutor_advanced') !== false) {
                        $tutor_errors[] = trim($line);
                    }
                }
                $recent_errors = array_slice($tutor_errors, -10); // Last 10 errors
            }
            ?>
            
            <?php if (!empty($recent_errors)): ?>
                <div class="error-log-container">
                    <div class="debug-output show">
                        <pre><?php echo esc_html(implode("\n", array_reverse($recent_errors))); ?></pre>
                    </div>
                </div>
            <?php else: ?>
                <div class="tutor-alert tutor-alert-success">
                    <?php _e('No recent errors found. The plugin is running smoothly!', 'tutor-lms-advanced-tracking'); ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Debug URL Parameters -->
        <div class="debug-section">
            <h3>
                <span class="dashicons dashicons-admin-links"></span>
                <?php _e('Debug URLs', 'tutor-lms-advanced-tracking'); ?>
            </h3>
            <p><?php _e('Quick links for debugging specific issues', 'tutor-lms-advanced-tracking'); ?></p>
            
            <div class="debug-urls">
                <div class="url-item">
                    <strong><?php _e('Frontend Debug:', 'tutor-lms-advanced-tracking'); ?></strong>
                    <code><?php echo home_url('?debug_dashboard=1'); ?></code>
                    <a href="<?php echo home_url('?debug_dashboard=1'); ?>" target="_blank" class="tutor-btn tutor-btn-secondary">
                        <?php _e('Open', 'tutor-lms-advanced-tracking'); ?>
                    </a>
                </div>
                
                <div class="url-item">
                    <strong><?php _e('Course Data Debug:', 'tutor-lms-advanced-tracking'); ?></strong>
                    <code><?php echo home_url('?debug_course_data=1'); ?></code>
                    <a href="<?php echo home_url('?debug_course_data=1'); ?>" target="_blank" class="tutor-btn tutor-btn-secondary">
                        <?php _e('Open', 'tutor-lms-advanced-tracking'); ?>
                    </a>
                </div>
                
                <div class="url-item">
                    <strong><?php _e('Integration Test:', 'tutor-lms-advanced-tracking'); ?></strong>
                    <code><?php echo home_url('?test_tutor_integration=1'); ?></code>
                    <a href="<?php echo home_url('?test_tutor_integration=1'); ?>" target="_blank" class="tutor-btn tutor-btn-secondary">
                        <?php _e('Open', 'tutor-lms-advanced-tracking'); ?>
                    </a>
                </div>
            </div>
            
            <div class="tutor-alert tutor-alert-warning">
                <strong><?php _e('Security Note:', 'tutor-lms-advanced-tracking'); ?></strong>
                <?php _e('Debug URLs are only accessible by administrators and should not be shared publicly.', 'tutor-lms-advanced-tracking'); ?>
            </div>
        </div>
    </div>
</div>

<style>
.error-log-container {
    margin-top: 15px;
}

.debug-urls {
    margin: 15px 0;
}

.url-item {
    display: flex;
    align-items: center;
    margin: 10px 0;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 4px;
    gap: 10px;
}

.url-item strong {
    min-width: 150px;
    color: #23282d;
}

.url-item code {
    flex: 1;
    background: #fff;
    padding: 5px 8px;
    border-radius: 3px;
    border: 1px solid #e0e0e0;
    font-family: 'Courier New', monospace;
    font-size: 12px;
}

.performance-stats {
    padding: 15px;
    background: #f8f9fa;
    border-radius: 4px;
    margin: 15px 0;
}

.cache-stat .stat-number {
    font-size: 24px;
    font-weight: bold;
    color: #0073aa;
    margin: 0;
}

.cache-stat .stat-label {
    color: #666;
    font-size: 12px;
    margin: 5px 0 0 0;
}
</style>