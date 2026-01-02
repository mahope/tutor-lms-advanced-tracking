<?php
/**
 * Admin Dashboard Template
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$courses = $dashboard->get_courses();
$total_students = array_sum(array_column($courses, 'student_count'));
$avg_completion = $courses ? round(array_sum(array_column($courses, 'completion_rate')) / count($courses), 1) : 0;
$avg_quiz_score = $courses ? round(array_sum(array_column($courses, 'avg_quiz_score')) / count($courses), 1) : 0;
?>

<div class="tutor-advanced-admin wrap">
    <div class="tutor-admin-header">
        <div>
            <h1>
                <?php _e('Advanced Tutor LMS Statistics', 'tutor-lms-advanced-tracking'); ?>
                <span class="version">v<?php echo TUTOR_ADVANCED_TRACKING_VERSION; ?></span>
            </h1>
            <p><?php _e('Comprehensive analytics and insights for your Tutor LMS courses', 'tutor-lms-advanced-tracking'); ?></p>
        </div>
        <div class="tutor-admin-actions">
            <button class="tutor-btn tutor-btn-secondary refresh-data-btn" data-type="all">
                <span class="dashicons dashicons-update"></span>
                <?php _e('Refresh Data', 'tutor-lms-advanced-tracking'); ?>
            </button>
            <a href="<?php echo admin_url('admin.php?page=tutor-advanced-stats-settings'); ?>" class="tutor-btn tutor-btn-primary">
                <span class="dashicons dashicons-admin-settings"></span>
                <?php _e('Settings', 'tutor-lms-advanced-tracking'); ?>
            </a>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="stats-grid">
        <div class="stat-item" data-stat="courses">
            <div class="stat-number"><?php echo count($courses); ?></div>
            <div class="stat-label"><?php _e('Total Courses', 'tutor-lms-advanced-tracking'); ?></div>
        </div>
        <div class="stat-item" data-stat="students">
            <div class="stat-number"><?php echo $total_students; ?></div>
            <div class="stat-label"><?php _e('Total Students', 'tutor-lms-advanced-tracking'); ?></div>
        </div>
        <div class="stat-item" data-stat="completion">
            <div class="stat-number"><?php echo $avg_completion; ?>%</div>
            <div class="stat-label"><?php _e('Avg. Completion', 'tutor-lms-advanced-tracking'); ?></div>
        </div>
        <div class="stat-item" data-stat="quiz-score">
            <div class="stat-number"><?php echo $avg_quiz_score; ?>%</div>
            <div class="stat-label"><?php _e('Avg. Quiz Score', 'tutor-lms-advanced-tracking'); ?></div>
        </div>
    </div>

    <!-- Dashboard Cards -->
    <div class="tutor-admin-dashboard">
        <!-- Recent Activity Card -->
        <div class="tutor-admin-card">
            <h3>
                <span class="dashicons dashicons-clock"></span>
                <?php _e('Recent Activity', 'tutor-lms-advanced-tracking'); ?>
            </h3>
            <div class="card-content">
                <p><?php _e('Last updated:', 'tutor-lms-advanced-tracking'); ?> 
                   <strong><?php echo current_time('M j, Y g:i A'); ?></strong></p>
                
                <?php if (!empty($courses)): ?>
                    <div class="recent-courses">
                        <h4><?php _e('Most Active Courses', 'tutor-lms-advanced-tracking'); ?></h4>
                        <?php 
                        $active_courses = array_slice(
                            array_filter($courses, function($c) { return $c['student_count'] > 0; }), 
                            0, 3
                        ); 
                        ?>
                        <?php foreach ($active_courses as $course): ?>
                            <div class="course-activity-item">
                                <strong><?php echo esc_html($course['title']); ?></strong>
                                <span class="activity-stats">
                                    <?php echo $course['student_count']; ?> students, 
                                    <?php echo $course['avg_progression']; ?>% progress
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="card-actions">
                <a href="<?php echo admin_url('admin.php?page=tutor-advanced-stats-debug'); ?>" class="tutor-btn tutor-btn-secondary">
                    <?php _e('View Details', 'tutor-lms-advanced-tracking'); ?>
                </a>
            </div>
        </div>

        <!-- System Health Card -->
        <div class="tutor-admin-card">
            <h3>
                <span class="dashicons dashicons-performance"></span>
                <?php _e('System Health', 'tutor-lms-advanced-tracking'); ?>
            </h3>
            <div class="card-content">
                <?php
                $tutor_active = function_exists('tutor');
                $cache_working = TutorAdvancedTracking_Cache::get('test_key') !== false || 
                                wp_cache_get('test_key', 'tutor_advanced_tracking') !== false;
                $tables_exist = TutorAdvancedTracking_TutorIntegration::table_exists($GLOBALS['wpdb']->prefix . 'tutor_enrollments');
                ?>
                
                <div class="health-check">
                    <div class="health-item">
                        <span class="status <?php echo $tutor_active ? 'status-ok' : 'status-error'; ?>">
                            <?php echo $tutor_active ? '✓' : '✕'; ?>
                        </span>
                        <?php _e('Tutor LMS Active', 'tutor-lms-advanced-tracking'); ?>
                    </div>
                    <div class="health-item">
                        <span class="status <?php echo $tables_exist ? 'status-ok' : 'status-warning'; ?>">
                            <?php echo $tables_exist ? '✓' : '⚠'; ?>
                        </span>
                        <?php _e('Database Tables', 'tutor-lms-advanced-tracking'); ?>
                    </div>
                    <div class="health-item">
                        <span class="status status-ok">✓</span>
                        <?php _e('Plugin Active', 'tutor-lms-advanced-tracking'); ?>
                    </div>
                </div>
            </div>
            <div class="card-actions">
                <a href="<?php echo admin_url('admin.php?page=tutor-advanced-stats-system'); ?>" class="tutor-btn tutor-btn-secondary">
                    <?php _e('System Info', 'tutor-lms-advanced-tracking'); ?>
                </a>
            </div>
        </div>

        <!-- Quick Actions Card -->
        <div class="tutor-admin-card">
            <h3>
                <span class="dashicons dashicons-admin-tools"></span>
                <?php _e('Quick Actions', 'tutor-lms-advanced-tracking'); ?>
            </h3>
            <div class="card-content">
                <div class="quick-actions">
                    <button class="tutor-btn tutor-btn-secondary cache-action-btn" data-action="clear_all">
                        <span class="dashicons dashicons-update"></span>
                        <?php _e('Clear All Cache', 'tutor-lms-advanced-tracking'); ?>
                    </button>
                    <button class="tutor-btn tutor-btn-secondary debug-action-btn" data-action="run_data_diagnostic" data-output="diagnostic-output">
                        <span class="dashicons dashicons-search"></span>
                        <?php _e('Run Diagnostics', 'tutor-lms-advanced-tracking'); ?>
                    </button>
                    <button class="tutor-btn tutor-btn-secondary export-btn" data-type="courses" data-format="csv">
                        <span class="dashicons dashicons-download"></span>
                        <?php _e('Export Data', 'tutor-lms-advanced-tracking'); ?>
                    </button>
                </div>
                <div id="diagnostic-output" class="debug-output"></div>
            </div>
            <div class="card-actions">
                <a href="<?php echo admin_url('admin.php?page=tutor-advanced-stats-debug'); ?>" class="tutor-btn tutor-btn-primary">
                    <?php _e('Debug Tools', 'tutor-lms-advanced-tracking'); ?>
                </a>
            </div>
        </div>
    </div>

    <!-- Courses Table -->
    <?php if (!empty($courses)): ?>
        <div class="tutor-admin-card">
            <h3>
                <span class="dashicons dashicons-book"></span>
                <?php _e('Course Overview', 'tutor-lms-advanced-tracking'); ?>
                <span class="course-count">(<?php echo count($courses); ?>)</span>
            </h3>
            <div class="card-content">
                <div class="courses-table">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Course', 'tutor-lms-advanced-tracking'); ?></th>
                                <th><?php _e('Instructor', 'tutor-lms-advanced-tracking'); ?></th>
                                <th><?php _e('Students', 'tutor-lms-advanced-tracking'); ?></th>
                                <th><?php _e('Progress', 'tutor-lms-advanced-tracking'); ?></th>
                                <th><?php _e('Quiz Score', 'tutor-lms-advanced-tracking'); ?></th>
                                <th><?php _e('Status', 'tutor-lms-advanced-tracking'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($courses as $course): ?>
                                <tr>
                                    <td>
                                        <strong>
                                            <a href="<?php echo get_edit_post_link($course['id']); ?>">
                                                <?php echo esc_html($course['title']); ?>
                                            </a>
                                        </strong>
                                    </td>
                                    <td><?php echo esc_html($course['instructor']); ?></td>
                                    <td>
                                        <span class="student-count"><?php echo $course['student_count']; ?></span>
                                        <?php if ($course['completed_students'] > 0): ?>
                                            <small>(<?php echo $course['completed_students']; ?> completed)</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="tutor-progress">
                                            <div class="tutor-progress-fill" style="width: <?php echo $course['avg_progression']; ?>%"></div>
                                        </div>
                                        <small><?php echo $course['avg_progression']; ?>%</small>
                                    </td>
                                    <td>
                                        <span class="quiz-score <?php echo $course['avg_quiz_score'] >= 70 ? 'passing' : 'failing'; ?>">
                                            <?php echo $course['avg_quiz_score']; ?>%
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $course['status']; ?>">
                                            <?php echo ucfirst($course['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="tutor-admin-card">
            <h3><?php _e('No Courses Found', 'tutor-lms-advanced-tracking'); ?></h3>
            <div class="card-content">
                <p><?php _e('No courses found. This could be because:', 'tutor-lms-advanced-tracking'); ?></p>
                <ul>
                    <li><?php _e('No courses have been created yet', 'tutor-lms-advanced-tracking'); ?></li>
                    <li><?php _e('Tutor LMS is not properly configured', 'tutor-lms-advanced-tracking'); ?></li>
                    <li><?php _e('Database connection issues', 'tutor-lms-advanced-tracking'); ?></li>
                </ul>
            </div>
            <div class="card-actions">
                <a href="<?php echo admin_url('admin.php?page=tutor-advanced-stats-debug'); ?>" class="tutor-btn tutor-btn-primary">
                    <?php _e('Run Diagnostics', 'tutor-lms-advanced-tracking'); ?>
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.health-check {
    margin: 15px 0;
}

.health-item {
    display: flex;
    align-items: center;
    margin: 8px 0;
    font-size: 14px;
}

.health-item .status {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 10px;
    font-size: 12px;
    font-weight: bold;
}

.recent-courses h4 {
    margin: 15px 0 10px 0;
    color: #23282d;
    font-size: 14px;
}

.course-activity-item {
    padding: 8px 0;
    border-bottom: 1px solid #f0f0f1;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.course-activity-item:last-child {
    border-bottom: none;
}

.activity-stats {
    font-size: 12px;
    color: #666;
}

.quick-actions {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-bottom: 15px;
}

.quick-actions .tutor-btn {
    justify-content: flex-start;
    text-align: left;
}

.course-count {
    color: #666;
    font-weight: normal;
    font-size: 14px;
}

.student-count {
    font-weight: 600;
}

.status-badge {
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.status-publish {
    background: #d4edda;
    color: #155724;
}

.status-draft {
    background: #f8d7da;
    color: #721c24;
}

.quiz-score.passing {
    color: #155724;
    font-weight: 600;
}

.quiz-score.failing {
    color: #721c24;
    font-weight: 600;
}
</style>