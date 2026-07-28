---
name: module-endpoint-change
description: >
  Step-by-step guide for changing existing backend endpoints in src/Modules/<Domain>/.
  Use when modifying CRUD handlers, request validation, access rules, or projections.
  Based on the existing Text and User modules.
---

# Skill: Change an Existing Backend Endpoint

Use this guide when the user asks to modify or extend an existing module endpoint.
Base the work on the closest existing examples, usually `Text` and `User`.

## 1. Start from the nearest module and route

- Use `Text` as the reference for CMS-like endpoints, language-aware data, and docs-friendly route behavior.
- Use `User` as the reference for CRUD-style endpoints, admin/self access checks, pagination, filtering, and projection handling.
- Start from the exact route handler that owns the behavior, not from a broad search.

## 2. Find the control path

- Inspect the module Api first.
- Follow the call into Service.
- Only then inspect Repository if SQL or persistence behavior is involved.
- Keep the change localized to the smallest layer that actually controls the behavior.

## 3. Common endpoint change types

- Add a new route to an existing module.
- Change validation for an existing route.
- Adjust auth or role checks.
- Update list, get, create, update, replace, delete, or custom action behavior.
- Change pagination, sorting, filtering, or projection handling.

## 4. Edit rules for module endpoints

- Preserve the response envelope and HTTP status conventions.
- Do not change schema, auth rules, franchise scoping, or public API behavior unless explicitly requested.
- Use the existing `Request`, `Response`, `Router`, `Database`, and `Auth` patterns from the repo.
- Keep validation near the Api layer and business rules in the Service layer.

## 5. Update module README when the route map changes

- Add or adjust the route summary in `src/Modules/<Domain>/README.md`.
- Keep the "Read first" list accurate.
- Mention any important constraints a future agent needs to know.

## 6. Validation

- Prefer a focused validation step for the touched route or module.
- Use the smallest executable check that exercises the changed handler.
- If the first validation fails, fix the local defect before widening scope.

## 7. Good output for the agent

When starting endpoint work, the agent should identify:

- The exact route handler.
- The Service method that owns the business logic.
- The Repository query or transaction, if any.
- The module README entry that needs updating.
- The smallest validation command.
