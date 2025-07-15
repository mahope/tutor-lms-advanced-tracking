<?php
/**
 * Dashboard template
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$courses = $dashboard->get_courses();
?>

<div class="tutor-advanced-tracking-dashboard">
    <div class="dashboard-header">
        <h2><?php _e('Advanced Tutor LMS Statistics', 'tutor-lms-advanced-tracking'); ?></h2>
        
        <div class="dashboard-search">
            <input type="text" id="dashboard-search" placeholder="<?php _e('Search courses or users...', 'tutor-lms-advanced-tracking'); ?>">
            <div class="search-filters">
                <select id="search-type">
                    <option value="all"><?php _e('All', 'tutor-lms-advanced-tracking'); ?></option>
                    <option value="courses"><?php _e('Courses', 'tutor-lms-advanced-tracking'); ?></option>
                    <option value="users"><?php _e('Users', 'tutor-lms-advanced-tracking'); ?></option>
                </select>
            </div>
        </div>
    </div>

    <div class="dashboard-stats-overview">
        <div class="stats-card">
            <h3><?php _e('Total Courses', 'tutor-lms-advanced-tracking'); ?></h3>
            <div class="stats-number"><?php echo count($courses); ?></div>
        </div>
        
        <div class="stats-card">
            <h3><?php _e('Total Students', 'tutor-lms-advanced-tracking'); ?></h3>
            <div class="stats-number"><?php echo array_sum(array_column($courses, 'student_count')); ?></div>
        </div>
        
        <div class="stats-card">
            <h3><?php _e('Average Progression', 'tutor-lms-advanced-tracking'); ?></h3>
            <div class="stats-number"><?php echo $courses ? round(array_sum(array_column($courses, 'avg_progression')) / count($courses), 1) : 0; ?>%</div>
        </div>
        
        <div class="stats-card">
            <h3><?php _e('Average Quiz Score', 'tutor-lms-advanced-tracking'); ?></h3>
            <div class="stats-number"><?php echo $courses ? round(array_sum(array_column($courses, 'avg_quiz_score')) / count($courses), 1) : 0; ?>%</div>
        </div>
    </div>

    <div class="dashboard-courses">
        <h3><?php _e('Course Overview', 'tutor-lms-advanced-tracking'); ?></h3>
        
        <?php if (empty($courses)): ?>
            <div class="no-courses">
                <p><?php _e('No courses found.', 'tutor-lms-advanced-tracking'); ?></p>
            </div>
        <?php else: ?>
            <div class="courses-table">
                <table>
                    <thead>
                        <tr>
                            <th><?php _e('Course', 'tutor-lms-advanced-tracking'); ?></th>
                            <th><?php _e('Instructor', 'tutor-lms-advanced-tracking'); ?></th>
                            <th><?php _e('Students', 'tutor-lms-advanced-tracking'); ?></th>
                            <th><?php _e('Avg. Progression', 'tutor-lms-advanced-tracking'); ?></th>
                            <th><?php _e('Avg. Quiz Score', 'tutor-lms-advanced-tracking'); ?></th>
                            <th><?php _e('Actions', 'tutor-lms-advanced-tracking'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($courses as $course): ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($course['title']); ?></strong>
                                </td>
                                <td><?php echo esc_html($course['instructor']); ?></td>
                                <td><?php echo $course['student_count']; ?></td>
                                <td>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo $course['avg_progression']; ?>%"></div>
                                        <span><?php echo $course['avg_progression']; ?>%</span>
                                    </div>
                                </td>
                                <td>
                                    <span class="quiz-score <?php echo $course['avg_quiz_score'] >= 70 ? 'passing' : 'failing'; ?>">
                                        <?php echo $course['avg_quiz_score']; ?>%
                                    </span>
                                </td>
                                <td>
                                    <a href="<?php echo add_query_arg(array('view' => 'course', 'course_id' => $course['id']), get_permalink()); ?>" 
                                       class="btn btn-primary">
                                        <?php _e('View Details', 'tutor-lms-advanced-tracking'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="search-results" id="search-results" style="display: none;">
        <h3><?php _e('Search Results', 'tutor-lms-advanced-tracking'); ?></h3>
        <div class="search-results-content"></div>
    </div>
</div>