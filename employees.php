<?php
/*
 * หน้าหลักสำหรับจัดการพนักงาน (แสดงรายการ)
 * แก้ไข: เพิ่ม Dropdown กรองสาขา
 */
require_once 'includes/auth_check.php';
require_once 'includes/db_connect.php'; // (ต้องใช้ DB เพื่อดึงสาขา)

$page_title = "จัดการข้อมูลพนักงาน";
require_once 'includes/header.php';

// --- (NEW) ดึงข้อมูลสาขาสำหรับ Filter ---
$branches = [];
try {
    $sql_branch = "SELECT id, branch_name_th FROM branches";
    
    // ถ้าเป็น HR ให้เห็นแค่สาขาของบริษัทตัวเอง
    if ($_SESSION['role'] === 'hr') {
        $company_id = $_SESSION['company_id'] ?? 0;
        $sql_branch .= " WHERE company_id = $company_id";
    }
    
    $sql_branch .= " ORDER BY branch_name_th";
    $branches = $mysqli->query($sql_branch)->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) { /* Ignore error */ }
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0"><?php echo $page_title; ?></h1>
    <a href="employee_add.php" class="btn btn-primary">
        <i class="fas fa-user-plus"></i> เพิ่มพนักงานใหม่
    </a>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white py-3">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h5 class="card-title mb-0">รายชื่อพนักงานทั้งหมด</h5>
            </div>
            <!-- (NEW) Filter Section -->
            <div class="col-md-6">
                <div class="d-flex justify-content-md-end align-items-center gap-2">
                    <label for="filterBranch" class="form-label mb-0 text-nowrap">กรองตามสาขา:</label>
                    <select id="filterBranch" class="form-select form-select-sm" style="max-width: 200px;">
                        <option value="">-- ทุกสาขา --</option>
                        <?php foreach ($branches as $b): ?>
                            <option value="<?php echo $b['id']; ?>"><?php echo $b['branch_name_th']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle" id="employeeTable">
                <thead class="table-light">
                    <tr>
                        <th>เลขบัตรประชาชน</th>
                        <th>ชื่อ - นามสกุล</th>
                        <th>ตำแหน่ง</th>
                        <th>แผนก</th>
                        <th>สังกัด (บ./สาขา)</th>
                        <th>สถานะ</th>
                        <th>จัดการ</th>
                    </tr>
                </thead>
                <tbody id="employeeTableBody">
                    <tr><td colspan="7" class="text-center text-muted py-3">... กำลังโหลดข้อมูล ...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>