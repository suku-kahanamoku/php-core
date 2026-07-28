# Invoice Module

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
- Admin-only module.
- Invoice creation copies key fields from the source order.
- Invoice detail responses include items and file links when requested.
