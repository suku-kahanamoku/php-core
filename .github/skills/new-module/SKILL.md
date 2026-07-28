---
name: new-module
description: >
  Step-by-step guide for creating a new backend module in src/Modules/<Domain>/.
  Use when the user asks to scaffold, add, or document a new module.
  Based on the existing Text and User modules.
---

# Skill: Create a New Backend Module

Use this guide when the user asks to **create a new module** in `src/Modules/<Domain>/`.
Base the implementation on the closest existing examples, usually `Text` and `User`.

## 1. Start from the nearest existing module

- Use `Text` as the reference for CMS/content-style endpoints, public reads, admin writes, and module-level README layout.
- Use `User` as the reference for CRUD-style endpoints, self/admin access rules, pagination, sorting, and projection handling.
- Do not copy unrelated logic from other domains unless the requested behavior clearly matches it.

## 2. Module folder structure

Create the following files and folders:

```text
src/Modules/<Domain>/
  README.md
  <Domain>Api.php
  <Domain>Service.php
  <Domain>Repository.php
  tests/                       # only if the repo already uses module-local tests here
```

If the module needs a new route entry point, also update:

```text
api/<module>/index.php
```

## 3. Api layer

- Register routes in `<Domain>Api.php` with `registerRoutes(Router $router): void`.
- Keep request parsing, validation, and response formatting in the Api layer.
- Use `Request`, `Response`, `Router`, `Database`, and `Auth` consistently with the existing modules.
- Preserve the project response envelope and HTTP status conventions.
- Validate inputs before calling the service.

## 4. Service layer

- Put business rules, permission checks, and flow coordination into `<Domain>Service.php`.
- Keep the service thin and deterministic.
- Delegate SQL and data access to the repository.
- Do not change schema, auth, franchise scoping, or public API behavior unless the user explicitly asks.

## 5. Repository layer

- Keep SQL queries and persistence logic in `<Domain>Repository.php`.
- Follow the existing repository style from `Text` and `User`.
- Keep database access focused and reusable.

## 6. Module README.md

Create `src/Modules/<Domain>/README.md` with:

- A one-line purpose statement.
- A short "Read first" list pointing to the Api, Service, and Repository files.
- A route summary.
- Notes for access rules, projections, filters, and any important data constraints.

This README is the first file agents should read for module work.

## 7. Update project docs if behavior changes

- If the new module changes public behavior, update `API.md`.
- If setup or usage changes, update `README.md`.
- Keep the docs aligned with the implementation.

## 8. Validation

- Prefer a focused validation step for the touched module.
- Use the nearest test or the smallest executable check that can confirm the new route or behavior.
- Do not widen scope unless the first validation fails.

## 9. Good default output for the agent

When starting a module task, the agent should identify:

- The closest existing module to copy patterns from.
- The exact files to create.
- The route registration point.
- The smallest validation command.
- Any schema, auth, or API-contract risks that need user confirmation.