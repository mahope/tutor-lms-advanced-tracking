/**
 * Assignment Timeline JavaScript
 * Handles the assignment submission timeline visualization
 */

jQuery(document).ready(function($) {
    'use strict';

    // Initialize assignment timeline
    window.initAssignmentTimeline = function() {
        loadOverview();
        loadTimeline();
        loadByCourse();
    };

    // Load overview data
    function loadOverview() {
        $.ajax({
            url: tutor_advanced_vars.api_url + 'tutor-advanced/v1/assignments/overview',
            method: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', tutor_advanced_vars.nonce);
            },
            success: function(response) {
                renderOverview(response);
            },
            error: function(xhr) {
                console.error('Failed to load assignment overview:', xhr);
                $('#assignment-overview').html('<p class="error">Failed to load overview data</p>');
            }
        });
    }

    // Render overview cards
    function renderOverview(data) {
        const html = `
            <div class="overview-grid">
                <div class="stat-card">
                    <div class="stat-value">${data.total_assignments}</div>
                    <div class="stat-label">Total Assignments</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">${data.total_submissions}</div>
                    <div class="stat-label">Total Submissions</div>
                </div>
                <div class="stat-card pending">
                    <div class="stat-value">${data.pending_reviews}</div>
                    <div class="stat-label">Pending Reviews</div>
                </div>
                <div class="stat-card success">
                    <div class="stat-value">${data.pass_rate}%</div>
                    <div class="stat-label">Pass Rate</div>
                </div>
            </div>
            <div class="status-breakdown">
                <h4>Status Breakdown</h4>
                <div class="progress-bars">
                    ${renderProgressBar('Passed', data.pass_count, data.total_submissions, 'passed')}
                    ${renderProgressBar('Pending', data.pending_reviews, data.total_submissions, 'pending')}
                    ${renderProgressBar('Failed', data.fail_count, data.total_submissions, 'failed')}
                </div>
            </div>
        `;
        $('#assignment-overview').html(html);
    }

    // Render progress bar
    function renderProgressBar(label, count, total, className) {
        const percentage = total > 0 ? Math.round((count / total) * 100) : 0;
        return `
            <div class="progress-item">
                <div class="progress-label">
                    <span>${label}</span>
                    <span>${count} (${percentage}%)</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill ${className}" style="width: ${percentage}%"></div>
                </div>
            </div>
        `;
    }

    // Load timeline data
    function loadTimeline() {
        const params = {
            start_date: $('#start-date').val() || getDefaultStartDate(),
            end_date: $('#end-date').val() || getDefaultEndDate(),
            group_by: $('#group-by').val() || 'day'
        };

        $.ajax({
            url: tutor_advanced_vars.api_url + 'tutor-advanced/v1/assignments/timeline',
            method: 'GET',
            data: params,
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', tutor_advanced_vars.nonce);
            },
            success: function(response) {
                renderTimeline(response);
            },
            error: function(xhr) {
                console.error('Failed to load timeline:', xhr);
                $('#timeline-chart').html('<p class="error">Failed to load timeline data</p>');
            }
        });
    }

    // Get default start date (30 days ago)
    function getDefaultStartDate() {
        const date = new Date();
        date.setDate(date.getDate() - 30);
        return date.toISOString().split('T')[0];
    }

    // Get default end date (today)
    function getDefaultEndDate() {
        return new Date().toISOString().split('T')[0];
    }

    // Render timeline chart
    function renderTimeline(data) {
        if (data.timeline.length === 0) {
            $('#timeline-chart').html('<p class="no-data">No submission data for selected period</p>');
            return;
        }

        // Create chart data
        const labels = data.timeline.map(item => item.date);
        const submissions = data.timeline.map(item => item.submissions);
        const uniqueSubmitters = data.timeline.map(item => item.unique_submitters);

        // Destroy existing chart if it exists
        if (window.assignmentTimelineChart) {
            window.assignmentTimelineChart.destroy();
        }

        const ctx = document.getElementById('timeline-canvas').getContext('2d');
        window.assignmentTimelineChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Total Submissions',
                        data: submissions,
                        borderColor: '#4CAF50',
                        backgroundColor: 'rgba(76, 175, 80, 0.1)',
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: 'Unique Submitters',
                        data: uniqueSubmitters,
                        borderColor: '#2196F3',
                        backgroundColor: 'rgba(33, 150, 243, 0.1)',
                        fill: true,
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top'
                    },
                    title: {
                        display: true,
                        text: 'Assignment Submissions Over Time'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index'
                }
            }
        });

        // Render recent submissions table
        renderRecentSubmissions(data.recent_submissions);
    }

    // Render recent submissions
    function renderRecentSubmissions(submissions) {
        if (submissions.length === 0) {
            $('#recent-submissions').html('<p class="no-data">No recent submissions</p>');
            return;
        }

        const html = submissions.map(sub => `
            <tr>
                <td>${sub.assignment_title}</td>
                <td>${sub.student_name || 'Unknown'}</td>
                <td>${formatDate(sub.submitted_at)}</td>
                <td><span class="status-badge ${sub.status}">${sub.status}</span></td>
            </tr>
        `).join('');

        $('#recent-submissions tbody').html(html);
    }

    // Format date
    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'});
    }

    // Load assignments by course
    function loadByCourse() {
        $.ajax({
            url: tutor_advanced_vars.api_url + 'tutor-advanced/v1/assignments/by-course',
            method: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', tutor_advanced_vars.nonce);
            },
            success: function(response) {
                renderByCourse(response);
            },
            error: function(xhr) {
                console.error('Failed to load by course:', xhr);
                $('#assignments-by-course').html('<p class="error">Failed to load course data</p>');
            }
        });
    }

    // Render assignments by course
    function renderByCourse(courses) {
        if (courses.length === 0) {
            $('#assignments-by-course').html('<p class="no-data">No courses with assignments found</p>');
            return;
        }

        const html = courses.map(course => `
            <div class="course-card">
                <div class="course-header">
                    <h4>${course.course_title}</h4>
                </div>
                <div class="course-stats">
                    <div class="course-stat">
                        <span class="stat-number">${course.assignment_count}</span>
                        <span class="stat-text">Assignments</span>
                    </div>
                    <div class="course-stat">
                        <span class="stat-number">${course.submission_count}</span>
                        <span class="stat-text">Submissions</span>
                    </div>
                    <div class="course-stat">
                        <span class="stat-number">${course.completion_rate}%</span>
                        <span class="stat-text">Completion</span>
                    </div>
                </div>
            </div>
        `).join('');

        $('#assignments-by-course').html(html);
    }

    // Filter handlers
    $('#apply-filter').on('click', function() {
        loadTimeline();
    });

    $('#reset-filter').on('click', function() {
        $('#start-date').val(getDefaultStartDate());
        $('#end-date').val(getDefaultEndDate());
        $('#group-by').val('day');
        loadTimeline();
    });

    // Initialize on page load
    if ($('#assignment-timeline-page').length) {
        initAssignmentTimeline();
    }
});
