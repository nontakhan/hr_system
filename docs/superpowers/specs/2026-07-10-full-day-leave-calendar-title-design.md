# Attendance Calendar Leave and Approved Late-request Presentation

## Goal

Make full-day leave and approved late-arrival requests easy to understand directly from the attendance calendar and its summary.

## Behavior

- When an attendance row has `status = leave` and a non-empty `leave_name`, the calendar event title is exactly `leave_name`.
- Partial-day hourly leave remains a supplement to the attendance status, for example `ปกติ + ลากิจ 10:00-12:00 2 ชม.`.
- Activity and late/early request labels continue to be appended to the attendance status.
- Click behavior and the detail modal remain unchanged. Calendar colors continue to follow the presentation status.
- If a leave row has no `leave_name`, the existing `status_label` remains as the fallback.
- A row whose raw attendance status is `late` remains late when it has no approved late-arrival request.
- A late row with an approved `ขอมาสาย` hourly-request label is presented as `present`: its title starts with `ปกติ`, it uses the normal green color, and it is counted under the normal summary instead of the late summary.
- The original check-in/check-out values and approved-request details remain available in the detail modal.

## Implementation

Update the presentation helpers in `assets/js/attendance.js`:

- `attendanceCalendarEventTitle()` returns the leave type early only for full-day leave rows.
- `attendanceCalendarPresentationStatus()` returns `present` for a late row containing an approved `ขอมาสาย` label.
- The event title uses the normal label when the presentation status has changed from late to present.
- Calendar event colors, day classes, and summary counts use the presentation status, while the original row remains unchanged for detail rendering.

## Verification

Update `tests/attendance_calendar_test.js` to cover:

- Full-day personal leave expects `ลากิจ`.
- Late attendance without an approved late request remains orange and counted as late.
- Late attendance with an approved late request displays `ปกติ + ขอมาสาย ...`, uses green, and is counted as normal.
- Existing activity and other hourly-request behavior remains unchanged.

Run the focused JavaScript test, JavaScript syntax check, and `git diff --check`.
