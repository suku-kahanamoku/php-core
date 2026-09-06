# Mailer Module

Purpose: send transactional email and project-specific notification templates.

Read first:
- `MailerApi.php`
- `MailerService.php`

Routes:
- `POST /mailer/send`
- `POST /mailer`
- `POST /mailer/newsletter`
- `GET /mailer/test`
- `GET /mailer/list`

Notes:
- `POST /mailer` (contact form) and `/mailer/newsletter` are public, purpose-specific, validated, and rate-limited.
- Generic `/mailer/send`, `/mailer/test`, and `/mailer/list` require an admin Bearer token or valid `X-Internal-Key`; `GET /mailer` returns 405.
- This module is support-oriented and works with email templates under `emails/`.
- It uses the franchise code to resolve template prefixes where needed.
- Attachments are accepted only from the current tenant's permanent file directory.
