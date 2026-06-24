<?php
$root = dirname(__DIR__);

function assertContainsText($source, $needle, $message) {
    if (strpos($source, $needle) === false) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Missing: ' . $needle . PHP_EOL);
        exit(1);
    }
}

function assertNotContainsText($source, $needle, $message) {
    if (strpos($source, $needle) !== false) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Unexpected: ' . $needle . PHP_EOL);
        exit(1);
    }
}

$editForm = file_get_contents($root . '/employee_edit.php');
$api = file_get_contents($root . '/api/employee_api.php');

assertContainsText($editForm, 'name="username"', 'Employee edit form should submit username.');
assertNotContainsText($editForm, 'name="username" value="<?php echo $emp[\'username\']; ?>" readonly', 'Existing username field should be editable.');
assertContainsText($api, 'SELECT id FROM users WHERE username = ? AND employee_id <> ?', 'Employee update should reject username duplicates on other employee accounts.');
assertContainsText($api, 'UPDATE users SET username=?, role=? WHERE employee_id=?', 'Employee update should persist username changes when password is unchanged.');
assertContainsText($api, 'UPDATE users SET username=?, password=?, role=? WHERE employee_id=?', 'Employee update should persist username changes when password is changed.');
