# Role Module

Purpose: role management, ordering, and user role assignment support.

Read first:
- `RoleApi.php`
- `RoleService.php`
- `RoleRepository.php`

Routes:
- `GET /roles`
- `GET /roles/:id`
- `POST /roles`
- `PATCH /roles/:id`
- `PUT /roles/:id`
- `DELETE /roles/:id`

Notes:
- Read access follows the router/auth configuration in the project.
- Write operations require admin.
- Keep role names lowercase and validated against the documented pattern.
