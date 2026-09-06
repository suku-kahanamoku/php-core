# Address Module

Purpose: manage user addresses for billing and shipping.

Read first:
- `AddressApi.php`
- `AddressService.php`
- `AddressRepository.php`

Routes:
- `GET /address`
- `GET /users/:userId/address`
- `GET /address/:id`
- `POST /address`
- `PATCH /address/:id`
- `PUT /address/:id`
- `DELETE /address/:id`

Notes:
- A valid `X-Internal-Key` may read the global list, detail, or a user's addresses.
- Without the internal key, the global list is admin-only and user-scoped reads are self-or-admin.
- Writes always require a Bearer token. A new address belongs to the caller; request `user_id` is not used to assign it to another user.
- `is_default` should keep only one default address per type and user.
