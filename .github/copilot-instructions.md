# php-core Copilot Instructions

- Read `README.md` and `API.md` before making changes.
- Read `CUSTOMER_PROFILE_MODEL.md` before changing customer-profile tables or relations.
- Keep changes minimal and localized to the affected module or shared layer.
- Follow the existing 3-layer pattern: Api -> Service -> Repository.
- Do not change schema, auth, franchise scoping, or public API behavior unless the user explicitly asks.
- Preserve the existing JSON response envelope and HTTP status conventions.
- Prefer targeted validation over broad exploration.
- Never use inline `new \\ClassName()`; import the class and instantiate it via `use`.
- If customer-profile code and docs diverge after an intentional change, update `README.md`, `API.md`, the affected module READMEs, and `CUSTOMER_PROFILE_MODEL.md` together.
