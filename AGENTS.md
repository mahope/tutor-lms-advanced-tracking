# Repository Guidelines

## Project Structure & Module Organization
- `tutor-lms-advanced-tracking.php` bootstraps the plugin, registers hooks, and enqueues assets.
- `includes/` holds feature classes (dashboard, analytics, API, cache, exports); add new services beside related peers.
- `templates/` provides shortcode and admin views—keep them presentation-only and push data work into `includes/`.
- `assets/css` and `assets/js` store enqueued UI assets; align filenames with the screen they serve.
- `debug-*.php` and `test-*.php` support staging-only diagnostics; strip them before production handoff.

## Build, Test, and Development Commands
- `wp plugin activate tutor-lms-advanced-tracking` after copying the folder into `wp-content/plugins/` to enable locally.
- `php -l includes` runs a fast syntax sweep across PHP sources.
- `phpcs --standard=WordPress tutor-lms-advanced-tracking.php includes/ templates/` enforces formatting (requires PHPCS).
- `wp eval-file installation-checker.php` verifies Tutor LMS prerequisites on a target site.

## Coding Style & Naming Conventions
- Follow WordPress PHP standards: tab indentation, snake_case functions, StudlyCaps classes, guarded `defined( 'ABSPATH' )` checks.
- Escape and sanitize consistently (`esc_html__`, `sanitize_text_field`, `wp_verify_nonce`) before output or persistence.
- JavaScript in `assets/js` sticks to ES5-compatible syntax with 2-space indent and camelCase identifiers that mirror PHP hook names.
- Name new files as `class-*.php` for services and `admin-*.php` for views; register assets inside the main plugin bootstrap.

## Testing Guidelines
- Provision WordPress 5.0+ with Tutor LMS Pro and follow the full walkthrough in `TESTING.md`.
- Seed realistic enrollments via `test-data-generator.php`, then exercise dashboards, detail views, and exports.
- Validate caching by loading a report twice, triggering an enrollment or quiz completion, and ensuring fresh data appears.
- Record environment details and pass/fail notes so reviewers can reproduce results.

## Commit & Pull Request Guidelines
- Keep commits imperative and under ~70 characters (`Add enrollment cache guard`, `Document dashboard shortcode`).
- Reference issues or tickets in the body, and squash WIP commits ahead of review.
- PRs should outline scope, manual tests, and UI screenshots; highlight security or performance impacts when relevant.
- Call out deployment steps (WP-CLI commands, cron changes) in the PR description to aid site operators.

## Security & Configuration Tips
- Preserve capability checks, nonces, and `$wpdb->prepare` whenever you touch access control or SQL.
- Limit debug utilities to secured staging environments and scrub sample credentials before packaging builds.
- Note cache TTL or key changes in `includes/class-cache.php` so analytics expectations stay aligned.
