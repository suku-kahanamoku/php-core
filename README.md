# php-core

PHP REST API backed by MySQL. Bearer token authentication, multi-tenant (franchise_code), clean routing — no framework dependencies.

## Requirements

- PHP 8.1+
- MySQL 8.0+
- Apache with `mod_rewrite` (or PHP built-in server)
- Composer

## Setup

```bash
cd php-core
composer install
cp .env.example .env
# edit .env with your DB credentials and FRANCHISE_CODES
```

## Database

```bash
# Create the database and user
mysql -u root -p -e "
  CREATE DATABASE php_core CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  CREATE USER 'admin'@'localhost' IDENTIFIED BY 'admin';
  GRANT ALL PRIVILEGES ON php_core.* TO 'admin'@'localhost';
  FLUSH PRIVILEGES;
"

# Shared repeatable schema (no data deletion or implicit seed)
mysql -u php_core -p php_core < migrations/schema.sql
# Optional original default dataset:
mysql -u php_core -p php_core < migrations/schema_seed.sql
```

Default admin credentials:
- **Email:** `admin@example.com`
- **Password:** `admin123`

## Development server

```bash
php -S localhost:8000
```

For local email testing with Mailpit, configure the tenant-specific values in
the gitignored `.env` (shown here for `collegas`):

```dotenv
COLLEGAS_MAILER_SMTP_HOST=127.0.0.1
COLLEGAS_MAILER_SMTP_PORT=1025
COLLEGAS_MAILER_SMTP_AUTH=false
COLLEGAS_MAILER_SMTP_SECURE=none
```

Mailpit then captures messages at `http://127.0.0.1:8025` without sending them
to real recipients. Production must use authenticated SMTP with TLS.

## Authentication

The API requires **X-Internal-Key** for application authentication (except the two Rokid POST endpoints described below). User authentication additionally uses a **Bearer token**. Cookies and sessions are not used.

**Login and get a token:**
```bash
curl -X POST http://localhost/php/php-core/api/auth/login \
  -H "Content-Type: application/json" \
  -H "X-Internal-Key: $INTERNAL_API_KEY" \
  -d '{"email":"admin@example.com","password":"admin123"}'
```

Response:
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "token": "a3f9c2...",
    "expires_at": "2026-06-03T10:00:00",
    "id": 1,
    "first_name": "Admin",
    "last_name": "User",
    "email": "admin@example.com",
    "role": "admin"
  }
}
```

**Use the token in subsequent requests:**
```bash
curl http://localhost/php/php-core/api/products \
  -H "X-Internal-Key: $INTERNAL_API_KEY" \
  -H "Authorization: Bearer a3f9c2..."
```

Tokens expire after 24 hours (configurable via `TOKEN_LIFETIME` in `.env`). Logout invalidates the token server-side.

## Rokid OpenAI Realtime session

`POST /api/openai/realtime-session` creates a short-lived client secret for the
Rokid Android application. The main `OPENAI_API_KEY` remains server-side; the
mobile client uses the returned secret to connect directly to OpenAI Realtime.
The endpoint requires `X-Rokid-Key`, uses the tenant resolved from the request
host and is rate-limited. See
[`src/Modules/OpenAi/README.md`](src/Modules/OpenAi/README.md).

The Realtime model is configured through `OPENAI_REALTIME_MODEL` (default
`gpt-realtime`). The session prompt is tenant-neutral; tenant-specific catalog
data is resolved from the trusted request host rather than hard-coded branding.
The compact, sectioned system instructions and tool descriptions are in English,
while the analyzed sales conversation remains Czech.

The same scoped credential protects `POST /api/openai/tool`, an allowlisted
read-only bridge between Realtime, OpenAI Responses file search and current
published product details. It does not expose CRM write operations.

The Realtime session silently analyzes the ongoing dialogue and queries the
Vector Store only after a concrete product category and either confirmed price
intent or a trusted normalized customer profile are known. A generic product
request, cosmetics, or gift intent is not a category. The current session has
no trusted profile input, so category plus price intent is the effective gate.
Realtime passes the structured active need to an OpenAI Responses model. That
model uses hosted `file_search`, evaluates requirements, preferences and budget,
and returns one evidence-backed product ID. It marks an exact match as `exact`; otherwise it returns the closest `nearest` product with a short Czech reason, so a completed gate never produces an empty business result. PHP neither filters nor ranks
candidates and never chooses a recommended product. The model never asks clarification questions or
generates sales, upsell or cross-sell arguments; while the gate is incomplete,
the required `continue_listening` tool ends the turn without free text and updates only
the salesperson checklist with the confirmed category and price intent. A move-on, rejection, or changed requirement sets `replace_current_product`;
Android converts it to the current `excluded_product_id`, and Responses file search
applies a one-request `product_id != ID` metadata filter. Previously displayed
products are not permanently excluded and may be selected again when the customer
returns to them. After Responses chooses an ID
from file-search evidence, Realtime calls `get_product`; PHP only loads its current published
catalog detail for Android. `migrations/fann_seed.sql` contains the consolidated
FAnn demo catalogue and structured source metadata. Reapplying it preserves
existing edited products.

The repeatable FAnn catalogue importer stores the six current top-level shop
categories and up to 50 public product variants per category in tenant `fann`:

```bash
php8.2 scripts/import_fann_catalog.php --limit=50 --concurrency=4
```

It reads authoritative Product JSON-LD plus explicit detail attributes,
deduplicates variants shared by categories, preserves unrelated product JSON,
and never deletes catalogue rows. See `src/Modules/FannCatalog/README.md`.

The OpenAI Vector Store provides product knowledge to the Responses model. PHP
only synchronizes published catalog documents and securely proxies the Responses
request, including an optional one-request current-product exclusion; it contains no fallback recommendation algorithm. Install
`migrations/schema.sql`, synchronize the selected tenant,
and only then enable the runtime lookup:

```bash
php8.2 scripts/sync_openai_vector_store.php --tenant=fann
```

```dotenv
OPENAI_VECTOR_STORE_ENABLED=true
OPENAI_RECOMMENDATION_MODEL=gpt-5.6-terra
```

The sync is incremental and can follow every catalogue import or run hourly.
See `src/Modules/OpenAi/README.md` for operation and unavailable-index behavior.

The available offline and disposable-database checks can be run through Composer:

```bash
composer lint
composer test:http
composer test:java-gateway
composer test
```

GitHub Actions runs `composer validate --strict`, `composer lint` and
`composer test:http` with PHP 8.2 for pushes and pull requests. The remaining
commands create their own temporary MySQL instances and can be run locally or
added to a dedicated CI job.

This is intentionally an HTTPS session broker, not a PHP WebSocket daemon.
CGI/FastCGI requests do not provide a reliable long-running WebSocket process.

Note: `POST /api/auth/logout` requires the `Authorization: Bearer <token>` header; calls without a valid token will be rejected with 401.

## Multi-tenancy

Every request is scoped to a `franchise_code` resolved from the frontend host. Allowed host-to-tenant mappings are defined in `.env` as a comma-separated list:

```
FRANCHISE_CODES=zoo.localhost:zoo,zoo-crm.netlify.app:zoo,vinozezajeci.cz:zajeci,collegas.netlify.app:collegas,collegas.cz:collegas,www.collegas.cz:collegas
```

Requests from unknown hosts return `403 Forbidden`. Do not map the generic PHP
backend hostname to a tenant. Trusted Nuxt server proxies pass their configured
frontend hostname in `X-Forwarded-Host`.

Every API request requires the same non-public `INTERNAL_API_KEY` in PHP and
the calling server, sent as `X-Internal-Key`. `api/bootstrap.php` runs
`InternalAuthMiddleware` after tenant resolution and before database/API
initialization. A missing, empty or incorrect key returns 401, even with an admin
Bearer token. The only application-key exceptions are POST
`/api/openai/realtime-session` and `/api/openai/tool`, which require
`X-Rokid-Key` instead. OPTIONS returns 204 after tenant validation without
executing handlers or opening the database.

The internal key is not a universal administrator credential. It also permits
read-only access to users, roles, and addresses for trusted server proxies, but
it does not unlock orders, invoice reads, files, or template previews. Every
request must still resolve a valid tenant. Never send the key to a browser or
store it in a public frontend runtime variable.

CORS accepts any origin with `Access-Control-Allow-Origin: *`, without an
origin allowlist or credentialed cookies. Browsers call their frontend server;
that proxy adds the internal key and, where required, the user's Bearer token.
CORS does not replace application authentication, tenant resolution or user
permissions. Internal keys must never be embedded in browser or Android code.

### Tenant boundaries in database access

`Request::resolveCode()` resolves a tenant before API modules initialize their
repositories. `Database` itself is an unrestricted PDO wrapper: it does not
inject or validate `franchise_code`. Primary entity repositories apply tenant
filters explicitly; child tables such as order/invoice items, product links and
profile questions are often accessed by a parent ID already checked by a service.

Some helper writes also use IDs alone (`UserRepository::touchLastLogin`, token
creation/revocation, password-reset bookkeeping and FAnn category migration).
They rely on their caller having selected the user or parent in the correct
tenant. Several joins (for example user-to-role and address/order-to-user) rely
on valid stored relationships rather than checking both tenants in the join.
These are not independent tenant guards and must not be reused with unchecked IDs.

`FRANCHISE_CODES` selects a tenant; it does not authenticate the caller.
`X-Forwarded-Host` / `X-Original-Host` select the tenant, then the common
middleware authenticates the calling application before any database access.
Endpoints described as public mean no user Bearer token is required; the
application key remains mandatory. Private endpoints retain their user
Bearer/role rules. CLI imports use an
explicit tenant instead of HTTP host resolution. Migration SQL and the test
cleanup helper can operate across tenants; never run the test cleanup against
production.

### Existing database / production migration

`migrations/schema.sql` now contains only repeatable shared DDL. It creates
missing tables, columns, indexes and constraints without deleting existing data.
Apply it before the selected `<project>_schema.sql`. Bootstrap data are optional
and live in `<project>_seed.sql`; `schema_seed.sql` retains the historical default
Zaječí dataset. Never run every product seed indiscriminately on a live database.

Installation order and available product schemas are documented in
[`migrations/README.md`](migrations/README.md).

```bash
mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p "$DB_NAME" < migrations/schema.sql
# Example product:
mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p "$DB_NAME" < migrations/etymolog_schema.sql
```

### Zoo CRM tenant

The companion Nuxt application in `nuxt/zoo` uses the `zoo` tenant. For a local
installation, keep `zoo.localhost:zoo` in `FRANCHISE_CODES`, then seed its roles,
administrator and animal categories:

```bash
mysql -u php_core -p php_core < migrations/schema.sql
mysql -u php_core -p php_core < migrations/zoo_schema.sql
mysql -u php_core -p php_core < migrations/zoo_seed.sql
```

The administrator is `admin@zoo.local` with password `admin`.

The complete customer-profile schema, columns, keys, and Mermaid relationship
diagram are documented in [`src/Modules/CustomerProfile/README.md`](src/Modules/CustomerProfile/README.md).

### FAnn CRM tenant

The companion application in `nuxt/fann` uses the `fann` tenant. Seed its initial
catalogue with final category and profile relations:

```bash
mysql -u php_core -p php_core < migrations/schema.sql
mysql -u php_core -p php_core < migrations/fann_schema.sql
mysql -u php_core -p php_core < migrations/fann_seed.sql
```

The administrator is `admin@fann.cz` with password `admin`.

## Project structure

```
php-core/
├── bootstrap.php          # Autoload, .env, CORS headers, error handling
├── .env.example
├── composer.json
├── src/
│   └── Modules/
│       └── CustomerProfile/
│           └── README.md                 # module guide + ER/UML diagram + columns
├── migrations/
│   ├── README.md                 # installation order and product schemas
│   ├── schema.sql                # repeatable shared DDL, no seed data
│   ├── schema_seed.sql           # optional default Zaječí data
│   ├── <project>_schema.sql      # Etymolog, SRY, TRAM, Zoo, FAnn, Zaječí
│   └── <project>_seed.sql        # insert missing bootstrap/reference data
├── pages/
│   ├── db-schema.html     # Mermaid ER diagram
│   ├── db-table.html      # HTML schema viewer with FK table
│   ├── flows.html         # Sequence diagrams for all endpoints
│   └── api-reference.html # Interactive API reference
├── tests/
│   └── api_test.php       # CLI test runner (785 tests)
├── api/
│   ├── .htaccess          # Routes /api/<module>/... to module index.php
│   ├── index.php          # Fallback (404)
│   ├── auth/index.php
│   ├── roles/index.php
│   ├── users/index.php
│   ├── customer-profiles/index.php
│   ├── address/index.php
│   ├── categories/index.php
│   ├── products/index.php
│   ├── texts/index.php
│   ├── enumerations/index.php
│   ├── orders/index.php
│   ├── invoices/index.php
│   └── files/index.php
└── src/
    ├── Middleware/
    │   └── CorsMiddleware.php        # Applied globally in bootstrap.php
    └── Modules/
        ├── Auth/
        │   ├── Auth.php              # Bearer token auth (instance class)
        │   ├── UserTokenRepository.php  # user_token DB operations
        │   ├── AuthApi.php
        │   └── AuthService.php
        ├── Database/
        │   └── Database.php          # PDO singleton with query helpers
        ├── Router/
        │   ├── Request.php           # HTTP request parsing + franchise resolution
        │   ├── Response.php          # JSON response helpers
        │   └── Router.php            # Regex router with middleware support
        ├── Validator/
        │   └── Validator.php
        ├── CustomerProfile/           # definitions, questions, objections, preferences
        ├── OpenAi/                    # Rokid Realtime client-secret broker
        └── <Module>/                 # Address, Category, Enumeration, File,
            ├── <Module>Repository.php  #   Invoice, Order, Product, Role, Text, User
            ├── <Module>Service.php
            ├── <Module>Api.php
            └── tests/
```

## Running tests

```bash
php tests/api_test.php

# Against a different base URL:
php tests/api_test.php http://myserver.com/api
```

## Endpoints overview

### Auth
| Method | Path | Auth | Description |
|--------|------|------|-------------|
| POST | `/auth/login` | public | Login → returns Bearer token |
| POST | `/auth/logout` | required | Logout (invalidates token) |
| GET  | `/auth/me` | required | Current user info |
| POST | `/auth/register` | public | Register new user |
| POST | `/auth/change-password` | required | Change password |
| POST | `/auth/reset-password` | public | Request one-time password reset |
| POST | `/auth/complete-reset` | public | Complete reset with token |
| POST | `/auth/oauth` | internal | Trusted OAuth handoff |

### Roles
| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET    | `/roles` | admin or internal | List roles |
| POST   | `/roles` | admin | Create role |
| GET    | `/roles/:id` | admin or internal | Get role |
| PATCH  | `/roles/:id` | admin | Partial update |
| PUT    | `/roles/:id` | admin | Full replace |
| DELETE | `/roles/:id` | admin | Delete (fails if users assigned) |

### Users
| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET    | `/users` | admin or internal | List users |
| POST   | `/users` | admin | Create user |
| GET    | `/users/:id` | self, admin, or internal | Get user |
| PATCH  | `/users/:id` | self or admin | Update user; admin may synchronize `profiles` |
| PUT    | `/users/:id` | self or admin | Replace user; admin may synchronize `profiles` |
| DELETE | `/users/:id` | admin | Delete user |
| GET    | `/users/:userId/address` | self, admin, or internal | User's addresses |

### Customer profiles

Profile definitions are stored in `customer_profile`. User assignments use the
M:N table `user_customer_profile` with a numeric position. Product suitability
uses `product_customer_profile_probability`; the API exposes those rows as the
`profile_probabilities` field on a product.

See [`src/Modules/CustomerProfile/README.md`](src/Modules/CustomerProfile/README.md) for the diagram,
all table columns, primary and foreign keys, uniqueness rules, and examples.

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | `/customer-profiles` | admin | List tenant profiles with questions, objections and preferences |
| POST | `/customer-profiles` | admin | Create profile |
| GET | `/customer-profiles/:id` | public | Get a published profile; unpublished profiles require admin |
| PATCH / PUT | `/customer-profiles/:id` | admin | Update profile and its child rows |
| DELETE | `/customer-profiles/:id` | admin | Soft-delete profile |

### Address
| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET    | `/address` | admin or internal | List all tenant addresses |
| POST   | `/address` | required | Create address |
| GET    | `/address/:id` | owner, admin, or internal | Get address |
| PATCH  | `/address/:id` | owner or admin | Partial update |
| PUT    | `/address/:id` | owner or admin | Full replace |
| DELETE | `/address/:id` | owner or admin | Delete address |

### Categories
| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET    | `/categories` | public | List published categories (admin: all) |
| POST   | `/categories` | admin | Create |
| GET    | `/categories/:id` | public | Get published category (admin: any) |
| PATCH  | `/categories/:id` | admin | Partial update |
| PUT    | `/categories/:id` | admin | Full replace |
| DELETE | `/categories/:id` | admin | Delete (fails if has active products) |

### Products
| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET    | `/products` | public | List published products (admin: all) |
| POST   | `/products` | admin | Create |
| GET    | `/products/:id` | public | Get published product (admin: any) |
| PATCH  | `/products/:id` | admin | Partial update |
| PUT    | `/products/:id` | admin | Full replace |
| DELETE | `/products/:id` | admin | Soft delete / `?force=true` for hard delete |
| PATCH  | `/products/:id/stock` | admin | Adjust stock quantity |

### Texts (CMS)
| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET    | `/texts` | public | List published public text fields (admin: all) |
| POST   | `/texts` | admin | Create |
| GET    | `/texts/by-key/:syscode` | public | Get by syscode + language |
| GET    | `/texts/:id` | public | Get by ID |
| PATCH  | `/texts/:id` | admin | Partial update |
| PUT    | `/texts/:id` | admin | Full replace |
| DELETE | `/texts/:id` | admin | Delete |

### Enumerations
| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET    | `/enumerations` | public | List allowed public values (admin: all) |
| GET    | `/enumerations/types` | public | List allowed public types (admin: all) |
| POST   | `/enumerations` | admin | Create |
| GET    | `/enumerations/:id` | public | Get by ID |
| PATCH  | `/enumerations/:id` | admin | Partial update |
| PUT    | `/enumerations/:id` | admin | Full replace |
| DELETE | `/enumerations/:id` | admin | Delete |

### Orders
| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET    | `/orders` | required | My orders (admin: all) |
| POST   | `/orders` | public | Create order |
| GET    | `/orders/:id` | required | Get order with items |
| PATCH  | `/orders/:id/status` | admin | Update status |
| DELETE | `/orders/:id` | admin | Soft delete / `?force=true` for hard delete |

### Invoices
| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET    | `/invoices` | required | My invoices (admin: all) |
| POST   | `/invoices` | admin or internal | Generate from order |
| GET    | `/invoices/:id` | owner or admin | Get invoice with items |
| PATCH  | `/invoices/:id/status` | admin | Update status |
| PATCH  | `/invoices/:id/files` | admin | Sync attached files |
| DELETE | `/invoices/:id` | admin | Soft delete / `?force=true` for hard delete |

### Files
| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET    | `/files` | required | Own committed files (admin: all) |
| GET    | `/files/:id` | owner or admin | Get file metadata |
| GET    | `/files/content?path=...` | authorized | Serve committed content |
| GET    | `/files/temp?path=...` | owner | Serve caller's temporary upload |
| POST   | `/files/upload` | required | Phase 1 — save to temp, return path |
| POST   | `/files/commit` | required | Phase 2 — move to permanent, insert DB |
| DELETE | `/files/:id` | admin | Soft delete / `?force=true` for hard delete |

### Mailer

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| POST | `/mailer` | public, rate-limited | Validated contact form |
| POST | `/mailer/newsletter` | public, rate-limited | Newsletter subscription |
| POST | `/mailer/send` | admin or internal | Generic transactional template |
| GET | `/mailer/test?email=...` | admin or internal | Send test message |
| GET | `/mailer/list` | admin or internal | List tenant templates |

### Templater

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | `/templater?template=...` | admin | Render sanitized template preview |

## Soft delete vs. hard delete

All DELETE endpoints support an optional `?force=true` query parameter:

| `?force=true` | Behaviour |
|---|---|
| absent (default) | **Soft delete** — sets `deleted=1` in DB; record remains in DB but `GET` returns 404 |
| present | **Hard delete** — permanent removal from DB (and physical file for `/files`) |

```
DELETE /products/5             # soft delete
DELETE /products/5?force=true  # hard delete
```

## Database schema

The fresh schema contains 26 tables:

`enumeration`, `customer_profile`, `customer_profile_question`,
`customer_profile_objection`, `customer_profile_preference`, `role`, `user`,
`user_customer_profile`, `address`, `user_token`, `category`, `product`,
`product_customer_profile_probability`, `product_alternative`, `product_category`, `product_file`,
`text`, `order`, `order_item`, `invoice`, `invoice_item`, `invoice_file`, `file`,
`oauth_identity`, `password_reset_token`, and `api_rate_limit`.

### Foreign-key relationships

| Source | Column | Target | Cardinality | On parent delete |
|---|---|---|---|---|
| `user` | `role_id` | `role.id` | N:1 | `RESTRICT` |
| `customer_profile_question` | `customer_profile_id` | `customer_profile.id` | N:1 | `CASCADE` |
| `customer_profile_objection` | `customer_profile_id` | `customer_profile.id` | N:1 | `CASCADE` |
| `customer_profile_preference` | `customer_profile_id` | `customer_profile.id` | N:1 | `CASCADE` |
| `user_customer_profile` | `user_id` | `user.id` | N:1, part of user/profile M:N | `CASCADE` |
| `user_customer_profile` | `customer_profile_id` | `customer_profile.id` | N:1, part of user/profile M:N | `CASCADE` |
| `address` | `user_id` | `user.id` | N:1 | `CASCADE` |
| `user_token` | `user_id` | `user.id` | N:1 | `CASCADE` |
| `category` | `parent_id` | `category.id` | tree/self-reference | `SET NULL` |
| `product_customer_profile_probability` | `product_id` | `product.id` | N:1, part of product/profile M:N | `CASCADE` |
| `product_customer_profile_probability` | `customer_profile_id` | `customer_profile.id` | N:1, part of product/profile M:N | `CASCADE` |
| `product_alternative` | `product_id` | `product.id` | N:1, source product | `CASCADE` |
| `product_alternative` | `alternative_product_id` | `product.id` | N:1, alternative product | `CASCADE` |
| `product_category` | `product_id` | `product.id` | N:1, part of product/category M:N | `CASCADE` |
| `product_category` | `category_id` | `category.id` | N:1, part of product/category M:N | `CASCADE` |
| `product_file` | `product_id` | `product.id` | N:1, part of product/file M:N | `CASCADE` |
| `product_file` | `file_id` | `file.id` | N:1, part of product/file M:N | `CASCADE` |
| `order` | `user_id` | `user.id` | N:1, optional | `SET NULL` |
| `order` | `shipping_address_id` | `address.id` | N:1, optional | `SET NULL` |
| `order` | `billing_address_id` | `address.id` | N:1, optional | `SET NULL` |
| `order_item` | `order_id` | `order.id` | N:1 | `CASCADE` |
| `order_item` | `product_id` | `product.id` | N:1, optional snapshot source | `SET NULL` |
| `invoice` | `order_id` | `order.id` | N:1, optional | `SET NULL` |
| `invoice_item` | `invoice_id` | `invoice.id` | N:1 | `CASCADE` |
| `invoice_file` | `invoice_id` | `invoice.id` | N:1, part of invoice/file M:N | `CASCADE` |
| `invoice_file` | `file_id` | `file.id` | N:1, part of invoice/file M:N | `CASCADE` |
| `oauth_identity` | `user_id` | `user.id` | N:1 | `CASCADE` |
| `password_reset_token` | `user_id` | `user.id` | N:1 | `CASCADE` |

`file.user_id` is a tenant-checked logical owner reference. It is indexed but
does not currently have a database foreign-key constraint. `franchise_code`
scopes entity and relation rows to a tenant; repositories validate tenant
agreement when writing cross-table relationships.

### Important columns and models

- **`user_customer_profile.position`** orders multiple customer profiles for one user; `1` is first and (`user_id`, `position`) is unique.
- **`product_customer_profile_probability`** stores the single `probability_percent` value for a product/profile pair; applications treat values from 30 % as likely products.
- **`product_alternative`** stores directed, ordered links between existing products; (`product_id`, `position`) is unique and self-links are forbidden.
- **`product_category`**, **`product_file`**, and **`invoice_file`** are M:N junction tables.
- **`category.syscode`** is the machine-readable identifier used by the `category_syscode` filter.
- **`product.data`** stores project-specific JSON attributes and supports dot-notation filters such as `q={"data.year":{"value":2022}}`.
- **`order.customer`** is the immutable guest customer and address snapshot.
- **`oauth_identity`**, **`password_reset_token`**, and **`api_rate_limit`** support authentication and abuse prevention.
- **`deleted`** is the soft-delete flag on entity tables that support soft deletion; junction and cascade-owned child tables do not all contain it.

The profile-focused diagram with every related table column is in
[`src/Modules/CustomerProfile/README.md`](src/Modules/CustomerProfile/README.md). The complete executable
schema remains [`migrations/schema.sql`](migrations/schema.sql).

### Prasentace – kontaktní formulář

Astro Prasentace používá existující `POST /mailer/send` s interním klíčem a
`X-Forwarded-Host` podle domény webu. Aktuální web běží na
`https://prasentace.netlify.app`, produkční doména bude `https://www.prasentace.cz`.
V `FRANCHISE_CODES` jsou `prasentace.netlify.app:prasentace`,
`www.prasentace.cz:prasentace` a `prasentace.cz:prasentace`; všechny vybírají šablonu
`emails/prasentace/contact-form-admin.php`. Příjemce a odesílatel je
`info@prasentace.cz`, jméno odesílatele `Prasentace`. Nastavení SMTP lze oddělit
pomocí `PRASENTACE_MAILER_*` stejným mechanismem jako `COLLEGAS_MAILER_*`; pro
lokální vývoj jsou převzaty parametry transportu Collegas. Žádné další endpointy
ani změny oprávnění nejsou potřeba. Produkční nasazení vyžaduje přenos šablony
a odpovídající nastavení prostředí. Mapování domén v `FRANCHISE_CODES` je nutné
přenést také do prostředí nasazeného PHP; lokální `.env` se do Gitu neukládá.
CORS přijímá všechny originy a nevyžaduje samostatný seznam domén.

### Authentication verification and rollout

Run `php tests/test_internal_auth.php` for offline middleware and entrypoint
regression tests (no database, email or external API calls). Deploy compatible
frontend server proxies with their server-only key before enabling the new PHP
middleware. JSON requests, login/session hydration, uploads and downloads all
need that header. Zoo/FAnn private admin reads must require an admin session
before forwarding the internal key. Published profile detail stays available
through the FAnn server for Rokid glasses. Existing Prasentace mail transport
already sends the internal key. No production deployment is performed by tests.


## TRAM transport backend

The [Transport module](src/Modules/Transport/README.md) is an authenticated,
tenant-bound JSON gateway to the [Java transport services](../../java/README.md).
Java owns GTFS/OSM collection, graph building, catalogues, planning and realtime.
Configure `TRANSPORT_JAVA_ENABLED`, `TRANSPORT_JAVA_TENANT`, `TRANSPORT_JAVA_URL`
and the server-only `TRANSPORT_JAVA_TOKEN`; preserve internal-key authentication
and `FRANCHISE_CODES`. PHP transport adapters/imports/CLI workers have been removed.
The gateway authenticates from server configuration and needs no SQL connection
or tables; it does not use the SQL rate limiter. Java backpressure is forwarded. Legacy TRAM SQL scripts have been removed from the project;
this change does not apply or drop existing application database structures/data.

### Sdílená odchozí komunikace

Všechny HTTP integrace a SMTP používají [HttpModule](src/Modules/Http/README.md).
Nové integrace injektují `Http\Contracts\HttpClient`; přímé cURL/network volání
se do doménových modulů nepřidává. Testy: `bash scripts/test-http.sh`.

### Etymolog

The [Etymolog module](src/Modules/Etymolog/README.md) adds a tenant-scoped editorial
name/etymology catalog with existing Bearer authentication, complete domain CRUD,
citations and historical variants. Install `migrations/etymolog_schema.sql`
and optionally `migrations/etymolog_seed.sql` for the initial
`etymolog` roles and Czech import jobs. The CLI `scripts/etymolog-sync.php`
imports CC0 Wikidata records in bounded, resumable batches while preserving
editorial changes. Tests use an isolated MySQL: `bash scripts/test-etymolog.sh`.

The additive `migrations/etymolog_schema.sql` extension adds shared
stories, reviewed name associations and Wikisource provenance. Apply the optional
`etymolog_seed.sql` seed for a Czech folklore sync job.
Imported narratives remain drafts and source updates preserve editorial changes.

Further licensed sources and synchronizers (Wiktionary, Poland PESEL and ČSÚ)
are documented in [Etymolog source research](docs/etymolog-sources.md). Apply
`etymolog_schema.sql` contains the complete model; `etymolog_seed.sql` contains
all provider rules. All sync providers preserve editorial changes and record provenance.


Etymolog cultural content must originate from a cited website; there is no AI
story generation. Apply the additive `etymolog_schema.sql` migration
and optional `etymolog_seed.sql` for traditions, weather lore, and calendar days.
See the [module contract](src/Modules/Etymolog/README.md) for verbatim-publication
validation and the Erben / Czech name-day calendar synchronizers.
