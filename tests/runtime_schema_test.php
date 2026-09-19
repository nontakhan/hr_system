<?php
require_once __DIR__ . '/../includes/leave_helpers.php';
require_once __DIR__ . '/../includes/day_swap_helpers.php';
require_once __DIR__ . '/../includes/training_request_helpers.php';
require_once __DIR__ . '/../includes/hr_scope_helpers.php';
require_once __DIR__ . '/../includes/employee_shift_assignment_helpers.php';
require_once __DIR__ . '/../includes/employee_warning_helpers.php';
class RuntimeNoSql extends mysqli {
    public function __construct() {}
    public function query(string $query, int $result_mode = MYSQLI_STORE_RESULT): mysqli_result|bool {
        throw new RuntimeException('Unexpected runtime SQL: ' . strtok($query, "
"));
    }
}
$db = new RuntimeNoSql();
$failed = [];
foreach (['leaveEnsureHourlyRequestTypes','leaveEnsureLeaveTypeCalculationColumns','leaveEnsureSettingsTable','leaveEnsurePoliciesTable','leaveEnsureRequestPartColumns','leaveEnsureTwoStepApprovalColumns','daySwapEnsureTable','trainingRequestEnsureTable','trainingRequestEnsureActivityTypesTable','trainingRequestEnsureActivityColumns','hrScopeEnsureTable','employeeShiftAssignmentsEnsureTable','employeeShiftAssignmentsBackfillCurrentDefaults','employeeWarningEnsureTables','employeeWarningEnsureSourceColumns'] as $function) {
    try { $function($db); } catch (Throwable $e) { $failed[] = $function . ': ' . $e->getMessage(); }
}
try { proxyRequestEnsureAuditColumns($db, 'leave_requests'); } catch (Throwable $e) { $failed[] = $e->getMessage(); }
if ($failed) { fwrite(STDERR, implode("
", $failed) . "
"); exit(1); }
echo "PASS runtime schema helpers issue no SQL
";
