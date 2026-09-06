# Auth Module

Purpose: bearer token authentication, registration, logout, password management, reset flow, and OAuth login.

Read first:
- `AuthApi.php`
- `AuthService.php`
- `Auth.php`
- `UserTokenRepository.php`

Routes:
- `POST /auth/login`
- `POST /auth/register`
- `POST /auth/logout`
- `GET /auth/me`
- `POST /auth/change-password`
- `POST /auth/reset-password`
- `POST /auth/complete-reset`
- `POST /auth/oauth`

Notes:
- Public endpoints are login, register, reset-password, and complete-reset. Login, registration and reset flows are rate-limited.
- OAuth is server-to-server only and requires `X-Internal-Key`; the identity is bound by provider and provider subject, not trusted by email alone.
- Password reset creates a short-lived, one-time token; completion requires `token` and `new_password`.
- Logout invalidates the current bearer token.
- Keep bearer token and franchise behavior aligned with `README.md` and `API.md`.
