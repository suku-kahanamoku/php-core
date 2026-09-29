# Database Module

Purpose: shared PDO/database access layer used by all modules.

Read first:
- `Database.php`

Notes:
- This is shared infrastructure, not an HTTP module.
- Keep connection, query helpers, and transaction behavior stable unless the task is explicitly about database access.
- Most module work should start in the module Api, then Service, then Repository layer.
- `migrations/schema.sql` contains repeatable shared DDL only: missing tables, columns, indexes and constraints. Apply it before a product schema.
- Bootstrap/demo data live in product seeds (`zoo_seed.sql`, `fann_seed.sql`, etc.). `schema_seed.sql` is the optional original Zaječí dataset. Seeds preserve existing records.
- Installation order: [`migrations/README.md`](../../../../migrations/README.md).
- The normalized customer-profile model is documented in [`../CustomerProfile/README.md`](../CustomerProfile/README.md).
- Shared schema includes OpenAI Vector Store mappings. The product mapping intentionally has no product FK: it survives a hard delete until synchronization removes the remote file.
