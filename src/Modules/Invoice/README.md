# Invoice Module

All HTTP routes require `X-Internal-Key` in the common API middleware, including
routes described below as public (no user login). User role/ownership checks
remain additional requirements.

Purpose: generate invoices from orders and manage invoice lifecycle.

Read first:
- `InvoiceApi.php`
- `InvoiceService.php`
- `InvoiceRepository.php`

Routes:
- `GET /invoices`
- `GET /invoices/:id`
- `POST /invoices`
- `PATCH /invoices/:id/status`
- `PATCH /invoices/:id/files`
- `DELETE /invoices/:id`

Notes:
- List/detail require Bearer authentication; admins see all invoices and ordinary users only their own.
- Creation requires the middleware-verified `X-Internal-Key`. Status changes, file synchronization, and deletion remain admin-only.
- Invoice creation copies key fields from the source order.
- Invoice detail responses include items and file links when requested.
