<?php
if (!defined('ABSPATH')) { exit; }
if (defined('WP_CLI') && WP_CLI) {
    class TutorAdvancedTracking_CLI {
        public function migrate() {
            if (!class_exists('TutorAdvancedTracking_EventsDB')) {
                require_once plugin_dir_path(__FILE__) . 'class-events-db.php';
            }
            TutorAdvancedTracking_EventsDB::install();
            \WP_CLI::success('Tutor Advanced Tracking DB migrated');
        }
    }
    \WP_CLI::add_command('tlat', 'TutorAdvancedTracking_CLI');
}
