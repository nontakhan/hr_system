<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

function sendTrainingRequestJson($payload) {
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit();
}

function sendTrainingRequestError($message) {
    sendTrainingRequestJson(['status' => 'error', 'message' => $message]);
}

try {
    if (session_status() == PHP_SESSION_NONE) session_start();
    require_once '../includes/db_connect.php';
    require_once '../includes/hr_scope_helpers.php';
    require_once '../includes/training_request_helpers.php';
    require_once '../includes/upload_security.php';

    if (!isset($_SESSION['user_id'])) {
        sendTrainingRequestError('กรุณาเข้าสู่ระบบก่อนใช้งาน');
    }

    trainingRequestEnsureTable($mysqli);

    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? ($_POST['action'] ?? '');
    $myEmployeeId = (int)($_SESSION['employee_id'] ?? 0);
    $myRole = $_SESSION['role'] ?? 'employee';

    if ($method === 'GET') {
        if ($action === 'my_requests') {
            sendTrainingRequestJson(['status' => 'success', 'data' => fetchMyTrainingRequests($mysqli, $myEmployeeId)]);
        }

        if ($action === 'pending' || $action === 'history') {
            if (!in_array($myRole, ['manager', 'hr', 'admin'], true)) {
                sendTrainingRequestError('Access Denied');
            }

            $scopes = hrScopeCurrentSessionScopes();
            $sql = trainingRequestApprovalQuery($action, $myRole, $scopes);
            $stmt = $mysqli->prepare($sql);
            if ($myRole === 'hr') {
                $scopeClause = hrScopeBuildEmployeeWhereClause($myRole, $scopes, 'e');
                hrScopeBindParams($stmt, $scopeClause['types'], $scopeClause['params']);
            } elseif ($myRole !== 'admin') {
                $stmt->bind_param('i', $myEmployeeId);
            }
            $stmt->execute();
            sendTrainingRequestJson(['status' => 'success', 'data' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
        }

        sendTrainingRequestError('Invalid Action');
    }

    if ($method === 'POST') {
        if ($action === 'create') {
            createTrainingRequest($mysqli, $myEmployeeId);
        }

        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $postAction = $input['action'] ?? $action;
        if ($postAction === 'approve' || $postAction === 'reject') {
            processTrainingRequestApproval($mysqli, $input, $postAction, $myRole, $myEmployeeId);
        }

        sendTrainingRequestError('Invalid Action');
    }

    sendTrainingRequestError('Method Not Allowed');
} catch (Throwable $e) {
    error_log($e->getMessage());
    sendTrainingRequestError($e instanceof InvalidArgumentException ? $e->getMessage() : 'System Error');
}

function fetchMyTrainingRequests(mysqli $mysqli, int $employeeId): array
{
    $stmt = $mysqli->prepare("SELECT tr.*, tr.created_via, tr.created_by_role, tr.proxy_note,
                                     CONCAT_WS(' ', ae.first_name_th, ae.last_name_th) AS approver_name,
                                     CONCAT_WS(' ', pce.first_name_th, pce.last_name_th) AS proxy_creator_name
                              FROM training_requests tr
                              LEFT JOIN employees ae ON tr.approver_id = ae.id
                              LEFT JOIN employees pce ON tr.created_by_employee_id = pce.id
                              WHERE tr.employee_id = ?
                              ORDER BY tr.created_at DESC
                              LIMIT 100");
    $stmt->bind_param('i', $employeeId);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function createTrainingRequest(mysqli $mysqli, int $employeeId): void
{
    if ($employeeId <= 0) {
        sendTrainingRequestError('ไม่พบข้อมูลพนักงานของผู้ใช้งาน');
    }

    $courseName = trainingRequestTrim((string)($_POST['course_name'] ?? ''), 255);
    $startDate = trainingRequestNormalizeDate((string)($_POST['start_date'] ?? ''), 'กรุณาระบุวันที่เริ่มอบรม');
    $endDate = trainingRequestNormalizeDate((string)($_POST['end_date'] ?? ''), 'กรุณาระบุวันที่สิ้นสุดอบรม');
    $location = trainingRequestTrim((string)($_POST['location'] ?? ''), 255);
    $objective = trim((string)($_POST['objective'] ?? ''));

    if ($courseName === '') {
        sendTrainingRequestError('กรุณาระบุชื่อหลักสูตร');
    }
    if ($objective === '') {
        sendTrainingRequestError('กรุณาระบุวัตถุประสงค์การอบรม');
    }
    if ($endDate < $startDate) {
        sendTrainingRequestError('วันที่สิ้นสุดต้องไม่ก่อนวันที่เริ่มอบรม');
    }
    $attachmentPath = '';
    if (isset($_FILES['attachment']) && ($_FILES['attachment']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $attachmentPath = saveEmployeeTrainingAttachment($_FILES['attachment'], $employeeId);
    }

    $stmt = $mysqli->prepare("INSERT INTO training_requests
        (employee_id, course_name, start_date, end_date, location, objective, attachment_path, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'pending_manager')");
    $stmt->bind_param('issssss', $employeeId, $courseName, $startDate, $endDate, $location, $objective, $attachmentPath);
    if (!$stmt->execute()) {
        throw new RuntimeException($stmt->error ?: 'Cannot save training request');
    }

    sendTrainingRequestJson(['status' => 'success', 'message' => 'ส่งคำขออบรมเรียบร้อยแล้ว รอหัวหน้าอนุมัติ']);
}

function processTrainingRequestApproval(mysqli $mysqli, array $input, string $action, string $role, int $employeeId): void
{
    if (!in_array($role, ['manager', 'hr', 'admin'], true)) {
        sendTrainingRequestError('Access Denied');
    }

    $requestId = (int)($input['request_id'] ?? 0);
    $reason = trim((string)($input['reason'] ?? ''));
    if ($requestId <= 0) {
        sendTrainingRequestError('Invalid request ID');
    }
    if ($action === 'reject' && $reason === '') {
        sendTrainingRequestError('กรุณาระบุเหตุผลที่ไม่อนุมัติ');
    }

    $request = trainingRequestFetchApprovableRequest($mysqli, $requestId, $role, $employeeId, hrScopeCurrentSessionScopes());
    if (!$request) {
        sendTrainingRequestError('Access Denied');
    }

    $currentStatus = $request['status'] === 'pending' ? 'pending_manager' : $request['status'];
    $now = date('Y-m-d H:i:s');
    $mysqli->begin_transaction();

    try {
        if ($currentStatus === 'pending_manager') {
            if (!($role === 'admin' || (int)$request['supervisor_id'] === $employeeId)) {
                throw new InvalidArgumentException('Access Denied');
            }
            if ($action === 'approve') {
                $stmt = $mysqli->prepare("UPDATE training_requests
                                          SET status = 'pending_hr',
                                              manager_approver_id = ?,
                                              manager_approval_date = ?
                                          WHERE id = ? AND status IN ('pending','pending_manager')");
                $stmt->bind_param('isi', $employeeId, $now, $requestId);
            } else {
                $stmt = $mysqli->prepare("UPDATE training_requests
                                          SET status = 'rejected',
                                              manager_approver_id = ?,
                                              manager_approval_date = ?,
                                              approver_id = ?,
                                              approval_date = ?,
                                              rejection_reason = ?
                                          WHERE id = ? AND status IN ('pending','pending_manager')");
                $stmt->bind_param('isissi', $employeeId, $now, $employeeId, $now, $reason, $requestId);
            }
        } elseif ($currentStatus === 'pending_hr') {
            if (!in_array($role, ['admin', 'hr'], true)) {
                throw new InvalidArgumentException('Access Denied');
            }
            $newStatus = $action === 'approve' ? 'approved' : 'rejected';
            $rejectReason = $action === 'approve' ? null : $reason;
            $trainingRecordId = null;
            if ($action === 'approve') {
                $trainingRecordId = trainingRequestCreateHistoryRecord($mysqli, $request, $employeeId);
            }
            $stmt = $mysqli->prepare("UPDATE training_requests
                                      SET status = ?,
                                          hr_approver_id = ?,
                                          hr_approval_date = ?,
                                          approver_id = ?,
                                          approval_date = ?,
                                          rejection_reason = ?,
                                          training_record_id = ?
                                      WHERE id = ? AND status = 'pending_hr'");
            $stmt->bind_param('sisissii', $newStatus, $employeeId, $now, $employeeId, $now, $rejectReason, $trainingRecordId, $requestId);
        } else {
            throw new InvalidArgumentException('Request was already processed');
        }

        if (!$stmt->execute() || $stmt->affected_rows !== 1) {
            throw new RuntimeException($stmt->error ?: 'Request was already processed');
        }

        $mysqli->commit();
        sendTrainingRequestJson(['status' => 'success', 'message' => 'บันทึกผลการพิจารณาเรียบร้อย']);
    } catch (Throwable $e) {
        $mysqli->rollback();
        throw $e;
    }
}
?>
