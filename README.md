# ASICs Mining Dashboard

A PHP full-stack mining dashboard inspired by the supplied reference image.

## Features

- Dark neon dashboard UI with responsive desktop, tablet, and mobile layouts
- Arabic-first interface with one-click English localization
- Separate dashboard, live mining, transactions, security, settings, and notifications pages
- PHP backend with MySQL/phpMyAdmin support and an automatic SQLite fallback for users, login sessions, products, crypto invoices, purchases, balances, earnings, transactions, and notifications
- Interactive navigation, notifications, demo login, market search/filtering, plan purchasing, and Arabic/English localization
- Crypto payment invoice flow with provider webhook confirmation
- BTC, ETH, TRX, USDT-TRC20, USDT-ERC20, SOL, LTC, and DOGE invoice options
- Pending and completed deposits/withdrawals in the transactions page
- Administrator dashboard for users, deposits, withdrawals, and plans
- Configurable coin wallets and withdrawal requests
- Referral codes and 5% referral rewards after confirmed purchases
- Profile editing and JPG/PNG/WebP avatar uploads
- Live crypto/USD conversion rates from CoinGecko with cached fallback
- $9.99 VIP subscription, VIP-only machines, 24-hour daily free-machine activation, and a $5 monthly reward after 30 activations
- Tiered referrals: 5% with 1-4 referrals, 7% with 5-9, and 10% with 10+
- Administrator account suspension/reactivation, balance adjustments, deposits/earnings summaries, device/IP history, and audit logs

## Run locally

For zero setup: PHP 8.1+ with `pdo_sqlite`.

For XAMPP/phpMyAdmin: PHP 8.1+, `pdo_mysql`, and an `asics_mining` database or import `seed.sql` directly.

```bash
php -S localhost:4173
```

Then open `http://localhost:4173`.

No demo customer account is created. Public visitors see the landing, products, company, and contact pages before authentication.

The default administrator is:

```text
Email: admin@asics.test
Password: Admin123456!
```

Change this password immediately after the first login. You can also configure a separate administrator through environment variables:

```bash
export ADMIN_EMAIL="admin@example.com"
export ADMIN_PASSWORD="a-strong-password-of-at-least-12-characters"
```

Start the PHP app, then open `/admin.php` after signing in with that administrator account. The administration URL is not linked anywhere in the public or member UI.

The SQLite database is created automatically as `database.sqlite`. For MySQL, `seed.sql` creates the `asics_mining` database and all tables. Products and user ownership are stored in the database; a purchase is created only after the invoice is marked paid by the payment webhook.

The public landing page includes home, products, company, and contact sections. Mining dashboard pages require login. `/admin.php` is intentionally not linked from the website and requires an account marked as an administrator.

To seed or refresh the administrator and products:

```bash
php seed.php
```

For phpMyAdmin, import `seed.sql`. For a direct SQLite seed, initialize the database first, then run:

```bash
sqlite3 database.sqlite < seed.sql
```

## Crypto payments

Set these environment variables for a real deployment:

```bash
export CRYPTO_PAYMENT_ADDRESS="your receiving address"
export PAYMENT_WEBHOOK_SECRET="shared webhook secret"
export APP_DEBUG="false"
```

Create an invoice with `POST /api.php/payments/invoice`, then configure BTCPay Server, Coinbase Commerce, or another crypto provider to call `POST /api.php/payments/webhook` with JSON containing `invoiceCode` and `txid`. The webhook creates the owned mining product and transaction atomically. The default address is intentionally a demo placeholder and must be replaced before accepting funds.
Responsive mining dashboard inspired by the provided reference image, with Arabic and English localization.
