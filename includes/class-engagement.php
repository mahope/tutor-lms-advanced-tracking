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
            case 'device_browser':
                $data = $this->get_device_browser_data(intval($_POST['days'] ?? 30));
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
            
            <!-- Device/Browser Breakdown Charts -->
            <div class="device-browser-section" style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e0e0e0;">
                <h4 style="margin-bottom: 15px;"><?php _e('Device & Browser Breakdown', 'tutor-lms-advanced-tracking'); ?></h4>
                <p class="description" style="margin-bottom: 15px; color: #666;">
                    <?php _e('Shows the distribution of devices and browsers used by students to access the learning platform.', 'tutor-lms-advanced-tracking'); ?>
                </p>
                
                <div class="device-browser-charts" style="display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 20px;">
                    <div class="chart-wrapper-half" style="flex: 1; min-width: 280px; max-width: 400px; background: #fff; padding: 15px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                        <h5 style="margin: 0 0 10px 0; font-size: 14px; color: #555;">
                            <span style="margin-right: 8px;">💻</span><?php _e('Device Type', 'tutor-lms-advanced-tracking'); ?>
                        </h5>
                        <div style="position: relative; height: 220px;">
                            <canvas id="device-chart"></canvas>
                        </div>
                    </div>
                    
                    <div class="chart-wrapper-half" style="flex: 1; min-width: 280px; max-width: 400px; background: #fff; padding: 15px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                        <h5 style="margin: 0 0 10px 0; font-size: 14px; color: #555;">
                            <span style="margin-right: 8px;">🌐</span><?php _e('Browser', 'tutor-lms-advanced-tracking'); ?>
                        </h5>
                        <div style="position: relative; height: 220px;">
                            <canvas id="browser-chart"></canvas>
                        </div>
                    </div>
                </div>
                
                <!-- Summary Table -->
                <div id="device-browser-summary" class="device-browser-summary" style="background: #f9f9f9; padding: 15px; border-radius: 8px;">
                    <table class="widefat" style="border-collapse: collapse; margin: 0;">
                        <thead>
                            <tr style="background: #f5f5f5;">
                                <th style="padding: 10px; text-align: left; border: 1px solid #e0e0e0; font-weight: 600;">
                                    <?php _e('Device / Browser', 'tutor-lms-advanced-tracking'); ?>
                                </th>
                                <th style="padding: 10px; text-align: center; border: 1px solid #e0e0e0; font-weight: 600; width: 100px;">
                                    <?php _e('Sessions', 'tutor-lms-advanced-tracking'); ?>
                                </th>
                                <th style="padding: 10px; text-align: center; border: 1px solid #e0e0e0; font-weight: 600; width: 100px;">
                                    <?php _e('Percentage', 'tutor-lms-advanced-tracking'); ?>
                                </th>
                            </tr>
                        </thead>
                        <tbody id="device-browser-tbody">
                            <tr>
                                <td colspan="3" style="padding: 20px; text-align: center; border: 1px solid #e0e0e0;">
                                    <span class="loading"><?php _e('Loading...', 'tutor-lms-advanced-tracking'); ?></span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
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
    
    // =========================================================================
    // Device/Browser Tracking Methods
    // =========================================================================

    /**
     * Parse user agent to extract device type
     */
    public function parse_device_type($user_agent) {
        if (empty($user_agent)) {
            return __('Unknown', 'tutor-lms-advanced-tracking');
        }

        $user_agent = strtolower($user_agent);

        // Check for mobile devices
        if (preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i', $user_agent) || 
            preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|g450|gp\-m|t(e|v)|goto|hiptop|hs(c|\-i)|hp(i|ip)|hs\-l|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i', substr($user_agent, 0, 4))) {
            return __('Mobile', 'tutor-lms-advanced-tracking');
        }

        // Check for tablets
        if (preg_match('/tablet|ipad|playbook|silk|kindle|hp\s+tablet|tab|gt\-p1000|sm\-t|xoom|sch\-i800|tablet/i', $user_agent)) {
            return __('Tablet', 'tutor-lms-advanced-tracking');
        }

        // Default to desktop
        return __('Desktop', 'tutor-lms-advanced-tracking');
    }

    /**
     * Parse user agent to extract browser name
     */
    public function parse_browser($user_agent) {
        if (empty($user_agent)) {
            return __('Unknown', 'tutor-lms-advanced-tracking');
        }

        $user_agent = strtolower($user_agent);

        // Check for browsers in order of specificity
        if (preg_match('/edg\/([0-9.]+)/', $user_agent, $matches)) {
            return sprintf(__('Edge %s', 'tutor-lms-advanced-tracking'), explode('.', $matches[1])[0]);
        }

        if (preg_match('/opr\/([0-9.]+)/', $user_agent, $matches)) {
            return sprintf(__('Opera %s', 'tutor-lms-advanced-tracking'), explode('.', $matches[1])[0]);
        }

        if (preg_match('/chrome\/([0-9.]+)/', $user_agent, $matches)) {
            return sprintf(__('Chrome %s', 'tutor-lms-advanced-tracking'), explode('.', $matches[1])[0]);
        }

        if (preg_match('/firefox\/([0-9.]+)/', $user_agent, $matches)) {
            return sprintf(__('Firefox %s', 'tutor-lms-advanced-tracking'), explode('.', $matches[1])[0]);
        }

        if (preg_match('/version\/([0-9.]+).*safari/', $user_agent, $matches)) {
            // Safari must be checked after Chrome/Firefox to avoid false positives
            return sprintf(__('Safari %s', 'tutor-lms-advanced-tracking'), explode('.', $matches[1])[0]);
        }

        if (preg_match('/msie\s+([0-9.]+)/', $user_agent, $matches) || 
            preg_match('/trident\/.*rv:([0-9.]+)/', $user_agent, $matches)) {
            return sprintf(__('Internet Explorer %s', 'tutor-lms-advanced-tracking'), explode('.', $matches[1])[0]);
        }

        // Fallback for less common browsers
        if (strpos($user_agent, 'bingbot') !== false || strpos($user_agent, 'msnbot') !== false) {
            return __('Bing Bot', 'tutor-lms-advanced-tracking');
        }

        if (strpos($user_agent, 'googlebot') !== false) {
            return __('Google Bot', 'tutor-lms-advanced-tracking');
        }

        if (strpos($user_agent, 'bot') !== false || strpos($user_agent, 'crawler') !== false) {
            return __('Bot', 'tutor-lms-advanced-tracking');
        }

        return __('Other', 'tutor-lms-advanced-tracking');
    }

    /**
     * Get device/browser breakdown data with caching
     * Stores cached data in wp_options for better performance
     */
    public function get_device_browser_data($days = 30, $force_refresh = false) {
        $cache_key = 'tlat_device_browser_data_' . $days;

        // Try to get cached data first (unless force refresh)
        if (!$force_refresh) {
            $cached_data = get_transient($cache_key);
            if ($cached_data !== false) {
                return $cached_data;
            }
        }

        global $wpdb;

        $start_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        $end_date = current_time('mysql');

        // Get all sessions with user agents
        $sessions = $wpdb->get_results($wpdb->prepare(
            "SELECT user_agent 
             FROM {$wpdb->prefix}tlat_login_sessions 
             WHERE session_start >= %s 
             AND user_agent IS NOT NULL 
             AND user_agent != ''",
            $start_date
        ));

        // Initialize counters
        $devices = array(
            __('Desktop', 'tutor-lms-advanced-tracking') => 0,
            __('Mobile', 'tutor-lms-advanced-tracking') => 0,
            __('Tablet', 'tutor-lms-advanced-tracking') => 0,
            __('Unknown', 'tutor-lms-advanced-tracking') => 0
        );

        $browsers = array();
        $device_browser_combinations = array();

        // Parse user agents
        foreach ($sessions as $session) {
            $device = $this->parse_device_type($session->user_agent);
            $browser = $this->parse_browser($session->user_agent);

            // Count devices
            if (isset($devices[$device])) {
                $devices[$device]++;
            } else {
                $devices[$device] = $devices[__('Unknown', 'tutor-lms-advanced-tracking')] + 1;
            }

            // Count browsers
            if (!isset($browsers[$browser])) {
                $browsers[$browser] = 0;
            }
            $browsers[$browser]++;

            // Count combinations
            $combo_key = $device . ' / ' . $browser;
            if (!isset($device_browser_combinations[$combo_key])) {
                $device_browser_combinations[$combo_key] = 0;
            }
            $device_browser_combinations[$combo_key]++;
        }

        // Calculate totals and percentages
        $total_sessions = count($sessions);

        // Prepare device data with percentages
        $device_data = array();
        foreach ($devices as $device_name => $count) {
            if ($count > 0) {
                $percentage = round(($count / $total_sessions) * 100, 1);
                $device_data[] = array(
                    'label' => $device_name,
                    'count' => $count,
                    'percentage' => $percentage
                );
            }
        }

        // Sort devices by count descending
        usort($device_data, function($a, $b) {
            return $b['count'] - $a['count'];
        });

        // Prepare browser data with percentages
        $browser_data = array();
        foreach ($browsers as $browser_name => $count) {
            if ($count > 0) {
                $percentage = round(($count / $total_sessions) * 100, 1);
                $browser_data[] = array(
                    'label' => $browser_name,
                    'count' => $count,
                    'percentage' => $percentage
                );
            }
        }

        // Sort browsers by count descending, keep top 8
        usort($browser_data, function($a, $b) {
            return $b['count'] - $a['count'];
        });
        $browser_data = array_slice($browser_data, 0, 8);

        // Prepare combinations data (top 10)
        $combinations_data = array();
        arsort($device_browser_combinations);
        $combo_count = 0;
        foreach ($device_browser_combinations as $combo => $count) {
            if ($combo_count >= 10) break;
            $percentage = round(($count / $total_sessions) * 100, 1);
            $combinations_data[] = array(
                'label' => $combo,
                'count' => $count,
                'percentage' => $percentage
            );
            $combo_count++;
        }

        $result = array(
            'total_sessions' => $total_sessions,
            'period_days' => $days,
            'devices' => $device_data,
            'browsers' => $browser_data,
            'combinations' => $combinations_data,
            'generated_at' => current_time('mysql')
        );

        // Cache the result for 1 hour (cache can be refreshed via AJAX)
        set_transient($cache_key, $result, 3600);

        return $result;
    }

    /**
     * Get device chart data formatted for Chart.js (donut chart)
     */
    public function get_device_chart_data($days = 30) {
        $data = $this->get_device_browser_data($days);

        $labels = array();
        $counts = array();
        $percentages = array();
        $colors = array(
            '#4285f4', // Desktop - Blue
            '#ea4335', // Mobile - Red
            '#34a853', // Tablet - Green
            '#fbbc04'  // Unknown - Yellow
        );
        $background_colors = array();

        foreach ($data['devices'] as $index => $device) {
            $labels[] = $device['label'] . ' (' . $device['percentage'] . '%)';
            $counts[] = $device['count'];
            $percentages[] = $device['percentage'];
            
            // Assign color based on device type
            $device_lower = strtolower($device['label']);
            if (strpos($device_lower, 'desktop') !== false) {
                $background_colors[] = $colors[0];
            } elseif (strpos($device_lower, 'mobile') !== false) {
                $background_colors[] = $colors[1];
            } elseif (strpos($device_lower, 'tablet') !== false) {
                $background_colors[] = $colors[2];
            } else {
                $background_colors[] = $colors[3];
            }
        }

        return array(
            'type' => 'doughnut',
            'title' => __('Device Distribution', 'tutor-lms-advanced-tracking'),
            'labels' => $labels,
            'datasets' => array(
                array(
                    'label' => __('Sessions', 'tutor-lms-advanced-tracking'),
                    'data' => $counts,
                    'backgroundColor' => $background_colors,
                    'borderWidth' => 2,
                    'borderColor' => '#ffffff',
                    'hoverOffset' => 4
                )
            ),
            'raw_data' => $data['devices'],
            'options' => array(
                'responsive' => true,
                'maintainAspectRatio' => false,
                'cutout' => '60%',
                'plugins' => array(
                    'legend' => array(
                        'position' => 'bottom',
                        'labels' => array(
                            'padding' => 15,
                            'usePointStyle' => true,
                            'font' => array(
                                'size' => 11
                            )
                        )
                    ),
                    'tooltip' => array(
                        'callbacks' => array(
                            'label' => "function(context) {
                                var label = context.label || '';
                                var value = context.parsed || 0;
                                var total = context.dataset.data.reduce(function(a, b) { return a + b; }, 0);
                                var percentage = Math.round((value / total) * 100) + '%';
                                return label + ': ' + value + ' (' + percentage + ')';
                            }"
                        )
                    )
                )
            )
        );
    }

    /**
     * Get browser chart data formatted for Chart.js (donut chart)
     */
    public function get_browser_chart_data($days = 30) {
        $data = $this->get_device_browser_data($days);

        $labels = array();
        $counts = array();
        $percentages = array();
        $colors = array(
            '#4285f4', '#ea4335', '#34a853', '#fbbc04', '#9c27b0',
            '#ff5722', '#795548', '#607d8b', '#e91e63', '#00bcd4'
        );
        $background_colors = array();

        foreach ($data['browsers'] as $index => $browser) {
            $labels[] = $browser['label'];
            $counts[] = $browser['count'];
            $percentages[] = $browser['percentage'];
            $background_colors[] = $colors[$index % count($colors)];
        }

        return array(
            'type' => 'doughnut',
            'title' => __('Browser Distribution', 'tutor-lms-advanced-tracking'),
            'labels' => $labels,
            'datasets' => array(
                array(
                    'label' => __('Sessions', 'tutor-lms-advanced-tracking'),
                    'data' => $counts,
                    'backgroundColor' => $background_colors,
                    'borderWidth' => 2,
                    'borderColor' => '#ffffff',
                    'hoverOffset' => 4
                )
            ),
            'raw_data' => $data['browsers'],
            'options' => array(
                'responsive' => true,
                'maintainAspectRatio' => false,
                'cutout' => '60%',
                'plugins' => array(
                    'legend' => array(
                        'position' => 'bottom',
                        'labels' => array(
                            'padding' => 15,
                            'usePointStyle' => true,
                            'font' => array(
                                'size' => 11
                            )
                        )
                    ),
                    'tooltip' => array(
                        'callbacks' => array(
                            'label' => "function(context) {
                                var label = context.label || '';
                                var value = context.parsed || 0;
                                var total = context.dataset.data.reduce(function(a, b) { return a + b; }, 0);
                                var percentage = Math.round((value / total) * 100) + '%';
                                return label + ': ' + value + ' (' + percentage + ')';
                            }"
                        )
                    )
                )
            )
        );
    }

    /**
     * Render device/browser breakdown section in dashboard
     */
    public function render_device_browser_section() {
        ?>
        <div class="device-browser-section" style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e0e0e0;">
            <h4><?php _e('Device & Browser Breakdown', 'tutor-lms-advanced-tracking'); ?></h4>
            <p class="description" style="margin-bottom: 15px; color: #666;">
                <?php _e('Shows the distribution of devices and browsers used by students to access the learning platform.', 'tutor-lms-advanced-tracking'); ?>
            </p>
            
            <div class="device-browser-charts" style="display: flex; gap: 20px; flex-wrap: wrap;">
                <div class="chart-wrapper-half" style="flex: 1; min-width: 280px; max-width: 400px;">
                    <h5 style="margin-bottom: 10px; font-size: 14px; color: #555;">
                        <?php _e('Device Type', 'tutor-lms-advanced-tracking'); ?>
                    </h5>
                    <div style="position: relative; height: 220px;">
                        <canvas id="device-chart" height="200"></canvas>
                    </div>
                    <div id="device-legend" class="chart-legend" style="margin-top: 10px;"></div>
                </div>
                
                <div class="chart-wrapper-half" style="flex: 1; min-width: 280px; max-width: 400px;">
                    <h5 style="margin-bottom: 10px; font-size: 14px; color: #555;">
                        <?php _e('Browser', 'tutor-lms-advanced-tracking'); ?>
                    </h5>
                    <div style="position: relative; height: 220px;">
                        <canvas id="browser-chart" height="200"></canvas>
                    </div>
                    <div id="browser-legend" class="chart-legend" style="margin-top: 10px;"></div>
                </div>
            </div>
            
            <!-- Summary Table -->
            <div id="device-browser-summary" style="margin-top: 20px;">
                <table class="widefat" style="border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f5f5f5;">
                            <th style="padding: 10px; text-align: left; border: 1px solid #e0e0e0;">
                                <?php _e('Category', 'tutor-lms-advanced-tracking'); ?>
                            </th>
                            <th style="padding: 10px; text-align: left; border: 1px solid #e0e0e0;">
                                <?php _e('Sessions', 'tutor-lms-advanced-tracking'); ?>
                            </th>
                            <th style="padding: 10px; text-align: left; border: 1px solid #e0e0e0;">
                                <?php _e('Percentage', 'tutor-lms-advanced-tracking'); ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody id="device-browser-tbody">
                        <tr>
                            <td colspan="3" style="padding: 20px; text-align: center; border: 1px solid #e0e0e0;">
                                <span class="spinner is-active" style="float: none; margin: 0 auto;"></span>
                                <span style="display: block; margin-top: 10px;">
                                    <?php _e('Loading data...', 'tutor-lms-advanced-tracking'); ?>
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
}
