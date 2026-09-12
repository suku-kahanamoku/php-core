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

# Fresh/development database only (destructive: drops and recreates tables)
mysql -u php_core -p php_core < migrations/schema.sql
```

Default admin credentials:
- **Email:** `admin@example.com`
- **Password:** `admin123`

## Development server

```bash
php -S localhost:8000
```

## Authentication

The API uses **Bearer token** authentication. Cookies and sessions are not used.

**Login and get a token:**
```bash
curl -X POST http://localhost/php/php-core/api/auth/login \
  -H "Content-Type: application/json" \
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
  -H "Authorization: Bearer a3f9c2..."
```

Tokens expire after 24 hours (configurable via `TOKEN_LIFETIME` in `.env`). Logout invalidates the token server-side.

Note: `POST /api/auth/logout` requires the `Authorization: Bearer <token>` header; calls without a valid token will be rejected with 401.

## Multi-tenancy

Every request is scoped to a `franchise_code` resolved from the frontend host. Allowed host-to-tenant mappings are defined in `.env` as a comma-separated list:

```
FRANCHISE_CODES=zoo.localhost:zoo,zoo-crm.netlify.app:zoo,vinozezajeci.cz:zajeci
```

Requests from unknown hosts return `403 Forbidden`. Do not map the generic PHP
backend hostname to a tenant. Trusted Nuxt server proxies pass their configured
frontend hostname in `X-Forwarded-Host`.

Server-to-server operations (OAuth handoff, generic transactional mail and
invoice creation) additionally require the same non-public `INTERNAL_API_KEY`
in PHP and the corresponding Nuxt deployment.

The internal key is not a universal administrator credential. It also permits
read-only access to users, roles, and addresses for trusted server proxies, but
it does not unlock orders, invoice reads, files, or template previews. Every
request must still resolve a valid tenant. Never send the key to a browser or
store it in a public frontend runtime variable.

### Existing database / production migration

Never run `migrations/schema.sql` on an existing database. It contains `DROP
TABLE` statements and is intended only for a new local installation.

The additive security migration is idempotent and preserves current business
rows:

```bash
mysqldump --single-transaction --quick --skip-lock-tables --no-tablespaces \
  -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p "$DB_NAME" \
  > "../${DB_NAME}-before-security-$(date +%Y%m%d-%H%M%S).sql"

mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p "$DB_NAME" \
  < migrations/20260906_security_hardening.sql
```

Run the migration locally first, verify its final row reports `1, 1, 1, 3`,
then run the same committed file in production before deploying the PHP code.

### Zoo CRM tenant

The companion Nuxt application in `nuxt/zoo` uses the `zoo` tenant. For a local
installation, keep `zoo.localhost:zoo` in `FRANCHISE_CODES`, then seed its roles,
administrator and animal categories:

```bash
mysql -u php_core -p php_core < migrations/zoo_seed.sql
mysql -u php_core -p php_core < migrations/20260912_customer_profiles.sql
```

The administrator is `admin@zoo.local` with password `admin`.

## Project structure

```
php-core/
├── bootstrap.php          # Autoload, .env, CORS headers, error handling
├── .env.example
├── composer.json
├── migrations/
│   ├── schema.sql                         # destructive fresh schema + seed
│   └── 20260906_security_hardening.sql   # additive production migration
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
M:N table `user_customer_profile` with a numeric priority. Product suitability
uses `product_customer_profile_probability`; the API exposes those rows as the
`profile_probabilities` field on a product.

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | `/customer-profiles` | admin | List tenant profiles with questions, objections and preferences |
| POST | `/customer-profiles` | admin | Create profile |
| GET | `/customer-profiles/:id` | admin | Get profile |
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

Tables (19): `enumeration`, `role`, `user`, `address`, `user_token`, `category`, `product`, `product_category`, `product_file`, `text`, `order`, `order_item`, `invoice`, `invoice_item`, `invoice_file`, `file`, `oauth_identity`, `password_reset_token`, `api_rate_limit`

- **`product_category`** — M:N pivot: products ↔ categories.
- **`product_file`** — M:N pivot: products ↔ files.
- **`invoice_file`** — M:N pivot: invoices ↔ files.
- **`category.syscode`** — machine-readable identifier for filtering via `category_syscode` query param.
- **`product.data`** — flexible JSON column. Filter via dot-notation: `q={"data.year":{"value":2022}}`.
- **`order.customer`** — immutable JSON snapshot used by guest checkout.
- **`file.user_id`** — owner of an uploaded/committed file.
- **`oauth_identity`**, **`password_reset_token`**, **`api_rate_limit`** — identity binding and abuse protection.
- **`deleted`** — soft-delete flag (`TINYINT(1) DEFAULT 0`) present on every entity table.
