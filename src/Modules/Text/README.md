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
- Public reads, admin writes.
- Keep `language`, `syscode`, and `is_active` handling aligned with the API docs.
