# Employee Holiday Calendar Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build an employee-facing calendar that shows company holidays and each employee's regular holidays with quick links to leave and day-swap requests.

**Architecture:** Add a focused PHP helper and read-only API, a new page, and a focused browser script. Reuse FullCalendar and existing day-swap shift-holiday logic so attendance/day-swap rules stay consistent.

**Tech Stack:** PHP, MySQLi, Bootstrap, FullCalendar, plain JavaScript, Node syntax/unit tests.

---

### Task 1: Test Calendar Event Shaping

**Files:**
- Create: `includes/holiday_calendar_helpers.php`
- Create: `tests/holiday_calendar_helpers_test.php`
- Create: `tests/holiday_calendar_ui_test.js`

- [ ] Write PHP tests for combining company holidays and regular shift holidays.
- [ ] Write JS tests for event styling and summary output.
- [ ] Run tests and verify they fail because the helper/script do not exist yet.

### Task 2: Add Helper and API

**Files:**
- Create: `includes/holiday_calendar_helpers.php`
- Create: `api/holiday_calendar_api.php`

- [ ] Implement event shaping helper.
- [ ] Implement read-only API for the signed-in employee.
- [ ] Reuse `daySwapBuildHolidayOptions()` for regular holidays.

### Task 3: Add Page, Script, Menu, and Styles

**Files:**
- Create: `holiday_calendar.php`
- Create: `assets/js/holiday_calendar.js`
- Modify: `includes/header.php`
- Modify: `includes/footer.php`
- Modify: `assets/style.css`

- [ ] Add the employee-facing page and quick links.
- [ ] Render FullCalendar from the API.
- [ ] Add sidebar navigation and styles.

### Task 4: Verify

**Files:**
- Test: `tests/holiday_calendar_helpers_test.php`
- Test: `tests/holiday_calendar_ui_test.js`

- [ ] Run PHP helper test.
- [ ] Run JS UI test.
- [ ] Lint touched PHP files.
- [ ] Run `node --check assets/js/holiday_calendar.js`.
- [ ] Run `git diff --check`.
