<?php
$root = dirname(__DIR__);
$pagePath = $root . '/my_profile.php';
$apiPath = $root . '/api/employee_api.php';
$headerPath = $root . '/includes/header.php';

function assertContainsText($haystack, $needle, $message) {
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, "Assertion failed: {$message}\nMissing: {$needle}\n");
        exit(1);
    }
}

function assertNotContainsText($haystack, $needle, $message) {
    if (strpos($haystack, $needle) !== false) {
        fwrite(STDERR, "Assertion failed: {$message}\nUnexpected: {$needle}\n");
        exit(1);
    }
}

function sliceFunction($source, $functionName) {
    $needle = 'function ' . $functionName;
    $start = strpos($source, $needle);
    if ($start === false) {
        fwrite(STDERR, "Assertion failed: function {$functionName} should exist\n");
        exit(1);
    }

    $next = strpos($source, "\nfunction ", $start + strlen($needle));
    if ($next === false) {
        return substr($source, $start);
    }
    return substr($source, $start, $next - $start);
}

if (!file_exists($pagePath)) {
    fwrite(STDERR, "Assertion failed: my_profile.php should exist\n");
    exit(1);
}

$page = file_get_contents($pagePath);
$api = file_get_contents($apiPath);
$header = file_get_contents($headerPath);
$selfUpdate = sliceFunction($api, 'updateMyProfile');

assertContainsText($page, "require_once 'includes/auth_check.php';", 'My profile page should require login.');
assertContainsText($page, "\$_SESSION['employee_id']", 'My profile page should use the current session employee id.');
assertNotContainsText($page, "\$_GET['id']", 'My profile page should not accept an employee id from the URL.');
assertContainsText($page, 'id="myProfileForm"', 'My profile page should render the self-service form.');
assertContainsText($page, 'action=update_my_profile', 'My profile form should submit to the self-service API action.');

foreach ([
    'education_level',
    'phone_number',
    'current_address',
    'province',
    'district',
    'postal_code',
    'emergency_contact_name',
    'emergency_contact_phone',
] as $field) {
    assertContainsText($page, 'name="' . $field . '"', "My profile form should include {$field}.");
    assertContainsText($selfUpdate, "'" . $field . "'", "Self-service API should update {$field}.");
}

assertContainsText($header, 'href="my_profile.php"', 'Topbar profile link should open my_profile.php.');
assertContainsText($api, "\$action === 'update_my_profile'", 'Employee API should route update_my_profile.');
assertContainsText($selfUpdate, "\$_SESSION['employee_id']", 'Self-service update should use the session employee id.');
assertContainsText($selfUpdate, 'ensureEmployeePostalCodeColumn($mysqli);', 'Self-service update should ensure postal_code exists.');
assertNotContainsText($selfUpdate, "getVal(\$data, 'id'", 'Self-service update should ignore posted employee id.');

foreach ([
    'company_id',
    'branch_id',
    'department_id',
    'position_id',
    'supervisor_id',
    'default_shift_id',
    'status',
    'role',
    'password',
    'username',
    'citizen_id',
    'birth_date',
    'first_name_th',
    'last_name_th',
] as $protectedField) {
    assertNotContainsText($selfUpdate, $protectedField . '=?', "Self-service update should not update {$protectedField}.");
    assertNotContainsText($selfUpdate, "getVal(\$data, '" . $protectedField . "'", "Self-service update should not read {$protectedField} from posted data.");
}

echo "My profile contract checks passed.\n";
