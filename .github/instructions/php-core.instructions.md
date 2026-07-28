# php-core - Project Context

These instructions are loaded only when a task is clearly about this repository.

## Where to look first

- `README.md` for setup, architecture, and repository conventions.
- `API.md` for endpoint contracts, filters, projection rules, and response shapes.
- `src/Modules/<Domain>/` for module logic.
- `api/<module>/index.php` for route entry points.
- `tests/api_test.php` for the main executable test suite.

## Repository shape

- The API is a multi-tenant PHP 8.1+ REST service.
- Shared behavior lives in `bootstrap.php` and `src/Modules/Router`, `Database`, `Middleware`, and `Utils`.
- Domain code follows `Api -> Service -> Repository`.
- Public behavior is documented in `API.md`; keep code aligned with it.

## Working rules for agents

- Start from the nearest concrete file or failing behavior.
- Prefer the smallest change that fixes the actual control path.
- Avoid unrelated refactors and broad cleanup unless the user asks.
- Do not alter DB schema, auth rules, franchise resolution, or endpoint contracts without explicit request.
- Use the project style rule: no inline `new \\ClassName()`; import the class first.
- After edits, run a focused validation step for the touched slice.

## Good task boundaries

- For endpoint work, inspect the relevant module API, service, and repository files only.
- For docs changes, keep `README.md` and `API.md` consistent with the implementation.
- For bugs, prefer a nearby test or an existing request flow as the cheapest validation.
