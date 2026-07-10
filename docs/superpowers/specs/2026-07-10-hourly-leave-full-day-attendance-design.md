# Hourly Leave Counted as Full-day Attendance Design

## Goal

Present an approved hourly leave request as full-day leave in attendance when the saved request result is one full day, while preserving partial hourly leave as a supplement to normal attendance.

## Source of Truth

Use the persisted `leave_requests.total_days` result. Do not recalculate the current leave-type threshold in the attendance flow because the request was already calculated under the policy active when it was submitted.

An approved request is full-day leave for attendance when all of these are true:

- `request_unit = 'hour'`
- `time_request_type IS NULL`
- `total_days >= 1`
- status is `approved` or `pending_cancel_hr`

## Data Flow

- `fetchApprovedLeavesForMonth()` includes ordinary day requests and qualifying hourly full-day requests in the approved-leave map.
- `fetchApprovedHourlyRequestsForMonth()` excludes qualifying hourly full-day requests so they do not also appear as hourly supplements.
- `attendanceEvaluateStatus()` already gives approved leave priority over scan-derived present/late status. No evaluator change is needed.
- The returned row therefore has `status = leave`, `leave_name` set to the leave type, and no duplicate hourly label for the same request.

## User-visible Behavior

- The approved 9-hour personal-leave request on 2026-05-08 displays `ลากิจ`, uses the blue leave color, and increments the leave summary.
- Approved partial hourly leave, such as 2 or 4 hours with `total_days < 1`, remains `ปกติ + ลากิจ ...` and increments the normal summary.
- Raw check-in/check-out values remain available in the detail modal.
- Late/early and overtime requests are unaffected because they have a non-null `time_request_type`.

## Verification

- Add source contract assertions for both attendance API query conditions.
- Add helper/evaluator coverage proving a qualifying full-day leave map produces `status = leave` despite scan data.
- Keep existing hourly-label tests proving partial hourly leave is still emitted.
- Run focused PHP and JavaScript tests, PHP lint, JavaScript syntax validation, and `git diff --check`.
