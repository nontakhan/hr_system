<?php
/*
 * หน้าจัดการกะการทำงาน (Work Shifts)
 * เฉพาะ Admin/HR
 */
require_once 'includes/auth_check.php';

if (!in_array($_SESSION['role'], ['admin', 'hr'])) {
    header("Location: dashboard.php");
    exit();
}

$page_title = "จัดการกะการทำงาน";
require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 text-gray-800">จัดการกะการทำงาน (Work Shifts)</h1>
        <p class="text-muted small">กำหนดช่วงเวลาเข้า-ออกงาน และกฎการมาสาย</p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#shiftModal" data-action="create">
        <i class="fas fa-plus"></i> เพิ่มกะใหม่
    </button>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle" id="shiftTable">
                <thead class="table-light">
                    <tr>
                        <th>ชื่อกะ</th>
                        <th>เวลาเข้างาน</th>
                        <th>เวลาเลิกงาน</th>
                        <th>สายได้ (นาที)</th>
                        <th>วันทำงาน</th>
                        <th style="width: 150px;">จัดการ</th>
                    </tr>
                </thead>
                <tbody id="shiftTableBody">
                    <tr><td colspan="6" class="text-center py-4 text-muted">กำลังโหลด...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="shiftModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalTitle">เพิ่มกะการทำงาน</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="shiftForm">
                <div class="modal-body">
                    <input type="hidden" name="id" id="shift_id">
                    
                    <div class="mb-3">
                        <label class="form-label">ชื่อกะ <span class="text-danger">*</span></label>
                        <input type="text" name="shift_name" class="form-control" placeholder="เช่น กะเช้า, เวลาปกติ" required>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label">เวลาเข้างาน <span class="text-danger">*</span></label>
                            <input type="time" name="start_time" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">เวลาเลิกงาน <span class="text-danger">*</span></label>
                            <input type="time" name="end_time" class="form-control" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">อนุโลมให้สายได้ (นาที)</label>
                        <input type="number" name="late_tolerance_mins" class="form-control" value="0" min="0">
                        <div class="form-text">เกินกว่านี้ถือว่า "มาสาย"</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label d-block">วันทำงานปกติ</label>
                        <div class="btn-group" role="group">
                            <input type="checkbox" class="btn-check day-check" id="day_Mon" value="Mon" checked>
                            <label class="btn btn-outline-secondary" for="day_Mon">จ</label>

                            <input type="checkbox" class="btn-check day-check" id="day_Tue" value="Tue" checked>
                            <label class="btn btn-outline-secondary" for="day_Tue">อ</label>

                            <input type="checkbox" class="btn-check day-check" id="day_Wed" value="Wed" checked>
                            <label class="btn btn-outline-secondary" for="day_Wed">พ</label>

                            <input type="checkbox" class="btn-check day-check" id="day_Thu" value="Thu" checked>
                            <label class="btn btn-outline-secondary" for="day_Thu">พฤ</label>

                            <input type="checkbox" class="btn-check day-check" id="day_Fri" value="Fri" checked>
                            <label class="btn btn-outline-secondary" for="day_Fri">ศ</label>

                            <input type="checkbox" class="btn-check day-check" id="day_Sat" value="Sat">
                            <label class="btn btn-outline-secondary" for="day_Sat">ส</label>

                            <input type="checkbox" class="btn-check day-check" id="day_Sun" value="Sun">
                            <label class="btn btn-outline-secondary" for="day_Sun">อา</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary">บันทึก</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>