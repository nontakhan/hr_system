<?php
/*
 * ไฟล์ Header (Layout ใหม่: Sidebar + Topbar)
 */
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// ฟังก์ชันช่วยเช็ค Active Menu
function isActive($page) {
    return basename($_SERVER['PHP_SELF']) == $page ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) : "HR System"; ?></title>

    <!-- CSS Links -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
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
        
        <div class="list-group list-group-flush my-3">
            
            <!-- Dashboard -->
            <a href="dashboard.php" class="list-group-item list-group-item-action bg-transparent <?php echo isActive('dashboard.php'); ?>">
                <i class="fas fa-home me-2"></i> หน้าหลัก
            </a>

            <!-- Leave System (Dropdown) -->
            <a href="#leaveSubmenu" data-bs-toggle="collapse" aria-expanded="false" class="list-group-item list-group-item-action bg-transparent dropdown-toggle">
                <i class="fas fa-calendar-alt me-2"></i> ระบบการลา
            </a>
            <div class="collapse sidebar-submenu <?php echo (isActive('leave_request.php') || isActive('my_leaves.php') || isActive('leave_approvals.php')) ? 'show' : ''; ?>" id="leaveSubmenu">
                <a href="leave_request.php" class="list-group-item list-group-item-action bg-transparent border-0 ps-5 <?php echo isActive('leave_request.php'); ?>">
                    <small>ยื่นใบลา</small>
                </a>
                <a href="my_leaves.php" class="list-group-item list-group-item-action bg-transparent border-0 ps-5 <?php echo isActive('my_leaves.php'); ?>">
                    <small>ประวัติการลา</small>
                </a>
                <?php if (in_array($_SESSION['role'], ['manager', 'admin', 'hr'])) : ?>
                <a href="leave_approvals.php" class="list-group-item list-group-item-action bg-transparent border-0 ps-5 <?php echo isActive('leave_approvals.php'); ?>">
                    <small>อนุมัติการลา</small>
                </a>
                <?php endif; ?>
            </div>

            <!-- Employee Management -->
            <?php if (in_array($_SESSION['role'], ['admin', 'hr'])) : ?>
            <a href="employees.php" class="list-group-item list-group-item-action bg-transparent <?php echo isActive('employees.php'); ?>">
                <i class="fas fa-users me-2"></i> จัดการพนักงาน
            </a>
            <?php endif; ?>

            <!-- Time Attendance (Next Phase Placeholder) -->
            <a href="#" class="list-group-item list-group-item-action bg-transparent text-muted">
                <i class="fas fa-clock me-2"></i> ลงเวลา (เร็วๆ นี้)
            </a>

            <!-- Settings (Dropdown) -->
            <?php if (in_array($_SESSION['role'], ['admin', 'hr'])) : ?>
            <a href="#settingsSubmenu" data-bs-toggle="collapse" aria-expanded="false" class="list-group-item list-group-item-action bg-transparent dropdown-toggle">
                <i class="fas fa-cogs me-2"></i> ตั้งค่าระบบ
            </a>
            <div class="collapse sidebar-submenu <?php echo (isActive('leave_types.php') || isActive('shifts.php') || isActive('manage_master_data.php')) ? 'show' : ''; ?>" id="settingsSubmenu">
                <a href="leave_types.php" class="list-group-item list-group-item-action bg-transparent border-0 ps-5 <?php echo isActive('leave_types.php'); ?>">
                    <small>ประเภทการลา</small>
                </a>
                <a href="shifts.php" class="list-group-item list-group-item-action bg-transparent border-0 ps-5 <?php echo isActive('shifts.php'); ?>">
                    <small>กะการทำงาน</small>
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
                <button class="btn btn-light text-primary" id="sidebarToggle">
                    <i class="fas fa-bars fa-lg"></i>
                </button>

                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <ul class="navbar-nav ms-auto mt-2 mt-lg-0">
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                                <div class="bg-light text-primary rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 35px; height: 35px;">
                                    <i class="fas fa-user"></i>
                                </div>
                                <div class="d-none d-sm-block text-start">
                                    <span class="d-block fw-bold small text-dark lh-1"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                                    <span class="d-block text-muted" style="font-size: 0.75rem;"><?php echo ucfirst($_SESSION['role']); ?></span>
                                </div>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                                <li><a class="dropdown-item" href="#"><i class="fas fa-id-card me-2 text-muted"></i> โปรไฟล์</a></li>
                                <li><a class="dropdown-item" href="#"><i class="fas fa-key me-2 text-muted"></i> เปลี่ยนรหัสผ่าน</a></li>
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