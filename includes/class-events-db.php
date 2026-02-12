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
        foreach ($sql as $statement) { dbDelta($statement); }
    }
    public static function uninstall() {
        global $wpdb;
        $tables = array($wpdb->prefix . 'tlat_events', $wpdb->prefix . 'tlat_agg_daily');
        foreach ($tables as $t) { $wpdb->query("DROP TABLE IF EXISTS {$t}"); }
    }
}
