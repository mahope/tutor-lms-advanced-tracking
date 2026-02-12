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
- [ ] Deploy tlat-license-server til Dokploy (license.tutor-tracking.com) — **READY**: Dockerfile + docker-compose + deployment guide klar i repo. Kræver Dokploy UI login for at fuldføre.
- [ ] Sæt op HTTPS med Let's Encrypt
- [x] Tilføj rate limiting (express-rate-limit)
- [ ] Monitoring: uptime check + error alerts
- [ ] Backup cron for SQLite database

### Plugin Polish
- [ ] Admin UI: Licensindstillinger side (Settings → TLAT License)
- [ ] License aktiverings-flow i admin (enter key → validate → activate)
- [ ] Graceful degradation når licens udløber (14-dages grace period)
- [ ] Loading states på alle dashboard charts
- [ ] Responsive fixes til admin UI på tablet

### Auto-Update System
- [ ] Update-server endpoint på licensserveren (/api/v1/update/check)
- [ ] JSON manifest med version, changelog, download URL
- [ ] Implementer update checker i plugin (pre_set_site_transient_update_plugins)
- [ ] Signed zip downloads (hash verification)

### Testing
- [ ] Unit tests for license validator (PHPUnit)
- [ ] Integration test: aktivering → deaktivering → reaktivering
- [ ] Test på WordPress 6.4, 6.5, 6.6
- [ ] Test med Tutor LMS Free + Pro

---

## 🎯 UI/UX Princip
**Alle features SKAL være synlige og tilgængelige for brugeren!**
- Backend-funktionalitet → tilføj også UI (knap, menu, side)
- Ingen "skjulte" features — brugeren skal kunne finde og bruge det
- Test at UI er responsiv og intuitiv


## 📈 Phase 2: Launch & Marketing (Prioritet 2)

### Sales Infrastructure
- [ ] Landing page på tutor-tracking.com (Next.js eller WordPress)
- [ ] Stripe checkout integration (LTD + Annual options)
- [ ] License delivery email (SendGrid/Resend)
- [ ] Customer portal: se licenser, download, support

### Marketing Assets
- [ ] Screenshots til docs/screenshots/ (min 5 forskellige views)
- [ ] 2-min demo video (screen recording + voiceover)
- [ ] Feature comparison table (Free vs Pro hvis relevant)
- [ ] Pricing page med FAQ

### Launch Outreach
- [ ] Tutor LMS Facebook group post
- [ ] r/Wordpress post
- [ ] ProductHunt launch forberedelse
- [ ] 10 relevante blogs til guest post/review outreach

### Analytics
- [ ] Plausible på landing page (analytics.holstjensen.eu)
- [ ] Event tracking: demo_clicked, pricing_viewed, checkout_started
- [ ] License activation tracking i admin

---

## 🎯 UI/UX Princip
**Alle features SKAL være synlige og tilgængelige for brugeren!**
- Backend-funktionalitet → tilføj også UI (knap, menu, side)
- Ingen "skjulte" features — brugeren skal kunne finde og bruge det
- Test at UI er responsiv og intuitiv


## 🔧 Phase 3: Growth Features (Prioritet 3)

### Advanced Features
- [ ] Webhooks til Zapier/Make (kursus fuldført, ny bruger, etc.)
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
