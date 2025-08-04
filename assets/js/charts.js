/**
 * Interactive Charts for Tutor LMS Advanced Tracking
 */

jQuery(document).ready(function($) {
    
    let charts = {};
    
    // Initialize dashboard charts
    if ($('#enrollment-trend-chart').length) {
        initDashboardCharts();
    }
    
    // Initialize course charts
    if ($('#progress-chart').length) {
        initCourseCharts();
    }
    
    // Handle time period changes
    $('#dashboard-chart-period, #course-chart-period').on('change', function() {
        const period = $(this).val();
        const courseId = $(this).data('course-id') || 0;
        
        if (courseId) {
            refreshCourseCharts(courseId, period);
        } else {
            refreshDashboardCharts(period);
        }
    });
    
    /**
     * Initialize dashboard charts
     */
    function initDashboardCharts() {
        const period = $('#dashboard-chart-period').val() || '30';
        
        // Enrollment trend chart
        loadChart('enrollment_trend', 'enrollment-trend-chart', { time_period: period });
        
        // Overall completion chart (if canvas exists)
        if ($('#completion-chart').length) {
            loadChart('course_completion', 'completion-chart', { course_id: 0 });
        }
    }
    
    /**
     * Initialize course-specific charts
     */
    function initCourseCharts() {
        const period = $('#course-chart-period').val() || '30';
        const courseId = $('#course-chart-period').data('course-id') || 0;
        
        if (!courseId) {
            console.error('Course ID not found for charts');
            return;
        }
        
        // Progress over time
        loadChart('progress_over_time', 'progress-chart', { course_id: courseId, time_period: period });
        
        // Quiz performance distribution
        loadChart('quiz_performance', 'quiz-performance-chart', { course_id: courseId, time_period: period });
        
        // Activity heatmap
        loadChart('student_activity', 'activity-heatmap-chart', { course_id: courseId, time_period: period });
        
        // Completion funnel
        loadChart('course_completion', 'completion-funnel-chart', { course_id: courseId });
    }
    
    /**
     * Refresh dashboard charts
     */
    function refreshDashboardCharts(period) {
        loadChart('enrollment_trend', 'enrollment-trend-chart', { time_period: period }, true);
    }
    
    /**
     * Refresh course charts
     */
    function refreshCourseCharts(courseId, period) {
        loadChart('progress_over_time', 'progress-chart', { course_id: courseId, time_period: period }, true);
        loadChart('quiz_performance', 'quiz-performance-chart', { course_id: courseId, time_period: period }, true);
        loadChart('student_activity', 'activity-heatmap-chart', { course_id: courseId, time_period: period }, true);
    }
    
    /**
     * Load chart data and render chart
     */
    function loadChart(chartType, canvasId, params, refresh = false) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) {
            console.error('Canvas not found: ' + canvasId);
            return;
        }
        
        const ctx = canvas.getContext('2d');
        
        // Show loading state
        showChartLoading(canvasId);
        
        // Destroy existing chart if refreshing
        if (refresh && charts[canvasId]) {
            charts[canvasId].destroy();
            delete charts[canvasId];
        }
        
        $.ajax({
            url: tutorAdvancedCharts.ajaxurl,
            method: 'POST',
            data: {
                action: 'tutor_advanced_chart_data',
                chart_type: chartType,
                course_id: params.course_id || 0,
                time_period: params.time_period || '30',
                nonce: tutorAdvancedCharts.nonce
            },
            success: function(response) {
                hideChartLoading(canvasId);
                
                if (response.success && response.data) {
                    renderChart(ctx, canvasId, response.data);
                } else {
                    showChartError(canvasId, response.data || tutorAdvancedCharts.strings.error);
                }
            },
            error: function(xhr, status, error) {
                hideChartLoading(canvasId);
                console.error('Chart AJAX error:', error);
                showChartError(canvasId, tutorAdvancedCharts.strings.error);
            }
        });
    }
    
    /**
     * Render chart using Chart.js
     */
    function renderChart(ctx, canvasId, chartData) {
        try {
            // Add default styling and configurations
            const config = {
                type: chartData.type,
                data: chartData.data,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            titleColor: '#fff',
                            bodyColor: '#fff',
                            borderColor: '#007cba',
                            borderWidth: 1
                        }
                    },
                    ...chartData.options
                }
            };
            
            // Create new chart
            charts[canvasId] = new Chart(ctx, config);
            
        } catch (error) {
            console.error('Error rendering chart:', error);
            showChartError(canvasId, tutorAdvancedCharts.strings.error);
        }
    }
    
    /**
     * Show loading state for chart
     */
    function showChartLoading(canvasId) {
        const container = $('#' + canvasId).closest('.chart-container');
        container.addClass('loading');
        
        if (!container.find('.chart-loading').length) {
            container.append(
                '<div class="chart-loading">' +
                '<div class="loading-spinner"></div>' +
                '<p>' + tutorAdvancedCharts.strings.loading + '</p>' +
                '</div>'
            );
        }
    }
    
    /**
     * Hide loading state for chart
     */
    function hideChartLoading(canvasId) {
        const container = $('#' + canvasId).closest('.chart-container');
        container.removeClass('loading');
        container.find('.chart-loading').remove();
    }
    
    /**
     * Show error state for chart
     */
    function showChartError(canvasId, message) {
        const container = $('#' + canvasId).closest('.chart-container');
        container.addClass('error');
        
        container.find('.chart-error').remove();
        container.append(
            '<div class="chart-error">' +
            '<p>' + escapeHtml(message) + '</p>' +
            '<button type="button" class="retry-chart" data-canvas="' + canvasId + '">' +
            'Retry' +
            '</button>' +
            '</div>'
        );
    }
    
    /**
     * Handle chart retry
     */
    $(document).on('click', '.retry-chart', function() {
        const canvasId = $(this).data('canvas');
        const container = $('#' + canvasId).closest('.chart-container');
        container.removeClass('error');
        container.find('.chart-error').remove();
        
        // Determine chart type and reload
        const courseId = $('#course-chart-period').data('course-id') || 0;
        const period = $('#dashboard-chart-period, #course-chart-period').val() || '30';
        
        let chartType = '';
        const params = { time_period: period };
        
        if (courseId) {
            params.course_id = courseId;
        }
        
        switch (canvasId) {
            case 'enrollment-trend-chart':
                chartType = 'enrollment_trend';
                break;
            case 'progress-chart':
                chartType = 'progress_over_time';
                break;
            case 'quiz-performance-chart':
                chartType = 'quiz_performance';
                break;
            case 'activity-heatmap-chart':
                chartType = 'student_activity';
                break;
            case 'completion-chart':
            case 'completion-funnel-chart':
                chartType = 'course_completion';
                break;
        }
        
        if (chartType) {
            loadChart(chartType, canvasId, params, true);
        }
    });
    
    /**
     * Chart animation on scroll (intersection observer)
     */
    if ('IntersectionObserver' in window) {
        const chartObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    const canvas = entry.target;
                    const canvasId = canvas.id;
                    
                    if (charts[canvasId]) {
                        // Animate chart if it hasn't been animated yet
                        if (!canvas.dataset.animated) {
                            charts[canvasId].update('active');
                            canvas.dataset.animated = 'true';
                        }
                    }
                }
            });
        }, {
            threshold: 0.3
        });
        
        // Observe all chart canvases
        $('.chart-container canvas').each(function() {
            chartObserver.observe(this);
        });
    }
    
    /**
     * Export chart as image
     */
    $(document).on('click', '.export-chart', function() {
        const canvasId = $(this).data('canvas');
        const chart = charts[canvasId];
        
        if (chart) {
            const canvas = chart.canvas;
            const url = canvas.toDataURL('image/png');
            
            // Create download link
            const link = document.createElement('a');
            link.download = 'chart-' + canvasId + '-' + new Date().getTime() + '.png';
            link.href = url;
            link.click();
        }
    });
    
    /**
     * Responsive chart handling
     */
    $(window).on('resize', debounce(function() {
        Object.keys(charts).forEach(function(canvasId) {
            if (charts[canvasId]) {
                charts[canvasId].resize();
            }
        });
    }, 250));
    
    /**
     * Utility functions
     */
    
    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    /**
     * Debounce function
     */
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
    
    /**
     * Format numbers for display
     */
    function formatNumber(num) {
        if (num >= 1000000) {
            return (num / 1000000).toFixed(1) + 'M';
        }
        if (num >= 1000) {
            return (num / 1000).toFixed(1) + 'K';
        }
        return num.toString();
    }
    
    /**
     * Generate random colors for charts
     */
    function generateColors(count) {
        const colors = [
            '#007cba', '#28a745', '#ffc107', '#dc3545', '#6f42c1',
            '#fd7e14', '#20c997', '#6c757d', '#e83e8c', '#17a2b8'
        ];
        
        const result = [];
        for (let i = 0; i < count; i++) {
            result.push(colors[i % colors.length]);
        }
        
        return result;
    }
    
});