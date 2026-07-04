(function () {
    const apiBase = 'api/proxy_request_api.php';
    const employeesUrl = 'api/proxy_request_api.php?action=employees';
    const employeeSelect = document.getElementById('proxyEmployeeId');
    const targetEmployeeSelect = document.getElementById('proxyTargetEmployeeId');
    const panels = Array.from(document.querySelectorAll('[data-proxy-panel]'));
    const allowedActions = new Set(['create_leave', 'create_late_early', 'create_overtime', 'create_day_swap', 'create_training']);
    let employees = [];
    let proxyLeaveTypes = [];

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
        proxyLeaveTypes = result.data || [];
        select.innerHTML = '<option value="">เลือกประเภทการลา</option>' + (result.data || []).map((row) => (
            `<option value="${escapeHtml(row.id)}">${escapeHtml(row.type_name)}</option>`
        )).join('');
        select.addEventListener('change', updateProxyLeaveMode);
        updateProxyLeaveMode();
    }

    function formatProxyTime(value) {
        return value ? String(value).slice(0, 5) : '-';
    }

    function parseProxyTimeToMinutes(value) {
        const match = String(value || '').match(/^(\d{2}):(\d{2})/);
        if (!match) return null;
        const hours = Number.parseInt(match[1], 10);
        const minutes = Number.parseInt(match[2], 10);
        if (hours < 0 || hours > 23 || minutes < 0 || minutes > 59) return null;
        return hours * 60 + minutes;
    }

    function formatProxyHourMinuteDuration(minutes) {
        const safeMinutes = Math.max(0, Number.parseInt(minutes || 0, 10) || 0);
        const hours = Math.floor(safeMinutes / 60);
        const remaining = safeMinutes % 60;
        const parts = [];
        if (hours > 0) parts.push(`${hours} ชม.`);
        if (remaining > 0 || !parts.length) parts.push(`${remaining} นาที`);
        return parts.join(' ');
    }

    function formatProxyOvertimeDuration(startTime, endTime) {
        const start = parseProxyTimeToMinutes(startTime);
        const end = parseProxyTimeToMinutes(endTime);
        if (start === null || end === null) return { valid: false, message: 'รูปแบบเวลาไม่ถูกต้อง', label: '' };
        const minutes = end - start;
        if (minutes <= 0) return { valid: false, message: 'เวลาสิ้นสุดต้องมากกว่าเวลาเริ่ม', label: '' };
        return { valid: true, message: '', label: formatProxyHourMinuteDuration(minutes) };
    }

    function selectedProxyLeaveType() {
        const selectedId = document.getElementById('proxyLeaveTypeId')?.value || '';
        return proxyLeaveTypes.find((type) => String(type.id) === String(selectedId)) || null;
    }

    function isSelectedProxyLeaveHourly() {
        return selectedProxyLeaveType()?.calculation_unit === 'hour';
    }

    function updateProxyLeaveMode() {
        const form = document.querySelector('[data-action="create_leave"]');
        if (!form) return;
        const isHourly = isSelectedProxyLeaveHourly();
        const endDate = form.querySelector('[name="end_date"]');
        const startTime = form.querySelector('[name="request_start_time"]');
        const endTime = form.querySelector('[name="request_end_time"]');

        form.querySelectorAll('.proxy-day-leave-field').forEach((field) => field.classList.toggle('d-none', isHourly));
        document.getElementById('proxyHourlyLeaveFields')?.classList.toggle('d-none', !isHourly);
        if (endDate) {
            endDate.required = !isHourly;
            if (isHourly) endDate.value = form.querySelector('[name="start_date"]')?.value || '';
        }
        form.querySelector('[name="start_day_part"]') && (form.querySelector('[name="start_day_part"]').required = !isHourly);
        form.querySelector('[name="end_day_part"]') && (form.querySelector('[name="end_day_part"]').required = !isHourly);
        if (startTime) {
            startTime.required = isHourly;
            if (!isHourly) startTime.value = '';
        }
        if (endTime) {
            endTime.required = isHourly;
            if (!isHourly) endTime.value = '';
        }
        renderProxyHourlyLeaveDuration();
    }

    function renderProxyHourlyLeaveDuration() {
        const form = document.querySelector('[data-action="create_leave"]');
        const target = document.getElementById('proxyHourlyLeaveDuration');
        if (!form || !target || !isSelectedProxyLeaveHourly()) return;
        const startTime = form.querySelector('[name="request_start_time"]')?.value || '';
        const endTime = form.querySelector('[name="request_end_time"]')?.value || '';
        if (!startTime || !endTime) {
            target.classList.add('d-none');
            target.textContent = '';
            return;
        }
        const duration = formatProxyOvertimeDuration(startTime, endTime);
        target.classList.remove('d-none', 'alert-danger', 'alert-success');
        target.classList.add(duration.valid ? 'alert-success' : 'alert-danger');
        target.textContent = duration.valid ? `รวม ${duration.label}` : duration.message;
    }

    function renderProxyWorkDateContext(context) {
        const dayLabel = escapeHtml(context.day_type_label || '-');
        const holiday = context.holiday_name ? ` <span class="text-muted">(${escapeHtml(context.holiday_name)})</span>` : '';
        const shift = context.shift_start_time && context.shift_end_time
            ? `${formatProxyTime(context.shift_start_time)}-${formatProxyTime(context.shift_end_time)}`
            : '-';
        return `<div class="small text-muted">ข้อมูลวันที่เลือก</div><div class="fw-semibold">${dayLabel}${holiday} | กะ ${shift}</div>`;
    }

    async function loadProxyOvertimeDateContext() {
        const form = document.querySelector('[data-action="create_overtime"]');
        const target = document.getElementById('proxyOvertimeDateContext');
        const workDate = form?.querySelector('[name="work_date"]')?.value || '';
        if (!target) return;
        if (!selectedEmployeeId() || !workDate) {
            target.classList.add('d-none');
            target.innerHTML = '';
            return;
        }
        target.classList.remove('d-none', 'alert-danger');
        target.classList.add('alert-light');
        target.innerHTML = '<span class="text-muted small">กำลังโหลดข้อมูลกะ...</span>';
        try {
            const params = new URLSearchParams({
                action: 'work_date_context',
                employee_id: selectedEmployeeId(),
                work_date: workDate,
            });
            const result = await loadJson(`${apiBase}?${params.toString()}`);
            if (result.status !== 'success') {
                target.classList.remove('alert-light');
                target.classList.add('alert-danger');
                target.textContent = result.message || 'ไม่พบข้อมูลกะของวันที่เลือก';
                return;
            }
            target.classList.remove('alert-danger');
            target.classList.add('alert-light');
            target.innerHTML = renderProxyWorkDateContext(result.data || {});
        } catch (error) {
            console.error(error);
            target.classList.remove('alert-light');
            target.classList.add('alert-danger');
            target.textContent = 'ไม่สามารถโหลดข้อมูลกะของวันที่เลือกได้';
        }
    }

    function renderProxyOvertimeDuration() {
        const form = document.querySelector('[data-action="create_overtime"]');
        const target = document.getElementById('proxyOvertimeDuration');
        if (!form || !target) return;
        const startTime = form.querySelector('[name="overtime_start_time"]')?.value || '';
        const endTime = form.querySelector('[name="overtime_end_time"]')?.value || '';
        if (!startTime || !endTime) {
            target.classList.add('d-none');
            target.textContent = '';
            return;
        }
        const duration = formatProxyOvertimeDuration(startTime, endTime);
        target.classList.remove('d-none', 'alert-danger', 'alert-success');
        target.classList.add(duration.valid ? 'alert-success' : 'alert-danger');
        target.textContent = duration.valid ? `รวม ${duration.label}` : duration.message;
    }

    function initProxyOvertimeHelpers() {
        const form = document.querySelector('[data-action="create_overtime"]');
        if (!form) return;
        form.querySelector('[name="work_date"]')?.addEventListener('change', loadProxyOvertimeDateContext);
        form.querySelector('[name="overtime_start_time"]')?.addEventListener('input', renderProxyOvertimeDuration);
        form.querySelector('[name="overtime_end_time"]')?.addEventListener('input', renderProxyOvertimeDuration);
        employeeSelect?.addEventListener('change', loadProxyOvertimeDateContext);
        if (window.jQuery && employeeSelect) {
            jQuery(employeeSelect).on('select2:select.proxyOvertime select2:clear.proxyOvertime', loadProxyOvertimeDateContext);
        }
    }

    function initProxyLeaveHelpers() {
        const form = document.querySelector('[data-action="create_leave"]');
        if (!form) return;
        form.querySelector('[name="start_date"]')?.addEventListener('change', updateProxyLeaveMode);
        form.querySelector('[name="request_start_time"]')?.addEventListener('input', renderProxyHourlyLeaveDuration);
        form.querySelector('[name="request_end_time"]')?.addEventListener('input', renderProxyHourlyLeaveDuration);
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
            loadProxyOvertimeDateContext();
            renderProxyOvertimeDuration();
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
    initProxyLeaveHelpers();
    initProxyOvertimeHelpers();
    showPanel('leave');
})();
