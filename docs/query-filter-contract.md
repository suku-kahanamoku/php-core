# Query filter contract for new endpoints

> **Status: mandatory wire format for php-core clients.**
> Existing endpoints may retain older inputs and response shapes for backwards
> compatibility, but current clients send the single format below.

---

## 1. Parameters

| Parameter    | Purpose                          | Format |
|--------------|----------------------------------|--------|
| `q`          | Filter                           | JSON object (see below) |
| `sort`       | Order                            | JSON array `[{"column":1},-1]` |
| `projection` | Field selection                  | Comma-separated names, dot-notation for relations |
| `page`       | 1-based page number              | integer ≥ 1 |
| `limit`      | Page size                        | integer ≥ 1 |

For new endpoints there is exactly **one** filter parameter: `q`. A
search box sends a JSON `q` filter. Existing endpoint adapters may accept
their documented legacy inputs and translate them before calling a repository.

---

## 2. Filter grammar (`q`)

`q` is a JSON object. Every key is a column; the value describes the condition.

### 2.1 Client wire format: MongoDB-compatible operator form

```json
{ "name": { "$regex": "nov" } }
{ "id":   { "$in": [1, 2, 3] } }
{ "price":{ "$gte": 100 } }
{ "status": { "$ne": "cancelled" } }
```

### 2.2 Backend compatibility only: native `value` + `operator` form

```json
{ "name":   { "value": "nov", "operator": "regex" } }
{ "price":  { "value": [100, 500], "operator": "range" } }
{ "deleted_at": { "operator": "null" } }
```

### 2.3 Scalar equality (implicit `eq`)

```json
{ "status": "active", "published": 1 }
```

### 2.4 Operators

| MongoDB key | Native operator | SQL | Value |
|---|---|---|---|
| `$eq` | `eq` | `col = ?` | scalar (default) |
| `$ne` | `neq` / `ne` | `col != ?` | scalar |
| `$lt` | `lt` | `col < ?` | scalar |
| `$lte` | `lte` | `col <= ?` | scalar |
| `$gt` | `gt` | `col > ?` | scalar |
| `$gte` | `gte` | `col >= ?` | scalar |
| — | `range` | `col BETWEEN ? AND ?` | `[min, max]`, exactly 2 |
| `$regex` | `regex` | `col LIKE ? ESCAPE '!'` | string (contains) |
| — | `start` | `col LIKE ? ESCAPE '!'` | string (starts-with) |
| — | `end` | `col LIKE ? ESCAPE '!'` | string (ends-with) |
| `$in` | `in` | `col IN (?, …)` | non-empty array |
| — | `null` | `col IS NULL` | — |
| — | `notnull` | `col IS NOT NULL` | — |

Multiple keys are combined with `AND`. Unknown columns and unknown operators
are **dropped** (never interpolated). Invalid JSON or `{}` yields an empty
filter, i.e. no restriction.

### 2.5 LIKE values are escaped

`regex`, `start` and `end` compile to `LIKE ? ESCAPE '!'` and the bound value
escapes `!`, `%` and `_`. A user typing `%` or `_` searches for that literal
character; it can never broaden the query.

### 2.6 Dot notation

| Form | Meaning | Requirement |
|---|---|---|
| `data.year` | JSON sub-field → `JSON_UNQUOTE(JSON_EXTRACT(alias.data, '$.year'))` | left column listed in the caller's `$jsonCols` |
| `relation.column` | column on another table, emitted as `alias.column` | the table and the column MUST be listed in the repository's `$relations` allowlist; otherwise the condition is dropped |

Arbitrary cross-table dot notation is forbidden. A repository opts a relation in
explicitly:

```php
private const FILTER_RELATIONS = [
    'user' => ['alias' => 'u', 'columns' => ['id', 'first_name', 'last_name', 'email']],
];
// ...
SQL_FILTER($q, 'o', $this->_jsonCols, self::FILTER_RELATIONS);
```

---

## 3. Server-side allowlists are mandatory

The grammar is safe, but each endpoint still restricts **which** columns may be
filtered/sorted, using `App\Utils\QueryPolicy`:

```php
use App\Utils\QueryPolicy;

private const FILTERS = ['id', 'name', 'kind', 'published'];
private const SORTS   = ['id', 'name', 'created_at'];

$filter = QueryPolicy::filter($q, self::FILTERS, ['tenant_column' => $code]);
$sort   = QueryPolicy::sort($sortParam, self::SORTS, 'created_at DESC');
```

`QueryPolicy` also enforces visibility (e.g. tenants, `deleted = 0` for
non-admins). Never pass a raw `q` from the client straight into `SQL_FILTER`
without a policy allowlist.

---

## 4. Sorting and projection

- Clients send `sort` as a JSON array of single-key objects: `[{"name":1},{"created_at":-1}]`.
  `1` = ASC, `-1` = DESC. Column names are validated; unknown names fall back to
  the repository default. Legacy `sort=name DESC` is tolerated by `SQL_SORT()`
  but new clients MUST send the JSON array.
- `projection=id,name,price` selects fields; `projection=user.email` selects a
  relation sub-field and triggers the JOIN. System fields (`id`, `created_at`,
  `updated_at`) are always returned.

---

## 5. Response envelope

List endpoints return the shared envelope and put rows under `data`:

```json
{ "success": true, "message": "OK", "data": [ … ], "meta": { "total": 42, "page": 1, "limit": 20, "totalPages": 3, "skip": 0 } }
```

`items` (or any other key) is not used by new list endpoints. `Response::successList()` already
reads `$data['data']`; repositories must return that shape.

---

## 6. Existing endpoint compatibility

- `/etymolog/public/names` accepts both JSON `q` and its older plain-text
  `q`, and still returns its established dossier envelope with `items`.
  The `kind` parameter remains supported for older callers; current clients put
  `kind` inside `q`.
- `/files` accepts JSON `q`, older plain-text `q`, and the older
  `{"search":"..."}` filter. It also accepts legacy sort aliases such as
  `created_desc`. These inputs are translated and validated before SQL.

Do not add these exceptions to new endpoints. Do not emit a relation column
that is absent from the repository allowlist, or concatenate unescaped user
text into a LIKE expression.

---

## 7. Checklist for a new endpoint

1. Accept `q`, `sort`, `projection`, `page`, `limit` — nothing bespoke.
2. Define `FILTERS` / `SORTS` and pass them through `QueryPolicy::filter()` /
   `QueryPolicy::sort()`, including tenant and soft-delete constraints.
3. Call `SQL_FILTER($q, $alias, $jsonCols, $relations)` and `SQL_SORT(...)`.
4. Never bind `LIKE` values by hand; use the `regex`/`start`/`end` operators.
5. Return rows under `data` via the shared response envelope.
6. Reject non-JSON `q` with `422` at the service/validation boundary.
7. Frontends encode the object with `JSON.stringify(...)` and URL query
   encoding. A free-text search box maps to
   `{ "<column>": { "$regex": <text> } }`. Nuxt form-module legacy
   `value/operator` values are converted by each server-side `phpApiFetch`
   proxy before reaching php-core; Astro Etymolog constructs the same wire
   format in its server-side provider. Never send plain-text `q`, an extra
   `kind` query parameter, or text `sort` from current clients.
