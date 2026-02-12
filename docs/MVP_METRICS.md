# MVP Metrics & Data Sources (draft)

Metrics (MVP):
- Lesson views (unique per user)
- Course completion (per user)
- Time-on-lesson (heartbeat with debounce)
- Quiz attempt details (score, duration)

Data sources:
- Tutor LMS hooks: lesson render, course complete, quiz attempt
- WP REST endpoints for admin charts and CSV export
- Cron jobs to aggregate raw events into summaries

Notes: prioritize performance and privacy; batch writes where possible.
