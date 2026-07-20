# HR/Admin Direct Request Cancellation Implementation Plan

> **For agentic workers:** Implement this plan task-by-task with test-first changes and a review gate after each task.

**Goal:** Allow scoped HR/Admin users to immediately cancel approved employee requests with a mandatory reason and durable audit trail.

**Architecture:** Extend the three request storage tables with shared cancellation-audit columns, then add a reviewer-only cancellation action to the existing approval APIs. Reuse each approval page's history tab for the UI, with server-side role and HR-scope enforcement as the authority.

**Tech Stack:** PHP 8.2, mysqli/MariaDB, vanilla JavaScript, Bootstrap 5.3, SweetAlert2, Node.js/PHP contract tests.

## Global Constraints

- Supported workflows: leave, late/early, overtime, day swap, and training/activity.
- Only `hr` and `admin` may perform direct cancellation.
- HR must be restricted by `user_hr_scopes`; Admin retains the existing unrestricted scope behavior.
- Only `approved` rows may transition directly to `cancelled`.
- A non-empty trimmed reason is mandatory.
- Preserve every existing approval identity and timestamp field.
- Preserve employee self-cancellation and `pending_cancel_hr` behavior.
- Do not commit unless the user requests it.

---

### Task 1: Cancellation audit schema and transition contract

**Files:**
- Modify: `includes/request_cancellation_helpers.php`
- Modify: `includes/leave_helpers.php`
- Modify: `includes/day_swap_helpers.php`
- Modify: `includes/training_request_helpers.php`
- Create: `database_add_request_cancellation_audit.sql`
- Modify: `tests/request_cancellation_helpers_test.php`
- Create: `tests/request_cancellation_audit_schema_test.js`

**Interfaces:**
- Produces `requestCancellationReviewerDirectTransition(string $status, string $role): ?string`.
- Produces audit columns `cancelled_by_user_id`, `cancelled_by_employee_id`, `cancelled_by_role`, and `cancelled_at` on `leave_requests`, `day_swap_requests`, and `training_requests`.

- [ ] Write tests asserting HR/Admin + `approved` returns `cancelled`, while manager/employee/non-approved statuses return `null`.
- [ ] Write a source contract test asserting all three table ensure paths and the SQL migration contain the four audit columns without modifying approval columns.
- [ ] Run both tests and verify they fail for the missing transition and columns.
- [ ] Implement the pure transition helper and add idempotent column ensures to the existing schema helpers.
- [ ] Add the matching deployment migration using `ALTER TABLE` statements compatible with the repository's MariaDB/XAMPP contract.
- [ ] Re-run both tests and verify they pass.

### Task 2: Scoped reviewer cancellation APIs

**Files:**
- Modify: `api/leave_approval_api.php`
- Modify: `api/late_early_request_api.php`
- Modify: `api/day_swap_api.php`
- Modify: `api/training_request_api.php`
- Create: `tests/hr_admin_direct_request_cancellation_api_test.js`

**Interfaces:**
- Consumes `requestCancellationReviewerDirectTransition()` and `hrScopeBuildEmployeeWhereClause()`.
- Accepts JSON `action=reviewer_cancel`, `request_id`, `cancellation_reason`, plus the existing time-request discriminator where required.
- Produces JSON success only after one atomic `approved` to `cancelled` update.

- [ ] Write a failing API source-contract test for role gates, trimmed mandatory reason, employee joins, HR-scope clauses, `status = 'approved'`, the four audit assignments, and `affected_rows === 1` across all endpoints.
- [ ] Add `reviewer_cancel` routing separately from employee `cancel` routing.
- [ ] Fetch the target through its employee join with the existing scope clause; return the same not-found/access response for missing and out-of-scope rows.
- [ ] Derive audit values only from session: user id, employee id, role, and server time.
- [ ] Update status, reason, and audit fields atomically without assigning any approval field.
- [ ] Keep the late/early and OT type filters distinct so an id cannot be operated through the wrong approval surface.
- [ ] Re-run the API contract test and existing cancellation workflow tests.

### Task 3: Return cancellation audit in approval history

**Files:**
- Modify: `api/leave_approval_api.php`
- Modify: `api/day_swap_api.php`
- Modify: `api/training_request_api.php`
- Modify: `tests/hr_admin_direct_request_cancellation_api_test.js`

**Interfaces:**
- Produces history fields `cancellation_reason`, `cancelled_at`, `cancelled_by_role`, and `cancelled_by_name`.

- [ ] Extend the failing contract to require a left join from `cancelled_by_employee_id` to employees and a `cancelled_by_name` alias in every history query.
- [ ] Update leave history, which also serves late/early and OT approval pages, without broadening its existing request-type filter.
- [ ] Update day-swap and training history queries with the same normalized field names.
- [ ] Verify pending queries and approval actions remain unchanged.
- [ ] Re-run the focused API contract tests.

### Task 4: Approval-history table actions

**Files:**
- Modify: `leave_approvals.php`
- Modify: `late_early_approvals.php`
- Modify: `overtime_approvals.php`
- Modify: `day_swap_approvals.php`
- Modify: `training_approvals.php`
- Modify: `assets/js/leave_approval.js`
- Modify: `assets/js/day_swap.js`
- Modify: `assets/js/training_request.js`
- Modify: `assets/style.css`
- Create: `tests/hr_admin_direct_request_cancellation_ui_test.js`

**Interfaces:**
- Consumes normalized history audit fields from Task 3.
- Produces compact `.reviewer-cancel-request-button` controls only for `approved` rows.

- [ ] Write a failing UI contract test requiring an action column in all five history tables, approved-only button guards, mandatory reason dialogs, `reviewer_cancel` requests, history reloads, escaped audit rendering, and corrected empty-row `colspan` values.
- [ ] Add a `จัดการ` header to each history table and update DataTables non-orderable column configuration.
- [ ] Render a compact red `ยกเลิกรายการ` button only when the row status is exactly `approved`; rely on API authorization rather than client-provided role data.
- [ ] Add SweetAlert2 confirmation with employee/request context and a textarea validator that rejects trimmed empty text.
- [ ] Submit to the workflow's existing approval endpoint and reload only the active history table after success.
- [ ] For cancelled rows, render reason plus `ยกเลิกโดย <name or role>` and Thai-friendly cancellation time; safely omit unavailable audit portions for legacy rows.
- [ ] Add scoped CSS matching the compact request-table action vocabulary.
- [ ] Run the UI contract and JavaScript syntax checks.

### Task 5: End-to-end regression and evidence

**Files:**
- Verify all files changed in Tasks 1-4.

**Interfaces:**
- Produces verification evidence and an explicit list of any environment boundary.

- [ ] Run PHP syntax checks for every changed PHP file.
- [ ] Run the new helper, schema, API, and UI tests.
- [ ] Run `tests/request_cancellation_workflows_test.js`, `tests/leave_cancellation_request_test.js`, and other directly affected approval/scope tests discovered from the changed endpoints.
- [ ] Run `git diff --check` and inspect the focused diff to confirm approval fields are never assigned by reviewer cancellation.
- [ ] With MariaDB available, verify through Apache/XAMPP: in-scope HR success, Admin success, out-of-scope HR denial, manager denial, mandatory reason, repeat-click conflict, audit display, and preserved original approver.
- [ ] Verify leave/late-early/OT consumers no longer treat the cancelled request as active, and verify day swap/training history remains visible but inactive.
- [ ] Report changed files, test commands, real-browser results, unverified boundaries, and confirm that no commit was created.
