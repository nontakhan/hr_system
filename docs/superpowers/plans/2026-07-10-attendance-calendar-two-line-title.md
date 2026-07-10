# Attendance Calendar Two-line Title Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Show attendance calendar text as status on the first line and details on the second line.

**Architecture:** Keep the change inside the existing calendar title helper. Use newline-separated event titles and CSS that lets FullCalendar render newline characters.

**Tech Stack:** JavaScript, FullCalendar, CSS, Node.js contract tests.

## Global Constraints

- Popup details must continue using the original row data.
- Calendar color/status classes must not change.
- If a day has no detail, keep a one-line status title.
- Apply the two-line format to leave, holidays, activities, hourly requests, and mixed request days.

---

### Task 1: Split Calendar Titles Into Status and Detail Lines

**Files:**
- Modify: `tests/attendance_calendar_test.js`
- Modify: `assets/js/attendance.js`
- Modify: `assets/style.css`

**Interfaces:**
- Consumes: `buildAttendanceCalendarEvent(row)` and `attendanceCalendarEventTitle(row)`
- Produces: calendar event `title` strings that contain `\n` when details exist.

- [x] **Step 1: Write failing tests**

Update calendar title assertions so:

```js
assertSame('ลา\nลากิจ', personalLeaveEvent.title, 'Full-day leave should show status and leave type on separate calendar lines.');
assertSame('ปกติ\nSafety Training', trainingEvent.title, 'Activity days should show status and activity detail on separate calendar lines.');
assertSame('ปกติ\nขอมาสาย 35 นาที', approvedLateRequestEvent.title, 'Approved late requests should show normal status and request detail on separate calendar lines.');
assertSame('วันหยุดบริษัท\nวันหยุดบริษัท', companyHolidayEvent.title, 'Company holidays should show status and detail on separate calendar lines.');
```

- [x] **Step 2: Verify RED**

Run:

```powershell
node tests/attendance_calendar_test.js
```

Expected: FAIL because current titles use a single-line ` + ` format or leave name only.

- [x] **Step 3: Implement the title helper**

Change `attendanceCalendarEventTitle(row)` so it builds `statusTitle` and `details`. Return `statusTitle` alone when details are empty, otherwise return `${statusTitle}\n${details.join(', ')}`.

- [x] **Step 4: Make newline visible in FullCalendar**

Add CSS under the attendance calendar event styles:

```css
#attendanceCalendar .fc-event-title {
    white-space: pre-line;
}
```

- [x] **Step 5: Verify GREEN**

Run:

```powershell
node tests/attendance_calendar_test.js
node --check assets/js/attendance.js
git diff --check
```

Expected: all commands exit 0.
