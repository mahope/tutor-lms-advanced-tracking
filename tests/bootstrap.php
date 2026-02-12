<?php
/**
 * PHPUnit bootstrap file for TLAT plugin tests
 *
 * @package TutorLMSAdvancedTracking
 */

// Composer autoloader
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Define WordPress constants
define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'TLAT_VERSION', '1.1.0' );

// Setup Brain Monkey for WordPress mocking
\Brain\Monkey\setUp();

/**
 * Mock WordPress functions needed by the plugin
 */

// Storage for mocked options
global $wp_mock_options;
$wp_mock_options = [];

// Mock get_option
if ( ! function_exists( 'get_option' ) ) {
    function get_option( $option, $default = false ) {
        global $wp_mock_options;
        return $wp_mock_options[ $option ] ?? $default;
    }
}

// Mock update_option
if ( ! function_exists( 'update_option' ) ) {
    function update_option( $option, $value, $autoload = null ) {
        global $wp_mock_options;
        $wp_mock_options[ $option ] = $value;
        return true;
    }
}

// Mock delete_option
if ( ! function_exists( 'delete_option' ) ) {
    function delete_option( $option ) {
        global $wp_mock_options;
        unset( $wp_mock_options[ $option ] );
        return true;
    }
}

// Mock home_url
if ( ! function_exists( 'home_url' ) ) {
    function home_url( $path = '' ) {
        return 'https://example.com' . $path;
    }
}

// Mock get_bloginfo
if ( ! function_exists( 'get_bloginfo' ) ) {
    function get_bloginfo( $show = '' ) {
        $data = [
            'version' => '6.5.0',
            'name'    => 'Test Site',
        ];
        return $data[ $show ] ?? '';
    }
}

// Mock wp_parse_url
if ( ! function_exists( 'wp_parse_url' ) ) {
    function wp_parse_url( $url, $component = -1 ) {
        return parse_url( $url, $component );
    }
}

// Mock wp_json_encode
if ( ! function_exists( 'wp_json_encode' ) ) {
    function wp_json_encode( $data, $options = 0, $depth = 512 ) {
        return json_encode( $data, $options, $depth );
    }
}

// Mock sanitize_text_field
if ( ! function_exists( 'sanitize_text_field' ) ) {
    function sanitize_text_field( $str ) {
        return trim( strip_tags( $str ) );
    }
}

// Mock add_action
if ( ! function_exists( 'add_action' ) ) {
    function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
        // No-op for unit tests
        return true;
    }
}

// Mock wp_schedule_event
if ( ! function_exists( 'wp_schedule_event' ) ) {
    function wp_schedule_event( $timestamp, $recurrence, $hook, $args = [] ) {
        return true;
    }
}

// Mock wp_next_scheduled
if ( ! function_exists( 'wp_next_scheduled' ) ) {
    function wp_next_scheduled( $hook ) {
        return false;
    }
}

// Mock wp_clear_scheduled_hook
if ( ! function_exists( 'wp_clear_scheduled_hook' ) ) {
    function wp_clear_scheduled_hook( $hook, $args = [] ) {
        return 0;
    }
}

// Mock admin_url
if ( ! function_exists( 'admin_url' ) ) {
    function admin_url( $path = '' ) {
        return 'https://example.com/wp-admin/' . ltrim( $path, '/' );
    }
}

// Mock get_current_screen
if ( ! function_exists( 'get_current_screen' ) ) {
    function get_current_screen() {
        return null;
    }
}

// Mock esc_html, esc_attr, esc_url
if ( ! function_exists( 'esc_html' ) ) {
    function esc_html( $text ) {
        return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
    }
}

if ( ! function_exists( 'esc_attr' ) ) {
    function esc_attr( $text ) {
        return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
    }
}

if ( ! function_exists( 'esc_url' ) ) {
    function esc_url( $url ) {
        return filter_var( $url, FILTER_SANITIZE_URL );
    }
}

if ( ! function_exists( 'esc_html_e' ) ) {
    function esc_html_e( $text, $domain = 'default' ) {
        echo esc_html( $text );
    }
}

// Mock translation functions
if ( ! function_exists( '__' ) ) {
    function __( $text, $domain = 'default' ) {
        return $text;
    }
}

if ( ! function_exists( '_e' ) ) {
    function _e( $text, $domain = 'default' ) {
        echo $text;
    }
}

if ( ! function_exists( '_n' ) ) {
    function _n( $single, $plural, $number, $domain = 'default' ) {
        return $number === 1 ? $single : $plural;
    }
}

// Mock WP_Error
if ( ! class_exists( 'WP_Error' ) ) {
    class WP_Error {
        protected $code;
        protected $message;
        protected $data;

        public function __construct( $code = '', $message = '', $data = '' ) {
            $this->code    = $code;
            $this->message = $message;
            $this->data    = $data;
        }

        public function get_error_code() {
            return $this->code;
        }

        public function get_error_message( $code = '' ) {
            return $this->message;
        }

        public function get_error_data( $code = '' ) {
            return $this->data;
        }
    }
}

// Mock is_wp_error
if ( ! function_exists( 'is_wp_error' ) ) {
    function is_wp_error( $thing ) {
        return $thing instanceof WP_Error;
    }
}

// Include the class under test
require_once dirname( __DIR__ ) . '/includes/class-license-validator.php';
