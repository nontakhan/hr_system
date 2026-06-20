<?php
$root = dirname(__DIR__);
$pagePath = $root . '/change_password.php';
$apiPath = $root . '/api/account_api.php';
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

if (!file_exists($pagePath)) {
    fwrite(STDERR, "Assertion failed: change_password.php should exist\n");
    exit(1);
}

if (!file_exists($apiPath)) {
    fwrite(STDERR, "Assertion failed: api/account_api.php should exist\n");
    exit(1);
}

$page = file_get_contents($pagePath);
$api = file_get_contents($apiPath);
$header = file_get_contents($headerPath);

assertContainsText($header, 'href="change_password.php"', 'Topbar password link should open change_password.php.');

assertContainsText($page, "require_once 'includes/auth_check.php';", 'Change password page should require login.');
assertContainsText($page, 'id="changePasswordForm"', 'Change password page should render a form.');
assertContainsText($page, 'name="current_password"', 'Change password form should ask for current password.');
assertContainsText($page, 'name="new_password"', 'Change password form should ask for new password.');
assertContainsText($page, 'name="confirm_password"', 'Change password form should ask for password confirmation.');
assertContainsText($page, 'api/account_api.php?action=change_password', 'Change password form should submit to account API.');

assertContainsText($api, "\$_SESSION['user_id']", 'Account API should use current session user id.');
assertContainsText($api, "\$action === 'change_password'", 'Account API should route change_password.');
assertContainsText($api, 'password_verify', 'Change password API should verify the current password.');
assertContainsText($api, 'password_hash', 'Change password API should hash the new password.');
assertContainsText($api, 'UPDATE users SET password=? WHERE id=?', 'Change password API should update only the current user password.');
assertNotContainsText($api, "employee_id=?", 'Change password API should not update by employee id.');
assertNotContainsText($api, "role=?", 'Change password API should not update user role.');

echo "Change password contract checks passed.\n";
