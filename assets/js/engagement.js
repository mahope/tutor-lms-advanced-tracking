/**
 * Engagement Analytics Dashboard JavaScript
 */

(function($) {
    'use strict';

    // Wait for DOM to be ready
    $(document).ready(function() {
        TutorEngagement.init();
    });

    var TutorEngagement = {
        nonce: '',
        ajaxUrl: '',
        currentDays: 30,

        init: function() {
            this.nonce = tutorAdvancedAdmin?.nonce || '';
            this.ajaxUrl = ajaxurl || tutorAdvancedAdmin?.ajaxurl || '';
            
            if (!this.nonce) {
                console.error('Engagement Analytics: Missing nonce');
                return;
            }

            this.bindEvents();
            this.loadEngagementOverview();
        },

        bindEvents: function() {
            var self = this;

            // Date range selector
            $('#engagement-date-range').on('change', function() {
                self.currentDays = parseInt($(this).val(), 10);
                self.loadAllCharts();
            });
        },

        loadEngagementOverview: function() {
            var self = this;

            $.ajax({
                url: this.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'tutor_advanced_engagement_data',
                    data_action: 'engagement_overview',
                    nonce: this.nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        self.updateOverviewStats(response.data);
                    } else {
                        self.setDefaultOverviewStats();
                    }
                },
                error: function() {
                    self.setDefaultOverviewStats();
                }
            });
        },

        updateOverviewStats: function(data) {
            $('#engagement-today-logins').text(data.today_logins || 0);
            $('#engagement-week-users').text(data.week_users || 0);
            $('#engagement-month-users').text(data.month_users || 0);
            $('#engagement-avg-session').text(data.avg_session_length || 'N/A');
        },

        setDefaultOverviewStats: function() {
            $('#engagement-today-logins').text('-');
            $('#engagement-week-users').text('-');
            $('#engagement-month-users').text('-');
            $('#engagement-avg-session').text('-');
        },

        loadAllCharts: function() {
            this.loadLoginFrequencyChart();
            this.loadSessionLengthChart();
            this.loadTopStudents();
        },

        loadLoginFrequencyChart: function() {
            var self = this;
            var canvas = document.getElementById('login-frequency-chart');
            
            if (!canvas) return;

            $.ajax({
                url: this.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'tutor_advanced_engagement_data',
                    data_action: 'login_frequency',
                    days: this.currentDays,
                    nonce: this.nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        self.renderLoginFrequencyChart(canvas, response.data);
                    } else {
                        self.renderEmptyChart(canvas, 'No login data available');
                    }
                },
                error: function() {
                    self.renderEmptyChart(canvas, 'Error loading login data');
                }
            });
        },

        renderLoginFrequencyChart: function(canvas, chartData) {
            var ctx = canvas.getContext('2d');

            // Destroy existing chart if it exists
            if (canvas.chartInstance) {
                canvas.chartInstance.destroy();
            }

            canvas.chartInstance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: chartData.labels,
                    datasets: chartData.datasets.map(function(ds) {
                        return {
                            label: ds.label,
                            data: ds.data,
                            borderColor: ds.borderColor,
                            backgroundColor: ds.backgroundColor,
                            fill: ds.fill !== false,
                            tension: ds.tension || 0.3,
                            pointRadius: 3,
                            pointHoverRadius: 5
                        };
                    })
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top'
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false
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
                        mode: 'nearest',
                        axis: 'x',
                        intersect: false
                    }
                }
            });
        },

        loadSessionLengthChart: function() {
            var self = this;
            var canvas = document.getElementById('session-length-chart');
            
            if (!canvas) return;

            $.ajax({
                url: this.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'tutor_advanced_engagement_data',
                    data_action: 'session_lengths',
                    days: this.currentDays,
                    nonce: this.nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        self.renderSessionLengthChart(canvas, response.data);
                    } else {
                        self.renderEmptyChart(canvas, 'No session data available');
                    }
                },
                error: function() {
                    self.renderEmptyChart(canvas, 'Error loading session data');
                }
            });
        },

        renderSessionLengthChart: function(canvas, chartData) {
            var ctx = canvas.getContext('2d');

            // Destroy existing chart if it exists
            if (canvas.chartInstance) {
                canvas.chartInstance.destroy();
            }

            canvas.chartInstance = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        label: chartData.summary?.total_sessions 
                            ? chartData.summary.total_sessions + ' sessions' 
                            : 'Sessions',
                        data: chartData.datasets[0].data,
                        backgroundColor: chartData.datasets[0].backgroundColor,
                        borderWidth: 1,
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    var total = chartData.summary?.total_sessions || 0;
                                    var value = context.raw;
                                    var percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                                    return value + ' sessions (' + percentage + '%)';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            },
                            title: {
                                display: true,
                                text: 'Number of Sessions'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Session Duration'
                            }
                        }
                    }
                }
            });
        },

        loadTopStudents: function() {
            var self = this;
            var tbody = $('#top-students-body');
            
            if (!tbody.length) return;

            $.ajax({
                url: this.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'tutor_advanced_engagement_data',
                    data_action: 'top_active_students',
                    limit: 10,
                    nonce: this.nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        self.renderTopStudents(response.data);
                    } else {
                        self.renderEmptyStudents();
                    }
                },
                error: function() {
                    self.renderEmptyStudents();
                }
            });
        },

        renderTopStudents: function(data) {
            var tbody = $('#top-students-body');
            var html = '';

            if (data.students && data.students.length > 0) {
                data.students.forEach(function(student) {
                    html += '<tr>';
                    html += '<td>';
                    html += '<div class="student-name">' + self.escapeHtml(student.name) + '</div>';
                    html += '<div class="student-email">' + self.escapeHtml(student.email) + '</div>';
                    html += '</td>';
                    html += '<td class="sessions-count">' + student.sessions + '</td>';
                    html += '<td class="total-time">' + student.total_time + '</td>';
                    html += '<td class="last-active">' + student.last_login + '</td>';
                    html += '</tr>';
                });
            } else {
                html = '<tr><td colspan="4" style="text-align: center; color: #646970;">';
                html += 'No student data available yet';
                html += '</td></tr>';
            }

            tbody.html(html);
        },

        renderEmptyStudents: function() {
            var tbody = $('#top-students-body');
            tbody.html('<tr><td colspan="4" style="text-align: center; color: #646970;">Unable to load student data</td></tr>');
        },

        renderEmptyChart: function(canvas, message) {
            var ctx = canvas.getContext('2d');
            var width = canvas.width || 300;
            var height = canvas.height || 200;

            ctx.clearRect(0, 0, width, height);
            ctx.font = '14px sans-serif';
            ctx.fillStyle = '#646970';
            ctx.textAlign = 'center';
            ctx.fillText(message, width / 2, height / 2);
        },

        escapeHtml: function(text) {
            if (!text) return '';
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    // Expose to global scope
    window.TutorEngagement = TutorEngagement;

})(jQuery);
