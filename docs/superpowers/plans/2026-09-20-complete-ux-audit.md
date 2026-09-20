# Complete UX audit fixes implementation plan

> For agentic workers: Use superpowers:executing-plans to implement each task in this session.

**Goal:** Close the remaining items in the approved 20 September UX report across employee, manager, HR and admin flows.
**Architecture:** Keep existing pages, Bootstrap/Sarabun, APIs and server authorization. Add reusable controls only for behavior shared by several existing screens.
**Tech Stack:** PHP, MariaDB, vanilla JavaScript, Bootstrap, DataTables.
**Spec:** .impeccable/critique/2026-09-20T00-18-36Z__includes-header-php.md

## Constraints and review focus
- Preserve HR union scope and employee-only credential policy; never expose secrets or real personnel in test evidence.
- Preserve manual report loading and existing URLs; distinguish edited filters from applied results.
- Keep request decisions attached to a full request summary, preserve rejection drafts, and prevent duplicate writes.
- Verify long Thai text, empty queues after deleting the last row, rapid filter changes, keyboard focus on mobile resize, and dates after shared date enhancement.
- Local fixture verification does not certify production or real-user usability.

## Implementation
- [x] Navigation/login/accessibility: compact login, keyboard sidebar with state sync and close control, source label associations, role-specific attendance/proxy/account entry points. Check at 320/390/1365 pixels with keyboard.
- [x] Dashboard: place actual scoped approval queues and routine shortcuts before organization statistics; expose all personal request histories.
- [x] Approval/proxy: show escaped full name, request number/type/dates/duration/reason and existing authorized attachments; guard submit while pending and preserve drafts. Test double activation and API error recovery.
- [x] Tables/reports: preserve page/search when refreshing identical filters, reset changed filters, recover last-page depletion, show applied filter snapshots only after successful report response. Add behavioral Node regression tests.
- [x] Policy follow-ups: resolve inert remember checkbox and pending-HR withdrawal with user; apply atomic cancellation transition if enabled and test API behavior in isolated MariaDB.
- [x] Verification: focused red/green tests, complete suite against isolated database, PHP/JS syntax, browser role/mobile checks, independent review, diff check, and audit closure record.

No production deployment is included. Existing report recommendations and the user's latest instruction authorize implementation; no repeated approval gate is needed.