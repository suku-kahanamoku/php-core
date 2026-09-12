# User Module

Purpose: user CRUD, customer-profile assignments, role assignment, and address lookup.

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
- `user` contains no customer-profile column. The M:N assignments live only in `user_customer_profile`.
- Admins synchronize assignments through the API field `profiles`; each item contains `customer_profile_id` and a unique per-user `priority` where `1` is highest.
- Profile reads require the `profiles` projection. Filtering users by one assigned profile uses the virtual filter `profile_id`.
- The complete table diagram and column list are in [`../../../CUSTOMER_PROFILE_MODEL.md`](../../../CUSTOMER_PROFILE_MODEL.md).
- Keep user role and validation behavior consistent with auth and API docs.
