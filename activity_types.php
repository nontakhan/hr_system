<?php
require_once 'includes/auth_check.php';

if (!in_array($_SESSION['role'] ?? '', ['admin', 'hr'], true)) {
    header('Location: dashboard.php');
    exit();
}

$page_title = 'จัดการประเภทกิจกรรม';
require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 text-gray-800">จัดการประเภทกิจกรรม</h1>
        <p class="text-muted small mb-0">กำหนดกิจกรรมที่พนักงานสามารถส่งคำขอได้ เช่น ไปอบรม สัมมนา งานบุญ</p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#activityTypeModal" data-action="create">
        <i class="fas fa-plus"></i> เพิ่มประเภทกิจกรรม
    </button>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle" id="activityTypesTable">
                <thead class="table-light">
                    <tr>
                        <th style="width: 70px;">ID</th>
                        <th>ประเภทกิจกรรม</th>
                        <th>คำอธิบาย</th>
                        <th>สถานะ</th>
                        <th style="width: 150px;">จัดการ</th>
                    </tr>
                </thead>
                <tbody id="activityTypesTableBody">
                    <tr><td colspan="5" class="text-center text-muted py-4">กำลังโหลดข้อมูล...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="activityTypeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="activityTypeModalTitle">เพิ่มประเภทกิจกรรม</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="activityTypeForm">
                <div class="modal-body">
                    <input type="hidden" name="id" id="activityTypeId">
                    <div class="mb-3">
                        <label class="form-label">ชื่อประเภทกิจกรรม <span class="text-danger">*</span></label>
                        <input type="text" name="type_name" class="form-control" maxlength="255" placeholder="เช่น ไปอบรม" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">คำอธิบาย</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_active" id="activityTypeIsActive" value="1" checked>
                        <label class="form-check-label" for="activityTypeIsActive">เปิดใช้งานในหน้าคำขอ</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> บันทึก
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="assets/js/activity_types.js"></script>
<?php require_once 'includes/footer.php'; ?>
