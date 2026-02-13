# Tutor LMS Compatibility

This document describes TLAT's compatibility with Tutor LMS Free and Pro versions.

## Tested Configurations

| Tutor LMS | Version | WordPress | PHP | Status |
|-----------|---------|-----------|-----|--------|
| Free | 3.9.6 | 6.6.2 | 8.2.25 | ✅ Passed |
| Free | 3.9.x | 6.5.x | 8.2.x | ✅ Passed |
| Free | 3.9.x | 6.4.x | 8.2.x | ✅ Passed |
| Pro | 3.x | 6.6.x | 8.2.x | 📋 Manual test required |

## Quick Test

```bash
# Test with Tutor LMS Free
./tests/docker/scripts/test-with-tutor.sh free

# Test with Tutor LMS Pro (requires manual plugin installation)
./tests/docker/scripts/test-with-tutor.sh pro
```

## Tutor LMS Free Testing

Tutor LMS Free is automatically downloaded from WordPress.org during testing.

### What's Tested

- Plugin activation with Tutor LMS active
- Core class loading (TutorAdvancedTracking, Cache, TutorIntegration)
- Admin menu registration
- Dashboard shortcode rendering
- REST API endpoints

### Running the Test

```bash
cd /path/to/tutor-lms-advanced-tracking
./tests/docker/scripts/test-with-tutor.sh free
```

## Tutor LMS Pro Testing

Tutor LMS Pro requires a valid license and manual installation.

### Prerequisites

1. Active Tutor LMS Pro license from [Themeum](https://www.themeum.com/product/tutor-lms/)
2. Downloaded `tutor-pro.zip` file

### Manual Test Steps

1. Start the test environment:
   ```bash
   WP_VERSION=6.6 docker compose -f docker-compose.test.yml up -d
   ```

2. Install WordPress:
   ```bash
   docker exec tlat-test-cli wp core install \
       --url="http://localhost:8080" \
       --title="Test" \
       --admin_user="admin" \
       --admin_password="admin" \
       --admin_email="admin@test.local" \
       --skip-email --allow-root
   ```

3. Install Tutor LMS Free (required dependency):
   ```bash
   docker exec tlat-test-cli wp plugin install tutor --activate --allow-root
   ```

4. Copy tutor-pro.zip to container:
   ```bash
   docker cp tutor-pro.zip tlat-test-wp:/tmp/
   docker exec tlat-test-cli wp plugin install /tmp/tutor-pro.zip --activate --allow-root
   ```

5. Activate TLAT:
   ```bash
   docker exec tlat-test-cli wp plugin activate tutor-lms-advanced-tracking --allow-root
   ```

6. Access WordPress admin at http://localhost:8080/wp-admin (admin/admin)

7. Verify:
   - TLAT admin menu appears under Tutor LMS
   - Dashboard shortcode works
   - No PHP errors in debug.log

### Pro-Specific Features

When Tutor LMS Pro is active, TLAT automatically detects:
- Additional course analytics data
- Certificate completions
- Advanced quiz types
- Subscription/membership data

## Database Tables

TLAT reads from these Tutor LMS tables:
- `wp_tutor_enrollments` - Course enrollments
- `wp_tutor_quiz_attempts` - Quiz attempts and scores
- `wp_tutor_quiz_questions` - Quiz questions
- `wp_tutor_quiz_question_answers` - Quiz answers
- `wp_comments` (type: `course_review`) - Course reviews

## Minimum Requirements

- **Tutor LMS**: 2.0.0 or higher
- **WordPress**: 5.0 or higher
- **PHP**: 7.4 or higher
- **MySQL**: 5.6 or higher

## Known Issues

### Tutor LMS 2.x Migration

If upgrading from Tutor LMS 1.x to 2.x, database tables may have different structures.
TLAT uses `TutorAdvancedTracking_TutorIntegration` to handle table aliasing automatically.

### Cache Invalidation

TLAT caches expensive queries. Cache is automatically cleared when:
- Students enroll in courses
- Quiz attempts are completed
- Course data is updated

To manually clear cache:
```php
TutorAdvancedTracking_Cache::clear_all_cache();
```

## Troubleshooting

### "Tutor LMS required" Error

TLAT requires Tutor LMS to be active. Install and activate Tutor LMS first.

### Missing Data

If course/user data isn't appearing:
1. Ensure Tutor LMS has actual course data
2. Check that users are enrolled in courses
3. Clear the TLAT cache via WP-CLI: `wp tlat cache clear`

### Debug Mode

Enable WordPress debug logging:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Check `/wp-content/debug.log` for any TLAT-related errors.
