/**
 * Funnel Dashboard JavaScript
 * 
 * @package TutorAdvancedTracking
 * @since 2.0.0
 */

(function($) {
    'use strict';

    let funnelChart = null;

    const FunnelDashboard = {
        init: function() {
            this.bindEvents();
            this.loadFunnelData();
        },

        bindEvents: function() {
            $('#tlat-refresh-funnel').on('click', () => this.loadFunnelData());
            $('#tlat-course-select, #tlat-period-select').on('change', () => this.loadFunnelData());
        },

        loadFunnelData: function() {
            const courseId = $('#tlat-course-select').val();
            const days = $('#tlat-period-select').val();

            this.showLoading();

            $.ajax({
                url: tlatFunnel.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'tlat_get_funnel_data',
                    nonce: tlatFunnel.nonce,
                    course_id: courseId,
                    days: days
                },
                success: (response) => {
                    if (response.success) {
                        this.renderFunnel(response.data);
                        this.loadDropoffAnalysis(courseId, days);
                    } else {
                        this.showError(response.data || 'Failed to load funnel data');
                    }
                },
                error: () => {
                    this.showError('Request failed. Please try again.');
                }
            });
        },

        loadDropoffAnalysis: function(courseId, days) {
            $.ajax({
                url: tlatFunnel.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'tlat_get_dropoff_analysis',
                    nonce: tlatFunnel.nonce,
                    course_id: courseId,
                    days: days
                },
                success: (response) => {
                    if (response.success) {
                        this.renderDropoffAnalysis(response.data);
                    }
                }
            });
        },

        renderFunnel: function(data) {
            this.renderFunnelChart(data.stages);
            this.renderMetrics(data.metrics);
        },

        renderFunnelChart: function(stages) {
            const ctx = document.getElementById('tlat-funnel-chart');
            if (!ctx) return;

            const labels = stages.map(s => this.getStageLabel(s.name));
            const counts = stages.map(s => s.count);
            const percentages = stages.map(s => s.percentage);

            // Destroy existing chart
            if (funnelChart) {
                funnelChart.destroy();
            }

            // Create gradient colors for funnel effect
            const colors = [
                'rgba(59, 130, 246, 0.8)',   // Blue - Enrolled
                'rgba(16, 185, 129, 0.8)',   // Green - Started
                'rgba(245, 158, 11, 0.8)',   // Yellow - In Progress
                'rgba(239, 68, 68, 0.8)'     // Red - Completed
            ];

            funnelChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: tlatFunnel.i18n.enrolled,
                        data: counts,
                        backgroundColor: colors,
                        borderColor: colors.map(c => c.replace('0.8', '1')),
                        borderWidth: 1,
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: (context) => {
                                    const index = context.dataIndex;
                                    const count = counts[index];
                                    const pct = percentages[index];
                                    return `${count} students (${pct}% of enrolled)`;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            grid: {
                                display: true,
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        },
                        y: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        },

        renderMetrics: function(metrics) {
            const container = $('#tlat-funnel-metrics');
            
            const html = `
                <div class="tlat-metric-cards">
                    <div class="tlat-metric-card">
                        <div class="tlat-metric-value">${metrics.total_enrolled}</div>
                        <div class="tlat-metric-label">${tlatFunnel.i18n.enrolled}</div>
                    </div>
                    <div class="tlat-metric-card">
                        <div class="tlat-metric-value">${metrics.total_completed}</div>
                        <div class="tlat-metric-label">${tlatFunnel.i18n.completed}</div>
                    </div>
                    <div class="tlat-metric-card highlight">
                        <div class="tlat-metric-value">${metrics.overall_conversion_rate}%</div>
                        <div class="tlat-metric-label">${tlatFunnel.i18n.conversionRate}</div>
                    </div>
                    <div class="tlat-metric-card">
                        <div class="tlat-metric-value">${metrics.avg_time_to_complete || '—'}</div>
                        <div class="tlat-metric-label">Avg. Days to Complete</div>
                    </div>
                    <div class="tlat-metric-card">
                        <div class="tlat-metric-value">${metrics.median_progress}%</div>
                        <div class="tlat-metric-label">Median Progress</div>
                    </div>
                </div>
            `;

            container.html(html);
        },

        renderDropoffAnalysis: function(data) {
            const container = $('#tlat-dropoff-analysis');
            const recsContainer = $('#tlat-recommendations');

            // Render critical lessons table
            if (data.critical_lessons && data.critical_lessons.length > 0) {
                const tableHtml = `
                    <table class="tlat-dropoff-table">
                        <thead>
                            <tr>
                                <th>Course</th>
                                <th>Lesson</th>
                                <th>Position</th>
                                <th>Viewers</th>
                                <th>Drop-off</th>
                                <th>Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${data.critical_lessons.map(l => `
                                <tr class="${l.dropoff_rate > 50 ? 'critical' : 'warning'}">
                                    <td>${this.escapeHtml(l.course_title)}</td>
                                    <td>${this.escapeHtml(l.lesson_title)}</td>
                                    <td>#${l.lesson_index + 1}</td>
                                    <td>${l.viewers}</td>
                                    <td>-${l.dropoff}</td>
                                    <td><span class="tlat-badge ${l.dropoff_rate > 50 ? 'critical' : 'warning'}">${l.dropoff_rate}%</span></td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                `;
                container.html(tableHtml);
            } else {
                container.html('<p class="tlat-no-data">No critical drop-off points detected.</p>');
            }

            // Render recommendations
            if (data.recommendations && data.recommendations.length > 0) {
                const recsHtml = data.recommendations.map(r => `
                    <div class="tlat-recommendation ${r.type}">
                        <div class="tlat-rec-icon">${this.getRecIcon(r.type)}</div>
                        <div class="tlat-rec-content">
                            <h4>${this.escapeHtml(r.title)}</h4>
                            <p>${this.escapeHtml(r.message)}</p>
                            ${r.action ? `<button class="button button-small">${this.escapeHtml(r.action)}</button>` : ''}
                        </div>
                    </div>
                `).join('');
                recsContainer.html(recsHtml);
            }
        },

        getStageLabel: function(stage) {
            const labels = {
                'enrolled': tlatFunnel.i18n.enrolled,
                'started': tlatFunnel.i18n.started,
                'in_progress': tlatFunnel.i18n.inProgress,
                'completed': tlatFunnel.i18n.completed
            };
            return labels[stage] || stage;
        },

        getRecIcon: function(type) {
            const icons = {
                'warning': '⚠️',
                'info': 'ℹ️',
                'success': '✅',
                'error': '❌'
            };
            return icons[type] || '📌';
        },

        showLoading: function() {
            $('#tlat-funnel-metrics').html('<div class="tlat-loading">Loading...</div>');
            $('#tlat-dropoff-analysis').html('<div class="tlat-loading">Loading...</div>');
        },

        showError: function(message) {
            $('#tlat-funnel-metrics').html(`<div class="tlat-error">${this.escapeHtml(message)}</div>`);
        },

        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    $(document).ready(() => FunnelDashboard.init());

})(jQuery);
