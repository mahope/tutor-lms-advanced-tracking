# Performance plan

Task: - [ ] Performance: WP_Query optimering, indexer, async cron for aggregation

Focus areas:
- WP_Query optimization (add selective fields, indexes, lazy meta fetch)
- Database indexes (on postmeta keys, tracking tables)
- Async cron for aggregation (avoid admin-req sync work)

Next steps:
- [ ] Audit top WP_Query calls and add indexes where missing
- [ ] Create async aggregation runner (wp cron event)
- [ ] Add transient caching for heavy dashboards

Notes (auto-created 2026-02-12 15:51 CET)
