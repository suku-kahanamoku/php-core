# Order Module

All HTTP routes require `X-Internal-Key` in the common API middleware, including
routes described below as public (no user login). User role/ownership checks
remain additional requirements.

Purpose: public checkout, order listing, order detail, and admin status management.

Read first:
- `OrderApi.php`
- `OrderService.php`
- `OrderRepository.php`

Routes:
- `GET /orders`
- `GET /orders/:id`
- `POST /orders`
- `PATCH /orders/:id/status`
- `DELETE /orders/:id`

Notes:
- Authenticated users see their own orders; admin sees all orders.
- Guest checkout stores an immutable `customer` JSON snapshot and leaves `user_id` null; it does not find or create an account by e-mail.
- Detail data uses the relation name `order_items`.
- Order creation decrements stock in a transaction and calculates totals server-side.
- Status changes and deletion require the admin role.
