# Order Module

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
- Guest checkout is supported.
- Order creation decrements stock in a transaction and calculates totals server-side.
