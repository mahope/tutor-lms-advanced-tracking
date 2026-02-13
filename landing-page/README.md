# Tutor LMS Advanced Tracking - Landing Page

Marketing landing page for TLAT (Tutor LMS Advanced Tracking).

## Tech Stack

- Next.js 15
- React 19
- Tailwind CSS v4
- TypeScript

## Development

```bash
npm install
npm run dev
```

Open http://localhost:3000

## Deployment

### Vercel (Recommended)

```bash
npm i -g vercel
vercel
```

### Docker (Dokploy)

```bash
docker build -t tlat-landing .
docker run -p 3000:3000 tlat-landing
```

### Dokploy Setup

1. Create new Application in Dokploy
2. Source: GitHub repo (path: `landing-page/`)
3. Build: Dockerfile
4. Domain: `tutor-tracking.com`
5. Enable HTTPS (Let's Encrypt)

## Configuration

Update Stripe checkout links in `src/app/page.tsx`:
- Line ~268: Lifetime purchase link
- Line ~293: Annual subscription link

## Structure

```
src/
  app/
    layout.tsx    # HTML layout + metadata
    page.tsx      # Main landing page
    globals.css   # Tailwind imports + custom CSS
```

## Pages to Add

- `/privacy` - Privacy policy
- `/terms` - Terms of service
- `/docs` - Documentation (redirect to docs.tutor-tracking.com)

## Analytics

Add Plausible tracking by updating `layout.tsx`:

```tsx
<script defer data-domain="tutor-tracking.com" src="https://plausible.io/js/script.js"></script>
```
