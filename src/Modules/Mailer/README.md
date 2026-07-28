# Mailer Module

Purpose: send transactional email and project-specific notification templates.

Read first:
- `MailerApi.php`
- `MailerService.php`

Routes:
- `GET /mailer`
- `POST /mailer`
- `POST /mailer/newsletter`
- `GET /mailer/test`
- `GET /mailer/list`

Notes:
- This module is support-oriented and works with email templates under `emails/`.
- It uses the franchise code to resolve template prefixes where needed.
- Keep attachment resolution and admin contact email behavior stable.
