<?php

function saveUploadedFile(array $file, string $targetDir, string $publicPrefix, array $allowedTypes, int $maxBytes, string $namePrefix): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'ไฟล์มีขนาดใหญ่เกินกว่าที่เซิร์ฟเวอร์อนุญาต',
            UPLOAD_ERR_FORM_SIZE => 'ไฟล์มีขนาดใหญ่เกินกว่าที่ฟอร์มอนุญาต',
            UPLOAD_ERR_PARTIAL => 'อัปโหลดไฟล์ไม่ครบ กรุณาลองใหม่',
            UPLOAD_ERR_NO_FILE => 'กรุณาเลือกไฟล์',
            UPLOAD_ERR_NO_TMP_DIR => 'เซิร์ฟเวอร์ไม่มีโฟลเดอร์ชั่วคราวสำหรับอัปโหลด',
            UPLOAD_ERR_CANT_WRITE => 'เซิร์ฟเวอร์ไม่สามารถบันทึกไฟล์ได้',
            UPLOAD_ERR_EXTENSION => 'ไฟล์ถูกบล็อกโดยส่วนขยายของ PHP',
        ];
        throw new InvalidArgumentException($messages[$file['error'] ?? UPLOAD_ERR_NO_FILE] ?? 'อัปโหลดไฟล์ไม่สำเร็จ');
    }

    if (($file['size'] ?? 0) <= 0 || $file['size'] > $maxBytes) {
        throw new InvalidArgumentException('ไฟล์มีขนาดใหญ่เกินกำหนด');
    }

    $tmpName = $file['tmp_name'] ?? '';
    if (!is_uploaded_file($tmpName)) {
        throw new InvalidArgumentException('ไฟล์อัปโหลดไม่ถูกต้อง');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmpName);
    if (!isset($allowedTypes[$mime])) {
        throw new InvalidArgumentException('ชนิดไฟล์ไม่รองรับ');
    }

    $extension = $allowedTypes[$mime];
    $safeName = $namePrefix . '_' . bin2hex(random_bytes(12)) . '.' . $extension;

    if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true)) {
        throw new InvalidArgumentException('ไม่สามารถสร้างโฟลเดอร์อัปโหลดได้ กรุณาตรวจสอบสิทธิ์โฟลเดอร์');
    }

    if (!is_writable($targetDir)) {
        throw new InvalidArgumentException('โฟลเดอร์อัปโหลดไม่สามารถเขียนไฟล์ได้ กรุณาตรวจสอบสิทธิ์โฟลเดอร์');
    }

    $targetPath = rtrim($targetDir, '/\\') . DIRECTORY_SEPARATOR . $safeName;
    if (!move_uploaded_file($tmpName, $targetPath)) {
        throw new InvalidArgumentException('บันทึกไฟล์อัปโหลดไม่สำเร็จ กรุณาตรวจสอบสิทธิ์และพื้นที่จัดเก็บบนเซิร์ฟเวอร์');
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
            'image/pjpeg' => 'jpg',
            'image/png' => 'png',
            'image/x-png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ],
        5 * 1024 * 1024,
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

function saveEmployeeTrainingAttachment(array $file, int $employeeId): string
{
    return saveUploadedFile(
        $file,
        __DIR__ . '/../assets/uploads/employee_training',
        'assets/uploads/employee_training',
        [
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/pjpeg' => 'jpg',
            'image/png' => 'png',
            'image/x-png' => 'png',
            'image/webp' => 'webp',
        ],
        5 * 1024 * 1024,
        'training_' . $employeeId
    );
}
?>
