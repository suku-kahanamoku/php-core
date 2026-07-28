---
name: docs-sync
description: >
  Keep API.md, README.md, and module README files aligned after code changes.
  Use when changing public behavior, endpoints, auth, validation, or setup.
---

# Skill: Sync Documentation After Code Changes

Use this guide when a code change affects public behavior or developer-facing setup.

## 1. Treat docs as part of the change

- Update `API.md` when endpoint behavior, request/response shapes, auth rules, filtering, sorting, projection, or status codes change.
- Update `README.md` when setup, local development, testing, or repo workflow changes.
- Update `src/Modules/<Domain>/README.md` when a module's purpose, route map, or local usage notes change.

## 2. Compare code against the docs

- Read the touched module Api, Service, and Repository files first.
- Compare the actual behavior with `API.md` and the module README.
- If the docs already describe the current behavior correctly, do not rewrite them.

## 3. Keep documentation minimal and accurate

- Prefer short, factual edits over expanding the docs with speculative detail.
- Keep examples aligned with the current code paths.
- Do not document behavior that is not implemented.

## 4. Common triggers for this skill

- Adding or changing an endpoint.
- Changing validation, required fields, or default values.
- Changing auth, permissions, or franchise scoping.
- Changing projection, filtering, pagination, or sorting.
- Changing setup, tests, or repo structure.

## 5. Validation

- After editing docs, run the smallest relevant check for the touched files.
- Prefer a focused test or a quick diff/format check over broad validation.
