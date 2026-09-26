# Mailer Module

All HTTP routes require `X-Internal-Key` in the common API middleware, including
routes described below as public (no user login). User role/ownership checks
remain additional requirements.

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
- Generic `/mailer/send`, `/mailer/test`, and `/mailer/list` require the middleware-verified `X-Internal-Key`; `GET /mailer` returns 405.
- This module is support-oriented and works with email templates under `emails/`.
- It uses the franchise code to resolve template prefixes where needed.
- Attachments are accepted only from the current tenant's permanent file directory.
- SMTP uses `MAILER_SMTP_HOST`, `MAILER_SMTP_USER`, `MAILER_SMTP_PASS`,
  `MAILER_SMTP_PORT`, `MAILER_SMTP_AUTH` and `MAILER_SMTP_SECURE`. Every value
  can be overridden per tenant, for example with `COLLEGAS_`.
- Production defaults to authenticated STARTTLS (`MAILER_SMTP_AUTH=true`,
  `MAILER_SMTP_SECURE=tls`). For local Mailpit use host `127.0.0.1`, port
  `1025`, auth `false` and secure `none`; never use this transport setting in
  production.
