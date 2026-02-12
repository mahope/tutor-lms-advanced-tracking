<?php
if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('tlat status', function() {
        WP_CLI::success('Tutor LMS Advanced Tracking OK');
    });
    WP_CLI::add_command('tlat migrate', function() {
        do_action('tlat/run_migrations');
        WP_CLI::success('Migrations triggered');
    });
}
