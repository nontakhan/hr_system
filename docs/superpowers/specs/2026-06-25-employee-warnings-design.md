# Employee Warnings Design

## Goal

Add an employee warning-record system so HR/admin users can define warning items, record warnings received by employees, review monthly warning summaries across all employees, and preserve the history for annual salary-increase review.

## Scope

Create a new employee-warning module with:

- a master list of warning items, such as "แต่งกายไม่เรียบร้อย" and "ไม่ใส่เสื้อบริษัท";
- an HR/admin monthly overview page for all employees with summary cards and detail drill-down;
- an HR/admin modal to record a warning by employee, warning item, incident date, and optional detail;
- an employee self-service page where the logged-in employee can view only their own monthly warning summary and details.

The first version is a record-keeping module only. It does not include approval, workflow status, penalties, attachments, notifications, or active/inactive warning item states.

## Selected UX

Use one operational HR/admin page plus one read-only employee page.

### HR/Admin Page

Create `employee_warnings.php` for `admin` and `hr`.

The page will:

- include `includes/auth_check.php`;
- redirect non-HR users to `dashboard.php`;
- provide a month selector defaulting to the current month;
- show summary cards for total warnings, employees with warnings, distinct warning item count, and the most frequent warning item;
- show a monthly table grouped by employee;
- include employee context columns such as employee name, company/branch/department/position where available;
- show each employee's warning count and the warning item names found in the selected month;
- provide a detail action per employee to view incident-date rows for that employee/month;
- provide an "เพิ่มใบเตือน" button that opens a Bootstrap modal for recording a new warning;
- provide a warning-item management area or modal for adding, editing, and deleting unused warning items.

The monthly overview is the primary HR experience because HR needs to see all employees in a month before drilling into one person.

### Employee Page

Create `my_warnings.php` for all logged-in users with a linked `employee_id`.

The page will:

- include `includes/auth_check.php`;
- use only `$_SESSION['employee_id']` for data access;
- provide a month selector defaulting to the current month;
- show summary cards for total warnings and distinct warning item count;
- show a read-only details table with incident date, warning item, and optional detail;
- show a friendly empty state when the selected month has no warnings.

The employee page must not accept an employee id from `$_GET`, `$_POST`, or JSON input.

## Data Model

Create `warning_types`.

Core fields:

- `id`
- `type_name`
- `description`
- `created_by`
- `updated_by`
- `created_at`
- `updated_at`

Rules:

- `type_name` is required and should be unique after trimming.
- There is no active/inactive status.
- A warning type can be edited.
- A warning type can be deleted only when it has not been used by any employee warning record.

Create `employee_warnings`.

Core fields:

- `id`
- `employee_id`
- `warning_type_id`
- `warning_date`
- `detail`
- `created_by`
- `updated_by`
- `created_at`
- `updated_at`

Rules:

- `employee_id`, `warning_type_id`, and `warning_date` are required.
- `detail` is optional.
- Warning records are preserved as historical HR data for annual salary review.
- The schema should use foreign keys to `employees.id`, `warning_types.id`, and `users.id` where the local schema supports them.

## API Shape

Add `api/employee_warning_api.php`.

Shared behavior:

- require login for every action;
- return the existing JSON shape: `status`, `message`, and optional `data`;
- validate month input as `YYYY-MM`;
- validate dates as `Y-m-d`;
- trim text fields;
- return friendly Thai validation messages for missing employee, warning item, or incident date.

HR/admin actions:

- `monthly_summary`: returns monthly summary cards and employee-grouped rows for all employees with warnings in the selected month;
- `employee_month_details`: returns detail rows for one employee in one selected month;
- `create_warning`: records a warning for an employee;
- `get_warning_types`: lists all warning items;
- `create_warning_type`: adds one warning item;
- `update_warning_type`: edits one warning item;
- `delete_warning_type`: deletes only unused warning items.

Employee action:

- `my_monthly_warnings`: returns the logged-in employee's monthly summary and detail rows.

Access rules:

- Only `admin` and `hr` can create warnings or manage warning items.
- Only `admin` and `hr` can call all-employee monthly summary and employee detail actions.
- Ordinary employees can call only `my_monthly_warnings`.
- The employee self-service action must use `$_SESSION['employee_id']` and ignore any supplied employee id.

## Navigation

Update `includes/header.php`.

Recommended sidebar placement:

- Add a new "ใบเตือนพนักงาน" menu entry.
- For `admin` and `hr`, link to `employee_warnings.php`.
- For ordinary employees, link to `my_warnings.php`.

The module should not be placed under leave or attendance menus because it is a separate HR record used for compensation review.

## Frontend Assets

Add `assets/js/employee_warnings.js`.

The script will:

- load HR/admin monthly summary;
- load employee self-service monthly data;
- submit new employee warning records;
- load and manage warning type rows;
- render empty/loading/error rows consistently with existing table pages;
- use SweetAlert for success, error, delete confirmation, and validation feedback.

The same JS file can support both pages by checking for page-specific root elements.

## Error Handling

- If a user is not logged in, existing auth handling redirects to login.
- If a logged-in employee has no linked employee profile, `my_warnings.php` shows a clear Thai message and no data table.
- If HR selects an invalid month or date, the API returns a validation error without changing data.
- If HR tries to delete a warning type already used in history, the API rejects the delete and explains that historical records exist.
- If no records exist for the selected month, both pages show an empty state rather than an error.

## Reporting Considerations

This design supports annual salary-review reporting by preserving employee warning history with incident dates and normalized warning item ids.

The first implementation will focus on monthly screen reporting. A future annual export/report can aggregate `employee_warnings` by employee, warning type, and calendar or fiscal year without changing the data model.

## Tests And Verification

Add focused contract tests, for example `tests/employee_warnings_contract_test.php`, that check:

- `employee_warnings.php`, `my_warnings.php`, and `api/employee_warning_api.php` exist;
- the HR/admin page restricts access to `admin` and `hr`;
- the employee page uses `$_SESSION['employee_id']` and does not accept a URL employee id;
- the API has HR/admin actions for monthly summary, details, warning creation, and warning type management;
- the API has an employee-only self-service action;
- `delete_warning_type` checks for existing `employee_warnings` rows before deleting;
- `includes/header.php` contains navigation for the warning module.

Run:

- `C:\xampp\php\php.exe -l employee_warnings.php`
- `C:\xampp\php\php.exe -l my_warnings.php`
- `C:\xampp\php\php.exe -l api\employee_warning_api.php`
- `node --check assets/js/employee_warnings.js`
- `C:\xampp\php\php.exe tests\employee_warnings_contract_test.php`
- `git diff --check`

## Out Of Scope

- Approval workflow or warning status.
- Active/inactive warning item state.
- Attachments or signed warning documents.
- Automatic notifications.
- Payroll rule automation or direct salary adjustment calculation.
- Annual export/report UI in the first version.
