(function () {
    const apiBase = 'api/proxy_request_api.php';
    const employeesUrl = 'api/proxy_request_api.php?action=employees';
    const employeeSelect = document.getElementById('proxyEmployeeId');
    const targetEmployeeSelect = document.getElementById('proxyTargetEmployeeId');
    const panels = Array.from(document.querySelectorAll('[data-proxy-panel]'));
    const allowedActions = new Set(['create_leave', 'create_late_early', 'create_overtime', 'create_day_swap', 'create_training']);
    let employees = [];

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function showPanel(name) {
        panels.forEach((panel) => panel.classList.toggle('d-none', panel.dataset.proxyPanel !== name));
        document.querySelectorAll('[data-proxy-tab]').forEach((tab) => {
            const isActive = tab.dataset.proxyTab === name;
            tab.classList.toggle('active', isActive);
            tab.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });
    }

    function selectedEmployeeId() {
        return employeeSelect ? employeeSelect.value : '';
    }

    async function loadJson(url, options) {
        const response = await fetch(url, options);
        return response.json();
    }

    function employeeOption(row) {
        const code = row.citizen_id ? `${row.citizen_id} - ` : '';
        const name = `${row.first_name_th || ''} ${row.last_name_th || ''}`.trim();
        return `<option value="${escapeHtml(row.id)}">${escapeHtml(code + name)}</option>`;
    }

    function initSelect2(select, placeholder) {
        if (!select || !window.jQuery || !jQuery.fn.select2) return;
        const $select = jQuery(select);
        if ($select.hasClass('select2-hidden-accessible')) {
            $select.select2('destroy');
        }
        $select.select2({
            width: '100%',
            placeholder,
            allowClear: true,
        });
    }

    function initEmployeeSelect2() {
        initSelect2(employeeSelect, 'เลือกพนักงาน');
        initSelect2(targetEmployeeSelect, 'เลือกพนักงานคู่สลับ');
    }

    async function loadEmployees() {
        const result = await loadJson(employeesUrl);
        if (result.status !== 'success') throw new Error(result.message || 'Load employees failed');
        employees = result.data || [];
        const options = '<option value="">เลือกพนักงาน</option>' + employees.map(employeeOption).join('');
        if (employeeSelect) employeeSelect.innerHTML = options;
        if (targetEmployeeSelect) targetEmployeeSelect.innerHTML = options;
        initEmployeeSelect2();
    }

    async function loadLeaveTypes() {
        const select = document.getElementById('proxyLeaveTypeId');
        if (!select) return;
        const result = await loadJson(`${apiBase}?action=leave_types`);
        if (result.status !== 'success') return;
        select.innerHTML = '<option value="">เลือกประเภทการลา</option>' + (result.data || []).map((row) => (
            `<option value="${escapeHtml(row.id)}">${escapeHtml(row.type_name)}</option>`
        )).join('');
    }

    async function submitProxyForm(event) {
        event.preventDefault();
        const form = event.currentTarget;
        const action = form.dataset.action;
        if (!allowedActions.has(action)) {
            Swal.fire('ไม่สำเร็จ', 'Invalid Action', 'error');
            return;
        }
        if (!selectedEmployeeId()) {
            Swal.fire('กรุณาเลือกพนักงาน', '', 'warning');
            return;
        }

        const submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        const data = new FormData(form);
        data.set('employee_id', selectedEmployeeId());
        data.set('action', action);

        try {
            const result = await loadJson(`${apiBase}?action=${encodeURIComponent(action)}`, {
                method: 'POST',
                body: data,
            });
            if (result.status !== 'success') {
                Swal.fire('ไม่สำเร็จ', result.message || 'System Error', 'error');
                return;
            }
            await Swal.fire('สำเร็จ', result.message || 'บันทึกเรียบร้อยแล้ว', 'success');
            form.reset();
        } catch (error) {
            console.error(error);
            Swal.fire('ผิดพลาด', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้', 'error');
        } finally {
            submitBtn.disabled = false;
        }
    }

    document.querySelectorAll('[data-proxy-tab]').forEach((tab) => {
        tab.addEventListener('click', () => showPanel(tab.dataset.proxyTab));
    });
    panels.forEach((panel) => panel.addEventListener('submit', submitProxyForm));

    loadEmployees().catch((error) => Swal.fire('ไม่สำเร็จ', error.message, 'error'));
    loadLeaveTypes();
    showPanel('leave');
})();
