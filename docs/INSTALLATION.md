# Installation

1) Ensure requirements: WordPress 6.x, PHP 8.2+, Tutor LMS (tested with current version)
2) Upload folder to wp-content/plugins/tutor-lms-advanced-tracking
3) Activate plugin in WP Admin → Plugins
4) On first run the plugin creates/update its DB tables (dbDelta). Check Tools → Site Health for notices.
5) (Optional) WP-CLI: `wp tlat migrate` to force DB migration.

Troubleshooting:
- If activation fails, check `wp-content/debug.log` and plugin Compatibility doc.
- Conflicts: Query Monitor and heavy analytics plugins can interfere with timings; disable to compare.
