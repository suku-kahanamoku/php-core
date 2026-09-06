# Product Module

Purpose: product catalog, projections, category/file links, and stock adjustments.

Read first:
- `ProductApi.php`
- `ProductService.php`
- `ProductRepository.php`

Routes:
- `GET /products`
- `GET /products/:id`
- `POST /products`
- `PATCH /products/:id`
- `PUT /products/:id`
- `DELETE /products/:id`
- `PATCH /products/:id/stock`

Notes:
- Anonymous reads expose only non-deleted published products and public relations; admin Bearer requests can read unpublished records.
- All writes and stock adjustments require the admin role.
- `data` is a JSON field and can be shallow-merged on patch.
- `category_ids` and `file_ids` are validated against the current tenant before links are written.
- Keep projection behavior aligned with `API.md`.
