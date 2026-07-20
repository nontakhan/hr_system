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
    require_once '../includes/request_cancellation_helpers.php';

    if (!isset($_SESSION['user_id'])) {
        sendTrainingRequestError('กรุณาเข้าสู่ระบบก่อนใช้งาน');
    }

    trainingRequestEnsureTable($mysqli);

    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? ($_POST['action'] ?? '');
    $myEmployeeId = (int)($_SESSION['employee_id'] ?? 0);
    $myRole = $_SESSION['role'] ?? 'employee';

    if ($method === 'GET') {
        if ($action === 'activity_types') {
            sendTrainingRequestJson(['status' => 'success', 'data' => trainingRequestFetchActiveActivityTypes($mysqli)]);
        }

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
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            foreach ($rows as &$row) {
                $row['can_reviewer_cancel'] = $action === 'history'
                    && in_array($myRole, ['hr', 'admin'], true)
                    && ($row['status'] ?? '') === 'approved';
            }
            unset($row);
            sendTrainingRequestJson(['status' => 'success', 'data' => $rows]);
        }

        sendTrainingRequestError('Invalid Action');
    }

    if ($method === 'POST') {
        if ($action === 'create') {
            createTrainingRequest($mysqli, $myEmployeeId);
        }

        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $postAction = $input['action'] ?? $action;
        if ($postAction === 'cancel') {
            cancelMyTrainingRequest($mysqli, $input, $myEmployeeId);
        }
        if ($postAction === 'reviewer_cancel') {
            reviewerCancelApprovedTrainingRequest($mysqli, $input, $myRole, $myEmployeeId);
        }
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
                                     at.type_name AS activity_type_name,
                                     CONCAT_WS(' ', ae.first_name_th, ae.last_name_th) AS approver_name,
                                     CONCAT_WS(' ', pce.first_name_th, pce.last_name_th) AS proxy_creator_name
                              FROM training_requests tr
                              LEFT JOIN activity_types at ON tr.activity_type_id = at.id
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

    $activityTypeId = (int)($_POST['activity_type_id'] ?? 0);
    $courseName = trainingRequestTrim((string)($_POST['course_name'] ?? ''), 255);
    $startDate = trainingRequestNormalizeDate((string)($_POST['start_date'] ?? ''), 'กรุณาระบุวันที่เริ่มกิจกรรม');
    $endDate = trainingRequestNormalizeDate((string)($_POST['end_date'] ?? ''), 'กรุณาระบุวันที่สิ้นสุดกิจกรรม');
    $startDayPart = trainingRequestNormalizeDayPart((string)($_POST['start_day_part'] ?? 'full'));
    $endDayPart = trainingRequestNormalizeDayPart((string)($_POST['end_day_part'] ?? 'full'));
    $location = trainingRequestTrim((string)($_POST['location'] ?? ''), 255);
    $objective = trim((string)($_POST['objective'] ?? ''));

    if (!trainingRequestActivityTypeExists($mysqli, $activityTypeId)) {
        sendTrainingRequestError('กรุณาเลือกประเภทกิจกรรม');
    }
    if ($courseName === '') {
        sendTrainingRequestError('กรุณาระบุชื่อกิจกรรม');
    }
    if ($objective === '') {
        sendTrainingRequestError('กรุณาระบุวัตถุประสงค์การทำกิจกรรม');
    }
    if ($endDate < $startDate) {
        sendTrainingRequestError('วันที่สิ้นสุดต้องไม่ก่อนวันที่เริ่มกิจกรรม');
    }
    $attachmentPath = '';
    if (isset($_FILES['attachment']) && ($_FILES['attachment']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $attachmentPath = saveEmployeeTrainingAttachment($_FILES['attachment'], $employeeId);
    }

    $stmt = $mysqli->prepare("INSERT INTO training_requests
        (employee_id, activity_type_id, course_name, start_date, end_date, start_day_part, end_day_part, location, objective, attachment_path, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending_manager')");
    $stmt->bind_param('iissssssss', $employeeId, $activityTypeId, $courseName, $startDate, $endDate, $startDayPart, $endDayPart, $location, $objective, $attachmentPath);
    if (!$stmt->execute()) {
        throw new RuntimeException($stmt->error ?: 'Cannot save training request');
    }

    sendTrainingRequestJson(['status' => 'success', 'message' => 'ส่งคำขอกิจกรรมเรียบร้อยแล้ว รอหัวหน้าอนุมัติ']);
}

function cancelMyTrainingRequest(mysqli $mysqli, array $input, int $employeeId): void {
    $requestId = (int)($input['request_id'] ?? 0);
    $reason = trim((string)($input['cancellation_reason'] ?? ''));
    if ($requestId <= 0 || $reason === '') sendTrainingRequestError('กรุณาระบุเหตุผลการยกเลิก');
    $stmt = $mysqli->prepare("SELECT id, status FROM training_requests WHERE id = ? AND employee_id = ?");
    $stmt->bind_param('ii', $requestId, $employeeId);
    $stmt->execute();
    $request = $stmt->get_result()->fetch_assoc();
    $newStatus = $request ? requestCancellationEmployeeTransition((string)$request['status']) : null;
    if ($newStatus === null) sendTrainingRequestError('ไม่สามารถยกเลิกรายการนี้ได้');
    $currentStatus = (string)$request['status'];
    $update = $mysqli->prepare("UPDATE training_requests SET status = ?, cancellation_reason = ? WHERE id = ? AND employee_id = ? AND status = ?");
    $update->bind_param('ssiis', $newStatus, $reason, $requestId, $employeeId, $currentStatus);
    if (!$update->execute() || $update->affected_rows !== 1) sendTrainingRequestError('สถานะรายการเปลี่ยนแปลงแล้ว กรุณาโหลดข้อมูลใหม่');
    sendTrainingRequestJson(['status' => 'success', 'message' => $newStatus === 'pending_cancel_hr' ? 'ส่งคำขอยกเลิกแล้ว รอ HR/Admin อนุมัติ' : 'ยกเลิกรายการเรียบร้อยแล้ว']);
}

function reviewerCancelApprovedTrainingRequest(mysqli $mysqli, array $input, string $role, int $employeeId): void
{
    if (!in_array($role, ['hr', 'admin'], true)) {
        sendTrainingRequestError('Access Denied');
    }
    $requestId = (int)($input['request_id'] ?? 0);
    $reason = trim((string)($input['cancellation_reason'] ?? ''));
    if ($requestId <= 0 || $reason === '') {
        sendTrainingRequestError('กรุณาระบุเหตุผลการยกเลิก');
    }

    $scopeClause = hrScopeBuildEmployeeWhereClause($role, hrScopeCurrentSessionScopes(), 'e');
    $sql = "SELECT tr.id, tr.status, tr.training_record_id
            FROM training_requests tr
            JOIN employees e ON tr.employee_id = e.id
            WHERE tr.id = ? AND tr.status = 'approved'";
    $types = 'i';
    $params = [$requestId];
    if ($role === 'hr') {
        $sql .= $scopeClause['sql'];
        $types .= $scopeClause['types'];
        $params = array_merge($params, $scopeClause['params']);
    }
    $stmt = $mysqli->prepare($sql);
    hrScopeBindParams($stmt, $types, $params);
    $stmt->execute();
    $result = $stmt->get_result();
    $request = $result->fetch_assoc();
    $result->free();
    $stmt->close();
    if (!$request || requestCancellationReviewerDirectTransition((string)$request['status'], $role) === null) {
        sendTrainingRequestError('Access Denied');
    }

    $userId = (int)($_SESSION['user_id'] ?? 0);
    $now = date('Y-m-d H:i:s');
    $mysqli->begin_transaction();
    try {
        $update = $mysqli->prepare("UPDATE training_requests
                                    SET status = 'cancelled', cancellation_reason = ?, cancelled_by_user_id = ?,
                                        cancelled_by_employee_id = ?, cancelled_by_role = ?, cancelled_at = ?
                                    WHERE id = ? AND status = 'approved'");
        $update->bind_param('siissi', $reason, $userId, $employeeId, $role, $now, $requestId);
        if (!$update->execute() || $update->affected_rows !== 1) {
            $mysqli->rollback();
            sendTrainingRequestError('สถานะรายการเปลี่ยนแปลงแล้ว กรุณาโหลดข้อมูลใหม่');
        }

        $trainingRecordId = (int)($request['training_record_id'] ?? 0);
        if ($trainingRecordId > 0) {
            $cancelledResult = 'ยกเลิก';
            $recordUpdate = $mysqli->prepare("UPDATE employee_training_records
                                              SET result_status = ?, updated_by = ?
                                              WHERE id = ?");
            $recordUpdate->bind_param('sii', $cancelledResult, $employeeId, $trainingRecordId);
            if (!$recordUpdate->execute()) {
                throw new RuntimeException($recordUpdate->error ?: 'Cannot update employee training record');
            }
        }
        $mysqli->commit();
    } catch (Throwable $e) {
        $mysqli->rollback();
        throw $e;
    }
    sendTrainingRequestJson(['status' => 'success', 'message' => 'ยกเลิกรายการเรียบร้อยแล้ว']);
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
        } elseif ($currentStatus === 'pending_cancel_hr') {
            $newStatus = requestCancellationReviewerTransition($currentStatus, $action, $role);
            if ($newStatus === null) throw new InvalidArgumentException('Access Denied');
            if ($action === 'reject' && $reason === '') throw new InvalidArgumentException('กรุณาระบุเหตุผลที่ไม่อนุมัติ');
            $rejectReason = $action === 'approve' ? null : $reason;
            $stmt = $mysqli->prepare("UPDATE training_requests SET status = ?, hr_approver_id = ?, hr_approval_date = ?, approver_id = ?, approval_date = ?, rejection_reason = ? WHERE id = ? AND status = 'pending_cancel_hr'");
            $stmt->bind_param('sisissi', $newStatus, $employeeId, $now, $employeeId, $now, $rejectReason, $requestId);
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
