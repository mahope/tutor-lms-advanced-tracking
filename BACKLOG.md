# Tutor LMS Advanced Tracking — Backlog

> **Produkt:** WordPress plugin til avanceret kursus-analytics for Tutor LMS
> **Revenue model:** LTD $99/€99, Årlig licens $15/€15
> **Target:** Tutor LMS brugere der vil have bedre insights

---

## 🎯 UI/UX Princip
**Alle features SKAL være synlige og tilgængelige for brugeren!**
- Backend-funktionalitet → tilføj også UI (knap, menu, side)
- Ingen "skjulte" features — brugeren skal kunne finde og bruge det
- Test at UI er responsiv og intuitiv


## ✅ Completed

### Core Plugin
- [x] MVP metrics definition (docs/MVP-metrics.md)
- [x] License validator scaffold (includes/class-license-validator.php)
- [x] Admin UI with Chart.js (includes/class-charts.php, class-dashboard.php)
- [x] CSV/JSON export (includes/class-export.php)
- [x] WP-CLI commands (includes/cli.php)
- [x] Funnel dashboard (includes/class-funnel-dashboard.php)
- [x] Cohort analytics (includes/class-cohort-analytics.php)
- [x] REST API endpoints (includes/class-api.php)
- [x] Compatibility docs (docs/COMPATIBILITY.md)

### Licensserver
- [x] Separat licensserver repo (/repos/tlat-license-server/)
- [x] Endpoints: /activate, /deactivate, /validate, /heartbeat
- [x] JWT-baserede licensnøgler med claims
- [x] SQLite database med WAL mode
- [x] Integrer med class-license-validator.php

---

## 🎯 UI/UX Princip
**Alle features SKAL være synlige og tilgængelige for brugeren!**
- Backend-funktionalitet → tilføj også UI (knap, menu, side)
- Ingen "skjulte" features — brugeren skal kunne finde og bruge det
- Test at UI er responsiv og intuitiv


## 🚀 Phase 1: Launch Ready (Prioritet 1)

### Licensserver Deployment
- [x] Deploy tlat-license-server til Dokploy — deployed til licenses.holstjensen.eu (Dokploy, auto-deploy fra GitHub)
- [x] Sæt op HTTPS med Let's Encrypt — certificateType: letsencrypt, verified working
- [x] Tilføj rate limiting (express-rate-limit)
- [x] Monitoring: uptime check + error alerts (healthcheck.sh + docs/monitoring.md)
- [x] Backup cron for SQLite database (backup-db.sh + retention + offsite docs)

### Plugin Polish
- [x] Admin UI: Licensindstillinger side (Settings → TLAT License)
- [x] License aktiverings-flow i admin (enter key → validate → activate)
- [x] Graceful degradation når licens udløber (14-dages grace period)
- [x] Loading states på alle dashboard charts
- [x] Responsive fixes til admin UI på tablet

### Auto-Update System
- [x] Update-server endpoint på licensserveren (/api/v1/update/check)
- [x] JSON manifest med version, changelog, download URL
- [x] Implementer update checker i plugin (pre_set_site_transient_update_plugins)
- [x] Signed zip downloads (hash verification)

### Testing
- [x] Unit tests for license validator (PHPUnit)
- [x] Integration test: aktivering → deaktivering → reaktivering
- [x] Docker-based test infrastructure (`make test-up`, `./scripts/test-wp-compat.sh`)
- [x] Test på WordPress 6.6 + PHP 8.3 — passed, all syntax clean (fixed critical `$this` bug in line 230)
- [x] Test på WordPress 6.4, 6.5 — All 35 PHP files pass syntax check (WP 6.4.3 + 6.5.5 + PHP 8.3.30)
- [ ] Test med Tutor LMS Free + Pro — **READY**: Use Docker env, install Tutor LMS manually

---

## 🎯 UI/UX Princip
**Alle features SKAL være synlige og tilgængelige for brugeren!**
- Backend-funktionalitet → tilføj også UI (knap, menu, side)
- Ingen "skjulte" features — brugeren skal kunne finde og bruge det
- Test at UI er responsiv og intuitiv


## 📈 Phase 2: Launch & Marketing (Prioritet 2)

### Sales Infrastructure
- [x] Landing page på tutor-tracking.com (Next.js eller WordPress) — `landing-page/` folder, Next.js 15 + Tailwind v4, Dockerfile ready. Deploy til Dokploy og opdater Stripe checkout links.
- [x] Stripe checkout integration (LTD + Annual options) — Payment Links created: LTD €99 + Annual €15/yr, redirects to license server success page
- [x] License delivery email (SendGrid/Resend) — Resend API integrated in license server
- [x] Customer portal: se licenser, download, support — `/portal` page med magic link auth, license dashboard, download links, support links

### Marketing Assets
- [ ] Screenshots til docs/screenshots/ (min 5 forskellige views)
- [ ] 2-min demo video (screen recording + voiceover)
- [x] Feature comparison table (TLAT vs native Tutor LMS) — added to landing page with 9 feature comparisons
- [x] Pricing page med FAQ — integrated in landing page (id="pricing" + id="faq" sections)

### Launch Outreach
- [ ] Tutor LMS Facebook group post
- [ ] r/Wordpress post
- [ ] ProductHunt launch forberedelse
- [x] 10 relevante blogs til guest post/review outreach (docs/outreach/BLOG-OUTREACH-LIST.md)

### Analytics
- [x] Plausible på landing page (analytics.holstjensen.eu)
- [x] Event tracking: demo_clicked, pricing_viewed, checkout_started
- [x] License activation tracking i admin (TLAT_Activation_Tracking class + UI)

---

## 🎯 UI/UX Princip
**Alle features SKAL være synlige og tilgængelige for brugeren!**
- Backend-funktionalitet → tilføj også UI (knap, menu, side)
- Ingen "skjulte" features — brugeren skal kunne finde og bruge det
- Test at UI er responsiv og intuitiv


## 🔧 Phase 3: Growth Features (Prioritet 3)

### Advanced Features
- [x] Webhooks til Zapier/Make (kursus fuldført, ny bruger, etc.) — admin UI at Tutor Stats → Webhooks, 7 events, HMAC signing, delivery logs
- [ ] Scheduled email reports (ugentlig/månedlig PDF til admin)
- [ ] Goal tracking (sæt mål for completion rate, alert ved afvigelse)
- [ ] Multisite network dashboard

### Integrations
- [ ] WooCommerce Subscriptions integration (renewal tracking)
- [ ] LearnDash data import (migrering fra konkurrent)
- [ ] Google Analytics 4 event push
- [ ] BigQuery export til enterprise kunder

### Premium Tier
- [ ] Definer "Pro" vs "Agency" features
- [ ] White-label option for agencies ($299 LTD)
- [ ] Priority support tier

---

## 🎯 UI/UX Princip
**Alle features SKAL være synlige og tilgængelige for brugeren!**
- Backend-funktionalitet → tilføj også UI (knap, menu, side)
- Ingen "skjulte" features — brugeren skal kunne finde og bruge det
- Test at UI er responsiv og intuitiv


## 📝 Notes

**Licensserver:** `/repos/tlat-license-server/`
**Key format:** `TLAT-XXXX-XXXX-XXXX-XXXX`
**Grace period:** 14 dage offline tolerance

<!-- Cleanup note: auto-45m duplicates removed 2026-02-13 - CSV export + CLI commands already in Completed -->
