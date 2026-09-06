# Category Module

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
