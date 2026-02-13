<?php
/**
 * Engagement Analytics - Login Frequency & Session Length Tracking
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class TutorAdvancedTracking_Engagement {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_login', array($this, 'track_user_login'), 10, 2);
        add_action('init', array($this, 'init_session_tracking'));
        add_action('wp_logout', array($this, 'track_user_logout'));
        
        // AJAX handlers for engagement data
        add_action('wp_ajax_tutor_advanced_engagement_data', array($this, 'handle_engagement_data_ajax'));
        
        // Add dashboard section
        add_action('tutor_advanced_tracking_dashboard_stats', array($this, 'render_engagement_dashboard'), 20);
    }

    /**
     * Initialize session tracking
     */
    public function init_session_tracking() {
        if (!is_user_logged_in()) {
            return;
        }
        
        $user_id = get_current_user_id();
        $session_id = $this->get_session_id($user_id);
        
        if (!$session_id) {
            $session_id = $this->create_session($user_id);
        } else {
            $this->update_session_activity($session_id);
        }
        
        // Store session ID in user meta for logout tracking
        update_user_meta($user_id, '_tutor_session_id', $session_id);
    }

    /**
     * Get or create session ID
     */
    private function get_session_id($user_id) {
        $session_id = get_user_meta($user_id, '_tutor_session_id', true);
        
        if ($session_id) {
            // Verify session exists and is still active
            global $wpdb;
            $session = $wpdb->get_row($wpdb->prepare(
                "SELECT id, last_activity FROM {$wpdb->prefix}tlat_login_sessions 
                 WHERE id = %d AND user_id = %d AND session_end IS NULL",
                $session_id, $user_id
            ));
            
            if ($session) {
                // Check if session is still valid (within 4 hours of last activity)
                $last_activity = strtotime($session->last_activity);
                $now = current_time('timestamp');
                
                if (($now - $last_activity) < 14400) { // 4 hours
                    return $session_id;
                }
            }
        }
        
        return false;
    }

    /**
     * Create new session
     */
    private function create_session($user_id) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'tlat_login_sessions',
            array(
                'user_id' => $user_id,
                'session_start' => current_time('mysql'),
                'last_activity' => current_time('mysql'),
                'ip_address' => $this->get_client_ip(),
                'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
                'session_data' => wp_json_encode(array(
                    'referrer' => $_SERVER['HTTP_REFERER'] ?? '',
                    'login_type' => is_admin() ? 'admin' : 'frontend'
                ))
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s')
        );
        
        return $wpdb->insert_id;
    }

    /**
     * Update session activity
     */
    private function update_session_activity($session_id) {
        global $wpdb;
        
        $wpdb->update(
            $wpdb->prefix . 'tlat_login_sessions',
            array('last_activity' => current_time('mysql')),
            array('id' => $session_id),
            array('%s'),
            array('%d')
        );
    }

    /**
     * Track user login
     */
    public function track_user_login($user_login, $user) {
        $user_id = $user->ID;
        
        // Create session record
        $session_id = $this->create_session($user_id);
        update_user_meta($user_id, '_tutor_session_id', $session_id);
        
        // Log login event
        $this->log_engagement_event($user_id, 'user_login', array(
            'session_id' => $session_id,
            'login_time' => current_time('mysql'),
            'ip' => $this->get_client_ip()
        ));
    }

    /**
     * Track user logout and calculate session length
     */
    public function track_user_logout() {
        if (!is_user_logged_in()) {
            return;
        }
        
        $user_id = get_current_user_id();
        $session_id = get_user_meta($user_id, '_tutor_session_id', true);
        
        if ($session_id) {
            global $wpdb;
            
            // Calculate session length
            $session = $wpdb->get_row($wpdb->prepare(
                "SELECT session_start FROM {$wpdb->prefix}tlat_login_sessions WHERE id = %d",
                $session_id
            ));
            
            if ($session) {
                $start = strtotime($session->session_start);
                $end = current_time('timestamp');
                $session_length = $end - $start;
                
                // Only record if session was at least 30 seconds
                if ($session_length >= 30) {
                    $wpdb->update(
                        $wpdb->prefix . 'tlat_login_sessions',
                        array(
                            'session_end' => current_time('mysql'),
                            'session_length_seconds' => $session_length
                        ),
                        array('id' => $session_id),
                        array('%s', '%d'),
                        array('%d')
                    );
                    
                    // Log session end event
                    $this->log_engagement_event($user_id, 'session_end', array(
                        'session_id' => $session_id,
                        'session_length' => $session_length,
                        'session_length_human' => $this->format_session_length($session_length)
                    ));
                }
            }
            
            // Clear session ID
            delete_user_meta($user_id, '_tutor_session_id');
        }
    }

    /**
     * Log engagement event
     */
    private function log_engagement_event($user_id, $event_type, $meta = array()) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'tlat_engagement_events',
            array(
                'user_id' => $user_id,
                'event_type' => $event_type,
                'event_time' => current_time('mysql'),
                'meta' => !empty($meta) ? wp_json_encode($meta) : null
            ),
            array('%d', '%s', '%s', '%s')
        );
    }

    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        // Handle proxied requests
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
        }
        return sanitize_text_field($ip);
    }

    /**
     * Format session length in human readable format
     */
    private function format_session_length($seconds) {
        if ($seconds < 60) {
            return sprintf(_n('%d second', '%d seconds', $seconds, 'tutor-lms-advanced-tracking'), $seconds);
        }
        
        $minutes = floor($seconds / 60);
        if ($minutes < 60) {
            return sprintf(_n('%d minute', '%d minutes', $minutes, 'tutor-lms-advanced-tracking'), $minutes);
        }
        
        $hours = floor($minutes / 60);
        $remaining_minutes = $minutes % 60;
        
        if ($hours < 24) {
            return sprintf(
                _n('%d hour', '%d hours', $hours, 'tutor-lms-advanced-tracking') . 
                ($remaining_minutes > 0 ? ', ' . _n('%d minute', '%d minutes', $remaining_minutes, 'tutor-lms-advanced-tracking') : ''),
                $hours,
                $remaining_minutes
            );
        }
        
        $days = floor($hours / 24);
        $remaining_hours = $hours % 24;
        return sprintf(
            _n('%d day', '%d days', $days, 'tutor-lms-advanced-tracking') . 
            ($remaining_hours > 0 ? ', ' . _n('%d hour', '%d hours', $remaining_hours, 'tutor-lms-advanced-tracking') : ''),
            $days,
            $remaining_hours
        );
    }

    /**
     * Handle AJAX requests for engagement data
     */
    public function handle_engagement_data_ajax() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'tutor_advanced_engagement_' . get_current_user_id())) {
            wp_send_json_error(__('Security check failed', 'tutor-lms-advanced-tracking'));
        }
        
        if (!current_user_can('manage_options') && !current_user_can('tutor_instructor')) {
            wp_send_json_error(__('Insufficient permissions', 'tutor-lms-advanced-tracking'));
        }
        
        $action = sanitize_text_field($_POST['data_action'] ?? '');
        
        switch ($action) {
            case 'login_frequency':
                $data = $this->get_login_frequency_data(intval($_POST['days'] ?? 30));
                break;
            case 'session_lengths':
                $data = $this->get_session_length_data(intval($_POST['days'] ?? 30));
                break;
            case 'top_active_students':
                $data = $this->get_top_active_students(intval($_POST['limit'] ?? 10));
                break;
            case 'engagement_overview':
                $data = $this->get_engagement_overview();
                break;
            default:
                wp_send_json_error(__('Invalid action', 'tutor-lms-advanced-tracking'));
        }
        
        wp_send_json_success($data);
    }

    /**
     * Get login frequency data for chart
     */
    public function get_login_frequency_data($days = 30) {
        global $wpdb;
        
        $end_date = current_time('Y-m-d');
        $start_date = date('Y-m-d', strtotime("-{$days} days"));
        
        $data = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DATE(session_start) as login_date,
                COUNT(DISTINCT user_id) as unique_users,
                COUNT(*) as total_logins
             FROM {$wpdb->prefix}tlat_login_sessions
             WHERE session_start BETWEEN %s AND %s
             GROUP BY DATE(session_start)
             ORDER BY login_date ASC",
            $start_date . ' 00:00:00',
            $end_date . ' 23:59:59'
        ));
        
        // Fill in missing dates
        $labels = array();
        $unique_users = array();
        $total_logins = array();
        
        $current = strtotime($start_date);
        $end = strtotime($end_date);
        
        while ($current <= $end) {
            $date_str = date('Y-m-d', $current);
            $labels[] = date('M j', $current);
            
            $found_unique = false;
            $found_total = false;
            
            foreach ($data as $row) {
                if ($row->login_date === $date_str) {
                    $unique_users[] = intval($row->unique_users);
                    $total_logins[] = intval($row->total_logins);
                    $found_unique = true;
                    $found_total = true;
                    break;
                }
            }
            
            if (!$found_unique) {
                $unique_users[] = 0;
                $total_logins[] = 0;
            }
            
            $current = strtotime('+1 day', $current);
        }
        
        return array(
            'type' => 'line',
            'title' => __('Login Frequency', 'tutor-lms-advanced-tracking'),
            'labels' => $labels,
            'datasets' => array(
                array(
                    'label' => __('Unique Users', 'tutor-lms-advanced-tracking'),
                    'data' => $unique_users,
                    'borderColor' => '#007cba',
                    'backgroundColor' => 'rgba(0, 124, 186, 0.1)',
                    'fill' => true,
                    'tension' => 0.3
                ),
                array(
                    'label' => __('Total Logins', 'tutor-lms-advanced-tracking'),
                    'data' => $total_logins,
                    'borderColor' => '#28a745',
                    'backgroundColor' => 'rgba(40, 167, 69, 0.1)',
                    'fill' => true,
                    'tension' => 0.3
                )
            ),
            'options' => array(
                'responsive' => true,
                'maintainAspectRatio' => false,
                'plugins' => array(
                    'legend' => array(
                        'position' => 'top'
                    )
                ),
                'scales' => array(
                    'y' => array(
                        'beginAtZero' => true,
                        'ticks' => array(
                            'stepSize' => 1
                        )
                    )
                )
            )
        );
    }

    /**
     * Get session length data and distribution
     */
    public function get_session_length_data($days = 30) {
        global $wpdb;
        
        $end_date = current_time('Y-m-d H:i:s');
        $start_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $sessions = $wpdb->get_results($wpdb->prepare(
            "SELECT session_length_seconds 
             FROM {$wpdb->prefix}tlat_login_sessions
             WHERE session_end IS NOT NULL
             AND session_start BETWEEN %s AND %s
             AND session_length_seconds >= 30",
            $start_date, $end_date
        ));
        
        // Create distribution buckets
        $buckets = array(
            '< 5 min' => 0,
            '5-15 min' => 0,
            '15-30 min' => 0,
            '30-60 min' => 0,
            '1-2 hours' => 0,
            '2-4 hours' => 0,
            '> 4 hours' => 0
        );
        
        $total_length = 0;
        $valid_sessions = 0;
        
        foreach ($sessions as $session) {
            $length = intval($session->session_length_seconds);
            $minutes = $length / 60;
            
            if ($minutes < 5) {
                $buckets['< 5 min']++;
            } elseif ($minutes < 15) {
                $buckets['5-15 min']++;
            } elseif ($minutes < 30) {
                $buckets['15-30 min']++;
            } elseif ($minutes < 60) {
                $buckets['30-60 min']++;
            } elseif ($minutes < 120) {
                $buckets['1-2 hours']++;
            } elseif ($minutes < 240) {
                $buckets['2-4 hours']++;
            } else {
                $buckets['> 4 hours']++;
            }
            
            $total_length += $length;
            $valid_sessions++;
        }
        
        $average_length = $valid_sessions > 0 ? round($total_length / $valid_sessions) : 0;
        $average_minutes = round($average_length / 60, 1);
        
        return array(
            'type' => 'bar',
            'title' => __('Session Length Distribution', 'tutor-lms-advanced-tracking'),
            'labels' => array_keys($buckets),
            'datasets' => array(
                array(
                    'label' => __('Sessions', 'tutor-lms-advanced-tracking'),
                    'data' => array_values($buckets),
                    'backgroundColor' => array(
                        '#ff6b6b',
                        '#ffa502',
                        '#ffd93d',
                        '#6bcb77',
                        '#4d96ff',
                        '#9b59b6',
                        '#34495e'
                    ),
                    'borderWidth' => 1
                )
            ),
            'summary' => array(
                'total_sessions' => $valid_sessions,
                'average_minutes' => $average_minutes,
                'average_formatted' => $this->format_session_length($average_length)
            ),
            'options' => array(
                'responsive' => true,
                'maintainAspectRatio' => false,
                'plugins' => array(
                    'legend' => array(
                        'display' => false
                    )
                ),
                'scales' => array(
                    'y' => array(
                        'beginAtZero' => true,
                        'ticks' => array(
                            'stepSize' => 1
                        ),
                        'title' => array(
                            'display' => true,
                            'text' => __('Number of Sessions', 'tutor-lms-advanced-tracking')
                        )
                    ),
                    'x' => array(
                        'title' => array(
                            'display' => true,
                            'text' => __('Session Duration', 'tutor-lms-advanced-tracking')
                        )
                    )
                )
            )
        );
    }

    /**
     * Get top active students
     */
    public function get_top_active_students($limit = 10) {
        global $wpdb;
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                s.user_id,
                u.display_name,
                u.user_email,
                COUNT(s.id) as session_count,
                SUM(s.session_length_seconds) as total_time_seconds,
                MAX(s.session_start) as last_login
             FROM {$wpdb->prefix}tlat_login_sessions s
             JOIN {$wpdb->users} u ON s.user_id = u.ID
             WHERE s.session_end IS NOT NULL
             AND s.session_length_seconds >= 30
             GROUP BY s.user_id, u.display_name, u.user_email
             ORDER BY session_count DESC, total_time_seconds DESC
             LIMIT %d",
            $limit
        ));
        
        $students = array();
        
        foreach ($results as $row) {
            $total_minutes = round(intval($row->total_time_seconds) / 60);
            $hours = floor($total_minutes / 60);
            $mins = $total_minutes % 60;
            
            $students[] = array(
                'id' => intval($row->user_id),
                'name' => $row->display_name,
                'email' => $this->mask_email($row->user_email),
                'sessions' => intval($row->session_count),
                'total_time' => $hours > 0 
                    ? sprintf(__('%dh %dm', 'tutor-lms-advanced-tracking'), $hours, $mins)
                    : sprintf(__('%dm', 'tutor-lms-advanced-tracking'), $mins),
                'last_login' => human_time_diff(strtotime($row->last_login), current_time('timestamp')) . ' ago'
            );
        }
        
        return array(
            'title' => __('Top Active Students', 'tutor-lms-advanced-tracking'),
            'students' => $students,
            'headers' => array(
                __('Student', 'tutor-lms-advanced-tracking'),
                __('Sessions', 'tutor-lms-advanced-tracking'),
                __('Total Time', 'tutor-lms-advanced-tracking'),
                __('Last Active', 'tutor-lms-advanced-tracking')
            )
        );
    }

    /**
     * Get engagement overview stats
     */
    public function get_engagement_overview() {
        global $wpdb;
        
        $today = current_time('Y-m-d');
        $week_ago = date('Y-m-d', strtotime('-7 days'));
        $month_ago = date('Y-m-d', strtotime('-30 days'));
        
        // Today's stats
        $today_logins = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->prefix}tlat_login_sessions 
             WHERE DATE(session_start) = %s",
            $today
        ));
        
        // This week's unique users
        $week_users = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->prefix}tlat_login_sessions 
             WHERE session_start >= %s",
            $week_ago . ' 00:00:00'
        ));
        
        // This month's unique users
        $month_users = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->prefix}tlat_login_sessions 
             WHERE session_start >= %s",
            $month_ago . ' 00:00:00'
        ));
        
        // Average session length this week
        $avg_session = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(session_length_seconds) FROM {$wpdb->prefix}tlat_login_sessions 
             WHERE session_end IS NOT NULL AND session_start >= %s",
            $week_ago . ' 00:00:00'
        ));
        
        return array(
            'today_logins' => intval($today_logins),
            'week_users' => intval($week_users),
            'month_users' => intval($month_users),
            'avg_session_length' => $this->format_session_length(round($avg_session ?? 0))
        );
    }

    /**
     * Mask email for non-admin users
     */
    private function mask_email($email) {
        if (current_user_can('manage_options')) {
            return $email;
        }
        
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return $email;
        }
        
        $username = $parts[0];
        $domain = $parts[1];
        
        $username_length = strlen($username);
        if ($username_length <= 3) {
            $masked_username = substr($username, 0, 1) . '***';
        } else {
            $masked_username = substr($username, 0, 2) . '***' . substr($username, -1);
        }
        
        return $masked_username . '@' . $domain;
    }

    /**
     * Render engagement dashboard section
     */
    public function render_engagement_dashboard() {
        ?>
        <div class="engagement-dashboard-section">
            <div class="section-header">
                <h3><?php _e('Student Engagement', 'tutor-lms-advanced-tracking'); ?></h3>
                <select id="engagement-date-range" class="engagement-select">
                    <option value="7"><?php _e('Last 7 Days', 'tutor-lms-advanced-tracking'); ?></option>
                    <option value="14"><?php _e('Last 14 Days', 'tutor-lms-advanced-tracking'); ?></option>
                    <option value="30" selected><?php _e('Last 30 Days', 'tutor-lms-advanced-tracking'); ?></option>
                    <option value="60"><?php _e('Last 60 Days', 'tutor-lms-advanced-tracking'); ?></option>
                    <option value="90"><?php _e('Last 90 Days', 'tutor-lms-advanced-tracking'); ?></option>
                </select>
            </div>
            
            <!-- Overview Stats Cards -->
            <div class="engagement-overview">
                <div class="stat-card">
                    <span class="stat-icon">👥</span>
                    <div class="stat-content">
                        <span class="stat-value" id="engagement-today-logins">-</span>
                        <span class="stat-label"><?php _e('Today\'s Active Users', 'tutor-lms-advanced-tracking'); ?></span>
                    </div>
                </div>
                <div class="stat-card">
                    <span class="stat-icon">📅</span>
                    <div class="stat-content">
                        <span class="stat-value" id="engagement-week-users">-</span>
                        <span class="stat-label"><?php _e('This Week', 'tutor-lms-advanced-tracking'); ?></span>
                    </div>
                </div>
                <div class="stat-card">
                    <span class="stat-icon">📆</span>
                    <div class="stat-content">
                        <span class="stat-value" id="engagement-month-users">-</span>
                        <span class="stat-label"><?php _e('This Month', 'tutor-lms-advanced-tracking'); ?></span>
                    </div>
                </div>
                <div class="stat-card">
                    <span class="stat-icon">⏱️</span>
                    <div class="stat-content">
                        <span class="stat-value" id="engagement-avg-session">-</span>
                        <span class="stat-label"><?php _e('Avg. Session Length', 'tutor-lms-advanced-tracking'); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Charts Grid -->
            <div class="engagement-charts">
                <div class="chart-wrapper">
                    <h4><?php _e('Login Frequency', 'tutor-lms-advanced-tracking'); ?></h4>
                    <canvas id="login-frequency-chart" height="250"></canvas>
                </div>
                <div class="chart-wrapper">
                    <h4><?php _e('Session Length Distribution', 'tutor-lms-advanced-tracking'); ?></h4>
                    <canvas id="session-length-chart" height="250"></canvas>
                </div>
            </div>
            
            <!-- Top Active Students Table -->
            <div class="top-students-section">
                <h4><?php _e('Top Active Students', 'tutor-lms-advanced-tracking'); ?></h4>
                <table class="top-students-table">
                    <thead>
                        <tr>
                            <th><?php _e('Student', 'tutor-lms-advanced-tracking'); ?></th>
                            <th><?php _e('Sessions', 'tutor-lms-advanced-tracking'); ?></th>
                            <th><?php _e('Total Time', 'tutor-lms-advanced-tracking'); ?></th>
                            <th><?php _e('Last Active', 'tutor-lms-advanced-tracking'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="top-students-body">
                        <tr><td colspan="4" class="loading"><?php _e('Loading...', 'tutor-lms-advanced-tracking'); ?></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
}
