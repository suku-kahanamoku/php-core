# Copilot Cost & Context Optimization - php-core

This repository now contains lightweight Copilot guidance for faster, more focused agent work.

What this repo now contains:
- `.github/copilot-instructions.md` - always-on repo guidance.
- `.github/instructions/php-core.instructions.md` - lazy-loaded project context.
- `.github/prompts/php-core-context.prompt.md` - reusable prompt for starting tasks with the right scope.

Quick maintainer rules:
1. Keep `.github/copilot-instructions.md` short and stable.
2. Put project-specific context in `.github/instructions/*.instructions.md`.
3. Use reusable prompts for recurring workflows instead of repeating the same setup in chat.
4. Treat `API.md` and `README.md` as the public source of truth for behavior and setup.

Recommended next steps:
- Add module-level `README.md` files under `src/Modules/<Domain>/` if a domain needs more detail.
- Add more focused prompt files for repetitive tasks like API doc sync, test runs, and endpoint checks.
- Keep this checklist updated when new repo-local agent artifacts are added.