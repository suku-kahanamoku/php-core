# Router Module

All HTTP routes require `X-Internal-Key` in the common API middleware, including
routes described below as public (no user login). User role/ownership checks
remain additional requirements.

Purpose: HTTP request parsing, route matching, and JSON/HTML response helpers.

Read first:
- `Router.php`
- `Request.php`
- `Response.php`

Notes:
- This is shared infrastructure, not a public API module.
- Keep route registration and response envelope behavior stable.
- Every request must resolve a tenant from the configured host mapping. Unknown hosts return 403; `X-Internal-Key` does not bypass tenant resolution.
- `X-Forwarded-Host` is intended for trusted reverse proxies/Nuxt servers and must match `FRANCHISE_CODES`.
- Changes here can affect every endpoint, so validate narrowly after edits.
