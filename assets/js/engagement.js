/**
 * Engagement Analytics Dashboard JavaScript
 */

(function($) {
    'use strict';

    // Wait for DOM to be ready
    $(document).ready(function() {
        TutorEngagement.init();
        PeakActivityHeatmap.init();
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

    /**
     * Peak Activity Hours Heatmap
     */
    var PeakActivityHeatmap = {
        nonce: '',
        ajaxUrl: '',
        chart: null,
        currentDays: 30,
        currentCourse: 0,

        init: function() {
            this.nonce = tutorAdvancedAdmin?.nonce || '';
            this.ajaxUrl = ajaxurl || tutorAdvancedAdmin?.ajaxurl || '';
            
            if (!this.nonce) {
                console.error('Peak Activity Heatmap: Missing nonce');
                return;
            }

            this.bindEvents();
            this.loadHeatmapData();
        },

        bindEvents: function() {
            var self = this;

            // Date range selector
            $('#heatmap-date-range').on('change', function() {
                self.currentDays = parseInt($(this).val(), 10);
                self.loadHeatmapData();
            });

            // Course filter
            $('#heatmap-course-filter').on('change', function() {
                self.currentCourse = parseInt($(this).val(), 10);
                self.loadHeatmapData();
            });
        },

        loadHeatmapData: function() {
            var self = this;
            var canvas = document.getElementById('peak-activity-heatmap');
            
            if (!canvas) return;

            // Show loading state
            $('#heatmap-loading').show();
            $('#heatmap-empty').hide();

            $.ajax({
                url: this.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'tutor_advanced_heatmap_data',
                    days: this.currentDays,
                    course_id: this.currentCourse,
                    nonce: this.nonce
                },
                success: function(response) {
                    $('#heatmap-loading').hide();
                    
                    if (response.success && response.data) {
                        self.renderHeatmap(canvas, response.data);
                    } else {
                        $('#heatmap-empty').show();
                        self.renderEmptyStats();
                    }
                },
                error: function() {
                    $('#heatmap-loading').hide();
                    $('#heatmap-empty').show();
                    self.renderEmptyStats();
                }
            });
        },

        renderHeatmap: function(canvas, chartData) {
            var self = this;
            var ctx = canvas.getContext('2d');

            // Destroy existing chart if it exists
            if (this.chart) {
                this.chart.destroy();
            }

            // Check if Chart.js matrix plugin is available
            if (typeof ChartMatrix === 'undefined' && typeof Chart.MatrixElement === 'undefined') {
                // Fallback: render a simple bar chart representation
                this.renderFallbackHeatmap(canvas, chartData);
                return;
            }

            // Prepare data for matrix chart
            var data = chartData.data.map(function(cell) {
                return {
                    x: cell.x,
                    y: cell.y,
                    v: cell.v
                };
            });

            // Color scale based on activity level
            var maxValue = chartData.max_value || 1;

            // Check which matrix implementation is available
            var MatrixElement = typeof ChartMatrix !== 'undefined' ? ChartMatrix.MatrixElement : (typeof Chart !== 'undefined' && Chart.MatrixElement ? Chart.MatrixElement : null);

            this.chart = new Chart(ctx, {
                type: 'matrix',
                data: {
                    datasets: [{
                        label: chartData.title,
                        data: data,
                        backgroundColor: function(context) {
                            var value = context.raw?.v || 0;
                            var intensity = value / maxValue;
                            // Blue color scale from light to dark
                            var r = 240 - (intensity * 220);
                            var g = 249 - (intensity * 230);
                            var b = 255 - (intensity * 235);
                            return 'rgb(' + Math.round(r) + ',' + Math.round(g) + ',' + Math.round(b) + ')';
                        },
                        borderColor: function(context) {
                            var value = context.raw?.v || 0;
                            var intensity = value / maxValue;
                            var r = 2 - (intensity * 0);
                            var g = 132 - (intensity * 120);
                            var b = 199 - (intensity * 190);
                            return 'rgb(' + Math.round(r) + ',' + Math.round(g) + ',' + Math.round(b) + ')';
                        },
                        borderWidth: 1,
                        width: function(context) {
                            var chartArea = context.chart.chartArea;
                            if (!chartArea) return 20;
                            return (chartArea.width / 24) - 1;
                        },
                        height: function(context) {
                            var chartArea = context.chart.chartArea;
                            if (!chartArea) return 20;
                            return (chartArea.height / 7) - 1;
                        }
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
                                title: function(context) {
                                    var data = context[0].raw;
                                    if (!data) return '';
                                    return data.d + ' ' + data.h;
                                },
                                label: function(context) {
                                    var data = context.raw;
                                    if (!data) return '';
                                    return data.v + ' active users';
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            type: 'linear',
                            min: -0.5,
                            max: 23.5,
                            offset: false,
                            grid: {
                                display: false
                            },
                            ticks: {
                                stepSize: 3,
                                callback: function(value) {
                                    return (value < 10 ? '0' : '') + Math.round(value) + ':00';
                                }
                            },
                            title: {
                                display: true,
                                text: 'Hour of Day',
                                font: {
                                    size: 12
                                }
                            }
                        },
                        y: {
                            type: 'linear',
                            min: -0.5,
                            max: 6.5,
                            offset: false,
                            grid: {
                                display: false
                            },
                            ticks: {
                                stepSize: 1,
                                callback: function(value) {
                                    var days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
                                    return days[Math.round(value)] || '';
                                }
                            },
                            title: {
                                display: true,
                                text: 'Day of Week',
                                font: {
                                    size: 12
                                }
                            }
                        }
                    }
                }
            });

            // Update summary stats
            this.updateSummaryStats(chartData.summary);
        },

        renderFallbackHeatmap: function(canvas, chartData) {
            // Fallback: Render as a grouped bar chart by day
            var ctx = canvas.getContext('2d');

            if (this.chart) {
                this.chart.destroy();
            }

            // Aggregate by day
            var days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            var dayTotals = new Array(7).fill(0);
            var hourTotals = new Array(24).fill(0);

            chartData.data.forEach(function(cell) {
                dayTotals[cell.y] = (dayTotals[cell.y] || 0) + cell.v;
                hourTotals[cell.x] = (hourTotals[cell.x] || 0) + cell.v;
            });

            this.chart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: days,
                    datasets: [{
                        label: 'Active Users',
                        data: dayTotals,
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.7)',
                            'rgba(54, 162, 235, 0.7)',
                            'rgba(255, 206, 86, 0.7)',
                            'rgba(75, 192, 192, 0.7)',
                            'rgba(153, 102, 255, 0.7)',
                            'rgba(255, 159, 64, 0.7)',
                            'rgba(199, 199, 199, 0.7)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        title: {
                            display: true,
                            text: chartData.description || 'Activity by Day (Matrix chart not available)'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Active Users'
                            }
                        }
                    }
                }
            });

            this.updateSummaryStats(chartData.summary);
        },

        updateSummaryStats: function(summary) {
            if (!summary) return;

            $('#heatmap-peak-value').text(summary.peak_value || '-');
            $('#heatmap-avg-value').text(summary.average_per_cell || '-');
            $('#heatmap-peak-time').text((summary.peak_day || '-') + ' ' + (summary.peak_hour || '-'));
        },

        renderEmptyStats: function() {
            $('#heatmap-peak-value').text('-');
            $('#heatmap-avg-value').text('-');
            $('#heatmap-peak-time').text('-');
        }
    };

    // Expose to global scope
    window.PeakActivityHeatmap = PeakActivityHeatmap;

})(jQuery);
