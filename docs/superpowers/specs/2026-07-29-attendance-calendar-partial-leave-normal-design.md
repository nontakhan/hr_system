# Attendance Calendar Partial-leave Normal Presentation Design

## Goal

Show approved leave that covers less than one full workday as normal attendance in the attendance calendar and summary, while keeping the leave type, period, and raw scanner information available in the calendar event and detail popup.

## Scope

This behavior applies to approved actual leave in both supported forms:

- a day-based request whose calendar date is `morning` or `afternoon`;
- an hourly request with `time_request_type IS NULL` and persisted `total_days < 1`.

An hourly actual-leave request with persisted `total_days >= 1` and every full date within an ordinary multi-day request remain full-day leave. Late-arrival, early-departure, overtime, activity, holiday, cancellation, and approval rules do not change.

## Source of Truth and Per-date Classification

Use values persisted on `leave_requests`; do not recalculate an old request with the leave type's current policy.

- For `request_unit = 'hour'`, use `total_days >= 1` as the full-day boundary.
- For a single-date day request, a non-`full` start or end part makes that date partial.
- For a multi-date day request, a non-`full` start part makes only the first date partial, and a non-`full` end part makes only the last date partial. Interior dates remain full.
- Continue accepting only the statuses currently used by the attendance calendar: `approved` and `pending_cancel_hr`.

## Data Flow

The attendance API will fetch the request unit, day parts, persisted duration, requested minutes, and requested time range needed to classify each overlapping date.

Attendance helpers will build two non-overlapping maps:

- a full-day leave map consumed by `attendanceEvaluateStatus()`;
- a partial-leave detail map containing labels such as `ลากิจ ครึ่งวันเช้า` or `ลาป่วย 09:00-11:00 2 ชม.`.

Each report row will expose the partial labels separately from late/early/OT request labels. The raw status produced from scanner data remains unchanged in the API row so the source information is not destroyed.

## Calendar Presentation

When a workday row has at least one partial-leave detail:

- derive the calendar presentation status as `present`;
- render the main title as `ปกติ`;
- use the normal green calendar color and normal day class;
- append the partial-leave labels on the next line;
- count the date in the `ปกติ` summary instead of `ลา`, `ขาด`, `สาย`, or scan-incomplete totals;
- show a normal badge plus a dedicated leave-detail section in the popup, while retaining check-in/check-out and the raw row for inspection.

Rows without partial leave keep their current presentation. Full-day leave remains `ลา`, uses the leave color, retains `leave_name`, and increments the leave summary.

## Safety and Compatibility

- Preserve existing role and HR scope checks.
- Preserve the current request statuses and cancellation behavior.
- Keep prepared statements and escaped popup content.
- Do not change request creation, leave calculation, quotas, stored data, or database schema.
- Keep late/early/OT details separate from actual partial-leave details.

## Testing

Use focused regression coverage to prove:

- a single-date half-day request is excluded from the full-day map and produces the correct partial label;
- partial first and last dates of a multi-day request are normal, while interior full dates remain leave;
- an hourly actual leave with `total_days < 1` is partial, while `total_days >= 1` remains full-day leave;
- a raw absent, late, or scan-incomplete row with partial leave presents and counts as normal without mutating its raw API status;
- the event and popup keep the leave details;
- full-day leave and late/early/OT behavior do not regress.

Run the focused PHP and Node.js tests, PHP lint, JavaScript syntax checking, and `git diff --check`.

## Acceptance Criteria

1. Every approved partial-leave workday displays `ปกติ` in green in the attendance calendar.
2. The same event displays the leave type and partial period.
3. The summary counts that date as normal and not as full-day leave or a scanner exception.
4. Full-day leave behavior is unchanged.
5. No schema or stored request data is modified.
