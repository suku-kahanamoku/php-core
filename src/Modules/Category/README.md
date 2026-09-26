# Category Module

All HTTP routes require `X-Internal-Key` in the common API middleware, including
routes described below as public (no user login). User role/ownership checks
remain additional requirements.

Purpose: manage product categories and category trees.

Read first:
- `CategoryApi.php`
- `CategoryService.php`
- `CategoryRepository.php`

Routes:
- `GET /categories`
- `GET /categories/:id`
- `POST /categories`
- `PATCH /categories/:id`
- `PUT /categories/:id`
- `DELETE /categories/:id`

Notes:
- Anonymous list/detail reads expose only non-deleted published categories and their public product data; an admin Bearer token can read unpublished records.
- Detail responses can include nested products.
- `syscode` is the machine identifier for filtering and linking.
- Writes require the admin role. Deletion is refused while active products are linked.
