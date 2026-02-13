<?php
/**
 * Quiz Analytics Class
 * 
 * Provides detailed quiz analytics including:
 * - Per-question analysis
 * - Answer pattern heatmaps
 * - Difficulty scoring
 * - Retry tracking
 * - Score distributions
 * 
 * @package TutorAdvancedTracking
 * @since 1.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class TutorAdvancedTracking_QuizAnalytics {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'), 26);
        
        // Register REST API endpoints
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        
        // AJAX handlers
        add_action('wp_ajax_tlat_get_quiz_details', array($this, 'ajax_get_quiz_details'));
        add_action('wp_ajax_tlat_get_question_analysis', array($this, 'ajax_get_question_analysis'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'tutor-stats',
            __('Quiz Analytics', 'tutor-lms-advanced-tracking'),
            __('📝 Quizzes', 'tutor-lms-advanced-tracking'),
            'manage_tutor',
            'tlat-quizzes',
            array($this, 'render_quizzes_page')
        );
    }
    
    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        register_rest_route('tlat/v1', '/quizzes', array(
            'methods' => 'GET',
            'callback' => array($this, 'api_get_quizzes'),
            'permission_callback' => function() {
                return current_user_can('manage_tutor');
            },
        ));
        
        register_rest_route('tlat/v1', '/quizzes/(?P<id>\d+)/questions', array(
            'methods' => 'GET',
            'callback' => array($this, 'api_get_quiz_questions'),
            'permission_callback' => function() {
                return current_user_can('manage_tutor');
            },
        ));
    }
    
    /**
     * Get all quizzes with analytics
     */
    public function get_quizzes_overview() {
        global $wpdb;
        
        $quizzes = get_posts(array(
            'post_type' => 'tutor_quiz',
            'posts_per_page' => -1,
            'post_status' => 'publish',
        ));
        
        $data = array();
        
        foreach ($quizzes as $quiz) {
            $stats = $this->get_quiz_stats($quiz->ID);
            $course = get_post(get_post_field('post_parent', $quiz->ID));
            
            $data[] = array(
                'id' => $quiz->ID,
                'title' => $quiz->post_title,
                'course' => $course ? $course->post_title : 'N/A',
                'course_id' => $course ? $course->ID : 0,
                'attempts' => $stats['total_attempts'],
                'unique_users' => $stats['unique_users'],
                'pass_rate' => $stats['pass_rate'],
                'avg_score' => $stats['avg_score'],
                'avg_time' => $stats['avg_time'],
                'difficulty' => $this->calculate_difficulty($stats),
            );
        }
        
        return $data;
    }
    
    /**
     * Get quiz statistics
     */
    public function get_quiz_stats($quiz_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'tutor_quiz_attempts';
        
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_attempts,
                COUNT(DISTINCT user_id) as unique_users,
                AVG(earned_marks / NULLIF(total_marks, 0) * 100) as avg_score,
                SUM(CASE WHEN earned_marks >= total_marks * 0.7 THEN 1 ELSE 0 END) as passed,
                AVG(TIMESTAMPDIFF(SECOND, attempt_started_at, attempt_ended_at)) as avg_time
            FROM {$table}
            WHERE quiz_id = %d AND attempt_status = 'attempt_ended'",
            $quiz_id
        ));
        
        return array(
            'total_attempts' => (int) ($stats->total_attempts ?? 0),
            'unique_users' => (int) ($stats->unique_users ?? 0),
            'avg_score' => round((float) ($stats->avg_score ?? 0), 1),
            'pass_rate' => $stats->total_attempts > 0 
                ? round(($stats->passed / $stats->total_attempts) * 100, 1) 
                : 0,
            'avg_time' => (int) ($stats->avg_time ?? 0),
        );
    }
    
    /**
     * Calculate difficulty score based on stats
     */
    private function calculate_difficulty($stats) {
        // Difficulty = inverse of pass rate, weighted by avg score
        $base_difficulty = 100 - $stats['pass_rate'];
        $score_factor = (100 - $stats['avg_score']) / 100;
        
        $difficulty = ($base_difficulty * 0.6) + ($score_factor * 100 * 0.4);
        
        if ($difficulty < 30) return 'easy';
        if ($difficulty < 60) return 'medium';
        return 'hard';
    }
    
    /**
     * Get per-question analytics
     */
    public function get_question_analytics($quiz_id) {
        global $wpdb;
        
        // Get all questions for this quiz
        $questions = $wpdb->get_results($wpdb->prepare(
            "SELECT question_id, question_title, question_type 
            FROM {$wpdb->prefix}tutor_quiz_questions 
            WHERE quiz_id = %d
            ORDER BY question_order ASC",
            $quiz_id
        ));
        
        $analytics = array();
        
        foreach ($questions as $question) {
            $question_stats = $this->get_question_stats($question->question_id);
            
            $analytics[] = array(
                'id' => $question->question_id,
                'title' => $question->question_title,
                'type' => $question->question_type,
                'total_answers' => $question_stats['total_answers'],
                'correct_answers' => $question_stats['correct_answers'],
                'correct_rate' => $question_stats['correct_rate'],
                'avg_time' => $question_stats['avg_time'],
                'difficulty_index' => $question_stats['difficulty_index'],
                'answer_distribution' => $question_stats['answer_distribution'],
            );
        }
        
        return $analytics;
    }
    
    /**
     * Get statistics for a single question
     */
    public function get_question_stats($question_id) {
        global $wpdb;
        
        // Get answer data - this depends on Tutor LMS table structure
        $answers = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                given_answer,
                is_correct,
                TIMESTAMPDIFF(SECOND, answer_started_at, answer_ended_at) as time_spent
            FROM {$wpdb->prefix}tutor_quiz_attempt_answers
            WHERE question_id = %d",
            $question_id
        ));
        
        $total = count($answers);
        $correct = 0;
        $total_time = 0;
        $answer_counts = array();
        
        foreach ($answers as $answer) {
            if ($answer->is_correct) {
                $correct++;
            }
            $total_time += (int) $answer->time_spent;
            
            // Count answer distribution
            $given = $answer->given_answer;
            if (!isset($answer_counts[$given])) {
                $answer_counts[$given] = 0;
            }
            $answer_counts[$given]++;
        }
        
        // Calculate difficulty index (0 = very hard, 1 = very easy)
        $difficulty_index = $total > 0 ? $correct / $total : 0;
        
        return array(
            'total_answers' => $total,
            'correct_answers' => $correct,
            'correct_rate' => $total > 0 ? round(($correct / $total) * 100, 1) : 0,
            'avg_time' => $total > 0 ? round($total_time / $total) : 0,
            'difficulty_index' => round($difficulty_index, 2),
            'answer_distribution' => $answer_counts,
        );
    }
    
    /**
     * Get score distribution for a quiz
     */
    public function get_score_distribution($quiz_id) {
        global $wpdb;
        
        $scores = $wpdb->get_col($wpdb->prepare(
            "SELECT ROUND(earned_marks / NULLIF(total_marks, 0) * 100) as score
            FROM {$wpdb->prefix}tutor_quiz_attempts
            WHERE quiz_id = %d AND attempt_status = 'attempt_ended'",
            $quiz_id
        ));
        
        // Group into buckets: 0-10, 11-20, ..., 91-100
        $distribution = array_fill(0, 10, 0);
        
        foreach ($scores as $score) {
            $bucket = min(9, floor($score / 10));
            if ($bucket < 0) $bucket = 0;
            $distribution[$bucket]++;
        }
        
        return array(
            'buckets' => array('0-10', '11-20', '21-30', '31-40', '41-50', '51-60', '61-70', '71-80', '81-90', '91-100'),
            'counts' => $distribution,
        );
    }
    
    /**
     * Get retry statistics
     */
    public function get_retry_stats($quiz_id) {
        global $wpdb;
        
        $retries = $wpdb->get_results($wpdb->prepare(
            "SELECT user_id, 
                    COUNT(*) as attempt_count,
                    MIN(earned_marks / NULLIF(total_marks, 0) * 100) as first_score,
                    MAX(earned_marks / NULLIF(total_marks, 0) * 100) as best_score
            FROM {$wpdb->prefix}tutor_quiz_attempts
            WHERE quiz_id = %d AND attempt_status = 'attempt_ended'
            GROUP BY user_id
            HAVING attempt_count > 1",
            $quiz_id
        ));
        
        $improvement_data = array();
        foreach ($retries as $retry) {
            $improvement_data[] = array(
                'attempts' => $retry->attempt_count,
                'improvement' => round($retry->best_score - $retry->first_score, 1),
            );
        }
        
        return array(
            'users_with_retries' => count($retries),
            'avg_improvement' => count($improvement_data) > 0 
                ? round(array_sum(array_column($improvement_data, 'improvement')) / count($improvement_data), 1)
                : 0,
            'details' => $improvement_data,
        );
    }
    
    /**
     * Render quizzes page
     */
    public function render_quizzes_page() {
        $quizzes = $this->get_quizzes_overview();
        
        // Calculate overview stats
        $total_quizzes = count($quizzes);
        $total_attempts = array_sum(array_column($quizzes, 'attempts'));
        $avg_pass_rate = $total_quizzes > 0 
            ? round(array_sum(array_column($quizzes, 'pass_rate')) / $total_quizzes, 1)
            : 0;
        $hard_quizzes = count(array_filter($quizzes, function($q) { return $q['difficulty'] === 'hard'; }));
        
        ?>
        <div class="wrap tlat-quizzes-page">
            <h1><?php _e('Quiz Analytics', 'tutor-lms-advanced-tracking'); ?></h1>
            
            <!-- Stats Cards -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">
                <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <div style="font-size: 32px; font-weight: bold; color: #3b82f6;"><?php echo esc_html($total_quizzes); ?></div>
                    <div style="color: #6b7280; margin-top: 5px;"><?php _e('Total Quizzes', 'tutor-lms-advanced-tracking'); ?></div>
                </div>
                
                <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <div style="font-size: 32px; font-weight: bold; color: #8b5cf6;"><?php echo esc_html($total_attempts); ?></div>
                    <div style="color: #6b7280; margin-top: 5px;"><?php _e('Total Attempts', 'tutor-lms-advanced-tracking'); ?></div>
                </div>
                
                <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <div style="font-size: 32px; font-weight: bold; color: #10b981;"><?php echo esc_html($avg_pass_rate); ?>%</div>
                    <div style="color: #6b7280; margin-top: 5px;"><?php _e('Avg Pass Rate', 'tutor-lms-advanced-tracking'); ?></div>
                </div>
                
                <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <div style="font-size: 32px; font-weight: bold; color: #ef4444;"><?php echo esc_html($hard_quizzes); ?></div>
                    <div style="color: #6b7280; margin-top: 5px;"><?php _e('Hard Quizzes', 'tutor-lms-advanced-tracking'); ?></div>
                </div>
            </div>
            
            <!-- Quizzes Table -->
            <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <h2 style="margin-top: 0;"><?php _e('All Quizzes', 'tutor-lms-advanced-tracking'); ?></h2>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Quiz', 'tutor-lms-advanced-tracking'); ?></th>
                            <th><?php _e('Course', 'tutor-lms-advanced-tracking'); ?></th>
                            <th><?php _e('Attempts', 'tutor-lms-advanced-tracking'); ?></th>
                            <th><?php _e('Pass Rate', 'tutor-lms-advanced-tracking'); ?></th>
                            <th><?php _e('Avg Score', 'tutor-lms-advanced-tracking'); ?></th>
                            <th><?php _e('Difficulty', 'tutor-lms-advanced-tracking'); ?></th>
                            <th><?php _e('Actions', 'tutor-lms-advanced-tracking'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($quizzes as $quiz): ?>
                        <tr>
                            <td><strong><?php echo esc_html($quiz['title']); ?></strong></td>
                            <td><?php echo esc_html($quiz['course']); ?></td>
                            <td>
                                <?php echo esc_html($quiz['attempts']); ?>
                                <span style="color: #6b7280; font-size: 12px;">
                                    (<?php echo esc_html($quiz['unique_users']); ?> <?php _e('users', 'tutor-lms-advanced-tracking'); ?>)
                                </span>
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <div style="width: 60px; height: 8px; background: #e5e7eb; border-radius: 4px; overflow: hidden;">
                                        <div style="width: <?php echo esc_attr($quiz['pass_rate']); ?>%; height: 100%; background: <?php echo $quiz['pass_rate'] < 50 ? '#ef4444' : ($quiz['pass_rate'] < 70 ? '#f59e0b' : '#10b981'); ?>;"></div>
                                    </div>
                                    <span><?php echo esc_html($quiz['pass_rate']); ?>%</span>
                                </div>
                            </td>
                            <td><?php echo esc_html($quiz['avg_score']); ?>%</td>
                            <td>
                                <?php 
                                $difficulty_colors = array('easy' => '#10b981', 'medium' => '#f59e0b', 'hard' => '#ef4444');
                                $difficulty_labels = array('easy' => __('Easy', 'tutor-lms-advanced-tracking'), 'medium' => __('Medium', 'tutor-lms-advanced-tracking'), 'hard' => __('Hard', 'tutor-lms-advanced-tracking'));
                                ?>
                                <span style="display: inline-block; background: <?php echo esc_attr($difficulty_colors[$quiz['difficulty']]); ?>20; color: <?php echo esc_attr($difficulty_colors[$quiz['difficulty']]); ?>; padding: 2px 10px; border-radius: 12px; font-size: 12px; font-weight: 500;">
                                    <?php echo esc_html($difficulty_labels[$quiz['difficulty']]); ?>
                                </span>
                            </td>
                            <td>
                                <button class="button button-small tlat-view-quiz" data-quiz-id="<?php echo esc_attr($quiz['id']); ?>">
                                    <?php _e('Analyze', 'tutor-lms-advanced-tracking'); ?>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($quizzes)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 40px; color: #6b7280;">
                                <?php _e('No quizzes found. Create quizzes in Tutor LMS to see analytics.', 'tutor-lms-advanced-tracking'); ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Quiz Detail Modal -->
            <div id="tlat-quiz-modal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 100000;">
                <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; border-radius: 12px; width: 90%; max-width: 900px; max-height: 90vh; overflow: auto;">
                    <div style="padding: 20px; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; background: white; z-index: 1;">
                        <h3 id="tlat-quiz-modal-title" style="margin: 0;"><?php _e('Quiz Analysis', 'tutor-lms-advanced-tracking'); ?></h3>
                        <button id="tlat-close-quiz-modal" class="button">&times; <?php _e('Close', 'tutor-lms-advanced-tracking'); ?></button>
                    </div>
                    <div id="tlat-quiz-modal-content" style="padding: 20px;">
                        <!-- Content loaded via AJAX -->
                    </div>
                </div>
            </div>
        </div>
        
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
        jQuery(document).ready(function($) {
            // View quiz details
            $('.tlat-view-quiz').on('click', function() {
                var quizId = $(this).data('quiz-id');
                $('#tlat-quiz-modal').show();
                $('#tlat-quiz-modal-content').html('<div style="text-align: center; padding: 40px;"><span class="spinner is-active" style="float: none;"></span></div>');
                
                $.post(ajaxurl, {
                    action: 'tlat_get_quiz_details',
                    nonce: '<?php echo wp_create_nonce('tlat_quiz_analytics'); ?>',
                    quiz_id: quizId
                }, function(response) {
                    if (response.success) {
                        $('#tlat-quiz-modal-content').html(response.data.html);
                        $('#tlat-quiz-modal-title').text(response.data.title);
                        
                        // Render charts
                        if (response.data.distribution) {
                            var ctx = document.getElementById('tlat-score-distribution-chart').getContext('2d');
                            new Chart(ctx, {
                                type: 'bar',
                                data: {
                                    labels: response.data.distribution.buckets,
                                    datasets: [{
                                        label: '<?php _e('Students', 'tutor-lms-advanced-tracking'); ?>',
                                        data: response.data.distribution.counts,
                                        backgroundColor: '#3b82f6',
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    plugins: { legend: { display: false } },
                                    scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
                                }
                            });
                        }
                    } else {
                        $('#tlat-quiz-modal-content').html('<p style="color: red;"><?php _e('Error loading quiz data', 'tutor-lms-advanced-tracking'); ?></p>');
                    }
                });
            });
            
            // Close modal
            $('#tlat-close-quiz-modal, #tlat-quiz-modal').on('click', function(e) {
                if (e.target === this) {
                    $('#tlat-quiz-modal').hide();
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * AJAX: Get quiz details
     */
    public function ajax_get_quiz_details() {
        check_ajax_referer('tlat_quiz_analytics', 'nonce');
        
        if (!current_user_can('manage_tutor')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }
        
        $quiz_id = intval($_POST['quiz_id']);
        $quiz = get_post($quiz_id);
        
        if (!$quiz) {
            wp_send_json_error(array('message' => 'Quiz not found'));
        }
        
        $stats = $this->get_quiz_stats($quiz_id);
        $questions = $this->get_question_analytics($quiz_id);
        $distribution = $this->get_score_distribution($quiz_id);
        $retries = $this->get_retry_stats($quiz_id);
        
        ob_start();
        ?>
        <div class="tlat-quiz-detail">
            <!-- Overview Stats -->
            <div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 15px; margin-bottom: 25px;">
                <div style="text-align: center; padding: 15px; background: #f8fafc; border-radius: 8px;">
                    <div style="font-size: 24px; font-weight: bold; color: #3b82f6;"><?php echo esc_html($stats['total_attempts']); ?></div>
                    <div style="color: #6b7280; font-size: 12px;"><?php _e('Attempts', 'tutor-lms-advanced-tracking'); ?></div>
                </div>
                <div style="text-align: center; padding: 15px; background: #f8fafc; border-radius: 8px;">
                    <div style="font-size: 24px; font-weight: bold; color: #10b981;"><?php echo esc_html($stats['pass_rate']); ?>%</div>
                    <div style="color: #6b7280; font-size: 12px;"><?php _e('Pass Rate', 'tutor-lms-advanced-tracking'); ?></div>
                </div>
                <div style="text-align: center; padding: 15px; background: #f8fafc; border-radius: 8px;">
                    <div style="font-size: 24px; font-weight: bold; color: #8b5cf6;"><?php echo esc_html($stats['avg_score']); ?>%</div>
                    <div style="color: #6b7280; font-size: 12px;"><?php _e('Avg Score', 'tutor-lms-advanced-tracking'); ?></div>
                </div>
                <div style="text-align: center; padding: 15px; background: #f8fafc; border-radius: 8px;">
                    <div style="font-size: 24px; font-weight: bold; color: #f59e0b;"><?php echo esc_html(gmdate('i:s', $stats['avg_time'])); ?></div>
                    <div style="color: #6b7280; font-size: 12px;"><?php _e('Avg Time', 'tutor-lms-advanced-tracking'); ?></div>
                </div>
                <div style="text-align: center; padding: 15px; background: #f8fafc; border-radius: 8px;">
                    <div style="font-size: 24px; font-weight: bold; color: #06b6d4;">+<?php echo esc_html($retries['avg_improvement']); ?>%</div>
                    <div style="color: #6b7280; font-size: 12px;"><?php _e('Retry Improve', 'tutor-lms-advanced-tracking'); ?></div>
                </div>
            </div>
            
            <!-- Score Distribution Chart -->
            <div style="margin-bottom: 25px;">
                <h4 style="margin-bottom: 15px;"><?php _e('Score Distribution', 'tutor-lms-advanced-tracking'); ?></h4>
                <canvas id="tlat-score-distribution-chart" height="100"></canvas>
            </div>
            
            <!-- Per-Question Analysis -->
            <div>
                <h4 style="margin-bottom: 15px;"><?php _e('Question Analysis', 'tutor-lms-advanced-tracking'); ?></h4>
                
                <?php if (empty($questions)): ?>
                    <p style="color: #6b7280; font-style: italic;"><?php _e('No question data available.', 'tutor-lms-advanced-tracking'); ?></p>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed" style="background: white;">
                        <thead>
                            <tr>
                                <th><?php _e('Question', 'tutor-lms-advanced-tracking'); ?></th>
                                <th style="width: 80px;"><?php _e('Type', 'tutor-lms-advanced-tracking'); ?></th>
                                <th style="width: 100px;"><?php _e('Correct %', 'tutor-lms-advanced-tracking'); ?></th>
                                <th style="width: 100px;"><?php _e('Difficulty', 'tutor-lms-advanced-tracking'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($questions as $q): ?>
                            <tr>
                                <td><?php echo esc_html(wp_trim_words($q['title'], 10)); ?></td>
                                <td>
                                    <span style="font-size: 11px; background: #e5e7eb; padding: 2px 6px; border-radius: 4px;">
                                        <?php echo esc_html($q['type']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 5px;">
                                        <div style="width: 50px; height: 6px; background: #e5e7eb; border-radius: 3px; overflow: hidden;">
                                            <div style="width: <?php echo esc_attr($q['correct_rate']); ?>%; height: 100%; background: <?php echo $q['correct_rate'] < 50 ? '#ef4444' : ($q['correct_rate'] < 70 ? '#f59e0b' : '#10b981'); ?>;"></div>
                                        </div>
                                        <span style="font-size: 12px;"><?php echo esc_html($q['correct_rate']); ?>%</span>
                                    </div>
                                </td>
                                <td>
                                    <?php 
                                    $diff = $q['difficulty_index'];
                                    $diff_label = $diff > 0.7 ? __('Easy', 'tutor-lms-advanced-tracking') : ($diff > 0.4 ? __('Medium', 'tutor-lms-advanced-tracking') : __('Hard', 'tutor-lms-advanced-tracking'));
                                    $diff_color = $diff > 0.7 ? '#10b981' : ($diff > 0.4 ? '#f59e0b' : '#ef4444');
                                    ?>
                                    <span style="color: <?php echo esc_attr($diff_color); ?>; font-size: 12px; font-weight: 500;">
                                        <?php echo esc_html($diff_label); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php
        $html = ob_get_clean();
        
        wp_send_json_success(array(
            'html' => $html,
            'title' => $quiz->post_title,
            'distribution' => $distribution,
        ));
    }
    
    /**
     * REST API: Get quizzes
     */
    public function api_get_quizzes($request) {
        return rest_ensure_response($this->get_quizzes_overview());
    }
    
    /**
     * REST API: Get quiz questions
     */
    public function api_get_quiz_questions($request) {
        $quiz_id = $request->get_param('id');
        return rest_ensure_response($this->get_question_analytics($quiz_id));
    }
}

// Initialize
new TutorAdvancedTracking_QuizAnalytics();
