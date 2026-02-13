<?php
/**
 * Live Activity Feed Admin Template
 * Real-time student activity monitoring
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get activities for initial display
$live_activity = new TutorAdvancedTracking_LiveActivity();
$initial_activities = $live_activity->get_activities(50);
?>

<div class="wrap tutor-advanced-stats-wrap">
    <h1 class="wp-heading-inline">
        <?php _e('Live Student Activity', 'tutor-lms-advanced-tracking'); ?>
        <span class="live-indicator">
            <span class="live-dot"></span>
            <?php _e('Live', 'tutor-lms-advanced-tracking'); ?>
        </span>
    </h1>
    
    <p class="description">
        <?php _e('Real-time feed of student activities across all courses. Auto-refreshes every 10 seconds.', 'tutor-lms-advanced-tracking'); ?>
    </p>
    
    <div class="tlat-live-activity-container">
        <!-- Stats Summary -->
        <div class="tlat-activity-stats">
            <div class="tlat-stat-box">
                <span class="tlat-stat-number" id="activity-count"><?php echo count($initial_activities); ?></span>
                <span class="tlat-stat-label"><?php _e('Activities Logged', 'tutor-lms-advanced-tracking'); ?></span>
            </div>
            <div class="tlat-stat-box">
                <span class="tlat-stat-number" id="unique-students">0</span>
                <span class="tlat-stat-label"><?php _e('Unique Students (Last Hour)', 'tutor-lms-advanced-tracking'); ?></span>
            </div>
            <div class="tlat-stat-box">
                <span class="tlat-stat-number" id="last-update">--</span>
                <span class="tlat-stat-label"><?php _e('Last Update', 'tutor-lms-advanced-tracking'); ?></span>
            </div>
        </div>
        
        <!-- Activity Feed -->
        <div class="tlat-activity-feed-wrapper">
            <div class="tlat-activity-header">
                <h2><?php _e('Activity Feed', 'tutor-lms-advanced-tracking'); ?></h2>
                <div class="tlat-activity-actions">
                    <button type="button" class="button" id="tlat-refresh-feed">
                        <span class="dashicons dashicons-update"></span>
                        <?php _e('Refresh Now', 'tutor-lms-advanced-tracking'); ?>
                    </button>
                    <button type="button" class="button" id="tlat-clear-feed">
                        <span class="dashicons dashicons-trash"></span>
                        <?php _e('Clear Feed', 'tutor-lms-advanced-tracking'); ?>
                    </button>
                </div>
            </div>
            
            <div class="tlat-activity-feed" id="tlat-activity-feed">
                <?php if (empty($initial_activities)): ?>
                    <div class="tlat-no-activities">
                        <span class="dashicons dashicons-info"></span>
                        <p><?php _e('No activities recorded yet. Student activities will appear here as they happen.', 'tutor-lms-advanced-tracking'); ?></p>
                        <p class="description"><?php _e('Try viewing a lesson or completing a quiz to generate activity data.', 'tutor-lms-advanced-tracking'); ?></p>
                    </div>
                <?php else: ?>
                    <?php foreach ($initial_activities as $activity): ?>
                        <div class="tlat-activity-item" data-id="<?php echo esc_attr($activity['id']); ?>">
                            <div class="tlat-activity-icon <?php echo esc_attr($activity['action_icon']); ?>">
                                <span class="dashicons <?php echo esc_attr($activity['action_icon']); ?>"></span>
                            </div>
                            <div class="tlat-activity-content">
                                <div class="tlat-activity-header">
                                    <span class="tlat-student-name"><?php echo esc_html($activity['student_name']); ?></span>
                                    <span class="tlat-activity-action"><?php echo esc_html($activity['action_label']); ?></span>
                                </div>
                                <div class="tlat-activity-details">
                                    <?php if (!empty($activity['course_name'])): ?>
                                        <span class="tlat-course-name">
                                            <span class="dashicons dashicons-book"></span>
                                            <?php echo esc_html($activity['course_name']); ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if (!empty($activity['lesson_name'])): ?>
                                        <span class="tlat-lesson-name">
                                            <span class="dashicons dashicons-media-document"></span>
                                            <?php echo esc_html($activity['lesson_name']); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="tlat-activity-time">
                                    <span class="dashicons dashicons-clock"></span>
                                    <?php echo esc_html($activity['timestamp_formatted']); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div class="tlat-activity-footer">
                <span class="tlat-auto-refresh">
                    <span class="dashicons dashicons-update tlat-spin"></span>
                    <?php _e('Auto-refreshing every 10 seconds', 'tutor-lms-advanced-tracking'); ?>
                </span>
                <span class="tlat-last-refresh" id="tlat-last-refresh">
                    <?php _e('Last refreshed:', 'tutor-lms-advanced-tracking'); ?> 
                    <?php echo esc_html(current_time('mysql')); ?>
                </span>
            </div>
        </div>
        
        <!-- Legend -->
        <div class="tlat-activity-legend">
            <h3><?php _e('Activity Types', 'tutor-lms-advanced-tracking'); ?></h3>
            <div class="tlat-legend-items">
                <div class="tlat-legend-item">
                    <span class="dashicons dashicons-welcome-learn-more"></span>
                    <span><?php _e('Viewed Lesson', 'tutor-lms-advanced-tracking'); ?></span>
                </div>
                <div class="tlat-legend-item">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <span><?php _e('Completed Lesson', 'tutor-lms-advanced-tracking'); ?></span>
                </div>
                <div class="tlat-legend-item">
                    <span class="dashicons dashicons-clipboard"></span>
                    <span><?php _e('Completed Quiz', 'tutor-lms-advanced-tracking'); ?></span>
                </div>
                <div class="tlat-legend-item">
                    <span class="dashicons dashicons-pressthis"></span>
                    <span><?php _e('Submitted Assignment', 'tutor-lms-advanced-tracking'); ?></span>
                </div>
                <div class="tlat-legend-item">
                    <span class="dashicons dashicons-plus"></span>
                    <span><?php _e('Enrolled in Course', 'tutor-lms-advanced-tracking'); ?></span>
                </div>
                <div class="tlat-legend-item">
                    <span class="dashicons dashicons-awards"></span>
                    <span><?php _e('Completed Course', 'tutor-lms-advanced-tracking'); ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Live Activity Feed Configuration
    var TlatLiveActivity = {
        refreshInterval: 10000, // 10 seconds
        isRefreshing: false,
        nonce: '<?php echo wp_create_nonce('tlat_live_activity_nonce'); ?>',
        ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>',
        container: $('#tlat-activity-feed'),
        countElement: $('#activity-count'),
        lastRefreshElement: $('#tlat-last-refresh'),
        uniqueStudentsElement: $('#unique-students'),
        
        init: function() {
            this.bindEvents();
            this.startAutoRefresh();
            this.calculateUniqueStudents();
        },
        
        bindEvents: function() {
            var self = this;
            
            // Manual refresh button
            $('#tlat-refresh-feed').on('click', function() {
                self.refreshFeed();
            });
            
            // Clear feed button
            $('#tlat-clear-feed').on('click', function() {
                if (confirm('<?php _e('Are you sure you want to clear all activities?', 'tutor-lms-advanced-tracking'); ?>')) {
                    self.clearFeed();
                }
            });
        },
        
        startAutoRefresh: function() {
            var self = this;
            setInterval(function() {
                self.refreshFeed();
            }, this.refreshInterval);
        },
        
        refreshFeed: function() {
            if (this.isRefreshing) return;
            
            this.isRefreshing = true;
            var self = this;
            
            $.ajax({
                url: this.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'tlat_get_live_activities',
                    nonce: this.nonce,
                    limit: 50
                },
                success: function(response) {
                    if (response.success) {
                        self.updateFeed(response.data.activities);
                        self.updateStats(response.data);
                        self.updateLastRefresh();
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error fetching activities:', error);
                },
                complete: function() {
                    self.isRefreshing = false;
                }
            });
        },
        
        updateFeed: function(activities) {
            var container = this.container;
            var html = '';
            
            if (!activities || activities.length === 0) {
                html = '<div class="tlat-no-activities">' +
                    '<span class="dashicons dashicons-info"></span>' +
                    '<p><?php _e('No activities recorded yet. Student activities will appear here as they happen.', 'tutor-lms-advanced-tracking'); ?></p>' +
                    '</div>';
            } else {
                $.each(activities, function(index, activity) {
                    var detailsHtml = '';
                    
                    if (activity.course_name) {
                        detailsHtml += '<span class="tlat-course-name">' +
                            '<span class="dashicons dashicons-book"></span>' +
                            activity.course_name + '</span>';
                    }
                    
                    if (activity.lesson_name) {
                        detailsHtml += '<span class="tlat-lesson-name">' +
                            '<span class="dashicons dashicons-media-document"></span>' +
                            activity.lesson_name + '</span>';
                    }
                    
                    html += '<div class="tlat-activity-item" data-id="' + activity.id + '">' +
                        '<div class="tlat-activity-icon ' + activity.action_icon + '">' +
                        '<span class="dashicons ' + activity.action_icon + '"></span>' +
                        '</div>' +
                        '<div class="tlat-activity-content">' +
                        '<div class="tlat-activity-header">' +
                        '<span class="tlat-student-name">' + activity.student_name + '</span>' +
                        '<span class="tlat-activity-action">' + activity.action_label + '</span>' +
                        '</div>' +
                        '<div class="tlat-activity-details">' + detailsHtml + '</div>' +
                        '<div class="tlat-activity-time">' +
                        '<span class="dashicons dashicons-clock"></span>' +
                        activity.timestamp_formatted + '</div>' +
                        '</div></div>';
                });
            }
            
            container.html(html);
        },
        
        updateStats: function(data) {
            if (data.count !== undefined) {
                this.countElement.text(data.count);
            }
            this.calculateUniqueStudents();
        },
        
        calculateUniqueStudents: function() {
            var students = {};
            this.container.find('.tlat-student-name').each(function() {
                students[$(this).text()] = true;
            });
            this.uniqueStudentsElement.text(Object.keys(students).length);
        },
        
        updateLastRefresh: function() {
            var now = new Date();
            var timeString = now.toLocaleTimeString();
            this.lastRefreshElement.html('<?php _e('Last refreshed:', 'tutor-lms-advanced-tracking'); ?> ' + timeString);
        },
        
        clearFeed: function() {
            var self = this;
            
            $.ajax({
                url: this.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'tlat_clear_activities',
                    nonce: this.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.updateFeed([]);
                        self.countElement.text('0');
                    }
                }
            });
        }
    };
    
    // Initialize
    TlatLiveActivity.init();
});
</script>
