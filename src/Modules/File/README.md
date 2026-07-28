# File Module

Purpose: two-phase file upload, commit, download, preview, and deletion.

Read first:
- `FileApi.php`
- `FileService.php`
- `FileRepository.php`

Routes:
- `GET /files`
- `GET /files/:id`
- `GET /files/:id/download`
- `GET /files/:id/preview`
- `POST /files/upload`
- `POST /files/commit`
- `DELETE /files/:id`

Notes:
- Upload stores the file in temp storage first.
- Commit moves it to permanent storage and creates the DB record.
- Keep soft delete and `?force=true` behavior intact.
