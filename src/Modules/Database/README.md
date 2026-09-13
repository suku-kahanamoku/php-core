# Database Module

Purpose: shared PDO/database access layer used by all modules.

Read first:
- `Database.php`

Notes:
- This is shared infrastructure, not an HTTP module.
- Keep connection, query helpers, and transaction behavior stable unless the task is explicitly about database access.
- Most module work should start in the module Api, then Service, then Repository layer.
- `migrations/schema.sql` is destructive and belongs only to a fresh database. Existing installations use dated additive migrations after a verified backup.
- The normalized customer-profile schema and its final relation-table names are documented in [`../CustomerProfile/README.md`](../CustomerProfile/README.md).
- Existing databases run `20260912_customer_profiles.sql`, `20260912_rename_customer_profile_relations.sql`, and then the idempotent `20260913_user_customer_profile_position.sql`; never substitute `schema.sql` for these production migrations.
- FAnn databases then run `20260913_fun_product_categories.sql` to create catalogue categories, fill `product_category`, and clear the tenant's legacy `product.kind` values.
- Zoo databases run `20260913_zoo_product_categories.sql` to convert the tenant's product-kind classification while preserving existing animal-category links.
