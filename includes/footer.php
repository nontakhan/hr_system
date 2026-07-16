</div> <!-- ปิด .container-fluid (Main Content) -->
    </div> <!-- ปิด #page-content-wrapper -->
</div> <!-- ปิด #wrapper -->

<?php // (ถ้าไม่ได้ Login จะไม่มี div #wrapper เปิดอยู่ แต่ปิดไปก็ไม่มีผลเสียอะไรใน HTML5) ?>

<!-- JS -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<?php if (!empty($use_select2)) : ?>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<?php endif; ?>
<?php if (!empty($use_fullcalendar)) : ?>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.20/index.global.min.js"></script>
<?php endif; ?>

<!-- Custom JS -->
<script src="assets/js/utils.js"></script>
<script src="assets/js/auth.js"></script>
<script src="assets/js/master_data.js"></script>
<script src="assets/js/employee.js"></script>
<script src="assets/js/leave.js"></script>
<script src="assets/js/bulk_employee_warnings.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/bulk_employee_warnings.js'); ?>"></script>
<script src="assets/js/leave_report.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/leave_report.js'); ?>"></script>
<script src="assets/js/leave_request.js"></script>
<script src="assets/js/late_early_request.js"></script>
<script src="assets/js/my_leaves.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/my_leaves.js'); ?>"></script>
<script src="assets/js/leave_approval.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/leave_approval.js'); ?>"></script>
<script src="assets/js/dashboard.js"></script>
<script src="assets/js/shift.js"></script>
<script src="assets/js/attendance.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/attendance.js'); ?>"></script>
<script src="assets/js/company_holidays.js"></script>
<script src="assets/js/holiday_calendar.js"></script>
<script src="assets/js/day_swap.js"></script>
<script src="assets/js/training_request.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/training_request.js'); ?>"></script>

<!-- (NEW) Script สำหรับ Toggle Sidebar -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var el = document.getElementById("wrapper");
        var toggleButton = document.getElementById("sidebarToggle");

        if (el && toggleButton) {
            toggleButton.onclick = function () {
                el.classList.toggle("sb-sidenav-toggled");
                document.body.classList.toggle("sb-sidenav-toggled");
            };
        }
    });
</script>

</body>
</html>
