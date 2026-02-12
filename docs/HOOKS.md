# Hooks & Filters Reference

These are key integration points used/exposed by the plugin.

## Tutor LMS / WordPress Actions consumed
- `tutor_after_enrolled` — triggers cache/agg updates after enrollment
- `tutor_quiz_finished` — updates stats after a quiz attempt
- `save_post` — clears/updates when course content changes

## AJAX Endpoints
- `wp_ajax_tlat_search` (auth required) — internal search data provider

## Filters (extensibility)
- `tlat_allowed_roles` (array $roles) — extend who can access the dashboard
- `tlat_export_columns` (array $cols) — adjust CSV export columns
- `tlat_privacy_mask_email` (bool $mask, WP_User $user) — override email masking logic

Example:
```php
add_filter('tlat_allowed_roles', function($roles){
  $roles[] = 'shop_manager';
  return $roles;
});
```
