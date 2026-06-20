# Employee Shift History Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Preserve historical attendance accuracy when HR changes an employee shift by storing shift assignments with HR-selected effective dates.

**Architecture:** Add an `employee_shift_assignments` history table and helper functions that resolve the effective shift for each work date. Employee create/update writes assignment ranges, and attendance reports read the dated assignment before falling back to `employees.default_shift_id`.

**Tech Stack:** PHP, MySQLi, existing XAMPP PHP app, focused PHP source tests.

---

### Task 1: Add Failing Coverage

**Files:**
- Create: `tests/employee_shift_assignment_history_test.php`

- [x] **Step 1: Write source-level expectations**

The test asserts that the helper, attendance report, employee API, and edit form expose the new history contract.

- [ ] **Step 2: Run the test and verify it fails**

Run: `C:\xampp\php\php.exe tests\employee_shift_assignment_history_test.php`

Expected: FAIL because `employee_shift_assignment_helpers.php` and related wiring do not exist yet.

### Task 2: Add Shift Assignment Helper

**Files:**
- Create: `includes/employee_shift_assignment_helpers.php`
- Create: `database_employee_shift_assignments.sql`

- [ ] **Step 1: Create table ensure and sync helpers**

Add helpers to create `employee_shift_assignments`, bootstrap a legacy current assignment from `employees.default_shift_id`, close overlapping current ranges, insert the new range, and fetch assignment rows for a month.

- [ ] **Step 2: Re-run focused test**

Run: `C:\xampp\php\php.exe tests\employee_shift_assignment_history_test.php`

Expected: still FAIL until employee API and attendance are wired.

### Task 3: Wire Employee Create/Update

**Files:**
- Modify: `api/employee_api.php`
- Modify: `employee_add.php`
- Modify: `employee_edit.php`

- [ ] **Step 1: Include the helper**
- [ ] **Step 2: On create, insert the first assignment from `start_date`**
- [ ] **Step 3: On update, use `shift_effective_from` when HR changes shift**
- [ ] **Step 4: Add date/reason fields near the shift picker**

### Task 4: Resolve Attendance by Assignment History

**Files:**
- Modify: `api/attendance_api.php`

- [ ] **Step 1: Include the helper**
- [ ] **Step 2: Fetch shift assignments for the requested month**
- [ ] **Step 3: Resolve each `work_date` through assignment history before weekly overrides/day swaps/status evaluation**

### Task 5: Verify

**Files:**
- Test: `tests/employee_shift_assignment_history_test.php`

- [ ] **Step 1: Run focused PHP test**

Run: `C:\xampp\php\php.exe tests\employee_shift_assignment_history_test.php`

- [ ] **Step 2: Run PHP lint on changed PHP files**

Run: `C:\xampp\php\php.exe -l includes\employee_shift_assignment_helpers.php`
Run: `C:\xampp\php\php.exe -l api\employee_api.php`
Run: `C:\xampp\php\php.exe -l api\attendance_api.php`
Run: `C:\xampp\php\php.exe -l employee_add.php`
Run: `C:\xampp\php\php.exe -l employee_edit.php`

- [ ] **Step 3: Check whitespace**

Run: `git diff --check`
