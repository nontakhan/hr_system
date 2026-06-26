/*
 * Logic สำหรับระบบการลา (Leave System)
 */

document.addEventListener('DOMContentLoaded', () => {
    
    // 1. หน้าจัดการประเภทการลา (leave_types.php)
    const leaveTypesTable = document.getElementById('leaveTypesTable');
    if (leaveTypesTable) {
        loadLeaveTypes();
        loadLeaveSettings();

        // จัดการ Modal (Create/Edit)
        const modal = document.getElementById('leaveTypeModal');
        const form = document.getElementById('leaveTypeForm');
        const modalTitle = document.getElementById('modalTitle');
        const settingsForm = document.getElementById('leaveSettingsForm');
        const settingsResetBtn = document.getElementById('leavePolicyResetBtn');
        const policyTable = document.getElementById('leavePolicyTable');
        const calculationUnitHour = document.getElementById('calculationUnitHour');

        if (settingsForm) {
            settingsForm.addEventListener('submit', handleSaveLeaveSettings);
        }
        if (settingsResetBtn) {
            settingsResetBtn.addEventListener('click', resetLeavePolicyForm);
        }
        if (policyTable) {
            policyTable.addEventListener('click', handleLeavePolicyTableClick);
        }
        if (calculationUnitHour) {
            calculationUnitHour.addEventListener('change', toggleLeaveTypeCalculationFields);
        }

        // เมื่อเปิด Modal
        modal.addEventListener('show.bs.modal', (e) => {
            const btn = e.relatedTarget;
            const action = btn.getAttribute('data-action');
            
            if (action === 'create') {
                form.reset();
                document.getElementById('type_id').value = '';
                modalTitle.innerText = 'เพิ่มประเภทการลา';
                if (form.hours_per_day) form.hours_per_day.value = '8';
                if (form.hour_full_day_threshold) form.hour_full_day_threshold.value = '0';
                if (form.vacation_min_months_before_leave) form.vacation_min_months_before_leave.value = '0';
                if (form.is_actual_leave) form.is_actual_leave.checked = true;
                toggleLeaveTypeCalculationFields();
            } else if (action === 'edit') {
                modalTitle.innerText = 'แก้ไขประเภทการลา';
                // ดึงข้อมูลจากปุ่มมาใส่ฟอร์ม (ใช้ Dataset ที่ฝังไว้ในปุ่ม)
                const data = JSON.parse(btn.getAttribute('data-info'));
                form.type_name.value = data.type_name;
                form.days_per_year.value = data.days_per_year;
                form.description.value = data.description || '';
                form.requires_file.checked = data.requires_file == 1;
                form.calculation_unit.checked = data.calculation_unit === 'hour';
                form.hours_per_day.value = data.hours_per_day || 8;
                form.hour_full_day_threshold.value = data.hour_full_day_threshold || 0;
                form.vacation_min_months_before_leave.value = data.vacation_min_months_before_leave || 0;
                form.is_actual_leave.checked = Number.parseInt(data.is_actual_leave ?? 1, 10) === 1;
                document.getElementById('type_id').value = data.id;
                toggleLeaveTypeCalculationFields();
            }
        });

        // Submit Form
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const id = document.getElementById('type_id').value;
            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());
            
            // เพิ่ม checkbox ถ้าไม่ได้ติ๊ก (เพราะ FormData จะไม่ส่งค่าถ้าไม่ติ๊ก)
            data.requires_file = form.requires_file.checked ? 1 : 0;
            data.calculation_unit = form.calculation_unit.checked ? 'hour' : 'day';
            data.hours_per_day = form.hours_per_day?.value || 8;
            data.hour_full_day_threshold = form.hour_full_day_threshold?.value || 0;
            data.vacation_min_months_before_leave = form.vacation_min_months_before_leave?.value || 0;
            data.is_actual_leave = form.is_actual_leave?.checked ? 1 : 0;
            data.action = id ? 'update_type' : 'create_type';

            try {
                const response = await fetch('api/leave_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const res = await response.json();
                
                if (res.status === 'success') {
                    Swal.fire('สำเร็จ', res.message, 'success');
                    bootstrap.Modal.getInstance(modal).hide();
                    loadLeaveTypes();
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            } catch (err) { Swal.fire('Error', err.message, 'error'); }
        });

        // Delete
        leaveTypesTable.addEventListener('click', (e) => {
            if (e.target.closest('.btn-delete')) {
                const id = e.target.closest('.btn-delete').getAttribute('data-id');
                handleDeleteLeaveType(id);
            }
        });
    }
});

function toggleLeaveTypeCalculationFields() {
    const calculationUnitHour = document.getElementById('calculationUnitHour');
    const settings = document.getElementById('hourlyCalculationSettings');
    if (!calculationUnitHour || !settings) return;

    const isHourly = calculationUnitHour.checked;
    settings.classList.toggle('d-none', !isHourly);
    settings.querySelectorAll('input').forEach(input => {
        input.disabled = !isHourly;
    });
}

function formatLeaveNumber(value) {
    const number = Number.parseFloat(value) || 0;
    return Number.isInteger(number) ? String(number) : number.toFixed(2).replace(/0+$/, '').replace(/\.$/, '');
}

function getThaiMonthName(month) {
    return [
        '',
        'มกราคม',
        'กุมภาพันธ์',
        'มีนาคม',
        'เมษายน',
        'พฤษภาคม',
        'มิถุนายน',
        'กรกฎาคม',
        'สิงหาคม',
        'กันยายน',
        'ตุลาคม',
        'พฤศจิกายน',
        'ธันวาคม',
    ][Number.parseInt(month, 10)] || '-';
}

async function loadLeaveSettings() {
    const select = document.getElementById('fiscalYearStartMonth');
    const policyNameInput = document.getElementById('leavePolicyName');
    const requestLimitInput = document.getElementById('leaveMaxRequestsPerYear');
    const activeInput = document.getElementById('leavePolicyActive');
    const preview = document.getElementById('leaveFiscalYearPreview');
    if (!select || !preview) return;

    try {
        const response = await fetch('api/leave_api.php?action=get_settings');
        const res = await response.json();
        if (res.status !== 'success') {
            throw new Error(res.message || 'โหลดการตั้งค่าไม่สำเร็จ');
        }

        const activePolicy = res.data.active_policy || {};
        select.value = String(activePolicy.fiscal_year_start_month || res.data.fiscal_year_start_month || 10);
        if (policyNameInput && !policyNameInput.value) {
            policyNameInput.value = '';
        }
        if (requestLimitInput) {
            requestLimitInput.value = String(activePolicy.leave_max_requests_per_year || res.data.leave_max_requests_per_year || 0);
        }
        if (activeInput) {
            activeInput.checked = false;
        }
        renderLeaveFiscalYearPreview(activePolicy.current_fiscal_year || res.data.current_fiscal_year, activePolicy);
        renderLeavePolicyRows(res.data.policies || []);
    } catch (err) {
        preview.textContent = err.message;
        preview.classList.add('text-danger');
    }
}

function renderLeaveFiscalYearPreview(fiscalYear, activePolicy = null) {
    const preview = document.getElementById('leaveFiscalYearPreview');
    if (!preview || !fiscalYear) return;

    preview.classList.remove('text-danger');
    const policyLabel = activePolicy && activePolicy.policy_name
        ? `<div><span class="badge bg-success">ใช้งานอยู่</span> ${escapeHtml(activePolicy.policy_name)}</div>`
        : '';
    preview.innerHTML = `${policyLabel}<div>ปีงบประมาณปัจจุบัน: <strong>${formatThaiDate(fiscalYear.start_date)}</strong> ถึง <strong>${formatThaiDate(fiscalYear.end_date)}</strong></div>`;
}

function renderLeavePolicyRows(policies) {
    const tbody = document.getElementById('leavePolicyTableBody');
    if (!tbody) return;

    if (!policies.length) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">ยังไม่มีชุดนโยบาย</td></tr>';
        return;
    }

    tbody.innerHTML = policies.map(policy => {
        const fiscal = policy.current_fiscal_year || {};
        const limitText = Number.parseInt(policy.leave_max_requests_per_year, 10) > 0
            ? `${policy.leave_max_requests_per_year} วัน`
            : 'ไม่จำกัด';
        const isActive = Number.parseInt(policy.is_active, 10) === 1;
        const data = escapeAttr(JSON.stringify(policy));
        return `
            <tr>
                <td>
                    <strong>${escapeHtml(policy.policy_name || '-')}</strong>
                    <div class="small text-muted">เริ่มเดือน${getThaiMonthName(policy.fiscal_year_start_month)}</div>
                </td>
                <td>${fiscal.start_date ? `${formatThaiDate(fiscal.start_date)} - ${formatThaiDate(fiscal.end_date)}` : '-'}</td>
                <td>${limitText}</td>
                <td>${isActive ? '<span class="badge bg-success">ใช้งานอยู่</span>' : '<span class="badge bg-secondary">สำรอง</span>'}</td>
                <td class="text-nowrap">
                    <button type="button" class="btn btn-sm btn-outline-primary btn-policy-edit" data-info="${data}">
                        <i class="fas fa-pencil-alt"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-success btn-policy-activate" data-id="${policy.id}" ${isActive ? 'disabled' : ''}>
                        ใช้งาน
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-danger btn-policy-delete" data-id="${policy.id}" ${isActive ? 'disabled' : ''}>
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

function resetLeavePolicyForm() {
    const idInput = document.getElementById('leavePolicyId');
    const nameInput = document.getElementById('leavePolicyName');
    const select = document.getElementById('fiscalYearStartMonth');
    const requestLimitInput = document.getElementById('leaveMaxRequestsPerYear');
    const activeInput = document.getElementById('leavePolicyActive');

    if (idInput) idInput.value = '';
    if (nameInput) nameInput.value = '';
    if (select) select.value = '10';
    if (requestLimitInput) requestLimitInput.value = '0';
    if (activeInput) activeInput.checked = false;
}

function fillLeavePolicyForm(policy) {
    const idInput = document.getElementById('leavePolicyId');
    const nameInput = document.getElementById('leavePolicyName');
    const select = document.getElementById('fiscalYearStartMonth');
    const requestLimitInput = document.getElementById('leaveMaxRequestsPerYear');
    const activeInput = document.getElementById('leavePolicyActive');

    if (idInput) idInput.value = policy.id || '';
    if (nameInput) nameInput.value = policy.policy_name || '';
    if (select) select.value = String(policy.fiscal_year_start_month || 10);
    if (requestLimitInput) requestLimitInput.value = String(policy.leave_max_requests_per_year || 0);
    if (activeInput) activeInput.checked = Number.parseInt(policy.is_active, 10) === 1;
}

async function handleLeavePolicyTableClick(e) {
    const editBtn = e.target.closest('.btn-policy-edit');
    const activateBtn = e.target.closest('.btn-policy-activate');
    const deleteBtn = e.target.closest('.btn-policy-delete');

    if (editBtn) {
        fillLeavePolicyForm(JSON.parse(editBtn.getAttribute('data-info')));
        document.getElementById('leavePolicyName')?.focus();
        return;
    }

    if (activateBtn) {
        try {
            await updateLeavePolicyState({ action: 'activate_settings', id: Number.parseInt(activateBtn.dataset.id, 10) }, 'เลือกนโยบายนี้เป็นชุดที่ใช้งานแล้ว');
        } catch (err) {
            Swal.fire('Error', err.message, 'error');
        }
        return;
    }

    if (deleteBtn) {
        const confirm = await Swal.fire({
            title: 'ยืนยันการลบ?',
            text: 'ชุดนโยบายที่ไม่ได้ใช้งานจะถูกลบออกจากรายการ',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'ลบ',
        });
        if (confirm.isConfirmed) {
            await deleteLeavePolicy(Number.parseInt(deleteBtn.dataset.id, 10));
        }
    }
}

async function updateLeavePolicyState(payload, successMessage) {
    const response = await fetch('api/leave_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
    });
    const res = await response.json();
    if (res.status !== 'success') {
        throw new Error(res.message || 'อัปเดตนโยบายไม่สำเร็จ');
    }
    renderLeaveFiscalYearPreview(res.data.current_fiscal_year, res.data.active_policy);
    renderLeavePolicyRows(res.data.policies || []);
    resetLeavePolicyForm();
    Swal.fire('สำเร็จ', successMessage || res.message, 'success');
}

async function deleteLeavePolicy(id) {
    try {
        const response = await fetch('api/leave_api.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete_settings', id }),
        });
        const res = await response.json();
        if (res.status !== 'success') {
            throw new Error(res.message || 'ลบนโยบายไม่สำเร็จ');
        }
        renderLeavePolicyRows(res.data.policies || []);
        renderLeaveFiscalYearPreview(res.data.active_policy.current_fiscal_year, res.data.active_policy);
        resetLeavePolicyForm();
        Swal.fire('สำเร็จ', res.message, 'success');
    } catch (err) {
        Swal.fire('Error', err.message, 'error');
    }
}

async function handleSaveLeaveSettings(e) {
    e.preventDefault();
    const idInput = document.getElementById('leavePolicyId');
    const nameInput = document.getElementById('leavePolicyName');
    const select = document.getElementById('fiscalYearStartMonth');
    const requestLimitInput = document.getElementById('leaveMaxRequestsPerYear');
    const activeInput = document.getElementById('leavePolicyActive');
    if (!select || !nameInput) return;

    try {
        const response = await fetch('api/leave_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'update_settings',
                id: Number.parseInt(idInput?.value || '0', 10),
                policy_name: nameInput.value.trim(),
                fiscal_year_start_month: Number.parseInt(select.value, 10),
                leave_max_requests_per_year: Number.parseInt(requestLimitInput?.value || '0', 10),
                is_active: activeInput?.checked ? 1 : 0,
            }),
        });
        const res = await response.json();
        if (res.status !== 'success') {
            throw new Error(res.message || 'บันทึกการตั้งค่าไม่สำเร็จ');
        }

        renderLeaveFiscalYearPreview(res.data.current_fiscal_year, res.data.active_policy);
        renderLeavePolicyRows(res.data.policies || []);
        resetLeavePolicyForm();
        Swal.fire('สำเร็จ', `บันทึกชุดนโยบายการลาแล้ว`, 'success');
    } catch (err) {
        Swal.fire('Error', err.message, 'error');
    }
}

async function loadLeaveTypes() {
    const tbody = document.getElementById('leaveTypesTableBody');
    try {
        const response = await fetch('api/leave_api.php?action=get_types');
        const res = await response.json();
        
        if (res.status === 'success') {
            tbody.innerHTML = '';
            if (res.data.length === 0) {
                tbody.innerHTML = `<tr><td colspan="5" class="text-center text-muted py-3">ยังไม่มีข้อมูล</td></tr>`;
                return;
            }
            res.data.forEach(item => {
                // เก็บข้อมูลใส่ data-info เพื่อใช้ตอน Edit
                const jsonInfo = JSON.stringify(item);
                const itemId = Number.parseInt(item.id, 10) || 0;
                const typeName = escapeHtml(item.type_name);
                const description = escapeHtml(item.description || '-');
                const daysPerYear = Number.parseFloat(item.days_per_year) || 0;
                const isHourly = item.calculation_unit === 'hour';
                const hourlyBadge = isHourly
                    ? `<span class="badge bg-info text-dark"><i class="fas fa-clock"></i> รายชั่วโมง (${formatLeaveNumber(item.hours_per_day || 8)} ชม. = 1 วัน)</span>`
                    : '';
                const thresholdBadge = isHourly && Number.parseFloat(item.hour_full_day_threshold || 0) > 0
                    ? `<span class="badge bg-light text-dark border">เกิน ${formatLeaveNumber(item.hour_full_day_threshold)} ชม. = 1 วัน</span>`
                    : '';
                const minMonths = Number.parseInt(item.vacation_min_months_before_leave, 10) || 0;
                const minMonthsBadge = minMonths > 0
                    ? `<span class="badge bg-secondary">อายุงานครบ ${minMonths} เดือน</span>`
                    : '';
                const isActualLeave = Number.parseInt(item.is_actual_leave ?? 1, 10) === 1;
                const actualLeaveBadge = isActualLeave
                    ? '<span class="badge bg-success">แสดงในสรุปสิทธิ์ลา</span>'
                    : '<span class="badge bg-secondary">ไม่ใช่การลา</span>';
                
                tbody.innerHTML += `
                    <tr>
                        <td>${itemId}</td>
                        <td>
                            <strong>${typeName}</strong>
                            <div class="small text-muted">${description}</div>
                        </td>
                        <td>${item.days_per_year} วัน</td>
                        <td>
                            <div class="d-flex flex-wrap gap-1">
                                ${item.requires_file == 1 ? '<span class="badge bg-warning text-dark"><i class="fas fa-file-medical"></i> ต้องมีใบรับรอง</span>' : ''}
                                ${hourlyBadge}
                                ${thresholdBadge}
                                ${minMonthsBadge}
                                ${actualLeaveBadge}
                            </div>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-warning me-1" 
                                data-bs-toggle="modal" 
                                data-bs-target="#leaveTypeModal" 
                                data-action="edit" 
                                data-info="${escapeAttr(jsonInfo)}">
                                <i class="fas fa-pencil-alt"></i>
                            </button>
                            <button class="btn btn-sm btn-danger btn-delete" data-id="${itemId}">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });
        }
    } catch (err) { console.error(err); }
}

async function handleDeleteLeaveType(id) {
    Swal.fire({
        title: 'ยืนยันการลบ?',
        text: "ข้อมูลนี้จะถูกลบถาวร",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'ลบข้อมูล'
    }).then(async (result) => {
        if (result.isConfirmed) {
            try {
                const response = await fetch('api/leave_api.php', {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id })
                });
                const res = await response.json();
                
                if (res.status === 'success') {
                    Swal.fire('สำเร็จ', 'ลบข้อมูลเรียบร้อย', 'success');
                    loadLeaveTypes();
                } else {
                    Swal.fire('ลบไม่ได้', res.message, 'error');
                }
            } catch (err) { Swal.fire('Error', err.message, 'error'); }
        }
    });
}
