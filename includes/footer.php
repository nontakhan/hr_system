</div> <!-- ปิด .container-fluid (Main Content) -->
    </div> <!-- ปิด #page-content-wrapper -->
</div> <!-- ปิด #wrapper -->

<?php // (ถ้าไม่ได้ Login จะไม่มี div #wrapper เปิดอยู่ แต่ปิดไปก็ไม่มีผลเสียอะไรใน HTML5) ?>

<?php
require_once __DIR__ . '/page_assets.php';
$pageAssets = hrPageAssets(basename($_SERVER['PHP_SELF']));
?>
<!-- JS -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<?php if ($pageAssets['chart']): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<?php endif; ?>
<?php if ($pageAssets['datatables']): ?>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<?php endif; ?>
<?php if (!empty($use_select2)) : ?>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<?php endif; ?>
<?php if (!empty($use_fullcalendar)) : ?>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.20/index.global.min.js"></script>
<?php endif; ?>

<!-- Custom JS -->
<?php foreach ($pageAssets['scripts'] as $script): ?>
<script src="assets/js/<?php echo $script; ?>.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/' . $script . '.js'); ?>"></script>
<?php endforeach; ?>

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
