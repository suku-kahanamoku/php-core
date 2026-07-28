# Templater Module

Purpose: render and preview email templates in the browser.

Read first:
- `TemplaterApi.php`
- `TemplaterService.php`

Routes:
- `GET /templater?template=...`

Notes:
- Returns HTML preview output.
- Template names are sanitized before rendering to avoid path traversal.
- This module is support tooling for email/template work rather than a data CRUD module.
