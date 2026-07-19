# Employee Monthly Request and Attendance Report Design

## Goal

Create a Thai admin/HR report for one selected employee and month that combines approved employee requests with actual scanner-derived attendance events. The report must make it easy to compare what was approved with what actually happened without exposing employees outside the current HR scope.

## Chosen Approach

Add one dedicated timeline-style report page backed by a dedicated report action. Each source event becomes its own row, rather than merging unrelated events into one daily row or separating requests and scanner data into tabs.

This approach keeps every request auditable, allows DataTables filtering by event type and source, and lets an approved late, early, or overtime request appear next to the corresponding scanner event on the same date.

## Access and Filters

- Only `admin` and `hr` sessions may open the page or call its API.
- The employee Select2 is required, contains active/probation employees only, and is constrained by `hrScopeBuildEmployeeWhereClause(...)`.
- The month is required in Gregorian `YYYY-MM` format and defaults to the current month.
- Data loads only after the user presses `แสดงรายงาน`.
- The API revalidates that the selected employee is inside the current user's scope; a forged employee ID must not disclose data.
- The page follows the existing Sarabun, Bootstrap, Select2, DataTables, loading, empty-state, and malformed-response patterns.

## Included Event Types

The report includes these approved request events:

1. Actual leave from `leave_requests`, only `status = 'approved'` and `leave_types.is_actual_leave = 1`.
2. Late-arrival requests from `leave_requests`, only `status = 'approved'` and `time_request_type = 'late_arrival'`.
3. Early-departure requests from `leave_requests`, only `status = 'approved'` and `time_request_type = 'early_departure'`.
4. Overtime requests from `leave_requests`, only `status = 'approved'` and `time_request_type = 'overtime_after_work'`.
5. Activity requests from `training_requests`, only `status = 'approved'`.
6. Shift-swap requests from `day_swap_requests`, only `status = 'approved'`, when the selected employee is either requester or target.

The report also includes these actual scanner-derived events:

7. Actual late arrival when effective check-in exceeds effective shift start plus `late_tolerance_mins`.
8. Actual early departure when effective check-out is before effective shift end.
9. Actual overtime when effective check-out is after effective shift end.

Only final `approved` requests are included. `pending_cancel_hr`, pending, rejected, cancelled, and other non-final statuses are excluded. Scanner events have no approval status and are labelled `ข้อมูลลงเวลา`.

## Month Boundaries and Expansion

- Leave and activity requests that overlap the selected month are clipped to the month and expanded to their counted dates using the existing workday, holiday, and day-part helpers.
- Hourly late, early, and overtime requests use their `start_date` inside the selected month.
- A shift-swap request is included if either `requester_date` or `target_date` is inside the selected month. For the selected employee, its row identifies their role in the swap and shows both dates and the other employee.
- Scanner-derived events use `attendance_records`, merged with HR attendance overrides, dated shift assignments, dated shift overrides, approved day-swap effects, holidays, approved leave, and approved activities through the existing attendance helpers.
- Non-working days, holidays, approved full-day leave, and approved full-day activities do not create late/early scanner incidents.

## Scanner Calculations

- Actual late minutes are the positive whole minutes between effective shift start and check-in. The event is included only after the shift tolerance is exceeded. This row shows the full actual deviation and does not deduct an approved late request, so users can compare the actual row with the request row.
- Actual early minutes are the positive whole minutes between check-out and effective shift end. This row shows the full actual deviation without deducting an approved early request.
- Actual overtime minutes are the positive whole minutes between effective shift end and check-out. A standalone scanner OT row is included even when no OT request exists.
- Missing or invalid scans/times do not produce guessed late, early, or overtime values.
- Overnight shifts and time ranges use existing attendance time calculations where available; new pure helpers must explicitly handle crossing midnight.

For an approved OT request, the request row shows its approved/requested start, end, and minutes. When the same date has a valid scanner check-out, it also shows actual OT minutes so the approved and actual values can be compared directly. The standalone scanner OT row remains visible as the source-of-truth attendance event.

## Page Layout

Create a dedicated report page in the report section with:

- Title `รายงานคำขอและเหตุการณ์พนักงาน`.
- Employee Select2 and native month input.
- A primary `แสดงรายงาน` button.
- Compact summary counts for total events, approved requests, scanner events, and total actual OT minutes.
- A responsive DataTable with a client-side type/source filter and search.
- An initial instruction state, loading state, no-results state, API error state, and malformed-response state.

The table columns are:

1. วันที่
2. ประเภท
3. แหล่งข้อมูล (`คำขออนุมัติ` or `เครื่องสแกน`)
4. ช่วงเวลา
5. จำนวน (วัน/นาที)
6. รายละเอียด
7. สถานะ

Rows sort by event date, then a stable event-type order, then source. All database-derived values are escaped before insertion into HTML.

## Row Presentation

- Leave rows show leave type, day part, counted days, original approved range, and reason.
- Late/early request rows show request time, approved minutes with fallback to requested minutes, and reason.
- Scanner late/early rows show effective shift time, scan time, and actual deviation minutes.
- OT request rows show approved range/minutes, reason, and actual OT minutes when a valid scan is available.
- Scanner OT rows show effective shift end, check-out, and actual OT minutes.
- Activity rows show activity type, activity/course name, clipped date or range, day part, location, and objective.
- Shift-swap rows show requester and target names, both dates, the selected employee's role, and reason.

## API Contract

Add a dedicated GET action under the attendance report API family:

```text
api/attendance_api.php?action=employee_request_attendance_report&employee_id=123&month=2026-07
```

The response shape is:

```json
{
  "status": "success",
  "month": "2026-07",
  "employee": {
    "id": 123,
    "full_name": "ชื่อ นามสกุล",
    "position_name": "ตำแหน่ง",
    "company_name": "บริษัท",
    "branch_name": "สาขา"
  },
  "summary": {
    "total_events": 8,
    "approved_requests": 5,
    "scanner_events": 3,
    "actual_overtime_minutes": 90
  },
  "data": []
}
```

Every data item exposes a stable `event_key`, `event_date`, `event_type`, `source`, `time_label`, numeric `amount`, `amount_unit`, `detail`, and `status_label`, plus optional structured fields needed by the renderer. An empty valid report returns success with zero summary values and an empty data array.

## Code Boundaries

- A new focused helper file owns event normalization, event keys, scanner minute calculations, stable sorting, and summary counting as pure testable functions.
- `api/attendance_api.php` owns authorization, input validation, scoped employee lookup, bulk monthly data loading, source-specific queries, and JSON output.
- A dedicated JavaScript file owns Select2 initialization, manual loading, safe rendering, filters, summary display, and DataTable lifecycle.
- The new PHP page owns markup and the admin/HR role gate.
- `includes/header.php` and `includes/footer.php` own navigation, active state, and script inclusion.

No database schema change is required.

## Performance and Safety

- Query one selected employee for one month; do not call a full monthly report once per employee.
- Load scanner rows, overrides, shifts, approved requests, activities, and swaps in bounded month-scoped queries.
- Use prepared statements for all employee and date inputs.
- Enforce HR scope before any event query.
- Escape names, reasons, locations, objectives, organization values, and request labels.
- Do not expose attachment paths or unrelated employee personal data.

## Verification

Focused tests must prove:

- Required employee/month validation and forged employee scope denial.
- Only final `approved` request rows are included for every request type.
- Leave/activity month clipping and day-part behavior.
- Shift swaps include the selected employee as requester or target and describe the counterpart correctly.
- Effective attendance uses scanner data plus HR overrides, dated shifts, day swaps, holidays, leave, and activities.
- Late tolerance, early minutes, OT minutes, missing scans, and overnight boundaries.
- Approved OT and actual OT comparison fields.
- Stable event keys, sorting, summary totals, and empty results.
- Select2, manual load, Thai labels, escaped rendering, error states, client-side filtering, and DataTable lifecycle.
- Report navigation and active state.

Run PHP syntax checks for changed PHP files, focused PHP/API tests, JavaScript syntax and UI tests, relevant existing attendance/leave/activity/day-swap tests, sidebar tests, and `git diff --check`.

## Out of Scope

- Creating, editing, approving, or cancelling requests from this report.
- Changing attendance import, request approval, leave balance, shift assignment, or cancellation behavior.
- Excel/PDF export.
- Manager or employee access.
- Automatic payroll or OT payment calculation.
