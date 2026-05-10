<?php

function saveUploadedFile(array $file, string $targetDir, string $publicPrefix, array $allowedTypes, int $maxBytes, string $namePrefix): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new Exception('File upload failed');
    }

    if (($file['size'] ?? 0) <= 0 || $file['size'] > $maxBytes) {
        throw new Exception('File size is not allowed');
    }

    $tmpName = $file['tmp_name'] ?? '';
    if (!is_uploaded_file($tmpName)) {
        throw new Exception('Invalid uploaded file');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmpName);
    if (!isset($allowedTypes[$mime])) {
        throw new Exception('File type is not allowed');
    }

    $extension = $allowedTypes[$mime];
    $safeName = $namePrefix . '_' . bin2hex(random_bytes(12)) . '.' . $extension;

    if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true)) {
        throw new Exception('Upload directory is not available');
    }

    $targetPath = rtrim($targetDir, '/\\') . DIRECTORY_SEPARATOR . $safeName;
    if (!move_uploaded_file($tmpName, $targetPath)) {
        throw new Exception('Could not save uploaded file');
    }

    return rtrim($publicPrefix, '/\\') . '/' . $safeName;
}

function saveProfileImage(array $file): string
{
    return saveUploadedFile(
        $file,
        __DIR__ . '/../assets/uploads/profile_images',
        'assets/uploads/profile_images',
        [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ],
        2 * 1024 * 1024,
        'profile'
    );
}

function saveLeaveAttachment(array $file, int $requestId): string
{
    return saveUploadedFile(
        $file,
        __DIR__ . '/../assets/uploads/leaves',
        'assets/uploads/leaves',
        [
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ],
        5 * 1024 * 1024,
        'leave_' . $requestId
    );
}
?>
