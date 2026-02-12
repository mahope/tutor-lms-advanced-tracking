<?php
if (!defined('WP_UNINSTALL_PLUGIN')) { exit; }
if (!class_exists('TutorAdvancedTracking_EventsDB')) {
    require_once __DIR__ . '/includes/class-events-db.php';
}
if (defined('TUTOR_ADVANCED_TRACKING_PURGE_ON_UNINSTALL') && TUTOR_ADVANCED_TRACKING_PURGE_ON_UNINSTALL) {
    TutorAdvancedTracking_EventsDB::uninstall();
}
