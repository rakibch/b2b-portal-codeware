# B2B Wholesale Ordering Portal — Architecture

API-first Laravel 13 backend (`backend/`) + Vue 3 SPA (`frontend/`). Bilingual (EN/BN) end to end.

## Key design decisions

| Concern | Decision |
|---|---|
| Tenancy isolation | Every buyer-owned row carries `company_id`; Policies compare `user.company_id` (never request input). External IDs are non-enumerable (company `public_id` ULID; orders/invoices/RFQs bound by document number). |
| Mass assignment | Sensitive columns (KYC status, credit terms, `outstanding_balance`, `stock_quantity`, user `company_id`/`user_type`) are **not** fillable; only services change them via `forceFill()`. |
| Money | `DECIMAL(14,2)` + `App\Support\Money` (brick/math, HALF_UP). No floats. |
| Inventory | Integer base units; decremented only inside `DB::transaction` + `lockForUpdate()` (Phase 3). |
| Credit | `companies.outstanding_balance` is a denormalised cache of the append-only `ledger_entries` running balance, mutated under a company row lock. |
| Ledger | Immutable (model blocks update/delete). Corrections = reversal/adjustment entries. |
| Numbering | `document_sequences` row-locked counter → `ORD-2026-000001`, gap-free and race-safe. |
| Translations | JSON columns + `App\Models\Concerns\HasTranslations` (active locale → `en` fallback → first available). Unsupported locales are dropped on write. |
| Search | Denormalised lowercase `products.search_index` (SKU, barcode, EN+BN names); FULLTEXT on MySQL/PostgreSQL, LIKE fallback. |
| Files | KYC docs & payment slips on a **private** disk, sha256 checksum, served via authorised endpoints only. |
| Polymorphism | `Relation::enforceMorphMap` (stable keys, no class names in DB). |
| Dev safety | `Model::shouldBeStrict()` outside production; destructive DB commands prohibited in production. |

## Pricing rules (PricingEngineService)

Candidate unit prices for a requested quantity — **the lowest applicable price wins** (ties go to the more specific source):

1. `base_price` less the customer group `discount_percent`
2. General volume tier (`customer_group_id IS NULL`) less the group discount
3. Customer-group volume tier (absolute price)
4. Company contract price (`company_product_prices`, within validity window, `quantity >= min_quantity`)

Accepted RFQ prices bypass the engine on RFQ → order conversion (Phase 3).
Quantity rules checked on every quote: active product, MOQ, `(qty − MOQ) % step == 0`, max order qty, stock (optional).
Each quote returns exact 2dp totals (subtotal, discount vs list, VAT, total) plus a `next_tier` hint
("add 8 more to pay ৳830"). Listings are priced in batch with ≤ 2 queries regardless of line count.

## Order, credit & payment flow (Phase 3)

```
checkout ──► [tx] lock company → lock products (id order) → re-price → credit check (limit − ledger balance − uninvoiced pending credit orders)
          ──► create order + item snapshots → conditional stock decrement → status history → clear cart
          ──► (sub-user over limit) approval_status = pending → Buyer Admin approves/rejects
confirm  ──► [tx] invoice (due = today + Net-N for credit) → ledger DEBIT → apply prepaid (gateway) payments
ship     ──► shipments (one Delivery Challan each, partial allowed) → processing → partially_dispatched → dispatched → delivered
pay      ──► bank/cheque slip (pending) → staff verify → ledger CREDIT → allocate (order invoice, then FIFO by due date)
          ──► gateway: signed session → callback verified by driver (HMAC/validation API, amount check, idempotent)
cancel   ──► restore undispatched stock → void invoice → ledger REVERSAL (payments stay as credit on account)
```

- Lock order is always **company → order → products** (deadlock-free). Concurrency verified on PostgreSQL:
  two parallel checkouts for the last 40 units → exactly one order, stock 0.
- Overdue invoices block new credit orders; `invoices:remind-overdue` notifies buyer admins on day 1/7/14/30.
- PDFs: mPDF + Noto Sans Bengali with OTL; Bangla numerals, lakh/crore grouping and amount-in-words
  (`App\Support\Localized`). Challans never contain prices.

## Database (ERD summary)

```
customer_groups 1─* companies *─1 users(account_manager)
companies 1─* users (buyer_admin / buyer_staff)        users ── spatie roles/permissions
companies 1─* company_documents | company_addresses | company_invitations
companies 1─* company_product_prices *─1 products
categories(self-ref) 1─* products *─1 brands, units ; products 1─* product_price_tiers, product_images
users 1─1 carts 1─* cart_items *─1 products
companies 1─* rfqs 1─* rfq_items ; rfqs ─1 orders (converted_order_id)
companies 1─* orders 1─* order_items ; orders 1─* order_status_histories, shipments 1─* shipment_items
orders 1─1 invoices ; payments *─* invoices (payment_allocations) ; credit_notes
companies 1─* ledger_entries (morph source: invoice | payment | credit_note)
announcements, settings, document_sequences, audit_logs, notifications, personal_access_tokens
```

Order status machine: `pending → confirmed → processing → (partially_dispatched →) dispatched → delivered`; `cancelled` reachable from pending/confirmed/processing (stock restored).

## Folder structure

```
b2b-portal-codeware/
├── docs/ARCHITECTURE.md
├── backend/                                   # Laravel 13 (PHP 8.3+)
│   ├── app/
│   │   ├── Enums/                             # OrderStatus (state machine), KycStatus, Role, PaymentMethod, ...
│   │   ├── Models/                            # 31 Eloquent models
│   │   │   └── Concerns/                      # HasTranslations, HasPublicUlid
│   │   ├── Support/Money.php                  # exact decimal arithmetic
│   │   ├── Services/                          # Phase 2–3
│   │   │   ├── Auth/RegistrationService.php
│   │   │   ├── Kyc/KycService.php
│   │   │   ├── Pricing/PricingEngineService.php
│   │   │   ├── Pricing/PriceQuote.php         # DTO
│   │   │   ├── Cart/CartService.php
│   │   │   ├── Cart/QuickOrderImportService.php
│   │   │   ├── Rfq/RfqService.php
│   │   │   ├── Order/OrderService.php
│   │   │   ├── Order/InventoryService.php
│   │   │   ├── Order/FulfilmentService.php
│   │   │   ├── Finance/CreditLedgerService.php
│   │   │   ├── Finance/InvoiceService.php
│   │   │   ├── Payment/PaymentService.php
│   │   │   ├── Payment/Contracts/PaymentGatewayInterface.php
│   │   │   ├── Payment/Gateways/MockGateway.php
│   │   │   ├── Pdf/DocumentPdfService.php
│   │   │   └── Dashboard/{Admin,Buyer}DashboardService.php
│   │   ├── Http/
│   │   │   ├── Controllers/Api/{Auth,Buyer,Admin,Shared}/
│   │   │   ├── Middleware/{SetLocale,EnsureUserIsActive,EnsureCompanyApproved,EnsureUserType}.php
│   │   │   ├── Requests/{Auth,Buyer,Admin}/
│   │   │   └── Resources/
│   │   ├── Policies/                          # Company, Order, Invoice, Rfq, Payment, LedgerEntry, Product, ...
│   │   ├── Imports/QuickOrderImport.php       # maatwebsite/excel
│   │   ├── Exports/{LedgerExport,SalesReportExport}.php
│   │   ├── Notifications/                     # bilingual mail + database
│   │   ├── Console/Commands/{MarkOverdueInvoices,ExpireRfqs}.php
│   │   └── Providers/AppServiceProvider.php
│   ├── config/b2b.php                         # locales, currency, sequences, upload limits
│   ├── database/{migrations,factories,seeders}/
│   ├── lang/{en,bn}/{enums,validation,messages,notifications,pdf}.php
│   ├── resources/views/pdf/{invoice,challan}.blade.php
│   ├── resources/fonts/                       # Hind Siliguri / Noto Sans Bengali TTF for mPDF
│   ├── routes/api.php
│   └── tests/{Feature,Unit}/
└── frontend/                                  # Vue 3 + Vite + Pinia + Tailwind (Phase 4–6)
    ├── index.html
    ├── vite.config.js / tailwind.config.js / postcss.config.js
    └── src/
        ├── main.js / App.vue
        ├── api/{http.js, auth.js, catalog.js, cart.js, orders.js, rfqs.js, ledger.js, admin/*.js}
        ├── i18n/{index.js, locales/en.json, locales/bn.json}
        ├── composables/{useCurrency.js, useToast.js, useDebounce.js, usePagination.js}
        ├── stores/{auth.js, locale.js, cart.js, catalog.js, notifications.js}
        ├── router/{index.js, guards.js, routes.buyer.js, routes.admin.js}
        ├── layouts/{AuthLayout.vue, PortalLayout.vue}
        ├── components/
        │   ├── layout/{AppNavbar,AppSidebar,MobileDrawer,LocaleSwitcher,NotificationBell}.vue
        │   ├── ui/{BaseButton,BaseInput,BaseSelect,BaseModal,StatusBadge,DataTable,Pagination,ToastHost,EmptyState,FileDrop}.vue
        │   ├── catalog/{ProductCard,ProductRow,TierPriceTable,QuantityStepper,StockBadge}.vue
        │   └── charts/SalesChart.vue
        └── views/
            ├── auth/{Login,Register,PendingApproval,AcceptInvitation,ForgotPassword}.vue
            ├── buyer/{Dashboard,Catalog,ProductDetail,QuickOrder,Cart,Checkout,Orders,OrderDetail,Rfqs,RfqDetail,Invoices,Ledger,CompanyUsers,Addresses}.vue
            └── admin/{Dashboard,KycQueue,KycReview,Companies,Products,ProductEditor,Orders,OrderFulfilment,Rfqs,RfqQuote,Payments,Ledger,Users,Settings}.vue
```
