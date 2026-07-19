<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

function sendEmployeeWarningJson(array $payload): void
{
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit();
}

function sendEmployeeWarningError(string $message): void
{
    sendEmployeeWarningJson(['status' => 'error', 'message' => $message]);
}

function employeeWarningRequireHr(string $role): void
{
    if (!in_array($role, ['admin', 'hr'], true)) {
        sendEmployeeWarningError('Access Denied');
    }
}

try {
    if (session_status() == PHP_SESSION_NONE) session_start();
    require_once '../includes/db_connect.php';
    require_once '../includes/hr_scope_helpers.php';
    require_once '../includes/employee_warning_helpers.php';

    if (!isset($_SESSION['user_id'])) {
        sendEmployeeWarningError('กรุณาเข้าสู่ระบบก่อนใช้งาน');
    }

    employeeWarningEnsureTables($mysqli);

    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? ($_POST['action'] ?? '');
    $role = $_SESSION['role'] ?? 'employee';
    $userId = (int)($_SESSION['user_id'] ?? 0);
    $employeeId = (int)($_SESSION['employee_id'] ?? 0);
    $scopes = function_exists('hrScopeCurrentSessionScopes') ? hrScopeCurrentSessionScopes() : [];

    if ($method === 'GET') {
        if ($action === 'my_monthly_warnings') {
            $month = employeeWarningNormalizeMonth($_GET['month'] ?? null);
            sendEmployeeWarningJson([
                'status' => 'success',
                'data' => employeeWarningFetchMyMonth($mysqli, (int)($_SESSION['employee_id'] ?? 0), $month),
            ]);
        }

        employeeWarningRequireHr($role);

        if ($action === 'monthly_summary') {
            $month = employeeWarningNormalizeMonth($_GET['month'] ?? null);
            sendEmployeeWarningJson([
                'status' => 'success',
                'data' => employeeWarningFetchMonthlySummary($mysqli, $month, $role, $scopes),
            ]);
        }

        if ($action === 'search_employee_warnings') {
            sendEmployeeWarningJson([
                'status' => 'success',
                'data' => employeeWarningSearchByName($mysqli, $_GET['q'] ?? '', $role, $scopes),
            ]);
        }

        if ($action === 'employee_month_details') {
            $month = employeeWarningNormalizeMonth($_GET['month'] ?? null);
            $targetEmployeeId = (int)($_GET['employee_id'] ?? 0);
            if ($targetEmployeeId <= 0) {
                sendEmployeeWarningError('พนักงานไม่ถูกต้อง');
            }
            sendEmployeeWarningJson([
                'status' => 'success',
                'data' => employeeWarningFetchEmployeeMonthDetails($mysqli, $targetEmployeeId, $month, $role, $scopes),
            ]);
        }

        if ($action === 'employee_warning_history') {
            $targetEmployeeId = (int)($_GET['employee_id'] ?? 0);
            sendEmployeeWarningJson([
                'status' => 'success',
                'data' => employeeWarningFetchEmployeeHistory($mysqli, $targetEmployeeId, $role, $scopes),
            ]);
        }

        if ($action === 'get_warning_types') {
            sendEmployeeWarningJson([
                'status' => 'success',
                'data' => employeeWarningFetchTypes($mysqli),
            ]);
        }

        if ($action === 'employees') {
            sendEmployeeWarningJson([
                'status' => 'success',
                'data' => employeeWarningFetchEmployees($mysqli, $role, $scopes),
            ]);
        }

        sendEmployeeWarningError('Invalid Action');
    }

    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $postAction = $input['action'] ?? $action;

    if ($method === 'POST') {
        employeeWarningRequireHr($role);

        if ($postAction === 'create_warning') {
            employeeWarningCreateRecord($mysqli, $input, $userId);
            sendEmployeeWarningJson(['status' => 'success', 'message' => 'บันทึกใบเตือนเรียบร้อยแล้ว']);
        }

        if ($postAction === 'update_warning') {
            employeeWarningUpdateRecord($mysqli, $input, $userId, $role, $scopes);
            sendEmployeeWarningJson(['status' => 'success', 'message' => 'แก้ไขใบเตือนเรียบร้อยแล้ว']);
        }

        if ($postAction === 'bulk_create') {
            sendEmployeeWarningJson([
                'status' => 'success',
                'data' => employeeWarningCreateBulk($mysqli, $input, $userId, $role, $scopes),
            ]);
        }

        if ($postAction === 'create_warning_type' || $postAction === 'update_warning_type') {
            $type = employeeWarningSaveType($mysqli, $input, $userId);
            sendEmployeeWarningJson([
                'status' => 'success',
                'message' => 'บันทึกรายการใบเตือนเรียบร้อยแล้ว',
                'data' => $type,
            ]);
        }

        sendEmployeeWarningError('Invalid Action');
    }

    if ($method === 'DELETE') {
        employeeWarningRequireHr($role);
        if (($input['action'] ?? '') === 'delete_warning') {
            employeeWarningDeleteRecord($mysqli, (int)($input['id'] ?? 0), $role, $scopes);
            sendEmployeeWarningJson(['status' => 'success', 'message' => 'ลบใบเตือนเรียบร้อยแล้ว']);
        }
        if (($input['action'] ?? '') === 'delete_warning_type') {
            employeeWarningDeleteType($mysqli, (int)($input['id'] ?? 0));
            sendEmployeeWarningJson(['status' => 'success', 'message' => 'ลบรายการใบเตือนเรียบร้อยแล้ว']);
        }
        sendEmployeeWarningError('Invalid Action');
    }

    sendEmployeeWarningError('Method Not Allowed');
} catch (Throwable $e) {
    error_log($e->getMessage());
    sendEmployeeWarningError($e instanceof InvalidArgumentException ? $e->getMessage() : 'System Error');
}
?>
