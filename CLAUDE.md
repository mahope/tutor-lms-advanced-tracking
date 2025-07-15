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

**File Structure:**
```
tutor-lms-advanced-tracking/
├── tutor-lms-advanced-tracking.php (main plugin file)
├── includes/
│   ├── class-dashboard.php (dashboard functionality)
│   ├── class-course-stats.php (course statistics)
│   ├── class-user-stats.php (user statistics)
│   └── class-shortcode.php (shortcode handler)
├── assets/
│   ├── css/
│   └── js/
└── templates/
    ├── dashboard.php
    ├── course-details.php
    └── user-details.php
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
- Object-oriented approach with separate classes for functionality
- WordPress coding standards and security practices
- Proper sanitization and validation of user inputs
- Database queries using WordPress $wpdb class

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

## Future Roadmap

Planned features for post-v1.1:
- CSV export functionality
- Interactive graphs (progression over time)
- REST API endpoints for external integration
- Automated low-activity alerts
- Student improvement recommendations