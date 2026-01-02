/**
 * Admin JavaScript for Tutor LMS Advanced Tracking
 */

(function($) {
    'use strict';
    
    const TutorAdvancedAdmin = {
        
        init: function() {
            this.bindEvents();
            this.initTabs();
            this.loadDashboardStats();
        },
        
        bindEvents: function() {
            // Debug tools
            $('.debug-action-btn').on('click', this.runDebugAction);
            $('.cache-action-btn').on('click', this.runCacheAction);
            
            // Settings form
            $('#tutor-settings-form').on('submit', this.saveSettings);
            
            // Refresh buttons
            $('.refresh-data-btn').on('click', this.refreshData);
            
            // Export buttons
            $('.export-btn').on('click', this.exportData);
            
            // Clear log button
            $('.clear-log-btn').on('click', this.clearLogs);
        },
        
        initTabs: function() {
            $('.tutor-tabs a').on('click', function(e) {
                e.preventDefault();
                
                const $tab = $(this);
                const target = $tab.attr('href');
                
                // Update active tab
                $('.tutor-tabs a').removeClass('active');
                $tab.addClass('active');
                
                // Show/hide content
                $('.tab-content').hide();
                $(target).show().addClass('tutor-fade-in');
            });
            
            // Activate first tab
            $('.tutor-tabs a:first').trigger('click');
        },
        
        loadDashboardStats: function() {
            if (!$('.tutor-admin-dashboard').length) return;
            
            this.showLoading('.stats-grid');
            
            $.ajax({
                url: tutorAdvancedAdmin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'tutor_advanced_dashboard_stats',
                    nonce: tutorAdvancedAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        TutorAdvancedAdmin.updateDashboardStats(response.data);
                    } else {
                        TutorAdvancedAdmin.showAlert('error', response.data || 'Failed to load stats');
                    }
                },
                error: function() {
                    TutorAdvancedAdmin.showAlert('error', 'Network error loading stats');
                },
                complete: function() {
                    TutorAdvancedAdmin.hideLoading('.stats-grid');
                }
            });
        },
        
        updateDashboardStats: function(data) {
            $('.stat-item[data-stat="courses"] .stat-number').text(data.total_courses || 0);
            $('.stat-item[data-stat="students"] .stat-number').text(data.total_students || 0);
            $('.stat-item[data-stat="completion"] .stat-number').text((data.avg_completion || 0) + '%');
            $('.stat-item[data-stat="quiz-score"] .stat-number').text((data.avg_quiz_score || 0) + '%');
        },
        
        runDebugAction: function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            const action = $btn.data('action');
            const outputId = $btn.data('output');
            
            if (!confirm(tutorAdvancedAdmin.strings.confirm_debug_run)) {
                return;
            }
            
            TutorAdvancedAdmin.setButtonLoading($btn, true);
            
            $.ajax({
                url: tutorAdvancedAdmin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'tutor_advanced_debug_action',
                    debug_action: action,
                    nonce: tutorAdvancedAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        TutorAdvancedAdmin.showDebugOutput(outputId, response.data);
                        TutorAdvancedAdmin.showAlert('success', 'Debug action completed');
                    } else {
                        TutorAdvancedAdmin.showAlert('error', response.message || 'Debug action failed');
                    }
                },
                error: function() {
                    TutorAdvancedAdmin.showAlert('error', 'Network error running debug action');
                },
                complete: function() {
                    TutorAdvancedAdmin.setButtonLoading($btn, false);
                }
            });
        },
        
        runCacheAction: function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            const action = $btn.data('action');
            
            if (action === 'clear_all' && !confirm(tutorAdvancedAdmin.strings.confirm_cache_clear)) {
                return;
            }
            
            TutorAdvancedAdmin.setButtonLoading($btn, true);
            
            $.ajax({
                url: tutorAdvancedAdmin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'tutor_advanced_cache_action',
                    cache_action: action,
                    nonce: tutorAdvancedAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        TutorAdvancedAdmin.showAlert('success', response.message);
                        // Refresh dashboard if on dashboard page
                        if ($('.tutor-admin-dashboard').length) {
                            TutorAdvancedAdmin.loadDashboardStats();
                        }
                    } else {
                        TutorAdvancedAdmin.showAlert('error', response.message || 'Cache action failed');
                    }
                },
                error: function() {
                    TutorAdvancedAdmin.showAlert('error', 'Network error running cache action');
                },
                complete: function() {
                    TutorAdvancedAdmin.setButtonLoading($btn, false);
                }
            });
        },
        
        saveSettings: function(e) {
            e.preventDefault();
            
            const $form = $(this);
            const $submitBtn = $form.find('input[type="submit"]');
            
            TutorAdvancedAdmin.setButtonLoading($submitBtn, true);
            
            $.ajax({
                url: $form.attr('action'),
                type: 'POST',
                data: $form.serialize(),
                success: function() {
                    TutorAdvancedAdmin.showAlert('success', 'Settings saved successfully');
                },
                error: function() {
                    TutorAdvancedAdmin.showAlert('error', 'Failed to save settings');
                },
                complete: function() {
                    TutorAdvancedAdmin.setButtonLoading($submitBtn, false);
                }
            });
        },
        
        refreshData: function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            const dataType = $btn.data('type');
            
            TutorAdvancedAdmin.setButtonLoading($btn, true);
            
            // Clear relevant cache first
            $.ajax({
                url: tutorAdvancedAdmin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'tutor_advanced_cache_action',
                    cache_action: 'clear_' + dataType,
                    nonce: tutorAdvancedAdmin.nonce
                },
                success: function() {
                    // Reload the page to show fresh data
                    window.location.reload();
                },
                error: function() {
                    TutorAdvancedAdmin.showAlert('error', 'Failed to refresh data');
                    TutorAdvancedAdmin.setButtonLoading($btn, false);
                }
            });
        },
        
        exportData: function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            const exportType = $btn.data('type');
            const format = $btn.data('format') || 'csv';
            
            TutorAdvancedAdmin.setButtonLoading($btn, true);
            
            $.ajax({
                url: tutorAdvancedAdmin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'tutor_advanced_export_data',
                    export_type: exportType,
                    format: format,
                    nonce: tutorAdvancedAdmin.nonce
                },
                success: function(response) {
                    if (response.success && response.data.download_url) {
                        // Trigger download
                        const link = document.createElement('a');
                        link.href = response.data.download_url;
                        link.download = response.data.filename;
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                        
                        TutorAdvancedAdmin.showAlert('success', 'Export completed');
                    } else {
                        TutorAdvancedAdmin.showAlert('error', response.message || 'Export failed');
                    }
                },
                error: function() {
                    TutorAdvancedAdmin.showAlert('error', 'Network error during export');
                },
                complete: function() {
                    TutorAdvancedAdmin.setButtonLoading($btn, false);
                }
            });
        },
        
        clearLogs: function(e) {
            e.preventDefault();
            
            if (!confirm('Are you sure you want to clear all logs?')) {
                return;
            }
            
            const $btn = $(this);
            TutorAdvancedAdmin.setButtonLoading($btn, true);
            
            $.ajax({
                url: tutorAdvancedAdmin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'tutor_advanced_clear_logs',
                    nonce: tutorAdvancedAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('.debug-output').empty().hide();
                        TutorAdvancedAdmin.showAlert('success', 'Logs cleared');
                    } else {
                        TutorAdvancedAdmin.showAlert('error', response.message || 'Failed to clear logs');
                    }
                },
                error: function() {
                    TutorAdvancedAdmin.showAlert('error', 'Network error clearing logs');
                },
                complete: function() {
                    TutorAdvancedAdmin.setButtonLoading($btn, false);
                }
            });
        },
        
        showDebugOutput: function(outputId, data) {
            const $output = $('#' + outputId);
            if (!$output.length) return;
            
            let content = '';
            if (typeof data === 'object') {
                content = '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
            } else {
                content = '<pre>' + data + '</pre>';
            }
            
            $output.html(content).addClass('show tutor-fade-in');
        },
        
        showAlert: function(type, message) {
            // Remove existing alerts
            $('.tutor-alert').remove();
            
            const alertHtml = `
                <div class="tutor-alert tutor-alert-${type} tutor-fade-in">
                    ${message}
                </div>
            `;
            
            $('.wrap').prepend(alertHtml);
            
            // Auto-hide after 5 seconds
            setTimeout(function() {
                $('.tutor-alert').fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        },
        
        setButtonLoading: function($btn, loading) {
            if (loading) {
                $btn.addClass('loading').prop('disabled', true);
                $btn.data('original-text', $btn.val() || $btn.text());
                
                if ($btn.is('input')) {
                    $btn.val('Loading...');
                } else {
                    $btn.text('Loading...');
                }
            } else {
                $btn.removeClass('loading').prop('disabled', false);
                const originalText = $btn.data('original-text');
                
                if ($btn.is('input')) {
                    $btn.val(originalText);
                } else {
                    $btn.text(originalText);
                }
            }
        },
        
        showLoading: function(selector) {
            $(selector).html('<div class="tutor-loading">Loading...</div>');
        },
        
        hideLoading: function(selector) {
            $(selector).find('.tutor-loading').remove();
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        TutorAdvancedAdmin.init();
    });
    
    // Make available globally
    window.TutorAdvancedAdmin = TutorAdvancedAdmin;
    
})(jQuery);