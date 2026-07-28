# Database Module

Purpose: shared PDO/database access layer used by all modules.

Read first:
- `Database.php`

Notes:
- This is shared infrastructure, not an HTTP module.
- Keep connection, query helpers, and transaction behavior stable unless the task is explicitly about database access.
- Most module work should start in the module Api, then Service, then Repository layer.
