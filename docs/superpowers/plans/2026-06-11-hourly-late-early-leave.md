# Hourly Late Early Leave Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add fixed 1-hour late-arrival and early-departure requests that reuse the leave approval flow and count toward the fiscal-year request count.

**Architecture:** Store hourly request metadata on `leave_requests` while keeping normal day-based leave behavior unchanged. The request form detects hourly leave types by name, submits one selected date with a fixed 60-minute allowance, and summaries count the request but not leave days.

**Tech Stack:** PHP 8-style procedural APIs, MySQL/MariaDB via mysqli, Bootstrap/vanilla JS, existing PHP and Node static tests.

---

### Task 1: Helper And Schema Support

**Files:**
- Modify: `includes/leave_helpers.php`
- Test: `tests/leave_helpers_test.php`

- [ ] Add tests for fixed 60-minute hourly request summaries and labels.
- [ ] Add request detail column migration helper for `request_unit`, `time_request_type`, and `request_minutes`.
- [ ] Add helper functions to detect late-arrival and early-departure leave types from type names.
- [ ] Add helper functions to normalize and label hourly requests.

### Task 2: Submit And Summary APIs

**Files:**
- Modify: `api/leave_request_api.php`
- Modify: `includes/leave_helpers.php`

- [ ] Accept hourly leave type submissions as a same-day request with fixed `request_minutes = 60` and `total_days = 0`.
- [ ] Keep normal leave calculation for non-hourly leave types unchanged.
- [ ] Include hourly metadata in fiscal-year usage entries and count pending/approved requests.

### Task 3: Employee And Approver UI

**Files:**
- Modify: `leave_request.php`
- Modify: `assets/js/leave_request.js`
- Modify: `assets/js/my_leaves.js`
- Modify: `assets/js/leave_approval.js`
- Test: `tests/leave_request_icon_ui_test.js`

- [ ] Add a fixed one-hour request notice and hidden `request_unit` field to the form.
- [ ] Make late-arrival and early-departure type cards switch the form to date-only fixed-hour mode.
- [ ] Display hourly requests as `1 hour` allowance in request summary, history, and approval rows.

### Task 4: Verification

**Files:**
- Test: `tests/leave_helpers_test.php`
- Test: `tests/leave_request_icon_ui_test.js`

- [ ] Run PHP lint on touched PHP files.
- [ ] Run focused PHP helper test.
- [ ] Run focused Node UI contract test.
