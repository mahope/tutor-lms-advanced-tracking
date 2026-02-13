<?php
/**
 * Engagement Analytics Admin Page Template
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$engagement = new TutorAdvancedTracking_Engagement();
$overview = $engagement->get_engagement_overview();
?>

<div class="tutor-advanced-admin wrap">
    <div class="tutor-admin-header">
        <div>
            <h1>
                <?php _e('Engagement Analytics', 'tutor-lms-advanced-tracking'); ?>
                <span class="version"><?php _e('Login Frequency & Session Tracking', 'tutor-lms-advanced-tracking'); ?></span>
            </h1>
            <p><?php _e('Track student login frequency and session lengths to understand engagement patterns', 'tutor-lms-advanced-tracking'); ?></p>
        </div>
        <div class="tutor-admin-actions">
            <select id="engagement-date-range" class="engagement-select">
                <option value="7"><?php _e('Last 7 Days', 'tutor-lms-advanced-tracking'); ?></option>
                <option value="14"><?php _e('Last 14 Days', 'tutor-lms-advanced-tracking'); ?></option>
                <option value="30" selected><?php _e('Last 30 Days', 'tutor-lms-advanced-tracking'); ?></option>
                <option value="60"><?php _e('Last 60 Days', 'tutor-lms-advanced-tracking'); ?></option>
                <option value="90"><?php _e('Last 90 Days', 'tutor-lms-advanced-tracking'); ?></option>
            </select>
        </div>
    </div>

    <!-- Overview Stats Cards -->
    <div class="engagement-overview">
        <div class="stat-card">
            <span class="stat-icon">👥</span>
            <div class="stat-content">
                <span class="stat-value" id="engagement-today-logins"><?php echo $overview['today_logins'] ?? '-'; ?></span>
                <span class="stat-label"><?php _e("Today's Active Users", 'tutor-lms-advanced-tracking'); ?></span>
            </div>
        </div>
        <div class="stat-card">
            <span class="stat-icon">📅</span>
            <div class="stat-content">
                <span class="stat-value" id="engagement-week-users"><?php echo $overview['week_users'] ?? '-'; ?></span>
                <span class="stat-label"><?php _e('This Week', 'tutor-lms-advanced-tracking'); ?></span>
            </div>
        </div>
        <div class="stat-card">
            <span class="stat-icon">📆</span>
            <div class="stat-content">
                <span class="stat-value" id="engagement-month-users"><?php echo $overview['month_users'] ?? '-'; ?></span>
                <span class="stat-label"><?php _e('This Month', 'tutor-lms-advanced-tracking'); ?></span>
            </div>
        </div>
        <div class="stat-card">
            <span class="stat-icon">⏱️</span>
            <div class="stat-content">
                <span class="stat-value" id="engagement-avg-session"><?php echo $overview['avg_session_length'] ?? '-'; ?></span>
                <span class="stat-label"><?php _e('Avg. Session Length', 'tutor-lms-advanced-tracking'); ?></span>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="tutor-admin-card">
        <h3>
            <span class="dashicons dashicons-chart-line"></span>
            <?php _e('Engagement Charts', 'tutor-lms-advanced-tracking'); ?>
        </h3>
        <div class="card-content">
            <div class="engagement-charts">
                <div class="chart-wrapper">
                    <h4><?php _e('Login Frequency', 'tutor-lms-advanced-tracking'); ?></h4>
                    <canvas id="login-frequency-chart" height="300"></canvas>
                </div>
                <div class="chart-wrapper">
                    <h4><?php _e('Session Length Distribution', 'tutor-lms-advanced-tracking'); ?></h4>
                    <canvas id="session-length-chart" height="300"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Active Students -->
    <div class="tutor-admin-card">
        <h3>
            <span class="dashicons dashicons-groups"></span>
            <?php _e('Top Active Students', 'tutor-lms-advanced-tracking'); ?>
        </h3>
        <div class="card-content">
            <table class="top-students-table wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Student', 'tutor-lms-advanced-tracking'); ?></th>
                        <th><?php _e('Sessions', 'tutor-lms-advanced-tracking'); ?></th>
                        <th><?php _e('Total Time', 'tutor-lms-advanced-tracking'); ?></th>
                        <th><?php _e('Last Active', 'tutor-lms-advanced-tracking'); ?></th>
                    </tr>
                </thead>
                <tbody id="top-students-body">
                    <tr>
                        <td colspan="4" class="loading">
                            <span class="loading-spinner"></span>
                            <?php _e('Loading student data...', 'tutor-lms-advanced-tracking'); ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Session Insights -->
    <div class="tutor-admin-card">
        <h3>
            <span class="dashicons dashicons-lightbulb"></span>
            <?php _e('Session Insights', 'tutor-lms-advanced-tracking'); ?>
        </h3>
        <div class="card-content">
            <div class="insights-grid">
                <div class="insight-item">
                    <h4><?php _e('Average Session Duration', 'tutor-lms-advanced-tracking'); ?></h4>
                    <p><?php _e('Shows how long students typically stay logged in. Longer sessions may indicate more engaged learning.', 'tutor-lms-advanced-tracking'); ?></p>
                </div>
                <div class="insight-item">
                    <h4><?php _e('Login Patterns', 'tutor-lms-advanced-tracking'); ?></h4>
                    <p><?php _e('Track peak login times to understand when students prefer to study. This can inform communication timing.', 'tutor-lms-advanced-tracking'); ?></p>
                </div>
                <div class="insight-item">
                    <h4><?php _e('Active Users Today', 'tutor-lms-advanced-tracking'); ?></h4>
                    <p><?php _e('Real-time indicator of current platform engagement. Use this to send timely announcements.', 'tutor-lms-advanced-tracking'); ?></p>
                </div>
                <div class="insight-item">
                    <h4><?php _e('Top Performers', 'tutor-lms-advanced-tracking'); ?></h4>
                    <p><?php _e('Students with highest session counts and total time may be candidates for leadership or peer mentoring roles.', 'tutor-lms-advanced-tracking'); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Peak Activity Hours Heatmap -->
    <div class="tutor-admin-card">
        <h3>
            <span class="dashicons dashicons-grid-view"></span>
            <?php _e('Peak Activity Hours Heatmap', 'tutor-lms-advanced-tracking'); ?>
        </h3>
        <div class="card-content">
            <?php
            // Enqueue Chart.js with matrix plugin support
            wp_enqueue_script(
                'chartjs',
                'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
                array(),
                '4.4.0',
                true
            );
            
            wp_enqueue_script(
                'chartjs-chart-matrix',
                TUTOR_ADVANCED_TRACKING_PLUGIN_URL . 'assets/js/chartjs-chart-matrix.min.js',
                array('chartjs'),
                '2.0.1',
                true
            );
            
            // Render the heatmap section
            $engagement->render_peak_activity_heatmap();
            ?>
        </div>
    </div>

</div>

<style>
.insights-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.insight-item {
    background: #f8fafc;
    border-radius: 8px;
    padding: 16px;
    border: 1px solid #e2e8f0;
}

.insight-item h4 {
    margin: 0 0 10px 0;
    font-size: 14px;
    color: #1d2327;
}

.insight-item p {
    margin: 0;
    font-size: 13px;
    color: #646970;
    line-height: 1.5;
}

.loading-spinner {
    display: inline-block;
    width: 16px;
    height: 16px;
    border: 2px solid #e2e8f0;
    border-top-color: #2271b1;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
    margin-right: 8px;
    vertical-align: middle;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}
</style>
