<?php
/**
 * Assignment Analytics Admin Template
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Enqueue scripts
wp_enqueue_script('tutor-advanced-charts');
wp_enqueue_style('tutor-advanced-admin');
wp_enqueue_style('tutor-advanced-assignment-analytics');
wp_enqueue_script('tutor-advanced-assignment-analytics');
?>

<div id="assignment-timeline-page" class="tutor-advanced-admin wrap">
    <div class="page-header">
        <div>
            <h1><?php _e('Assignment Analytics', 'tutor-lms-advanced-tracking'); ?></h1>
            <p class="subtitle"><?php _e('Track and analyze assignment submissions across your courses', 'tutor-lms-advanced-tracking'); ?></p>
        </div>
        <div class="page-actions">
            <button class="tutor-btn tutor-btn-secondary" id="refresh-all">
                <span class="dashicons dashicons-update"></span>
                <?php _e('Refresh Data', 'tutor-lms-advanced-tracking'); ?>
            </button>
        </div>
    </div>

    <!-- Overview Section -->
    <div class="section-card">
        <h3><?php _e('Overview', 'tutor-lms-advanced-tracking'); ?></h3>
        <div id="assignment-overview">
            <div class="loading">
                <span class="spinner is-active"></span>
                <?php _e('Loading overview data...', 'tutor-lms-advanced-tracking'); ?>
            </div>
        </div>
    </div>

    <!-- Timeline Section -->
    <div class="timeline-section">
        <div class="timeline-header">
            <h3><?php _e('Submission Timeline', 'tutor-lms-advanced-tracking'); ?></h3>
            <div class="filter-controls">
                <label for="start-date"><?php _e('From:', 'tutor-lms-advanced-tracking'); ?></label>
                <input type="date" id="start-date" value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>">
                
                <label for="end-date"><?php _e('To:', 'tutor-lms-advanced-tracking'); ?></label>
                <input type="date" id="end-date" value="<?php echo date('Y-m-d'); ?>">
                
                <label for="group-by"><?php _e('Group by:', 'tutor-lms-advanced-tracking'); ?></label>
                <select id="group-by">
                    <option value="day"><?php _e('Day', 'tutor-lms-advanced-tracking'); ?></option>
                    <option value="week"><?php _e('Week', 'tutor-lms-advanced-tracking'); ?></option>
                    <option value="hour"><?php _e('Hour', 'tutor-lms-advanced-tracking'); ?></option>
                </select>
                
                <button id="apply-filter" class="tutor-btn tutor-btn-primary">
                    <?php _e('Apply', 'tutor-lms-advanced-tracking'); ?>
                </button>
                <button id="reset-filter" class="tutor-btn tutor-btn-secondary">
                    <?php _e('Reset', 'tutor-lms-advanced-tracking'); ?>
                </button>
            </div>
        </div>
        <div class="timeline-chart-container">
            <canvas id="timeline-canvas"></canvas>
        </div>
    </div>

    <!-- Recent Submissions -->
    <div class="recent-submissions">
        <h3><?php _e('Recent Submissions', 'tutor-lms-advanced-tracking'); ?></h3>
        <div class="table-responsive">
            <table class="tutor-advanced-table">
                <thead>
                    <tr>
                        <th><?php _e('Assignment', 'tutor-lms-advanced-tracking'); ?></th>
                        <th><?php _e('Student', 'tutor-lms-advanced-tracking'); ?></th>
                        <th><?php _e('Submitted', 'tutor-lms-advanced-tracking'); ?></th>
                        <th><?php _e('Status', 'tutor-lms-advanced-tracking'); ?></th>
                    </tr>
                </thead>
                <tbody id="recent-submissions">
                    <tr>
                        <td colspan="4" class="loading">
                            <span class="spinner is-active"></span>
                            <?php _e('Loading submissions...', 'tutor-lms-advanced-tracking'); ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- By Course -->
    <div class="section-card">
        <h3><?php _e('Assignments by Course', 'tutor-lms-advanced-tracking'); ?></h3>
        <div id="assignments-by-course">
            <div class="loading">
                <span class="spinner is-active"></span>
                <?php _e('Loading course data...', 'tutor-lms-advanced-tracking'); ?>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    'use strict';
    
    // Initialize chart.js if not already loaded
    if (typeof Chart === 'undefined') {
        // Fallback: Load chart.js dynamically
        $('<script>').attr('src', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js').appendTo('head');
    }
});
</script>
