<?php
$root = dirname(__DIR__);

function assert_contains_text(string $haystack, string $needle, string $message): void {
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, "FAIL: {$message}\nMissing: {$needle}\n");
        exit(1);
    }
}

function assert_not_contains_text(string $haystack, string $needle, string $message): void {
    if (strpos($haystack, $needle) !== false) {
        fwrite(STDERR, "FAIL: {$message}\nUnexpected: {$needle}\n");
        exit(1);
    }
}

function read_required(string $path): string {
    if (!is_file($path)) {
        fwrite(STDERR, "FAIL: Missing file {$path}\n");
        exit(1);
    }
    return file_get_contents($path);
}

$api = read_required($root . '/api/employee_warning_api.php');
$helper = read_required($root . '/includes/employee_warning_helpers.php');
$hrPage = read_required($root . '/employee_warnings.php');
$myPage = read_required($root . '/my_warnings.php');
$js = read_required($root . '/assets/js/employee_warnings.js');
$header = read_required($root . '/includes/header.php');

assert_contains_text($hrPage, "in_array(\$_SESSION['role'], ['admin', 'hr']", 'HR page must restrict access to admin/hr');
assert_contains_text($hrPage, 'employeeWarningMonth', 'HR page must expose a month selector');
assert_contains_text($hrPage, 'employeeWarningForm', 'HR page must expose create warning form');
assert_contains_text($hrPage, 'warningTypeForm', 'HR page must expose warning type management form');

assert_contains_text($myPage, "\$_SESSION['employee_id']", 'Employee page must use the session employee id');
assert_not_contains_text($myPage, "\$_GET['employee_id']", 'Employee page must not read employee id from URL');
assert_not_contains_text($myPage, "\$_GET['id']", 'Employee page must not read id from URL');
assert_contains_text($myPage, 'myWarningMonth', 'Employee page must expose a month selector');

foreach ([
    'monthly_summary',
    'employee_month_details',
    'search_employee_warnings',
    'employee_warning_history',
    'create_warning',
    'update_warning',
    'delete_warning',
    'get_warning_types',
    'create_warning_type',
    'update_warning_type',
    'delete_warning_type',
    'my_monthly_warnings',
    'bulk_create',
] as $action) {
    assert_contains_text($api, $action, "API must expose {$action}");
}

assert_contains_text($api, "in_array(\$role, ['admin', 'hr']", 'API must gate HR/admin actions');
assert_contains_text($api, "\$_SESSION['employee_id']", 'API self-service action must use session employee id');
assert_contains_text($helper, 'CREATE TABLE IF NOT EXISTS warning_types', 'Helper must create warning_types table');
assert_contains_text($helper, 'CREATE TABLE IF NOT EXISTS employee_warnings', 'Helper must create employee_warnings table');
assert_contains_text($helper, 'source_type', 'Employee warnings must store a report source type');
assert_contains_text($helper, 'source_key', 'Employee warnings must store a stable report source key');
assert_contains_text($helper, 'source_event_date', 'Employee warnings must store the original report event date');
assert_contains_text($helper, 'uq_employee_warnings_source', 'Employee warnings must prevent duplicate report sources');
assert_contains_text($helper, 'employeeWarningDeleteType', 'Helper must include protected delete function');
assert_contains_text($helper, 'SELECT id FROM employee_warnings WHERE warning_type_id = ?', 'Delete must check existing warning history');
assert_contains_text($helper, 'employeeWarningUpdateRecord', 'Helper must update a warning record');
assert_contains_text($helper, 'employeeWarningDeleteRecord', 'Helper must delete a warning record');
assert_contains_text($helper, 'updated_by = ?', 'Warning updates must record the acting user');
assert_contains_text($helper, "JOIN employees e ON ew.employee_id = e.id", 'Warning mutations must authorize through employee scope');
assert_contains_text($helper, 'SET employee_id = ?, warning_type_id = ?, warning_date = ?, detail = ?, updated_by = ?', 'Warning update must change only editable fields and audit user');
assert_not_contains_text($helper, 'SET source_type =', 'Warning update must preserve bulk source type');
assert_not_contains_text($helper, 'source_key = ?', 'Warning update must preserve bulk source key');
assert_contains_text($helper, 'employeeWarningNormalizeSearch', 'Helper must normalize all-month name searches');
assert_contains_text($helper, 'employeeWarningSearchByName', 'Helper must search warning employees across all months');
assert_contains_text($helper, 'employeeWarningFetchEmployeeHistory', 'Helper must fetch all warning history for an employee');
assert_contains_text($helper, "CONCAT_WS(' ', e.first_name_th, e.last_name_th) LIKE ?", 'Name search must use a prepared partial-name pattern');
assert_contains_text($helper, "employeeWarningEmployeeScopeClause(\$role, \$scopes, 'e')", 'Warning searches and history must reuse HR scope filtering');

assert_contains_text($js, 'initEmployeeWarningsAdminPage', 'JS must initialize HR/admin page');
assert_contains_text($js, 'initMyWarningsPage', 'JS must initialize employee page');
assert_contains_text($header, 'employee_warnings.php', 'Sidebar must link HR/admin warning page');
assert_contains_text($header, 'my_warnings.php', 'Sidebar must link employee warning page');

$myWarningPos = strpos($header, 'href="my_warnings.php"');
$adminWarningPos = strpos($header, 'href="employee_warnings.php"');
$peopleAdminPos = strpos($header, 'sidebar-section-label">บริหารบุคลากร');
if ($myWarningPos === false || $adminWarningPos === false || $peopleAdminPos === false || $myWarningPos > $peopleAdminPos || $adminWarningPos < $peopleAdminPos) {
    fwrite(STDERR, "FAIL: Warning menus must split employee self-service under personal items and HR/admin warnings under people admin\n");
    exit(1);
}

$adminWarningMenu = substr($header, $peopleAdminPos);
assert_not_contains_text($adminWarningMenu, "isActive('employee_warnings.php') || isActive('my_warnings.php')", 'HR/admin warning parent must not become active on my_warnings.php');
assert_not_contains_text($adminWarningMenu, 'href="my_warnings.php"', 'HR/admin warning section should not duplicate the employee self-service warning link');
assert_contains_text($header, '<i class="fas fa-user-shield me-2"></i> ใบเตือนของฉัน', 'Employee warning menu must include an icon in the self-service section');

echo "employee warnings contract ok\n";
