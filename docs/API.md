# API Reference (v1)

Base URL: `/api/v1` · Auth: `Authorization: Bearer <token>` (Sanctum) · Locale: `Accept-Language: en|bn`.

Responses: single resources and collections use `{"data": ...}` (paginated lists add `links` + `meta`).
Errors: `{"message": "<localised>", "code": "<stable_code>", "context": {...}}`; validation errors are 422 with `errors`.

## Middleware layers

| Layer | Purpose |
|---|---|
| `auth:sanctum` + `active` | Valid, non-expired token; deactivated users are rejected and their token revoked |
| `user.type:buyer` / `user.type:staff` | Hard separation of Buyer and Admin portals |
| `company.approved` | KYC gate — prices, cart, quick order, RFQ, team require an approved company |
| `permission:*` | spatie RBAC per route group |
| Policies | Tenant isolation (company A never sees company B) and sales-manager account scoping |
| Throttles | `login` 5/min per email+IP · `register` 10/h per IP · `password-reset` 3/min · `uploads` 20/min · `api` 180/min |

## Auth (public)

| Method | Path | Notes |
|---|---|---|
| POST | `/auth/register` | Multipart KYC registration (`company.*`, `user.*`, `billing_address.*`, `delivery_addresses[]`, `documents[trade_license|nid|tin_certificate|bin_certificate|other]`, `accept_terms`) → token, company `pending` |
| POST | `/auth/login` | `email`, `password`, `device_name?` → token + user + permissions |
| POST | `/auth/forgot-password` · `/auth/reset-password` | Enumeration-safe reset |
| GET | `/auth/invitations?token=` | Invitation preview |
| POST | `/auth/invitations/accept` | Create sub-user from invitation → token |

## Auth (authenticated)

`GET /auth/me` · `POST /auth/logout` · `POST /auth/logout-all` · `PATCH /auth/me/locale` · `PUT /auth/me/password`
`GET /documents/{id}/download` — KYC document stream (owner company admin or reviewing staff only)

## Catalog (any authenticated user; prices only for staff / approved buyers)

| Method | Path | Notes |
|---|---|---|
| GET | `/catalog/categories` · `/catalog/brands` | Cached, localised |
| GET | `/catalog/products` | `q, category_id, brand_ids[], in_stock, featured, sort(relevance,newest,name,sku,price_asc,price_desc,stock), per_page` — includes `pricing.quote` (at MOQ) + `pricing.tier_table` |
| GET | `/catalog/products/lookup?q=` | Quick-order typeahead (SKU/barcode/EN/BN) |
| GET | `/catalog/products/{slug}` | Detail |
| POST | `/catalog/price-quote` | `lines[{product_id, quantity}]` live recalculation (approved only) |

## Buyer portal (`/buyer`)

| Method | Path | Gate |
|---|---|---|
| GET/PATCH | `/company` | profile (PATCH: buyer admin) |
| POST | `/company/documents` | KYC (re)submission while pending/rejected |
| CRUD | `/addresses` | billing + multiple delivery branches |
| GET/PATCH | `/team/members[/{id}]` | buyer admin · approved |
| GET/POST/DELETE | `/team/invitations[/{id}]` | buyer admin · approved |
| GET/DELETE | `/cart` | approved |
| POST | `/cart/items` | `items[{product_id, quantity}], source?, mode=merge|replace` — atomic, all lines validated |
| PATCH/DELETE | `/cart/items/{id}` | owner only |
| POST | `/quick-order/validate` | `rows[{row?, sku|product_id, quantity}]` → matched/unmatched preview |
| POST | `/quick-order/add-to-cart` | one-click add (fails if any row unmatched/invalid) |
| POST | `/quick-order/import` | CSV/XLSX `file` (sku, quantity) → preview |
| GET | `/quick-order/template` | CSV template |
| GET/POST | `/rfqs` | list / create (`items[{product_id, quantity, target_unit_price?, notes?}]`) |
| GET | `/rfqs/{reference}` | |
| POST | `/rfqs/{reference}/accept|reject|cancel` | accept/reject a quote; cancel before quoting |

## Admin portal (`/admin`, staff only)

| Method | Path | Permission |
|---|---|---|
| GET | `/kyc?status=` · `/kyc/{company}` | `kyc.review` |
| POST | `/kyc/{company}/approve` | `kyc.review` (+ `credit.manage` to set `credit_limit, payment_terms_days, credit_enabled, account_manager_id`) |
| POST | `/kyc/{company}/reject|suspend` | `kyc.review` · `reason` |
| PATCH | `/kyc/{company}/documents/{id}` | `kyc.review` · verify/reject a document |
| GET | `/companies` · `/companies/{company}` · `/companies/{company}/users` | `companies.view` (sales: own + unassigned) |
| PATCH | `/companies/{company}/terms` | `credit.manage` |
| CRUD | `/companies/{company}/prices` | `pricing.manage` (contract prices) |
| CRUD | `/customer-groups` | `pricing.manage` |
| PUT | `/products/{id}/tiers` | `pricing.manage` |
| GET | `/products` · `/products/{id}` · `/categories` · `/brands` · `/units` | `catalog.view` |
| POST/PATCH/DELETE | `/products`, `/categories`, `/brands`, `/units`; `POST /products/{id}/restore` | `catalog.manage` |
| POST | `/products/{id}/stock-adjustments` | `inventory.adjust` · `delta, reason` |
| GET | `/rfqs` · `/rfqs/{reference}` | `rfqs.view` |
| POST | `/rfqs/{reference}/review|quote|decline` | `rfqs.quote` |

Company route keys are the non-enumerable `public_id` (ULID); RFQs use their reference (`RFQ-2026-000001`).
