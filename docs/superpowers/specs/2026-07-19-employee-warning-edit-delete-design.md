# Employee Warning Edit and Delete Design

## Goal

Allow admin and HR users to edit or delete every employee warning from the employee warning detail modal, including warnings created manually and warnings created in bulk from attendance or leave reports.

## User Interface

- Add an actions column to the warning detail table in `employee_warnings.php`.
- Each warning row has Edit and Delete buttons with Thai labels or accessible titles.
- Edit reuses the existing employee warning modal. The modal changes its title and submit label to edit mode and populates employee, warning type, event date, and detail.
- Opening the modal from the Add button always resets it to create mode.
- Delete requires explicit confirmation before sending the request.
- Successful edit or deletion refreshes the selected month's summary and the currently open employee detail list. If an edit moves the warning to another employee or month, the old list reflects that removal after refresh.
- Errors are shown using the page's existing alert mechanism and do not silently close the active modal.

## API and Data Flow

- The employee month detail response includes the identifiers and field values needed by the edit form: warning ID, employee ID, warning type ID, warning date, and detail.
- Add an `update_warning` POST action. It validates the warning ID, employee, warning type, warning date, and detail using the same rules as creation, then updates `updated_by`.
- Add a `delete_warning` DELETE action. It validates the warning ID and deletes only the selected warning record.
- Editing a bulk-created warning preserves `source_type`, `source_key`, and `source_event_date`; these provenance fields are not editable from this screen.
- Deleting a bulk-created warning removes its source uniqueness record with the row. The same source event can therefore be selected and warned again later.

## Authorization and Scope

- Existing role checks continue to require `admin` or `hr` for create, update, and delete actions.
- Update and delete load the existing warning joined to its employee before mutation.
- For an HR user, both the existing warning owner and the newly selected employee on update must be inside the user's current HR scope.
- Admin users retain unrestricted employee scope within the existing admin behavior.
- Missing or out-of-scope warning IDs return the same safe not-found/invalid response and disclose no employee information.

## Validation and Error Handling

- Employee and warning type IDs must exist.
- Warning dates must be valid Gregorian `YYYY-MM-DD` values.
- Detail remains optional and follows the existing storage behavior.
- Database failures return the existing generic system error while detailed errors remain server-side.
- Update and delete report a clear Thai error when the target warning no longer exists.

## Testing

- Extend the employee warning contract test first and observe it fail for missing edit/delete behavior.
- Cover the detail modal actions, edit-mode form state, and API calls in a focused JavaScript contract/regression test.
- Cover helper/API contracts for update, delete, preservation of source fields, `updated_by`, and scoped target checks.
- Run PHP and JavaScript syntax checks for changed files, focused warning tests, `git diff --check`, and inspect worktree status.

## Out of Scope

- Editing source provenance fields.
- Audit-history/version tables for prior warning values.
- Bulk edit or bulk delete from the detail modal.
- Changes to the employee self-service warning page.
