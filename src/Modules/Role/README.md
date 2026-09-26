# Role Module

All HTTP routes require `X-Internal-Key` in the common API middleware, including
routes described below as public (no user login). User role/ownership checks
remain additional requirements.

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
- List/detail reads use the middleware-verified internal application identity.
- The internal key is read-only here; write operations always require admin.
- Keep role names lowercase and validated against the documented pattern.
