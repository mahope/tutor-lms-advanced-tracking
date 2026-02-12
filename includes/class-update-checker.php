<?php
/**
 * Plugin Update Checker
 * 
 * Integrates with WordPress Update API to check for updates from license server
 * 
 * @package TutorLMSAdvancedTracking
 */

if (!defined('ABSPATH')) {
    exit;
}

class TLAT_Update_Checker {

    /**
     * License server URL
     */
    const UPDATE_SERVER = 'https://licenses.holstjensen.eu';
    
    /**
     * Plugin slug
     */
    const PLUGIN_SLUG = 'tutor-lms-advanced-tracking';
    
    /**
     * Plugin base file
     */
    const PLUGIN_FILE = 'tutor-lms-advanced-tracking/tutor-lms-advanced-tracking.php';
    
    /**
     * Cache key for update check
     */
    const CACHE_KEY = 'tlat_update_check';
    
    /**
     * Cache duration in seconds (12 hours)
     */
    const CACHE_DURATION = 43200;

    /**
     * Initialize update checker
     */
    public static function init() {
        // Hook into WordPress update system
        add_filter('pre_set_site_transient_update_plugins', array(__CLASS__, 'check_for_updates'));
        add_filter('plugins_api', array(__CLASS__, 'plugin_info'), 20, 3);
        add_action('upgrader_process_complete', array(__CLASS__, 'clear_update_cache'), 10, 2);
        
        // Add update message on plugins page
        add_action('in_plugin_update_message-' . self::PLUGIN_FILE, array(__CLASS__, 'update_message'), 10, 2);
    }

    /**
     * Check for plugin updates
     * 
     * @param object $transient Update transient
     * @return object Modified transient
     */
    public static function check_for_updates($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        // Get current version
        $current_version = isset($transient->checked[self::PLUGIN_FILE]) 
            ? $transient->checked[self::PLUGIN_FILE] 
            : TLAT_VERSION;

        // Check cache first
        $cached = get_transient(self::CACHE_KEY);
        if ($cached !== false) {
            if (!empty($cached['hasUpdate'])) {
                $transient->response[self::PLUGIN_FILE] = self::build_update_object($cached);
            }
            return $transient;
        }

        // Get license info for authenticated update check
        $license_key = get_option('tlat_license_key', '');
        $domain = self::get_site_domain();

        // Make request to update server
        $response = self::api_request('/api/v1/update/check', array(
            'slug' => self::PLUGIN_SLUG,
            'version' => $current_version,
            'license_key' => $license_key,
            'domain' => $domain,
            'wp_version' => get_bloginfo('version'),
            'php_version' => phpversion()
        ));

        if (is_wp_error($response) || empty($response)) {
            // On error, don't block updates, just skip
            return $transient;
        }

        // Cache the result
        set_transient(self::CACHE_KEY, $response, self::CACHE_DURATION);

        // Add to update transient if update available
        if (!empty($response['hasUpdate'])) {
            $transient->response[self::PLUGIN_FILE] = self::build_update_object($response);
        } else {
            // Make sure it's not in response (no update)
            unset($transient->response[self::PLUGIN_FILE]);
        }

        return $transient;
    }

    /**
     * Build update object for WordPress
     * 
     * @param array $data API response data
     * @return object
     */
    private static function build_update_object($data) {
        $update = $data['updateInfo'] ?? array();
        
        $obj = new stdClass();
        $obj->id = self::PLUGIN_SLUG;
        $obj->slug = self::PLUGIN_SLUG;
        $obj->plugin = self::PLUGIN_FILE;
        $obj->new_version = $update['version'] ?? $data['latestVersion'];
        $obj->url = 'https://tutor-tracking.com';
        $obj->package = $update['downloadUrl'] ?? '';
        $obj->icons = array(
            '1x' => 'https://tutor-tracking.com/assets/icon-128x128.png',
            '2x' => 'https://tutor-tracking.com/assets/icon-256x256.png'
        );
        $obj->banners = array(
            'low' => 'https://tutor-tracking.com/assets/banner-772x250.jpg',
            'high' => 'https://tutor-tracking.com/assets/banner-1544x500.jpg'
        );
        $obj->banners_rtl = array();
        $obj->tested = $update['testedUpTo'] ?? '6.6';
        $obj->requires_php = $update['requiresPHP'] ?? '7.4';
        $obj->compatibility = new stdClass();

        return $obj;
    }

    /**
     * Plugin information for WordPress plugins_api
     * 
     * @param mixed $result
     * @param string $action
     * @param object $args
     * @return mixed
     */
    public static function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information') {
            return $result;
        }

        if (!isset($args->slug) || $args->slug !== self::PLUGIN_SLUG) {
            return $result;
        }

        // Fetch plugin info from server
        $response = self::api_request('/api/v1/update/info/' . self::PLUGIN_SLUG, array(), 'GET');

        if (is_wp_error($response) || empty($response)) {
            return $result;
        }

        // Convert to WordPress-expected object
        $info = new stdClass();
        $info->name = $response['name'] ?? 'Advanced Tutor LMS Stats Dashboard';
        $info->slug = self::PLUGIN_SLUG;
        $info->version = $response['version'] ?? TLAT_VERSION;
        $info->author = $response['author'] ?? '<a href="https://mahope.dk">Mads Holst Jensen</a>';
        $info->author_profile = $response['author_profile'] ?? 'https://mahope.dk';
        $info->requires = $response['requires'] ?? '5.0';
        $info->tested = $response['tested'] ?? '6.6';
        $info->requires_php = $response['requires_php'] ?? '7.4';
        $info->rating = $response['rating'] ?? 100;
        $info->num_ratings = $response['num_ratings'] ?? 1;
        $info->active_installs = $response['active_installs'] ?? 10;
        $info->last_updated = $response['last_updated'] ?? date('Y-m-d');
        $info->added = $response['added'] ?? '2026-02-01';
        $info->homepage = $response['homepage'] ?? 'https://tutor-tracking.com';
        $info->sections = $response['sections'] ?? array();
        $info->banners = $response['banners'] ?? array();
        $info->download_link = ''; // Will be populated from update check

        return $info;
    }

    /**
     * Display update message on plugins page
     * 
     * @param array $plugin_data
     * @param object $response
     */
    public static function update_message($plugin_data, $response) {
        // Check if license is valid
        if (!TLAT_License_Validator::is_licensed()) {
            echo '<br><span style="color: #d63638;">';
            echo esc_html__('A valid license is required to download updates.', 'tutor-lms-advanced-tracking');
            echo ' <a href="' . esc_url(admin_url('options-general.php?page=tlat-license')) . '">';
            echo esc_html__('Enter your license key', 'tutor-lms-advanced-tracking');
            echo '</a>';
            echo '</span>';
        }
    }

    /**
     * Clear update cache
     * 
     * @param object $upgrader
     * @param array $options
     */
    public static function clear_update_cache($upgrader, $options) {
        if ($options['action'] === 'update' && $options['type'] === 'plugin') {
            delete_transient(self::CACHE_KEY);
        }
    }

    /**
     * Make API request to update server
     * 
     * @param string $endpoint
     * @param array $data
     * @param string $method
     * @return array|WP_Error
     */
    private static function api_request($endpoint, $data = array(), $method = 'POST') {
        $url = self::UPDATE_SERVER . $endpoint;

        $args = array(
            'timeout' => 15,
            'headers' => array(
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            )
        );

        if ($method === 'POST') {
            $args['method'] = 'POST';
            $args['body'] = json_encode($data);
            $response = wp_remote_post($url, $args);
        } else {
            $response = wp_remote_get($url, $args);
        }

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return new WP_Error('api_error', 'API returned status ' . $code);
        }

        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true);
    }

    /**
     * Get site domain
     * 
     * @return string
     */
    private static function get_site_domain() {
        $url = get_site_url();
        $parsed = parse_url($url);
        return $parsed['host'] ?? '';
    }

    /**
     * Force check for updates (for manual trigger)
     * 
     * @return array|WP_Error
     */
    public static function force_check() {
        delete_transient(self::CACHE_KEY);
        delete_site_transient('update_plugins');
        
        // Trigger WordPress update check
        wp_update_plugins();
        
        return get_transient(self::CACHE_KEY);
    }

    /**
     * Get changelog from server
     * 
     * @return array|WP_Error
     */
    public static function get_changelog() {
        return self::api_request('/api/v1/update/changelog/' . self::PLUGIN_SLUG, array(), 'GET');
    }
}

// Initialize on load
add_action('init', array('TLAT_Update_Checker', 'init'));
