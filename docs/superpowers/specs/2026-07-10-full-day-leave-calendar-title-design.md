# Full-day Leave Calendar Title

## Goal

Make a full-day approved leave entry show its leave type directly in the attendance calendar, such as `ลากิจ` or `ลาป่วย`, instead of combining a normal or generic attendance label with the leave type.

## Behavior

- When an attendance row has `status = leave` and a non-empty `leave_name`, the calendar event title is exactly `leave_name`.
- Partial-day hourly leave remains a supplement to the attendance status, for example `ปกติ + ลากิจ 10:00-12:00 2 ชม.`.
- Activity and late/early request labels continue to be appended to the attendance status.
- Calendar colors, click behavior, and the detail modal remain unchanged.
- If a leave row has no `leave_name`, the existing `status_label` remains as the fallback.

## Implementation

Update `attendanceCalendarEventTitle()` in `assets/js/attendance.js` to return the leave type early only for full-day leave rows. Keep the existing supplement-building path for every other status.

## Verification

Update `tests/attendance_calendar_test.js` so a full-day personal leave expects `ลากิจ`, while existing activity and hourly-request assertions continue to confirm their current combined titles. Run the focused JavaScript test, JavaScript syntax check, and `git diff --check`.
