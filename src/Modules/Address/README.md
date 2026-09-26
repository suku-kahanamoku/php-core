# Address Module

All HTTP routes require `X-Internal-Key` in the common API middleware, including
routes described below as public (no user login). User role/ownership checks
remain additional requirements.

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
- Missing or invalid application keys are rejected before API initialization.
- Writes always require a Bearer token. A new address belongs to the caller; request `user_id` is not used to assign it to another user.
- `is_default` should keep only one default address per type and user.
