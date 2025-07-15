# Testing Guide - Advanced Tutor LMS Stats Dashboard

This guide will help you test the Advanced Tutor LMS Stats Dashboard plugin in a WordPress environment.

## 📋 Prerequisites

Before testing, ensure you have:
- WordPress 5.0 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher
- Tutor LMS Pro plugin (required dependency)
- Basic WordPress admin access

## 🚀 Step 1: WordPress Installation & Setup

### Option A: Local Development (Recommended)
1. **Install Local WordPress Environment**:
   ```bash
   # Using Local by Flywheel, XAMPP, or WAMP
   # Or use WordPress CLI
   wp core download
   wp config create --dbname=test_db --dbuser=root --dbpass=password
   wp core install --url=localhost --title="Test Site" --admin_user=admin --admin_password=password --admin_email=test@example.com
   ```

2. **Navigate to your WordPress directory**:
   ```bash
   cd /path/to/your/wordpress/wp-content/plugins/
   ```

### Option B: Existing WordPress Site
1. Access your WordPress site via FTP/cPanel
2. Navigate to `/wp-content/plugins/` directory

## 📦 Step 2: Plugin Installation

1. **Copy Plugin Files**:
   ```bash
   # Copy the entire plugin directory to WordPress plugins folder
   cp -r /path/to/tutor-lms-advanced-tracking /path/to/wordpress/wp-content/plugins/
   ```

2. **Set Proper Permissions**:
   ```bash
   chmod -R 755 /path/to/wordpress/wp-content/plugins/tutor-lms-advanced-tracking/
   ```

3. **Install Tutor LMS Pro** (Required):
   - Download Tutor LMS Pro from the official website
   - Install and activate it through WordPress admin
   - Complete the Tutor LMS setup wizard

## 🔧 Step 3: Plugin Activation

1. **Login to WordPress Admin**:
   - Go to `http://your-site.com/wp-admin/`
   - Login with admin credentials

2. **Activate the Plugin**:
   - Go to `Plugins > Installed Plugins`
   - Find "Advanced Tutor LMS Stats Dashboard"
   - Click "Activate"

3. **Verify Installation**:
   - Check for any error messages
   - Ensure no conflicts with other plugins

## 📊 Step 4: Create Test Data

### Create Test Courses

1. **Create a Test Course**:
   - Go to `Tutor LMS > Courses`
   - Click "Add New Course"
   - Title: "Test Course Analytics"
   - Add course description and content
   - Publish the course

2. **Add Course Content**:
   - Add 3-5 lessons to the course
   - Add 2-3 quizzes with multiple questions
   - Set quiz passing grades (e.g., 70%)

### Create Test Users

1. **Create Test Students**:
   ```php
   // You can add this to your WordPress functions.php temporarily
   function create_test_students() {
       for ($i = 1; $i <= 10; $i++) {
           $user_id = wp_insert_user([
               'user_login' => 'student' . $i,
               'user_pass' => 'password123',
               'user_email' => 'student' . $i . '@test.com',
               'display_name' => 'Test Student ' . $i,
               'role' => 'subscriber'
           ]);
           
           // Assign tutor student role if available
           if (function_exists('tutor_utils')) {
               tutor_utils()->add_user_role($user_id, 'tutor_student');
           }
       }
   }
   // Call this function once, then remove it
   ```

2. **Create Test Instructor**:
   - Go to `Users > Add New`
   - Username: `test_instructor`
   - Email: `instructor@test.com`
   - Role: `Tutor Instructor`

### Generate Test Enrollments and Activity

1. **Enroll Students in Course**:
   - Go to course page as admin
   - Use Tutor LMS enrollment features
   - Or use database queries to simulate enrollments

2. **Simulate Quiz Attempts** (Manual Testing):
   - Login as different test students
   - Take quizzes with varying scores
   - Some students should fail, others pass
   - Create variety in performance data

## 🧪 Step 5: Test the Plugin Features

### Basic Dashboard Testing

1. **Create a Test Page**:
   - Go to `Pages > Add New`
   - Title: "Course Analytics Dashboard"
   - Content: `[tutor_advanced_stats]`
   - Publish the page

2. **View Dashboard**:
   - Visit the page as admin
   - Should see course overview with statistics
   - Verify all courses are listed
   - Check student counts and averages

### Course Details Testing

1. **Click "View Details"** on any course
2. **Verify Course Details Page Shows**:
   - Course title and instructor
   - Student performance table
   - Quiz performance data
   - "View Advanced Analytics" button

### Advanced Analytics Testing

1. **Click "View Advanced Analytics"**
2. **Test Each Analytics Section**:
   - **Completion Funnel**: Should show enrollment → completion stages
   - **Engagement Metrics**: Should display engagement score and level
   - **Time Analytics**: Should show activity patterns (may be limited with test data)
   - **Difficulty Analysis**: Should show quiz difficulty levels
   - **Predictive Analytics**: Should identify at-risk students

### User Details Testing

1. **Click "View Details"** on any student
2. **Verify User Details Page Shows**:
   - Student information
   - Course progress
   - Quiz performance
   - Problem areas (if any)

### Search Functionality Testing

1. **Test Search Feature**:
   - Search for course names
   - Search for student names
   - Verify search results are relevant
   - Test rate limiting (try 15+ searches quickly)

## 🔍 Step 6: Test Different User Roles

### As Administrator
- Should see all courses and students
- Full access to all analytics
- Can view all dashboard features

### As Instructor
- Should only see own courses
- Limited to enrolled students in their courses
- Analytics limited to their course data

### As Student/Subscriber
- Should see "insufficient permissions" message
- Cannot access dashboard without proper role

## 🐛 Step 7: Testing & Debugging

### Enable WordPress Debug Mode

Add to `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

### Check Error Logs

1. **WordPress Error Log**:
   ```bash
   tail -f /path/to/wordpress/wp-content/debug.log
   ```

2. **Server Error Log**:
   ```bash
   # Check your server's error log location
   tail -f /var/log/apache2/error.log
   # or
   tail -f /var/log/nginx/error.log
   ```

### Common Issues & Solutions

#### Plugin Activation Errors
- **Error**: "Tutor LMS required"
- **Solution**: Install and activate Tutor LMS Pro first

#### Database Connection Issues
- **Error**: Database query errors
- **Solution**: Check WordPress database credentials

#### Permission Errors
- **Error**: "You do not have permission"
- **Solution**: Ensure user has correct role (Administrator or Tutor Instructor)

#### Empty Analytics Data
- **Error**: No data showing in analytics
- **Solution**: 
  - Ensure test data exists
  - Check database table names match Tutor LMS version
  - Verify plugin is querying correct tables

## 📝 Step 8: Test Specific Features

### Test Caching System
1. Load analytics page (should be slow first time)
2. Reload page (should be faster - cached)
3. Add new enrollment/quiz attempt
4. Check if cache invalidates properly

### Test Security Features
1. **CSRF Protection**:
   - Try search without proper nonce
   - Should show "Security check failed"

2. **Rate Limiting**:
   - Perform 15+ searches quickly
   - Should show "Too many requests" message

3. **Email Masking**:
   - Login as instructor (not admin)
   - Check if student emails are masked

### Test Mobile Responsiveness
1. Open dashboard on mobile device
2. Verify tables adapt to mobile layout
3. Check navigation works on touch devices

## 📊 Step 9: Performance Testing

### Test with Large Dataset
1. Create 50+ test students
2. Generate 100+ quiz attempts
3. Monitor page load times
4. Check database query performance

### Monitor Resource Usage
```bash
# Monitor memory usage
ps aux | grep php
# Monitor database queries
mysql -u root -p -e "SHOW PROCESSLIST;"
```

## 🎯 Step 10: Final Verification

### Checklist for Complete Testing

- [ ] Plugin activates without errors
- [ ] Dashboard loads with course data
- [ ] Course details show student performance
- [ ] Advanced analytics display all sections
- [ ] User details show comprehensive data
- [ ] Search functionality works
- [ ] Different user roles have appropriate access
- [ ] Security features work (CSRF, rate limiting)
- [ ] Mobile responsive design works
- [ ] Performance is acceptable
- [ ] No JavaScript errors in console
- [ ] No PHP errors in logs

## 🔧 Development Testing

### For Plugin Development
```bash
# Run PHP syntax check
php -l includes/class-advanced-analytics.php

# Check WordPress coding standards (if you have PHPCS)
phpcs --standard=WordPress includes/

# Test database queries
mysql -u root -p your_db_name < test_queries.sql
```

### JavaScript Testing
```javascript
// Open browser console and test
console.log('Testing search functionality...');
// Test search features
// Check for errors
```

## 📋 Test Results Documentation

Create a simple test results document:

```markdown
# Test Results - [Date]

## Environment
- WordPress Version: X.X.X
- PHP Version: X.X.X
- Tutor LMS Version: X.X.X

## Test Results
- [ ] Plugin Installation: PASS/FAIL
- [ ] Basic Dashboard: PASS/FAIL
- [ ] Course Analytics: PASS/FAIL
- [ ] User Analytics: PASS/FAIL
- [ ] Search Functionality: PASS/FAIL
- [ ] Security Features: PASS/FAIL
- [ ] Mobile Responsiveness: PASS/FAIL

## Issues Found
1. Issue description
2. Steps to reproduce
3. Expected vs actual behavior

## Performance Notes
- Page load time: X seconds
- Memory usage: X MB
- Database queries: X queries
```

## 🚀 Next Steps

After successful testing:
1. **Production Deployment**: Move to live site if tests pass
2. **User Training**: Train instructors on using analytics
3. **Monitoring**: Set up monitoring for production use
4. **Backup**: Ensure regular backups are in place
5. **Updates**: Plan for future plugin updates

## 📞 Support

If you encounter issues during testing:
1. Check the troubleshooting section in README.md
2. Review WordPress error logs
3. Verify Tutor LMS Pro is properly installed
4. Check database table structure matches expectations

Remember: This is a complex analytics plugin that requires proper test data to demonstrate its full capabilities. The more realistic your test data, the better you can evaluate the plugin's features!