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

# Run migration (creates all tables + seeds enumerations + admin user)
php bin/migrate.php
```

Default admin credentials:
- **Email:** `admin@example.com`
- **Password:** `12345678` *(change immediately in production)*

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
  -d '{"email":"admin@example.com","password":"password"}'
```

Response:
```json
{
  "success": true,
  "data": {
    "token": "a3f9c2...",
    "id": 1,
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

## Multi-tenancy

Every request is scoped to a `franchise_code` resolved from the HTTP `Host` header. Allowed codes are defined in `.env` as a comma-separated list:

```
FRANCHISE_CODES=default,shop1,shop2
```

Requests from unknown hosts return `403 Forbidden`.

## Project structure

```
php-core/
├── bootstrap.php          # Autoload, .env, CORS headers, error handling
├── .env.example
├── composer.json
├── bin/
│   └── migrate.php        # DB migration runner
├── migrations/
│   └── schema.sql         # Full schema + seed data
├── pages/
│   ├── db-schema.html     # Mermaid ER diagram
│   ├── db-table.html      # HTML schema viewer with FK table
│   └── flows.html         # Sequence diagrams for all endpoints
├── tests/
│   └── api_test.php       # CLI test runner (364 tests)
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
│   └── invoices/index.php
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
        └── <Module>/                 # Address, Category, Enumeration, Invoice,
            ├── <Module>Repository.php  #   Order, Product, Role, Text, User
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

### Roles *(admin)*
| Method | Path | Description |
|--------|------|-------------|
| GET    | `/roles` | List roles |
| POST   | `/roles` | Create role |
| GET    | `/roles/:id` | Get role |
| PATCH  | `/roles/:id` | Partial update |
| PUT    | `/roles/:id` | Full replace |
| DELETE | `/roles/:id` | Delete (fails if users assigned) |

### Users *(admin)*
| Method | Path | Description |
|--------|------|-------------|
| GET    | `/users` | List users |
| POST   | `/users` | Create user |
| GET    | `/users/:id` | Get user |
| PATCH  | `/users/:id` | Partial update |
| PUT    | `/users/:id` | Full replace |
| DELETE | `/users/:id` | Delete user |
| GET    | `/users/:userId/address` | User's addresses |

### Address
| Method | Path | Auth | Description |
|--------|------|------|-------------|
| POST   | `/address` | required | Create address |
| GET    | `/address/:id` | required | Get address |
| PATCH  | `/address/:id` | required | Partial update |
| PUT    | `/address/:id` | required | Full replace |
| DELETE | `/address/:id` | required | Delete address |

### Categories
| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET    | `/categories` | public | List (tree structure) |
| POST   | `/categories` | admin | Create |
| GET    | `/categories/:id` | public | Get with products |
| PATCH  | `/categories/:id` | admin | Partial update |
| PUT    | `/categories/:id` | admin | Full replace |
| DELETE | `/categories/:id` | admin | Delete (fails if has active products) |

### Products
| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET    | `/products` | public | List products |
| POST   | `/products` | admin | Create |
| GET    | `/products/:id` | public | Get product |
| PATCH  | `/products/:id` | admin | Partial update |
| PUT    | `/products/:id` | admin | Full replace |
| DELETE | `/products/:id` | admin | Delete |
| PATCH  | `/products/:id/stock` | admin | Adjust stock quantity |

### Texts (CMS)
| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET    | `/texts` | public | List texts |
| POST   | `/texts` | admin | Create |
| GET    | `/texts/by-key/:syscode` | public | Get by syscode + language |
| GET    | `/texts/:id` | public | Get by ID |
| PATCH  | `/texts/:id` | admin | Partial update |
| PUT    | `/texts/:id` | admin | Full replace |
| DELETE | `/texts/:id` | admin | Delete |

### Enumerations
| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET    | `/enumerations` | public | List (grouped by type) |
| GET    | `/enumerations/types` | public | List all types |
| POST   | `/enumerations` | admin | Create |
| GET    | `/enumerations/:id` | public | Get by ID |
| PATCH  | `/enumerations/:id` | admin | Partial update |
| PUT    | `/enumerations/:id` | admin | Full replace |
| DELETE | `/enumerations/:id` | admin | Delete |

### Orders
| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET    | `/orders` | required | My orders (admin: all) |
| POST   | `/orders` | required | Create order |
| GET    | `/orders/:id` | required | Get order with items |
| PATCH  | `/orders/:id/status` | admin | Update status |
| DELETE | `/orders/:id` | admin | Delete |

### Invoices
| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET    | `/invoices` | required | My invoices (admin: all) |
| POST   | `/invoices` | admin | Generate from order |
| GET    | `/invoices/:id` | required | Get invoice with items |
| PATCH  | `/invoices/:id/status` | admin | Update status |
| DELETE | `/invoices/:id` | admin | Delete |

## Database schema

Tables: `role`, `user`, `user_token`, `address`, `category`, `product`, `product_category`, `text`, `enumeration`, `order`, `order_item`, `invoice`, `invoice_item`

- **`product_category`** — M:N pivot table linking products to categories (one product can belong to multiple categories).
- **`category.syscode`** — machine-readable identifier (e.g. `top`, `new`) for filtering via `category_syscode` query param.
- **`product.data`** — flexible JSON column for project-specific attributes. Filter via dot-notation: `filter={"data.year":{"value":2022}}`.

All tables (except `user_token`, `order_item`, `invoice_item`) are scoped by `franchise_code`.


