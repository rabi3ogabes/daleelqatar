<p align="center"><img src="public/brand/daleel-qatar-logo-stacked-light.svg" alt="دليل قطر — Daleel Qatar" height="180"></p>

<h3 align="center">دليل قطر — اكتشف قطر، بكل ما فيها.<br>Daleel Qatar — Discover Qatar. Find Everything.</h3>

<p align="center">A premium, bilingual (Arabic-first) business directory for Qatar — public website, business-owner dashboard,
administration dashboard and a versioned REST API for the future Flutter app, on one Laravel backend.</p>

---

## Contents
1. [Stack](#stack) · 2. [Features](#features) · 3. [Quick start](#quick-start) · 4. [Environment variables](#environment-variables)
5. [Supabase](#supabase-database--storage) · 6. [Tests](#tests) · 7. [Deployment](#deployment) · 8. [Project structure](#project-structure) · 9. [Limitations](#known-limitations--next-steps)

Further documentation: [`docs/database.md`](docs/database.md) · [`docs/deployment.md`](docs/deployment.md) · [`docs/api/README.md`](docs/api/README.md) ([OpenAPI](docs/api/openapi.yaml)) · [`docs/brand.md`](docs/brand.md)

## Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 12 (PHP 8.3), form requests, policies, services, notifications, queues |
| Database | **Supabase PostgreSQL 17** via Laravel's native `pgsql` driver (isolated `daleel` schema) |
| Search | PostgreSQL full-text (`tsvector`) + `pg_trgm` trigram matching with Arabic normalisation, behind a swappable `BusinessSearchEngine` interface |
| Files | **Supabase Storage** through a custom Flysystem adapter (public bucket for images, private bucket + signed URLs for documents) |
| Frontend | Blade components, Tailwind CSS 4, Alpine.js, Vite; IBM Plex Sans Arabic + Inter (self-hosted) |
| Auth | Session auth for the web, **Laravel Sanctum** bearer tokens for mobile; `spatie/laravel-permission` roles |
| API | `/api/v1` JSON REST, OpenAPI 3.1 |

## Features

**Public website (Arabic RTL by default, English LTR under `/en`)**
- Premium homepage matching the brand artwork: Doha-skyline hero, three-part search (keyword · location · category) with live suggestions, category tiles, featured businesses, *Explore Qatar by location*, recently added, popular & verified, live offers, owner call-to-action, trust section.
- Search with Arabic spelling normalisation, typo tolerance, category/sub-category, municipality/area, verified, rating (only when real ratings exist), open-now (Qatar time, overnight hours handled), sorting, grid/list views, shareable URLs, helpful empty states.
- Business profiles: logo, cover, gallery lightbox, bilingual description, services with prices, opening hours (today highlighted), contact actions (call, WhatsApp, website, directions) with interaction tracking, OpenStreetMap embed, social links, offers, moderated reviews with rating distribution and owner replies, report listing, share, favourites, claim, similar businesses, `LocalBusiness` JSON-LD.
- Category, location, featured, latest, offers, About, FAQ, Contact, Privacy, Terms, "Add your business", claim pages; localized 401/403/404/419/429/500/503 pages.
- SEO: canonical URLs, `hreflang` (ar/en/x-default), Open Graph/Twitter cards, JSON-LD (Organization, WebSite SearchAction, LocalBusiness, BreadcrumbList, FAQPage), XML sitemap with alternates (only published, non-sample listings and non-empty pages), environment-aware `robots.txt`, `noindex` on search results, drafts, dashboards and empty pages.

**Business-owner dashboard** (`/account`)
- Registration, e-mail verification (also from the mobile app), password reset, login throttling, suspended-account handling.
- Create/edit listings in both languages (draft → submit → review), completeness checklist, status history and rejection reasons, logo/cover/gallery uploads (re-encoded to WebP, EXIF stripped), opening hours with split shifts, services, social links, SEO fields.
- 30-day views and contact-interaction statistics, offers & promotions (moderated, auto-expiring), review replies, verification requests (private documents), claims, favourites, notifications, profile/password/notification preferences, account deletion.

**Administration dashboard** (`/admin`)
- Overview with real metrics and trends, business growth, category distribution, approvals vs rejections, activity feeds.
- Businesses (filters, CRUD, approve/reject/suspend/restore/archive, feature, verify, owner assignment, history, CSV export), claims and verification queues, hierarchical categories, municipalities & areas, users (roles with anti-escalation rules, suspension, password reset), roles & permissions matrix, reviews and reports moderation, offers, pages, FAQs, banners, homepage content, contact messages, settings (brand, homepage, contact, moderation, uploads, notifications, SEO, system health), analytics, audit logs.

**REST API v1** — 62 operations for the Flutter app (public directory, auth, profile, favourites, reviews, owner listings/media/offers, claims, notifications). See [`docs/api/README.md`](docs/api/README.md).

**Security** — CSRF, policies on every protected action, ownership checks, rate limiting, CSP with nonces and other security headers, validated & re-encoded uploads, private documents behind signed URLs, safe external links, hashed analytics IPs, audit logging without secrets, no stack traces in production, service-role key server-side only.

## Quick start

Requirements: PHP 8.2+ (`pdo_pgsql`, `gd` with WebP, `intl`, `mbstring`), Composer 2, Node 20+, PostgreSQL 15+ (local) or a Supabase project.

```bash
composer install
npm install && npm run build
cp .env.example .env && php artisan key:generate
# Local PostgreSQL: set DB_HOST/DB_DATABASE/DB_USERNAME/DB_PASSWORD, DB_SSLMODE=disable, DB_SEARCH_PATH=public
php artisan migrate --seed                      # schema + reference data (roles, locations, categories, pages)
php artisan db:seed --class=DemoDataSeeder      # optional, LOCAL ONLY: 24 clearly-labelled sample listings
php artisan daleel:create-admin                 # first super administrator
php artisan storage:link
php artisan serve                               # http://localhost:8000 (Arabic) · http://localhost:8000/en (English)
```

Run `npm run dev` for hot reloading and `php artisan queue:work` to process e-mails/notifications.
Remove sample data at any time with `php artisan daleel:purge-demo`.

## Environment variables

See [`.env.example`](.env.example) — every variable is documented there. The important groups:

| Variables | Purpose |
|---|---|
| `APP_LOCALE=ar`, `APP_SUPPORTED_LOCALES=ar,en`, `APP_TIMEZONE=Asia/Qatar` | Arabic default, English under `/en`, Qatar time |
| `DB_*`, `DB_SEARCH_PATH`, `DB_SSLMODE`, `DB_EMULATE_PREPARES` | PostgreSQL / Supabase connection |
| `MEDIA_DISK`, `PRIVATE_MEDIA_DISK` | `supabase` / `supabase_private` in production, `public` / `local` in development |
| `SUPABASE_URL`, `SUPABASE_SERVICE_ROLE_KEY`, `SUPABASE_PUBLIC_BUCKET`, `SUPABASE_PRIVATE_BUCKET`, `SUPABASE_SIGNED_URL_TTL` | Storage (service-role key is **server-side only**) |
| `SANCTUM_TOKEN_EXPIRATION_MINUTES` | Mobile token lifetime |
| `SECURITY_HSTS`, `SECURITY_CSP` | Security headers |
| `MAIL_*`, `QUEUE_CONNECTION`, `CACHE_STORE`, `SESSION_*` | Infrastructure |

Brand texts, homepage hero, contact details, moderation rules, upload limits and SEO defaults are **not** env
variables — administrators edit them in *Admin → Settings*.

## Supabase (database & storage)

A dedicated Supabase project **`daleelqatar`** (ref `uunuojnapwrehvrtozos`, region `ap-south-1`) has been provisioned with
the complete schema, reference data and both storage buckets; the security advisor reports no issues. Details,
the connection string format and the remaining credentials you must copy from the dashboard are in
[`docs/deployment.md`](docs/deployment.md#1-what-is-already-provisioned-on-supabase).

To provision a different project yourself:

1. Create a project; in the SQL editor run `create extension if not exists pg_trgm with schema extensions; create schema daleel;`
2. Set the `DB_*` variables (with `DB_SEARCH_PATH=daleel,extensions,public`) and run `php artisan migrate --seed`.
3. Create buckets `daleel-public` (public) and `daleel-private` (private) under *Storage*, then set `MEDIA_DISK=supabase`, `PRIVATE_MEDIA_DISK=supabase_private`, `SUPABASE_URL`, `SUPABASE_SERVICE_ROLE_KEY`.
4. `revoke all on schema daleel from anon, authenticated;` keeps the tables out of Supabase's public REST API.

## Tests

The suite runs against a **real PostgreSQL database** (never SQLite) — see `phpunit.xml` (`DB_DATABASE=daleelqatar_test`).

```bash
createdb daleelqatar_test      # once
php artisan test
```

Coverage includes: public pages in both languages and RTL/LTR, language switching, search on PostgreSQL (Arabic normalisation, typos, filters), publication rules, reviews/favourites/claims/reports, offer expiry, sitemap/robots/security headers, the Supabase Storage adapter, registration/login/verification/password reset, owner listing workflow and ownership enforcement, uploads, admin moderation/permissions/anti-escalation/exports/settings, and the full API (auth, search, owner CRUD, favourites, reviews, rate limiting).

## Deployment

Step-by-step production instructions (server requirements, Nginx, queue worker, scheduler, release procedure,
backups & recovery, monitoring, security checklist): [`docs/deployment.md`](docs/deployment.md).

## Project structure

```
app/
  Contracts/BusinessSearchEngine.php      search abstraction
  Enums/                                  statuses, reasons, platforms, event types
  Http/Controllers/{Web,Account,Admin,Auth,Api/V1}
  Http/Requests/                          BusinessRequest (shared by web, admin and API) + area requests
  Http/Resources/                         API resources
  Models/                                 Eloquent models (HasTranslations for *_ar/*_en)
  Notifications/                          localized database + mail notifications
  Policies/                               Business, Offer, Review, User
  Services/                               ListingWorkflow, BusinessManager, ReviewService, ClaimService,
                                          VerificationService, OfferService, MediaService, AnalyticsRecorder,
                                          SettingsService, AuditLogger, DashboardMetrics
  Support/                                Localization, Permissions, Search/, Storage/SupabaseStorageAdapter, Icons, Brand
resources/views/{web,account,admin,auth,components,partials,errors}
routes/web.php (+ web/*.php registered per locale), routes/api/v1.php
database/{migrations,seeders,factories}
docs/                                     database, deployment, API, brand
public/brand/                             logo suite, icons, OG image
```

## Known limitations & next steps

- **Monetization / listing plans** are not implemented (the prompt made them optional); the schema leaves room for a `listing_plans` table.
- **Maps** use OpenStreetMap embeds and Google Maps direction links — no API key required. Owners enter coordinates manually (a map picker would need a maps API key).
- **Hero photography:** the site ships an original vector Doha-skyline illustration; upload a licensed photograph in *Admin → Homepage* for a photographic hero like the reference artwork.
- **Supabase backups:** the project is on the free plan (no self-service restores). Upgrade to Pro before launch and schedule the `pg_dump` backup in `docs/deployment.md`.
- **Legal pages** are professional drafts and must be reviewed by counsel.
- The live Supabase Storage upload path is verified with HTTP-level tests; run one real upload after adding `SUPABASE_SERVICE_ROLE_KEY` on the server (this build environment could not reach `*.supabase.co`).
