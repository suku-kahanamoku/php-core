# Customer Profile Module

Purpose: tenant-scoped customer-profile definitions and their ordered questions,
objections, and preferences.

Read first:

- `CustomerProfileApi.php`
- `CustomerProfileService.php`
- `CustomerProfileRepository.php`
- [`../../../CUSTOMER_PROFILE_MODEL.md`](../../../CUSTOMER_PROFILE_MODEL.md)

Routes:

- `GET /customer-profiles`
- `GET /customer-profiles/:id`
- `POST /customer-profiles`
- `PATCH /customer-profiles/:id`
- `PUT /customer-profiles/:id`
- `DELETE /customer-profiles/:id`

Notes:

- Every route requires the admin role.
- `customer_profile` owns the profile definition; it is not an enumeration and is not embedded in `user`.
- `customer_profile_question`, `customer_profile_objection`, and `customer_profile_preference` contain ordered child rows and are deleted by cascade with their profile.
- Supplying `questions`, `objections`, or `preferences` synchronizes that complete child collection. Omitting a collection leaves it unchanged.
- Users connect through `user_customer_profile`; products connect through `product_customer_profile_probability`.
- All profile, user, product, and relation lookups remain scoped to the current `franchise_code`.
