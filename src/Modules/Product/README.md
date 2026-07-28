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
- Public reads, admin writes.
- `data` is a JSON field and can be shallow-merged on patch.
- Keep `category_ids`, `file_ids`, and projection behavior aligned with `API.md`.
