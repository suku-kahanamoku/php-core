# User Module

All HTTP routes require `X-Internal-Key` in the common API middleware, including
routes described below as public (no user login). User role/ownership checks
remain additional requirements.

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
- Missing or invalid application keys are rejected before API initialization.
- The internal key is read-only; create and delete require admin. Owners may update only their own permitted profile fields.
- Only admins may change `email`, `status` and `role_id`; ordinary users may update their own name and phone.
- `user` contains no customer-profile column. The M:N assignments live only in `user_customer_profile`.
- Admins synchronize assignments through the API field `profiles`; each item contains `customer_profile_id` and a unique per-user `position`, where `1` is first.
- Profile reads require the `profiles` projection. Filtering users by one assigned profile uses the virtual filter `profile_id`.
- The complete table diagram and column list are in [`../CustomerProfile/README.md`](../CustomerProfile/README.md).
- Keep user role and validation behavior consistent with auth and API docs.
