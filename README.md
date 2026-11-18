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

## Run locally

For zero setup: PHP 8.1+ with `pdo_sqlite`.

For XAMPP/phpMyAdmin: PHP 8.1+, `pdo_mysql`, and an `asics_mining` database or import `seed.sql` directly.

```bash
php -S localhost:4173
```

Then open `http://localhost:4173`.

Demo login: `demo@asics.test` / `demo123`

The SQLite database is created automatically as `database.sqlite`. For MySQL, `seed.sql` creates the `asics_mining` database and all tables. Products and user ownership are stored in the database; a purchase is created only after the invoice is marked paid by the payment webhook.

To seed or refresh the demo account and products:

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
