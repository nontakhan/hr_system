# Holiday Calendar Approved Leave Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Show approved day-based leave on the employee holiday calendar with a distinct color and clickable details.

**Architecture:** Extend the existing read-only holiday calendar API to fetch the signed-in employee's approved leave rows for the selected month, expand date ranges into per-day calendar events, and merge them with existing company and regular holiday events. Keep presentation logic in `assets/js/holiday_calendar.js` and add focused helper/UI tests before implementation.

**Tech Stack:** PHP 8/XAMPP, MySQLi, FullCalendar, vanilla JavaScript, Node source-level tests.

---

### Task 1: Helper Contract

**Files:**
- Modify: `tests/holiday_calendar_helpers_test.php`
- Modify: `includes/holiday_calendar_helpers.php`

- [ ] Add a failing helper test that calls `holidayCalendarBuildEvents($company, $regular, $approvedLeaves)` and expects an `approved_leave` event plus summary count.
- [ ] Implement `holidayCalendarNormalizeLeaveDateRange()` and `holidayCalendarBuildApprovedLeaveEvents()` to expand approved leave rows across the selected month.
- [ ] Update `holidayCalendarBuildEvents()` to accept approved leave rows as an optional third argument and keep company holiday cell priority intact.
- [ ] Update `holidayCalendarBuildSummary()` to count `approved_leave`.
- [ ] Run `C:\xampp\php\php.exe tests\holiday_calendar_helpers_test.php`.

### Task 2: API Data Flow

**Files:**
- Modify: `api/holiday_calendar_api.php`
- Modify: `includes/holiday_calendar_helpers.php`

- [ ] Add `holidayCalendarFetchApprovedLeavesForMonth($mysqli, $employeeId, $month)` with `status IN ('approved','pending_cancel_hr')` and `(request_unit IS NULL OR request_unit <> 'hour')`.
- [ ] Include leave type name, reason, total days, status, and original range fields in the returned rows.
- [ ] Pass fetched leaves into `holidayCalendarBuildEvents()`.
- [ ] Run PHP lint for the touched PHP files.

### Task 3: Calendar UI

**Files:**
- Modify: `tests/holiday_calendar_ui_test.js`
- Modify: `holiday_calendar.php`
- Modify: `assets/js/holiday_calendar.js`
- Modify: `assets/style.css`

- [ ] Add failing JS tests for approved leave colors, day class, summary label, and detail HTML.
- [ ] Add the approved leave legend item in `holiday_calendar.php`.
- [ ] Add JS color mapping, day class mapping, four-card summary layout, and `eventClick` detail display for leave events.
- [ ] Add CSS for approved leave dot, summary icon tone, and cell color.
- [ ] Run `node tests\holiday_calendar_ui_test.js`.

### Task 4: Verification

**Files:**
- Test: `tests/holiday_calendar_helpers_test.php`
- Test: `tests/holiday_calendar_ui_test.js`

- [ ] Run `C:\xampp\php\php.exe -l api\holiday_calendar_api.php`.
- [ ] Run `C:\xampp\php\php.exe -l includes\holiday_calendar_helpers.php`.
- [ ] Run `C:\xampp\php\php.exe -l holiday_calendar.php`.
- [ ] Run `C:\xampp\php\php.exe tests\holiday_calendar_helpers_test.php`.
- [ ] Run `node tests\holiday_calendar_ui_test.js`.
- [ ] Run `git diff --check`.
