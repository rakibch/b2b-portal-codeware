# Production Deployment Checklist

Target: one Ubuntu 24.04 server (or VM) running Nginx, PHP-FPM 8.3, MySQL 8 / PostgreSQL 16, Redis 7,
Supervisor. The SPA and API are served from **one origin** (`https://portal.example.com`, API under `/api`).
Templates live in [`deploy/`](../deploy) and [`backend/.env.production.example`](../backend/.env.production.example).

## 1. Server prerequisites

- [ ] PHP 8.3 + extensions: `bcmath` (optional), `ctype`, `curl`, `dom`, `fileinfo`, `gd`, `intl`, `mbstring`, `openssl`,
      `pdo_mysql` or `pdo_pgsql`, `redis`, `tokenizer`, `xml`, `zip`, `opcache`
- [ ] `php.ini` (FPM): `upload_max_filesize = 10M`, `post_max_size = 12M`, `memory_limit = 256M` (mPDF), `expose_php = Off`,
      `opcache.enable=1`, `opcache.validate_timestamps=0` (reload FPM on deploy)
- [ ] Composer 2, Node 22 LTS (build only), Nginx, Supervisor, Redis (password set, bound to 127.0.0.1)
- [ ] Database user with privileges on the app schema only (no `SUPER`/global grants)
- [ ] Firewall: only 22 (restricted), 80, 443 open; DB/Redis not publicly reachable
- [ ] Unattended security updates enabled; SSH key-only login, root login disabled

## 2. Application setup (first deploy)

```bash
sudo mkdir -p /var/www/b2b-portal && sudo chown deploy:www-data /var/www/b2b-portal
git clone <repo-url> /var/www/b2b-portal
cd /var/www/b2b-portal/backend
cp .env.production.example .env && chmod 640 .env       # fill every CHANGE_ME
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force                              # roles, settings, groups, catalog seed, super admin only
php artisan storage:link
sudo chown -R www-data:www-data storage bootstrap/cache && sudo chmod -R ug+rwX storage bootstrap/cache
cd ../frontend && cp .env.example .env && npm ci --legacy-peer-deps && npm run build
```

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, unique `APP_KEY` (back it up — it encrypts queued invitation payloads)
- [ ] Production seeding creates **only** the Super Admin (demo buyers are never seeded). Set `SEED_DEFAULT_PASSWORD`
      (≥ 12 chars) or note the one-time password printed by the seeder; change it at first login
- [ ] Update seller details used on invoices (`settings` table: `seller.*`, `invoice.*`) — name, address, BIN/TIN, bank
- [ ] Replace demo catalog data or remove it before go-live

## 3. Web server & TLS

- [ ] Install [`deploy/nginx.conf`](../deploy/nginx.conf), set `server_name`, obtain a certificate (`certbot --nginx`)
- [ ] Confirm the strict **Content-Security-Policy** header is served for the SPA (verified: the production bundle runs with
      `script-src 'self'` and no violations)
- [ ] HSTS enabled; HTTP → HTTPS redirect
- [ ] `client_max_body_size 12m` (KYC uploads are capped at 5 MB server-side)
- [ ] Behind a load balancer: set `TRUSTED_PROXIES` to its IPs/CIDRs (never `*` on a public host)

## 4. Queue workers & scheduler

- [ ] Install [`deploy/supervisor-queue.conf`](../deploy/supervisor-queue.conf) → `supervisorctl reread && supervisorctl update`
      (notifications: KYC, orders, payments, RFQs, invitations are queued)
- [ ] Install the scheduler cron from [`deploy/crontab`](../deploy/crontab):
  - `rfqs:expire` daily 00:10 — expires stale quotations
  - `invoices:remind-overdue` daily 09:05 — reminders on day 1 / 7 / 14 / 30 overdue
  - `sanctum:prune-expired` daily, `auth:clear-resets` every 15 min
- [ ] `php artisan schedule:list` shows all tasks; `supervisorctl status` shows workers `RUNNING`

## 5. Security review (go-live gate)

- [ ] `CORS_ALLOWED_ORIGINS` = the portal origin only
- [ ] `SANCTUM_TOKEN_EXPIRATION` set (default 7 days); tokens are revoked on logout / password change / deactivation
- [ ] `CACHE_STORE=redis` so login/registration/upload rate limits are shared across all PHP workers
- [ ] KYC documents & payment slips are on the **private** `local` disk (`storage/app/private`) — never under `public/`
- [ ] `PAYMENT_MOCK_ENABLED=false`; only a real, signature-verifying gateway driver may be enabled
- [ ] Mail SPF/DKIM/DMARC configured for `MAIL_FROM_ADDRESS`
- [ ] `php artisan about` shows: environment production, debug OFF, cache/queue/session = redis
- [ ] Audit log (`audit_logs` table) reviewed periodically; restrict DB read access to it

## 6. Deploying updates

```bash
cd /var/www/b2b-portal && ./deploy/deploy.sh
```

The script: `git pull --ff-only` → `composer install --no-dev` → maintenance mode → `migrate --force` → config/route/view/event
caches → frontend `npm ci && npm run build` → `queue:restart` → reload PHP-FPM → back online.

- [ ] Destructive commands (`migrate:fresh`, `db:wipe`) are blocked in production by `DB::prohibitDestructiveCommands`

## 7. Backups & recovery

- [ ] Configure [`deploy/backup.sh`](../deploy/backup.sh) (set `BACKUP_GPG_RECIPIENT`): nightly encrypted DB dump +
      private files, 14-day local retention — **sync `/var/backups/b2b-portal` off-site**
- [ ] Back up `.env` (incl. `APP_KEY`) separately in a secrets manager
- [ ] Test a restore to a staging server at least quarterly

## 8. Monitoring

- [ ] Uptime check on `https://portal.example.com/up`
- [ ] Alert on `storage/logs/laravel-*.log` errors and failed jobs (`php artisan queue:failed`)
- [ ] Track disk usage of `storage/app/private` (KYC uploads) and DB growth

## 9. Smoke test after go-live

- [ ] Register a test company with documents → appears in **KYC Approvals** → approve with a credit limit
- [ ] Buyer: catalog shows tier prices, quick order adds to cart, checkout on credit succeeds
- [ ] Admin: confirm order → invoice PDF (EN & বাংলা) downloads, Bangla conjuncts render correctly
- [ ] Create a partial shipment → challan PDF has no prices → dispatch → deliver
- [ ] Submit a bank-transfer slip → verify → ledger balance and invoice status update
- [ ] Switch EN | বাং on several screens; emails arrive in the user's language
