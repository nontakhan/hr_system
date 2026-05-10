<?php
/*
 * หน้าประวัติการลาของฉัน (My Leaves)
 */
require_once 'includes/auth_check.php';
require_once 'includes/db_connect.php';

$page_title = "ประวัติการลาของฉัน";
require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 text-gray-800">ประวัติการลาของฉัน</h1>
        <p class="text-muted small">ตรวจสอบสถานะและประวัติการลาทั้งหมด</p>
    </div>
    <a href="leave_request.php" class="btn btn-primary">
        <i class="fas fa-plus"></i> ยื่นใบลาใหม่
    </a>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle" id="myLeavesTable">
                <thead class="table-light">
                    <tr>
                        <th>วันที่ยื่น</th>
                        <th>ประเภท</th>
                        <th>ช่วงเวลาที่ลา</th>
                        <th>จำนวนวัน</th>
                        <th>เหตุผล</th>
                        <th>สถานะ</th>
                        <th>จัดการ</th>
                    </tr>
                </thead>
                <tbody id="myLeavesTableBody">
                    <tr><td colspan="7" class="text-center text-muted py-4">กำลังโหลดข้อมูล...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>