<?php
/**
 * Installation Checker for Advanced Tutor LMS Stats Dashboard
 * 
 * This script checks if your WordPress environment is ready for the plugin.
 * Add this to your WordPress admin or run via WP-CLI.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('This script must be run from WordPress admin or through WP-CLI');
}

class TutorAdvancedTracking_InstallationChecker {
    
    private $checks = [];
    
    public function run_checks() {
        echo "<h2>Advanced Tutor LMS Stats Dashboard - Installation Checker</h2>";
        
        $this->check_wordpress_version();
        $this->check_php_version();
        $this->check_tutor_lms();
        $this->check_database_tables();
        $this->check_file_permissions();
        $this->check_memory_limit();
        $this->check_plugin_files();
        
        $this->display_results();
        $this->display_next_steps();
    }
    
    private function check_wordpress_version() {
        global $wp_version;
        $required_version = '5.0';
        
        if (version_compare($wp_version, $required_version, '>=')) {
            $this->add_check('WordPress Version', '✅ PASS', "WordPress $wp_version (Required: $required_version+)");
        } else {
            $this->add_check('WordPress Version', '❌ FAIL', "WordPress $wp_version is too old. Required: $required_version+");
        }
    }
    
    private function check_php_version() {
        $required_version = '7.4';
        $current_version = PHP_VERSION;
        
        if (version_compare($current_version, $required_version, '>=')) {
            $this->add_check('PHP Version', '✅ PASS', "PHP $current_version (Required: $required_version+)");
        } else {
            $this->add_check('PHP Version', '❌ FAIL', "PHP $current_version is too old. Required: $required_version+");
        }
    }
    
    private function check_tutor_lms() {
        if (is_plugin_active('tutor-pro/tutor-pro.php') || is_plugin_active('tutor/tutor.php')) {
            if (function_exists('tutor')) {
                $this->add_check('Tutor LMS', '✅ PASS', 'Tutor LMS is installed and active');
            } else {
                $this->add_check('Tutor LMS', '⚠️ WARNING', 'Tutor LMS is active but functions not available');
            }
        } else {
            $this->add_check('Tutor LMS', '❌ FAIL', 'Tutor LMS is not installed or not active');
        }
    }
    
    private function check_database_tables() {
        global $wpdb;
        
        $required_tables = [
            'tutor_enrollments',
            'tutor_quiz_attempts',
            'tutor_quiz_questions',
            'tutor_quiz_question_answers'
        ];
        
        $missing_tables = [];
        foreach ($required_tables as $table) {
            $table_name = $wpdb->prefix . $table;
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
            
            if (!$exists) {
                $missing_tables[] = $table;
            }
        }
        
        if (empty($missing_tables)) {
            $this->add_check('Database Tables', '✅ PASS', 'All required Tutor LMS tables exist');
        } else {
            $this->add_check('Database Tables', '❌ FAIL', 'Missing tables: ' . implode(', ', $missing_tables));
        }
    }
    
    private function check_file_permissions() {
        $plugin_dir = TUTOR_ADVANCED_TRACKING_PLUGIN_DIR;
        
        if (is_readable($plugin_dir) && is_writable($plugin_dir)) {
            $this->add_check('File Permissions', '✅ PASS', 'Plugin directory has correct permissions');
        } else {
            $this->add_check('File Permissions', '⚠️ WARNING', 'Plugin directory may have permission issues');
        }
    }
    
    private function check_memory_limit() {
        $memory_limit = ini_get('memory_limit');
        $memory_bytes = $this->convert_to_bytes($memory_limit);
        $required_bytes = $this->convert_to_bytes('128M');
        
        if ($memory_bytes >= $required_bytes) {
            $this->add_check('Memory Limit', '✅ PASS', "Memory limit: $memory_limit (Required: 128M+)");
        } else {
            $this->add_check('Memory Limit', '⚠️ WARNING', "Memory limit: $memory_limit is low. Recommended: 128M+");
        }
    }
    
    private function check_plugin_files() {
        $required_files = [
            'tutor-lms-advanced-tracking.php',
            'includes/class-advanced-analytics.php',
            'includes/class-dashboard.php',
            'includes/class-shortcode.php',
            'templates/dashboard.php',
            'templates/advanced-analytics.php',
            'assets/css/dashboard.css',
            'assets/js/dashboard.js'
        ];
        
        $missing_files = [];
        foreach ($required_files as $file) {
            if (!file_exists(TUTOR_ADVANCED_TRACKING_PLUGIN_DIR . $file)) {
                $missing_files[] = $file;
            }
        }
        
        if (empty($missing_files)) {
            $this->add_check('Plugin Files', '✅ PASS', 'All required plugin files exist');
        } else {
            $this->add_check('Plugin Files', '❌ FAIL', 'Missing files: ' . implode(', ', $missing_files));
        }
    }
    
    private function add_check($name, $status, $message) {
        $this->checks[] = [
            'name' => $name,
            'status' => $status,
            'message' => $message
        ];
    }
    
    private function display_results() {
        echo "<h3>Installation Check Results</h3>";
        echo "<table style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background: #f1f1f1;'>";
        echo "<th style='padding: 10px; border: 1px solid #ddd; text-align: left;'>Check</th>";
        echo "<th style='padding: 10px; border: 1px solid #ddd; text-align: center;'>Status</th>";
        echo "<th style='padding: 10px; border: 1px solid #ddd; text-align: left;'>Details</th>";
        echo "</tr>";
        
        foreach ($this->checks as $check) {
            echo "<tr>";
            echo "<td style='padding: 10px; border: 1px solid #ddd;'><strong>{$check['name']}</strong></td>";
            echo "<td style='padding: 10px; border: 1px solid #ddd; text-align: center;'>{$check['status']}</td>";
            echo "<td style='padding: 10px; border: 1px solid #ddd;'>{$check['message']}</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    }
    
    private function display_next_steps() {
        $failed_checks = array_filter($this->checks, function($check) {
            return strpos($check['status'], '❌') !== false;
        });
        
        $warning_checks = array_filter($this->checks, function($check) {
            return strpos($check['status'], '⚠️') !== false;
        });
        
        echo "<h3>Next Steps</h3>";
        
        if (!empty($failed_checks)) {
            echo "<div style='background: #ffebee; padding: 15px; border-left: 4px solid #f44336; margin: 20px 0;'>";
            echo "<h4>❌ Critical Issues Found</h4>";
            echo "<p>Please fix these issues before using the plugin:</p>";
            echo "<ul>";
            foreach ($failed_checks as $check) {
                echo "<li><strong>{$check['name']}</strong>: {$check['message']}</li>";
            }
            echo "</ul>";
            echo "</div>";
        }
        
        if (!empty($warning_checks)) {
            echo "<div style='background: #fff3e0; padding: 15px; border-left: 4px solid #ff9800; margin: 20px 0;'>";
            echo "<h4>⚠️ Warnings</h4>";
            echo "<p>These issues may affect plugin performance:</p>";
            echo "<ul>";
            foreach ($warning_checks as $check) {
                echo "<li><strong>{$check['name']}</strong>: {$check['message']}</li>";
            }
            echo "</ul>";
            echo "</div>";
        }
        
        if (empty($failed_checks)) {
            echo "<div style='background: #e8f5e8; padding: 15px; border-left: 4px solid #4caf50; margin: 20px 0;'>";
            echo "<h4>✅ Ready to Install</h4>";
            echo "<p>Your WordPress environment is ready for the Advanced Tutor LMS Stats Dashboard plugin!</p>";
            echo "<p><strong>Installation Steps:</strong></p>";
            echo "<ol>";
            echo "<li>Activate the plugin from the WordPress admin</li>";
            echo "<li>Create a page with the [tutor_advanced_stats] shortcode</li>";
            echo "<li>Generate test data using the test data generator</li>";
            echo "<li>Test the plugin features</li>";
            echo "</ol>";
            echo "</div>";
        }
        
        echo "<h4>Additional Resources</h4>";
        echo "<ul>";
        echo "<li><a href='?action=generate_test_data'>Generate Test Data</a> - Create sample data for testing</li>";
        echo "<li><a href='#'>Plugin Documentation</a> - Read the complete documentation</li>";
        echo "<li><a href='#'>Troubleshooting Guide</a> - Common issues and solutions</li>";
        echo "</ul>";
    }
    
    private function convert_to_bytes($value) {
        $value = trim($value);
        $last = strtolower($value[strlen($value) - 1]);
        $numeric = substr($value, 0, -1);
        
        switch ($last) {
            case 'g':
                $numeric *= 1024;
            case 'm':
                $numeric *= 1024;
            case 'k':
                $numeric *= 1024;
        }
        
        return $numeric;
    }
}

// Usage
if (isset($_GET['action']) && $_GET['action'] === 'check_installation') {
    $checker = new TutorAdvancedTracking_InstallationChecker();
    $checker->run_checks();
} else {
    echo "<h2>Installation Checker</h2>";
    echo "<p>Click the button below to check if your WordPress environment is ready for the Advanced Tutor LMS Stats Dashboard plugin.</p>";
    echo "<p><a href='?action=check_installation' class='button button-primary'>Run Installation Check</a></p>";
}
?>