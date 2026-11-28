# B2B Wholesale Ordering Portal

Bilingual (English / বাংলা) B2B wholesale ordering platform — Laravel 13 API + Vue 3 SPA.

See [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) for design decisions, ERD and folder structure, [docs/API.md](docs/API.md) for the endpoint reference and [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) for the production checklist.

## Status

| Phase | Scope | Status |
|---|---|---|
| 1 | Architecture, migrations, models, seeders | ✅ Done |
| 2 | Auth, RBAC, KYC, PricingEngine, catalog, quick order, RFQ APIs | ✅ Done |
| 3 | Orders, credit ledger, payments, mPDF invoices/challans, routes, policies, lang | ✅ Done |
| 4 | Vue foundation: i18n, Pinia, router guards, layout | ✅ Done |
| 5 | Buyer portal UI | ✅ Done |
| 6 | Admin portal UI, deployment checklist | ✅ Done |

## Backend setup

Requirements: PHP 8.3+ (gd, intl, mbstring, zip, xml, pdo_mysql/pdo_pgsql; mPDF fonts ship in `resources/fonts`), Composer 2, MySQL 8 or PostgreSQL 14+.

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
# configure DB_* in .env, then:
php artisan migrate --seed
```

### Tests

```bash
php artisan test            # SQLite in-memory
DB_CONNECTION=pgsql DB_DATABASE=b2b_test php artisan test   # PostgreSQL
```

### Queue & Scheduler

Notifications are queued (`QUEUE_CONNECTION=database` or redis):

```bash
php artisan queue:work --tries=3 --backoff=30
```

```bash
* * * * * php /path/to/backend/artisan schedule:run >> /dev/null 2>&1
```

### Demo accounts (non-production seeding only; password `Password@123` unless `SEED_DEFAULT_PASSWORD` is set)

| Role | Email |
|---|---|
| Super Admin | admin@example.com |
| Sales / Account Manager | sales@example.com |
| Warehouse / Dispatch | warehouse@example.com |
| Buyer Admin — Dhaka Super Mart Ltd. (approved, Gold, ৳500,000 Net-30) | buyer@example.com |
| Buyer Staff — approval limit ৳50,000 | procurement@example.com |
| Buyer Admin — Chattogram Traders (KYC pending) | pending.buyer@example.com |

In production only the Super Admin is seeded; set `SEED_DEFAULT_PASSWORD` (≥12 chars) or a random password is generated and printed once.

## Frontend setup

Requirements: Node 20.19+ / 22.

```bash
cd frontend
cp .env.example .env
npm ci
npm run dev          # http://localhost:5173 — proxies /api to Laravel on :8000
npm test             # Vitest unit tests
npm run build        # production bundle in dist/
```

Production: serve `frontend/dist` and reverse-proxy `/api` to Laravel on the same origin (keeps
`VITE_API_URL=/api/v1`, no CORS). Use an SPA fallback (`try_files $uri /index.html`).

## Package note

`lucide-vue-next` is deprecated upstream; the SPA uses its official successor `@lucide/vue` (same icon set and API).

`niklasravnsborg/laravel-pdf` is unmaintained and does not support Laravel 12/13, so the portal uses its maintained fork
[`carlos-meneses/laravel-mpdf`](https://github.com/mccarlosen/laravel-mpdf) — same mPDF engine and API, with full Bangla conjunct (যুক্তবর্ণ) shaping.
