<?php
/**
 * Segment Filter class for user group segmentation and filterable charts
 * 
 * Supports filtering by:
 * - User role (student, instructor, administrator)
 * - Course enrollment
 * - Activity level (active, inactive, dormant)
 * - Custom user meta
 * 
 * @package TutorLMS_Advanced_Tracking
 * @since 1.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class TutorAdvancedTracking_SegmentFilter {

    /**
     * Segment types
     */
    const SEGMENT_ROLE = 'role';
    const SEGMENT_COURSE = 'course';
    const SEGMENT_ACTIVITY = 'activity';
    const SEGMENT_CUSTOM = 'custom';

    /**
     * Activity thresholds (days)
     */
    const ACTIVE_THRESHOLD = 7;
    const INACTIVE_THRESHOLD = 30;

    /**
     * Cache group
     */
    private $cache_group = 'tlat_segments';

    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_ajax_tlat_get_segments', array($this, 'ajax_get_segments'));
        add_action('wp_ajax_tlat_filter_chart_data', array($this, 'ajax_filter_chart_data'));
        add_action('wp_ajax_tlat_save_segment', array($this, 'ajax_save_segment'));
        add_action('wp_ajax_tlat_delete_segment', array($this, 'ajax_delete_segment'));
    }

    /**
     * Get available segment types
     */
    public function get_segment_types() {
        return array(
            self::SEGMENT_ROLE => array(
                'label' => __('User Role', 'tutor-lms-advanced-tracking'),
                'icon' => 'dashicons-admin-users',
                'options' => $this->get_role_options()
            ),
            self::SEGMENT_COURSE => array(
                'label' => __('Course Enrollment', 'tutor-lms-advanced-tracking'),
                'icon' => 'dashicons-welcome-learn-more',
                'options' => $this->get_course_options()
            ),
            self::SEGMENT_ACTIVITY => array(
                'label' => __('Activity Level', 'tutor-lms-advanced-tracking'),
                'icon' => 'dashicons-chart-line',
                'options' => $this->get_activity_options()
            ),
            self::SEGMENT_CUSTOM => array(
                'label' => __('Custom Segment', 'tutor-lms-advanced-tracking'),
                'icon' => 'dashicons-filter',
                'options' => $this->get_saved_segments()
            )
        );
    }

    /**
     * Get role-based options
     */
    private function get_role_options() {
        $roles = wp_roles()->get_names();
        $options = array();
        
        foreach ($roles as $role_key => $role_name) {
            $count = count(get_users(array('role' => $role_key, 'fields' => 'ID')));
            $options[$role_key] = array(
                'label' => translate_user_role($role_name),
                'count' => $count
            );
        }
        
        return $options;
    }

    /**
     * Get course enrollment options
     */
    private function get_course_options() {
        global $wpdb;
        
        $courses = $wpdb->get_results(
            "SELECT p.ID, p.post_title, COUNT(e.ID) as enrollment_count
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->prefix}tutor_enrollments e ON p.ID = e.course_id AND e.status = 'completed'
             WHERE p.post_type = 'courses' AND p.post_status = 'publish'
             GROUP BY p.ID
             ORDER BY p.post_title ASC"
        );
        
        $options = array();
        foreach ($courses as $course) {
            $options[$course->ID] = array(
                'label' => $course->post_title,
                'count' => (int) $course->enrollment_count
            );
        }
        
        return $options;
    }

    /**
     * Get activity level options
     */
    private function get_activity_options() {
        $counts = $this->count_users_by_activity();
        
        return array(
            'active' => array(
                'label' => sprintf(__('Active (last %d days)', 'tutor-lms-advanced-tracking'), self::ACTIVE_THRESHOLD),
                'count' => $counts['active']
            ),
            'inactive' => array(
                'label' => sprintf(__('%d-%d days ago', 'tutor-lms-advanced-tracking'), self::ACTIVE_THRESHOLD, self::INACTIVE_THRESHOLD),
                'count' => $counts['inactive']
            ),
            'dormant' => array(
                'label' => sprintf(__('Over %d days ago', 'tutor-lms-advanced-tracking'), self::INACTIVE_THRESHOLD),
                'count' => $counts['dormant']
            ),
            'never' => array(
                'label' => __('Never active', 'tutor-lms-advanced-tracking'),
                'count' => $counts['never']
            )
        );
    }

    /**
     * Count users by activity level
     */
    private function count_users_by_activity() {
        global $wpdb;
        
        $cache_key = 'activity_counts';
        $cached = wp_cache_get($cache_key, $this->cache_group);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $now = current_time('mysql');
        $active_date = date('Y-m-d H:i:s', strtotime("-" . self::ACTIVE_THRESHOLD . " days"));
        $inactive_date = date('Y-m-d H:i:s', strtotime("-" . self::INACTIVE_THRESHOLD . " days"));
        
        // Get last activity from events table
        $activities = $wpdb->get_results(
            "SELECT user_id, MAX(event_time) as last_active
             FROM {$wpdb->prefix}tlat_events
             GROUP BY user_id"
        );
        
        $counts = array(
            'active' => 0,
            'inactive' => 0,
            'dormant' => 0,
            'never' => 0
        );
        
        $active_users = array();
        foreach ($activities as $activity) {
            $active_users[$activity->user_id] = $activity->last_active;
            
            if ($activity->last_active >= $active_date) {
                $counts['active']++;
            } elseif ($activity->last_active >= $inactive_date) {
                $counts['inactive']++;
            } else {
                $counts['dormant']++;
            }
        }
        
        // Count users with no activity
        $total_users = count(get_users(array('fields' => 'ID')));
        $counts['never'] = $total_users - count($active_users);
        
        wp_cache_set($cache_key, $counts, $this->cache_group, 300);
        
        return $counts;
    }

    /**
     * Get saved custom segments
     */
    private function get_saved_segments() {
        $segments = get_option('tlat_custom_segments', array());
        $options = array();
        
        foreach ($segments as $id => $segment) {
            $options[$id] = array(
                'label' => $segment['name'],
                'count' => $this->count_segment_users($segment['filters'])
            );
        }
        
        return $options;
    }

    /**
     * Count users matching segment filters
     */
    private function count_segment_users($filters) {
        $user_ids = $this->get_segment_user_ids($filters);
        return count($user_ids);
    }

    /**
     * Get user IDs matching segment filters
     */
    public function get_segment_user_ids($filters) {
        global $wpdb;
        
        $user_ids = null;
        
        foreach ($filters as $filter) {
            $filter_users = array();
            
            switch ($filter['type']) {
                case self::SEGMENT_ROLE:
                    $filter_users = get_users(array(
                        'role' => $filter['value'],
                        'fields' => 'ID'
                    ));
                    break;
                    
                case self::SEGMENT_COURSE:
                    $filter_users = $wpdb->get_col($wpdb->prepare(
                        "SELECT DISTINCT user_id FROM {$wpdb->prefix}tutor_enrollments
                         WHERE course_id = %d AND status = 'completed'",
                        $filter['value']
                    ));
                    break;
                    
                case self::SEGMENT_ACTIVITY:
                    $filter_users = $this->get_users_by_activity($filter['value']);
                    break;
            }
            
            // Intersect with existing user IDs (AND logic)
            if ($user_ids === null) {
                $user_ids = $filter_users;
            } else {
                $user_ids = array_intersect($user_ids, $filter_users);
            }
        }
        
        return $user_ids ?: array();
    }

    /**
     * Get users by activity level
     */
    private function get_users_by_activity($level) {
        global $wpdb;
        
        $now = current_time('mysql');
        $active_date = date('Y-m-d H:i:s', strtotime("-" . self::ACTIVE_THRESHOLD . " days"));
        $inactive_date = date('Y-m-d H:i:s', strtotime("-" . self::INACTIVE_THRESHOLD . " days"));
        
        switch ($level) {
            case 'active':
                return $wpdb->get_col($wpdb->prepare(
                    "SELECT DISTINCT user_id FROM {$wpdb->prefix}tlat_events
                     WHERE event_time >= %s",
                    $active_date
                ));
                
            case 'inactive':
                return $wpdb->get_col($wpdb->prepare(
                    "SELECT user_id FROM (
                        SELECT user_id, MAX(event_time) as last_active
                        FROM {$wpdb->prefix}tlat_events
                        GROUP BY user_id
                    ) t WHERE last_active < %s AND last_active >= %s",
                    $active_date,
                    $inactive_date
                ));
                
            case 'dormant':
                return $wpdb->get_col($wpdb->prepare(
                    "SELECT user_id FROM (
                        SELECT user_id, MAX(event_time) as last_active
                        FROM {$wpdb->prefix}tlat_events
                        GROUP BY user_id
                    ) t WHERE last_active < %s",
                    $inactive_date
                ));
                
            case 'never':
                $active_users = $wpdb->get_col(
                    "SELECT DISTINCT user_id FROM {$wpdb->prefix}tlat_events"
                );
                $all_users = get_users(array('fields' => 'ID'));
                return array_diff($all_users, $active_users);
        }
        
        return array();
    }

    /**
     * Filter chart data by segment
     */
    public function filter_chart_data($data, $segment_filters, $chart_type = 'completion') {
        if (empty($segment_filters)) {
            return $data;
        }
        
        $user_ids = $this->get_segment_user_ids($segment_filters);
        
        if (empty($user_ids)) {
            return $this->empty_chart_data($chart_type);
        }
        
        // Re-query data filtered by user IDs
        switch ($chart_type) {
            case 'completion':
                return $this->get_completion_data_filtered($user_ids);
            case 'engagement':
                return $this->get_engagement_data_filtered($user_ids);
            case 'progress':
                return $this->get_progress_data_filtered($user_ids);
            case 'time':
                return $this->get_time_data_filtered($user_ids);
            default:
                return $data;
        }
    }

    /**
     * Get completion data filtered by user IDs
     */
    private function get_completion_data_filtered($user_ids) {
        global $wpdb;
        
        $user_ids_str = implode(',', array_map('intval', $user_ids));
        
        $results = $wpdb->get_results(
            "SELECT 
                DATE(event_time) as date,
                COUNT(DISTINCT user_id) as completions
             FROM {$wpdb->prefix}tlat_events
             WHERE user_id IN ($user_ids_str)
               AND event_type = 'course_complete'
               AND event_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY DATE(event_time)
             ORDER BY date ASC"
        );
        
        return $this->format_chart_data($results, 'completions');
    }

    /**
     * Get engagement data filtered by user IDs
     */
    private function get_engagement_data_filtered($user_ids) {
        global $wpdb;
        
        $user_ids_str = implode(',', array_map('intval', $user_ids));
        
        $results = $wpdb->get_results(
            "SELECT 
                DATE(event_time) as date,
                COUNT(*) as events,
                COUNT(DISTINCT user_id) as unique_users
             FROM {$wpdb->prefix}tlat_events
             WHERE user_id IN ($user_ids_str)
               AND event_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY DATE(event_time)
             ORDER BY date ASC"
        );
        
        return $this->format_chart_data($results, 'events');
    }

    /**
     * Get progress data filtered by user IDs
     */
    private function get_progress_data_filtered($user_ids) {
        global $wpdb;
        
        $user_ids_str = implode(',', array_map('intval', $user_ids));
        
        $results = $wpdb->get_results(
            "SELECT 
                CASE 
                    WHEN progress_percent >= 100 THEN 'Completed'
                    WHEN progress_percent >= 75 THEN '75-99%'
                    WHEN progress_percent >= 50 THEN '50-74%'
                    WHEN progress_percent >= 25 THEN '25-49%'
                    ELSE '0-24%'
                END as bracket,
                COUNT(*) as count
             FROM (
                SELECT user_id, course_id, 
                       (completed_lessons * 100.0 / total_lessons) as progress_percent
                FROM {$wpdb->prefix}tlat_agg_daily
                WHERE user_id IN ($user_ids_str)
                  AND agg_date = CURDATE()
             ) t
             GROUP BY bracket
             ORDER BY FIELD(bracket, '0-24%', '25-49%', '50-74%', '75-99%', 'Completed')"
        );
        
        return $this->format_bracket_data($results);
    }

    /**
     * Get time data filtered by user IDs
     */
    private function get_time_data_filtered($user_ids) {
        global $wpdb;
        
        $user_ids_str = implode(',', array_map('intval', $user_ids));
        
        $results = $wpdb->get_results(
            "SELECT 
                HOUR(event_time) as hour,
                COUNT(*) as activity
             FROM {$wpdb->prefix}tlat_events
             WHERE user_id IN ($user_ids_str)
               AND event_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY HOUR(event_time)
             ORDER BY hour ASC"
        );
        
        return $this->format_hourly_data($results);
    }

    /**
     * Format chart data for Chart.js
     */
    private function format_chart_data($results, $value_field) {
        $labels = array();
        $data = array();
        
        foreach ($results as $row) {
            $labels[] = date('M j', strtotime($row->date));
            $data[] = (int) $row->$value_field;
        }
        
        return array(
            'labels' => $labels,
            'datasets' => array(
                array(
                    'label' => ucfirst($value_field),
                    'data' => $data,
                    'borderColor' => '#3B82F6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => true
                )
            )
        );
    }

    /**
     * Format bracket data for Chart.js
     */
    private function format_bracket_data($results) {
        $labels = array();
        $data = array();
        $colors = array('#EF4444', '#F97316', '#EAB308', '#22C55E', '#3B82F6');
        
        foreach ($results as $i => $row) {
            $labels[] = $row->bracket;
            $data[] = (int) $row->count;
        }
        
        return array(
            'labels' => $labels,
            'datasets' => array(
                array(
                    'data' => $data,
                    'backgroundColor' => array_slice($colors, 0, count($data))
                )
            )
        );
    }

    /**
     * Format hourly data for Chart.js
     */
    private function format_hourly_data($results) {
        $hourly = array_fill(0, 24, 0);
        
        foreach ($results as $row) {
            $hourly[(int) $row->hour] = (int) $row->activity;
        }
        
        $labels = array();
        for ($i = 0; $i < 24; $i++) {
            $labels[] = sprintf('%02d:00', $i);
        }
        
        return array(
            'labels' => $labels,
            'datasets' => array(
                array(
                    'label' => __('Activity', 'tutor-lms-advanced-tracking'),
                    'data' => $hourly,
                    'borderColor' => '#8B5CF6',
                    'backgroundColor' => 'rgba(139, 92, 246, 0.1)',
                    'fill' => true
                )
            )
        );
    }

    /**
     * Empty chart data placeholder
     */
    private function empty_chart_data($chart_type) {
        return array(
            'labels' => array(),
            'datasets' => array(
                array(
                    'label' => __('No data', 'tutor-lms-advanced-tracking'),
                    'data' => array()
                )
            ),
            'message' => __('No users match the selected segment filters.', 'tutor-lms-advanced-tracking')
        );
    }

    /**
     * AJAX: Get segments
     */
    public function ajax_get_segments() {
        check_ajax_referer('tlat_nonce', 'nonce');
        
        if (!current_user_can('manage_tutor')) {
            wp_send_json_error('Unauthorized');
        }
        
        wp_send_json_success(array(
            'types' => $this->get_segment_types(),
            'saved' => $this->get_saved_segments()
        ));
    }

    /**
     * AJAX: Filter chart data
     */
    public function ajax_filter_chart_data() {
        check_ajax_referer('tlat_nonce', 'nonce');
        
        if (!current_user_can('manage_tutor')) {
            wp_send_json_error('Unauthorized');
        }
        
        $filters = isset($_POST['filters']) ? json_decode(stripslashes($_POST['filters']), true) : array();
        $chart_type = isset($_POST['chart_type']) ? sanitize_text_field($_POST['chart_type']) : 'completion';
        
        $data = $this->filter_chart_data(array(), $filters, $chart_type);
        
        wp_send_json_success($data);
    }

    /**
     * AJAX: Save segment
     */
    public function ajax_save_segment() {
        check_ajax_referer('tlat_nonce', 'nonce');
        
        if (!current_user_can('manage_tutor')) {
            wp_send_json_error('Unauthorized');
        }
        
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $filters = isset($_POST['filters']) ? json_decode(stripslashes($_POST['filters']), true) : array();
        
        if (empty($name) || empty($filters)) {
            wp_send_json_error('Name and filters required');
        }
        
        $segments = get_option('tlat_custom_segments', array());
        $id = 'seg_' . wp_generate_uuid4();
        
        $segments[$id] = array(
            'name' => $name,
            'filters' => $filters,
            'created' => current_time('mysql'),
            'created_by' => get_current_user_id()
        );
        
        update_option('tlat_custom_segments', $segments);
        
        wp_send_json_success(array(
            'id' => $id,
            'segment' => $segments[$id]
        ));
    }

    /**
     * AJAX: Delete segment
     */
    public function ajax_delete_segment() {
        check_ajax_referer('tlat_nonce', 'nonce');
        
        if (!current_user_can('manage_tutor')) {
            wp_send_json_error('Unauthorized');
        }
        
        $id = isset($_POST['id']) ? sanitize_text_field($_POST['id']) : '';
        
        $segments = get_option('tlat_custom_segments', array());
        
        if (isset($segments[$id])) {
            unset($segments[$id]);
            update_option('tlat_custom_segments', $segments);
            wp_send_json_success();
        }
        
        wp_send_json_error('Segment not found');
    }

    /**
     * Render segment filter UI component
     */
    public function render_filter_ui($chart_id = 'main') {
        $types = $this->get_segment_types();
        ?>
        <div class="tlat-segment-filter" data-chart="<?php echo esc_attr($chart_id); ?>">
            <div class="tlat-filter-header">
                <h4><?php _e('Filter by Segment', 'tutor-lms-advanced-tracking'); ?></h4>
                <button type="button" class="button tlat-add-filter">
                    <span class="dashicons dashicons-plus-alt2"></span>
                    <?php _e('Add Filter', 'tutor-lms-advanced-tracking'); ?>
                </button>
            </div>
            
            <div class="tlat-active-filters"></div>
            
            <div class="tlat-filter-builder" style="display:none;">
                <select class="tlat-filter-type">
                    <option value=""><?php _e('Select filter type...', 'tutor-lms-advanced-tracking'); ?></option>
                    <?php foreach ($types as $key => $type): ?>
                        <option value="<?php echo esc_attr($key); ?>">
                            <?php echo esc_html($type['label']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <select class="tlat-filter-value" style="display:none;"></select>
                
                <button type="button" class="button button-primary tlat-apply-filter" style="display:none;">
                    <?php _e('Apply', 'tutor-lms-advanced-tracking'); ?>
                </button>
                <button type="button" class="button tlat-cancel-filter">
                    <?php _e('Cancel', 'tutor-lms-advanced-tracking'); ?>
                </button>
            </div>
            
            <div class="tlat-filter-actions" style="display:none;">
                <button type="button" class="button tlat-save-segment">
                    <span class="dashicons dashicons-saved"></span>
                    <?php _e('Save as Segment', 'tutor-lms-advanced-tracking'); ?>
                </button>
                <button type="button" class="button tlat-clear-filters">
                    <?php _e('Clear All', 'tutor-lms-advanced-tracking'); ?>
                </button>
            </div>
        </div>
        
        <script>
        jQuery(function($) {
            var segmentTypes = <?php echo json_encode($types); ?>;
            
            var $container = $('.tlat-segment-filter[data-chart="<?php echo esc_js($chart_id); ?>"]');
            var activeFilters = [];
            
            $container.on('click', '.tlat-add-filter', function() {
                $container.find('.tlat-filter-builder').show();
                $(this).hide();
            });
            
            $container.on('click', '.tlat-cancel-filter', function() {
                $container.find('.tlat-filter-builder').hide();
                $container.find('.tlat-filter-type').val('');
                $container.find('.tlat-filter-value').hide().empty();
                $container.find('.tlat-apply-filter').hide();
                $container.find('.tlat-add-filter').show();
            });
            
            $container.on('change', '.tlat-filter-type', function() {
                var type = $(this).val();
                var $value = $container.find('.tlat-filter-value');
                
                if (!type) {
                    $value.hide().empty();
                    $container.find('.tlat-apply-filter').hide();
                    return;
                }
                
                var options = segmentTypes[type].options;
                var html = '<option value=""><?php _e('Select...', 'tutor-lms-advanced-tracking'); ?></option>';
                
                $.each(options, function(key, opt) {
                    html += '<option value="' + key + '">' + opt.label + ' (' + opt.count + ')</option>';
                });
                
                $value.html(html).show();
            });
            
            $container.on('change', '.tlat-filter-value', function() {
                if ($(this).val()) {
                    $container.find('.tlat-apply-filter').show();
                } else {
                    $container.find('.tlat-apply-filter').hide();
                }
            });
            
            $container.on('click', '.tlat-apply-filter', function() {
                var type = $container.find('.tlat-filter-type').val();
                var value = $container.find('.tlat-filter-value').val();
                var label = $container.find('.tlat-filter-value option:selected').text();
                
                activeFilters.push({ type: type, value: value, label: label });
                renderActiveFilters();
                updateChart();
                
                // Reset builder
                $container.find('.tlat-cancel-filter').click();
            });
            
            $container.on('click', '.tlat-remove-filter', function() {
                var index = $(this).closest('.tlat-filter-tag').data('index');
                activeFilters.splice(index, 1);
                renderActiveFilters();
                updateChart();
            });
            
            $container.on('click', '.tlat-clear-filters', function() {
                activeFilters = [];
                renderActiveFilters();
                updateChart();
            });
            
            function renderActiveFilters() {
                var $list = $container.find('.tlat-active-filters');
                $list.empty();
                
                $.each(activeFilters, function(i, filter) {
                    $list.append(
                        '<span class="tlat-filter-tag" data-index="' + i + '">' +
                        '<span class="dashicons ' + segmentTypes[filter.type].icon + '"></span> ' +
                        filter.label +
                        '<button type="button" class="tlat-remove-filter">&times;</button>' +
                        '</span>'
                    );
                });
                
                if (activeFilters.length > 0) {
                    $container.find('.tlat-filter-actions').show();
                } else {
                    $container.find('.tlat-filter-actions').hide();
                }
            }
            
            function updateChart() {
                $(document).trigger('tlat_filter_changed', [activeFilters, '<?php echo esc_js($chart_id); ?>']);
            }
        });
        </script>
        <?php
    }
}
