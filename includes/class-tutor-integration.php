<?php
/**
 * Tutor LMS Integration Layer
 * Provides standardized access to Tutor LMS data using proper APIs
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class TutorAdvancedTracking_TutorIntegration {

    /**
     * Cache resolved table names to avoid repeated lookups.
     *
     * @var array<string, string|false>
     */
    private static $table_cache = array();

    /**
     * Cached enrollment column map.
     *
     * @var array|null
     */
    private static $enrollment_columns = null;

    /**
     * Cached enrollment rows keyed by course:user pairs.
     *
     * @var array<string, object|null>
     */
    private static $enrollment_record_cache = array();

    /**
     * Guard to prevent recursive table detection during query filtering.
     *
     * @var bool
     */
    private static $inside_table_detection = false;

    /**
     * Tracks whether legacy table replacement has been hooked.
     *
     * @var bool
     */
    private static $alias_filter_attached = false;


    /**
     * Get course post type using Tutor LMS API
     */
    public static function get_course_post_type() {
        if (function_exists('tutor') && isset(tutor()->course_post_type)) {
            return tutor()->course_post_type;
        }
        
        // Fallback check for different Tutor LMS versions
        if (defined('TUTOR_COURSE_POST_TYPE')) {
            return TUTOR_COURSE_POST_TYPE;
        }
        
        return 'courses'; // Default fallback
    }
    
    /**
     * Get lesson post type using Tutor LMS API
     */
    public static function get_lesson_post_type() {
        if (function_exists('tutor') && isset(tutor()->lesson_post_type)) {
            return tutor()->lesson_post_type;
        }
        
        if (defined('TUTOR_LESSON_POST_TYPE')) {
            return TUTOR_LESSON_POST_TYPE;
        }
        
        return 'tutor_lesson'; // Default fallback
    }
    
    /**
     * Get quiz post type using Tutor LMS API
     */
    public static function get_quiz_post_type() {
        if (function_exists('tutor') && isset(tutor()->quiz_post_type)) {
            return tutor()->quiz_post_type;
        }
        
        if (defined('TUTOR_QUIZ_POST_TYPE')) {
            return TUTOR_QUIZ_POST_TYPE;
        }
        
        return 'tutor_quiz'; // Default fallback
    }
    
    /**
     * Resolve Tutor LMS table names across versions.
     *
     * @param string $slug Table identifier.
     * @return string|false
     */
    public static function get_table_name($slug) {
        if (isset(self::$table_cache[$slug])) {
            return self::$table_cache[$slug];
        }

        self::$inside_table_detection = true;

        try {
            foreach (self::get_table_candidates($slug) as $table_name) {
                if (self::table_exists($table_name)) {
                    self::$table_cache[$slug] = $table_name;
                    return $table_name;
                }
            }
        } finally {
            self::$inside_table_detection = false;
        }

        self::$table_cache[$slug] = false;
        return false;
    }

    /**
     * Get the enrollment table name helper.
     *
     * @return string|false
     */
    public static function get_enrollments_table_name() {
        return self::get_table_name('enrollments');
    }

    /**
     * Get the lesson activity table name helper.
     *
     * @return string|false
     */
    public static function get_lesson_activity_table_name() {
        return self::get_table_name('lesson_activities');
    }

    /**
     * Provide candidate table names for Tutor LMS across versions.
     *
     * @param string $slug
     * @return array<int, string>
     */
    private static function get_table_candidates($slug) {
        global $wpdb;

        switch ($slug) {
            case 'enrollments':
                return array(
                    $wpdb->prefix . 'tutor_enrollments',
                    $wpdb->prefix . 'tutor_enrolled'
                );

            case 'lesson_activities':
                return array(
                    $wpdb->prefix . 'tutor_lesson_activities',
                    $wpdb->prefix . 'tutor_activities'
                );

            default:
                return array();
        }
    }

    /**
     * Describe a table to obtain column names.
     *
     * @param string $table_name
     * @return array<int, string>
     */
    private static function describe_table($table_name) {
        global $wpdb;

        $columns = $wpdb->get_results("DESCRIBE {$table_name}");

        if (empty($columns) || !is_array($columns)) {
            return array();
        }

        if (!function_exists('wp_list_pluck')) {
            require_once ABSPATH . 'wp-includes/functions.php';
        }

        return wp_list_pluck($columns, 'Field');
    }

    /**
     * Map enrollment table columns to a normalized structure.
     *
     * @return array<string, string|null>
     */
    public static function get_enrollment_columns_map() {
        if (null !== self::$enrollment_columns) {
            return self::$enrollment_columns;
        }

        $table_name = self::get_enrollments_table_name();
        if (!$table_name) {
            self::$enrollment_columns = array();
            return self::$enrollment_columns;
        }

        $columns = self::describe_table($table_name);

        self::$enrollment_columns = array(
            'id' => in_array('id', $columns, true) ? 'id' : (in_array('enrollment_id', $columns, true) ? 'enrollment_id' : null),
            'user_id' => in_array('user_id', $columns, true) ? 'user_id' : null,
            'course_id' => in_array('course_id', $columns, true) ? 'course_id' : null,
            'enrollment_date' => in_array('enrollment_date', $columns, true) ? 'enrollment_date' : (in_array('time', $columns, true) ? 'time' : null),
            'completion_date' => in_array('completion_date', $columns, true) ? 'completion_date' : (in_array('completed_at', $columns, true) ? 'completed_at' : null),
            'is_completed' => in_array('is_completed', $columns, true) ? 'is_completed' : null,
            'status' => in_array('status', $columns, true) ? 'status' : null
        );

        return self::$enrollment_columns;
    }

    /**
     * Build a completion condition that works across Tutor LMS schemas.
     *
     * @param string $alias SQL table alias.
     * @return string
     */
    public static function get_enrollment_completion_condition($alias = 'e') {
        $columns = self::get_enrollment_columns_map();

        $conditions = array();

        if (!empty($columns['is_completed'])) {
            $conditions[] = "{$alias}.{$columns['is_completed']} = 1";
        }

        if (!empty($columns['completion_date'])) {
            $conditions[] = "{$alias}.{$columns['completion_date']} IS NOT NULL";
        }

        if (!empty($columns['status'])) {
            $conditions[] = "{$alias}.{$columns['status']} IN ('completed', 'complete', 'passed', 'publish')";
        }

        if (empty($conditions)) {
            return '0=1';
        }

        return implode(' OR ', array_unique($conditions));
    }

    /**
     * Normalize enrollment datetime values.
     *
     * @param mixed $value Raw database value.
     * @return string
     */
    public static function format_enrollment_datetime($value) {
        if (empty($value) || '0000-00-00 00:00:00' === $value) {
            return '';
        }

        if (is_numeric($value)) {
            return gmdate('Y-m-d H:i:s', (int) $value);
        }

        return (string) $value;
    }

    /**
     * Retrieve an enrollment record for a given course/user pair.
     *
     * @param int $course_id Course ID.
     * @param int $user_id User ID.
     * @return object|null
     */
    public static function get_enrollment_record($course_id, $user_id) {
        $course_id = (int) $course_id;
        $user_id = (int) $user_id;
        $cache_key = $course_id . ':' . $user_id;

        if (isset(self::$enrollment_record_cache[$cache_key])) {
            return self::$enrollment_record_cache[$cache_key];
        }

        $table_name = self::get_enrollments_table_name();
        if (!$table_name) {
            self::$enrollment_record_cache[$cache_key] = null;
            return null;
        }

        $columns = self::get_enrollment_columns_map();
        if (empty($columns['course_id']) || empty($columns['user_id'])) {
            self::$enrollment_record_cache[$cache_key] = null;
            return null;
        }

        global $wpdb;

        $alias = 'e';

        $fields = array(
            "{$alias}.{$columns['user_id']} AS user_id"
        );

        if (!empty($columns['enrollment_date'])) {
            $fields[] = "{$alias}.{$columns['enrollment_date']} AS enrollment_date";
        } else {
            $fields[] = 'NULL AS enrollment_date';
        }

        if (!empty($columns['completion_date'])) {
            $fields[] = "{$alias}.{$columns['completion_date']} AS completion_date";
        } else {
            $fields[] = 'NULL AS completion_date';
        }

        if (!empty($columns['is_completed'])) {
            $fields[] = "{$alias}.{$columns['is_completed']} AS completion_flag";
        } else {
            $fields[] = 'NULL AS completion_flag';
        }

        if (!empty($columns['status'])) {
            $fields[] = "{$alias}.{$columns['status']} AS status_value";
        } else {
            $fields[] = 'NULL AS status_value';
        }

        $sql = $wpdb->prepare(
            "SELECT " . implode(', ', $fields) . "
             FROM {$table_name} {$alias}
             WHERE {$alias}.{$columns['course_id']} = %d
               AND {$alias}.{$columns['user_id']} = %d
             LIMIT 1",
            $course_id,
            $user_id
        );

        $record = $wpdb->get_row($sql);

        self::$enrollment_record_cache[$cache_key] = $record ? $record : null;

        return self::$enrollment_record_cache[$cache_key];
    }

    /**
     * Determine if an enrollment record is completed.
     *
     * @param object|null $record Enrollment row.
     * @return bool
     */
    public static function enrollment_is_completed($record) {
        if (!$record) {
            return false;
        }

        if (isset($record->completion_flag) && '' !== $record->completion_flag && null !== $record->completion_flag) {
            if (is_numeric($record->completion_flag)) {
                if ((int) $record->completion_flag === 1) {
                    return true;
                }
            } else {
                $flag = strtolower((string) $record->completion_flag);
                if (in_array($flag, array('completed', 'complete', 'yes', 'true', '1'), true)) {
                    return true;
                }
            }
        }

        if (!empty($record->status_value)) {
            $status = strtolower((string) $record->status_value);
            if (in_array($status, array('completed', 'complete', 'passed', 'success', 'publish'), true)) {
                return true;
            }
        }

        if (!empty($record->completion_date) && '0000-00-00 00:00:00' !== $record->completion_date) {
            return true;
        }

        return false;
    }

    /**
     * Ensure legacy Tutor LMS table names are remapped when executing raw SQL.
     */
    public static function ensure_table_alias_filter() {
        if (self::$alias_filter_attached) {
            return;
        }

        self::$alias_filter_attached = true;

        // Prime caches before filters modify queries
        self::get_enrollments_table_name();
        self::get_lesson_activity_table_name();

        add_filter('query', array(__CLASS__, 'filter_legacy_tables'));
    }

    /**
     * Replace legacy Tutor LMS table names with their resolved counterparts.
     *
     * @param string $query SQL query.
     * @return string
     */
    public static function filter_legacy_tables($query) {
        if (self::$inside_table_detection || empty($query) || !is_string($query)) {
            return $query;
        }

        global $wpdb;

        $legacy_enrollments = isset($wpdb->prefix) ? $wpdb->prefix . 'tutor_enrollments' : 'tutor_enrollments';
        $resolved_enrollments = self::get_enrollments_table_name();

        if ($resolved_enrollments && $resolved_enrollments !== $legacy_enrollments && strpos($query, $legacy_enrollments) !== false) {
            $query = str_replace($legacy_enrollments, $resolved_enrollments, $query);
        }

        $legacy_lessons = isset($wpdb->prefix) ? $wpdb->prefix . 'tutor_lesson_activities' : 'tutor_lesson_activities';
        $resolved_lessons = self::get_lesson_activity_table_name();

        if ($resolved_lessons && $resolved_lessons !== $legacy_lessons && strpos($query, $legacy_lessons) !== false) {
            $query = str_replace($legacy_lessons, $resolved_lessons, $query);
        }

        return $query;
    }


    /**
     * Get enrolled students for a course using Tutor LMS API
     */
    public static function get_course_students($course_id) {
        // Try Tutor LMS helper function first
        if (function_exists('tutor_utils')) {
            $students = tutor_utils()->get_enrolled_users($course_id);
            if (!empty($students)) {
                return $students;
            }
        }
        
        // Try alternative Tutor LMS function
        if (function_exists('tutils')) {
            $students = tutils()->get_enrolled_users($course_id);
            if (!empty($students)) {
                return $students;
            }
        }
        
        // Fallback to WordPress user query with enrollment meta
        return self::get_course_students_fallback($course_id);
    }
    
    /**
     * Get user's course progress using Tutor LMS API
     */
    public static function get_user_course_progress($user_id, $course_id) {
        // Try Tutor LMS helper function
        if (function_exists('tutor_utils')) {
            $progress = tutor_utils()->get_course_completed_percent($course_id, $user_id);
            if ($progress !== false) {
                return round($progress, 1);
            }
        }
        
        // Try alternative function
        if (function_exists('tutils')) {
            $progress = tutils()->get_course_completed_percent($course_id, $user_id);
            if ($progress !== false) {
                return round($progress, 1);
            }
        }
        
        // Fallback calculation
        return self::calculate_progress_fallback($user_id, $course_id);
    }
    
    /**
     * Get course lessons using Tutor LMS API
     */
    public static function get_course_lessons($course_id) {
        // Try Tutor LMS content function
        if (function_exists('tutor_utils')) {
            $lessons = tutor_utils()->get_course_contents_by_type($course_id, self::get_lesson_post_type());
            if (!empty($lessons)) {
                return $lessons;
            }
        }
        
        // Fallback to WordPress post query
        return get_posts(array(
            'post_type' => self::get_lesson_post_type(),
            'post_parent' => $course_id,
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'menu_order',
            'order' => 'ASC'
        ));
    }
    
    /**
     * Get course quizzes using Tutor LMS API
     */
    public static function get_course_quizzes($course_id) {
        // Try Tutor LMS content function
        if (function_exists('tutor_utils')) {
            $quizzes = tutor_utils()->get_course_contents_by_type($course_id, self::get_quiz_post_type());
            if (!empty($quizzes)) {
                return $quizzes;
            }
        }
        
        // Fallback to WordPress post query
        return get_posts(array(
            'post_type' => self::get_quiz_post_type(),
            'post_parent' => $course_id,
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'menu_order',
            'order' => 'ASC'
        ));
    }
    
    /**
     * Check if user is enrolled in course
     */
    public static function is_user_enrolled($user_id, $course_id) {
        // Try Tutor LMS function
        if (function_exists('tutor_utils')) {
            return tutor_utils()->is_enrolled($course_id, $user_id);
        }
        
        // Fallback check enrollment meta
        $enrolled_courses = get_user_meta($user_id, '_tutor_enrolled_course_ids', true);
        return is_array($enrolled_courses) && in_array($course_id, $enrolled_courses);
    }
    
    /**
     * Get user's quiz attempts for a course
     */
    public static function get_user_quiz_attempts($user_id, $course_id) {
        global $wpdb;
        
        // This requires database query as Tutor LMS doesn't have a direct API for this
        // But we'll make it more robust with table existence checks
        $table_name = $wpdb->prefix . 'tutor_quiz_attempts';
        
        if (!self::table_exists($table_name)) {
            return array();
        }
        
        $quiz_post_type = self::get_quiz_post_type();
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT qa.*, p.post_title as quiz_title
             FROM {$table_name} qa
             JOIN {$wpdb->posts} p ON qa.quiz_id = p.ID
             WHERE qa.user_id = %d 
             AND p.post_parent = %d
             AND p.post_type = %s
             AND qa.attempt_status = 'attempt_ended'
             ORDER BY qa.attempt_started_at DESC",
            $user_id, $course_id, $quiz_post_type
        ));
    }
    
    /**
     * Get course enrollment statistics
     */
    public static function get_course_enrollment_stats($course_id) {
        // Always use fallback for more reliable results
        $stats = self::get_enrollment_stats_fallback($course_id);
        
        // Try Tutor LMS function as a backup check
        if (function_exists('tutor_utils') && $stats['total_students'] == 0) {
            $total_students = tutor_utils()->count_enrolled_users($course_id);
            if ($total_students !== false && $total_students > 0) {
                $stats['total_students'] = (int)$total_students;
                $stats['completed_students'] = self::count_completed_students($course_id);
            }
        }
        
        return $stats;
    }
    
    /**
     * Fallback method to get course students using WordPress API
     */
    private static function get_course_students_fallback($course_id) {
        $table_name = self::get_enrollments_table_name();
        if (!$table_name) {
            return array();
        }

        $columns = self::get_enrollment_columns_map();
        if (empty($columns['course_id']) || empty($columns['user_id'])) {
            return array();
        }

        global $wpdb;

        $user_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT {$columns['user_id']} FROM {$table_name} WHERE {$columns['course_id']} = %d",
            $course_id
        ));

        if (empty($user_ids)) {
            return array();
        }

        $user_query = new WP_User_Query(array(
            'include' => $user_ids,
            'fields' => array('ID', 'display_name', 'user_email')
        ));

        return $user_query->get_results();
    }
        
    
    /**
     * Fallback method to calculate user progress
     */
    private static function calculate_progress_fallback($user_id, $course_id) {
        $lessons = self::get_course_lessons($course_id);
        if (empty($lessons)) {
            return 0;
        }
        
        $total_lessons = count($lessons);
        $completed_lessons = 0;
        
        foreach ($lessons as $lesson) {
            $is_completed = get_user_meta($user_id, '_tutor_lesson_completed_' . $lesson->ID, true);
            if ($is_completed) {
                $completed_lessons++;
            }
        }
        
        return $total_lessons > 0 ? round(($completed_lessons / $total_lessons) * 100, 1) : 0;
    }
    
    /**
     * Count completed students for a course
     */
    private static function count_completed_students($course_id) {
        $table_name = self::get_enrollments_table_name();
        if (!$table_name) {
            return 0;
        }

        $columns = self::get_enrollment_columns_map();
        if (empty($columns['course_id'])) {
            return 0;
        }

        $completion_condition = self::get_enrollment_completion_condition('e');
        if ('0=1' === $completion_condition) {
            return 0;
        }

        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} e
             WHERE e.{$columns['course_id']} = %d
               AND ({$completion_condition})",
            $course_id
        ));
    }

    /**
     * Fallback enrollment stats
     */
    private static function get_enrollment_stats_fallback($course_id) {
        $table_name = self::get_enrollments_table_name();
        if (!$table_name) {
            return array('total_students' => 0, 'completed_students' => 0);
        }

        $columns = self::get_enrollment_columns_map();
        if (empty($columns['course_id'])) {
            return array('total_students' => 0, 'completed_students' => 0);
        }

        $completion_condition = self::get_enrollment_completion_condition('e');

        global $wpdb;

        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT
                COUNT(*) AS total_students,
                SUM(CASE WHEN {$completion_condition} THEN 1 ELSE 0 END) AS completed_students
             FROM {$table_name} e
             WHERE e.{$columns['course_id']} = %d",
            $course_id
        ));

        return array(
            'total_students' => (int) ($stats->total_students ?? 0),
            'completed_students' => (int) ($stats->completed_students ?? 0)
        );
    }

    /**
     * Check if database table exists
     */
    public static function table_exists($table_name) {
        global $wpdb;
        $result = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));
        return $result === $table_name;
    }
    
    /**
     * Get all courses using WordPress API
     */
    public static function get_all_courses($args = array()) {
        $defaults = array(
            'post_type' => self::get_course_post_type(),
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        return get_posts($args);
    }
    
    /**
     * Get courses for current user (instructor filter)
     */
    public static function get_user_courses($user_id) {
        return self::get_all_courses(array(
            'author' => $user_id
        ));
    }
}