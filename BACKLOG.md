# Tutor LMS Advanced Tracking — Backlog

## Completed
- [x] MVP metrics definition (docs/MVP-metrics.md)
- [x] License validator scaffold (includes/class-license-validator.php)
- [x] Admin UI with Chart.js (includes/class-charts.php, class-dashboard.php)
- [x] CSV/JSON export (includes/class-export.php)
- [x] WP-CLI commands (includes/cli.php)
- [x] Funnel dashboard (includes/class-funnel-dashboard.php)
- [x] Cohort analytics (includes/class-cohort-analytics.php)
- [x] REST API endpoints (includes/class-api.php)
- [x] Compatibility docs (docs/COMPATIBILITY.md)

## In Progress / To Do

### Licensserver (Prioritet 1)
- [x] Opret separat licensserver repo (Node.js/Express + SQLite) → /repos/tlat-license-server/
- [x] Implementer endpoints: /activate, /deactivate, /validate, /heartbeat
- [x] JWT-baserede licensnøgler med claims (plan, domain, expiry)
- [x] Integrer med class-license-validator.php (API kald til licensserver)
- [x] Admin UI: Licensindstillinger side i WP (Settings → TLAT License)

### Auto-Update (Prioritet 2)
- [ ] Opret update-server endpoint (JSON manifest + zip URL)
- [ ] Implementer update checker i plugin (hook til wp_update_plugins)
- [ ] Version bump og changelog workflow

### UI/UX Polish (Prioritet 3)
- [ ] Tilføj loading states til dashboard charts
- [ ] Responsive design fixes til admin UI
- [ ] Export modal med format-valg (CSV/JSON/PDF)
- [ ] Inline help tooltips på dashboard

### Dokumentation & Marketing
- [ ] Screenshots til docs/screenshots/ (admin UI, charts, export)
- [ ] Video script til 2-min demo (docs/demo-script.md exists)
- [ ] Landing page tekst + feature list
- [ ] Pricing page (LTD $99, Årlig $15)

### Avancerede Features (Later)
- [ ] Webhooks til eksterne systemer (Zapier/Make)
- [ ] Multisite network dashboard
- [ ] Scheduled email reports (ugentlig PDF)
- [ ] BigQuery export integration
