# MVP Metrics and Data Sources

This document outlines the initial MVP metric definitions and Tutor LMS hooks/data sources to capture them.

## Metrics
- Lesson views (unique per user per lesson)
- Lesson completions (timestamped)
- Time-on-lesson (start/stop events, idle threshold)
- Quiz details (attempts, score, duration)

## Tutor LMS Hooks / Data
- Lesson progression: `tutor_lesson_completed`, `tutor/course/lesson/before/complete`
- Quiz: `tutor_quiz/submitted`, `tutor_quiz/attempt_ended`
- Engagement: AJAX endpoints and `template_redirect` for view events

## Storage
- Custom tables prefixed `wp_tlat_` for events and aggregates
- Minimal postmeta for cross-links

## Privacy
- Respect WP consent; provide opt-out setting

## Next Steps
- Draft DB schema in docs/db-schema.md
- Spike event capture in plugin bootstrap
