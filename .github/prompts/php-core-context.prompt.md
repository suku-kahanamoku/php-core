---
description: "Prime a Copilot agent for focused php-core work"
---

Use this prompt when starting any non-trivial task in this repository.

1. Read `README.md` and `API.md` first.
2. Inspect only the nearest module files that control the requested behavior.
3. State one narrow hypothesis and one cheap validation before editing.
4. Keep changes localized and do not widen scope unless the first check fails.
5. Preserve the response envelope, auth rules, franchise scoping, and endpoint contracts.
6. If the task touches docs, keep `README.md` and `API.md` in sync with the implementation.

Expected output for the agent:
- The smallest relevant files to inspect.
- The likely control path.
- The next validation command.
- Any risks that would require a user decision.