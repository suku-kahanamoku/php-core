# User Module

Purpose: user CRUD, profile data, role assignment, and address lookup.

Read first:
- `UserApi.php`
- `UserService.php`
- `UserRepository.php`

Routes:
- `GET /users`
- `GET /users/:id`
- `POST /users`
- `PATCH /users/:id`
- `PUT /users/:id`
- `DELETE /users/:id`
- `GET /users/:userId/address`

Notes:
- Admin-only for list/create/update/delete.
- Self-or-admin access applies to detail and address lookup routes.
- Keep user role and validation behavior consistent with auth and API docs.
