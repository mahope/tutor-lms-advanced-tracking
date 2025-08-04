/**
 * Tutor LMS Advanced Tracking Dashboard JavaScript
 */

jQuery(document).ready(function($) {
    
    // Search functionality
    let searchTimeout;
    const searchInput = $('#dashboard-search');
    const searchType = $('#search-type');
    const searchResults = $('#search-results');
    const searchResultsContent = $('.search-results-content');
    
    // Handle search input
    searchInput.on('input', function() {
        clearTimeout(searchTimeout);
        const query = $(this).val().trim();
        
        if (query.length < 2) {
            searchResults.hide();
            return;
        }
        
        searchTimeout = setTimeout(function() {
            performSearch(query, searchType.val());
        }, 300);
    });
    
    // Handle search type change
    searchType.on('change', function() {
        const query = searchInput.val().trim();
        if (query.length >= 2) {
            performSearch(query, $(this).val());
        }
    });
    
    /**
     * Perform search via AJAX
     */
    function performSearch(query, type) {
        searchResultsContent.html('<div class="loading">Searching...</div>');
        searchResults.show();
        
        $.ajax({
            url: tutorAdvancedTracking.ajaxurl,
            method: 'POST',
            data: {
                action: 'tutor_advanced_search',
                query: query,
                type: type,
                nonce: tutorAdvancedTracking.nonce
            },
            success: function(response) {
                if (response.success) {
                    displaySearchResults(response.data);
                } else {
                    searchResultsContent.html('<div class="error">Search failed. Please try again.</div>');
                }
            },
            error: function() {
                searchResultsContent.html('<div class="error">Search failed. Please try again.</div>');
            }
        });
    }
    
    /**
     * Display search results
     */
    function displaySearchResults(data) {
        let html = '';
        
        if (data.courses && data.courses.length > 0) {
            html += '<div class="search-section">';
            html += '<h4>Courses</h4>';
            html += '<div class="search-items">';
            
            data.courses.forEach(function(course) {
                html += '<div class="search-item">';
                html += '<div class="search-item-title">';
                html += '<a href="' + course.url + '">' + escapeHtml(course.title) + '</a>';
                html += '</div>';
                html += '<div class="search-item-type">Course</div>';
                html += '</div>';
            });
            
            html += '</div>';
            html += '</div>';
        }
        
        if (data.users && data.users.length > 0) {
            html += '<div class="search-section">';
            html += '<h4>Students</h4>';
            html += '<div class="search-items">';
            
            data.users.forEach(function(user) {
                html += '<div class="search-item">';
                html += '<div class="search-item-title">';
                html += '<a href="' + user.url + '">' + escapeHtml(user.name) + '</a>';
                html += '</div>';
                html += '<div class="search-item-details">' + escapeHtml(user.email) + '</div>';
                html += '<div class="search-item-type">Student</div>';
                html += '</div>';
            });
            
            html += '</div>';
            html += '</div>';
        }
        
        if (html === '') {
            html = '<div class="no-results">No results found for your search.</div>';
        }
        
        searchResultsContent.html(html);
    }
    
    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Hide search results when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.dashboard-search, .search-results').length) {
            searchResults.hide();
        }
    });
    
    // Export functionality
    $('.export-csv').on('click', function() {
        const exportType = $(this).data('type');
        const courseId = $(this).data('course-id') || 0;
        
        // Create form and submit
        const form = $('<form>', {
            method: 'POST',
            action: tutorAdvancedTracking.ajaxurl
        });
        
        form.append($('<input>', {
            type: 'hidden',
            name: 'action',
            value: 'tutor_advanced_export_csv'
        }));
        
        form.append($('<input>', {
            type: 'hidden',
            name: 'export_type',
            value: exportType
        }));
        
        form.append($('<input>', {
            type: 'hidden',
            name: 'course_id',
            value: courseId
        }));
        
        form.append($('<input>', {
            type: 'hidden',
            name: 'nonce',
            value: tutorAdvancedTracking.exportNonce
        }));
        
        $('body').append(form);
        form.submit();
        form.remove();
    });
    
    $('.export-pdf').on('click', function() {
        const exportType = $(this).data('type');
        const courseId = $(this).data('course-id') || 0;
        
        // Create form and submit
        const form = $('<form>', {
            method: 'POST',
            action: tutorAdvancedTracking.ajaxurl
        });
        
        form.append($('<input>', {
            type: 'hidden',
            name: 'action',
            value: 'tutor_advanced_export_pdf'
        }));
        
        form.append($('<input>', {
            type: 'hidden',
            name: 'export_type',
            value: exportType
        }));
        
        form.append($('<input>', {
            type: 'hidden',
            name: 'course_id',
            value: courseId
        }));
        
        form.append($('<input>', {
            type: 'hidden',
            name: 'nonce',
            value: tutorAdvancedTracking.exportNonce
        }));
        
        $('body').append(form);
        form.submit();
        form.remove();
    });
    
    // Progress bar animation
    $('.progress-fill').each(function() {
        const $this = $(this);
        const width = $this.css('width');
        $this.css('width', '0%');
        
        setTimeout(function() {
            $this.animate({
                width: width
            }, 1000);
        }, 200);
    });
    
    // Smooth scroll for anchor links
    $('a[href^="#"]').on('click', function(e) {
        e.preventDefault();
        const target = $(this.getAttribute('href'));
        if (target.length) {
            $('html, body').animate({
                scrollTop: target.offset().top - 20
            }, 500);
        }
    });
    
    // Tooltip functionality for truncated text
    $('.quiz-score, .status').each(function() {
        const $this = $(this);
        if (this.scrollWidth > this.clientWidth) {
            $this.attr('title', $this.text());
        }
    });
    
    // Enhanced table interactions
    $('.courses-table tbody tr, .students-table tbody tr, .quiz-performance-table tbody tr').hover(
        function() {
            $(this).addClass('hover');
        },
        function() {
            $(this).removeClass('hover');
        }
    );
    
    // Activity status warnings
    $('.status-indicator.inactive').each(function() {
        const $this = $(this);
        if (!$this.find('.inactive-warning').length) {
            // Add pulse animation for inactive users
            $this.css('animation', 'pulse 2s infinite');
        }
    });
    
    // Quiz performance highlighting
    $('.quiz-score').each(function() {
        const $this = $(this);
        const score = parseFloat($this.text());
        
        if (score >= 90) {
            $this.addClass('excellent');
        } else if (score >= 80) {
            $this.addClass('good');
        } else if (score >= 70) {
            $this.addClass('average');
        }
    });
    
    // Problem areas highlighting
    $('.problem-area-item').each(function() {
        const $this = $(this);
        const wrongCount = parseInt($this.find('.wrong-count').text());
        
        if (wrongCount >= 5) {
            $this.addClass('critical');
        } else if (wrongCount >= 3) {
            $this.addClass('warning');
        }
    });
    
    // Mobile responsiveness enhancements
    function handleMobileView() {
        if (window.innerWidth <= 768) {
            // Convert tables to mobile-friendly format
            $('.courses-table, .students-table, .quiz-performance-table').each(function() {
                if (!$(this).hasClass('mobile-enhanced')) {
                    $(this).addClass('mobile-enhanced');
                    
                    // Add mobile-specific styling
                    $(this).find('td').each(function() {
                        const headerText = $(this).closest('table').find('th').eq($(this).index()).text();
                        $(this).attr('data-label', headerText);
                    });
                }
            });
        }
    }
    
    handleMobileView();
    $(window).on('resize', handleMobileView);
    
    // Performance monitoring
    if (window.performance && window.performance.timing) {
        const loadTime = window.performance.timing.domContentLoadedEventEnd - window.performance.timing.navigationStart;
        if (loadTime > 3000) {
            console.warn('Dashboard loading time is slow: ' + loadTime + 'ms');
        }
    }
    
    // Accessibility enhancements
    $('.btn').on('keypress', function(e) {
        if (e.which === 13) { // Enter key
            $(this)[0].click();
        }
    });
    
    // Add focus indicators
    $('.btn, input, select').on('focus', function() {
        $(this).addClass('focused');
    }).on('blur', function() {
        $(this).removeClass('focused');
    });
    
    // Initialize any additional features
    initializeCharts();
    initializeNotifications();
    
    /**
     * Initialize charts (placeholder for future chart implementations)
     */
    function initializeCharts() {
        // Placeholder for chart initialization
        // This would be expanded when adding chart libraries
    }
    
    /**
     * Initialize notifications (placeholder for future notification system)
     */
    function initializeNotifications() {
        // Placeholder for notification system
        // This would handle showing alerts for inactive students, etc.
    }
});

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes pulse {
        0% { opacity: 1; }
        50% { opacity: 0.7; }
        100% { opacity: 1; }
    }
    
    .loading {
        text-align: center;
        padding: 20px;
        color: #6c757d;
    }
    
    .loading::after {
        content: '';
        display: inline-block;
        width: 20px;
        height: 20px;
        margin-left: 10px;
        border: 2px solid #6c757d;
        border-radius: 50%;
        border-top-color: transparent;
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
    
    .search-section {
        margin-bottom: 20px;
    }
    
    .search-section h4 {
        margin: 0 0 10px 0;
        color: #2c3e50;
        font-size: 16px;
    }
    
    .search-item {
        padding: 10px;
        border-bottom: 1px solid #eee;
        transition: background-color 0.2s;
    }
    
    .search-item:hover {
        background-color: #f8f9fa;
    }
    
    .search-item:last-child {
        border-bottom: none;
    }
    
    .search-item-title a {
        color: #007cba;
        text-decoration: none;
        font-weight: 500;
    }
    
    .search-item-title a:hover {
        text-decoration: underline;
    }
    
    .search-item-details {
        color: #6c757d;
        font-size: 13px;
        margin: 2px 0;
    }
    
    .search-item-type {
        color: #6c757d;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .no-results {
        text-align: center;
        padding: 20px;
        color: #6c757d;
    }
    
    .error {
        background-color: #f8d7da;
        color: #721c24;
        padding: 10px;
        border-radius: 4px;
        text-align: center;
    }
    
    .focused {
        outline: 2px solid #007cba;
        outline-offset: 2px;
    }
    
    @media (max-width: 768px) {
        .mobile-enhanced table,
        .mobile-enhanced thead,
        .mobile-enhanced tbody,
        .mobile-enhanced th,
        .mobile-enhanced td,
        .mobile-enhanced tr {
            display: block;
        }
        
        .mobile-enhanced thead tr {
            position: absolute;
            top: -9999px;
            left: -9999px;
        }
        
        .mobile-enhanced tr {
            border: 1px solid #ccc;
            margin-bottom: 10px;
            padding: 10px;
            border-radius: 4px;
        }
        
        .mobile-enhanced td {
            border: none;
            border-bottom: 1px solid #eee;
            position: relative;
            padding-left: 50% !important;
        }
        
        .mobile-enhanced td:before {
            content: attr(data-label) ': ';
            position: absolute;
            left: 6px;
            width: 45%;
            padding-right: 10px;
            white-space: nowrap;
            font-weight: bold;
            color: #2c3e50;
        }
    }
`;
document.head.appendChild(style);