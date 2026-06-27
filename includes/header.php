<?php
/*
 * ไฟล์ Header (Layout ใหม่: Sidebar + Topbar)
 */
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/date_helpers.php';

// ฟังก์ชันช่วยเช็ค Active Menu
function isActive($page) {
    return basename($_SERVER['PHP_SELF']) == $page ? 'active' : '';
}

function renderSidebarApprovalBadge($count) {
    $count = (int)$count;
    if ($count <= 0) {
        return '';
    }
    $label = $count > 99 ? '99+' : (string)$count;
    return '<span class="badge rounded-pill bg-danger ms-auto order-2">' . htmlspecialchars($label) . '</span>';
}

$displayName = trim($_SESSION['full_name'] ?? '') ?: ($_SESSION['username'] ?? '');
$displayPosition = trim($_SESSION['position_name'] ?? '') ?: ucfirst($_SESSION['role'] ?? '');
$approvalBadgeCounts = ['leave' => 0, 'time_request' => 0, 'overtime' => 0, 'day_swap' => 0, 'training' => 0, 'total' => 0];

if (!empty($_SESSION['user_id']) && in_array($_SESSION['role'] ?? '', ['manager', 'hr', 'admin'], true)) {
    require_once __DIR__ . '/db_connect.php';
    require_once __DIR__ . '/leave_helpers.php';
    require_once __DIR__ . '/day_swap_helpers.php';
    require_once __DIR__ . '/training_request_helpers.php';
    require_once __DIR__ . '/hr_scope_helpers.php';
    require_once __DIR__ . '/approval_badge_helpers.php';

    $approvalBadgeCounts = approvalBadgeFetchCounts(
        $mysqli,
        $_SESSION['role'] ?? 'employee',
        (int)($_SESSION['employee_id'] ?? 0),
        hrScopeCurrentSessionScopes()
    );
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) : "HR System"; ?></title>

    <!-- CSS Links -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <?php if (!empty($use_select2)) : ?>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <?php endif; ?>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<?php if (isset($_SESSION['user_id'])) : ?>
<div class="d-flex" id="wrapper">
    
    <!-- ================= Sidebar ================= -->
    <div class="bg-white" id="sidebar-wrapper">
        <div class="sidebar-heading text-center py-4 primary-text fs-4 fw-bold text-uppercase border-bottom bg-primary-custom text-white">
            <i class="fas fa-hospital-user me-2"></i> HR System
        </div>
        
        <div class="list-group list-group-flush my-3" id="sidebarMenu">
            
            <!-- Dashboard -->
            <a href="dashboard.php" class="list-group-item list-group-item-action bg-transparent <?php echo isActive('dashboard.php'); ?>">
                <i class="fas fa-home me-2"></i> หน้าหลัก
            </a>

            <a href="holiday_calendar.php" class="list-group-item list-group-item-action bg-transparent <?php echo isActive('holiday_calendar.php'); ?>">
                <i class="fas fa-calendar-days me-2"></i> ปฏิทินวันหยุด
            </a>

            <!-- Leave System -->
            <a href="my_leaves.php" class="list-group-item list-group-item-action bg-transparent d-flex align-items-center <?php echo (isActive('my_leaves.php') || isActive('leave_request.php') || isActive('leave_approvals.php')) ? 'active' : ''; ?>">
                <?php echo renderSidebarApprovalBadge($approvalBadgeCounts['leave']); ?>
                <i class="fas fa-calendar-alt me-2"></i> ระบบการลา
            </a>
            <!-- Late / Early Time Request System -->
            <a href="late_early_history.php" class="list-group-item list-group-item-action bg-transparent d-flex align-items-center <?php echo (isActive('late_early_request.php') || isActive('late_early_history.php') || isActive('late_early_approvals.php')) ? 'active' : ''; ?>">
                <?php echo renderSidebarApprovalBadge($approvalBadgeCounts['time_request']); ?>
                <i class="fas fa-business-time me-2"></i> ขอมาสาย/ออกก่อนเวลา
            </a>

            <!-- Overtime Request System -->
            <a href="overtime_history.php" class="list-group-item list-group-item-action bg-transparent d-flex align-items-center <?php echo (isActive('overtime_request.php') || isActive('overtime_history.php') || isActive('overtime_approvals.php')) ? 'active' : ''; ?>">
                <?php echo renderSidebarApprovalBadge($approvalBadgeCounts['overtime']); ?>
                <i class="fas fa-business-time me-2"></i> OT หลังเลิกงาน
            </a>

            <!-- Day Swap System (Dropdown) -->
            <a href="#daySwapSubmenu" data-bs-toggle="collapse" aria-expanded="false" class="list-group-item list-group-item-action bg-transparent dropdown-toggle d-flex align-items-center">
                <?php echo renderSidebarApprovalBadge($approvalBadgeCounts['day_swap']); ?>
                <i class="fas fa-right-left me-2"></i> สลับวันหยุด
            </a>
            <div class="collapse sidebar-submenu <?php echo (isActive('day_swap_request.php') || isActive('day_swap_history.php') || isActive('day_swap_approvals.php')) ? 'show' : ''; ?>" id="daySwapSubmenu" data-bs-parent="#sidebarMenu">
                <a href="day_swap_request.php" class="list-group-item list-group-item-action bg-transparent border-0 ps-5 <?php echo isActive('day_swap_request.php'); ?>">
                    <small>ขอสลับวันหยุด</small>
                </a>
                <a href="day_swap_history.php" class="list-group-item list-group-item-action bg-transparent border-0 ps-5 <?php echo isActive('day_swap_history.php'); ?>">
                    <small>ประวัติคำขอ</small>
                </a>
                <?php if (in_array($_SESSION['role'], ['manager', 'admin', 'hr'])) : ?>
                <a href="day_swap_approvals.php" class="list-group-item list-group-item-action bg-transparent border-0 ps-5 d-flex align-items-center <?php echo isActive('day_swap_approvals.php'); ?>">
                    <?php echo renderSidebarApprovalBadge($approvalBadgeCounts['day_swap']); ?>
                    <small>อนุมัติสลับวันหยุด</small>
                </a>
                <?php endif; ?>
            </div>

            <!-- Training Request System (Dropdown) -->
            <a href="#trainingSubmenu" data-bs-toggle="collapse" aria-expanded="false" class="list-group-item list-group-item-action bg-transparent dropdown-toggle d-flex align-items-center">
                <?php echo renderSidebarApprovalBadge($approvalBadgeCounts['training']); ?>
                <i class="fas fa-graduation-cap me-2"></i> อบรม
            </a>
            <div class="collapse sidebar-submenu <?php echo (isActive('training_request.php') || isActive('training_history.php') || isActive('training_approvals.php')) ? 'show' : ''; ?>" id="trainingSubmenu" data-bs-parent="#sidebarMenu">
                <a href="training_request.php" class="list-group-item list-group-item-action bg-transparent border-0 ps-5 <?php echo isActive('training_request.php'); ?>">
                    <small>ขอไปอบรม</small>
                </a>
                <a href="training_history.php" class="list-group-item list-group-item-action bg-transparent border-0 ps-5 <?php echo isActive('training_history.php'); ?>">
                    <small>ประวัติคำขออบรม</small>
                </a>
                <?php if (in_array($_SESSION['role'], ['manager', 'admin', 'hr'])) : ?>
                <a href="training_approvals.php" class="list-group-item list-group-item-action bg-transparent border-0 ps-5 d-flex align-items-center <?php echo isActive('training_approvals.php'); ?>">
                    <?php echo renderSidebarApprovalBadge($approvalBadgeCounts['training']); ?>
                    <small>อนุมัติคำขออบรม</small>
                </a>
                <?php endif; ?>
            </div>

            <!-- Employee Warning Records -->
            <?php if (in_array($_SESSION['role'], ['admin', 'hr'], true)) : ?>
            <a href="employee_warnings.php" class="list-group-item list-group-item-action bg-transparent <?php echo isActive('employee_warnings.php'); ?>">
                <i class="fas fa-triangle-exclamation me-2"></i> ใบเตือนพนักงาน
            </a>
            <a href="my_warnings.php" class="list-group-item list-group-item-action bg-transparent <?php echo isActive('my_warnings.php'); ?>">
                <i class="fas fa-user-shield me-2"></i> ใบเตือนของฉัน
            </a>
            <?php else : ?>
            <a href="my_warnings.php" class="list-group-item list-group-item-action bg-transparent <?php echo isActive('my_warnings.php'); ?>">
                <i class="fas fa-triangle-exclamation me-2"></i> ใบเตือนของฉัน
            </a>
            <?php endif; ?>

            <!-- Employee Management -->
            <?php if (in_array($_SESSION['role'], ['admin', 'hr'])) : ?>
            <a href="employees.php" class="list-group-item list-group-item-action bg-transparent <?php echo isActive('employees.php'); ?>">
                <i class="fas fa-users me-2"></i> จัดการพนักงาน
            </a>
            <?php endif; ?>

            <!-- Time Attendance -->
            <a href="attendance.php" class="list-group-item list-group-item-action bg-transparent <?php echo isActive('attendance.php'); ?>">
                <i class="fas fa-clock me-2"></i> เวลาทำงานของฉัน
            </a>
            <?php if (in_array($_SESSION['role'], ['admin', 'hr'])) : ?>
            <a href="attendance_import.php" class="list-group-item list-group-item-action bg-transparent <?php echo isActive('attendance_import.php'); ?>">
                <i class="fas fa-file-import me-2"></i> นำเข้าลงเวลา
            </a>
            <a href="attendance_adjustments.php" class="list-group-item list-group-item-action bg-transparent <?php echo isActive('attendance_adjustments.php'); ?>">
                <i class="fas fa-pen-to-square me-2"></i> ปรับแก้เวลาสแกน
            </a>
            <?php endif; ?>

            <!-- Settings (Dropdown) -->
            <?php if (in_array($_SESSION['role'], ['admin', 'hr'])) : ?>
            <a href="#settingsSubmenu" data-bs-toggle="collapse" aria-expanded="false" class="list-group-item list-group-item-action bg-transparent dropdown-toggle">
                <i class="fas fa-cogs me-2"></i> ตั้งค่าระบบ
            </a>
            <div class="collapse sidebar-submenu <?php echo (isActive('leave_types.php') || isActive('shifts.php') || isActive('company_holidays.php') || isActive('manage_master_data.php')) ? 'show' : ''; ?>" id="settingsSubmenu" data-bs-parent="#sidebarMenu">
                <a href="leave_types.php" class="list-group-item list-group-item-action bg-transparent border-0 ps-5 <?php echo isActive('leave_types.php'); ?>">
                    <small>ประเภทการลา</small>
                </a>
                <a href="shifts.php" class="list-group-item list-group-item-action bg-transparent border-0 ps-5 <?php echo isActive('shifts.php'); ?>">
                    <small>กะการทำงาน</small>
                </a>
                <a href="company_holidays.php" class="list-group-item list-group-item-action bg-transparent border-0 ps-5 <?php echo isActive('company_holidays.php'); ?>">
                    <small>วันหยุดพิเศษ</small>
                </a>
                <?php if ($_SESSION['role'] === 'admin') : ?>
                <a href="manage_master_data.php" class="list-group-item list-group-item-action bg-transparent border-0 ps-5 <?php echo isActive('manage_master_data.php'); ?>">
                    <small>ข้อมูลพื้นฐาน</small>
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <a href="logout.php" class="list-group-item list-group-item-action bg-transparent text-danger fw-bold mt-3">
                <i class="fas fa-power-off me-2"></i> ออกจากระบบ
            </a>
        </div>
    </div>
    <!-- /#sidebar-wrapper -->

    <!-- ================= Page Content ================= -->
    <div id="page-content-wrapper">
        
        <!-- Top navigation -->
        <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom navbar-top">
            <div class="container-fluid">
                <!-- Toggle Button -->
                <button class="btn btn-light text-white" id="sidebarToggle">
                    <i class="fas fa-bars fa-lg"></i>
                </button>

                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <ul class="navbar-nav ms-auto mt-2 mt-lg-0">
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                                <div class="bg-light text-danger rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 35px; height: 35px;">
                                    <i class="fas fa-user"></i>
                                </div>
                                <div class="d-none d-sm-block text-start">
                                    <span class="d-block fw-bold small text-white lh-1"><?php echo htmlspecialchars($displayName); ?></span>
                                    <span class="d-block text-white-50" style="font-size: 0.75rem;"><?php echo htmlspecialchars($displayPosition); ?></span>
                                </div>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                                <li><a class="dropdown-item" href="my_profile.php"><i class="fas fa-id-card me-2 text-muted"></i> โปรไฟล์</a></li>
                                <li><a class="dropdown-item" href="change_password.php"><i class="fas fa-key me-2 text-muted"></i> เปลี่ยนรหัสผ่าน</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> ออกจากระบบ</a></li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>

        <!-- Main Content Container -->
        <div class="container-fluid px-4 py-4">
<?php endif; ?>
