# Address Module

Purpose: manage user addresses for billing and shipping.

Read first:
- `AddressApi.php`
- `AddressService.php`
- `AddressRepository.php`

Routes:
- `GET /users/:userId/address`
- `GET /addresses/:id`
- `POST /addresses`
- `PATCH /addresses/:id`
- `PUT /addresses/:id`
- `DELETE /addresses/:id`

Notes:
- Access is self or admin for reads and updates.
- `user_id` from the request is only honored for admin callers.
- `is_default` should keep only one default address per type and user.
