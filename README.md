# ASICs Mining Dashboard

A polished, responsive full-stack mining dashboard inspired by the supplied reference image.

## Features

- Dark neon dashboard UI with responsive desktop, tablet, and mobile layouts
- Arabic-first interface with one-click English localization
- Separate dashboard, live mining, transactions, security, settings, and notifications pages
- Node.js backend with persistent JSON storage, authentication, sessions, contracts, balances, transactions, and profile/password updates
- Interactive navigation, notifications, demo login, market search/filtering, plan purchasing, and Arabic/English localization

## Run locally

```bash
npm start
```

Then open `http://localhost:4173`.

Demo login: `demo@asics.test` / `demo123`

The backend is intentionally dependency-free and stores development data in `data.json`. Do not use the demo storage/authentication setup as-is for production without a real database, HTTPS, rate limiting, email verification, and production secret/session management.
Responsive mining dashboard inspired by the provided reference image, with Arabic and English localization.
