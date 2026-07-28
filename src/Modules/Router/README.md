# Router Module

Purpose: HTTP request parsing, route matching, and JSON/HTML response helpers.

Read first:
- `Router.php`
- `Request.php`
- `Response.php`

Notes:
- This is shared infrastructure, not a public API module.
- Keep route registration and response envelope behavior stable.
- Changes here can affect every endpoint, so validate narrowly after edits.
