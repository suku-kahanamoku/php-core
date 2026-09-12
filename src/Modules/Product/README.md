# Product Module

Purpose: product catalog, profile probabilities, category/file links, and stock adjustments.

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
- Product-to-profile suitability lives in `product_customer_profile_probability`, not in the `product` table.
- The API field `profile_probabilities` synchronizes `{customer_profile_id, probability_percent, is_target}` rows; values are tenant-checked and probability is limited to 0–100.
- The complete table diagram and column list are in [`../../../CUSTOMER_PROFILE_MODEL.md`](../../../CUSTOMER_PROFILE_MODEL.md).
- Keep projection behavior aligned with `API.md`.
