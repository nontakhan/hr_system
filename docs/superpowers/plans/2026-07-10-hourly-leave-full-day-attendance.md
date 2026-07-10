# Hourly Leave Counted as Full-day Attendance Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Classify approved hourly leave with persisted `total_days >= 1` as full-day leave in attendance without duplicating it as an hourly supplement.

**Architecture:** Change the two attendance API data-source queries so qualifying hourly leave enters the approved-leave map and is excluded from the hourly-request map. Keep `attendanceEvaluateStatus()` and frontend presentation unchanged because they already render approved leave as the blue leave status.

**Tech Stack:** PHP 8, MySQL, PHP contract tests, Node.js calendar tests.

## Global Constraints

- Use persisted `leave_requests.total_days`; do not recalculate leave-type thresholds.
- Qualifying rows have `request_unit = 'hour'`, `time_request_type IS NULL`, and `total_days >= 1`.
- Partial hourly leave with `total_days < 1` remains an hourly supplement.
- Late/early and overtime requests remain hourly requests.
- Raw check-in/check-out data remains unchanged.

---

### Task 1: Route Full-day Hourly Leave Through the Leave Map

**Files:**
- Modify: `tests/attendance_api_source_test.php`
- Modify: `tests/attendance_helpers_test.php`
- Modify: `api/attendance_api.php`

**Interfaces:**
- Consumes: approved `leave_requests` rows and persisted `total_days`.
- Produces: `fetchApprovedLeavesForMonth()` rows containing full-day leave and `fetchApprovedHourlyRequestsForMonth()` rows containing only partial leave/time requests.

- [x] **Step 1: Add failing regression tests**

Add source assertions requiring the approved-leave query to include `(lr.request_unit = 'hour' AND lr.time_request_type IS NULL AND lr.total_days >= 1)` and the hourly query to exclude that same condition. Add an evaluator assertion with scan times and a leave map proving the resulting status is `leave` with the expected leave name.

- [x] **Step 2: Verify RED**

Run:

```powershell
C:\xampp\php\php.exe tests\attendance_api_source_test.php
C:\xampp\php\php.exe tests\attendance_helpers_test.php
```

Expected: the source contract test fails because the API queries currently route all hourly leave to the hourly-request map.

- [x] **Step 3: Implement the query routing**

Change the approved-leave filter to accept day requests or qualifying hourly full-day requests. Change the hourly-request filter to exclude qualifying hourly full-day requests while retaining partial hourly leave, late/early requests, and overtime.

- [x] **Step 4: Verify GREEN**

Run:

```powershell
C:\xampp\php\php.exe tests\attendance_api_source_test.php
C:\xampp\php\php.exe tests\attendance_helpers_test.php
C:\xampp\php\php.exe -l api\attendance_api.php
node tests/attendance_calendar_test.js
node --check assets/js/attendance.js
git diff --check -- api/attendance_api.php tests/attendance_api_source_test.php tests/attendance_helpers_test.php
```

Expected: every command exits with code 0.

- [x] **Step 5: Review scoped changes**

Run: `git diff -- api/attendance_api.php tests/attendance_api_source_test.php tests/attendance_helpers_test.php`

Expected: only query routing and regression coverage change.
