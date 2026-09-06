# Text Module

Purpose: multilingual CMS content blocks keyed by syscode and language.

Read first:
- `TextApi.php`
- `TextService.php`
- `TextRepository.php`

Routes:
- `GET /texts`
- `GET /texts/:id`
- `GET /texts/by-key/:syscode`
- `POST /texts`
- `PATCH /texts/:id`
- `PUT /texts/:id`
- `DELETE /texts/:id`

Notes:
- Anonymous reads expose only published public text fields; an admin Bearer token can access the full tenant record.
- All writes require the admin role.
- Keep `language`, `syscode`, and `published` handling aligned with the API docs.
