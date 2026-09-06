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
- Anonymous reads expose only allowed public types and fields. Admin Bearer requests can access the complete tenant codebook.
- All writes require the admin role.
- Keep `type`, `syscode`, and `value` handling consistent with the API contract.
