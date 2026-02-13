<?php
// Prevent direct access
if (!defined('ABSPATH')) { exit; }

/**
 * Events/Analytics DB schema and migrator for Tutor Advanced Tracking
 */
class TutorAdvancedTracking_EventsDB {
    public static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset_collate = $wpdb->get_charset_collate();
        $events_table = $wpdb->prefix . 'tlat_events';
        $agg_table    = $wpdb->prefix . 'tlat_agg_daily';
        $sql = array();
        $sql[] = "CREATE TABLE {$events_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NULL,
            course_id BIGINT UNSIGNED NULL,
            lesson_id BIGINT UNSIGNED NULL,
            event_type VARCHAR(64) NOT NULL,
            event_time DATETIME NOT NULL,
            meta LONGTEXT NULL,
            PRIMARY KEY (id),
            KEY evt_time (event_time),
            KEY evt_type (event_type),
            KEY course_user (course_id, user_id),
            KEY lesson (lesson_id)
        ) {$charset_collate};";
        $sql[] = "CREATE TABLE {$agg_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            course_id BIGINT UNSIGNED NOT NULL,
            stat_date DATE NOT NULL,
            metric VARCHAR(64) NOT NULL,
            value BIGINT NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY course_date_metric (course_id, stat_date, metric),
            KEY stat_date (stat_date)
        ) {$charset_collate};";
        // Video watch progress tracking
        $video_table = $wpdb->prefix . 'tlat_video_progress';
        $sql[] = "CREATE TABLE {$video_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            course_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            lesson_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            video_url VARCHAR(2048) NOT NULL,
            video_provider VARCHAR(32) NOT NULL DEFAULT 'html5',
            duration INT UNSIGNED NOT NULL DEFAULT 0,
            watched_seconds INT UNSIGNED NOT NULL DEFAULT 0,
            max_position INT UNSIGNED NOT NULL DEFAULT 0,
            completion_pct DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            play_count INT UNSIGNED NOT NULL DEFAULT 1,
            segments_watched LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY user_video (user_id, video_url(191), lesson_id),
            KEY course_id (course_id),
            KEY lesson_id (lesson_id),
            KEY completion (completion_pct),
            KEY provider (video_provider),
            KEY updated (updated_at)
        ) {$charset_collate};";

        // Login sessions tracking
        $login_sessions_table = $wpdb->prefix . 'tlat_login_sessions';
        $sql[] = "CREATE TABLE {$login_sessions_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            session_start DATETIME NOT NULL,
            session_end DATETIME NULL,
            last_activity DATETIME NOT NULL,
            session_length_seconds INT UNSIGNED NULL,
            ip_address VARCHAR(45) NULL,
            user_agent VARCHAR(500) NULL,
            session_data LONGTEXT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY session_start (session_start),
            KEY session_end (session_end),
            KEY activity (last_activity),
            KEY ip_address (ip_address(45))
        ) {$charset_collate};";

        // Engagement events tracking
        $engagement_events_table = $wpdb->prefix . 'tlat_engagement_events';
        $sql[] = "CREATE TABLE {$engagement_events_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            event_type VARCHAR(64) NOT NULL,
            event_time DATETIME NOT NULL,
            meta LONGTEXT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY event_type (event_type),
            KEY event_time (event_time)
        ) {$charset_collate};";

        foreach ($sql as $statement) { dbDelta($statement); }
    }
    public static function uninstall() {
        global $wpdb;
        $tables = array(
            $wpdb->prefix . 'tlat_events',
            $wpdb->prefix . 'tlat_agg_daily',
            $wpdb->prefix . 'tlat_video_progress',
            $wpdb->prefix . 'tlat_login_sessions',
            $wpdb->prefix . 'tlat_engagement_events',
        );
        foreach ($tables as $t) { $wpdb->query("DROP TABLE IF EXISTS {$t}"); }
    }
}
