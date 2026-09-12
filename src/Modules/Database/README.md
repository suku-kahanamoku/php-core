# Database Module

Purpose: shared PDO/database access layer used by all modules.

Read first:
- `Database.php`

Notes:
- This is shared infrastructure, not an HTTP module.
- Keep connection, query helpers, and transaction behavior stable unless the task is explicitly about database access.
- Most module work should start in the module Api, then Service, then Repository layer.
- `migrations/schema.sql` is destructive and belongs only to a fresh database. Existing installations use dated additive migrations after a verified backup.
- The normalized customer-profile schema and its final relation-table names are documented in [`../../../CUSTOMER_PROFILE_MODEL.md`](../../../CUSTOMER_PROFILE_MODEL.md).
- Existing databases run `20260912_customer_profiles.sql` and then the idempotent `20260912_rename_customer_profile_relations.sql`; never substitute `schema.sql` for these production migrations.
