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
- List/detail reads require either an admin Bearer token or a valid `X-Internal-Key`.
- The internal key is read-only here; write operations always require admin.
- Keep role names lowercase and validated against the documented pattern.
