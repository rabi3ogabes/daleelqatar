# Daleel Qatar API — v1

The versioned JSON REST API used by the Daleel Qatar | دليل قطر website and the future Flutter mobile app.
It runs on the same Laravel backend, database and business rules as the website — there is no separate mobile backend.

* **Base URL:** `https://daleelqatar.com/api/v1` (local: `http://localhost:8000/api/v1`)
* **Specification:** [`openapi.yaml`](openapi.yaml) (OpenAPI 3.1) — also served at `GET /api/v1/openapi.yaml`
* **Format:** JSON request and response bodies (`Content-Type: application/json`); uploads use `multipart/form-data`
* **Tests:** `tests/Feature/Api/*` (run `php artisan test --filter='Tests\\Feature\\Api'`)

---

## 1. Authentication (mobile clients)

The API uses **Laravel Sanctum personal access tokens**. Web sessions and API tokens are separate:
the website uses cookie sessions, the app uses bearer tokens.

1. **Register** or **log in** with a `device_name` (e.g. the phone model). The response contains a `token`.
2. Store the token in the platform's secure storage (Keychain / Android Keystore — e.g. `flutter_secure_storage`).
3. Send it on every request: `Authorization: Bearer <token>`.
4. **Log out** revokes the current token; **log out everywhere** revokes all tokens. Changing the password revokes every other token.

Tokens expire after `SANCTUM_TOKEN_EXPIRATION_MINUTES` (default 30 days, see `.env.example`); `expires_at` is returned with the token.
When a request returns `401 unauthenticated`, discard the token and send the user to the login screen.

```bash
curl -X POST https://daleelqatar.com/api/v1/auth/login \
  -H 'Accept: application/json' -H 'Content-Type: application/json' -H 'Accept-Language: en' \
  -d '{"email":"owner@example.com","password":"••••••••","device_name":"Pixel 9"}'
```

```json
{
  "data": {
    "user": { "id": "9b1d…", "name": "Mariam", "email": "owner@example.com", "locale": "en",
              "email_verified": true, "roles": ["business-owner"], "is_business_owner": true },
    "token": "12|kD9x…",
    "token_type": "Bearer",
    "expires_at": "2026-11-20T09:00:00+03:00"
  }
}
```

**E-mail verification.** Registration sends a verification link. Favourites, reviews, claims and all business-owner
endpoints require a verified address and otherwise answer `403` with `code: "email_unverified"`
(re-send the link with `POST /auth/email/resend`).

**Suspended accounts** cannot log in (`403 account_suspended`) and existing tokens are refused and revoked.

**Password reset.** `POST /auth/forgot-password` always returns the same message, whether or not the address exists,
so accounts cannot be enumerated. The reset link opens the website.

## 2. Languages

Send `Accept-Language: ar` or `en`. Arabic is the default when the header is absent.
Localized fields (`name`, `description`, …) are returned in the negotiated language, and detail resources include a
`translations` object with both languages so the app can switch language without refetching:

```json
"name": "مقهى سدرة",
"translations": { "name": { "ar": "مقهى سدرة", "en": "Sidra Coffee House" } }
```

The response carries `Content-Language` and error messages are localized too.

## 3. Identifiers

| Resource | Identifier in URLs |
|---|---|
| Business | UUID **or** slug (`/businesses/sidra-coffee-house`) |
| Offer | UUID (public endpoints also accept the slug) |
| Review, claim | UUID |
| Category, municipality | slug |
| Gallery image, notification | numeric ID / UUID as returned |

Internal numeric database IDs of businesses, users and reviews are never exposed.

## 4. Responses, pagination, filtering and sorting

Single resources: `{ "data": { … } }`. Collections are paginated:

```json
{
  "data": [ … ],
  "links": { "first": "…?page=1", "last": "…?page=4", "prev": null, "next": "…?page=2" },
  "meta": { "current_page": 1, "from": 1, "last_page": 4, "per_page": 12, "to": 12, "total": 43 }
}
```

* `page` (≥ 1) and `per_page` (1–48, default 12) on list endpoints.
* **Directory search** `GET /businesses`: `q`, `category` (slug, includes sub-categories), `municipality`, `area`,
  `verified`, `open_now`, `featured` (`1|0|true|false`), `rating` (1–5 minimum average) and
  `sort` = `relevance` (default) · `newest` · `rating` · `popular` · `name`. The applied filters are echoed in `meta.filters`.
* Search is PostgreSQL full-text + trigram matching with Arabic normalisation (أ/إ/آ → ا, ى → ي, ة → ه, diacritics
  removed), so `قهوه` matches `قهوة` and small typos still match. Searching a category or place name
  (e.g. `Lusail`) returns businesses in it. The engine sits behind `App\Contracts\BusinessSearchEngine` and can be
  replaced by a dedicated search service without changing this API.
* `is_open_now` is computed in Qatar time (`Asia/Qatar`) and is `null` when a business has not provided opening hours.
* Ratings are only ever computed from genuine, moderated reviews; `rating.average` is `null` when there are none.
* Development sample listings are flagged `is_sample: true`.

## 5. Errors

Every error uses the same envelope:

```json
{ "message": "The given data was invalid.", "code": "validation_failed", "errors": { "email": ["The email field is required."] } }
```

| HTTP | `code` | Meaning |
|---|---|---|
| 401 | `unauthenticated` | Missing, invalid or expired token |
| 401 | `invalid_credentials` | Wrong e-mail or password (login) |
| 403 | `forbidden` | Not allowed (e.g. editing another owner's listing) |
| 403 | `account_suspended` | The account is suspended |
| 403 | `email_unverified` | The e-mail address must be verified first |
| 404 | `not_found` | Unknown resource — or a listing that is not published |
| 422 | `validation_failed` | Field errors in `errors` |
| 422 | `workflow_error` | A business rule blocked the action (e.g. submitting an incomplete listing, duplicate review) |
| 429 | `too_many_requests` | Rate limit exceeded — honour `Retry-After` |
| 500 | `server_error` | Unexpected error (details are never exposed in production) |

## 6. Rate limits

| Limiter | Applies to | Limit |
|---|---|---|
| `api` | every endpoint | 120 requests / minute per user (or IP) |
| `api-auth` | register, login, forgot-password | 6 / minute per e-mail + IP, 30 / minute per IP |
| `search` | `GET /businesses` | 90 / minute per IP |
| `submissions` | reviews, reports, claims, contact, new listings/offers | 15 / hour per user (or IP) |
| `uploads` | logo, cover, gallery, verification documents | 120 / hour per user |
| `interactions` | favourites, contact-interaction events | 30 / minute per IP |

## 7. Uploads

`multipart/form-data`. Images: JPG, PNG or WebP, max `uploads.max_image_kb` (default 5 MB), 200–8000 px.
They are decoded and **re-encoded to WebP on the server** (EXIF/GPS metadata removed) and stored in Supabase Storage;
public URLs are returned. Verification and claim documents (PDF/JPG/PNG) go to a **private** bucket and are never
returned by the API. Current limits are available from `GET /settings/app`.

```bash
curl -X POST https://daleelqatar.com/api/v1/me/businesses/sidra-coffee-house/gallery \
  -H "Authorization: Bearer $TOKEN" -H 'Accept: application/json' \
  -F 'images[]=@front.jpg' -F 'images[]=@interior.jpg'
```

## 8. Business-owner workflow

```text
POST /me/businesses            → status "draft"  (incomplete drafts are allowed)
PATCH /me/businesses/{id}      → edit any subset of fields; omitted fields are untouched
POST /me/businesses/{id}/logo  → images
POST /me/businesses/{id}/submit→ "pending" (or 422 workflow_error listing missing_requirements)
          … staff approve / reject in the admin dashboard …
                               → "approved" (published) | "rejected" (fix and submit again)
POST /me/businesses/{id}/verification → request the verified badge (approved listings only)
POST /me/offers                → offers on approved listings (moderated when required)
```

Statuses: `draft`, `pending`, `approved`, `rejected`, `suspended`, `archived`. Owners can only ever see and change their
own listings — every endpoint checks ownership server-side.

Example — create a listing:

```bash
curl -X POST https://daleelqatar.com/api/v1/me/businesses \
  -H "Authorization: Bearer $TOKEN" -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{
    "name_ar": "مقهى اللؤلؤة", "name_en": "Pearl Cafe",
    "category_id": 3, "municipality_id": 1, "area_id": 11,
    "description_ar": "مقهى مختص بالقهوة العربية.", "description_en": "A specialty Arabic coffee house.",
    "phone": "44001122", "website": "pearlcafe.qa",
    "social": { "instagram": "https://instagram.com/pearlcafe" },
    "hours": { "0": { "periods": [{ "opens": "08:00", "closes": "22:00" }] }, "5": { "closed": true } },
    "services": [{ "name_ar": "قهوة", "name_en": "Coffee", "price_from": 15 }]
  }'
```

`201 Created` returns the owner view of the listing, including `status`, `missing_requirements` and `can_submit`.
Local 8-digit phone numbers are normalised to `+974…`, website URLs get `https://`.

## 9. Route inventory

**Auth**

| Method | Path | Auth | Description |
|---|---|---|---|
| POST | `/api/v1/auth/register` | — | Register a new account and receive a token |
| POST | `/api/v1/auth/login` | — | Exchange credentials for a bearer token |
| POST | `/api/v1/auth/forgot-password` | — | Request a password reset link |
| POST | `/api/v1/auth/logout` | Bearer | Revoke the current token |
| POST | `/api/v1/auth/logout-all` | Bearer | Revoke every token of the current user (all devices) |
| POST | `/api/v1/auth/email/resend` | Bearer | Resend the e-mail verification link |

**Profile**

| Method | Path | Auth | Description |
|---|---|---|---|
| GET | `/api/v1/me` | Bearer | Current user |
| PATCH | `/api/v1/me` | Bearer | Update profile and notification preferences |
| DELETE | `/api/v1/me` | Bearer | Delete the account (requires the password) |
| PUT | `/api/v1/me/password` | Bearer | Change password (other tokens are revoked) |

**Directory**

| Method | Path | Auth | Description |
|---|---|---|---|
| GET | `/api/v1/categories` | — | Category tree with published-listing counts |
| GET | `/api/v1/categories/{slug}` | — | A category with its sub-categories |
| GET | `/api/v1/municipalities` | — | Qatar's municipalities and their areas |
| GET | `/api/v1/municipalities/{slug}` | — | One municipality with its areas |
| GET | `/api/v1/businesses` | — | Search the directory |
| GET | `/api/v1/businesses/featured` | — | Featured listings |
| GET | `/api/v1/businesses/{business}` | Optional | Business profile |
| POST | `/api/v1/businesses/{business}/reports` | Optional | Report incorrect or abusive listing information |
| POST | `/api/v1/businesses/{business}/events` | — | Record a contact interaction (owner insights) |

**Offers**

| Method | Path | Auth | Description |
|---|---|---|---|
| GET | `/api/v1/offers` | — | Live offers (expired offers are never returned) |
| GET | `/api/v1/offers/{offer}` | — | A live offer |

**Reviews**

| Method | Path | Auth | Description |
|---|---|---|---|
| GET | `/api/v1/businesses/{business}/reviews` | — | Approved reviews of a business |
| POST | `/api/v1/businesses/{business}/reviews` | Bearer | Write a review (verified e-mail required) |
| POST | `/api/v1/reviews/{review}/report` | Bearer | Report a review |
| GET | `/api/v1/me/reviews` | Bearer | Reviews I wrote (any status) |
| DELETE | `/api/v1/me/reviews/{review}` | Bearer | Delete one of my reviews |
| GET | `/api/v1/me/reviews/received` | Bearer | Published reviews on businesses I own |
| POST | `/api/v1/me/reviews/{review}/reply` | Bearer | Reply to a review on my business (empty value removes the reply) |

**Favorites**

| Method | Path | Auth | Description |
|---|---|---|---|
| GET | `/api/v1/me/favorites` | Bearer | My favourite businesses |
| POST | `/api/v1/me/favorites/{business}` | Bearer | Add to favourites (idempotent) |
| DELETE | `/api/v1/me/favorites/{business}` | Bearer | Remove from favourites (idempotent) |

**Owner — Businesses**

| Method | Path | Auth | Description |
|---|---|---|---|
| GET | `/api/v1/me/businesses` | Bearer | My listings (any status) |
| POST | `/api/v1/me/businesses` | Bearer | Create a listing (saved as draft) |
| GET | `/api/v1/me/businesses/{business}` | Bearer | One of my listings |
| PUT | `/api/v1/me/businesses/{business}` | Bearer | Replace listing details |
| PATCH | `/api/v1/me/businesses/{business}` | Bearer | Partially update listing details (omitted fields are untouched) |
| DELETE | `/api/v1/me/businesses/{business}` | Bearer | Delete a draft, rejected or archived listing |
| POST | `/api/v1/me/businesses/{business}/submit` | Bearer | Submit a draft/rejected listing for review |
| POST | `/api/v1/me/businesses/{business}/archive` | Bearer | Archive a listing (hides it from the directory) |
| POST | `/api/v1/me/businesses/{business}/unarchive` | Bearer | Restore an archived listing to draft |

**Owner — Media**

| Method | Path | Auth | Description |
|---|---|---|---|
| POST | `/api/v1/me/businesses/{business}/logo` | Bearer | Upload or replace the logo |
| DELETE | `/api/v1/me/businesses/{business}/logo` | Bearer | Remove the logo |
| POST | `/api/v1/me/businesses/{business}/cover` | Bearer | Upload or replace the cover image |
| DELETE | `/api/v1/me/businesses/{business}/cover` | Bearer | Remove the cover image |
| POST | `/api/v1/me/businesses/{business}/gallery` | Bearer | Add gallery images |
| PUT | `/api/v1/me/businesses/{business}/gallery/order` | Bearer | Reorder gallery images |
| DELETE | `/api/v1/me/businesses/{business}/gallery/{image}` | Bearer | Delete a gallery image |
| POST | `/api/v1/me/businesses/{business}/verification` | Bearer | Request the verification badge (approved listings only) |

**Owner — Offers**

| Method | Path | Auth | Description |
|---|---|---|---|
| GET | `/api/v1/me/offers` | Bearer | Offers on my listings |
| POST | `/api/v1/me/offers` | Bearer | Create an offer on one of my approved listings |
| GET | `/api/v1/me/offers/{offer}` | Bearer | One of my offers |
| PUT | `/api/v1/me/offers/{offer}` | Bearer | Update an offer |
| PATCH | `/api/v1/me/offers/{offer}` | Bearer | Partially update an offer (content edits may return it to moderation) |
| DELETE | `/api/v1/me/offers/{offer}` | Bearer | Delete an offer |

**Claims**

| Method | Path | Auth | Description |
|---|---|---|---|
| POST | `/api/v1/businesses/{business}/claim` | Bearer | Claim ownership of an unowned listing (verified e-mail required) |
| GET | `/api/v1/me/claims` | Bearer | My ownership claims |

**Notifications**

| Method | Path | Auth | Description |
|---|---|---|---|
| GET | `/api/v1/me/notifications` | Bearer | My notifications (newest first) |
| POST | `/api/v1/me/notifications/{id}/read` | Bearer | Mark one notification as read |
| POST | `/api/v1/me/notifications/read-all` | Bearer | Mark all notifications as read |

**Meta**

| Method | Path | Auth | Description |
|---|---|---|---|
| GET | `/api/v1/openapi.yaml` | — | This OpenAPI specification |
| GET | `/api/v1/settings/app` | — | Public app configuration (brand text, contact, limits) |
| POST | `/api/v1/contact` | Optional | Send a message to the Daleel Qatar team |

## 10. Versioning policy

* The version is part of the path (`/api/v1`). Within v1, changes are **additive only**: new endpoints, new optional
  parameters and new response fields may appear — clients must ignore unknown fields.
* Renaming or removing fields, changing types or semantics requires a new version (`/api/v2`), which will run alongside
  v1 for a deprecation period announced in advance.
* Breaking-change candidates are tracked in the OpenAPI `info.version` and in this document.

## 11. Testing the API locally

```bash
php artisan serve
curl -s 'http://localhost:8000/api/v1/businesses?q=coffee&per_page=5' -H 'Accept-Language: en' | jq
php artisan test --filter='Tests\\Feature\\Api'
```

Import `openapi.yaml` into Postman, Insomnia or Swagger UI to explore every endpoint; for Flutter, a typed client can be
generated with `openapi-generator` (`dart-dio`).
