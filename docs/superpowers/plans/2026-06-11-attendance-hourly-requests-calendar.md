# Attendance Hourly Requests Calendar Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Show approved one-hour late-arrival and early-departure requests on attendance calendar days.

**Architecture:** Keep attendance status as the primary day state, and add approved hourly requests as supplemental row metadata. The API builds an `hourly_requests` list per date; the calendar title and popup render that list without changing the main status color/counts.

**Tech Stack:** PHP/mysqli attendance API, shared PHP attendance helpers, vanilla JS FullCalendar rendering, focused PHP and Node tests.

---

### Task 1: Calendar Rendering

**Files:**
- Modify: `tests/attendance_calendar_test.js`
- Modify: `assets/js/attendance.js`

- [ ] Add failing tests proving `hourly_requests` appears in calendar event titles and popup details.
- [ ] Update `attendanceCalendarEventTitle()` to append a short hourly request marker.
- [ ] Update `buildAttendanceCalendarDetails()` to show a "คำขอเวลา" section.

### Task 2: Attendance API Data

**Files:**
- Modify: `tests/attendance_helpers_test.php`
- Modify: `includes/attendance_helpers.php`
- Modify: `api/attendance_api.php`

- [ ] Add failing tests for an approved hourly request map keyed by work date.
- [ ] Build hourly request labels from approved `leave_requests.request_unit = 'hour'`.
- [ ] Attach `hourly_requests` to each monthly attendance report row.

### Task 3: Verification

**Files:**
- Test: `tests/attendance_calendar_test.js`
- Test: `tests/attendance_helpers_test.php`

- [ ] Run focused JS and PHP tests.
- [ ] Run syntax checks for touched PHP/JS files.
