# php-core API Documentation

> **Base URL:** `http://<host>/php/php-core/api`  
> **Content-Type:** `application/json` (request & response)  
> **Encoding:** UTF-8

---

## Table of Contents

1. [Architecture Overview](#architecture-overview)
2. [Authentication](#authentication)
3. [Response Envelope](#response-envelope)
4. [HTTP Status Codes](#http-status-codes)
5. [Pagination](#pagination)
6. [Sorting](#sorting)
7. [Filtering](#filtering)
8. [Projection](#projection)
9. [Modules](#modules)
   - [Auth](#auth)
   - [Roles](#roles)
   - [Users](#users)
   - [Addresses](#addresses)
   - [Categories](#categories)
   - [Products](#products)
   - [Enumerations](#enumerations)
   - [Texts](#texts)
   - [Orders](#orders)
   - [Invoices](#invoices)
9. [Projection](#projection)
10. [Field Reference](#field-reference)
11. [Common Patterns & Frontend Recipes](#common-patterns--frontend-recipes)

---

## Architecture Overview

The API is a multi-tenant REST API. Every resource is scoped to a **franchise_code** (configured server-side via `.env`). The frontend never sends `franchise_code` explicitly — it is resolved automatically from the server configuration.

All list endpoints support three universal query parameters:

| Parameter  | Description                        |
|------------|------------------------------------|
| `page`     | Page number (default: `1`)         |
| `limit`    | Items per page (default: `20`, max: `100`) |
| `sort`     | JSON sort specification (see [Sorting](#sorting)) |
| `filter`   | JSON filter specification (see [Filtering](#filtering)) |

---

## Authentication

The API uses **Bearer token** authentication.

### Obtaining a token

```http
POST /auth/login
Content-Type: application/json

{
  "email": "admin@example.com",
  "password": "password"
}
```

Response:
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "token": "abc123...",
    "expires_at": "2026-06-03T10:00:00",
    "user": {
      "id": 1,
      "first_name": "Admin",
      "last_name": "User",
      "email": "admin@example.com",
      "role": "admin"
    }
  }
}
```

### Using the token

Include the token in every subsequent request:

```http
Authorization: Bearer <token>
```

### Token lifetime & logout

Tokens have a server-configured TTL. To invalidate the token explicitly:

```http
POST /auth/logout
Authorization: Bearer <token>
```

---

## Response Envelope

Every response — success or error — is wrapped in a consistent JSON envelope.

### Success
```json
{
  "success": true,
  "message": "OK",
  "data": { ... }
}
```

### Created (HTTP 201)
```json
{
  "success": true,
  "message": "Role created",
  "data": { ... }
}
```

### Error
```json
{
  "success": false,
  "message": "Not Found",
  "errors": null
}
```

### Validation error (HTTP 422)
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "email": "Invalid email address",
    "password": "Minimum 8 characters required"
  }
}
```

---

## HTTP Status Codes

| Code | Meaning                             |
|------|-------------------------------------|
| 200  | OK – read / update successful       |
| 201  | Created – resource created          |
| 400  | Bad Request – general client error  |
| 401  | Unauthorized – missing or invalid token |
| 403  | Forbidden – token valid but insufficient permissions |
| 404  | Not Found – resource does not exist |
| 409  | Conflict – duplicate unique value (e.g. email, SKU) |
| 422  | Unprocessable Entity – validation failed |
| 500  | Internal Server Error               |

---

## Pagination

All list endpoints return a paginated envelope inside `data`:

```json
{
  "success": true,
  "message": "OK",
  "data": {
    "items": [ ... ],
    "total": 243,
    "page": 2,
    "limit": 20,
    "totalPages": 13
  }
}
```

| Field        | Type    | Description                                  |
|--------------|---------|----------------------------------------------|
| `items`      | array   | Current page of records                      |
| `total`      | integer | Total matching records (after filter applied)|
| `page`       | integer | Current page number                          |
| `limit`      | integer | Items per page used                          |
| `totalPages` | integer | Calculated: `ceil(total / limit)`            |

### Query parameters

| Parameter | Default | Max | Description         |
|-----------|---------|-----|---------------------|
| `page`    | 1       | —   | Page number         |
| `limit`   | 20      | 100 | Items per page      |

### Examples

```
GET /products?page=3&limit=50
GET /users?page=1&limit=100
```

---

## Sorting

The `sort` parameter accepts a **JSON array of sort objects**. Each object has one key — the column name — and a value of `1` (ascending) or `-1` (descending).

### Format

```
sort=[{"col": 1}]          → ORDER BY col ASC
sort=[{"col": -1}]         → ORDER BY col DESC
sort=[{"a": 1}, {"b": -1}] → ORDER BY a ASC, b DESC
```

### URL encoding

Always URL-encode the JSON:

```js
const sort = JSON.stringify([{ name: 1 }, { created_at: -1 }]);
const url = `/products?sort=${encodeURIComponent(sort)}`;
```

### Rules

- Column names must match `/^[a-zA-Z_][a-zA-Z0-9_]*$/` — any invalid name is silently skipped.
- Unknown columns are silently ignored (no SQL error).
- Each endpoint has its own default sort (e.g. `position ASC` for roles, `created_at DESC` for orders).
- Only columns that exist in the underlying table (or its joined tables) should be used.

### Practical examples

```
# Products by price ascending
GET /products?sort=[{"price":1}]

# Users by last name, then first name
GET /users?sort=[{"last_name":1},{"first_name":1}]

# Orders newest first
GET /orders?sort=[{"created_at":-1}]

# Roles by position then name
GET /roles?sort=[{"position":1},{"name":1}]
```

---

## Filtering

The `filter` parameter accepts a **JSON object** where each key is a column name and the value is a specification object `{ "value": ..., "operator": "..." }`.

### Format

```json
{
  "column_name": {
    "value": <scalar | array>,
    "operator": "<operator>"
  }
}
```

When `operator` is omitted, `eq` (equals) is used as default.

### Supported operators

| Operator   | SQL equivalent          | Value type                | Notes                              |
|------------|------------------------|---------------------------|------------------------------------|
| `eq`       | `col = ?`              | scalar                    | Default when operator not provided |
| `neq`      | `col != ?`             | scalar                    |                                    |
| `lt`       | `col < ?`              | scalar (number/date)      |                                    |
| `lte`      | `col <= ?`             | scalar (number/date)      |                                    |
| `gt`       | `col > ?`              | scalar (number/date)      |                                    |
| `gte`      | `col >= ?`             | scalar (number/date)      |                                    |
| `range`    | `col BETWEEN ? AND ?`  | `[min, max]` array        | Exactly 2 elements required        |
| `regex`    | `col LIKE '%val%'`     | string                    | Contains search                    |
| `start`    | `col LIKE 'val%'`      | string                    | Starts-with search                 |
| `end`      | `col LIKE '%val'`      | string                    | Ends-with search                   |
| `in`       | `col IN (?,?,?)`       | non-empty array           |                                    |
| `null`     | `col IS NULL`          | (none needed)             | `value` is ignored                 |
| `notnull`  | `col IS NOT NULL`      | (none needed)             | `value` is ignored                 |

**Operator names are case-insensitive** — `"GTE"` and `"gte"` are equivalent.

### Security

- Column names are validated to prevent SQL injection.
  - Simple columns must match `/^[a-zA-Z_][a-zA-Z0-9_]*$/`.
  - **Dot-notation** for JSON sub-fields is also supported: `"data.year"` is translated to `JSON_UNQUOTE(JSON_EXTRACT(alias.data, '$.year'))`. Both the column part and the field part are validated separately. Any other use of `.` (e.g. two dots, leading dot) is rejected.
  - Any invalid name is silently skipped — no SQL injection is possible.
- Values are always passed as PDO bound parameters — no escaping needed.
- Invalid JSON, empty `{}`, or entirely invalid column names return an empty filter (all records returned).

### Multiple columns

Multiple columns are combined with `AND`:

```json
{
  "status": { "value": "active" },
  "price":  { "value": [100, 500], "operator": "range" }
}
```
→ `WHERE status = 'active' AND price BETWEEN 100 AND 500`

### URL encoding

```js
const filter = JSON.stringify({
  status: { value: "active" },
  price:  { value: [100, 500], operator: "range" }
});
const url = `/products?filter=${encodeURIComponent(filter)}`;
```

### Combining sort + filter + pagination

All three parameters can be combined freely:

```
GET /products
  ?page=1
  &limit=20
  &sort=[{"price":1}]
  &filter={"price":{"value":[100,500],"operator":"range"},"name":{"value":"shirt","operator":"regex"}}
```

### Practical filter examples

```
# Exact match (default operator)
filter={"status":{"value":"active"}}

# Not equal
filter={"status":{"value":"cancelled","operator":"neq"}}

# Numeric comparison – price above 1000
filter={"price":{"value":1000,"operator":"gt"}}

# Date range
filter={"created_at":{"value":["2026-01-01","2026-12-31"],"operator":"range"}}

# Contains (case-sensitive LIKE)
filter={"name":{"value":"shirt","operator":"regex"}}

# Starts with
filter={"email":{"value":"admin","operator":"start"}}

# Ends with
filter={"email":{"value":"@example.com","operator":"end"}}

# IN list – multiple statuses
filter={"status":{"value":["pending","confirmed"],"operator":"in"}}

# IS NULL
filter={"deleted_at":{"operator":"null"}}

# IS NOT NULL
filter={"phone":{"operator":"notnull"}}

# Multi-column – active products in price range
filter={"is_active":{"value":1},"price":{"value":[50,200],"operator":"range"}}
```

---

## Projection

The `projection` query parameter controls which fields are returned in every response — list, get, create, update and delete responses all respect it.

### Format

```
GET /products?projection=id,name,price
```

Comma-separated list of field names (snake_case).

### Behaviour

| `projection` value | What is returned |
|--------------------|-----------------|
| *omitted*          | All fields (system + own columns + no relation JOINs by default) |
| `projection=`      | System fields only (`id`, `created_at`, `updated_at`) |
| `projection=name,price` | System fields + requested own fields |
| `projection=user`  | System fields + full `user` relation object (triggers JOIN) |
| `projection=user.first_name,user.email` | System fields + selected relation sub-fields |

### System fields (always returned)

`id`, `created_at`, `updated_at` — these are always included regardless of projection.

### Relation names per module

| Module | Available relations |
|--------|-------------------|
| Users | `role` |
| Orders | `user` |
| Invoices | `user` |
| Products | `categories` |
| Others | *(none)* |

### Examples

```
# Only id, name and price (+ system fields)
GET /products?projection=name,price

# System fields only
GET /products?projection=

# Full user object included in each order
GET /orders?projection=order_number,status,user

# Specific user sub-fields
GET /orders?projection=order_number,user.email,user.first_name

# Apply projection to POST response
POST /products?projection=id,sku,name
```

---

## Modules

> **Auth note:** Unless marked as **Public**, all endpoints require `Authorization: Bearer <token>`.  
> Admin-only endpoints additionally require the authenticated user to have the `admin` role.

---

### Auth

| Method | Endpoint                | Auth     | Description               |
|--------|-------------------------|----------|---------------------------|
| POST   | `/auth/login`           | Public   | Login, receive token      |
| POST   | `/auth/register`        | Public   | Register new user account |
| POST   | `/auth/logout`          | Required | Invalidate current token  |
| GET    | `/auth/me`              | Required | Current user info         |
| POST   | `/auth/change-password` | Required | Change own password       |

---

#### `POST /auth/login`

**Public**

Request:
```json
{
  "email": "admin@example.com",
  "password": "password"
}
```

Response `200`:
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "token": "64-character-hex-token",
    "expires_at": "2026-06-03T12:00:00",
    "user": {
      "id": 1,
      "first_name": "Admin",
      "last_name": "User",
      "email": "admin@example.com",
      "role": "admin"
    }
  }
}
```

Errors: `422` missing fields, `401` wrong credentials.

---

#### `POST /auth/register`

**Public**

Request:
```json
{
  "first_name": "Jana",
  "last_name": "Nováková",
  "email": "jana@example.com",
  "password": "secret123"
}
```

Response `201`:
```json
{
  "success": true,
  "message": "Registration successful",
  "data": { "id": 42 }
}
```

Errors: `422` validation, `409` email already exists.

---

#### `POST /auth/logout`

Response `200`:
```json
{ "success": true, "message": "Logged out", "data": null }
```

---

#### `GET /auth/me`

Response `200`:
```json
{
  "success": true,
  "message": "OK",
  "data": {
    "id": 1,
    "first_name": "Admin",
    "last_name": "User",
    "email": "admin@example.com",
    "phone": null,
    "role": "admin",
    "last_login_at": "2026-05-03T09:00:00",
    "created_at": "2026-01-01T00:00:00"
  }
}
```

---

#### `POST /auth/change-password`

Request:
```json
{
  "current_password": "oldpassword",
  "new_password": "newpassword123"
}
```

Response `200`: `{ "success": true, "message": "Password changed successfully", "data": null }`

---

### Roles

> **Admin only** for write operations. Read is public (no auth required for list/get if the router allows it — verify your configuration).

| Method | Endpoint       | Auth       | Description       |
|--------|----------------|------------|-------------------|
| GET    | `/roles`       | Required   | List all roles    |
| GET    | `/roles/:id`   | Required   | Get role by ID    |
| POST   | `/roles`       | Admin only | Create role       |
| PATCH  | `/roles/:id`   | Admin only | Partial update    |
| PUT    | `/roles/:id`   | Admin only | Full replace      |
| DELETE | `/roles/:id`   | Admin only | Delete role       |

#### Role object

```json
{
  "id": 1,
  "name": "admin",
  "label": "Admin",
  "position": 10,
  "user_count": 3,
  "created_at": "2026-01-01T00:00:00",
  "updated_at": null
}
```

| Field        | Type    | Notes                                          |
|--------------|---------|------------------------------------------------|
| `id`         | integer | Auto-increment PK                              |
| `name`       | string  | Lowercase, `/^[a-z0-9_]+$/`, unique per franchise |
| `label`      | string  | Human-readable name                            |
| `position`   | integer | Sort order                                     |
| `user_count` | integer | Number of users assigned (detail only)         |

#### `GET /roles`

Query parameters:

| Parameter | Description                       |
|-----------|-----------------------------------|
| `page`    | Page number (default: 1)          |
| `limit`   | Per page (default: 20, max: 100)  |
| `sort`    | JSON sort (columns: `name`, `label`, `position`, `created_at`) |
| `filter`  | JSON filter (same columns)        |

```
GET /roles?sort=[{"position":1}]&filter={"name":{"value":"adm","operator":"start"}}
```

#### `POST /roles`

Request:
```json
{
  "name": "editor",
  "label": "Editor",
  "position": 25
}
```

#### `PATCH /roles/:id`

Send only the fields to update:
```json
{
  "label": "Chief Editor",
  "position": 15
}
```

#### `PUT /roles/:id`

Full replacement — all fields required:
```json
{
  "name": "editor",
  "label": "Editor",
  "position": 25
}
```

---

### Users

| Method | Endpoint       | Auth         | Description          |
|--------|----------------|--------------|----------------------|
| GET    | `/users`       | Admin only   | List all users       |
| GET    | `/users/:id`   | Self or admin| Get user by ID       |
| POST   | `/users`       | Admin only   | Create user          |
| PATCH  | `/users/:id`   | Admin only   | Partial update       |
| PUT    | `/users/:id`   | Admin only   | Full replace         |
| DELETE | `/users/:id`   | Admin only   | Delete user          |
| GET    | `/users/:id/address` | Self or admin | List user's addresses |

#### User object

```json
{
  "id": 1,
  "first_name": "Jana",
  "last_name": "Nováková",
  "email": "jana@example.com",
  "phone": "+420123456789",
  "role": "user",
  "role_id": 3,
  "last_login_at": "2026-05-01T10:30:00",
  "created_at": "2026-01-15T08:00:00",
  "updated_at": "2026-04-01T14:22:00"
}
```

#### `GET /users`

Query parameters:

| Parameter | Description                                               |
|-----------|-----------------------------------------------------------|
| `page`    | Page number                                               |
| `limit`   | Per page (max: 100)                                       |
| `search`  | Full-text search in `first_name`, `last_name`, `email`    |
| `role`    | Filter by role name (e.g. `?role=admin`)                  |
| `sort`    | JSON sort (columns: `first_name`, `last_name`, `email`, `created_at`, `last_login_at`) |
| `filter`  | JSON filter (same columns + `phone`, `role_id`)           |

```
GET /users?search=jana&role=user&sort=[{"last_name":1}]&page=1&limit=20
```

> Note: `search` and `role` are shorthand convenience params. For more complex queries, use `filter`.

#### `POST /users`

Request:
```json
{
  "first_name": "Jana",
  "last_name": "Nováková",
  "email": "jana@example.com",
  "password": "secret123",
  "phone": "+420123456789",
  "role": "user"
}
```

| Field        | Required | Notes                                      |
|--------------|----------|--------------------------------------------|
| `first_name` | ✓        |                                            |
| `last_name`  | ✓        |                                            |
| `email`      | ✓        | Unique per franchise                       |
| `password`   | ✓        | Min 8 characters, stored as bcrypt hash    |
| `phone`      | —        | Optional                                   |
| `role`       | —        | Role `name` string; defaults to `"user"`   |

#### `PATCH /users/:id`

Send only changed fields (password change uses `/auth/change-password`):
```json
{
  "first_name": "Janka",
  "phone": "+420987654321",
  "role": "manager"
}
```

---

### Addresses

Addresses belong to a user. They are accessed under `/users/:userId/address` (list) and directly under `/addresses/:id` (get/update/delete).

| Method | Endpoint                    | Auth           | Description                |
|--------|-----------------------------|----------------|----------------------------|
| GET    | `/users/:userId/address`    | Self or admin  | List user's addresses      |
| GET    | `/addresses/:id`            | Self or admin  | Get single address         |
| POST   | `/addresses`                | Required       | Create address             |
| PATCH  | `/addresses/:id`            | Self or admin  | Partial update             |
| PUT    | `/addresses/:id`            | Self or admin  | Full replace               |
| DELETE | `/addresses/:id`            | Self or admin  | Delete address             |

#### Address object

```json
{
  "id": 5,
  "user_id": 1,
  "type": "billing",
  "company": "ACME s.r.o.",
  "name": "Jana Nováková",
  "street": "Hlavní 123",
  "city": "Praha",
  "zip": "110 00",
  "country": "CZ",
  "is_default": 1,
  "created_at": "2026-02-01T09:00:00",
  "updated_at": null
}
```

| Field        | Type    | Notes                                     |
|--------------|---------|-------------------------------------------|
| `type`       | string  | `"billing"` or `"shipping"`               |
| `company`    | string  | Optional company name                     |
| `name`       | string  | Optional contact name                     |
| `street`     | string  | **Required**                              |
| `city`       | string  | **Required**                              |
| `zip`        | string  | **Required**                              |
| `country`    | string  | ISO 3166-1 alpha-2, default `"CZ"`        |
| `is_default` | 0 or 1  | Only one default per type per user        |

#### `GET /users/:userId/address`

Query parameters:

| Parameter | Description                                      |
|-----------|--------------------------------------------------|
| `type`    | Filter by type: `billing` or `shipping`          |
| `sort`    | JSON sort (columns: `type`, `city`, `is_default`, `created_at`) |
| `filter`  | JSON filter                                      |
| `page`    | Page number                                      |
| `limit`   | Per page (max: 100)                              |

```
GET /users/1/address?type=billing&sort=[{"is_default":-1}]
```

#### `POST /addresses`

```json
{
  "type": "shipping",
  "company": "ACME s.r.o.",
  "name": "Jana Nováková",
  "street": "Vedlejší 456",
  "city": "Brno",
  "zip": "602 00",
  "country": "CZ",
  "is_default": 1,
  "user_id": 42
}
```

> `user_id` is only respected when the caller has the `admin` role. Otherwise the address is created for the authenticated user.

> When `is_default: 1` is set, all other addresses of the same `type` for that user are automatically cleared (`is_default → 0`).

---

### Categories

| Method | Endpoint           | Auth       | Description           |
|--------|--------------------|------------|-----------------------|
| GET    | `/categories`      | Required   | List all categories   |
| GET    | `/categories/:id`  | Required   | Get category by ID    |
| POST   | `/categories`      | Admin only | Create category       |
| PATCH  | `/categories/:id`  | Admin only | Partial update        |
| PUT    | `/categories/:id`  | Admin only | Full replace          |
| DELETE | `/categories/:id`  | Admin only | Delete category       |

#### Category object (list)

```json
{
  "id": 3,
  "parent_id": 1,
  "syscode": "sale",
  "name": "T-Shirts",
  "description": "All t-shirt variants",
  "position": 10,
  "created_at": "2026-01-10T08:00:00",
  "updated_at": null
}
```

#### Category object (detail — includes nested products)

```json
{
  "id": 3,
  "parent_id": 1,
  "syscode": "sale",
  "name": "T-Shirts",
  "description": "All t-shirt variants",
  "position": 10,
  "created_at": "2026-01-10T08:00:00",
  "updated_at": null,
  "products": [
    { "id": 10, "name": "Classic Tee", "sku": "TSH-001", "price": "299.00" }
  ]
}
```

#### `GET /categories`

Query parameters:

| Parameter | Description                                         |
|-----------|-----------------------------------------------------|
| `page`    | Page number                                         |
| `limit`   | Per page (max: 100)                                 |
| `sort`    | JSON sort (columns: `name`, `position`, `parent_id`, `created_at`) |
| `filter`  | JSON filter                                         |

```
GET /categories?sort=[{"position":1},{"name":1}]&filter={"parent_id":{"operator":"null"}}
```

> Tip: Use `filter={"parent_id":{"operator":"null"}}` to get only top-level (root) categories.

#### `POST /categories`

```json
{
  "name": "Footwear",
  "description": "Shoes, boots and sandals",
  "syscode": "footwear",
  "parent_id": null,
  "position": 30
}
```

| Field         | Required | Notes                                             |
|---------------|----------|---------------------------------------------------|
| `name`        | ✓        |                                                   |
| `syscode`     | —        | Machine-readable identifier, unique per franchise |
| `description` | —        |                                                   |
| `parent_id`   | —        | ID of parent category for nesting                 |
| `position`    | —        | Sort order                                        |

---

### Products

| Method | Endpoint               | Auth       | Description            |
|--------|------------------------|------------|------------------------|
| GET    | `/products`            | Required   | List all products      |
| GET    | `/products/:id`        | Required   | Get product by ID      |
| POST   | `/products`            | Admin only | Create product         |
| PATCH  | `/products/:id`        | Admin only | Partial update         |
| PUT    | `/products/:id`        | Admin only | Full replace           |
| DELETE | `/products/:id`        | Admin only | Delete product         |
| PATCH  | `/products/:id/stock`  | Admin only | Adjust stock quantity  |

#### Product object

```json
{
  "id": 10,
  "category_ids": [3, 7],
  "category_names": ["T-Shirts", "Sale"],
  "sku": "TSH-001",
  "name": "Classic Tee",
  "description": "100% cotton t-shirt",
  "price": "299.00",
  "vat_rate": "21.00",
  "stock_quantity": 150,
  "is_active": 1,
  "kind": "dry",
  "color": "white",
  "variant": "riesling",
  "data": { "quality": "kabinett", "volume": 0.75, "year": 2022 },
  "created_at": "2026-01-15T08:00:00",
  "updated_at": "2026-04-01T10:00:00"
}
```

| Field            | Type           | Notes                                                     |
|------------------|----------------|-----------------------------------------------------------|
| `sku`            | string         | Unique per franchise                                      |
| `price`          | decimal string | e.g. `"299.00"`                                           |
| `vat_rate`       | decimal string | Percentage, e.g. `"21.00"` = 21 % VAT                    |
| `stock_quantity` | integer        | Can be negative (backordering)                            |
| `is_active`      | 0 or 1         | `1` = visible/active                                      |
| `kind`           | string or null | Project-specific attribute, e.g. dry, sweet               |
| `color`          | string or null | Project-specific attribute, e.g. white, red               |
| `variant`        | string or null | Project-specific attribute, e.g. grape variety            |
| `data`           | object or null | Flexible JSON attributes — project-defined keys           |
| `category_ids`   | integer[]      | IDs of all assigned categories (M:N)                      |
| `category_names` | string[]       | Names of all assigned categories (GET `/:id` only)        |

#### `GET /products`

Query parameters:

| Parameter         | Description                                                                      |
|-------------------|----------------------------------------------------------------------------------|
| `page`            | Page number                                                                      |
| `limit`           | Per page (max: 100)                                                              |
| `search`          | Full-text search in `name`, `sku`, `description`                                 |
| `category_id`     | Filter products belonging to a category ID                                       |
| `category_syscode`| Filter products belonging to a category identified by its `syscode`              |
| `sort`            | JSON sort (columns: `name`, `sku`, `price`, `stock_quantity`, `created_at`)      |
| `filter`          | JSON filter — supports `kind`, `color`, `variant`, `is_active`, `price`, `stock_quantity`, `vat_rate` and dot-notation for JSON attributes e.g. `data.year` |

```
GET /products?category_id=3&search=shirt&sort=[{"price":1}]&filter={"stock_quantity":{"value":0,"operator":"gt"}}
GET /products?category_syscode=top
GET /products?filter={"is_active":{"value":1},"data.year":{"value":2022},"color":{"value":"white"}}
```

#### `POST /products`

```json
{
  "sku": "TSH-002",
  "name": "Premium Tee",
  "description": "Organic cotton premium t-shirt",
  "price": 499.00,
  "vat_rate": 21,
  "stock_quantity": 50,
  "is_active": 1,
  "kind": "dry",
  "color": "white",
  "variant": "riesling",
  "data": { "quality": "kabinett", "volume": 0.75, "year": 2022 },
  "category_ids": [3, 7]
}
```

| Field          | Required | Notes                                                           |
|----------------|----------|-----------------------------------------------------------------|
| `name`         | ✓        |                                                                 |
| `sku`          | —        | Auto-generated if omitted                                       |
| `price`        | ✓        | Numeric                                                         |
| `vat_rate`     | —        | Default: `21`                                                   |
| `stock_quantity`| —       | Default: `0`                                                    |
| `is_active`    | —        | `1` or `0`, default: `1`                                        |
| `kind`         | —        | Project-specific string attribute                               |
| `color`        | —        | Project-specific string attribute                               |
| `variant`      | —        | Project-specific string attribute                               |
| `data`         | —        | Flexible JSON object — any project-defined key/value pairs      |
| `category_ids` | —        | Array of category IDs (M:N)                                     |

#### `PATCH /products/:id`

Send only the fields to update. For `data`, the provided object is **shallow-merged** into the existing `data` — keys not sent are preserved:

```json
{
  "kind": "sweet",
  "data": { "quality": "late_harvest" }
}
```

To clear all JSON data, send `"data": null`.

#### `PUT /products/:id`

Full replacement — all fields required (same structure as POST). `data` is replaced entirely.

#### `PATCH /products/:id/stock`

Adjusts stock by a **delta** (positive = add, negative = subtract):

```json
{ "quantity": -5 }
```

Response:
```json
{
  "success": true,
  "message": "Stock adjusted",
  "data": { "stock_quantity": 145 }
}
```

---

### Enumerations

Enumerations are the system's codebook/lookup tables (order statuses, payment methods, VAT rates, currencies, etc.).

| Method | Endpoint                    | Auth       | Description                     |
|--------|-----------------------------|------------|---------------------------------|
| GET    | `/enumerations`             | Required   | List enumeration items          |
| GET    | `/enumerations/types`       | Required   | List all distinct type names    |
| GET    | `/enumerations/:id`         | Required   | Get single enumeration item     |
| POST   | `/enumerations`             | Admin only | Create enumeration item         |
| PATCH  | `/enumerations/:id`         | Admin only | Partial update                  |
| PUT    | `/enumerations/:id`         | Admin only | Full replace                    |
| DELETE | `/enumerations/:id`         | Admin only | Delete enumeration item         |

#### Enumeration object

```json
{
  "id": 1,
  "type": "order_status",
  "syscode": "pending",
  "label": "Pending",
  "value": "pending",
  "position": 10,
  "is_active": 1,
  "created_at": "2026-01-01T00:00:00",
  "updated_at": null
}
```

| Field      | Type    | Notes                                                    |
|------------|---------|----------------------------------------------------------|
| `type`     | string  | Category identifier, e.g. `order_status`, `vat_rate`    |
| `syscode`  | string  | Machine key — unique within `(franchise, type)`          |
| `label`    | string  | Human-readable display label                             |
| `value`    | string  | Stored value (may differ from syscode)                   |
| `is_active`| 0 or 1  |                                                          |

#### Built-in types (default seed data)

| Type             | Example syscodes                                            |
|------------------|-------------------------------------------------------------|
| `order_status`   | `pending`, `confirmed`, `processing`, `shipped`, `delivered`, `cancelled`, `refunded` |
| `invoice_status` | `draft`, `issued`, `paid`, `overdue`, `cancelled`, `refunded` |
| `payment_method` | `bank_transfer`, `cash`, `card`, `online`                   |
| `currency`       | `CZK`, `EUR`, `USD`                                         |
| `vat_rate`       | `0`, `10`, `12`, `21`                                       |

#### `GET /enumerations`

Query parameters:

| Parameter   | Description                                                   |
|-------------|---------------------------------------------------------------|
| `type`      | Filter by type name (e.g. `?type=order_status`)               |
| `is_active` | `1` for active only, `0` for inactive only                    |
| `sort`      | JSON sort (columns: `type`, `syscode`, `label`, `position`, `is_active`) |
| `filter`    | JSON filter                                                   |
| `page`      | Page number                                                   |
| `limit`     | Per page (max: 100)                                           |

```
GET /enumerations?type=order_status&is_active=1&sort=[{"position":1}]
```

#### `GET /enumerations/types`

Returns a flat list of distinct type strings:
```json
{
  "success": true,
  "message": "OK",
  "data": ["currency", "invoice_status", "order_status", "payment_method", "vat_rate"]
}
```

#### `POST /enumerations`

```json
{
  "type": "payment_method",
  "syscode": "crypto",
  "label": "Cryptocurrency",
  "value": "crypto",
  "position": 50,
  "is_active": 1
}
```

---

### Texts

CMS content blocks — multilingual, versioned by `(syscode, language)`.

| Method | Endpoint                  | Auth       | Description                    |
|--------|---------------------------|------------|--------------------------------|
| GET    | `/texts`                  | Required   | List text blocks               |
| GET    | `/texts/:id`              | Required   | Get text block by ID           |
| GET    | `/texts/by-key/:syscode`  | Required   | Get text block by syscode      |
| POST   | `/texts`                  | Admin only | Create text block              |
| PATCH  | `/texts/:id`              | Admin only | Partial update                 |
| PUT    | `/texts/:id`              | Admin only | Full replace                   |
| DELETE | `/texts/:id`              | Admin only | Delete text block              |

#### Text object

```json
{
  "id": 1,
  "syscode": "homepage_hero",
  "title": "Welcome to our store",
  "content": "<h1>Hello!</h1><p>Shop our collection...</p>",
  "language": "cs",
  "is_active": 1,
  "created_by": 1,
  "created_at": "2026-01-01T00:00:00",
  "updated_at": "2026-04-15T09:30:00"
}
```

| Field      | Type    | Notes                                              |
|------------|---------|----------------------------------------------------|
| `syscode`  | string  | Machine key, unique per `(franchise, syscode, language)` |
| `language` | string  | BCP-47 language tag, e.g. `cs`, `en`, `de`         |
| `content`  | string  | Arbitrary text / HTML                              |
| `is_active`| 0 or 1  |                                                    |

#### `GET /texts`

Query parameters:

| Parameter   | Description                                                  |
|-------------|--------------------------------------------------------------|
| `language`  | Language code (default: `cs`)                                |
| `is_active` | `1` active only, `0` inactive only                           |
| `search`    | Full-text search in `title`, `syscode`, `content`            |
| `sort`      | JSON sort (columns: `syscode`, `title`, `language`, `is_active`, `created_at`) |
| `filter`    | JSON filter                                                  |
| `page`      | Page number                                                  |
| `limit`     | Per page (max: 100)                                          |

```
GET /texts?language=en&is_active=1&search=hero&sort=[{"syscode":1}]
```

#### `GET /texts/by-key/:syscode`

Fetch a single text block by its syscode. Optionally pass `?language=en` (default: `cs`).

```
GET /texts/by-key/homepage_hero?language=cs
```

#### `POST /texts`

```json
{
  "syscode": "homepage_hero",
  "title": "Welcome to our store",
  "content": "<h1>Hello!</h1>",
  "language": "cs",
  "is_active": 1
}
```

---

### Orders

| Method | Endpoint                   | Auth       | Description              |
|--------|----------------------------|------------|--------------------------|
| GET    | `/orders`                  | Admin only | List all orders          |
| GET    | `/orders/:id`              | Admin only | Get order by ID          |
| POST   | `/orders`                  | Required   | Create order             |
| PATCH  | `/orders/:id/status`       | Admin only | Update order status      |
| DELETE | `/orders/:id`              | Admin only | Delete order             |

#### Order object (list)

```json
{
  "id": 100,
  "order_number": "ORD-2026-000100",
  "user_id": 1,
  "status": "pending",
  "total_amount": "1495.00",
  "currency": "CZK",
  "payment_method": "bank_transfer",
  "shipping_address_id": 5,
  "billing_address_id": 3,
  "note": "",
  "created_at": "2026-05-01T09:00:00",
  "updated_at": null
}
```

#### Order object (detail — includes items)

```json
{
  "id": 100,
  "order_number": "ORD-2026-000100",
  "user_id": 1,
  "status": "pending",
  "total_amount": "1495.00",
  "currency": "CZK",
  "payment_method": "bank_transfer",
  "shipping_address_id": 5,
  "billing_address_id": 3,
  "note": "",
  "created_at": "2026-05-01T09:00:00",
  "updated_at": null,
  "items": [
    {
      "id": 201,
      "product_id": 10,
      "quantity": 3,
      "unit_price": "299.00",
      "total_price": "897.00"
    },
    {
      "id": 202,
      "product_id": 15,
      "quantity": 1,
      "unit_price": "598.00",
      "total_price": "598.00"
    }
  ]
}
```

#### `GET /orders`

Query parameters:

| Parameter | Description                                                        |
|-----------|--------------------------------------------------------------------|
| `status`  | Quick filter by status string (shorthand)                          |
| `sort`    | JSON sort (columns: `order_number`, `status`, `total_amount`, `created_at`, `user_id`) |
| `filter`  | JSON filter                                                        |
| `page`    | Page number                                                        |
| `limit`   | Per page (max: 100)                                                |

```
GET /orders?status=pending&sort=[{"created_at":-1}]
GET /orders?filter={"status":{"value":["pending","confirmed"],"operator":"in"},"total_amount":{"value":1000,"operator":"gte"}}
```

#### Order statuses

`pending` → `confirmed` → `processing` → `shipped` → `delivered`  
Any status → `cancelled` or `refunded`

#### `POST /orders`

```json
{
  "items": [
    { "product_id": 10, "quantity": 3 },
    { "product_id": 15, "quantity": 1 }
  ],
  "currency": "CZK",
  "payment_method": "bank_transfer",
  "note": "Leave at door",
  "shipping_address_id": 5,
  "billing_address_id": 3
}
```

| Field                  | Required | Notes                                              |
|------------------------|----------|----------------------------------------------------|
| `items`                | ✓        | Array of `{ product_id, quantity }`                |
| `currency`             | —        | Default `"CZK"`                                    |
| `payment_method`       | —        | Default `"bank_transfer"`. See enumeration type `payment_method` |
| `shipping_address_id`  | —        | Address ID                                         |
| `billing_address_id`   | —        | Address ID                                         |
| `note`                 | —        | Free text                                          |

`total_amount` is calculated server-side from items. `order_number` is generated automatically.

#### `PATCH /orders/:id/status`

```json
{ "status": "confirmed" }
```

---

### Invoices

Invoices are generated from orders.

| Method | Endpoint                    | Auth       | Description              |
|--------|-----------------------------|------------|--------------------------|
| GET    | `/invoices`                 | Admin only | List all invoices        |
| GET    | `/invoices/:id`             | Admin only | Get invoice by ID        |
| POST   | `/invoices`                 | Admin only | Create invoice from order|
| PATCH  | `/invoices/:id/status`      | Admin only | Update invoice status    |
| DELETE | `/invoices/:id`             | Admin only | Delete invoice           |

#### Invoice object (list)

```json
{
  "id": 50,
  "invoice_number": "INV-2026-000050",
  "order_id": 100,
  "user_id": 1,
  "status": "issued",
  "total_amount": "1495.00",
  "currency": "CZK",
  "billing_address_id": 3,
  "note": "",
  "issued_at": "2026-05-01T09:05:00",
  "due_at": "2026-05-15",
  "paid_at": null,
  "created_at": "2026-05-01T09:05:00",
  "updated_at": null
}
```

#### Invoice object (detail — includes items)

```json
{
  ...same as list...,
  "items": [
    {
      "id": 301,
      "product_id": 10,
      "description": "Classic Tee × 3",
      "quantity": 3,
      "unit_price": "299.00",
      "total_price": "897.00"
    }
  ]
}
```

#### `GET /invoices`

Query parameters:

| Parameter | Description                                                        |
|-----------|--------------------------------------------------------------------|
| `status`  | Quick filter by status string                                      |
| `sort`    | JSON sort (columns: `invoice_number`, `status`, `total_amount`, `issued_at`, `due_at`, `paid_at`) |
| `filter`  | JSON filter                                                        |
| `page`    | Page number                                                        |
| `limit`   | Per page (max: 100)                                                |

```
GET /invoices?filter={"status":{"value":"overdue"}}&sort=[{"due_at":1}]
```

#### Invoice statuses

`draft` → `issued` → `paid`  
`issued` → `overdue`  
Any → `cancelled` or `refunded`

#### `POST /invoices`

```json
{
  "order_id": 100,
  "due_at": "2026-05-15",
  "note": "Please pay within 14 days"
}
```

`invoice_number`, `total_amount`, `currency`, `user_id`, `billing_address_id` are all copied from the source order automatically. Only one invoice per order is allowed (returns `409` on duplicate).

#### `PATCH /invoices/:id/status`

```json
{ "status": "paid" }
```

---

## Field Reference

### Filterable columns per endpoint

| Endpoint        | Filterable columns                                                  |
|-----------------|---------------------------------------------------------------------|
| `/roles`        | `name`, `label`, `position`, `created_at`                           |
| `/users`        | `first_name`, `last_name`, `email`, `phone`, `role_id`, `created_at`, `last_login_at` |
| `/users/:id/address` | `type`, `city`, `zip`, `country`, `is_default`, `created_at`  |
| `/categories`   | `name`, `description`, `parent_id`, `position`, `created_at`        |
| `/products`     | `name`, `sku`, `price`, `vat_rate`, `stock_quantity`, `created_at` |
| `/enumerations` | `type`, `syscode`, `label`, `value`, `position`, `is_active`, `created_at` |
| `/texts`        | `syscode`, `title`, `language`, `is_active`, `created_at`           |
| `/orders`       | `order_number`, `status`, `total_amount`, `currency`, `payment_method`, `user_id`, `created_at` |
| `/invoices`     | `invoice_number`, `status`, `total_amount`, `currency`, `user_id`, `order_id`, `issued_at`, `due_at`, `paid_at` |

---

## Common Patterns & Frontend Recipes

### 1. Paginated table with server-side sort & filter

```js
async function fetchPage({ page, limit, sort, filter }) {
  const params = new URLSearchParams({ page, limit });
  if (sort.length)       params.set('sort',   JSON.stringify(sort));
  if (filter && Object.keys(filter).length) {
    params.set('filter', JSON.stringify(filter));
  }

  const res = await fetch(`/api/products?${params}`, {
    headers: { Authorization: `Bearer ${token}` }
  });
  const json = await res.json();
  // json.data.items   — current page rows
  // json.data.total   — total matching rows
  // json.data.totalPages
  return json.data;
}

// Usage
const data = await fetchPage({
  page: 2,
  limit: 20,
  sort: [{ price: 1 }],
  filter: {
    stock_quantity: { value: 0, operator: 'gt' },
    price: { value: [100, 1000], operator: 'range' }
  }
});
```

### 2. Login and persist token

```js
async function login(email, password) {
  const res = await fetch('/api/auth/login', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, password })
  });
  const json = await res.json();
  if (!json.success) throw new Error(json.message);
  localStorage.setItem('token', json.data.token);
  return json.data.user;
}
```

### 3. Handle validation errors (422)

```js
async function createUser(data) {
  const res = await fetch('/api/users', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Authorization: `Bearer ${token}`
    },
    body: JSON.stringify(data)
  });
  const json = await res.json();

  if (res.status === 422) {
    // json.errors = { email: "...", password: "..." }
    setFormErrors(json.errors);
    return;
  }
  if (!json.success) throw new Error(json.message);
  return json.data;
}
```

### 4. Load all dropdown options from enumerations

```js
async function loadOptions(type) {
  const params = new URLSearchParams({ type, is_active: 1, limit: 100 });
  const res    = await fetch(`/api/enumerations?${params}`, {
    headers: { Authorization: `Bearer ${token}` }
  });
  const json = await res.json();
  return json.data.items.map(e => ({ value: e.syscode, label: e.label }));
}

const orderStatuses  = await loadOptions('order_status');
const paymentMethods = await loadOptions('payment_method');
```

### 5. Create an order

```js
const order = await apiFetch('POST', '/orders', {
  items: [
    { product_id: 10, quantity: 2 },
    { product_id: 42, quantity: 1 }
  ],
  currency: 'CZK',
  payment_method: 'card',
  billing_address_id: 3,
  shipping_address_id: 5
});
// order.data.id — new order ID
// order.data.order_number — generated number
```

### 6. Stock adjustment

```js
// Remove 3 units from stock
await apiFetch('PATCH', `/products/${id}/stock`, { quantity: -3 });

// Receive 100 new units
await apiFetch('PATCH', `/products/${id}/stock`, { quantity: 100 });
```

### 7. CMS text by key

```js
async function getText(syscode, language = 'cs') {
  const res  = await fetch(`/api/texts/by-key/${syscode}?language=${language}`, {
    headers: { Authorization: `Bearer ${token}` }
  });
  const json = await res.json();
  return json.data?.content ?? '';
}
```

### 8. Filter with IS NULL / IS NOT NULL

```js
// Products without any category
const filter = JSON.stringify({ stock_quantity: { operator: 'null' } });

// Users who have ever logged in
const filter2 = JSON.stringify({ last_login_at: { operator: 'notnull' } });
```

### 9. Universal API client helper

```js
const BASE = '/php/php-core/api';

async function apiFetch(method, path, body = null) {
  const options = {
    method,
    headers: { 'Content-Type': 'application/json' }
  };
  const token = localStorage.getItem('token');
  if (token) options.headers.Authorization = `Bearer ${token}`;
  if (body)  options.body = JSON.stringify(body);

  const res  = await fetch(BASE + path, options);
  const json = await res.json();

  if (res.status === 401) {
    localStorage.removeItem('token');
    window.location.href = '/login';
    return;
  }
  if (!json.success) throw Object.assign(new Error(json.message), { errors: json.errors, status: res.status });
  return json;
}
```

---

*Last updated: 2026-05-03*
