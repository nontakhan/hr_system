<?php
require_once __DIR__ . '/hr_scope_helpers.php';

class AttachmentAccessException extends RuntimeException {}

// Resolve metadata and fresh account authority before touching the filesystem.
function attachmentResolveForUser(mysqli $db, int $userId, string $kind, int $id, int $attachmentId = 0): array
{
    $queries = [
        'leave' => "SELECT e.id AS employee_id,e.company_id,e.branch_id,e.supervisor_id,la.file_path FROM leave_attachments la JOIN leave_requests r ON r.id=la.leave_request_id JOIN employees e ON e.id=r.employee_id WHERE r.id=? ORDER BY la.id DESC LIMIT 1",
        'training_request' => "SELECT e.id AS employee_id,e.company_id,e.branch_id,e.supervisor_id,r.attachment_path AS file_path FROM training_requests r JOIN employees e ON e.id=r.employee_id WHERE r.id=?",
        'training_record' => "SELECT e.id AS employee_id,e.company_id,e.branch_id,e.supervisor_id,r.attachment_path AS file_path FROM employee_training_records r JOIN employees e ON e.id=r.employee_id WHERE r.id=?",
    ];
    if ($id <= 0 || !isset($queries[$kind])) throw new AttachmentAccessException('ไม่พบเอกสารแนบนี้', 404);
    $stmt = $db->prepare('SELECT role,employee_id FROM users WHERE id=?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $actor = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$actor || !in_array($actor['role'], ['employee','manager','hr','admin'], true)) throw new AttachmentAccessException('กรุณาเข้าสู่ระบบด้วยบัญชีที่มีสิทธิ์', 403);
    $query = $queries[$kind];
    if ($kind === 'leave' && $attachmentId > 0) $query = str_replace('WHERE r.id=?', 'WHERE r.id=? AND la.id=?', $query);
    $stmt = $db->prepare($query);
    if ($kind === 'leave' && $attachmentId > 0) $stmt->bind_param('ii', $id, $attachmentId);
    else $stmt->bind_param('i', $id);
    $stmt->execute();
    $record = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$record || !$record['file_path']) throw new AttachmentAccessException('ไม่พบเอกสารแนบนี้', 404);
    $allowed = $actor['role'] === 'admin';
    if ($actor['role'] === 'hr') {
        $scopes = hrScopeFetchForUser($db, $userId);
        $allowed = in_array((int)$record['company_id'], $scopes['company_ids'], true) || in_array((int)$record['branch_id'], $scopes['branch_ids'], true);
    }
    if ($kind !== 'training_record') {
        $employeeId = (int)$actor['employee_id'];
        $allowed = $allowed || ($employeeId > 0 && (int)$record['employee_id'] === $employeeId)
            || ($actor['role'] === 'manager' && $employeeId > 0 && (int)$record['supervisor_id'] === $employeeId);
    }
    if (!$allowed) throw new AttachmentAccessException('คุณไม่มีสิทธิ์อ่านเอกสารแนบนี้', 403);
    $folder = $kind === 'leave' ? 'leaves' : 'employee_training';
    $prefix = 'assets/uploads/' . $folder . '/';
    $stored = str_replace('\\', '/', $record['file_path']);
    $filename = substr($stored, strlen($prefix));
    if (!str_starts_with($stored, $prefix) || $filename === '' || str_contains($filename, '/') || str_contains($filename, "\0")) throw new AttachmentAccessException('ไม่พบเอกสารแนบนี้', 404);
    $root = realpath(__DIR__ . '/../' . $prefix);
    $path = realpath(__DIR__ . '/../' . $stored);
    if (!$root || !$path || !is_file($path) || !str_starts_with($path, $root . DIRECTORY_SEPARATOR)) throw new AttachmentAccessException('ไม่พบเอกสารแนบนี้', 404);
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
    $extensions = ['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/pjpeg'=>'jpg','image/png'=>'png','image/x-png'=>'png','image/webp'=>'webp'];
    if (!isset($extensions[$mime])) throw new AttachmentAccessException('ไม่รองรับรูปแบบเอกสารนี้ กรุณาติดต่อฝ่ายบุคคล', 415);
    return ['path'=>$path,'mime'=>$mime,'filename'=>'attachment-' . $id . '.' . $extensions[$mime]];
}