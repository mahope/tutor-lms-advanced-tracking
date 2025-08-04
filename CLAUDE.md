# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a WordPress plugin called "Advanced Tutor LMS Stats Dashboard" that extends Tutor LMS Pro with a frontend dashboard providing advanced statistics and detailed insights into course and user data.

**Key Features:**
- Frontend dashboard accessible via `[tutor_advanced_stats]` shortcode
- Role-based access (Administrator: full access, tutor_instructor: own courses only)
- Course overview with student counts, progression, and quiz scores
- Detailed course views showing individual student performance
- User lookup functionality with comprehensive learning analytics
- Quiz analysis with answer statistics and performance insights

## Development Commands

**Git Operations:**
- `git init` - Initialize repository
- `git add .` - Stage all changes
- `git commit -m "message"` - Commit changes
- `git status` - Check repository status
- `git log --oneline` - View commit history

**WordPress Plugin Development:**
- No build process required - PHP files are interpreted directly
- Test plugin by copying to WordPress `/wp-content/plugins/` directory
- Activate plugin through WordPress admin dashboard
- Use WordPress debug mode: `define('WP_DEBUG', true);` in wp-config.php

**Debugging Tools:**
- `debug-data-issues.php` - Comprehensive data diagnostic tool
- `debug-course-data.php` - Course-specific data debugging
- `installation-checker.php` - Environment validation tool
- `test-data-generator.php` - Creates realistic test data
- `tutor-lms-compliance-analysis.php` - Analyzes plugin against official Tutor LMS data structure
- `test-fixes.php` - Tests if bug fixes are working correctly
- Add `?action=debug_data` to any WordPress admin page to run diagnostics

**File Structure:**
```
tutor-lms-advanced-tracking/
├── tutor-lms-advanced-tracking.php (main plugin file)
├── includes/
│   ├── class-dashboard.php (dashboard functionality)
│   ├── class-course-stats.php (course statistics)
│   ├── class-user-stats.php (user statistics)
│   ├── class-advanced-analytics.php (advanced metrics and caching)
│   └── class-shortcode.php (shortcode handler)
├── assets/
│   ├── css/
│   └── js/
└── templates/
    ├── dashboard.php
    ├── course-details.php
    ├── user-details.php
    └── advanced-analytics.php
```

## Architecture

The plugin follows WordPress plugin conventions and integrates with Tutor LMS Pro:

- **Frontend Dashboard Components:**
  - Course overview (main dashboard)
  - Course detail view (student performance per course)
  - User detail view (comprehensive user analytics)
  
- **Data Sources:**
  - Tutor LMS Pro helper functions
  - Direct `wpdb` queries to relevant WordPress/Tutor LMS tables
  
- **Access Control:**
  - Shortcode requires user login
  - Role-based data filtering (instructors see only their courses)

**Plugin Structure:**
- Main plugin file with header and activation hooks
- Object-oriented approach with separate classes for functionality (5 core classes)
- WordPress coding standards and security practices
- Proper sanitization and validation of user inputs
- Database queries using WordPress $wpdb class with prepared statements
- Transient-based caching system for performance optimization

## Development Context

WordPress plugin development focusing on:
- Security: Nonce verification, input sanitization, capability checks
- Performance: Efficient database queries, caching where appropriate
- User Experience: Responsive design, clear navigation
- Maintainability: Clean code structure, proper documentation

**Target User Roles:**
- **Administrator**: Full access to all courses and users
- **tutor_instructor**: Access limited to own courses and associated students

**Core Functionality Requirements:**
- Course statistics with progression tracking
- Quiz performance analysis
- Individual student performance monitoring
- Search and filtering capabilities
- Mobile-responsive UI

## Troubleshooting Common Issues

**Plugin Shows Zero Users Despite Having Users:**
1. Run `debug-data-issues.php` to diagnose database connectivity
2. Check if Tutor LMS enrollment table exists: `wp_tutor_enrollments`
3. Verify course post type (should be 'courses' or 'course')
4. Check enrollment status filters in queries (avoid overly restrictive filters)

**Course Clicking Doesn't Work:**
1. Ensure shortcode properly handles URL parameters ($_GET['view'], $_GET['course_id'])
2. Check if course IDs are being passed correctly in dashboard template
3. Verify shortcode is registered and handling view parameter routing

**Database Issues:**
- Plugin auto-detects correct course post type ('courses' vs 'tutor_course')
- Plugin auto-detects correct lesson post type ('tutor_lesson' vs 'lessons' vs 'lesson')
- Enrollment queries now include all enrollment statuses, not just 'completed'
- Database table existence is validated by debugging tools
- Plugin follows official Tutor LMS Pro v3.6.2 data structure

**Performance Issues:**
- Plugin uses intelligent caching with automatic invalidation
- Database queries are optimized and use prepared statements
- Clear cache: `wp_cache_flush()` or delete transients starting with 'tutor_advanced_'

## Future Roadmap

Planned features for post-v1.1:
- CSV export functionality
- Interactive graphs (progression over time)
- REST API endpoints for external integration
- Automated low-activity alerts
- Student improvement recommendations