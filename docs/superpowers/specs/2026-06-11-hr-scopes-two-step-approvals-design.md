# HR Scopes and Two-Step Approvals Design

## Goal

Split HR permissions into selectable company-level and branch-level scopes, while keeping one user account able to hold multiple HR scopes at the same time. Add a two-step approval flow for leave requests and day swap requests: manager approval first, then HR approval.

## Current System Context

- Main account role is stored in `users.role`.
- Existing roles in code are `employee`, `manager`, `hr`, and `admin`.
- Existing HR access is treated as company-wide through the logged-in employee's `company_id`.
- Leave requests and day swap requests currently use one approval step with statuses like `pending`, `approved`, `rejected`, and `cancelled`.
- Attendance/report logic treats only `approved` leave and day swap rows as effective.

## Recommended Approach

Keep `users.role` as the main account role and add a separate HR scope table. This avoids overloading one role column and supports these cases:

- One user can be HR for multiple companies.
- One user can be HR for multiple branches.
- One user can be both company-level HR and branch-level HR.
- Admin remains full-access regardless of HR scope rows.

Rejected alternatives:

- New single roles such as `hr_branch` and `hr_company`: too rigid because one account can need both.
- JSON or multiple columns on `users`: quicker at first, but harder to query safely across employee, leave, and day swap filters.

## Data Model

Add a table named `user_hr_scopes`.

Suggested columns:

- `id INT AUTO_INCREMENT PRIMARY KEY`
- `user_id INT NOT NULL`
- `scope_type ENUM('company','branch') NOT NULL`
- `scope_id INT NOT NULL`
- `created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`
- `UNIQUE KEY uniq_user_hr_scope (user_id, scope_type, scope_id)`
- `INDEX idx_user_hr_scope_user (user_id)`
- `INDEX idx_user_hr_scope_lookup (scope_type, scope_id)`
- Foreign key from `user_id` to `users.id`.

The table should be created through an ensure/migration helper so existing installs can upgrade without a manual SQL step. Foreign keys to companies/branches are not required because one `scope_id` points to different tables based on `scope_type`; validation should happen in application code before saving.

## Account Form

Update the User Account section in `employee_add.php` and `employee_edit.php`.

Behavior:

- Keep the main role select with `Employee`, `Manager`, `HR`, and `Admin`.
- When role is `HR` or `Admin`, show an HR permission scope section.
- Allow selecting multiple companies for company-level HR.
- Allow selecting multiple branches for branch-level HR.
- Branch choices should show company context so admin can distinguish duplicate branch names.
- If role is changed away from `HR` or `Admin`, clear or ignore submitted HR scopes.

Admin handling:

- Admin always has full access, even with no HR scopes.
- Saving optional HR scopes for admin is allowed but not required.

## Authentication Session

After login and on authenticated page refresh, load HR scopes into the session or expose them through shared permission helpers.

Recommended helper responsibilities:

- `authEnsureHrScopeTable(mysqli $mysqli)`
- `authFetchHrScopes(mysqli $mysqli, int $userId): array`
- `authUserCanAccessEmployee(mysqli $mysqli, int $employeeId): bool`
- `authBuildEmployeeScopeSql(...)` or equivalent reusable filter builder
- `authUserCanApproveHrStageForEmployee(...)`

Session should continue storing:

- `user_id`
- `employee_id`
- `role`
- `company_id`

New scope information can be stored as arrays such as:

- `hr_company_ids`
- `hr_branch_ids`

The permission helpers should remain the source of truth so stale sessions can be refreshed by `includes/auth_check.php`.

## Data Visibility Rules

Admin:

- Sees all companies, branches, employees, leave requests, and day swap requests.
- Can approve or reject any approval stage based on the request's current stage.

HR company scope:

- Sees employees in selected companies.
- Can HR-approve requests for employees in selected companies.

HR branch scope:

- Sees employees in selected branches.
- Can HR-approve requests for employees in selected branches.

Combined HR scopes:

- Union all selected company and branch scopes.
- If a user has company A and branch B, they see employees in company A plus employees in branch B.

Manager:

- Sees and manager-approves direct reports through `employees.supervisor_id`.

Employee:

- Sees own requests/history only.

## Leave Approval Flow

New canonical statuses:

- `pending_manager`
- `pending_hr`
- `approved`
- `rejected`
- `cancelled`

Submission:

- New leave requests start as `pending_manager`.

Manager stage:

- Manager pending list shows only direct-report requests with `pending_manager`.
- Manager approve changes status to `pending_hr`.
- Manager reject changes status to `rejected`.
- Store manager approver and timestamp.

HR stage:

- HR pending list shows only requests with `pending_hr` inside the user's HR scopes.
- HR approve changes status to `approved`.
- HR reject changes status to `rejected`.
- Store HR approver and timestamp.
- For backward compatibility, set existing `approver_id` and `approval_date` to the final HR/admin approver when status becomes `approved` or `rejected`.

Admin:

- If request is `pending_manager`, admin can approve/reject the manager stage.
- If request is `pending_hr`, admin can approve/reject the HR stage.
- Admin sees all pending and history rows.

Recommended new columns on `leave_requests`:

- `manager_approver_id INT NULL`
- `manager_approval_date DATETIME NULL`
- `hr_approver_id INT NULL`
- `hr_approval_date DATETIME NULL`
- `approval_stage ENUM('manager','hr','done')` is optional; status alone can drive the flow.

The implementation can avoid `approval_stage` if status names are used consistently.

Existing data migration:

- Existing `pending` rows should be treated as `pending_manager`.
- Existing `approved`, `rejected`, and `cancelled` rows should keep their current meaning.
- Queries should include compatibility handling until all live rows are migrated.

## Day Swap Approval Flow

Use the same two-step model as leave requests.

Submission:

- New day swap requests start as `pending_manager`.

Manager stage:

- Manager sees `pending_manager` requests where the requester is their direct report.
- Manager approve changes status to `pending_hr`.
- Manager reject changes status to `rejected`.

HR stage:

- HR sees `pending_hr` requests where the requester is inside their HR scope.
- HR approve changes status to `approved`.
- HR reject changes status to `rejected`.

Admin:

- Admin can act on either pending stage and sees all rows.

Recommended new columns on `day_swap_requests`:

- `manager_approver_id INT NULL`
- `manager_approval_date DATETIME NULL`
- `hr_approver_id INT NULL`
- `hr_approval_date DATETIME NULL`

Existing `approver_id` and `approval_date` should represent final approval for compatibility.

## UI Changes

Leave approval page:

- Pending tab should show the current stage in the table.
- Button labels can remain approve/reject, but confirmation text should mention whether this is manager approval or HR final approval.
- History tab should show manager approval and HR approval details when available.

Day swap approval page:

- Same stage label and history detail behavior as leave approval.

My requests/history pages:

- `pending_manager` should display as waiting for manager approval.
- `pending_hr` should display as waiting for HR approval.
- `approved`, `rejected`, and `cancelled` should keep the existing display behavior.

Employee management:

- Employee list and branch filters must use the new HR scope union.
- HR branch users should only see selectable/filterable branches they are scoped to.
- HR company users should see all branches under scoped companies.

Sidebar/page access:

- HR-scoped users keep HR menu access if role is `hr`.
- Admin keeps all access.
- Manager keeps approval menu access for manager-stage approvals.

## Reporting and Attendance Impact

Attendance and downstream reports continue to use only `status = 'approved'`.

Requests in `pending_manager` or `pending_hr` must not affect attendance, leave summaries as approved, or approved day swap maps. They may still appear as pending in request history and usage warning summaries where the UI already displays pending requests.

## Error Handling

- If an HR user has no scope rows, they should see no employee/request rows instead of falling back to company_id.
- If admin changes a user's role away from HR/admin, submitted HR scopes should be removed.
- If a branch scope is submitted for a branch that does not exist, reject the save.
- If a company scope is submitted for a company that does not exist, reject the save.
- If an approval action is attempted on the wrong stage, return access denied or already processed.

## Testing Plan

PHP syntax checks:

- `C:\xampp\php\php.exe -l includes/auth_check.php`
- `C:\xampp\php\php.exe -l api/login_process.php`
- `C:\xampp\php\php.exe -l api/employee_api.php`
- `C:\xampp\php\php.exe -l api/leave_approval_api.php`
- `C:\xampp\php\php.exe -l api/day_swap_api.php`

Existing focused tests:

- `C:\xampp\php\php.exe tests\leave_helpers_test.php`
- `C:\xampp\php\php.exe tests\attendance_helpers_test.php`
- `node tests\day_swap_calendar_test.js`
- `node --check assets/js/employee.js`
- `node --check assets/js/leave_approval.js`
- `node --check assets/js/day_swap.js`

New tests should cover:

- HR company scope includes all branches under selected companies.
- HR branch scope includes only selected branches.
- Combined HR scopes are unioned.
- HR with no scopes sees no scoped employee rows.
- Manager approval moves leave/day swap request to `pending_hr`.
- HR approval moves request to `approved`.
- Rejection at either stage moves request to `rejected`.
- Admin can act on both pending stages.

## Implementation Order

1. Add shared HR scope helpers and database ensure logic.
2. Update login/auth refresh to load scopes.
3. Update employee add/edit account UI and persistence.
4. Replace existing HR company filters with scope-aware filters in employee data paths.
5. Update leave request statuses, approval API, and approval UI.
6. Update day swap request statuses, approval API, and approval UI.
7. Update history/status labels for employee-facing pages.
8. Run lint and focused tests.

## Open Decisions

None. The agreed decisions are:

- HR company scopes are selected manually by admin.
- HR branch scopes are selected manually by admin.
- One user can hold both HR company and HR branch scopes.
- Approval order is manager first, then HR.
- HR sees only requests after manager approval.
- Admin sees all requests and can act on the current approval stage.
