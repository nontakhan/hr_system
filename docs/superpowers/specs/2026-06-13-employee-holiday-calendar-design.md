# Employee Holiday Calendar Design

## Goal

Add a self-service holiday calendar where employees can see both company holidays and their own regular shift holidays, then jump directly to leave requests or day-swap requests.

## Scope

- Create a read-only employee-facing page: `holiday_calendar.php`.
- Show company holidays from `company_holidays`.
- Show regular holidays derived from the signed-in employee's shift, shift overrides, and approved day swaps.
- Add quick action buttons to `leave_request.php` and `day_swap_request.php`.
- Add a sidebar menu item visible to authenticated users.
- Keep `company_holidays.php` as the HR/admin management page.

## Architecture

The page uses FullCalendar, following the existing attendance and day-swap calendar patterns. A new read-only API returns month events for the current employee. The API reuses existing holiday logic from `day_swap_helpers.php` for shift holidays and reads company holidays through a small helper that shapes events consistently for the UI.

## Data Flow

1. `holiday_calendar.php` loads the shared authenticated layout and enables FullCalendar.
2. `assets/js/holiday_calendar.js` requests `api/holiday_calendar_api.php?month=YYYY-MM`.
3. The API validates the session and month.
4. Company holidays and shift holidays are combined into event objects.
5. FullCalendar renders the month, with distinct colors and a summary count.

## UX

- Header: page title, short description, and two quick buttons.
- Filter: a month picker and refresh button.
- Calendar: company holidays and regular holidays use different colors.
- Summary: counts for company holidays, regular holidays, and total days shown.
- Empty/loading/error states are explicit.

## Testing

- PHP helper test verifies event merging and company-holiday precedence.
- JS test verifies event colors, summary HTML, and loading state.
- Lint touched PHP files and run `node --check` on touched JS.
