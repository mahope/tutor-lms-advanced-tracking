# Tutor LMS Advanced Tracking — Backlog

> **Produkt:** WordPress plugin til avanceret kursus-analytics for Tutor LMS
> **Revenue model:** LTD €99, Årlig licens €15
> **Target:** Tutor LMS brugere der vil have bedre insights

---

## 🎯 UI/UX Princip
**Alle features SKAL være synlige og tilgængelige for brugeren!**
- Backend-funktionalitet → tilføj også UI (knap, menu, side)
- Ingen "skjulte" features — brugeren skal kunne finde og bruge det
- Test at UI er responsiv og intuitiv

---

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

### Phase 1: Launch Ready
- [x] Deploy licensserver til Dokploy (licenses.holstjensen.eu)
- [x] HTTPS med Let's Encrypt
- [x] Rate limiting + monitoring + backup
- [x] Admin UI licensindstillinger
- [x] 14-dages grace period
- [x] Loading states på alle charts
- [x] Auto-update system
- [x] PHPUnit tests + Docker test infrastructure
- [x] WordPress 6.4/6.5/6.6 kompatibilitet
- [x] Tutor LMS Free 3.9.6 kompatibilitet

### Phase 2: Launch & Marketing
- [x] Landing page (Next.js, Dockerfile ready)
- [x] Stripe checkout (LTD €99 + Annual €15)
- [x] License delivery email (Resend)
- [x] Customer portal med magic link
- [x] Feature comparison table
- [x] Plausible analytics + event tracking
- [x] License activation tracking
- [x] Webhooks til Zapier/Make

---

## 🚀 Phase 2.5: Analytics Deep Dive (Prioritet 1)

> **Mål:** Gør pluginnet til den mest dybdegående analytics-løsning for Tutor LMS

### Student Progress Analytics
- [x] Individual student progress dashboard (`Tutor Stats → Students → [Student]`)
- [x] Time spent tracking per lesson og kursus (track start/end timestamps)
- [x] Engagement score beregning (baseret på aktivitet, quiz scores, completion)
- [x] At-risk student identificering (ingen aktivitet i X dage, lav progression)
- [x] Student timeline view (kronologisk aktivitetslog per elev)
- [x] Student sammenligning (sammenlign flere elevers progression)

### Quiz & Assessment Analytics
- [x] Per-question analytics (hvilke spørgsmål er sværest)
- [x] Answer pattern heatmap (hvad svarer folk forkert)
- [x] Time spent per question tracking
- [x] Retry/forsøg tracking med score-forbedring
- [x] Score distribution histogram per quiz
- [x] Quiz difficulty scoring (automatisk baseret på fail rates)
- [x] Question effectiveness metrics (discrimination index)

### Engagement Analytics
- [x] Video watch completion rates (kræver video tracking)
- [x] Assignment submission timeline
- [x] Login frequency og session-længde
- [x] Device/browser breakdown chart
- [x] Peak activity hours heatmap
- [ ] Content engagement score per lesson
- [ ] Lesson drop-off points (hvor stopper folk?)

### Real-time Dashboard
- [ ] Live student activity feed (hvem gør hvad nu)
- [ ] Current active users count + chart
- [ ] Real-time enrollment notifications
- [ ] Live completion alerts
- [ ] WebSocket eller Server-Sent Events integration

### Gamification & Motivation
- [ ] Student leaderboard per kursus (opt-in, anonymiseret option)
- [ ] Achievement badges system (first quiz, perfect score, speed demon)
- [ ] Progress milestones med visuel celebration
- [ ] Weekly progress email til studerende (digest af fremskridt)
- [ ] Instructor "kudos" system (anerkend studerende fra admin)

### Course Health Dashboard
- [ ] Course health score widget (samlet vurdering 0-100)
- [ ] Bottleneck detection (hvilke lessons skaber drop-off)
- [ ] Content freshness indicator (hvornår sidst opdateret)
- [ ] Suggested improvements baseret på data
- [ ] A/B test tracking for course content

### Admin Feature Control
- [ ] Feature toggle dashboard (Tutor Stats → Settings → Features)
- [ ] Slå individuelle analytics-moduler til/fra (cohort, funnel, quiz analytics, etc.)
- [ ] Per-rolle feature adgang (admin kan alt, instructor kun egne kurser)
- [ ] Performance mode (deaktiver tunge features på langsom hosting)
- [ ] Gem feature-præferencer i wp_options med UI reset-knap

### Custom Reports
- [ ] Custom date range selector på alle dashboards
- [ ] PDF report generation med branding
- [ ] Scheduled email reports (daglig/ugentlig/månedlig)
- [ ] Report templates (executive summary, detailed, instructor)
- [ ] Excel export med formatering
- [ ] Report scheduling UI i admin

---

## 📊 Phase 3: Advanced Analytics (Prioritet 2)

### Revenue & Business Analytics
- [ ] Revenue per course dashboard
- [ ] Enrollment vs completion korrelation
- [ ] Course profitability analysis (revenue - time invested)
- [ ] Refund tracking og årsagsanalyse
- [ ] Subscription churn analysis (for recurring payments)
- [ ] Student LTV (Lifetime Value) beregning
- [ ] Revenue forecasting baseret på trends

### Instructor Analytics
- [ ] Instructor performance dashboard
- [ ] Course quality score (completion rate + ratings + engagement)
- [ ] Response time tracking (Q&A, assignments)
- [ ] Content creation metrics
- [ ] Student satisfaction trends per instructor
- [ ] Instructor sammenligning (anonymiseret benchmarking)

### Predictive Analytics
- [ ] Course completion prediction (ML-baseret)
- [ ] Churn risk scoring per student
- [ ] Recommended actions for at-risk students
- [ ] Optimal email timing prediction
- [ ] Success factor analysis

### Goal Tracking & Alerts
- [ ] Custom goal definition (e.g., "80% completion rate")
- [ ] Alert system når goals ikke nås
- [ ] Trend warnings (e.g., "completion rate faldende")
- [ ] Slack/Discord/Email notifications
- [ ] Goal dashboard med progress meters

### Comparison Tools
- [ ] Course vs course comparison
- [ ] Period vs period (this month vs last month)
- [ ] Cohort vs cohort analysis
- [ ] Industry benchmarking (anonymiseret)

---

## 🔧 Phase 4: Integrations & Enterprise (Prioritet 3)

### External Integrations
- [ ] Google Analytics 4 event push
- [ ] Segment.io integration
- [ ] Mixpanel integration
- [ ] BigQuery export
- [ ] Slack notifications
- [ ] Discord notifications
- [ ] Microsoft Teams integration
- [ ] HubSpot/Salesforce CRM sync

### WooCommerce Integration
- [ ] WooCommerce Subscriptions tracking
- [ ] Renewal prediction
- [ ] Payment failure handling
- [ ] Upsell opportunity identification

### Multi-site & Enterprise
- [ ] Multisite network dashboard (aggregate stats)
- [ ] Cross-site student tracking
- [ ] White-label option
- [ ] Custom branding
- [ ] API rate limits per tier
- [ ] SSO integration

### Data & Privacy
- [ ] GDPR data export per student
- [ ] Data retention policies
- [ ] Anonymized reporting mode
- [ ] Audit log for admin actions

### Import/Migration
- [ ] LearnDash data import
- [ ] LifterLMS data import
- [ ] Generic CSV import
- [ ] Historical data backfill

---

## 📝 Marketing (Pending)

### Assets Needed
- [ ] Screenshots til docs/screenshots/ (5+ views)
- [ ] 2-min demo video
- [ ] Tutor LMS Facebook group post
- [ ] r/Wordpress post
- [ ] ProductHunt launch

---

## 📝 Notes

**Licensserver:** `/repos/tlat-license-server/` — LIVE på licenses.holstjensen.eu
**Key format:** `TLAT-XXXX-XXXX-XXXX-XXXX`
**Grace period:** 14 dage offline tolerance
**Stripe:** Payments aktive (LTD + Annual)

---

## Testing Checklist

Før hver release, verificer:
- [ ] Alle PHP filer syntax check (`find . -name "*.php" -exec php -l {} \;`)
- [ ] PHPUnit tests passes
- [ ] Docker test med Tutor LMS Free
- [ ] Manuel test i browser (alle admin sider)
- [ ] Licens aktivering/deaktivering
- [ ] Export funktioner (CSV, JSON)
- [ ] Chart.js visualiseringer
- [ ] Responsive design på tablet

## February 2026 — Nye muligheder

### Fra codebase analyse og research


- [ ] Mobile analytics dashboard
- [ ] Student progress PDF export
- [ ] Quiz attempt replay feature
- [ ] Email notifications for inactivity
- [ ] Course completion certificates
- [ ] Live session tracking (WebSocket)
- [ ] Multi-instructor support
- [ ] Custom report builder
- [ ] AI-powered content recommendations
- [ ] Integration with Zoom/Meet attendance


> 💡 Disse er forslag — evaluer og prioriter baseret på værdi/indsats.

