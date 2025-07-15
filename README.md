# Advanced Tutor LMS Stats Dashboard

A secure WordPress plugin that extends Tutor LMS Pro with advanced statistics and detailed insights into course and user data.

## Features

- **Role-based Access Control**: Administrators see all data, instructors only see their courses
- **Course Overview Dashboard**: Student count, progression, and quiz scores
- **Detailed Course Analytics**: Individual student performance and quiz analysis
- **User Profile Analytics**: Comprehensive student progress and problem areas
- **Real-time Search**: AJAX-powered search for courses and users
- **Mobile Responsive**: Works on all devices with adaptive design
- **Performance Optimized**: Caching and optimized database queries

## Security Features

- ✅ **SQL Injection Protection**: All queries use prepared statements
- ✅ **XSS Prevention**: Comprehensive output escaping
- ✅ **CSRF Protection**: Nonce verification for all AJAX requests
- ✅ **Access Control**: Role-based permissions and capability checks
- ✅ **Input Validation**: Comprehensive sanitization and validation
- ✅ **Rate Limiting**: Search request throttling
- ✅ **Email Masking**: Privacy protection for non-admin users
- ✅ **Direct Access Prevention**: All PHP files protected

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- Tutor LMS Pro plugin installed and activated
- MySQL 5.6 or higher

## Installation

1. Download the plugin files
2. Upload to your WordPress `/wp-content/plugins/tutor-lms-advanced-tracking/` directory
3. Activate the plugin through the WordPress admin dashboard
4. Ensure Tutor LMS Pro is installed and activated

## Usage

1. Add the shortcode `[tutor_advanced_stats]` to any page or post
2. Users must be logged in to access the dashboard
3. Administrators can view all courses and users
4. Instructors can only view their own courses and enrolled students

## Performance Optimization

- **Caching**: Automatic caching of expensive database queries (5-10 minutes)
- **Cache Invalidation**: Smart cache clearing on data changes
- **Optimized Queries**: Combined queries to reduce database load
- **Rate Limiting**: Prevents abuse of search functionality

## Security Considerations

### For Administrators

- Regularly update the plugin and WordPress core
- Monitor user access and permissions
- Review search logs for suspicious activity
- Backup database before major updates

### For Developers

- All user inputs are sanitized and validated
- Database queries use prepared statements
- Output is properly escaped for XSS prevention
- CSRF tokens are verified for all AJAX requests
- Rate limiting prevents abuse

## Database Tables Used

The plugin uses existing WordPress and Tutor LMS tables:
- `wp_posts` - Course and quiz data
- `wp_users` - User information
- `wp_tutor_enrollments` - Course enrollments
- `wp_tutor_quiz_attempts` - Quiz attempts and scores
- `wp_tutor_quiz_questions` - Quiz questions
- `wp_tutor_quiz_question_answers` - Quiz answers

## Hooks and Filters

### Actions
- `tutor_after_enrolled` - Clears course cache on enrollment
- `tutor_quiz_finished` - Clears course cache on quiz completion
- `save_post` - Clears course cache on course updates

### AJAX Endpoints
- `wp_ajax_tutor_advanced_search` - Handles search requests (logged-in users only)

## Caching

The plugin implements intelligent caching:
- Course statistics: 10 minutes
- Dashboard data: 5 minutes
- Search rate limiting: 1 minute (10 requests max)

Cache is automatically cleared when:
- Students enroll in courses
- Quiz attempts are completed
- Course data is updated

## Troubleshooting

### Common Issues

1. **"Tutor LMS required" error**
   - Ensure Tutor LMS Pro is installed and activated

2. **Permission denied errors**
   - Check user roles and capabilities
   - Verify user is logged in

3. **Search not working**
   - Check for JavaScript errors
   - Verify AJAX endpoint is accessible
   - Check rate limiting (max 10 requests per minute)

4. **Slow performance**
   - Check database query optimization
   - Verify caching is working
   - Review server performance

### Debug Mode

To enable debug mode, add to wp-config.php:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

### Error Logs

Check WordPress error logs for:
- Database query errors
- Permission issues
- Cache problems
- AJAX request failures

## Development

### File Structure
```
tutor-lms-advanced-tracking/
├── tutor-lms-advanced-tracking.php (main plugin file)
├── includes/
│   ├── class-shortcode.php (shortcode handler)
│   ├── class-dashboard.php (dashboard functionality)
│   ├── class-course-stats.php (course statistics)
│   └── class-user-stats.php (user statistics)
├── assets/
│   ├── css/dashboard.css (styles)
│   └── js/dashboard.js (JavaScript)
├── templates/
│   ├── dashboard.php (main dashboard)
│   ├── course-details.php (course view)
│   └── user-details.php (user view)
├── CLAUDE.md (development guide)
└── README.md (this file)
```

### Development Commands

```bash
# Initialize development environment
git clone <repository-url>
cd tutor-lms-advanced-tracking

# WordPress plugin development
# No build process required - PHP files are interpreted directly

# Testing
# Copy to WordPress /wp-content/plugins/ directory
# Activate through WordPress admin dashboard
```

## Support

For issues and feature requests:
1. Check the troubleshooting section
2. Review WordPress and Tutor LMS documentation
3. Check plugin logs for error messages
4. Test with default WordPress theme

## License

GPL v2 or later - https://www.gnu.org/licenses/gpl-2.0.html

## Changelog

### Version 1.0.0
- Initial release
- Basic dashboard functionality
- Course and user statistics
- Search functionality
- Security hardening
- Performance optimizations

## Contributing

1. Follow WordPress coding standards
2. Ensure all code is properly sanitized and escaped
3. Add comprehensive error handling
4. Include security considerations
5. Test thoroughly before deployment

## Security Disclosure

If you discover a security vulnerability, please contact the maintainer directly rather than creating a public issue.