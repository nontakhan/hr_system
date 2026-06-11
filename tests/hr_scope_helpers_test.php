<?php
require_once __DIR__ . '/../includes/hr_scope_helpers.php';

function assertHrScopeSame($expected, $actual, $message) {
    if ($expected !== $actual) {
        fwrite(STDERR, $message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

$scopes = hrScopeNormalizeRows([
    ['scope_type' => 'company', 'scope_id' => '2'],
    ['scope_type' => 'branch', 'scope_id' => '7'],
    ['scope_type' => 'company', 'scope_id' => '2'],
    ['scope_type' => 'branch', 'scope_id' => '0'],
    ['scope_type' => 'bad', 'scope_id' => '9'],
]);

assertHrScopeSame([2], $scopes['company_ids'], 'Company scopes should be unique positive ints.');
assertHrScopeSame([7], $scopes['branch_ids'], 'Branch scopes should be unique positive ints.');
assertHrScopeSame(true, hrScopeHasAnyScope($scopes), 'Combined scopes should be detected.');

$empty = hrScopeNormalizeRows([]);
assertHrScopeSame(false, hrScopeHasAnyScope($empty), 'Empty scopes should not grant access.');

$adminClause = hrScopeBuildEmployeeWhereClause('admin', [], 'e');
assertHrScopeSame('', $adminClause['sql'], 'Admin should not need an employee scope SQL clause.');
assertHrScopeSame([], $adminClause['params'], 'Admin scope SQL should not bind params.');

$hrClause = hrScopeBuildEmployeeWhereClause('hr', ['company_ids' => [2], 'branch_ids' => [7, 8]], 'e');
assertHrScopeSame(' AND (e.company_id IN (?) OR e.branch_id IN (?,?)) ', $hrClause['sql'], 'HR scope clause should combine company and branch scopes.');
assertHrScopeSame('iii', $hrClause['types'], 'HR scope clause should bind integer params.');
assertHrScopeSame([2, 7, 8], $hrClause['params'], 'HR scope params should be company IDs then branch IDs.');

$noScopeClause = hrScopeBuildEmployeeWhereClause('hr', ['company_ids' => [], 'branch_ids' => []], 'e');
assertHrScopeSame(' AND 1=0 ', $noScopeClause['sql'], 'HR with no scopes should see no employee rows.');

echo "hr_scope_helpers_test passed\n";
