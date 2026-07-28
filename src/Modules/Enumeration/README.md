# Enumeration Module

Purpose: system codebook and lookup tables such as statuses, payment methods, currencies, and VAT rates.

Read first:
- `EnumerationApi.php`
- `EnumerationService.php`
- `EnumerationRepository.php`

Routes:
- `GET /enumerations`
- `GET /enumerations/types`
- `GET /enumerations/:id`
- `POST /enumerations`
- `PATCH /enumerations/:id`
- `PUT /enumerations/:id`
- `DELETE /enumerations/:id`

Notes:
- Public reads, admin writes.
- Keep `type`, `syscode`, and `value` handling consistent with the API contract.
