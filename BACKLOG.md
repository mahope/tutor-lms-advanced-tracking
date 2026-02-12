# Tutor LMS Advanced Tracking — Backlog (v1)

- [x] - [ ] Produktdefinition: afgræns MVP-metrics (lesson views, completion, time-on-lesson, quiz detail) og datakilder (Tutor LMS hooks) — completed 2026-02-12 14:15
- [x] Licensmodel — completed 2026-02-12 14:45: årlig licens + domain checks (LemonSqueezy/WOOREST), simple license validator
- [x] (2026-02-12 15:22 CET) Admin-UI: oversigtsside med grafer (Chart.js) + eksport (CSV)
- [x] Data-layer: events/DB-schema (custom tables vs postmeta), migrator, uninstaller — completed 2026-02-12 15:40 CET: scaffolds added (tables: tlat_events, tlat_agg_daily), dbDelta migrator, uninstall + WP-CLI stub
- [x] 2026-02-12T15:19:04Z Performance: WP_Query optimering, indexer, async cron for aggregation
- [x] Compatibility: Tutor LMS v aktuelt + WP 6.x + PHP 8.2+, WP-CLI kommandoer — completed 2026-02-12 16:37 CET
- [x] Telemetry (opt-in): anonym brugstælling, crash logs (WP options + remote endpoint) — completed 2026-02-12 16:46
- [x] Docs/README: install, setup, screenshots, hooks/filters reference — completed 2026-02-12 17:07 CET
- [x] Demo-video (2 min): key value prop + UI-walkthrough (script outline) — done: 2026-02-12 17:30
- [x] Launch-plan: versioning, changelog, release checklist, support-templates (done: 2026-02-12 17:50)

## New Features (2026-02-12)
- [ ] Kursus-funnel dashboard (enroll → start → complete) med drop-off analyse
- [ ] Segmenter pr. brugergruppe (role, kursus, aktivitet) + filtrerbare grafer
- [ ] Real-time session-tracking (heartbeat) med "aktive elever nu"
- [ ] Alerting: e-mail/Slack ved fald i completion >X% uge/uge
- [ ] Eksport til CSV/JSON pr. kursus/lektioneniveau (inkl. quiz metrics)
- [ ] "Learner journey" visning (timeline for elevens haendelser)
- [ ] Multisite support + netvaerksoversigter
- [ ] REST API endpoints til dataudtraek (JWT beskyttet)
- [ ] Dataintegritet: reprocesserings-queue og "rebuild aggregates"
- [ ] Indbygget datamaskering/GDPR (hash af PII, opt-in/-out UI)
- [ ] Cohort-analyse (uge/maaned) med retention-kurver
- [ ] Instruktor-dashboard (kursusperformance pr. underviser)
- [ ] Egen KPI-builder (vaelg metrics + filtre → gemt rapport)
- [ ] Webhooks (event-stream til eksterne systemer)
- [ ] Ugentlig e-mailrapport (PDF/CSV bilag) pr. kursus
- [ ] BigQuery/CSV bulk-eksport (schedule + on-demand)
- [ ] Rollebaserede rettigheder (admin/instructor/viewer)
- [ ] Rapport-planlaegning (send til mail/Slack paa tid)
- [ ] Kursus-sammenligning (A/B af curriculums)
- [ ] Data quality monitor (manglende events, clock drift)
- [ ] Implementer Plausible analytics (self-hosted: analytics.holstjensen.eu)
