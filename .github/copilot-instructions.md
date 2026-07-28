# php-core Copilot Instructions

- Read `README.md` and `API.md` before making changes.
- Keep changes minimal and localized to the affected module or shared layer.
- Follow the existing 3-layer pattern: Api -> Service -> Repository.
- Do not change schema, auth, franchise scoping, or public API behavior unless the user explicitly asks.
- Preserve the existing JSON response envelope and HTTP status conventions.
- Prefer targeted validation over broad exploration.
- Never use inline `new \\ClassName()`; import the class and instantiate it via `use`.
- If code and docs diverge, update the docs only when the code change is intentional.
