# Product Module

Purpose: product catalog, profile probabilities, alternatives, category/file links, and stock adjustments.

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
- `alternatives` synchronizes ordered rows shaped as `{alternative_product_id, position}` in `product_alternative`; IDs are tenant-validated and a product cannot reference itself.
- Product responses enrich each `alternatives` row with the linked product's name, SKU, price, availability, and data.
- FAnn product classification uses `category` and `product_category`; migration `20260913_fun_product_categories.sql` converts its legacy `product.kind` values and clears them.
- Zoo product classification also uses `category` and `product_category`; migration `20260913_zoo_product_categories.sql` preserves animal-category links, adds product-category links, and clears the tenant's legacy `product.kind` values.
- Product-to-profile suitability lives in `product_customer_profile_probability`, not in the `product` table.
- The API field `profile_probabilities` synchronizes `{customer_profile_id, probability_percent}` rows; values are tenant-checked and probability is limited to 0–100.
- The complete table diagram and column list are in [`../CustomerProfile/README.md`](../CustomerProfile/README.md).
- Keep projection behavior aligned with `API.md`.
