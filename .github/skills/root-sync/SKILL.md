---
name: root-sync
description: >
  Keep root-level project and Copilot artifacts aligned.
  Use when changing repository-wide behavior, root docs, root config, or .github guidance files.
---

# Skill: Sync Root-Level Artifacts

Use this guide when a change impacts repository-wide behavior.

## 1. Scope

Apply this skill for changes in root-level files such as:

- `README.md`
- `API.md`
- `.gitignore`
- `bootstrap.php`
- `composer.json`
- `.github/copilot-instructions.md`
- `.github/COPILOT-OPTIMIZATION.md`
- `.github/instructions/php-core.instructions.md`
- `.github/prompts/*.prompt.md`
- `.github/skills/*/SKILL.md`

## 2. Root sync rules

- Keep `README.md` and `API.md` aligned with actual behavior.
- Keep `.github` guidance aligned with real repository structure and workflows.
- If `.github` tracking rules change, ensure `.gitignore` still allows required AI artifacts.
- Keep always-on instructions short; move detailed workflows to prompts and skills.

## 3. When to update which file

- Update `README.md` for setup, architecture, tests, and onboarding flow.
- Update `API.md` for endpoint contracts and request/response semantics.
- Update `.github/copilot-instructions.md` for stable repository rules.
- Update `.github/instructions/php-core.instructions.md` for task routing and file reading order.
- Update `.github/prompts/` and `.github/skills/` for repeatable workflows.

## 4. Validation checklist

- Confirm docs describe current behavior and not planned behavior.
- Confirm references to paths and commands are still valid.
- Run focused checks after edits (for example `git diff --check` and targeted status checks).

## 5. Good output for the agent

When applying root sync, the agent should identify:

- Which root files changed.
- Which dependent root docs or guidance files need matching updates.
- Whether `.gitignore` still tracks required `.github` artifacts.
- The smallest validation command proving the sync is complete.