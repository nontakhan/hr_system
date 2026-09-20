<?php
ini_set('display_errors', '0');
require_once __DIR__ . '/includes/attachment_helpers.php';
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
try {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $userId = (int)($_SESSION['user_id'] ?? 0);
    if (!$userId) throw new AttachmentAccessException('กรุณาเข้าสู่ระบบก่อนเปิดเอกสารแนบ', 403);
    require_once __DIR__ . '/includes/db_connect.php';
    $kind = is_string($_GET['type'] ?? null) ? $_GET['type'] : '';
    $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
    $attachmentId = filter_var($_GET['file'] ?? null, FILTER_VALIDATE_INT) ?: 0;
    if (isset($_GET['file']) && ($kind !== 'leave' || $attachmentId <= 0)) throw new AttachmentAccessException('ไม่พบเอกสารแนบนี้', 404);
    $attachment = attachmentResolveForUser($mysqli, $userId, $kind, $id, $attachmentId);
    session_write_close();
    header('Content-Type: ' . $attachment['mime']);
    header('Content-Disposition: inline; filename="' . $attachment['filename'] . '"');
    header("Content-Security-Policy: sandbox");
    readfile($attachment['path']);
} catch (Throwable $error) {
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    http_response_code($error instanceof AttachmentAccessException ? $error->getCode() : 500);
    header('Content-Type: text/html; charset=UTF-8');
    $message = $error instanceof AttachmentAccessException ? $error->getMessage() : 'เปิดเอกสารไม่สำเร็จ กรุณาลองใหม่';
    echo '<!doctype html><html lang="th"><meta name="viewport" content="width=device-width, initial-scale=1"><title>เอกสารแนบ</title><main><h1>เปิดเอกสารแนบไม่ได้</h1><p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p><a href="dashboard.php">กลับหน้าหลัก / เข้าสู่ระบบ</a></main></html>';
}