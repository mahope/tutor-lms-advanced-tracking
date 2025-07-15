<?php
/**
 * User details template
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

if (!isset($user_data) || !$user_data) {
    return;
}
?>

<div class="tutor-advanced-tracking-user-details">
    <div class="user-header">
        <div class="breadcrumb">
            <a href="<?php echo get_permalink(); ?>"><?php _e('Dashboard', 'tutor-lms-advanced-tracking'); ?></a>
            <span class="separator">/</span>
            <span><?php _e('Student Details', 'tutor-lms-advanced-tracking'); ?></span>
        </div>
        
        <div class="user-info">
            <h2><?php echo esc_html($user_data['name']); ?></h2>
            <p class="user-email"><?php echo esc_html($user_data['email']); ?></p>
            <p class="user-registration">
                <?php _e('Member since:', 'tutor-lms-advanced-tracking'); ?> 
                <?php echo date_i18n(get_option('date_format'), strtotime($user_data['registration_date'])); ?>
            </p>
        </div>
        
        <div class="activity-status">
            <div class="status-indicator <?php echo $user_data['activity_status']['activity_level']; ?>">
                <span class="status-label">
                    <?php
                    switch ($user_data['activity_status']['activity_level']) {
                        case 'very_active':
                            _e('Very Active', 'tutor-lms-advanced-tracking');
                            break;
                        case 'active':
                            _e('Active', 'tutor-lms-advanced-tracking');
                            break;
                        case 'moderate':
                            _e('Moderate', 'tutor-lms-advanced-tracking');
                            break;
                        case 'low':
                            _e('Low Activity', 'tutor-lms-advanced-tracking');
                            break;
                        case 'inactive':
                            _e('Inactive', 'tutor-lms-advanced-tracking');
                            break;
                        default:
                            _e('Unknown', 'tutor-lms-advanced-tracking');
                    }
                    ?>
                </span>
                <?php if ($user_data['activity_status']['is_inactive']): ?>
                    <div class="inactive-warning">
                        <span class="warning-icon">⚠️</span>
                        <?php _e('Student has been inactive for more than 7 days', 'tutor-lms-advanced-tracking'); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="user-stats-overview">
        <div class="stats-card">
            <h3><?php _e('Total Courses', 'tutor-lms-advanced-tracking'); ?></h3>
            <div class="stats-number"><?php echo $user_data['overall_stats']['total_courses']; ?></div>
        </div>
        
        <div class="stats-card">
            <h3><?php _e('Completed', 'tutor-lms-advanced-tracking'); ?></h3>
            <div class="stats-number"><?php echo $user_data['overall_stats']['completed_courses']; ?></div>
        </div>
        
        <div class="stats-card">
            <h3><?php _e('Completion Rate', 'tutor-lms-advanced-tracking'); ?></h3>
            <div class="stats-number"><?php echo $user_data['overall_stats']['completion_rate']; ?>%</div>
        </div>
        
        <div class="stats-card">
            <h3><?php _e('Quiz Average', 'tutor-lms-advanced-tracking'); ?></h3>
            <div class="stats-number"><?php echo $user_data['overall_stats']['avg_quiz_score']; ?>%</div>
        </div>
    </div>

    <div class="user-content">
        <div class="user-courses">
            <h3><?php _e('Course Progress', 'tutor-lms-advanced-tracking'); ?></h3>
            
            <?php if (empty($user_data['courses'])): ?>
                <div class="no-courses">
                    <p><?php _e('Student is not enrolled in any courses.', 'tutor-lms-advanced-tracking'); ?></p>
                </div>
            <?php else: ?>
                <div class="courses-table">
                    <table>
                        <thead>
                            <tr>
                                <th><?php _e('Course', 'tutor-lms-advanced-tracking'); ?></th>
                                <th><?php _e('Instructor', 'tutor-lms-advanced-tracking'); ?></th>
                                <th><?php _e('Progress', 'tutor-lms-advanced-tracking'); ?></th>
                                <th><?php _e('Quiz Avg.', 'tutor-lms-advanced-tracking'); ?></th>
                                <th><?php _e('Status', 'tutor-lms-advanced-tracking'); ?></th>
                                <th><?php _e('Last Activity', 'tutor-lms-advanced-tracking'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($user_data['courses'] as $course): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html($course['title']); ?></strong>
                                    </td>
                                    <td><?php echo esc_html($course['instructor']); ?></td>
                                    <td>
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width: <?php echo $course['progression']; ?>%"></div>
                                            <span><?php echo $course['progression']; ?>%</span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="quiz-score <?php echo $course['quiz_average'] >= 70 ? 'passing' : 'failing'; ?>">
                                            <?php echo $course['quiz_average']; ?>%
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status <?php echo $course['is_completed'] ? 'completed' : 'in-progress'; ?>">
                                            <?php echo $course['is_completed'] ? __('Completed', 'tutor-lms-advanced-tracking') : __('In Progress', 'tutor-lms-advanced-tracking'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($course['last_activity']): ?>
                                            <?php echo date_i18n(get_option('date_format'), strtotime($course['last_activity'])); ?>
                                        <?php else: ?>
                                            <span class="no-activity"><?php _e('No activity', 'tutor-lms-advanced-tracking'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="user-quiz-performance">
            <h3><?php _e('Quiz Performance', 'tutor-lms-advanced-tracking'); ?></h3>
            
            <?php if (empty($user_data['quiz_performance'])): ?>
                <div class="no-quizzes">
                    <p><?php _e('No quiz attempts found.', 'tutor-lms-advanced-tracking'); ?></p>
                </div>
            <?php else: ?>
                <div class="quiz-performance-table">
                    <table>
                        <thead>
                            <tr>
                                <th><?php _e('Quiz', 'tutor-lms-advanced-tracking'); ?></th>
                                <th><?php _e('Course', 'tutor-lms-advanced-tracking'); ?></th>
                                <th><?php _e('Score', 'tutor-lms-advanced-tracking'); ?></th>
                                <th><?php _e('Result', 'tutor-lms-advanced-tracking'); ?></th>
                                <th><?php _e('Date', 'tutor-lms-advanced-tracking'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($user_data['quiz_performance'] as $quiz): ?>
                                <tr>
                                    <td><?php echo esc_html($quiz['quiz_title']); ?></td>
                                    <td><?php echo esc_html($quiz['course_title']); ?></td>
                                    <td>
                                        <span class="quiz-score <?php echo $quiz['score'] >= 70 ? 'passing' : 'failing'; ?>">
                                            <?php echo $quiz['score']; ?>%
                                        </span>
                                        <small>(<?php echo $quiz['earned_marks']; ?>/<?php echo $quiz['total_marks']; ?>)</small>
                                    </td>
                                    <td>
                                        <span class="result <?php echo $quiz['passed'] ? 'passed' : 'failed'; ?>">
                                            <?php echo $quiz['passed'] ? __('Passed', 'tutor-lms-advanced-tracking') : __('Failed', 'tutor-lms-advanced-tracking'); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date_i18n(get_option('date_format'), strtotime($quiz['attempt_date'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($user_data['problem_areas'])): ?>
            <div class="user-problem-areas">
                <h3><?php _e('Areas for Improvement', 'tutor-lms-advanced-tracking'); ?></h3>
                <p class="problem-areas-description">
                    <?php _e('Questions where the student has answered incorrectly multiple times:', 'tutor-lms-advanced-tracking'); ?>
                </p>
                
                <div class="problem-areas-list">
                    <?php foreach ($user_data['problem_areas'] as $problem): ?>
                        <div class="problem-area-item">
                            <div class="problem-question">
                                <strong><?php echo esc_html($problem['question_title']); ?></strong>
                                <span class="wrong-count"><?php echo $problem['wrong_count']; ?> <?php _e('incorrect attempts', 'tutor-lms-advanced-tracking'); ?></span>
                            </div>
                            <div class="problem-context">
                                <?php _e('Course:', 'tutor-lms-advanced-tracking'); ?> <?php echo esc_html($problem['course_title']); ?> - 
                                <?php _e('Quiz:', 'tutor-lms-advanced-tracking'); ?> <?php echo esc_html($problem['quiz_title']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($user_data['certificates'])): ?>
            <div class="user-certificates">
                <h3><?php _e('Certificates', 'tutor-lms-advanced-tracking'); ?></h3>
                
                <div class="certificates-list">
                    <?php foreach ($user_data['certificates'] as $certificate): ?>
                        <div class="certificate-item">
                            <div class="certificate-title">
                                <strong><?php echo esc_html($certificate['course_title']); ?></strong>
                            </div>
                            <div class="certificate-date">
                                <?php _e('Completed:', 'tutor-lms-advanced-tracking'); ?> 
                                <?php echo date_i18n(get_option('date_format'), strtotime($certificate['completion_date'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>