# Employee Training History Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add employee training history to `employee_view.php` with HR/admin create/edit/delete, optional one-file attachments, and a visible history table.

**Architecture:** Follow the existing employee transfer-history pattern. Keep the schema/bootstrap logic in a focused helper, route list/save/delete through `api/employee_api.php`, render the profile-page UI in `employee_view.php`, and add browser behavior to `assets/js/employee.js`.

**Tech Stack:** PHP 8-compatible procedural code, MySQLi, Bootstrap modal/table UI, SweetAlert, vanilla JavaScript with `fetch` and `FormData`.

---

## File Structure

- Create `includes/employee_training_helpers.php`: owns table creation and any schema safeguards for `employee_training_records`.
- Modify `includes/upload_security.php`: add `saveEmployeeTrainingAttachment()` using the existing secure upload helper.
- Modify `api/employee_api.php`: add POST actions `save_training` and `delete_training`, GET action `training_history`, and helper functions for validation/persistence.
- Modify `employee_view.php`: include the training helper, add the top action button, history card, and modal.
- Modify `assets/js/employee.js`: add training form setup, list loading, edit prefill, delete, and attachment display.
- Test with `C:\xampp\php\php.exe -l` and `node --check`.

### Task 1: Schema Helper

**Files:**
- Create: `includes/employee_training_helpers.php`
- Modify: `employee_view.php`
- Modify: `api/employee_api.php`

- [ ] **Step 1: Create the helper file**

Add this file:

```php
<?php

function ensureEmployeeTrainingRecordsTable(mysqli $mysqli): void
{
    $mysqli->query("CREATE TABLE IF NOT EXISTS employee_training_records (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        training_date DATE NOT NULL,
        course_name VARCHAR(255) NOT NULL,
        provider VARCHAR(255) NULL,
        training_type VARCHAR(100) NULL,
        result_status VARCHAR(100) NULL,
        certificate_expiry_date DATE NULL,
        attachment_path VARCHAR(255) NULL,
        notes TEXT NULL,
        created_by INT NULL,
        updated_by INT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_employee_training_records_employee (employee_id),
        INDEX idx_employee_training_records_date (training_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
?>
```

- [ ] **Step 2: Load the helper in both entrypoints**

In `employee_view.php`, after the DB include:

```php
require_once 'includes/employee_training_helpers.php';
ensureEmployeeTrainingRecordsTable($mysqli);
```

In `api/employee_api.php`, after existing `require_once` lines:

```php
require_once '../includes/employee_training_helpers.php';
ensureEmployeeTrainingRecordsTable($mysqli);
```

- [ ] **Step 3: Verify PHP syntax**

Run:

```powershell
C:\xampp\php\php.exe -l includes\employee_training_helpers.php
C:\xampp\php\php.exe -l employee_view.php
C:\xampp\php\php.exe -l api\employee_api.php
```

Expected: each command prints `No syntax errors detected`.

### Task 2: Secure Optional Attachment Upload

**Files:**
- Modify: `includes/upload_security.php`

- [ ] **Step 1: Add training attachment helper**

Append before the closing `?>`:

```php
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
```

- [ ] **Step 2: Verify PHP syntax**

Run:

```powershell
C:\xampp\php\php.exe -l includes\upload_security.php
```

Expected: `No syntax errors detected`.

### Task 3: Employee API Actions

**Files:**
- Modify: `api/employee_api.php`

- [ ] **Step 1: Add action routing**

In the POST branch, after `update_transfer_history`, add:

```php
elseif ($action === 'save_training') {
    echo json_encode(saveEmployeeTrainingRecord($mysqli, $_POST, $_FILES));
}
elseif ($action === 'delete_training') {
    echo json_encode(deleteEmployeeTrainingRecord($mysqli, $_POST));
}
```

In the GET branch, before `get_history`, add:

```php
if (isset($_GET['action']) && $_GET['action'] === 'training_history') {
    $emp_id = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;
    echo json_encode(getEmployeeTrainingHistory($mysqli, $emp_id));
}
elseif (isset($_GET['action']) && $_GET['action'] === 'get_history') {
```

Then adjust the existing `if`/`else` braces so only one response is emitted.

- [ ] **Step 2: Add validation helpers**

Add near the other helper functions:

```php
function normalizeTrainingDate($value, bool $required = false): ?string
{
    $text = trim((string)$value);
    if ($text === '') {
        if ($required) throw new InvalidArgumentException('กรุณาระบุวันที่อบรม');
        return null;
    }
    $dt = DateTime::createFromFormat('Y-m-d', $text);
    if (!$dt || $dt->format('Y-m-d') !== $text) {
        throw new InvalidArgumentException('รูปแบบวันที่ไม่ถูกต้อง');
    }
    return $text;
}

function trimTrainingText($value, int $maxLength): string
{
    $text = trim((string)$value);
    if (mb_strlen($text, 'UTF-8') > $maxLength) {
        return mb_substr($text, 0, $maxLength, 'UTF-8');
    }
    return $text;
}
```

- [ ] **Step 3: Add list function**

Add near `getTransferHistory()`:

```php
function getEmployeeTrainingHistory($mysqli, $emp_id) {
    try {
        if ($emp_id <= 0) throw new InvalidArgumentException('Invalid employee ID');

        $sql = "SELECT tr.*, u.username AS created_by_username
                FROM employee_training_records tr
                LEFT JOIN users u ON tr.created_by = u.id
                WHERE tr.employee_id = ?
                ORDER BY tr.training_date DESC, tr.id DESC";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param('i', $emp_id);
        $stmt->execute();
        return ['status' => 'success', 'data' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)];
    } catch (Throwable $e) {
        error_log($e->getMessage());
        return ['status' => 'error', 'message' => 'System Error'];
    }
}
```

- [ ] **Step 4: Add save function**

Add near `updateTransferHistory()`:

```php
function saveEmployeeTrainingRecord($mysqli, $data, $files) {
    $mysqli->begin_transaction();
    try {
        $training_id = (int)getVal($data, 'training_id', 0);
        $emp_id = (int)getVal($data, 'employee_id', 0);
        if ($emp_id <= 0) throw new InvalidArgumentException('Invalid employee ID');

        $course_name = trimTrainingText(getVal($data, 'course_name', ''), 255);
        if ($course_name === '') throw new InvalidArgumentException('กรุณาระบุชื่อหลักสูตร');

        $training_date = normalizeTrainingDate(getVal($data, 'training_date', ''), true);
        $provider = trimTrainingText(getVal($data, 'provider', ''), 255);
        $training_type = trimTrainingText(getVal($data, 'training_type', ''), 100);
        $result_status = trimTrainingText(getVal($data, 'result_status', ''), 100);
        $certificate_expiry_date = normalizeTrainingDate(getVal($data, 'certificate_expiry_date', ''), false);
        $notes = trim((string)getVal($data, 'notes', ''));
        $user_id = (int)($_SESSION['user_id'] ?? 0);

        $attachment_path = null;
        if (isset($files['attachment']) && ($files['attachment']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $attachment_path = saveEmployeeTrainingAttachment($files['attachment'], $emp_id);
        }

        if ($training_id > 0) {
            $check = $mysqli->prepare("SELECT attachment_path FROM employee_training_records WHERE id = ? AND employee_id = ?");
            $check->bind_param('ii', $training_id, $emp_id);
            $check->execute();
            $current = $check->get_result()->fetch_assoc();
            if (!$current) throw new InvalidArgumentException('ไม่พบประวัติอบรมที่ต้องการแก้ไข');
            if ($attachment_path === null) $attachment_path = $current['attachment_path'];

            $sql = "UPDATE employee_training_records
                    SET training_date = ?, course_name = ?, provider = ?, training_type = ?,
                        result_status = ?, certificate_expiry_date = ?, attachment_path = ?,
                        notes = ?, updated_by = ?
                    WHERE id = ? AND employee_id = ?";
            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param('ssssssssiii', $training_date, $course_name, $provider, $training_type, $result_status, $certificate_expiry_date, $attachment_path, $notes, $user_id, $training_id, $emp_id);
            $stmt->execute();
            $message = 'แก้ไขประวัติอบรมสำเร็จ';
        } else {
            $sql = "INSERT INTO employee_training_records
                    (employee_id, training_date, course_name, provider, training_type,
                     result_status, certificate_expiry_date, attachment_path, notes, created_by, updated_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param('issssssssii', $emp_id, $training_date, $course_name, $provider, $training_type, $result_status, $certificate_expiry_date, $attachment_path, $notes, $user_id, $user_id);
            $stmt->execute();
            $message = 'บันทึกประวัติอบรมสำเร็จ';
        }

        $mysqli->commit();
        return ['status' => 'success', 'message' => $message];
    } catch (Throwable $e) {
        $mysqli->rollback();
        if ($e instanceof InvalidArgumentException) return ['status' => 'error', 'message' => $e->getMessage()];
        error_log($e->getMessage());
        return ['status' => 'error', 'message' => 'System Error'];
    }
}
```

- [ ] **Step 5: Add delete function**

Add near the save function:

```php
function deleteEmployeeTrainingRecord($mysqli, $data) {
    try {
        $training_id = (int)getVal($data, 'training_id', 0);
        $emp_id = (int)getVal($data, 'employee_id', 0);
        if ($training_id <= 0 || $emp_id <= 0) throw new InvalidArgumentException('Invalid training record');

        $stmt = $mysqli->prepare("DELETE FROM employee_training_records WHERE id = ? AND employee_id = ?");
        $stmt->bind_param('ii', $training_id, $emp_id);
        $stmt->execute();
        if ($stmt->affected_rows < 1) throw new InvalidArgumentException('ไม่พบประวัติอบรมที่ต้องการลบ');

        return ['status' => 'success', 'message' => 'ลบประวัติอบรมสำเร็จ'];
    } catch (Throwable $e) {
        if ($e instanceof InvalidArgumentException) return ['status' => 'error', 'message' => $e->getMessage()];
        error_log($e->getMessage());
        return ['status' => 'error', 'message' => 'System Error'];
    }
}
```

- [ ] **Step 6: Verify PHP syntax**

Run:

```powershell
C:\xampp\php\php.exe -l api\employee_api.php
```

Expected: `No syntax errors detected`.

### Task 4: Employee Profile UI

**Files:**
- Modify: `employee_view.php`

- [ ] **Step 1: Add top action button**

Inside the existing `admin/hr` button block, add before the transfer button:

```php
<button class="btn btn-info text-white me-2" data-bs-toggle="modal" data-bs-target="#trainingModal">
    <i class="fas fa-graduation-cap"></i> บันทึกอบรม
</button>
```

- [ ] **Step 2: Add training history card**

Add near the transfer-history card:

```php
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h5 class="mb-0 text-primary"><i class="fas fa-graduation-cap me-2"></i> ประวัติการฝึกอบรม</h5>
        <div class="d-flex gap-2">
            <?php if($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'hr'): ?>
            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#trainingModal">
                <i class="fas fa-plus"></i> เพิ่ม
            </button>
            <?php endif; ?>
            <button class="btn btn-sm btn-outline-primary" onclick="loadTrainingHistory(<?php echo $emp['id']; ?>)">
                <i class="fas fa-sync-alt"></i>
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-striped mb-0" id="trainingHistoryTable" data-can-manage-training="<?php echo ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'hr') ? '1' : '0'; ?>">
                <thead class="table-light">
                    <tr>
                        <th>วันที่อบรม</th>
                        <th>หลักสูตร</th>
                        <th>ผู้จัด/สถาบัน</th>
                        <th>ประเภท</th>
                        <th>ผลลัพธ์</th>
                        <th>ใบรับรองหมดอายุ</th>
                        <th>เอกสาร</th>
                        <th>หมายเหตุ</th>
                        <th>จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td colspan="9" class="text-center text-muted py-4">กำลังโหลดข้อมูล...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
```

- [ ] **Step 3: Add training modal**

Add before the transfer modal:

```php
<div class="modal fade" id="trainingModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="trainingModalTitle"><i class="fas fa-graduation-cap"></i> บันทึกประวัติการฝึกอบรม</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="trainingForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="employee_id" value="<?php echo $emp['id']; ?>">
                    <input type="hidden" name="training_id" id="trainingId" value="">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">วันที่อบรม <span class="text-danger">*</span></label>
                            <input type="date" name="training_date" id="trainingDate" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">ชื่อหลักสูตร <span class="text-danger">*</span></label>
                            <input type="text" name="course_name" id="trainingCourseName" class="form-control" required maxlength="255">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">ผู้จัด/สถาบัน</label>
                            <input type="text" name="provider" id="trainingProvider" class="form-control" maxlength="255">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">ประเภทอบรม</label>
                            <input type="text" name="training_type" id="trainingType" class="form-control" maxlength="100" placeholder="เช่น ภายใน, ภายนอก, Online">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">ผลลัพธ์/สถานะ</label>
                            <input type="text" name="result_status" id="trainingResultStatus" class="form-control" maxlength="100" placeholder="เช่น ผ่าน, เข้าร่วม, ได้ใบรับรอง">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">ใบรับรองหมดอายุ</label>
                            <input type="date" name="certificate_expiry_date" id="trainingCertificateExpiryDate" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label">เอกสารแนบ/ใบประกาศ (ถ้ามี)</label>
                            <input type="file" name="attachment" id="trainingAttachment" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp">
                            <div class="form-text" id="trainingCurrentAttachment"></div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">หมายเหตุ</label>
                            <textarea name="notes" id="trainingNotes" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-info text-white" id="trainingSubmitBtn">บันทึกประวัติอบรม</button>
                </div>
            </form>
        </div>
    </div>
</div>
```

- [ ] **Step 4: Load history on page load**

Extend the existing `DOMContentLoaded` script:

```php
if(typeof loadTrainingHistory === 'function') {
    loadTrainingHistory(<?php echo $emp['id']; ?>);
}
```

- [ ] **Step 5: Verify PHP syntax**

Run:

```powershell
C:\xampp\php\php.exe -l employee_view.php
```

Expected: `No syntax errors detected`.

### Task 5: Training JavaScript

**Files:**
- Modify: `assets/js/employee.js`

- [ ] **Step 1: Initialize the form**

In `DOMContentLoaded`, after transfer form setup:

```javascript
const trainingForm = document.getElementById('trainingForm');
if (trainingForm) {
    setupTrainingHistoryForm(trainingForm);
}
```

- [ ] **Step 2: Add reset and setup functions**

Add after transfer form helpers:

```javascript
function setupTrainingHistoryForm(form) {
    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const formData = new FormData(form);
        formData.append('action', 'save_training');

        Swal.fire({
            title: 'กำลังบันทึก...',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        try {
            const response = await fetch('api/employee_api.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.status === 'success') {
                Swal.fire('สำเร็จ', result.message, 'success');
                bootstrap.Modal.getInstance(document.getElementById('trainingModal')).hide();
                loadTrainingHistory(Number.parseInt(formData.get('employee_id'), 10) || 0);
            } else {
                Swal.fire('บันทึกไม่สำเร็จ', result.message, 'error');
            }
        } catch (error) {
            Swal.fire('Error', error.message, 'error');
        }
    });

    document.getElementById('trainingModal')?.addEventListener('hidden.bs.modal', () => {
        resetTrainingForm(form);
    });
}

function resetTrainingForm(form) {
    form.reset();
    document.getElementById('trainingId').value = '';
    setThaiDateInputValue(document.getElementById('trainingDate'), new Date().toISOString().slice(0, 10));
    document.getElementById('trainingCurrentAttachment').innerHTML = '';
    document.getElementById('trainingModalTitle').innerHTML = '<i class="fas fa-graduation-cap"></i> บันทึกประวัติการฝึกอบรม';
    document.getElementById('trainingSubmitBtn').textContent = 'บันทึกประวัติอบรม';
}
```

- [ ] **Step 3: Add table loader**

Add:

```javascript
window.loadTrainingHistory = async function(empId) {
    const table = document.getElementById('trainingHistoryTable');
    const tbody = table?.querySelector('tbody');
    if (!tbody) return;

    const canManage = table.dataset.canManageTraining === '1';
    tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">กำลังโหลดข้อมูล...</td></tr>';

    try {
        const response = await fetch(`api/employee_api.php?action=training_history&employee_id=${encodeURIComponent(empId)}`);
        const result = await response.json();

        if (result.status !== 'success') {
            tbody.innerHTML = `<tr><td colspan="9" class="text-center text-danger py-4">${escapeHtml(result.message || 'โหลดข้อมูลไม่สำเร็จ')}</td></tr>`;
            return;
        }

        if (result.data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">ยังไม่มีประวัติการฝึกอบรม</td></tr>';
            return;
        }

        tbody.innerHTML = result.data.map(row => {
            const payload = escapeAttr(JSON.stringify(row));
            const attachment = row.attachment_path
                ? `<a href="${escapeAttr(safeUploadPath(row.attachment_path, '#'))}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">เปิดไฟล์</a>`
                : '-';
            const actions = canManage ? `
                <button type="button" class="btn btn-sm btn-warning me-1" data-training="${payload}" onclick="openTrainingHistoryEdit(this)">
                    <i class="fas fa-pencil-alt"></i>
                </button>
                <button type="button" class="btn btn-sm btn-danger" data-id="${escapeAttr(row.id)}" data-employee-id="${escapeAttr(row.employee_id)}" onclick="deleteTrainingHistory(this)">
                    <i class="fas fa-trash"></i>
                </button>
            ` : '-';

            return `
                <tr>
                    <td>${formatThaiDate(row.training_date)}</td>
                    <td>${escapeHtml(row.course_name || '-')}</td>
                    <td>${escapeHtml(row.provider || '-')}</td>
                    <td>${escapeHtml(row.training_type || '-')}</td>
                    <td>${escapeHtml(row.result_status || '-')}</td>
                    <td>${formatThaiDate(row.certificate_expiry_date)}</td>
                    <td>${attachment}</td>
                    <td>${escapeHtml(row.notes || '-')}</td>
                    <td>${actions}</td>
                </tr>
            `;
        }).join('');
    } catch (error) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center text-danger py-4">เกิดข้อผิดพลาดในการโหลดข้อมูล</td></tr>';
    }
}
```

- [ ] **Step 4: Add edit and delete handlers**

Add:

```javascript
window.openTrainingHistoryEdit = function(button) {
    const row = JSON.parse(button.dataset.training);

    document.getElementById('trainingId').value = row.id || '';
    setThaiDateInputValue(document.getElementById('trainingDate'), row.training_date || new Date().toISOString().slice(0, 10));
    document.getElementById('trainingCourseName').value = row.course_name || '';
    document.getElementById('trainingProvider').value = row.provider || '';
    document.getElementById('trainingType').value = row.training_type || '';
    document.getElementById('trainingResultStatus').value = row.result_status || '';
    setThaiDateInputValue(document.getElementById('trainingCertificateExpiryDate'), row.certificate_expiry_date || '');
    document.getElementById('trainingNotes').value = row.notes || '';
    document.getElementById('trainingAttachment').value = '';
    document.getElementById('trainingCurrentAttachment').innerHTML = row.attachment_path
        ? `ไฟล์ปัจจุบัน: <a href="${escapeAttr(safeUploadPath(row.attachment_path, '#'))}" target="_blank" rel="noopener">เปิดไฟล์</a>`
        : '';
    document.getElementById('trainingModalTitle').innerHTML = '<i class="fas fa-pencil-alt"></i> แก้ไขประวัติการฝึกอบรม';
    document.getElementById('trainingSubmitBtn').textContent = 'บันทึกการแก้ไข';

    new bootstrap.Modal(document.getElementById('trainingModal')).show();
}

window.deleteTrainingHistory = async function(button) {
    const trainingId = Number.parseInt(button.dataset.id, 10) || 0;
    const employeeId = Number.parseInt(button.dataset.employeeId, 10) || 0;
    const confirm = await Swal.fire({
        title: 'ลบประวัติอบรม?',
        text: 'รายการนี้จะถูกลบออกจากประวัติพนักงาน',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'ลบ',
        cancelButtonText: 'ยกเลิก',
        confirmButtonColor: '#dc3545'
    });
    if (!confirm.isConfirmed) return;

    const formData = new FormData();
    formData.append('action', 'delete_training');
    formData.append('training_id', trainingId);
    formData.append('employee_id', employeeId);

    try {
        const response = await fetch('api/employee_api.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        if (result.status === 'success') {
            Swal.fire('สำเร็จ', result.message, 'success');
            loadTrainingHistory(employeeId);
        } else {
            Swal.fire('ลบไม่สำเร็จ', result.message, 'error');
        }
    } catch (error) {
        Swal.fire('Error', error.message, 'error');
    }
}
```

- [ ] **Step 5: Verify JavaScript syntax**

Run:

```powershell
node --check assets\js\employee.js
```

Expected: no output and exit code `0`.

### Task 6: End-to-End Verification

**Files:**
- Verify only; no planned edits.

- [ ] **Step 1: Run syntax checks**

Run:

```powershell
C:\xampp\php\php.exe -l includes\employee_training_helpers.php
C:\xampp\php\php.exe -l includes\upload_security.php
C:\xampp\php\php.exe -l api\employee_api.php
C:\xampp\php\php.exe -l employee_view.php
node --check assets\js\employee.js
git diff --check
```

Expected: PHP commands report no syntax errors, `node --check` exits cleanly, and `git diff --check` prints no whitespace errors.

- [ ] **Step 2: Manual browser checks**

Open an employee profile as an HR/admin user and verify:

```text
1. The บันทึกอบรม button appears beside โยกย้าย/ปรับตำแหน่ง.
2. The ประวัติการฝึกอบรม card loads an empty state.
3. Saving a record with only วันที่อบรม and ชื่อหลักสูตร succeeds.
4. The new record appears in the table after save.
5. Editing the record prefills the modal and saves changes.
6. Saving a record with a PDF or image attachment succeeds.
7. The เปิดไฟล์ link appears only for rows with an attachment.
8. Deleting a record removes it from the table.
```

- [ ] **Step 3: Review final diff**

Run:

```powershell
git status --short
git diff --stat
git diff -- includes\employee_training_helpers.php includes\upload_security.php api\employee_api.php employee_view.php assets\js\employee.js
```

Expected: only task-owned implementation files plus any intentionally retained design/mockup files are changed.
