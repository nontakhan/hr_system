<?php
/*
 * หน้าสำหรับจัดการข้อมูล Master (ตั้งค่าระบบ)
 * (Updated: เพิ่ม ID ให้ตารางเพื่อรองรับ DataTables)
 */
require_once 'includes/auth_check.php';

if ($_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

$page_title = "ตั้งค่าระบบ (Master Data)";
require_once 'includes/header.php';
?>

<ul class="nav nav-tabs" id="masterDataTabs" role="tablist">
    <li class="nav-item">
        <button class="nav-link active" id="companies-tab" data-bs-toggle="tab" data-bs-target="#companies-pane">จัดการบริษัท</button>
    </li>
    <li class="nav-item">
        <button class="nav-link" id="branches-tab" data-bs-toggle="tab" data-bs-target="#branches-pane">จัดการสาขา</button>
    </li>
    <li class="nav-item">
        <button class="nav-link" id="departments-tab" data-bs-toggle="tab" data-bs-target="#departments-pane">จัดการแผนก</button>
    </li>
    <li class="nav-item">
        <button class="nav-link" id="positions-tab" data-bs-toggle="tab" data-bs-target="#positions-pane">จัดการตำแหน่ง</button>
    </li>
</ul>

<div class="tab-content card border-top-0 rounded-bottom shadow-sm" id="masterDataTabsContent">

    <!-- Companies -->
    <div class="tab-pane fade show active" id="companies-pane">
        <div class="card-body">
            <div class="d-flex justify-content-between mb-3">
                <h5 class="card-title">รายชื่อบริษัท</h5>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#masterDataModal" data-type="company"><i class="fas fa-plus"></i> เพิ่มบริษัท</button>
            </div>
            <!-- (เพิ่ม ID) -->
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle" id="companyTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>ชื่อบริษัท</th>
                            <th>ที่อยู่</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="companyTableBody"></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Branches -->
    <div class="tab-pane fade" id="branches-pane">
        <div class="card-body">
            <div class="d-flex justify-content-between mb-3">
                <h5 class="card-title">รายชื่อสาขา</h5>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#masterDataModal" data-type="branch"><i class="fas fa-plus"></i> เพิ่มสาขา</button>
            </div>
            <!-- (เพิ่ม ID) -->
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle" id="branchTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>ชื่อสาขา</th>
                            <th>สังกัดบริษัท</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="branchTableBody"></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Departments -->
    <div class="tab-pane fade" id="departments-pane">
        <div class="card-body">
            <div class="d-flex justify-content-between mb-3">
                <h5 class="card-title">รายชื่อแผนก</h5>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#masterDataModal" data-type="department"><i class="fas fa-plus"></i> เพิ่มแผนก</button>
            </div>
            <!-- (เพิ่ม ID) -->
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle" id="departmentTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>ชื่อแผนก (ไทย)</th>
                            <th>ชื่อแผนก (อังกฤษ)</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="departmentTableBody"></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Positions -->
    <div class="tab-pane fade" id="positions-pane">
        <div class="card-body">
            <div class="d-flex justify-content-between mb-3">
                <h5 class="card-title">รายชื่อตำแหน่ง</h5>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#masterDataModal" data-type="position"><i class="fas fa-plus"></i> เพิ่มตำแหน่ง</button>
            </div>
            <!-- (เพิ่ม ID) -->
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle" id="positionTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>ชื่อตำแหน่ง (ไทย)</th>
                            <th>ชื่อตำแหน่ง (อังกฤษ)</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="positionTableBody"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="masterDataModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="masterDataForm">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="modalTitle">ฟอร์มข้อมูล</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="formAction" name="action">
                    <input type="hidden" id="formType" name="type">
                    <input type="hidden" id="formEditId" name="id">
                    <div id="modalFormContent"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                    <button type="submit" class="btn btn-primary">บันทึก</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>