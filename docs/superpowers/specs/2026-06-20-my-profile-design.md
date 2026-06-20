# My Profile Page Design

## Goal

Add a logged-in user profile page so employees can view their own HR profile and update low-risk personal information without using the HR employee edit screen.

## Scope

Create a new authenticated page, `my_profile.php`, linked from the existing topbar profile menu. The page must always use the current session employee id and must not accept an employee id from the URL.

Employees can edit only:

- `education_level`
- `phone_number`
- `current_address`
- `province`
- `district`
- `postal_code`
- `emergency_contact_name`
- `emergency_contact_phone`

Employees can view but not edit:

- Thai and English names
- nickname
- citizen id
- birth date
- gender
- marital status
- religion
- blood group
- company
- branch
- department
- position
- supervisor
- default shift
- start date
- employee status
- username, role, HR scope, password, and all account or permission fields

## Recommended Approach

Use a new page and a new API action instead of reusing `employee_edit.php`.

This keeps the HR edit screen unchanged and avoids exposing employment, account, role, shift, or HR-scope fields to ordinary employees. The API update action will whitelist the allowed fields and force `WHERE id = $_SESSION['employee_id']`.

## Page Behavior

`my_profile.php` will:

- include `includes/auth_check.php`;
- redirect or show a friendly message if the session has no `employee_id`;
- fetch the current employee with joins for company, branch, department, position, supervisor, and shift labels;
- render read-only profile and employment cards;
- render an editable contact, address, education, and emergency-contact form;
- submit with JavaScript to `api/employee_api.php?action=update_my_profile`;
- show a SweetAlert success or error message;
- keep styling aligned with the existing Bootstrap employee pages.

The province and district controls should reuse the existing page pattern from employee add/edit where practical. If that shared behavior depends on `assets/js/employee.js`, the new page should provide the expected `data-province` and `data-district` attributes or include a small page-local initializer.

## API Behavior

Add `update_my_profile` to `api/employee_api.php`.

The action must:

- require login;
- require a positive `$_SESSION['employee_id']`;
- ignore any posted employee id;
- normalize editable text fields through the existing `getVal` helper pattern;
- update only the whitelisted fields;
- call the existing postal-code schema guard before touching `postal_code`;
- return the same JSON status/message shape used by current employee API calls.

The action must not update names, citizen id, birth date, employment fields, account fields, role, password, shift, HR scope, or employee status.

## Navigation

Update `includes/header.php` so the topbar dropdown profile link points to `my_profile.php`. The page should be active if a sidebar link is added later, but the initial implementation only needs the existing dropdown link.

## Error Handling

- If the user is not logged in, existing auth handling redirects to login.
- If the logged-in user has no linked employee profile, show a clear Thai message and no editable form.
- If the API update fails, keep the user on the page and show the returned message.
- If the session employee id does not match a row, return an error without updating anything.

## Tests And Verification

Add a focused contract test, for example `tests/my_profile_contract_test.php`, that checks:

- `my_profile.php` exists and uses `$_SESSION['employee_id']`;
- the page does not read an editable employee id from `$_GET['id']`;
- `includes/header.php` links the profile dropdown to `my_profile.php`;
- `api/employee_api.php` exposes `update_my_profile`;
- the API action uses the session employee id;
- the allowed self-service fields are present;
- protected fields such as role, password, company, branch, department, position, supervisor, status, and shift are not updated by the self-service action.

Run:

- `C:\xampp\php\php.exe -l my_profile.php`
- `C:\xampp\php\php.exe -l api\employee_api.php`
- `C:\xampp\php\php.exe -l includes\header.php`
- `C:\xampp\php\php.exe tests\my_profile_contract_test.php`
- `git diff --check`

## Out Of Scope

- HR approval workflow for profile edits.
- Editing legal identity fields such as name, citizen id, or birth date.
- Editing employment, shift, role, login, password, or HR scope fields.
- Profile photo upload changes.
- Full audit history for profile edits.
