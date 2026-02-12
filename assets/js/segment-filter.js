/**
 * Segment Filter JavaScript
 * 
 * Handles segment filtering for charts and data views
 * 
 * @package TutorLMS_Advanced_Tracking
 * @since 1.1.0
 */

(function($) {
    'use strict';

    /**
     * SegmentFilter Class
     */
    class SegmentFilter {
        constructor(container, options = {}) {
            this.$container = $(container);
            this.chartId = this.$container.data('chart') || 'main';
            this.activeFilters = [];
            this.segmentTypes = options.segmentTypes || {};
            this.chartInstance = options.chartInstance || null;
            this.onFilterChange = options.onFilterChange || null;
            
            this.init();
        }

        init() {
            this.bindEvents();
            this.loadSavedSegments();
        }

        bindEvents() {
            const self = this;

            // Add filter button
            this.$container.on('click', '.tlat-add-filter', function() {
                self.$container.find('.tlat-filter-builder').slideDown(200);
                $(this).hide();
            });

            // Cancel filter
            this.$container.on('click', '.tlat-cancel-filter', function() {
                self.resetFilterBuilder();
            });

            // Filter type change
            this.$container.on('change', '.tlat-filter-type', function() {
                self.onTypeChange($(this).val());
            });

            // Filter value change
            this.$container.on('change', '.tlat-filter-value', function() {
                if ($(this).val()) {
                    self.$container.find('.tlat-apply-filter').show();
                } else {
                    self.$container.find('.tlat-apply-filter').hide();
                }
            });

            // Apply filter
            this.$container.on('click', '.tlat-apply-filter', function() {
                self.applyFilter();
            });

            // Remove filter
            this.$container.on('click', '.tlat-remove-filter', function() {
                const index = $(this).closest('.tlat-filter-tag').data('index');
                self.removeFilter(index);
            });

            // Clear all filters
            this.$container.on('click', '.tlat-clear-filters', function() {
                self.clearAllFilters();
            });

            // Save segment
            this.$container.on('click', '.tlat-save-segment', function() {
                self.saveSegment();
            });

            // Load saved segment
            this.$container.on('click', '.tlat-load-segment', function() {
                const segmentId = $(this).data('segment-id');
                self.loadSegment(segmentId);
            });
        }

        onTypeChange(type) {
            const $value = this.$container.find('.tlat-filter-value');
            const $apply = this.$container.find('.tlat-apply-filter');

            if (!type || !this.segmentTypes[type]) {
                $value.hide().empty();
                $apply.hide();
                return;
            }

            const options = this.segmentTypes[type].options;
            let html = '<option value="">' + tlatSegment.i18n.select + '</option>';

            $.each(options, function(key, opt) {
                html += '<option value="' + key + '">' + opt.label + ' (' + opt.count + ')</option>';
            });

            $value.html(html).show();
        }

        applyFilter() {
            const type = this.$container.find('.tlat-filter-type').val();
            const value = this.$container.find('.tlat-filter-value').val();
            const label = this.$container.find('.tlat-filter-value option:selected').text();

            if (!type || !value) return;

            // Check for duplicate
            const exists = this.activeFilters.some(f => f.type === type && f.value === value);
            if (exists) {
                this.showNotice(tlatSegment.i18n.duplicateFilter, 'warning');
                return;
            }

            this.activeFilters.push({
                type: type,
                value: value,
                label: label,
                typeLabel: this.segmentTypes[type].label
            });

            this.renderActiveFilters();
            this.updateChart();
            this.resetFilterBuilder();
        }

        removeFilter(index) {
            this.activeFilters.splice(index, 1);
            this.renderActiveFilters();
            this.updateChart();
        }

        clearAllFilters() {
            this.activeFilters = [];
            this.renderActiveFilters();
            this.updateChart();
        }

        renderActiveFilters() {
            const self = this;
            const $list = this.$container.find('.tlat-active-filters');
            $list.empty();

            if (this.activeFilters.length === 0) {
                this.$container.find('.tlat-filter-actions').hide();
                return;
            }

            $.each(this.activeFilters, function(i, filter) {
                const icon = self.segmentTypes[filter.type]?.icon || 'dashicons-filter';
                $list.append(
                    '<span class="tlat-filter-tag" data-index="' + i + '" title="' + filter.typeLabel + '">' +
                    '<span class="dashicons ' + icon + '"></span> ' +
                    self.escapeHtml(filter.label) +
                    '<button type="button" class="tlat-remove-filter" aria-label="' + tlatSegment.i18n.remove + '">&times;</button>' +
                    '</span>'
                );
            });

            this.$container.find('.tlat-filter-actions').show();
        }

        updateChart() {
            const self = this;

            // Show loading
            this.showLoading(true);

            // Prepare filters for API
            const filters = this.activeFilters.map(f => ({
                type: f.type,
                value: f.value
            }));

            // Call AJAX to get filtered data
            $.ajax({
                url: tlatSegment.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'tlat_filter_chart_data',
                    nonce: tlatSegment.nonce,
                    filters: JSON.stringify(filters),
                    chart_type: this.getActiveChartType()
                },
                success: function(response) {
                    self.showLoading(false);

                    if (response.success) {
                        self.updateChartData(response.data);
                        
                        // Trigger custom event
                        $(document).trigger('tlat_filter_applied', [self.activeFilters, self.chartId, response.data]);
                        
                        // Callback
                        if (typeof self.onFilterChange === 'function') {
                            self.onFilterChange(self.activeFilters, response.data);
                        }
                    } else {
                        self.showNotice(response.data || tlatSegment.i18n.error, 'error');
                    }
                },
                error: function() {
                    self.showLoading(false);
                    self.showNotice(tlatSegment.i18n.error, 'error');
                }
            });
        }

        updateChartData(data) {
            if (!this.chartInstance) {
                // Try to find chart by ID
                const chartCanvas = document.getElementById('tlat-chart-' + this.chartId);
                if (chartCanvas && chartCanvas._chart) {
                    this.chartInstance = chartCanvas._chart;
                }
            }

            if (this.chartInstance) {
                this.chartInstance.data.labels = data.labels;
                this.chartInstance.data.datasets = data.datasets;
                this.chartInstance.update('none'); // Update without animation
            }

            // Update stats if present
            if (data.stats) {
                this.updateStats(data.stats);
            }

            // Show message if no data
            if (data.message) {
                this.showNotice(data.message, 'info');
            }
        }

        updateStats(stats) {
            const $stats = this.$container.siblings('.tlat-segment-stats');
            if ($stats.length === 0) return;

            $.each(stats, function(key, value) {
                $stats.find('[data-stat="' + key + '"] .tlat-segment-stat-value').text(value);
            });
        }

        getActiveChartType() {
            // Determine chart type from context
            const $chartContainer = this.$container.closest('.tlat-chart-container');
            return $chartContainer.data('chart-type') || 'completion';
        }

        saveSegment() {
            const self = this;
            const name = prompt(tlatSegment.i18n.enterSegmentName);

            if (!name || name.trim() === '') return;

            $.ajax({
                url: tlatSegment.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'tlat_save_segment',
                    nonce: tlatSegment.nonce,
                    name: name.trim(),
                    filters: JSON.stringify(this.activeFilters.map(f => ({
                        type: f.type,
                        value: f.value
                    })))
                },
                success: function(response) {
                    if (response.success) {
                        self.showNotice(tlatSegment.i18n.segmentSaved, 'success');
                        self.loadSavedSegments();
                    } else {
                        self.showNotice(response.data || tlatSegment.i18n.error, 'error');
                    }
                },
                error: function() {
                    self.showNotice(tlatSegment.i18n.error, 'error');
                }
            });
        }

        loadSavedSegments() {
            const self = this;

            $.ajax({
                url: tlatSegment.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'tlat_get_segments',
                    nonce: tlatSegment.nonce
                },
                success: function(response) {
                    if (response.success && response.data.types) {
                        self.segmentTypes = response.data.types;
                    }
                }
            });
        }

        loadSegment(segmentId) {
            // Load a saved segment's filters
            if (this.segmentTypes.custom && this.segmentTypes.custom.options[segmentId]) {
                const segment = this.segmentTypes.custom.options[segmentId];
                // This would need additional data from the saved segment
                this.showNotice(tlatSegment.i18n.segmentLoaded, 'success');
            }
        }

        resetFilterBuilder() {
            this.$container.find('.tlat-filter-builder').slideUp(200);
            this.$container.find('.tlat-filter-type').val('');
            this.$container.find('.tlat-filter-value').hide().empty();
            this.$container.find('.tlat-apply-filter').hide();
            this.$container.find('.tlat-add-filter').show();
        }

        showLoading(show) {
            const $loader = this.$container.find('.tlat-filter-loading');
            if (show) {
                if ($loader.length === 0) {
                    this.$container.append('<div class="tlat-filter-loading"><span class="spinner is-active"></span></div>');
                }
            } else {
                $loader.remove();
            }
        }

        showNotice(message, type = 'info') {
            const $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + this.escapeHtml(message) + '</p></div>');
            this.$container.prepend($notice);
            
            setTimeout(function() {
                $notice.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 3000);
        }

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Public API
        getFilters() {
            return this.activeFilters.slice();
        }

        setFilters(filters) {
            this.activeFilters = filters;
            this.renderActiveFilters();
            this.updateChart();
        }

        setChartInstance(chart) {
            this.chartInstance = chart;
        }
    }

    // Global initialization
    window.TLATSegmentFilter = SegmentFilter;

    // Auto-init on document ready
    $(function() {
        $('.tlat-segment-filter').each(function() {
            new SegmentFilter(this);
        });
    });

    // Expose for external use
    $.fn.tlatSegmentFilter = function(options) {
        return this.each(function() {
            if (!$(this).data('tlatSegmentFilter')) {
                $(this).data('tlatSegmentFilter', new SegmentFilter(this, options));
            }
        });
    };

})(jQuery);
