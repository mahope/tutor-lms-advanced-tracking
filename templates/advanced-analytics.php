<?php
/**
 * Advanced Analytics template
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

if (!isset($analytics_data) || !$analytics_data) {
    return;
}

$analytics = $analytics_data;
?>

<div class="tutor-advanced-analytics">
    <div class="analytics-header">
        <div class="breadcrumb">
            <a href="<?php echo esc_url(get_permalink()); ?>"><?php _e('Dashboard', 'tutor-lms-advanced-tracking'); ?></a>
            <span class="separator">/</span>
            <a href="<?php echo esc_url(add_query_arg(array('view' => 'course', 'course_id' => intval($_GET['course_id'])), get_permalink())); ?>"><?php _e('Course Details', 'tutor-lms-advanced-tracking'); ?></a>
            <span class="separator">/</span>
            <span><?php _e('Advanced Analytics', 'tutor-lms-advanced-tracking'); ?></span>
        </div>
        
        <h2><?php _e('Advanced Course Analytics', 'tutor-lms-advanced-tracking'); ?></h2>
        <div class="analytics-nav">
            <ul>
                <li><a href="#completion-funnel" class="nav-link active"><?php _e('Completion Funnel', 'tutor-lms-advanced-tracking'); ?></a></li>
                <li><a href="#engagement-metrics" class="nav-link"><?php _e('Engagement', 'tutor-lms-advanced-tracking'); ?></a></li>
                <li><a href="#time-analytics" class="nav-link"><?php _e('Time Analytics', 'tutor-lms-advanced-tracking'); ?></a></li>
                <li><a href="#difficulty-analysis" class="nav-link"><?php _e('Difficulty Analysis', 'tutor-lms-advanced-tracking'); ?></a></li>
                <li><a href="#predictive-metrics" class="nav-link"><?php _e('Predictive Analytics', 'tutor-lms-advanced-tracking'); ?></a></li>
            </ul>
        </div>
    </div>

    <!-- Completion Funnel -->
    <div id="completion-funnel" class="analytics-section">
        <h3><?php _e('Course Completion Funnel', 'tutor-lms-advanced-tracking'); ?></h3>
        <div class="funnel-container">
            <?php foreach ($analytics['completion_funnel'] as $step): ?>
                <div class="funnel-step">
                    <div class="funnel-step-header">
                        <h4><?php echo esc_html($step['step']); ?></h4>
                        <span class="funnel-percentage"><?php echo esc_html($step['percentage']); ?>%</span>
                    </div>
                    <div class="funnel-bar">
                        <div class="funnel-fill" style="width: <?php echo esc_attr($step['percentage']); ?>%"></div>
                    </div>
                    <div class="funnel-stats">
                        <span class="count"><?php echo esc_html($step['count']); ?> <?php _e('students', 'tutor-lms-advanced-tracking'); ?></span>
                        <?php if ($step['drop_rate'] > 0): ?>
                            <span class="drop-rate"><?php echo esc_html($step['drop_rate']); ?>% <?php _e('drop-off', 'tutor-lms-advanced-tracking'); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Engagement Metrics -->
    <div id="engagement-metrics" class="analytics-section">
        <h3><?php _e('Student Engagement Metrics', 'tutor-lms-advanced-tracking'); ?></h3>
        <div class="engagement-grid">
            <div class="engagement-card">
                <div class="engagement-score-circle">
                    <div class="score-value"><?php echo esc_html($analytics['engagement_metrics']['engagement_score']); ?></div>
                    <div class="score-label"><?php _e('Engagement Score', 'tutor-lms-advanced-tracking'); ?></div>
                </div>
                <div class="engagement-level <?php echo esc_attr(strtolower($analytics['engagement_metrics']['engagement_level'])); ?>">
                    <?php echo esc_html($analytics['engagement_metrics']['engagement_level']); ?>
                </div>
            </div>
            
            <div class="engagement-card">
                <h4><?php _e('Session Duration', 'tutor-lms-advanced-tracking'); ?></h4>
                <div class="metric-value"><?php echo esc_html($analytics['engagement_metrics']['avg_session_duration']); ?> <?php _e('minutes', 'tutor-lms-advanced-tracking'); ?></div>
            </div>
            
            <div class="engagement-card">
                <h4><?php _e('Retry Rate', 'tutor-lms-advanced-tracking'); ?></h4>
                <div class="metric-value"><?php echo esc_html($analytics['engagement_metrics']['retry_percentage']); ?>%</div>
            </div>
        </div>
    </div>

    <!-- Time Analytics -->
    <div id="time-analytics" class="analytics-section">
        <h3><?php _e('Time-Based Analytics', 'tutor-lms-advanced-tracking'); ?></h3>
        
        <div class="time-analytics-grid">
            <div class="time-card">
                <h4><?php _e('Peak Learning Day', 'tutor-lms-advanced-tracking'); ?></h4>
                <div class="peak-info">
                    <span class="peak-value"><?php echo esc_html($analytics['time_analytics']['peak_day']); ?></span>
                </div>
            </div>
            
            <div class="time-card">
                <h4><?php _e('Peak Learning Hour', 'tutor-lms-advanced-tracking'); ?></h4>
                <div class="peak-info">
                    <span class="peak-value"><?php echo esc_html($analytics['time_analytics']['peak_hour']); ?>:00</span>
                </div>
            </div>
            
            <div class="time-card">
                <h4><?php _e('Average Completion Time', 'tutor-lms-advanced-tracking'); ?></h4>
                <div class="peak-info">
                    <span class="peak-value"><?php echo esc_html($analytics['time_analytics']['avg_completion_time']); ?> <?php _e('days', 'tutor-lms-advanced-tracking'); ?></span>
                </div>
            </div>
        </div>

        <div class="time-charts">
            <div class="chart-container">
                <h4><?php _e('Daily Activity Pattern', 'tutor-lms-advanced-tracking'); ?></h4>
                <div class="daily-chart">
                    <?php foreach ($analytics['time_analytics']['daily_activity'] as $day): ?>
                        <div class="day-bar">
                            <div class="bar-fill" style="height: <?php echo esc_attr(($day->activity_count / max(1, max(array_column($analytics['time_analytics']['daily_activity'], 'activity_count')))) * 100); ?>%"></div>
                            <div class="day-label"><?php echo esc_html(substr($day->day_name, 0, 3)); ?></div>
                            <div class="day-count"><?php echo esc_html($day->activity_count); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="chart-container">
                <h4><?php _e('Hourly Activity Pattern', 'tutor-lms-advanced-tracking'); ?></h4>
                <div class="hourly-chart">
                    <?php foreach ($analytics['time_analytics']['hourly_activity'] as $hour): ?>
                        <div class="hour-bar">
                            <div class="bar-fill" style="height: <?php echo esc_attr(($hour->activity_count / max(1, max(array_column($analytics['time_analytics']['hourly_activity'], 'activity_count')))) * 100); ?>%"></div>
                            <div class="hour-label"><?php echo esc_html($hour->hour); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Difficulty Analysis -->
    <div id="difficulty-analysis" class="analytics-section">
        <h3><?php _e('Quiz Difficulty Analysis', 'tutor-lms-advanced-tracking'); ?></h3>
        <div class="difficulty-table">
            <table>
                <thead>
                    <tr>
                        <th><?php _e('Quiz', 'tutor-lms-advanced-tracking'); ?></th>
                        <th><?php _e('Average Score', 'tutor-lms-advanced-tracking'); ?></th>
                        <th><?php _e('Retry Rate', 'tutor-lms-advanced-tracking'); ?></th>
                        <th><?php _e('Avg Duration', 'tutor-lms-advanced-tracking'); ?></th>
                        <th><?php _e('Difficulty Level', 'tutor-lms-advanced-tracking'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($analytics['difficulty_analysis'] as $quiz): ?>
                        <tr>
                            <td><?php echo esc_html($quiz['title']); ?></td>
                            <td>
                                <span class="score-badge <?php echo esc_attr($quiz['avg_score'] >= 70 ? 'passing' : 'failing'); ?>">
                                    <?php echo esc_html($quiz['avg_score']); ?>%
                                </span>
                            </td>
                            <td><?php echo esc_html($quiz['retry_rate']); ?>x</td>
                            <td><?php echo esc_html($quiz['avg_duration']); ?> <?php _e('min', 'tutor-lms-advanced-tracking'); ?></td>
                            <td>
                                <span class="difficulty-badge <?php echo esc_attr(strtolower(str_replace(' ', '-', $quiz['difficulty_level']))); ?>">
                                    <?php echo esc_html($quiz['difficulty_level']); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Dropout Patterns -->
    <div id="dropout-patterns" class="analytics-section">
        <h3><?php _e('Student Dropout Patterns', 'tutor-lms-advanced-tracking'); ?></h3>
        <div class="dropout-grid">
            <div class="dropout-card">
                <h4><?php _e('Never Started', 'tutor-lms-advanced-tracking'); ?></h4>
                <div class="dropout-value"><?php echo esc_html($analytics['dropout_patterns']['never_started']); ?></div>
                <div class="dropout-label"><?php _e('students', 'tutor-lms-advanced-tracking'); ?></div>
            </div>
            
            <div class="dropout-card">
                <h4><?php _e('Started but Incomplete', 'tutor-lms-advanced-tracking'); ?></h4>
                <div class="dropout-value"><?php echo esc_html($analytics['dropout_patterns']['started_not_completed']); ?></div>
                <div class="dropout-label"><?php _e('students', 'tutor-lms-advanced-tracking'); ?></div>
            </div>
            
            <div class="dropout-card">
                <h4><?php _e('Early Dropouts', 'tutor-lms-advanced-tracking'); ?></h4>
                <div class="dropout-value"><?php echo esc_html($analytics['dropout_patterns']['early_dropouts']); ?></div>
                <div class="dropout-label"><?php _e('students', 'tutor-lms-advanced-tracking'); ?></div>
            </div>
            
            <div class="dropout-card total">
                <h4><?php _e('Total Dropout Rate', 'tutor-lms-advanced-tracking'); ?></h4>
                <div class="dropout-value"><?php echo esc_html($analytics['dropout_patterns']['dropout_rate']); ?>%</div>
                <div class="dropout-label"><?php _e('of enrolled students', 'tutor-lms-advanced-tracking'); ?></div>
            </div>
        </div>
        
        <div class="retention-strategies">
            <h4><?php _e('Retention Strategies', 'tutor-lms-advanced-tracking'); ?></h4>
            <ul>
                <?php foreach ($analytics['dropout_patterns']['retention_strategies'] as $strategy): ?>
                    <li><?php echo esc_html($strategy); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <!-- Predictive Analytics -->
    <div id="predictive-metrics" class="analytics-section">
        <h3><?php _e('Predictive Analytics', 'tutor-lms-advanced-tracking'); ?></h3>
        
        <div class="predictive-overview">
            <div class="risk-distribution">
                <h4><?php _e('Risk Distribution', 'tutor-lms-advanced-tracking'); ?></h4>
                <div class="risk-bars">
                    <div class="risk-bar high-risk">
                        <span class="risk-label"><?php _e('High Risk', 'tutor-lms-advanced-tracking'); ?></span>
                        <div class="risk-count"><?php echo esc_html($analytics['predictive_metrics']['risk_distribution']['high']); ?></div>
                    </div>
                    <div class="risk-bar medium-risk">
                        <span class="risk-label"><?php _e('Medium Risk', 'tutor-lms-advanced-tracking'); ?></span>
                        <div class="risk-count"><?php echo esc_html($analytics['predictive_metrics']['risk_distribution']['medium']); ?></div>
                    </div>
                    <div class="risk-bar low-risk">
                        <span class="risk-label"><?php _e('Low Risk', 'tutor-lms-advanced-tracking'); ?></span>
                        <div class="risk-count"><?php echo esc_html($analytics['predictive_metrics']['risk_distribution']['low']); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="at-risk-students">
            <h4><?php _e('At-Risk Students', 'tutor-lms-advanced-tracking'); ?></h4>
            <?php if (empty($analytics['predictive_metrics']['at_risk_students'])): ?>
                <div class="no-risk-students">
                    <p><?php _e('No at-risk students identified at this time.', 'tutor-lms-advanced-tracking'); ?></p>
                </div>
            <?php else: ?>
                <div class="risk-students-table">
                    <table>
                        <thead>
                            <tr>
                                <th><?php _e('Student', 'tutor-lms-advanced-tracking'); ?></th>
                                <th><?php _e('Risk Score', 'tutor-lms-advanced-tracking'); ?></th>
                                <th><?php _e('Completion Probability', 'tutor-lms-advanced-tracking'); ?></th>
                                <th><?php _e('Recommended Actions', 'tutor-lms-advanced-tracking'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($analytics['predictive_metrics']['at_risk_students'], 0, 10) as $student): ?>
                                <tr>
                                    <td>
                                        <div class="student-info">
                                            <strong><?php echo esc_html($student['name']); ?></strong>
                                            <br>
                                            <small><?php echo esc_html($student['email']); ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="risk-score <?php echo esc_attr($student['risk_score'] > 70 ? 'high' : ($student['risk_score'] > 40 ? 'medium' : 'low')); ?>">
                                            <?php echo esc_html($student['risk_score']); ?>%
                                        </span>
                                    </td>
                                    <td>
                                        <span class="completion-probability">
                                            <?php echo esc_html($student['completion_probability']); ?>%
                                        </span>
                                    </td>
                                    <td>
                                        <div class="recommended-actions">
                                            <?php foreach ($student['recommended_actions'] as $action): ?>
                                                <span class="action-item"><?php echo esc_html($action); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="intervention-priorities">
            <h4><?php _e('Intervention Priorities', 'tutor-lms-advanced-tracking'); ?></h4>
            <?php if (empty($analytics['predictive_metrics']['intervention_priorities'])): ?>
                <div class="no-interventions">
                    <p><?php _e('No immediate interventions required.', 'tutor-lms-advanced-tracking'); ?></p>
                </div>
            <?php else: ?>
                <div class="priorities-list">
                    <?php foreach ($analytics['predictive_metrics']['intervention_priorities'] as $priority): ?>
                        <div class="priority-item <?php echo esc_attr(strtolower($priority['priority'])); ?>">
                            <div class="priority-badge"><?php echo esc_html($priority['priority']); ?></div>
                            <div class="priority-content">
                                <strong><?php echo esc_html($priority['student']); ?></strong>
                                <p><?php echo esc_html($priority['action']); ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Student Cohorts -->
    <div id="student-cohorts" class="analytics-section">
        <h3><?php _e('Student Cohort Analysis', 'tutor-lms-advanced-tracking'); ?></h3>
        <div class="cohorts-table">
            <table>
                <thead>
                    <tr>
                        <th><?php _e('Cohort Month', 'tutor-lms-advanced-tracking'); ?></th>
                        <th><?php _e('Total Students', 'tutor-lms-advanced-tracking'); ?></th>
                        <th><?php _e('Completed', 'tutor-lms-advanced-tracking'); ?></th>
                        <th><?php _e('Completion Rate', 'tutor-lms-advanced-tracking'); ?></th>
                        <th><?php _e('Avg. Days to Complete', 'tutor-lms-advanced-tracking'); ?></th>
                        <th><?php _e('Trend', 'tutor-lms-advanced-tracking'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($analytics['student_cohorts'] as $cohort): ?>
                        <tr>
                            <td><?php echo esc_html($cohort['month']); ?></td>
                            <td><?php echo esc_html($cohort['total_students']); ?></td>
                            <td><?php echo esc_html($cohort['completed_students']); ?></td>
                            <td>
                                <span class="completion-rate <?php echo esc_attr($cohort['completion_rate'] >= 70 ? 'good' : ($cohort['completion_rate'] >= 50 ? 'average' : 'poor')); ?>">
                                    <?php echo esc_html($cohort['completion_rate']); ?>%
                                </span>
                            </td>
                            <td><?php echo esc_html($cohort['avg_completion_days']); ?></td>
                            <td>
                                <span class="trend <?php echo esc_attr($cohort['performance_trend']); ?>">
                                    <?php echo esc_html(ucfirst($cohort['performance_trend'])); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Export Options -->
    <div class="analytics-export">
        <h3><?php _e('Export Options', 'tutor-lms-advanced-tracking'); ?></h3>
        <div class="export-buttons">
            <button class="btn btn-primary" onclick="exportToPDF()"><?php _e('Export as PDF', 'tutor-lms-advanced-tracking'); ?></button>
            <button class="btn btn-secondary" onclick="exportToCSV()"><?php _e('Export as CSV', 'tutor-lms-advanced-tracking'); ?></button>
            <button class="btn btn-secondary" onclick="exportToExcel()"><?php _e('Export as Excel', 'tutor-lms-advanced-tracking'); ?></button>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Navigation functionality
    $('.nav-link').on('click', function(e) {
        e.preventDefault();
        
        // Remove active class from all nav links
        $('.nav-link').removeClass('active');
        
        // Add active class to clicked link
        $(this).addClass('active');
        
        // Get target section
        var target = $(this).attr('href');
        
        // Scroll to section
        $('html, body').animate({
            scrollTop: $(target).offset().top - 20
        }, 500);
    });
    
    // Smooth scrolling for internal links
    $('a[href^="#"]').on('click', function(e) {
        e.preventDefault();
        var target = $(this.getAttribute('href'));
        if (target.length) {
            $('html, body').animate({
                scrollTop: target.offset().top - 20
            }, 500);
        }
    });
});

// Export functions (placeholders)
function exportToPDF() {
    alert('PDF export functionality would be implemented here');
}

function exportToCSV() {
    alert('CSV export functionality would be implemented here');
}

function exportToExcel() {
    alert('Excel export functionality would be implemented here');
}
</script>