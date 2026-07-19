# Employee Warning All-Month Name Search Design

## Goal

Allow admin and HR users to find employees by partial Thai name across all employee-warning history, without changing the existing selected-month view.

## User Interface

- Add a name-search input, Search button, and Clear Search button above the employee warning summary table.
- Search is submitted by the button or Enter.
- A non-empty search switches the table into all-month search mode and displays matching employees aggregated across their complete warning history.
- Search rows retain the existing employee, unit, warning count, warning types, and detail action columns.
- The table heading/badge identifies all-month search mode and shows the active term.
- Clear Search empties the term and restores the selected month's summary and cards.
- In search mode, opening a row displays all warnings for that employee rather than only the selected month. Existing edit/delete actions remain available.
- After an edit or deletion, the active search result and open all-history detail list refresh without losing the search term.

## API and Queries

- Add a scoped GET action `search_employee_warnings` accepting `q`.
- Normalize the term by trimming whitespace, require at least one non-whitespace character, and cap it at 100 Unicode characters.
- Match against `CONCAT_WS(' ', e.first_name_th, e.last_name_th)` with a prepared `LIKE` pattern.
- Aggregate warning count and distinct warning types over all warning dates, returning the same row shape as the monthly summary.
- Add a scoped GET action `employee_warning_history` accepting `employee_id` and returning the same detail shape as `employee_month_details` without a month range.
- Reuse a shared detail-select helper so monthly detail, all-history detail, and self-service behavior keep the same selected fields and ordering.

## Authorization and Privacy

- Both actions require the existing `admin` or `hr` role gate.
- Apply `employeeWarningEmployeeScopeClause()` to search results and history detail queries.
- Out-of-scope employees return no rows and disclose no identifying details.
- Render all returned names and text through the existing escaping helpers.

## UI State and Errors

- Store the active detail context with a mode of `month` or `history`.
- Search-mode detail refresh calls the history endpoint; month-mode refresh calls the existing month endpoint.
- Summary cards remain month-specific. Entering search mode does not replace their values with all-time totals, avoiding mixed metric definitions.
- Empty searches display a Thai validation alert without sending a request.
- API and network failures render the existing table-level or SweetAlert error and preserve the search term for retry.

## Testing

- Extend the PHP contract first for both actions, scoped helper functions, prepared name matching, and history detail fields.
- Add focused JavaScript contract assertions for the search controls, both detail modes, clear behavior, and active-mode refresh after mutations.
- Run warning-focused PHP/Node regressions, changed-file syntax checks, and `git diff --check`.

## Out of Scope

- Searching by citizen ID, department, or warning type.
- Pagination or server-side DataTables conversion.
- Changes to the employee self-service page.
- Full-text indexing or database schema changes.
