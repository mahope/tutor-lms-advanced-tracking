<?php
/**
 * Course details template
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

if (!isset($course_data) || !$course_data) {
    return;
}
?>

<div class="tutor-advanced-tracking-course-details">
    <div class="course-header">
        <div class="breadcrumb">
            <a href="<?php echo get_permalink(); ?>"><?php _e('Dashboard', 'tutor-lms-advanced-tracking'); ?></a>
            <span class="separator">/</span>
            <span><?php _e('Course Details', 'tutor-lms-advanced-tracking'); ?></span>
        </div>
        
        <h2><?php echo esc_html($course_data['title']); ?></h2>
        <p class="course-instructor"><?php _e('Instructor:', 'tutor-lms-advanced-tracking'); ?> <?php echo esc_html($course_data['instructor']); ?></p>
    </div>

    <div class="course-stats-overview">
        <div class="stats-card">
            <h3><?php _e('Total Students', 'tutor-lms-advanced-tracking'); ?></h3>
            <div class="stats-number"><?php echo esc_html($course_data['stats']['total_students']); ?></div>
        </div>
        
        <div class="stats-card">
            <h3><?php _e('Completed', 'tutor-lms-advanced-tracking'); ?></h3>
            <div class="stats-number"><?php echo esc_html($course_data['stats']['completed_students']); ?></div>
        </div>
        
        <div class="stats-card">
            <h3><?php _e('Completion Rate', 'tutor-lms-advanced-tracking'); ?></h3>
            <div class="stats-number"><?php echo esc_html($course_data['stats']['completion_rate']); ?>%</div>
        </div>
        
        <div class="stats-card">
            <h3><?php _e('Avg. Completion Time', 'tutor-lms-advanced-tracking'); ?></h3>
            <div class="stats-number"><?php echo esc_html($course_data['stats']['avg_completion_time']); ?> <?php _e('days', 'tutor-lms-advanced-tracking'); ?></div>
        </div>
    </div>

    <div class="course-content">
        <div class="course-students">
            <h3><?php _e('Student Performance', 'tutor-lms-advanced-tracking'); ?></h3>
            
            <?php if (empty($course_data['students'])): ?>
                <div class="no-students">
                    <p><?php _e('No students enrolled in this course.', 'tutor-lms-advanced-tracking'); ?></p>
                </div>
            <?php else: ?>
                <div class="students-table">
                    <table>
                        <thead>
                            <tr>
                                <th><?php _e('Student', 'tutor-lms-advanced-tracking'); ?></th>
                                <th><?php _e('Progression', 'tutor-lms-advanced-tracking'); ?></th>
                                <th><?php _e('Quiz Average', 'tutor-lms-advanced-tracking'); ?></th>
                                <th><?php _e('Last Activity', 'tutor-lms-advanced-tracking'); ?></th>
                                <th><?php _e('Status', 'tutor-lms-advanced-tracking'); ?></th>
                                <th><?php _e('Actions', 'tutor-lms-advanced-tracking'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($course_data['students'] as $student): ?>
                                <tr>
                                    <td>
                                        <div class="student-info">
                                            <strong><?php echo esc_html($student['name']); ?></strong>
                                            <br>
                                            <small><?php echo esc_html($student['email']); ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width: <?php echo esc_attr($student['progression']); ?>%"></div>
                                            <span><?php echo esc_html($student['progression']); ?>%</span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="quiz-score <?php echo esc_attr($student['quiz_average'] >= 70 ? 'passing' : 'failing'); ?>">
                                            <?php echo esc_html($student['quiz_average']); ?>%
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($student['last_activity']): ?>
                                            <span class="last-activity">
                                                <?php echo date_i18n(get_option('date_format'), strtotime($student['last_activity'])); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="no-activity"><?php _e('No activity', 'tutor-lms-advanced-tracking'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status <?php echo $student['completion_status']['is_completed'] ? 'completed' : 'in-progress'; ?>">
                                            <?php echo $student['completion_status']['is_completed'] ? __('Completed', 'tutor-lms-advanced-tracking') : __('In Progress', 'tutor-lms-advanced-tracking'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="<?php echo esc_url(add_query_arg(array('view' => 'user', 'user_id' => intval($student['id'])), get_permalink())); ?>" 
                                           class="btn btn-secondary">
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

        <div class="course-quizzes">
            <h3><?php _e('Quiz Performance', 'tutor-lms-advanced-tracking'); ?></h3>
            
            <?php if (empty($course_data['quizzes'])): ?>
                <div class="no-quizzes">
                    <p><?php _e('No quizzes found in this course.', 'tutor-lms-advanced-tracking'); ?></p>
                </div>
            <?php else: ?>
                <div class="quizzes-grid">
                    <?php foreach ($course_data['quizzes'] as $quiz): ?>
                        <div class="quiz-card">
                            <h4><?php echo esc_html($quiz['title']); ?></h4>
                            <div class="quiz-stats">
                                <div class="stat">
                                    <span class="label"><?php _e('Attempts:', 'tutor-lms-advanced-tracking'); ?></span>
                                    <span class="value"><?php echo $quiz['attempts_count']; ?></span>
                                </div>
                                <div class="stat">
                                    <span class="label"><?php _e('Avg. Score:', 'tutor-lms-advanced-tracking'); ?></span>
                                    <span class="value"><?php echo $quiz['average_score']; ?>%</span>
                                </div>
                                <div class="stat">
                                    <span class="label"><?php _e('Pass Rate:', 'tutor-lms-advanced-tracking'); ?></span>
                                    <span class="value"><?php echo $quiz['pass_rate']; ?>%</span>
                                </div>
                            </div>
                            
                            <?php if (!empty($quiz['questions'])): ?>
                                <div class="quiz-questions">
                                    <h5><?php _e('Question Analysis', 'tutor-lms-advanced-tracking'); ?></h5>
                                    <?php foreach ($quiz['questions'] as $question): ?>
                                        <div class="question-stat">
                                            <span class="question-title"><?php echo esc_html($question['title']); ?></span>
                                            <span class="correct-rate <?php echo $question['correct_rate'] >= 70 ? 'good' : 'poor'; ?>">
                                                <?php echo $question['correct_rate']; ?>% <?php _e('correct', 'tutor-lms-advanced-tracking'); ?>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>