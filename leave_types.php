<?php
/*
 * หน้าจัดการประเภทการลา (Master Data)
 * เฉพาะ Admin และ HR เท่านั้น
 */
require_once 'includes/auth_check.php';

// เช็คสิทธิ์
if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'hr') {
    header("Location: dashboard.php");
    exit();
}

$page_title = "จัดการประเภทการลา";
require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 text-gray-800">จัดการประเภทการลา</h1>
        <p class="text-muted small">กำหนดประเภทและจำนวนวันลาต่อปี</p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#leaveTypeModal" data-action="create">
        <i class="fas fa-plus"></i> เพิ่มประเภทการลา
    </button>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle" id="leaveTypesTable">
                <thead class="table-light">
                    <tr>
                        <th style="width: 50px;">ID</th>
                        <th>ชื่อประเภทการลา</th>
                        <th>จำนวนวัน/ปี</th>
                        <th>เงื่อนไขเพิ่มเติม</th>
                        <th style="width: 150px;">จัดการ</th>
                    </tr>
                </thead>
                <tbody id="leaveTypesTableBody">
                    <tr><td colspan="5" class="text-center text-muted py-4">กำลังโหลดข้อมูล...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Form -->
<div class="modal fade" id="leaveTypeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalTitle">เพิ่มประเภทการลา</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="leaveTypeForm">
                <div class="modal-body">
                    <input type="hidden" name="id" id="type_id">
                    
                    <div class="mb-3">
                        <label class="form-label">ชื่อประเภทการลา <span class="text-danger">*</span></label>
                        <input type="text" name="type_name" class="form-control" placeholder="เช่น ลาพักร้อน" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">จำนวนวันที่ให้ลาได้ต่อปี (วัน) <span class="text-danger">*</span></label>
                        <input type="number" name="days_per_year" class="form-control" value="30" min="0" required>
                        <div class="form-text">ใส่ 0 หากไม่จำกัดจำนวนวัน</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">คำอธิบายเพิ่มเติม</label>
                        <textarea name="description" class="form-control" rows="2"></textarea>
                    </div>

                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="requires_file" id="requiresFile" value="1">
                        <label class="form-check-label" for="requiresFile">ต้องแนบไฟล์หลักฐาน (เช่น ใบรับรองแพทย์)</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary">บันทึกข้อมูล</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>