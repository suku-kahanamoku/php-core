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
- List and detail reads are public in the documented API.
- Detail responses can include nested products.
- `syscode` is the machine identifier for filtering and linking.
