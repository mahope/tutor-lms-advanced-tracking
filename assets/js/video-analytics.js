/**
 * TLAT Video Analytics — Admin Charts & Interactions
 *
 * Provides Chart.js visualizations for the Video Analytics admin page.
 * Loaded only on the Tutor Stats → Videos admin page.
 */
(function($) {
    'use strict';

    if (typeof tlatVideoAnalytics === 'undefined') {
        return;
    }

    var config = tlatVideoAnalytics;

    /**
     * Helper: Format seconds to "Xm Ys" string.
     */
    function formatDuration(seconds) {
        seconds = parseInt(seconds, 10) || 0;
        if (seconds < 60) return seconds + 's';
        var m = Math.floor(seconds / 60);
        var s = seconds % 60;
        if (m < 60) return m + 'm ' + s + 's';
        var h = Math.floor(m / 60);
        m = m % 60;
        return h + 'h ' + m + 'm';
    }

    /**
     * Render a completion distribution bar chart.
     *
     * @param {string} canvasId
     * @param {object} distData - { buckets: [...], counts: [...] }
     */
    window.tlatRenderCompletionDistChart = function(canvasId, distData) {
        var ctx = document.getElementById(canvasId);
        if (!ctx || !distData) return null;

        return new Chart(ctx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: distData.buckets,
                datasets: [{
                    label: config.i18n.students || 'Students',
                    data: distData.counts,
                    backgroundColor: '#3b82f6',
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 1 } }
                }
            }
        });
    };

    /**
     * Render a watch heatmap bar chart.
     *
     * @param {string} canvasId
     * @param {Array} segments - [ { label, pct, viewers } ]
     */
    window.tlatRenderHeatmapChart = function(canvasId, segments) {
        var ctx = document.getElementById(canvasId);
        if (!ctx || !segments || !segments.length) return null;

        var labels = segments.map(function(s) { return s.label; });
        var values = segments.map(function(s) { return s.pct; });

        // Color gradient: high viewership = warm (red), low = cool (blue)
        var colors = values.map(function(v) {
            var ratio = v / 100;
            var r = Math.round(59 + (239 - 59) * ratio);
            var g = Math.round(130 + (68 - 130) * ratio);
            var b = Math.round(246 + (68 - 246) * ratio);
            return 'rgba(' + r + ',' + g + ',' + b + ', 0.8)';
        });

        return new Chart(ctx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: config.i18n.viewers || 'Viewership %',
                    data: values,
                    backgroundColor: colors,
                    borderRadius: 2
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        ticks: { callback: function(v) { return v + '%'; } }
                    },
                    x: {
                        ticks: { maxRotation: 45, font: { size: 10 } }
                    }
                }
            }
        });
    };

    /**
     * Render a drop-off line chart showing viewer count across segments.
     *
     * @param {string} canvasId
     * @param {Array} segments - [ { label, viewers } ]
     */
    window.tlatRenderDropOffChart = function(canvasId, segments) {
        var ctx = document.getElementById(canvasId);
        if (!ctx || !segments || !segments.length) return null;

        var labels = segments.map(function(s) { return s.label; });
        var viewers = segments.map(function(s) { return s.viewers; });

        return new Chart(ctx.getContext('2d'), {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: config.i18n.viewers || 'Viewers',
                    data: viewers,
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    fill: true,
                    tension: 0.3,
                    pointRadius: 3,
                    pointBackgroundColor: '#3b82f6'
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true },
                    x: {
                        ticks: { maxRotation: 45, font: { size: 10 } }
                    }
                }
            }
        });
    };

})(jQuery);
