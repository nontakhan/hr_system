# Proxy Request Design

## Goal

Allow admin and HR users to create request records on behalf of employees. These records are approved immediately, remain visible in the employee's normal request history, and clearly show that they were created by an admin or HR user.

This covers every current request workflow:

- Leave requests
- Late arrival and early departure requests
- Overtime after work requests
- Day swap requests
- Training requests

## Recommended Approach

Create one admin/HR-only page, `request_proxy.php`, for all proxy request creation. The page keeps employee self-service screens simple while giving admin/HR users one predictable place to handle proxy work.

The page starts with employee selection, then shows request-type controls for the supported workflows. Each form uses the same validation rules as the existing employee request path as much as possible, but the save path writes final status `approved` immediately.

## Access Rules

Only `admin` and `hr` roles can access the proxy request page and API.

Admin users can create proxy requests for any active or probation employee.

HR users can create proxy requests only for employees allowed by the existing HR scope helpers.

Employees and managers cannot access this feature unless their role is also admin or HR.

## Data Model

Add a shared audit column set to every request table used by this feature:

- `created_by_user_id INT NULL`
- `created_by_employee_id INT NULL`
- `created_by_role VARCHAR(30) NULL`
- `created_via ENUM('self_service','admin_proxy') NOT NULL DEFAULT 'self_service'`
- `proxy_note TEXT NULL`

Tables affected:

- `leave_requests`
- `day_swap_requests`
- `training_requests`

`leave_requests` covers normal leave, late arrival, early departure, and overtime after work.

Existing rows default to `created_via = 'self_service'`, so current history screens keep their normal behavior.

For proxy-created rows:

- `created_by_user_id` stores the logged-in user's `$_SESSION['user_id']`.
- `created_by_employee_id` stores the logged-in user's `$_SESSION['employee_id']` when available.
- `created_by_role` stores `admin` or `hr`.
- `created_via` stores `admin_proxy`.
- `proxy_note` stores an optional internal reason or note entered by admin/HR.

## API Design

Add `api/proxy_request_api.php`.

GET actions:

- `employees`: returns employees available to the current admin/HR user.
- `leave_types`: returns normal leave types for the leave form.
- `day_swap_holidays`: returns selectable holiday dates for day swap validation.
- `calculate_leave`: calculates leave duration for the selected employee.
- `calculate_time_request`: calculates late, early, or overtime minutes for the selected employee.

POST actions:

- `create_leave`
- `create_late_early`
- `create_overtime`
- `create_day_swap`
- `create_training`

The API must validate role and HR scope before accepting the selected employee ID.

The API should reuse existing helper logic where possible:

- Leave duration, leave type, quota, duplicate-date, and attachment behavior from the leave helpers and request API.
- Late/early/OT shift, day swap, and attendance-related calculations from the existing time request helpers/API logic.
- Day swap holiday and conflict validation from `day_swap_helpers.php` and `api/day_swap_api.php`.
- Training request validation and training-record creation from `training_request_helpers.php`.

## Workflow Behavior

Proxy-created records are saved as approved immediately.

For `leave_requests`:

- Insert with `status = 'approved'`.
- Set `approver_id`, `approval_date`, `hr_approver_id`, and `hr_approval_date` to the proxy creator where the columns exist.
- Set manager approval fields too when the table has them, so approval history is complete.
- Keep quota, duplicate-date, and date summary behavior consistent with normal approved leave.

For late arrival, early departure, and overtime:

- Insert into `leave_requests` with `request_unit = 'hour'`.
- Set the correct `time_request_type`.
- Insert with `status = 'approved'`.
- Keep the existing minute limits: late/early up to 60 minutes, OT up to 480 minutes.

For `day_swap_requests`:

- Validate requester employee, target employee, requester date, target date, and conflicts.
- Insert with `status = 'approved'`.
- Set approval fields to the proxy creator.

For `training_requests`:

- Insert with `status = 'approved'`.
- Create the downstream `employee_training_records` row immediately, the same way HR approval currently does.
- Store the generated training record ID back on the request.

## UI Design

Add sidebar link for admin/HR:

- Label: `ทำรายการแทนพนักงาน`
- Target: `request_proxy.php`
- Icon: use an existing Font Awesome user/edit style icon.

`request_proxy.php` layout:

- Employee selector at the top with searchable employee code/name.
- Request type tabs or segmented buttons:
  - Leave
  - Late/Early
  - OT
  - Day Swap
  - Training
- Each tab shows only fields relevant to that workflow.
- Common proxy note field is available for internal context.
- Submit button text makes the immediate approval clear, for example `บันทึกและอนุมัติทันที`.

The page should use the existing Bootstrap/Sarabun admin style and avoid mixing proxy fields into employee self-service request pages.

## History Display

Every affected history/approval view that displays request rows should include proxy audit text for proxy-created rows:

`สร้างโดย HR/Admin: {creator_name}`

This should appear as a small badge or muted line, not as the primary status. Existing self-service rows should look unchanged.

Affected history pages:

- `my_leaves.php` or the leave history surface backed by `api/leave_history_api.php`
- `late_early_history.php`
- `overtime_history.php`
- `day_swap_history.php`
- `training_history.php`
- Admin/HR approval history views where already-approved rows are visible

History queries must join the creator employee when `created_by_employee_id` is present. If that employee is missing, fall back to `created_by_role`.

## Error Handling

The API returns JSON with the existing shape:

- `status = success|error`
- `message`
- optional `data`

Validation errors should be user-facing and specific. Unexpected errors should be logged server-side and return a generic system error.

The API must not create a partial proxy request. Training request creation and downstream training history creation must run in one transaction.

## Testing Plan

Add focused contract tests before implementation where practical:

- Proxy API requires admin/hr role.
- HR cannot create a proxy request for an out-of-scope employee.
- Proxy leave insert uses `status = approved` and `created_via = admin_proxy`.
- Proxy time request insert preserves `time_request_type`, minutes, and approved status.
- Proxy day swap validates conflicts and writes approved status.
- Proxy training creates an approved request and an employee training history row.
- History query exposes proxy creator display fields.

Run focused verification:

- `C:\xampp\php\php.exe -l` for touched PHP files.
- Existing relevant PHP/JS contract tests.
- New proxy-request contract tests.
- `git diff --check`.

## Out Of Scope

This feature does not change employee self-service request screens.

This feature does not add a new approval stage.

This feature does not bypass existing business validation except the approval wait state. Date, quota, conflict, shift, scope, and attachment rules still apply.
