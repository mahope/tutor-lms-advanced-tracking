<?php
/**
 * Admin Settings Template
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="tutor-advanced-admin wrap">
    <div class="tutor-admin-header">
        <div>
            <h1><?php _e('Settings', 'tutor-lms-advanced-tracking'); ?></h1>
            <p><?php _e('Configure Advanced Tutor LMS Statistics plugin settings', 'tutor-lms-advanced-tracking'); ?></p>
        </div>
        <div class="tutor-admin-actions">
            <a href="<?php echo admin_url('admin.php?page=tutor-advanced-stats'); ?>" class="tutor-btn tutor-btn-secondary">
                <span class="dashicons dashicons-arrow-left-alt"></span>
                <?php _e('Back to Dashboard', 'tutor-lms-advanced-tracking'); ?>
            </a>
        </div>
    </div>

    <div class="tutor-settings-form">
        <form method="post" action="options.php" id="tutor-settings-form">
            <?php
            settings_fields('tutor_advanced_stats_settings');
            do_settings_sections('tutor_advanced_stats_settings');
            ?>
            
            <div class="submit-section">
                <hr>
                <p class="submit">
                    <?php submit_button(__('Save Settings', 'tutor-lms-advanced-tracking'), 'primary', 'submit', false); ?>
                    <button type="button" class="tutor-btn tutor-btn-secondary" id="reset-settings">
                        <?php _e('Reset to Defaults', 'tutor-lms-advanced-tracking'); ?>
                    </button>
                </p>
            </div>
        </form>
    </div>

    <!-- Settings Help -->
    <div class="tutor-admin-card">
        <h3>
            <span class="dashicons dashicons-info"></span>
            <?php _e('Settings Help', 'tutor-lms-advanced-tracking'); ?>
        </h3>
        <div class="card-content">
            <div class="settings-help">
                <h4><?php _e('General Settings', 'tutor-lms-advanced-tracking'); ?></h4>
                <ul>
                    <li><strong><?php _e('Enable Plugin:', 'tutor-lms-advanced-tracking'); ?></strong> <?php _e('Turn the plugin functionality on or off without deactivating', 'tutor-lms-advanced-tracking'); ?></li>
                    <li><strong><?php _e('Dashboard Access:', 'tutor-lms-advanced-tracking'); ?></strong> <?php _e('Control who can view the frontend dashboard via shortcode', 'tutor-lms-advanced-tracking'); ?></li>
                </ul>

                <h4><?php _e('Cache Settings', 'tutor-lms-advanced-tracking'); ?></h4>
                <ul>
                    <li><strong><?php _e('Cache Duration:', 'tutor-lms-advanced-tracking'); ?></strong> <?php _e('How long data is cached. Lower values = more up-to-date data but slower performance', 'tutor-lms-advanced-tracking'); ?></li>
                </ul>

                <h4><?php _e('Debug Settings', 'tutor-lms-advanced-tracking'); ?></h4>
                <ul>
                    <li><strong><?php _e('Debug Mode:', 'tutor-lms-advanced-tracking'); ?></strong> <?php _e('Enable detailed error logging for troubleshooting', 'tutor-lms-advanced-tracking'); ?></li>
                    <li><strong><?php _e('Performance Monitoring:', 'tutor-lms-advanced-tracking'); ?></strong> <?php _e('Track database query performance and execution times', 'tutor-lms-advanced-tracking'); ?></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Current Configuration -->
    <div class="tutor-admin-card">
        <h3>
            <span class="dashicons dashicons-admin-settings"></span>
            <?php _e('Current Configuration', 'tutor-lms-advanced-tracking'); ?>
        </h3>
        <div class="card-content">
            <?php
            $options = TutorAdvancedTracking_Admin::get_options();
            $wp_roles = wp_roles();
            ?>
            <table class="system-info-table">
                <tbody>
                    <tr>
                        <td><strong><?php _e('Plugin Status', 'tutor-lms-advanced-tracking'); ?></strong></td>
                        <td>
                            <span class="status <?php echo $options['enable_plugin'] ? 'status-ok' : 'status-error'; ?>">
                                <?php echo $options['enable_plugin'] ? __('Enabled', 'tutor-lms-advanced-tracking') : __('Disabled', 'tutor-lms-advanced-tracking'); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Dashboard Access', 'tutor-lms-advanced-tracking'); ?></strong></td>
                        <td>
                            <?php 
                            $role_name = isset($wp_roles->roles[$options['dashboard_access_role']]) 
                                ? $wp_roles->roles[$options['dashboard_access_role']]['name'] 
                                : ucfirst($options['dashboard_access_role']);
                            echo esc_html($role_name) . ' ' . __('and above', 'tutor-lms-advanced-tracking'); 
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Cache Duration', 'tutor-lms-advanced-tracking'); ?></strong></td>
                        <td><?php echo $options['cache_duration']; ?> <?php _e('minutes', 'tutor-lms-advanced-tracking'); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Debug Mode', 'tutor-lms-advanced-tracking'); ?></strong></td>
                        <td>
                            <span class="status <?php echo $options['debug_mode'] ? 'status-warning' : 'status-ok'; ?>">
                                <?php echo $options['debug_mode'] ? __('Enabled', 'tutor-lms-advanced-tracking') : __('Disabled', 'tutor-lms-advanced-tracking'); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Performance Monitoring', 'tutor-lms-advanced-tracking'); ?></strong></td>
                        <td>
                            <span class="status <?php echo $options['performance_monitoring'] ? 'status-warning' : 'status-ok'; ?>">
                                <?php echo $options['performance_monitoring'] ? __('Enabled', 'tutor-lms-advanced-tracking') : __('Disabled', 'tutor-lms-advanced-tracking'); ?>
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Shortcode Reference -->
    <div class="tutor-admin-card">
        <h3>
            <span class="dashicons dashicons-shortcode"></span>
            <?php _e('Shortcode Reference', 'tutor-lms-advanced-tracking'); ?>
        </h3>
        <div class="card-content">
            <div class="shortcode-examples">
                <h4><?php _e('Basic Usage', 'tutor-lms-advanced-tracking'); ?></h4>
                <code>[tutor_advanced_stats]</code>
                <p><?php _e('Displays the full dashboard with all features', 'tutor-lms-advanced-tracking'); ?></p>

                <h4><?php _e('Specific Views', 'tutor-lms-advanced-tracking'); ?></h4>
                <div class="shortcode-list">
                    <div class="shortcode-item">
                        <code>[tutor_advanced_stats view="dashboard"]</code>
                        <span><?php _e('Dashboard overview (default)', 'tutor-lms-advanced-tracking'); ?></span>
                    </div>
                    <div class="shortcode-item">
                        <code>[tutor_advanced_stats view="course" course_id="123"]</code>
                        <span><?php _e('Specific course details', 'tutor-lms-advanced-tracking'); ?></span>
                    </div>
                    <div class="shortcode-item">
                        <code>[tutor_advanced_stats view="user" user_id="456"]</code>
                        <span><?php _e('Specific user analytics', 'tutor-lms-advanced-tracking'); ?></span>
                    </div>
                    <div class="shortcode-item">
                        <code>[tutor_advanced_stats view="analytics"]</code>
                        <span><?php _e('Advanced analytics view', 'tutor-lms-advanced-tracking'); ?></span>
                    </div>
                </div>

                <div class="shortcode-note">
                    <p><strong><?php _e('Note:', 'tutor-lms-advanced-tracking'); ?></strong></p>
                    <ul>
                        <li><?php _e('Users must be logged in to view the dashboard', 'tutor-lms-advanced-tracking'); ?></li>
                        <li><?php _e('Access is controlled by the "Dashboard Access" setting above', 'tutor-lms-advanced-tracking'); ?></li>
                        <li><?php _e('Instructors can only see their own courses and students', 'tutor-lms-advanced-tracking'); ?></li>
                        <li><?php _e('URL parameters override shortcode attributes for navigation', 'tutor-lms-advanced-tracking'); ?></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Reset settings confirmation
    $('#reset-settings').on('click', function(e) {
        e.preventDefault();
        
        if (confirm('<?php _e('Are you sure you want to reset all settings to defaults? This cannot be undone.', 'tutor-lms-advanced-tracking'); ?>')) {
            // Clear all form values and set defaults
            $('input[type="checkbox"]').prop('checked', false);
            $('input[name="tutor_advanced_stats_options[enable_plugin]"]').prop('checked', true);
            $('select[name="tutor_advanced_stats_options[dashboard_access_role]"]').val('instructor');
            $('input[name="tutor_advanced_stats_options[cache_duration]"]').val(5);
            
            // Submit form
            $('#tutor-settings-form').submit();
        }
    });
    
    // Form validation
    $('#tutor-settings-form').on('submit', function(e) {
        var cacheDuration = parseInt($('input[name="tutor_advanced_stats_options[cache_duration]"]').val());
        
        if (cacheDuration < 1 || cacheDuration > 1440) {
            e.preventDefault();
            alert('<?php _e('Cache duration must be between 1 and 1440 minutes.', 'tutor-lms-advanced-tracking'); ?>');
            return false;
        }
    });
});
</script>

<style>
.settings-help h4 {
    color: #23282d;
    margin: 20px 0 10px 0;
    font-size: 14px;
}

.settings-help h4:first-child {
    margin-top: 0;
}

.settings-help ul {
    margin: 10px 0 20px 20px;
}

.settings-help li {
    margin: 8px 0;
    font-size: 14px;
    line-height: 1.5;
}

.shortcode-examples h4 {
    color: #23282d;
    margin: 20px 0 10px 0;
    font-size: 14px;
}

.shortcode-examples h4:first-child {
    margin-top: 0;
}

.shortcode-examples code {
    background: #f0f0f1;
    padding: 4px 8px;
    border-radius: 3px;
    font-family: 'Courier New', monospace;
    font-size: 13px;
}

.shortcode-list {
    margin: 15px 0;
}

.shortcode-item {
    display: flex;
    align-items: center;
    margin: 10px 0;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 4px;
}

.shortcode-item code {
    margin-right: 15px;
    min-width: 280px;
    background: #fff;
    border: 1px solid #e0e0e0;
}

.shortcode-note {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 4px;
    border-left: 4px solid #0073aa;
    margin-top: 20px;
}

.shortcode-note ul {
    margin: 10px 0 0 20px;
}

.shortcode-note li {
    margin: 5px 0;
    font-size: 13px;
}

.submit-section {
    margin-top: 20px;
}

.submit-section hr {
    margin: 20px 0;
    border: none;
    border-top: 1px solid #e0e0e0;
}
</style>