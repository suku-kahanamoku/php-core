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
- A valid `X-Internal-Key` may read list/detail and address lookup routes without a Bearer token.
- Without the internal key, list is admin-only and detail/address lookup is self-or-admin.
- The internal key is read-only; create and delete require admin. Owners may update only their own permitted profile fields.
- Only admins may change `email`, `status` and `role_id`; ordinary users may update their own name and phone.
- Keep user role and validation behavior consistent with auth and API docs.
