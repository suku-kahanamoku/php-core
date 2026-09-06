# File Module

Purpose: two-phase file upload, commit, authorized content delivery, and deletion.

Read first:
- `FileApi.php`
- `FileService.php`
- `FileRepository.php`

Routes:
- `GET /files`
- `GET /files/:id`
- `GET /files/content?path=...`
- `GET /files/temp?path=...`
- `POST /files/upload`
- `POST /files/commit`
- `DELETE /files/:id`

Notes:
- Every route requires Bearer authentication. Non-admin list/read access is restricted to owned files or otherwise authorized public files.
- Upload stores the file in temp storage first.
- Temp paths are scoped to the authenticated user; commit cannot consume another user's upload.
- Commit moves the file to tenant-scoped permanent storage and creates the DB record with `user_id`.
- Raw web access to `/temp` and `/files` is blocked; content is served only through the API authorization checks.
- Delete is admin-only. Keep soft delete and `?force=true` behavior intact.
