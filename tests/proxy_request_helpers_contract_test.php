<?php
require_once __DIR__ . '/../includes/proxy_request_helpers.php';

function assertProxySame($expected, $actual, $message) {
    if ($expected !== $actual) {
        fwrite(STDERR, $message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

$_SESSION = [
    'user_id' => 99,
    'employee_id' => 42,
    'role' => 'hr',
];

$audit = proxyRequestBuildAuditPayload('created by phone request');
assertProxySame(99, $audit['created_by_user_id'], 'audit should store session user id');
assertProxySame(42, $audit['created_by_employee_id'], 'audit should store session employee id');
assertProxySame('hr', $audit['created_by_role'], 'audit should store session role');
assertProxySame('admin_proxy', $audit['created_via'], 'audit should mark proxy source');
assertProxySame('created by phone request', $audit['proxy_note'], 'audit should trim proxy note');

assertProxySame(true, proxyRequestRoleCanCreate('admin'), 'admin can create proxy requests');
assertProxySame(true, proxyRequestRoleCanCreate('hr'), 'hr can create proxy requests');
assertProxySame(false, proxyRequestRoleCanCreate('manager'), 'manager cannot create proxy requests');
assertProxySame(false, proxyRequestRoleCanCreate('employee'), 'employee cannot create proxy requests');

$display = proxyRequestCreatorLabel([
    'proxy_creator_name' => 'HR Test User',
    'created_by_role' => 'hr',
]);
assertProxySame('Created by HR/Admin: HR Test User', $display, 'creator label should use employee name');

$fallback = proxyRequestCreatorLabel([
    'proxy_creator_name' => '',
    'created_by_role' => 'admin',
]);
assertProxySame('Created by HR/Admin: admin', $fallback, 'creator label should fall back to role');
