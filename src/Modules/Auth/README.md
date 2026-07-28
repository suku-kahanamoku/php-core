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
- `POST /auth/oauth`

Notes:
- Public endpoints are login, register, reset-password, and oauth.
- Logout invalidates the current bearer token.
- Keep bearer token and franchise behavior aligned with `README.md` and `API.md`.
