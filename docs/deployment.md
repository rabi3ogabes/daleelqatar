# Deployment guide

## 1. What is already provisioned on Supabase

| Item | Value |
|---|---|
| Project | `daleelqatar` — ref `uunuojnapwrehvrtozos` (region `ap-south-1`, PostgreSQL 17, free plan) |
| Project URL | `https://uunuojnapwrehvrtozos.supabase.co` |
| Application schema | `daleel` — all 41 tables, 119 indexes, 51 foreign keys (identical to `php artisan migrate`) |
| Extension | `pg_trgm` in schema `extensions` |
| Reference data | roles & permissions, 8 municipalities, 61 areas, 77 categories, pages, FAQs, Laravel `migrations` history |
| Access control | `anon` / `authenticated` have **no** privileges on `daleel` (not reachable via the public REST API) |
| Storage buckets | `daleel-public` (public; webp/jpeg/png; 10 MB) and `daleel-private` (private; pdf/jpeg/png; 10 MB) |
| Security advisor | 0 findings after provisioning |

> A row named "Deployment probe (safe to delete)" (slug `__probe__`, soft-deleted, archived) was left by the
> post-deployment search check because hard deletes through the connector need interactive approval. Remove it
> in the Supabase SQL editor: `delete from daleel.businesses where slug = '__probe__';`

### Credentials you need from the Supabase dashboard (never commit them)
1. **Database password** — *Project Settings → Database*. Reset it there if you do not have it.
2. **Connection string** — *Connect* button. Use the **direct** connection (`db.uunuojnapwrehvrtozos.supabase.co:5432`)
   if your server has IPv6, otherwise the **session pooler** (`aws-*-ap-south-1.pooler.supabase.com:5432`, user
   `postgres.uunuojnapwrehvrtozos`). Only if you must use the *transaction* pooler (port 6543) set `DB_EMULATE_PREPARES=true`.
3. **Service-role key** — *Project Settings → API Keys* (secret key). Server-side only: it goes in `SUPABASE_SERVICE_ROLE_KEY`
   and is used exclusively by `App\Support\Storage\SupabaseStorageAdapter`. It is never rendered to HTML/JS or returned by the API.

```dotenv
DB_CONNECTION=pgsql
DB_HOST=db.uunuojnapwrehvrtozos.supabase.co
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres
DB_PASSWORD=<from dashboard>
DB_SSLMODE=require
DB_SEARCH_PATH=daleel,extensions,public

MEDIA_DISK=supabase
PRIVATE_MEDIA_DISK=supabase_private
SUPABASE_URL=https://uunuojnapwrehvrtozos.supabase.co
SUPABASE_SERVICE_ROLE_KEY=<secret key>
```

Verify the connection from the server:

```bash
php artisan migrate:status      # all migrations show "Ran"
php artisan tinker --execute="echo App\Models\Category::count();"   # 77
```

## 2. Vercel (serverless)

The repository deploys to Vercel as-is: `vercel.json` sends every request that is not a static file in `public/`
to one PHP function, `api/index.php`, built with the community runtime
[`vercel-php@0.8.0`](https://github.com/vercel-community/php) (PHP 8.4, with `gd`/WebP, `pdo_pgsql` and `intl`).

| Piece | How it works on Vercel |
|---|---|
| Static files | `npm run build` writes Vite assets to `public/build`; everything in `public/` is served by Vercel's CDN (`/build/*` cached for a year). |
| PHP | `api/index.php` boots Laravel. The bundle is read-only, so it moves storage, compiled views and bootstrap caches to `/tmp`. It also sets serverless defaults (`LOG_CHANNEL=stderr`, `QUEUE_CONNECTION=sync`, database sessions/cache, Supabase media disks). Any variable set in the Vercel project overrides these defaults. |
| Composer | The runtime runs `composer install --no-dev`, then `composer run vercel`, which builds the assets if they are missing. |
| Scheduler | There is no long-running process, so a Vercel Cron Job calls `GET /cron/maintenance` daily at 03:00 UTC. It runs `daleel:sitemap`, `sanctum:prune-expired`, `queue:prune-failed`, `auth:clear-resets` and `model:prune`. Vercel authenticates with `Authorization: Bearer $CRON_SECRET`; without `CRON_SECRET` the endpoint returns 404. |
| Files | Uploads go to Supabase Storage (`MEDIA_DISK=supabase`). Nothing written to the function's disk survives the request. |
| Mail | Sent synchronously during the request. Configure a transactional provider (SMTP / Postmark / Resend) through the `MAIL_*` variables. |

### Environment variables (Project → Settings → Environment Variables)

| Variable | Value | Notes |
|---|---|---|
| `APP_KEY` | `base64:…` | `php artisan key:generate --show`. Mark it *Sensitive*. Changing it logs everyone out. |
| `CRON_SECRET` | random string (16+ characters) | Vercel sends it to the cron endpoint automatically. *Sensitive*, Production. |
| `APP_URL` | `https://daleelqatar.com` | Optional. Defaults to the production `*.vercel.app` URL. |
| `DB_HOST` | `aws-?-ap-south-1.pooler.supabase.com` | Copy it from Supabase → **Connect** → *Session pooler*. Vercel has no IPv6, so use the session pooler (port 5432), **not** `db.<ref>.supabase.co`. Do not use the transaction pooler: it drops the per-session `search_path`. |
| `DB_PORT` | `5432` | |
| `DB_DATABASE` | `postgres` | |
| `DB_USERNAME` | `postgres.uunuojnapwrehvrtozos` | Pooler user name: the `postgres.` prefix and the project ref. |
| `DB_PASSWORD` | database password | *Sensitive*. |
| `SUPABASE_URL` | `https://uunuojnapwrehvrtozos.supabase.co` | |
| `SUPABASE_SERVICE_ROLE_KEY` | secret key | *Sensitive*. Server-side only (Storage uploads and signed URLs). |
| `MAIL_*` | your mail provider | Optional until email delivery is needed. The default `log` mailer writes mail to the function logs. |

`DB_SSLMODE=require`, `DB_SEARCH_PATH=daleel,extensions,public`, `MEDIA_DISK=supabase` and
`PRIVATE_MEDIA_DISK=supabase_private` are already the defaults on Vercel.

### Function region
Set **Project → Settings → Functions → Region** to **Mumbai (`bom1`)**, next to the Supabase project in
`ap-south-1`. Every page makes several database round trips, so a cross-continent region adds most of the response time.

### Deploying
Pushes to the production branch deploy to production; other branches get preview deployments. Migrations do not run
on Vercel. Apply schema changes from a trusted machine with the production variables:
`php artisan migrate --force`, or through Supabase migrations as in section 1.

Verify a deployment with `GET /up` (framework health check) and `GET /robots.txt`.

## 3. Server requirements

* PHP 8.2+ (8.3 recommended) with `pdo_pgsql`, `mbstring`, `intl`, `gd` (WebP support), `fileinfo`, `openssl`, `curl`, `zip`, optional `exif`, `redis`
* Composer 2, Node 20+ (build step only), a web server (Nginx recommended), Supervisor (queue worker), cron
* HTTPS certificate (Let's Encrypt or your CDN)

Any Laravel-capable host works: a VPS (Ubuntu 24.04), Laravel Forge, Ploi, or Laravel Cloud. Supabase only hosts the database and files.

## 4. First deployment

```bash
git clone https://github.com/rabi3ogabes/daleelqatar.git /var/www/daleelqatar && cd /var/www/daleelqatar
composer install --no-dev --optimize-autoloader
npm ci && npm run build
cp .env.example .env            # then fill in production values (APP_ENV=production, APP_DEBUG=false, APP_URL=https://daleelqatar.com …)
php artisan key:generate
php artisan migrate --force     # no-op on the provisioned Supabase schema; applies future migrations
php artisan db:seed --force     # idempotent reference data (roles, locations, categories, pages)
php artisan daleel:create-admin # first super administrator (prompts for the password)
php artisan storage:link
php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache
```

Production `.env` essentials: `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, `SECURITY_HSTS=true`
(once HTTPS is permanent), a real `MAIL_MAILER` (SMTP/SES/Postmark/Resend), `QUEUE_CONNECTION=database` (or `redis`),
`LOG_LEVEL=warning`.

### Nginx

```nginx
server {
    listen 443 ssl http2;
    server_name daleelqatar.com www.daleelqatar.com;
    root /var/www/daleelqatar/public;
    index index.php;
    charset utf-8;
    client_max_body_size 12M;

    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }
    location ~* \.(?:css|js|woff2|svg|png|jpg|webp|ico)$ { expires 1y; access_log off; add_header Cache-Control "public, immutable"; }
    location ~ /\.(?!well-known) { deny all; }
}
```

### Queue worker & scheduler

```ini
; /etc/supervisor/conf.d/daleel-worker.conf
[program:daleel-worker]
command=php /var/www/daleelqatar/artisan queue:work --sleep=3 --tries=3 --max-time=3600
user=www-data
numprocs=2
autostart=true
autorestart=true
stopwaitsecs=3600
```

```cron
* * * * * www-data cd /var/www/daleelqatar && php artisan schedule:run >> /dev/null 2>&1
```

E-mails and notifications are queued; the scheduler warms the sitemap and prunes expired tokens (see `routes/console.php`).

### Subsequent releases

```bash
php artisan down --render="errors::503"
git pull && composer install --no-dev --optimize-autoloader && npm ci && npm run build
php artisan migrate --force
php artisan optimize:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan queue:restart
php artisan up
```

## 5. Backups & recovery

* **Supabase:** the free plan has no automatic backups you can restore yourself; Pro includes daily backups and optional point-in-time recovery. Upgrade before launch.
* **Independent logical backups (recommended on every plan):**
  ```bash
  pg_dump "$DATABASE_URL" --schema=daleel --format=custom --file=daleel-$(date +%F).dump
  ```
  Run nightly from cron and copy off-site (e.g. an S3-compatible bucket with lifecycle rules). Keep ≥ 30 days.
* **Restore:** `pg_restore --clean --if-exists --no-owner --dbname "$DATABASE_URL" daleel-YYYY-MM-DD.dump`, then `php artisan optimize:clear`.
* **Files:** Supabase Storage objects are not included in database backups; replicate the `daleel-public` / `daleel-private` buckets with `rclone` (S3 protocol) on a schedule.
* Test a restore into a staging project at least once per quarter.

## 6. Monitoring & operations

* Health check: `GET /up` (returns 200 when the app boots).
* Logs: daily files in `storage/logs` (`LOG_STACK=daily`); ship them to your log platform or add Sentry/Flare via `LOG_STACK`.
* Audit trail: *Admin → Audit logs* (moderation, role changes, suspensions, settings, exports, logins).
* Failed jobs: `php artisan queue:failed`; system health card under *Admin → Settings*.
* Keep dependencies patched: `composer audit`, `npm audit`, monthly `composer update` on staging first.

## 7. Security checklist

- [x] CSRF protection on every web form; Sanctum bearer tokens for the mobile API
- [x] Authorisation through policies/permissions on every protected action; ownership checks for owners
- [x] Rate limiting: login, registration, password reset, submissions, uploads, search, API, API auth
- [x] Uploaded images are decoded and re-encoded (EXIF/GPS stripped); documents stored in a private bucket with signed URLs
- [x] Security headers (CSP with nonces, X-Frame-Options, nosniff, Referrer-Policy, Permissions-Policy, optional HSTS)
- [x] IP addresses stored only as salted hashes in analytics; passwords/tokens never logged
- [x] Production error pages never expose stack traces (`APP_DEBUG=false`)
- [ ] Set `SECURITY_HSTS=true` and submit to the HSTS preload list once HTTPS is permanent
- [ ] Have the Privacy Policy and Terms reviewed by Qatari legal counsel
