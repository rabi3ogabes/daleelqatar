# Database schema — Daleel Qatar | دليل قطر

PostgreSQL (Supabase, PostgreSQL 17). Laravel migrations in `database/migrations/` are the single source of truth.
On Supabase all application tables live in the **`daleel` schema**, which is *not* exposed through Supabase's
auto-generated REST/GraphQL API (the `anon` and `authenticated` roles have no privileges on it). Laravel connects
directly as the database owner with `DB_SEARCH_PATH=daleel,extensions,public`.

## Conventions

| Topic | Decision |
|---|---|
| Primary keys | `bigint` identity for joins and indexes; **UUID** (`uuid` column) on resources exposed by the API (users, businesses, offers, reviews, claims, verification requests). |
| Bilingual content | Paired columns `<field>_ar` / `<field>_en` (exactly two languages → indexable, simple validation). Read with `$model->t('field')`. |
| Status fields | `varchar` + `CHECK` constraint (and a PHP backed enum in `app/Enums`). |
| Soft deletes | `users`, `businesses`, `reviews`, `offers`. |
| Timestamps | Asia/Qatar (UTC+3, no DST). |
| Integrity | Foreign keys everywhere (`RESTRICT` for taxonomy, `CASCADE` for owned detail rows, `SET NULL` for actors), unique and partial-unique indexes, `CHECK` constraints for ratings, coordinates, days and statuses. |
| Search | Generated columns `search_document` (Arabic-normalised text, trigram GIN index) and `search_vector` (weighted `tsvector`, GIN index). |

## Entity overview

```
users ─┬─< businesses >── categories (tree: parent_id)
       │        │  └──< business_category >── categories   (up to 3 secondary categories)
       │        ├── municipalities ──< areas
       │        ├──< business_images / business_services / opening_hours / business_social_links
       │        ├──< business_status_changes            (moderation history)
       │        ├──< business_claim_requests >── users  (ownership claims)
       │        ├──< business_verification_requests     (verification badge workflow)
       │        ├──< reviews >── users ──< review_reports
       │        ├──< listing_reports
       │        ├──< offers
       │        ├──< favorites >── users
       │        └──< business_events                    (views, calls, WhatsApp, website, directions, share, offer views)
       ├──< search_events
       ├──< audit_logs
       ├──< notifications (Laravel database notifications)
       └──< personal_access_tokens (Sanctum, mobile app)
pages · faqs · banners · site_settings · contact_messages
roles · permissions · model_has_roles · role_has_permissions (spatie/laravel-permission)
sessions · cache · jobs · failed_jobs · job_batches · password_reset_tokens (framework)
```

## Core tables

### `businesses`
The listing. Owner-editable columns are mass-assignable; moderation, verification, featuring and aggregate
columns are only changed by services (`ListingWorkflow`, `VerificationService`, `ReviewService`, …).

| Column | Notes |
|---|---|
| `uuid` | Public API identifier |
| `owner_id` | Nullable (staff-created listings can be **claimed**) |
| `category_id`, `municipality_id`, `area_id` | Taxonomy (`RESTRICT` on delete) |
| `name_*`, `tagline_*`, `description_*`, `address_*`, `keywords`, `services_text` | Content; `services_text` is denormalised from `business_services` for search |
| `latitude`, `longitude` | Optional; validated to Qatar's bounding box |
| `phone`, `whatsapp`, `email`, `website` | Only what the business supplied |
| `logo_path`, `cover_path` | Paths on the media disk (Supabase Storage) |
| `status` | `draft · pending · approved · rejected · suspended · archived` |
| `submitted_at`, `approved_at`, `approved_by`, `published_at`, `rejection_reason`, `moderation_notes` | Workflow |
| `is_featured`, `featured_until` | Editorial featuring |
| `is_verified`, `verified_at`, `verified_by` | Granted only by staff after document review |
| `is_demo` | Development sample data flag (shown as "Sample listing", excluded from the sitemap) |
| `rating_avg`, `reviews_count` | Computed from **approved** reviews only |
| `views_count`, `favorites_count` | Counters maintained from events |
| `search_document`, `search_vector` | Generated, indexed search columns |

Indexes: `(status, published_at)`, `(status, category_id)`, `(status, municipality_id, area_id)`, `(status, is_featured)`,
`(status, is_verified)`, `(status, rating_avg)`, `owner_id`, GIN on `search_vector`, GIN trigram on `search_document`.

### Moderation & trust
* **`business_status_changes`** — every transition (from, to, actor, note).
* **`business_claim_requests`** — `pending · approved · rejected · cancelled`; partial unique index allows one *pending* claim per user per listing. Approval assigns `owner_id` in a transaction and closes competing claims.
* **`business_verification_requests`** — one pending request per listing (partial unique index); documents live in the **private** bucket and are only opened through short-lived signed URLs.
* **`reviews`** — one review per user per business (`UNIQUE (business_id, user_id)`), rating `CHECK 1..5`, statuses `pending · approved · rejected · hidden`, optional owner reply.
* **`review_reports`**, **`listing_reports`** — `open · resolved · dismissed`.
* **`audit_logs`** — actor, action, auditable (morph map), old/new values (secrets redacted), IP, user agent.

### Engagement & analytics
* **`favorites`** — `UNIQUE (user_id, business_id)`.
* **`search_events`** — query, normalised query, filters, result count, locale, source (`web`/`api`), **hashed** IP.
* **`business_events`** — `view · call · whatsapp · website · directions · share · offer_view`, source, hashed IP. Views are de-duplicated per visitor per 30 minutes and bots are ignored.

### Content & configuration
`pages` (Markdown, rendered with raw HTML stripped), `faqs` (audience `general · users · owners`), `banners`
(placement + schedule), `site_settings` (JSON values, cached; defaults in `App\Services\SettingsService::defaults()`),
`contact_messages`.

## Search design

`App\Support\Search\PostgresBusinessSearch` (behind the `BusinessSearchEngine` interface) combines:

1. Prefix full-text match: `search_vector @@ to_tsquery('simple', 'word1:* & word2:*')`, ranked with `ts_rank` (name weight A, services/keywords/tagline B, description C).
2. Substring match on the normalised `search_document` (trigram index).
3. Typo tolerance: `word_similarity(query, search_document) >= 0.5`.
4. Category and place names: a query matching a category or area/municipality name returns businesses in it.

Arabic normalisation (identical in PHP and SQL): أ إ آ ٱ → ا, ى → ي, ة → ه, tatweel and tashkeel removed.
To move to Meilisearch/Typesense/OpenSearch later, implement `App\Contracts\BusinessSearchEngine` and change the
binding in `AppServiceProvider` — controllers and the API do not change.

## Seeders

| Seeder | Content | Environments |
|---|---|---|
| `RolesAndPermissionsSeeder` | 5 roles, 25 permissions (`App\Support\Permissions`) | all |
| `LocationSeeder` | 8 municipalities, 61 areas | all |
| `CategorySeeder` | 14 categories, 63 sub-categories | all |
| `ContentSeeder` | About / Privacy / Terms pages, 8 FAQs | all |
| `DemoDataSeeder` | 24 **sample** listings flagged `is_demo` (no fabricated contacts, ratings, reviews or verification) | local only — refuses to run in production; remove with `php artisan daleel:purge-demo` |
