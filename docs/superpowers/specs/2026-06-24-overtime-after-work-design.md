# Overtime After Work Request Design

## Goal

Add an after-work overtime request flow for employees. Employees choose a work date, requested OT duration, and reason. The request follows the existing manager to HR approval path, and HR final approval is constrained by the actual check-out scan for that work date. Approved OT must also appear in the employee attendance view.

## Current System Context

- Employee time requests already use `leave_requests` with `request_unit = 'hour'`.
- Existing hourly request types are `late_arrival` and `early_departure`.
- Hourly requests are submitted from `late_early_request.php`, saved through `api/late_early_request_api.php`, approved through `late_early_approvals.php` and `api/leave_approval_api.php`, and rendered by `assets/js/late_early_request.js` / `assets/js/leave_approval.js`.
- Approval status already follows `pending_manager` to `pending_hr` to `approved` or `rejected`.
- Attendance reports already fetch approved hourly requests in `api/attendance_api.php` and display them in `assets/js/attendance.js`.
- Attendance calculations resolve the effective shift per date, including shift assignment history, weekly overrides, and approved day swaps.

## Recommended Approach

Extend the existing hourly time-request flow with a new `time_request_type` value: `overtime_after_work`.

This keeps OT inside the existing approval, history, badge, and attendance-report paths. It avoids creating a parallel approval system and reduces the amount of new UI employees and approvers must learn.

Rejected alternative: create a dedicated OT table and pages. This would isolate OT data, but it duplicates request history, approval scope, notification badge, and attendance rendering behavior that already exists for hourly requests.

## Employee Request Flow

The time request page becomes a broader "time request" page rather than only "late/early request".

Employee inputs:

- Request type: late arrival, early departure, or after-work OT.
- Work date.
- Requested OT duration for `overtime_after_work`.
- Request time for `late_arrival` and `early_departure`.
- Reason.

For `overtime_after_work`, the employee enters a duration instead of a clock time. The first implementation should support minutes as the source value and display hours/minutes in the UI. The UI can use hour and minute inputs or a minute select, but the saved value remains `request_minutes`.

Submission rules for OT:

- The work date must be a valid date.
- The date must be a workday according to the effective shift for that employee and date.
- Requested duration must be greater than zero.
- Requested duration should be capped at a reasonable first-pass maximum of 480 minutes.
- The request starts as `pending_manager`.
- The request does not need a check-out scan at submission time.

## Approval Flow

Manager approval remains unchanged:

- Managers see OT together with other hourly time requests for their direct reports.
- Manager approval moves the request to `pending_hr`.
- Manager rejection moves the request to `rejected`.

HR approval adds scan validation for OT:

- HR sees the employee's effective shift end time.
- HR sees the actual check-out scan for the requested date.
- HR sees requested OT duration.
- HR sees system-calculated eligible OT duration.
- If there is no check-out scan, HR approval is blocked with a Thai error message that the scan-out result is required.
- If check-out is not after shift end, HR approval is blocked.
- If eligible OT is less than requested OT, approval is allowed only for the eligible duration.
- If eligible OT is equal to or greater than requested OT, approval uses the requested duration.

The final approved duration should be stored separately from the original requested duration so history and reports can distinguish "requested" from "approved by scan". Add a nullable column to `leave_requests`:

- `approved_request_minutes SMALLINT UNSIGNED NULL`

For existing late/early requests this column can stay `NULL`; those requests continue using `request_minutes`.

## OT Calculation

Use the same effective shift source that attendance uses for the requested date.

Inputs:

- Work date.
- Effective shift end time.
- Actual check-out scan after HR attendance overrides are applied.
- Requested OT minutes.

Rules:

- Invalid if shift end time is missing.
- Invalid if check-out is missing.
- Invalid if check-out is less than or equal to shift end.
- Eligible minutes are `ceil((check_out - shift_end) / 60)`.
- Approved minutes are `min(requested_minutes, eligible_minutes)`.

Overnight shifts are out of scope for this first pass unless the existing shift helpers already model them consistently. The calculation should stay explicit about same-date check-out behavior.

## Attendance Display

Approved OT should appear in the attendance report alongside existing approved hourly requests.

Attendance API:

- Fetch approved hourly rows where `time_request_type = 'overtime_after_work'`.
- Use `approved_request_minutes` when present.
- Fall back to `request_minutes` for compatibility.
- Return labels such as "OT หลังเลิกงาน 1 ชม. 30 นาที".

Attendance UI:

- Calendar event titles include approved OT after the status label.
- Calendar popup lists OT under the existing time-request section.
- Only `approved` and `pending_cancel_hr` rows affect the attendance display, matching existing hourly request behavior.

## Data Model and Migration

Update the existing hourly request ensure helper so `leave_requests.time_request_type` allows:

- `late_arrival`
- `early_departure`
- `overtime_after_work`

Add an idempotent helper for `approved_request_minutes`.

The helper should be safe for existing installs and should not require a manual SQL file to be run before the feature works.

## Pages and Menus

Rename copy where practical from late/early specific wording to general time-request wording:

- `late_early_request.php` remains the file name for compatibility but page text should include OT.
- `late_early_history.php` remains the file name but should show all hourly request types.
- `late_early_approvals.php` remains the approval page for hourly requests and should show OT-specific scan details for approvers.
- Sidebar labels can stay broadly as time requests if already neutral; avoid renaming routes in the first pass.

## Error Handling

- Employee submission returns Thai validation messages for invalid date, non-workday, invalid duration, and missing reason.
- HR approval returns Thai validation messages when scan-out data is missing or does not support OT.
- Wrong-stage approval attempts continue returning the existing access or processed-state error.
- If the approved duration is lower than requested, the response should make that visible to the approver and saved history.

## Testing Plan

Add failing tests before implementation.

PHP tests:

- `attendanceCalculateOvertimeAfterWorkMinutes` rejects missing shift end, missing check-out, and check-out before or at shift end.
- `attendanceCalculateOvertimeAfterWorkMinutes` caps approved minutes to requested minutes.
- `attendanceCalculateOvertimeAfterWorkMinutes` returns eligible minutes when actual scan-out is lower than requested duration.
- `attendanceBuildApprovedHourlyRequestMap` renders `overtime_after_work` using `approved_request_minutes` when present.
- Existing late/early hourly request tests still pass.

JavaScript tests:

- Time request form contract includes an OT option.
- History and approval duration formatters render OT distinctly from late/early requests.
- Attendance popup/title includes the OT label from approved hourly requests.

Syntax checks:

- `C:\xampp\php\php.exe -l api\late_early_request_api.php`
- `C:\xampp\php\php.exe -l api\leave_approval_api.php`
- `C:\xampp\php\php.exe -l api\attendance_api.php`
- `C:\xampp\php\php.exe -l includes\attendance_helpers.php`
- `node --check assets\js\late_early_request.js`
- `node --check assets\js\leave_approval.js`
- `node --check assets\js\attendance.js`

## Implementation Order

1. Add tests for OT helper calculations and hourly request rendering.
2. Extend hourly request schema helpers for `overtime_after_work` and `approved_request_minutes`.
3. Add OT calculation helper using effective shift end and actual check-out.
4. Extend employee request API and UI for OT duration submission.
5. Extend approval API and UI so HR sees scan details and saves approved OT minutes.
6. Extend attendance API/UI labels for approved OT.
7. Run focused PHP and JS tests plus syntax checks.

## Open Decisions

None. The agreed behavior is:

- Employees can submit OT before scan-out exists.
- Manager approval does not require scan validation.
- HR final approval requires scan-out evidence.
- HR can approve only the duration supported by actual check-out, capped by the requested duration.
- Approved OT appears in the attendance view.
