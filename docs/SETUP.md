# Setup & Configuration

- Access: Administrators see all data; instructors limited to own courses.
- Aggregation: Daily aggregation via WP-Cron; ensure cron is running (server cron or traffic).
- Privacy: Telemetry is opt-in (Settings → Advanced Tracking). Crash logs can be disabled.
- Export: CSV export available from the dashboard.

Recommended:
- Enable object cache (Redis/Memcached) for faster queries.
- Add DB indexes per docs/performance/ if dataset is large.
