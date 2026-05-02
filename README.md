# php-core

PHP microservice REST API backed by MySQL. Bearer token authentication, clean routing, no framework dependencies.

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
# edit .env with your DB credentials
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
- **Password:** `password` *(change immediately in production)*

## Development server

```bash
php -S localhost:8000
```

## Authentication

The API uses **Bearer token** authentication. Cookies and sessions are not used.

**Login and get a token:**
```bash
curl -X POST http://localhost/php/php-core/auth/login \
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
curl http://localhost/php/php-core/products \
  -H "Authorization: Bearer a3f9c2..."
```

Tokens expire after 24 hours (configurable via `TOKEN_LIFETIME` in `.env`). Logout invalidates the token server-side.

## Project structure

```
php-core/
├── index.php              # Entry point – routes all requests, lists endpoints at GET /
├── bootstrap.php          # Autoload, .env, error handling
├── .env.example
├── .htaccess              # Mod_rewrite + Authorization header passthrough + security headers
├── composer.json
├── bin/
│   └── migrate.php        # DB migration runner
├── migrations/
│   └── schema.sql         # Full schema + seed data
├── pages/
│   ├── index.html         # Rozcestník – přehled všech stránek
│   ├── db-schema.html     # Mermaid ER diagram (light theme, export SVG)
│   └── db-table.html      # HTML/CSS schema viewer with FK table
│   ├── flow-login.html    # Sekvenční diagram – přihlášení
│   ├── flow-order-cancel.html  # Flow – storno objednávky
│   └── flow-models.html   # Flow – všechny modely
├── tests/
│   └── api_test.php       # CLI test runner (57 tests)
└── src/
    ├── Core/
    │   ├── Auth.php        # Bearer token auth (login/logout/check/require)
    │   ├── Database.php    # PDO singleton with helper methods
    │   ├── Request.php     # HTTP request parsing
    │   ├── Response.php    # JSON response helpers
    │   └── Router.php      # Simple regex router with middleware support
    ├── Middleware/
    │   ├── AuthMiddleware.php   # Requires valid Bearer token
    │   └── CorsMiddleware.php   # CORS headers
    └── Controllers/
        ├── AuthController.php         # Login, logout, register, me, change-password
        ├── UserController.php         # CRUD users (admin) + self-edit
        ├── AddressController.php      # Billing/shipping addresses per user
        ├── CategoryController.php     # Product categories (tree structure)
        ├── ProductController.php      # Products with stock management
        ├── TextController.php         # CMS text blocks (multilingual)
        ├── EnumerationController.php  # Codebook/enumeration values (ciselnik)
        ├── OrderController.php        # Orders with order items + stock decrement
        └── InvoiceController.php      # Invoices generated from orders
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
| POST | `/auth/register` | public | Register |
| POST | `/auth/change-password` | required | Change password |

### Users *(admin)*
| Method | Path | Description |
|--------|------|-------------|
| GET    | `/users` | List users |
| POST   | `/users` | Create user |
| GET    | `/users/:id` | Get user |
| PUT    | `/users/:id` | Update user |
| DELETE | `/users/:id` | Soft-delete user |
| GET    | `/users/:userId/addresses` | User's addresses |

### Products
| Method | Path | Description |
|--------|------|-------------|
| GET    | `/products` | List products |
| POST   | `/products` | Create *(admin)* |
| GET    | `/products/:id` | Get product |
| PUT    | `/products/:id` | Update *(admin)* |
| DELETE | `/products/:id` | Soft-delete *(admin)* |
| PATCH  | `/products/:id/stock` | Adjust stock *(admin)* |

### Categories
| Method | Path | Description |
|--------|------|-------------|
| GET    | `/categories` | List as tree |
| POST   | `/categories` | Create *(admin)* |
| GET    | `/categories/:id` | Get with products |
| PUT    | `/categories/:id` | Update *(admin)* |
| DELETE | `/categories/:id` | Delete *(admin)* |

### Orders
| Method | Path | Description |
|--------|------|-------------|
| GET    | `/orders` | My orders (admin: all) |
| POST   | `/orders` | Create order |
| GET    | `/orders/:id` | Get order with items |
| PATCH  | `/orders/:id/status` | Update status *(admin)* |
| DELETE | `/orders/:id` | Soft-delete *(admin)* |

### Invoices
| Method | Path | Description |
|--------|------|-------------|
| GET    | `/invoices` | My invoices (admin: all) |
| POST   | `/invoices` | Generate from order *(admin)* |
| GET    | `/invoices/:id` | Get invoice with items |
| PATCH  | `/invoices/:id/status` | Update status *(admin)* |
| DELETE | `/invoices/:id` | Soft-delete *(admin)* |

### Texts (CMS)
| Method | Path | Description |
|--------|------|-------------|
| GET    | `/texts` | List texts |
| POST   | `/texts` | Create *(admin)* |
| GET    | `/texts/:id` | Get by ID |
| GET    | `/texts/by-key/:key` | Get by key |
| PUT    | `/texts/:id` | Update *(admin)* |
| DELETE | `/texts/:id` | Delete *(admin)* |

### Enumerations (Ciselnik)
| Method | Path | Description |
|--------|------|-------------|
| GET    | `/enumerations` | List (grouped by type) |
| GET    | `/enumerations/types` | List all types |
| POST   | `/enumerations` | Create *(admin)* |
| GET    | `/enumerations/:id` | Get by ID |
| PUT    | `/enumerations/:id` | Update *(admin)* |
| DELETE | `/enumerations/:id` | Delete *(admin)* |

### Addresses
| Method | Path | Description |
|--------|------|-------------|
| POST   | `/addresses` | Create address |
| GET    | `/addresses/:id` | Get address |
| PUT    | `/addresses/:id` | Update address |
| DELETE | `/addresses/:id` | Delete address |

## Database schema

Tables: `user`, `user_token`, `address`, `category`, `product`, `text`, `enumeration`, `order`, `order_item`, `invoice`, `invoice_item`

All write operations require a valid Bearer token. Non-admin users can only access their own data.
