# AGENTS.md

## Project Overview

This repository is a Thai HR management system running on PHP and MySQL/MariaDB through XAMPP. The UI uses Bootstrap, vanilla JavaScript, and the Sarabun font. User-facing text is primarily Thai.

## Repository Map

- Root `*.php` files are page controllers and rendered screens.
- `api/` contains HTTP endpoints and persistence flows.
- `includes/` contains shared authentication, database, date, attendance, leave, approval, and workflow helpers.
- `assets/js/` contains page behavior and API integrations.
- `assets/css/` contains shared and page-specific styles.
- `tests/` contains focused PHP and Node.js contract/regression tests.
- `database_*.sql` files contain schema additions or migrations.
- `docs/` contains design notes and implementation plans.

## Working Guidelines

1. Trace the complete runtime path before editing: rendered page, JavaScript, API endpoint, helper, SQL query, and schema where applicable.
2. Keep changes focused on the requested workflow. Do not refactor unrelated code or overwrite existing worktree changes.
3. When a field or workflow exists on both employee self-service and HR proxy screens, keep both capture paths consistent.
4. Preserve role checks and HR scope filtering in pages, APIs, reports, and approval queues.
5. Escape rendered user data and use prepared statements for database input. Never expose or commit secrets from `.env` or `includes/db_config.php`.
6. Keep the existing Sarabun/Bootstrap visual language unless a different design is explicitly requested.

## Dates and Thai UI

- Show Thai-friendly dates in the UI, but persist Gregorian `YYYY-MM-DD` values.
- Normalize and validate dates on the server, preferably through `includes/date_helpers.php`.
- Shared Thai date-input behavior lives in `assets/js/utils.js`.
- Use `data-native-date-picker="true"` only when a field must retain the browser's native calendar control.
- Do not rely on client-side validation alone.

## Testing and Verification

Run checks that match the files and behavior changed.

```powershell
# PHP syntax
C:\xampp\php\php.exe -l path\to\changed-file.php

# Focused PHP test
C:\xampp\php\php.exe tests\relevant_test.php

# JavaScript syntax
node --check assets\js\changed-file.js

# Focused JavaScript test
node tests\relevant_test.js

# Whitespace and patch validation
git diff --check
```

For bug fixes, add or update a focused regression test when practical. Verify the actual affected workflow, not only syntax.

## Git Discipline

- Inspect `git status --short` before and after editing.
- Treat pre-existing modified and untracked files as user-owned.
- Stage only task-owned files unless the user explicitly asks to include all changes.
- Review `git diff` and `git diff --cached --check` before committing.
- Do not commit or push unless the user asks for it.
- When the target branch could be `main` or `master`, confirm the live branch and follow the user's latest instruction.

## Completion Standard

A change is complete only when the requested UI behavior and its real save/read path agree, relevant checks pass, and unrelated worktree changes remain untouched. Report the files changed and the verification commands run.
