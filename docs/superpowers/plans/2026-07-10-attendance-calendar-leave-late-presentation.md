# Attendance Calendar Leave and Approved Late-request Presentation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Show full-day leave types directly and treat approved late-arrival requests as normal in the attendance calendar and summary.

**Architecture:** Keep raw API attendance rows unchanged. Derive a presentation status in `assets/js/attendance.js`, then reuse it for calendar titles, colors, day classes, and summary counts while the detail modal continues to receive the original row.

**Tech Stack:** Browser JavaScript, Node.js contract tests.

## Global Constraints

- Full-day leave with `leave_name` displays that name only.
- A raw `late` row changes presentation to `present` only when an approved hourly label begins with `ขอมาสาย`.
- Approved-late rows count as normal, use green, and display `ปกติ + ขอมาสาย ...`.
- Raw times and detail-modal data remain unchanged.

---

### Task 1: Calendar Presentation Rules

**Files:**
- Modify: `tests/attendance_calendar_test.js`
- Modify: `assets/js/attendance.js`

**Interfaces:**
- Consumes: attendance row objects with `status`, `status_label`, `leave_name`, and `hourly_requests`.
- Produces: `attendanceCalendarPresentationStatus(row)` returning the effective calendar/summary status and `attendanceCalendarEventTitle(row)` returning the effective title.

- [ ] **Step 1: Write failing behavior tests**

Add assertions that a full-day leave title equals `ลากิจ`; a late row without a request stays orange and increments `late`; and a late row with `hourly_requests: ['ขอมาสาย 35 นาที']` has title `ปกติ + ขอมาสาย 35 นาที`, green presentation, and increments `present` instead of `late`.

- [ ] **Step 2: Run tests and verify RED**

Run: `node tests/attendance_calendar_test.js`

Expected: FAIL because full-day leave still includes the generic leave label and approved-late rows still use `late` presentation/counting.

- [ ] **Step 3: Implement minimal presentation logic**

In `attendanceCalendarPresentationStatus(row)`, detect a late row whose normalized hourly labels contain one beginning with `ขอมาสาย` and return `present`. In `attendanceCalendarEventTitle(row)`, return `leave_name` for full-day leave and use `ปกติ` when the derived presentation status is present but the raw status is late. In `countAttendanceReportStatuses(rows)`, increment the derived presentation status while preserving holiday and training auxiliary counts.

- [ ] **Step 4: Run focused verification and verify GREEN**

Run:

```powershell
node tests/attendance_calendar_test.js
node --check assets/js/attendance.js
git diff --check -- assets/js/attendance.js tests/attendance_calendar_test.js
```

Expected: all commands exit with code 0.

- [ ] **Step 5: Review the diff**

Run: `git diff -- assets/js/attendance.js tests/attendance_calendar_test.js`

Expected: only the specified presentation rules and their regression tests change.
