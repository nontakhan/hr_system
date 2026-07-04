document.addEventListener('DOMContentLoaded', () => {
    const importForm = document.getElementById('attendanceImportForm');
    if (importForm) {
        importForm.addEventListener('submit', handleAttendanceImport);
        initAttendanceImportSummary();
    }

    const filters = document.getElementById('attendanceFilters');
    if (filters) {
        initAttendanceReport(filters.dataset.canManage === '1');
    }

    const adjustmentPage = document.getElementById('attendanceAdjustmentPage');
    if (adjustmentPage) {
        initAttendanceAdjustments();
    }
});

let attendanceCalendar = null;
let attendanceCalendarDayClassMap = {};
let attendanceImportDetailRows = [];
let attendanceImportDetailDataTable = null;
let attendanceAdjustmentRows = [];
let attendanceAdjustmentDataTable = null;
let attendanceAdjustmentFilterOptions = { companies: [], branches: [], positions: [] };
let attendanceAdjustmentSelectedEmployeeIds = new Set();

async function handleAttendanceImport(e) {
    e.preventDefault();
    const form = e.currentTarget;
    const submitBtn = form.querySelector('button[type="submit"]');
    const progress = getAttendanceImportProgressElements();
    submitBtn.disabled = true;
    setAttendanceImportProgress(progress, 0, 'กำลังเตรียมนำเข้าไฟล์', false);

    try {
        const res = await uploadAttendanceImport(new FormData(form), progress);
        if (res.status !== 'success') {
            Swal.fire('Error', res.message || 'นำเข้าไม่สำเร็จ', 'error');
            return;
        }

        setAttendanceImportProgress(progress, 100, 'นำเข้าไฟล์เสร็จสิ้น', false);
        Swal.fire(
            'นำเข้าสำเร็จ',
            `เพิ่มใหม่ ${res.inserted} รายการ, อัปเดตข้อมูลที่ขาด ${res.updated || 0} รายการ, ข้าม ${res.skipped} รายการ, ไม่พบพนักงาน ${res.unmatched} รายการ`,
            'success'
        );
        form.reset();
        loadAttendanceImportSummary();
    } catch (err) {
        Swal.fire('Error', err.message, 'error');
    } finally {
        submitBtn.disabled = false;
        setTimeout(() => hideAttendanceImportProgress(progress), 1200);
    }
}

function uploadAttendanceImport(formData, progress) {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'api/attendance_api.php');

        xhr.upload.addEventListener('progress', (event) => {
            if (!event.lengthComputable) {
                setAttendanceImportProgress(progress, 35, 'กำลังอัปโหลดไฟล์', true);
                return;
            }

            const percent = Math.max(1, Math.min(95, Math.round((event.loaded / event.total) * 95)));
            setAttendanceImportProgress(progress, percent, 'กำลังอัปโหลดไฟล์', false);
        });
        xhr.upload.addEventListener('load', () => {
            setAttendanceImportProgress(progress, 96, 'กำลังประมวลผลข้อมูล', true);
        });

        xhr.addEventListener('load', () => {
            try {
                resolve(JSON.parse(xhr.responseText));
            } catch (err) {
                reject(new Error('ไม่สามารถอ่านผลลัพธ์จากระบบได้'));
            }
        });

        xhr.addEventListener('error', () => reject(new Error('เชื่อมต่อระบบนำเข้าไม่ได้')));
        xhr.addEventListener('abort', () => reject(new Error('ยกเลิกการนำเข้าไฟล์แล้ว')));
        xhr.send(formData);
    });
}

function getAttendanceImportProgressElements() {
    return {
        wrapper: document.getElementById('attendanceImportProgress'),
        label: document.getElementById('attendanceImportProgressLabel'),
        percent: document.getElementById('attendanceImportProgressPercent'),
        bar: document.getElementById('attendanceImportProgressBar'),
    };
}

function setAttendanceImportProgress(elements, percent, label, animated) {
    if (!elements.wrapper || !elements.bar) return;

    const safePercent = Math.max(0, Math.min(100, percent));
    elements.wrapper.classList.remove('d-none');
    elements.label.textContent = label;
    elements.percent.textContent = `${safePercent}%`;
    elements.bar.style.width = `${safePercent}%`;
    elements.bar.textContent = `${safePercent}%`;
    elements.bar.classList.toggle('progress-bar-animated', animated);
}

function hideAttendanceImportProgress(elements) {
    if (!elements.wrapper || !elements.bar) return;
    elements.wrapper.classList.add('d-none');
    elements.bar.classList.remove('progress-bar-animated');
    elements.bar.style.width = '0%';
    elements.bar.textContent = '0%';
    if (elements.percent) elements.percent.textContent = '0%';
}

function initAttendanceImportSummary() {
    const refreshBtn = document.getElementById('attendanceImportSummaryRefresh');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', loadAttendanceImportSummary);
    }
    const summary = document.getElementById('attendanceImportSummary');
    if (summary) {
        summary.addEventListener('click', (event) => {
            const card = event.target.closest('[data-import-month]');
            if (!card || card.disabled) return;
            openAttendanceImportDetail(card.dataset.importMonth);
        });
    }
    const search = document.getElementById('attendanceImportDetailSearch');
    if (search) {
        search.addEventListener('input', () => {
            if (attendanceImportDetailDataTable) {
                attendanceImportDetailDataTable.search(search.value).draw();
                updateAttendanceImportDetailStatus(search.value);
                return;
            }
            renderAttendanceImportDetailRows(search.value);
        });
    }
    loadAttendanceImportSummary();
}

async function loadAttendanceImportSummary() {
    const target = document.getElementById('attendanceImportSummary');
    if (!target) return;

    target.innerHTML = '<div class="col-12 text-muted small">กำลังโหลดสรุปการนำเข้า...</div>';
    try {
        const response = await fetch('api/attendance_api.php?action=import_summary');
        const res = await response.json();
        if (res.status !== 'success') {
            target.innerHTML = `<div class="col-12 text-danger small">${res.message || 'โหลดสรุปการนำเข้าไม่สำเร็จ'}</div>`;
            return;
        }
        renderAttendanceImportSummary(res.data || []);
    } catch (err) {
        target.innerHTML = `<div class="col-12 text-danger small">${err.message}</div>`;
    }
}

function renderAttendanceImportSummaryLegacy(items) {
    const target = document.getElementById('attendanceImportSummary');
    if (!target) return;

    if (!items.length) {
        target.innerHTML = '<div class="col-12 text-muted small">ยังไม่มีข้อมูลสรุปการนำเข้า</div>';
        return;
    }

    target.innerHTML = items.map(item => {
        const hasData = Boolean(item.has_data);
        const tone = hasData ? 'success' : 'light';
        const textClass = hasData ? 'text-white' : 'text-dark';
        const borderClass = hasData ? 'border-0' : 'border';
        const icon = hasData ? 'fa-circle-check' : 'fa-circle-minus';
        const status = hasData ? 'นำเข้าแล้ว' : 'ยังไม่มีข้อมูล';
        const latest = item.latest_work_date ? formatThaiDate(item.latest_work_date) : '-';

        return `
            <div class="col-12 col-md-6 col-xl-4">
                <div class="rounded-3 p-3 bg-${tone} ${textClass} ${borderClass} h-100">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div>
                            <div class="fw-semibold">${formatThaiMonth(item.import_month)}</div>
                            <div class="small ${hasData ? 'opacity-75' : 'text-muted'}">${status}</div>
                        </div>
                        <i class="fas ${icon} fs-5 ${hasData ? 'opacity-75' : 'text-muted'}"></i>
                    </div>
                    <div class="d-flex justify-content-between gap-3 mt-3 small">
                        <span>รายการ <strong>${Number(item.record_count || 0).toLocaleString('th-TH')}</strong></span>
                        <span>พนักงาน <strong>${Number(item.employee_count || 0).toLocaleString('th-TH')}</strong></span>
                    </div>
                    <div class="small ${hasData ? 'opacity-75' : 'text-muted'} mt-2">ล่าสุด ${latest}</div>
                </div>
            </div>`;
    }).join('');
}

function renderAttendanceImportSummary(items) {
    const target = document.getElementById('attendanceImportSummary');
    if (!target) return;

    if (!items.length) {
        target.innerHTML = '<div class="col-12 text-muted small">ยังไม่มีข้อมูลสรุปการนำเข้า</div>';
        return;
    }

    target.innerHTML = items.map(item => {
        const hasData = Boolean(item.has_data);
        const tone = hasData ? 'success' : 'light';
        const textClass = hasData ? 'text-white' : 'text-dark';
        const borderClass = hasData ? 'border-0' : 'border';
        const icon = hasData ? 'fa-circle-check' : 'fa-circle-minus';
        const status = hasData ? 'นำเข้าแล้ว' : 'ยังไม่มีข้อมูล';
        const latest = item.latest_work_date ? formatThaiDate(item.latest_work_date) : '-';
        const tagName = hasData ? 'button' : 'div';
        const detailAttrs = hasData ? ` type="button" data-import-month="${escapeAttr(item.import_month)}"` : '';
        const detailHint = hasData ? '<div class="small opacity-75 mt-2"><i class="fas fa-list me-1"></i>คลิกเพื่อดูรายชื่อ</div>' : '';

        return `
            <div class="col-12 col-md-6 col-xl-4">
                <${tagName}${detailAttrs} class="attendance-import-summary-card rounded-3 p-3 bg-${tone} ${textClass} ${borderClass} h-100 w-100 text-start">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div>
                            <div class="fw-semibold">${formatThaiMonth(item.import_month)}</div>
                            <div class="small ${hasData ? 'opacity-75' : 'text-muted'}">${status}</div>
                        </div>
                        <i class="fas ${icon} fs-5 ${hasData ? 'opacity-75' : 'text-muted'}"></i>
                    </div>
                    <div class="d-flex justify-content-between gap-3 mt-3 small">
                        <span>รายการ <strong>${Number(item.record_count || 0).toLocaleString('th-TH')}</strong></span>
                        <span>พนักงาน <strong>${Number(item.employee_count || 0).toLocaleString('th-TH')}</strong></span>
                    </div>
                    <div class="small ${hasData ? 'opacity-75' : 'text-muted'} mt-2">ล่าสุด ${latest}</div>
                    ${detailHint}
                </${tagName}>
            </div>`;
    }).join('');
}

async function openAttendanceImportDetail(month) {
    const modalEl = document.getElementById('attendanceImportDetailModal');
    const title = document.getElementById('attendanceImportDetailTitle');
    const subtitle = document.getElementById('attendanceImportDetailSubtitle');
    const status = document.getElementById('attendanceImportDetailStatus');
    const rows = document.getElementById('attendanceImportDetailRows');
    const search = document.getElementById('attendanceImportDetailSearch');
    if (!modalEl || !month) return;

    resetAttendanceImportDetailDataTable();
    attendanceImportDetailRows = [];
    if (title) title.textContent = 'รายชื่อพนักงานที่มีข้อมูลนำเข้า';
    if (subtitle) subtitle.textContent = formatThaiMonth(month);
    if (search) search.value = '';
    if (status) status.textContent = 'กำลังโหลดรายชื่อ...';
    if (rows) {
        rows.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">กำลังโหลดรายชื่อ...</td></tr>';
    }

    bootstrap.Modal.getOrCreateInstance(modalEl).show();

    try {
        const response = await fetch(`api/attendance_api.php?action=import_summary_detail&month=${encodeURIComponent(month)}`);
        const res = await response.json();
        if (res.status !== 'success') {
            throw new Error(res.message || 'โหลดรายชื่อไม่สำเร็จ');
        }
        attendanceImportDetailRows = res.data || [];
        renderAttendanceImportDetailRows('');
    } catch (err) {
        if (status) status.textContent = '';
        if (rows) {
            rows.innerHTML = `<tr><td colspan="7" class="text-center text-danger py-4">${escapeHtml(err.message)}</td></tr>`;
        }
    }
}

function renderAttendanceImportDetailRows(query) {
    const status = document.getElementById('attendanceImportDetailStatus');
    const rows = document.getElementById('attendanceImportDetailRows');
    if (!rows) return;

    resetAttendanceImportDetailDataTable();
    const keyword = String(query || '').trim().toLowerCase();
    const filtered = attendanceImportDetailRows.filter(item => {
        const text = [
            item.full_name,
            item.first_name_th,
            item.last_name_th,
            item.position_name_th,
            item.branch_name_th,
            item.company_name_th,
            item.citizen_id,
            item.record_count,
            item.first_work_date,
            item.latest_work_date,
        ].join(' ').toLowerCase();
        return !keyword || text.includes(keyword);
    });

    if (status) {
        const total = attendanceImportDetailRows.length.toLocaleString('th-TH');
        const shown = filtered.length.toLocaleString('th-TH');
        status.textContent = keyword ? `พบ ${shown} จาก ${total} คน` : `ทั้งหมด ${total} คน`;
    }

    if (!filtered.length) {
        rows.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">ไม่พบรายชื่อที่ตรงกับคำค้นหา</td></tr>';
        return;
    }

    rows.innerHTML = filtered.map(buildAttendanceImportDetailRowHtml).join('');
    initAttendanceImportDetailDataTable(filtered.length);
}

function buildAttendanceImportDetailRowHtml(item) {
    const firstDate = item.first_work_date ? formatThaiDate(item.first_work_date) : '-';
    const latestDate = item.latest_work_date ? formatThaiDate(item.latest_work_date) : '-';
    const dateRange = firstDate === latestDate ? latestDate : `${firstDate} - ${latestDate}`;
    return `
        <tr>
            <td><div class="fw-semibold">${escapeHtml(item.full_name || '-')}</div></td>
            <td>${escapeHtml(item.position_name_th || '-')}</td>
            <td>${escapeHtml(item.branch_name_th || '-')}</td>
            <td>${escapeHtml(item.company_name_th || '-')}</td>
            <td>${escapeHtml(item.citizen_id || '-')}</td>
            <td class="text-end">${Number(item.record_count || 0).toLocaleString('th-TH')}</td>
            <td>${escapeHtml(dateRange)}</td>
        </tr>`;
}

function buildAttendanceAdjustmentEmployeeRowHtml(row) {
    const fullName = `${row.first_name_th || ''} ${row.last_name_th || ''}`.trim() || '-';
    const rawIn = formatAttendanceTime(row.raw_check_in);
    const rawOut = formatAttendanceTime(row.raw_check_out);
    const overrideIn = formatAttendanceTime(row.override_check_in);
    const overrideOut = formatAttendanceTime(row.override_check_out);
    const checked = attendanceAdjustmentSelectedEmployeeIds.has(String(row.employee_id)) ? ' checked' : '';
    const reason = row.override_reason ? `<div class="small text-primary mt-1">เหตุผล: ${escapeHtml(row.override_reason)}</div>` : '';
    return `
        <tr>
            <td><input type="checkbox" class="attendance-adjustment-select" value="${escapeAttr(row.employee_id)}"${checked}></td>
            <td>
                <div class="fw-semibold">${escapeHtml(fullName)}</div>
                <div class="small text-muted">${escapeHtml(row.citizen_id || '-')}</div>
            </td>
            <td>${escapeHtml(row.position_name_th || '-')}</td>
            <td>${escapeHtml(row.branch_name_th || '-')}</td>
            <td>${escapeHtml(row.company_name_th || '-')}</td>
            <td>
                <div class="small">สแกน: ${rawIn} - ${rawOut}</div>
                <div class="small">ปรับแก้: ${overrideIn} - ${overrideOut}</div>
                ${reason}
            </td>
        </tr>`;
}

function initAttendanceAdjustments() {
    if (typeof $ !== 'undefined' && $.fn.select2) {
        $('.attendance-select2').select2({ width: '100%', allowClear: true });
    }

    const today = new Date().toISOString().slice(0, 10);
    const singleDate = document.getElementById('attendanceSingleDate');
    const bulkDate = document.getElementById('attendanceBulkDate');
    if (singleDate && !singleDate.value) singleDate.value = today;
    if (bulkDate && !bulkDate.value) bulkDate.value = today;

    const loadBtn = document.getElementById('attendanceAdjustmentLoadBtn');
    if (loadBtn) loadBtn.addEventListener('click', loadAttendanceAdjustmentEmployees);

    const rowsEl = document.getElementById('attendanceAdjustmentRows');
    if (rowsEl) {
        rowsEl.addEventListener('change', (event) => {
            if (!event.target.classList.contains('attendance-adjustment-select')) return;
            setAttendanceAdjustmentEmployeeSelected(event.target.value, event.target.checked);
        });
    }

    bindAttendanceAdjustmentFilterChange('attendanceAdjustmentCompany', () => {
        updateAttendanceAdjustmentBranchOptions();
        loadAttendanceAdjustmentEmployees();
    });
    bindAttendanceAdjustmentFilterChange('attendanceAdjustmentBranch', loadAttendanceAdjustmentEmployees);
    bindAttendanceAdjustmentFilterChange('attendanceAdjustmentPosition', loadAttendanceAdjustmentEmployees);

    bindAttendanceAdjustmentSelectAll();

    const singleForm = document.getElementById('attendanceSingleSaveForm');
    if (singleForm) {
        singleForm.addEventListener('submit', (event) => saveAttendanceAdjustmentForm(event, false));
    }

    const bulkForm = document.getElementById('attendanceBulkSaveForm');
    if (bulkForm) {
        bulkForm.addEventListener('submit', (event) => saveAttendanceAdjustmentForm(event, true));
    }

    loadAttendanceAdjustmentFilterOptions();
}

function bindAttendanceAdjustmentFilterChange(id, handler) {
    const select = document.getElementById(id);
    if (!select) return;
    select.addEventListener('change', handler);
    if (typeof $ !== 'undefined' && $.fn.select2) {
        $(select)
            .off('select2:select.attendanceAdjustment select2:clear.attendanceAdjustment')
            .on('select2:select.attendanceAdjustment select2:clear.attendanceAdjustment', handler);
    }
}

function bindAttendanceAdjustmentSelectAll() {
    const selectAll = document.getElementById('attendanceAdjustmentSelectAll');
    if (!selectAll) return;
    const selectAllCell = selectAll.closest ? selectAll.closest('.attendance-adjustment-select-all-cell') : null;

    selectAll.addEventListener('click', (event) => {
        event.stopPropagation();
    });
    selectAll.addEventListener('change', (event) => {
        event.stopPropagation();
        setAllAttendanceAdjustmentEmployeesSelected(selectAll.checked);
    });

    if (selectAllCell) {
        selectAllCell.addEventListener('click', (event) => {
            if (event.target === selectAll) return;
            event.preventDefault();
            event.stopPropagation();
            selectAll.checked = !selectAll.checked;
            setAllAttendanceAdjustmentEmployeesSelected(selectAll.checked);
        });
    }
}

async function loadAttendanceAdjustmentFilterOptions() {
    try {
        const response = await fetch('api/attendance_api.php?action=adjustment_filter_options');
        const res = await response.json();
        if (res.status !== 'success') return;
        attendanceAdjustmentFilterOptions = {
            companies: res.data?.companies || [],
            branches: res.data?.branches || [],
            positions: res.data?.positions || [],
        };

        fillAttendanceAdjustmentSelect('attendanceAdjustmentCompany', res.data?.companies || [], 'บริษัททั้งหมด');
        fillAttendanceAdjustmentSelect('attendanceAdjustmentBranch', res.data?.branches || [], 'สาขาทั้งหมด');
        fillAttendanceAdjustmentSelect('attendanceAdjustmentPosition', res.data?.positions || [], 'ตำแหน่งทั้งหมด');
        updateAttendanceAdjustmentBranchOptions();
    } catch (err) {
        console.warn(err);
    }
}

function buildAttendanceBranchOptionsForCompany(branches, companyId) {
    const selectedCompanyId = String(companyId || '');
    if (!selectedCompanyId) return [];
    return (branches || []).filter(branch => String(branch.company_id || '') === selectedCompanyId);
}

function updateAttendanceAdjustmentBranchOptions() {
    const companyId = document.getElementById('attendanceAdjustmentCompany')?.value || '';
    const branchSelect = document.getElementById('attendanceAdjustmentBranch');
    if (branchSelect && typeof $ !== 'undefined') {
        $(branchSelect).prop('disabled', !companyId);
    } else if (branchSelect) {
        branchSelect.disabled = !companyId;
    }
    fillAttendanceAdjustmentSelect(
        'attendanceAdjustmentBranch',
        buildAttendanceBranchOptionsForCompany(attendanceAdjustmentFilterOptions.branches, companyId),
        companyId ? 'สาขาทั้งหมดในบริษัท' : 'สาขาทั้งหมด'
    );
    if (branchSelect) {
        if (!companyId) branchSelect.value = '';
        refreshAttendanceAdjustmentSelect2(branchSelect);
    }
}

function refreshAttendanceAdjustmentSelect2(select) {
    if (typeof $ === 'undefined' || !$.fn.select2 || !select) return;
    const $select = $(select);
    if ($select.hasClass('select2-hidden-accessible')) {
        $select.select2('destroy');
    }
    $select.select2({ width: '100%', allowClear: true });
    $select.trigger('change.select2');
}

function fillAttendanceAdjustmentSelect(id, rows, placeholder) {
    const select = document.getElementById(id);
    if (!select) return;
    const currentValue = select.value;
    select.innerHTML = `<option value="">${escapeHtml(placeholder)}</option>` + rows.map(row => (
        `<option value="${escapeAttr(row.id)}">${escapeHtml(row.label || '-')}</option>`
    )).join('');
    select.value = rows.some(row => String(row.id) === currentValue) ? currentValue : '';
    if (typeof $ !== 'undefined') {
        $(select).trigger('change.select2');
    }
}

async function loadAttendanceAdjustmentEmployees() {
    const rowsEl = document.getElementById('attendanceAdjustmentRows');
    const workDate = document.getElementById('attendanceBulkDate')?.value || new Date().toISOString().slice(0, 10);
    const params = new URLSearchParams({
        action: 'adjustment_employees',
        work_date: workDate,
        search: document.getElementById('attendanceAdjustmentSearch')?.value || '',
        company_id: document.getElementById('attendanceAdjustmentCompany')?.value || '',
        branch_id: document.getElementById('attendanceAdjustmentBranch')?.value || '',
        position_id: document.getElementById('attendanceAdjustmentPosition')?.value || '',
    });

    if (rowsEl) {
        rowsEl.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">กำลังโหลดรายชื่อ...</td></tr>';
    }

    try {
        const response = await fetch(`api/attendance_api.php?${params.toString()}`);
        const res = await response.json();
        if (res.status !== 'success') {
            if (rowsEl) rowsEl.innerHTML = `<tr><td colspan="6" class="text-center text-danger py-4">${escapeHtml(res.message || 'โหลดรายชื่อไม่สำเร็จ')}</td></tr>`;
            return;
        }

        attendanceAdjustmentRows = res.data || [];
        resetAttendanceAdjustmentSelectedEmployeeIds();
        renderAttendanceAdjustmentEmployees(attendanceAdjustmentRows);
        syncAttendanceSingleEmployeeOptions(attendanceAdjustmentRows);
    } catch (err) {
        if (rowsEl) rowsEl.innerHTML = `<tr><td colspan="6" class="text-center text-danger py-4">${escapeHtml(err.message)}</td></tr>`;
    }
}

function renderAttendanceAdjustmentEmployees(rows) {
    const rowsEl = document.getElementById('attendanceAdjustmentRows');
    if (!rowsEl) return;
    if (attendanceAdjustmentDataTable) {
        attendanceAdjustmentDataTable.destroy();
        attendanceAdjustmentDataTable = null;
    }
    if (!rows.length) {
        rowsEl.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">ไม่พบพนักงาน</td></tr>';
        return;
    }
    rowsEl.innerHTML = rows.map(buildAttendanceAdjustmentEmployeeRowHtml).join('');
    const table = typeof $ !== 'undefined' ? $('#attendanceAdjustmentTable') : null;
    if (rows.length > 10 && table && table.length && $.fn.DataTable) {
        attendanceAdjustmentDataTable = table.DataTable({
            pageLength: 10,
            order: [],
            columnDefs: [
                { targets: 0, orderable: false, searchable: false },
            ],
            language: { search: 'ค้นหา:', lengthMenu: 'แสดง _MENU_ รายการ', info: 'แสดง _START_ ถึง _END_ จาก _TOTAL_ รายการ' },
        });
        attendanceAdjustmentDataTable.on('draw', syncAttendanceAdjustmentRenderedCheckboxes);
    }
    syncAttendanceAdjustmentRenderedCheckboxes();
}

function syncAttendanceSingleEmployeeOptions(rows) {
    const select = document.getElementById('attendanceSingleEmployee');
    if (!select) return;
    const currentValue = select.value;
    select.innerHTML = '<option value="">เลือกพนักงาน</option>' + rows.map(row => {
        const fullName = `${row.first_name_th || ''} ${row.last_name_th || ''}`.trim() || '-';
        return `<option value="${escapeAttr(row.employee_id)}">${escapeHtml(fullName)}</option>`;
    }).join('');
    select.value = currentValue;
    if (typeof $ !== 'undefined') {
        $(select).trigger('change.select2');
    }
}

function resetAttendanceAdjustmentSelectedEmployeeIds() {
    attendanceAdjustmentSelectedEmployeeIds = new Set();
    if (typeof document.getElementById === 'function') {
        const selectAll = document.getElementById('attendanceAdjustmentSelectAll');
        if (selectAll) selectAll.checked = false;
    }
}

function setAttendanceAdjustmentEmployeeSelected(employeeId, selected) {
    const id = String(employeeId || '');
    if (!id) return;
    if (selected) {
        attendanceAdjustmentSelectedEmployeeIds.add(id);
    } else {
        attendanceAdjustmentSelectedEmployeeIds.delete(id);
    }
}

function setAllAttendanceAdjustmentEmployeesSelected(selected) {
    getVisibleAttendanceAdjustmentEmployeeIds().forEach(employeeId => {
        setAttendanceAdjustmentEmployeeSelected(employeeId, selected);
    });
    syncAttendanceAdjustmentRenderedCheckboxes();
}

function getVisibleAttendanceAdjustmentEmployeeIds() {
    if (attendanceAdjustmentDataTable && typeof attendanceAdjustmentDataTable.rows === 'function') {
        const nodes = attendanceAdjustmentDataTable.rows({ search: 'applied', page: 'all' }).nodes();
        return Array.from(nodes).map(row => {
            const input = row.querySelector ? row.querySelector('.attendance-adjustment-select') : null;
            return input ? String(input.value || '') : '';
        }).filter(Boolean);
    }
    return attendanceAdjustmentRows.map(row => String(row.employee_id || '')).filter(Boolean);
}

function syncAttendanceAdjustmentRenderedCheckboxes() {
    document.querySelectorAll('.attendance-adjustment-select').forEach(input => {
        input.checked = attendanceAdjustmentSelectedEmployeeIds.has(String(input.value));
    });
    updateAttendanceAdjustmentSelectAllState();
}

function updateAttendanceAdjustmentSelectAllState() {
    const selectAll = document.getElementById('attendanceAdjustmentSelectAll');
    if (!selectAll) return;
    const visibleIds = getVisibleAttendanceAdjustmentEmployeeIds();
    const total = visibleIds.length;
    const selected = visibleIds.filter(employeeId => attendanceAdjustmentSelectedEmployeeIds.has(String(employeeId))).length;
    selectAll.checked = total > 0 && selected === total;
    selectAll.indeterminate = selected > 0 && selected < total;
}

function resetAttendanceAdjustmentForm(form, isBulk) {
    form.querySelectorAll('input[name="override_check_in"], input[name="override_check_out"], input[name="reason"], textarea[name="reason"]').forEach(input => {
        input.value = '';
    });
    if (isBulk) {
        resetAttendanceAdjustmentSelectedEmployeeIds();
        document.querySelectorAll('.attendance-adjustment-select').forEach(input => {
            input.checked = false;
        });
    }
}

function getSelectedAttendanceAdjustmentEmployeeIds() {
    return Array.from(attendanceAdjustmentSelectedEmployeeIds);
}

async function saveAttendanceAdjustmentForm(event, isBulk) {
    event.preventDefault();
    const form = event.currentTarget;
    const formData = new FormData(form);
    formData.set('action', isBulk ? 'save_bulk_adjustments' : 'save_adjustment');

    if (isBulk) {
        const workDate = document.getElementById('attendanceBulkDate')?.value || '';
        const employeeIds = getSelectedAttendanceAdjustmentEmployeeIds();
        formData.set('work_date', workDate);
        employeeIds.forEach(id => formData.append('employee_ids[]', id));
    }

    try {
        const response = await fetch('api/attendance_api.php', { method: 'POST', body: formData });
        const res = await response.json();
        if (res.status !== 'success') {
            Swal.fire('Error', res.message || 'บันทึกไม่สำเร็จ', 'error');
            return;
        }

        Swal.fire('สำเร็จ', `บันทึก ${Number(res.saved || 0).toLocaleString('th-TH')} รายการ`, 'success');
        resetAttendanceAdjustmentForm(form, isBulk);
        loadAttendanceAdjustmentEmployees();
    } catch (err) {
        Swal.fire('Error', err.message, 'error');
    }
}

function resetAttendanceImportDetailDataTable() {
    const table = document.getElementById('attendanceImportDetailTable');
    if (attendanceImportDetailDataTable) {
        attendanceImportDetailDataTable.destroy();
        attendanceImportDetailDataTable = null;
    } else if (window.jQuery && table && $.fn.DataTable.isDataTable(table)) {
        $(table).DataTable().destroy();
    }
}

function initAttendanceImportDetailDataTable(rowCount) {
    const table = document.getElementById('attendanceImportDetailTable');
    const search = document.getElementById('attendanceImportDetailSearch');
    if (!table || rowCount <= 10 || !window.jQuery || !$.fn.DataTable) return;

    attendanceImportDetailDataTable = $(table).DataTable({
        language: {
            lengthMenu: 'แสดง _MENU_ รายการ ต่อหน้า',
            zeroRecords: 'ไม่พบข้อมูลที่ตรงกัน',
            info: 'แสดง _START_ ถึง _END_ จากทั้งหมด _TOTAL_ รายการ',
            infoEmpty: 'แสดง 0 ถึง 0 จากทั้งหมด 0 รายการ',
            infoFiltered: '(กรองจากทั้งหมด _MAX_ รายการ)',
            search: 'ค้นหา:',
            paginate: { first: 'หน้าแรก', last: 'สุดท้าย', next: 'ถัดไป', previous: 'ก่อนหน้า' }
        },
        order: [[0, 'asc']],
        pageLength: 10,
        deferRender: true,
        autoWidth: false,
        dom: 'lrtip',
    });

    if (search && search.value) {
        attendanceImportDetailDataTable.search(search.value).draw();
    }
}

function updateAttendanceImportDetailStatus(query) {
    const status = document.getElementById('attendanceImportDetailStatus');
    if (!status || !attendanceImportDetailDataTable) return;
    const keyword = String(query || '').trim();
    const total = attendanceImportDetailRows.length.toLocaleString('th-TH');
    const shown = attendanceImportDetailDataTable.rows({ filter: 'applied' }).count().toLocaleString('th-TH');
    status.textContent = keyword ? `พบ ${shown} จาก ${total} คน` : `ทั้งหมด ${total} คน`;
}

async function initAttendanceReport(canManage) {
    const employeeSelect = document.getElementById('attendanceEmployee');
    const monthStartSelect = document.getElementById('attendanceMonthStart');
    const monthEndSelect = document.getElementById('attendanceMonthEnd');
    const loadBtn = document.getElementById('attendanceLoadBtn');

    if (canManage) {
        await loadAttendanceEmployees(employeeSelect);
    } else {
        await loadAttendanceMonths([monthStartSelect, monthEndSelect], '');
    }
    initializeAttendanceSelect2();
    if (canManage) {
        bindAttendanceEmployeeChange(employeeSelect, [monthStartSelect, monthEndSelect]);
    }

    loadBtn.addEventListener('click', () => {
        const employeeId = canManage ? employeeSelect.value : '';
        loadAttendanceReport(employeeId, monthStartSelect.value, monthEndSelect.value);
    });
}

function bindAttendanceEmployeeChange(employeeSelect, monthSelects) {
    const loadSelectedEmployeeMonths = () => loadAttendanceMonths(monthSelects, employeeSelect.value);
    employeeSelect.addEventListener('change', loadSelectedEmployeeMonths);

    if (typeof $ !== 'undefined' && typeof $.fn.select2 === 'function') {
        $(employeeSelect)
            .off('select2:select.attendance select2:clear.attendance')
            .on('select2:select.attendance select2:clear.attendance', loadSelectedEmployeeMonths);
    }

    if (employeeSelect.value) {
        loadSelectedEmployeeMonths();
    }
}

async function loadAttendanceEmployees(select) {
    const response = await fetch('api/attendance_api.php?action=employees');
    const res = await response.json();
    if (res.status !== 'success') {
        select.innerHTML = '<option value="">โหลดรายชื่อพนักงานไม่สำเร็จ</option>';
        initializeAttendanceSelect2(select);
        return;
    }

    select.innerHTML = '<option value="">เลือกพนักงาน</option>';
    if (!res.data.length) {
        select.innerHTML = '<option value="">ไม่พบข้อมูลพนักงาน</option>';
    }
    res.data.forEach(emp => {
        select.innerHTML += `<option value="${emp.id}">${emp.first_name_th} ${emp.last_name_th} (${emp.citizen_id})</option>`;
    });
    initializeAttendanceSelect2(select);
}

async function loadAttendanceMonths(selects, employeeId) {
    const monthSelects = Array.isArray(selects) ? selects : [selects];
    monthSelects.forEach(select => {
        select.innerHTML = '<option value="">กำลังโหลด...</option>';
        initializeAttendanceSelect2(select);
    });
    const url = new URL('api/attendance_api.php', window.location.href);
    url.searchParams.set('action', 'months');
    if (employeeId) url.searchParams.set('employee_id', employeeId);

    const response = await fetch(url);
    const res = await response.json();
    if (res.status !== 'success') {
        monthSelects.forEach(select => {
            select.innerHTML = '<option value="">โหลดเดือนไม่สำเร็จ</option>';
            initializeAttendanceSelect2(select);
        });
        return;
    }

    monthSelects.forEach((select, index) => {
        renderAttendanceMonthOptions(select, res.data || [], index === 1);
        initializeAttendanceSelect2(select);
    });
}

function renderAttendanceMonthOptions(select, items, allowSingleMonthBlank) {
    select.innerHTML = allowSingleMonthBlank
        ? '<option value="">เดือนเดียว</option>'
        : '<option value="">เลือกเดือน</option>';

    if (!items.length) {
        select.innerHTML = '<option value="">ยังไม่มีข้อมูลเดือน</option>';
        return;
    }

    items.forEach(item => {
        select.innerHTML += `<option value="${item.import_month}">${formatThaiMonth(item.import_month)}</option>`;
    });
}

function initializeAttendanceSelect2(target) {
    if (typeof $ === 'undefined' || typeof $.fn.select2 !== 'function') return;

    const $elements = target ? $(target) : $('.attendance-select2');
    $elements.each(function () {
        const placeholder = this.dataset.placeholder || 'เลือกข้อมูล';
        if ($(this).hasClass('select2-hidden-accessible')) {
            $(this).select2('destroy');
        }
        $(this).select2({
            width: '100%',
            placeholder,
            allowClear: true
        });
    });
}

function normalizeAttendanceRangeEnd(endMonth, startMonth) {
    return endMonth || startMonth;
}

function isAttendanceRangeValid(startMonth, endMonth) {
    return /^\d{4}-\d{2}$/.test(startMonth || '')
        && /^\d{4}-\d{2}$/.test(endMonth || '')
        && startMonth <= endMonth
        && countAttendanceRangeMonths(startMonth, endMonth) <= 12;
}

function countAttendanceRangeMonths(startMonth, endMonth) {
    const [startYear, startIndex] = startMonth.split('-').map(Number);
    const [endYear, endIndex] = endMonth.split('-').map(Number);
    return ((endYear - startYear) * 12) + (endIndex - startIndex) + 1;
}

function formatAttendanceReportRangeLabel(res) {
    const startMonth = res.start_month || res.month;
    const endMonth = res.end_month || res.month;
    if (!startMonth || startMonth === endMonth) {
        return formatThaiMonth(startMonth || '');
    }
    return `${formatThaiMonth(startMonth)} - ${formatThaiMonth(endMonth)}`;
}

async function loadAttendanceReport(employeeId, startMonth, endMonth) {
    if (!startMonth) {
        Swal.fire('แจ้งเตือน', 'กรุณาเลือกเดือนเริ่มต้น', 'warning');
        return;
    }

    const normalizedEndMonth = normalizeAttendanceRangeEnd(endMonth, startMonth);
    if (!isAttendanceRangeValid(startMonth, normalizedEndMonth)) {
        Swal.fire('แจ้งเตือน', 'กรุณาเลือกช่วงเดือนไม่เกิน 12 เดือน และเดือนสิ้นสุดต้องไม่ก่อนเดือนเริ่มต้น', 'warning');
        return;
    }

    const url = new URL('api/attendance_api.php', window.location.href);
    url.searchParams.set('action', startMonth === normalizedEndMonth ? 'report' : 'report_range');
    if (startMonth === normalizedEndMonth) {
        url.searchParams.set('month', startMonth);
    } else {
        url.searchParams.set('start_month', startMonth);
        url.searchParams.set('end_month', normalizedEndMonth);
    }
    if (employeeId) url.searchParams.set('employee_id', employeeId);

    setAttendanceReportLoading(true);
    try {
        const response = await fetch(url);
        const res = await response.json();
        if (res.status !== 'success') {
            Swal.fire('Error', res.message || 'โหลดข้อมูลไม่สำเร็จ', 'error');
            return;
        }

        renderAttendanceReport(res);
    } catch (err) {
        Swal.fire('Error', err.message || 'โหลดข้อมูลไม่สำเร็จ', 'error');
    } finally {
        setAttendanceReportLoading(false);
    }
}

function setAttendanceReportLoading(isLoading) {
    const summary = document.getElementById('attendanceSummary');
    const emptyEl = document.getElementById('attendanceCalendarEmpty');
    const loadBtn = document.getElementById('attendanceLoadBtn');

    if (loadBtn) {
        loadBtn.disabled = isLoading;
        loadBtn.innerHTML = isLoading
            ? '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> กำลังโหลด'
            : '<i class="fas fa-search me-1"></i> แสดงข้อมูล';
    }
    if (!isLoading) return;

    if (attendanceCalendar && typeof attendanceCalendar.destroy === 'function') {
        attendanceCalendar.destroy();
        attendanceCalendar = null;
    }
    if (summary) summary.innerHTML = buildAttendanceReportLoadingHtml();
    if (emptyEl) {
        emptyEl.classList.remove('d-none');
        emptyEl.innerHTML = buildAttendanceReportLoadingHtml();
    }
}

function buildAttendanceReportLoadingHtml() {
    return `
        <div class="attendance-report-loading text-muted">
            <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
            กำลังโหลดข้อมูลการมาทำงาน...
        </div>`;
}

function renderAttendanceReport(res) {
    const summary = document.getElementById('attendanceSummary');
    const counts = countAttendanceReportStatuses(res.data || []);
    const rangeLabel = formatAttendanceReportRangeLabel(res);

    const holidayTotal = (counts.regular_holiday || 0) + (counts.company_holiday || 0);
    const workdayTotal = res.data.length - holidayTotal;
    const incompleteTotal = (counts.missing_in || 0) + (counts.missing_out || 0);
    summary.innerHTML = `
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <div class="fw-bold fs-5">${res.employee.first_name_th} ${res.employee.last_name_th}</div>
                <div class="text-muted small">${rangeLabel} | วันทำงาน ${workdayTotal} วัน</div>
            </div>
            <span class="badge bg-light text-dark border">รวม ${res.data.length} วัน</span>
        </div>
        <div class="row g-3">
            ${attendanceSummaryCard('ปกติ', counts.present || 0, 'success', 'fa-check-circle')}
            ${attendanceSummaryCard('สาย', counts.late || 0, 'attendance-late', 'fa-clock')}
            ${attendanceSummaryCard('ขาด', counts.absent || 0, 'danger', 'fa-circle-xmark')}
            ${attendanceSummaryCard('สแกนไม่ครบ', incompleteTotal, 'attendance-incomplete', 'fa-triangle-exclamation')}
            ${attendanceSummaryCard('ลา', counts.leave || 0, 'info', 'fa-file-signature')}
            ${attendanceSummaryCard('กิจกรรม', counts.training || 0, 'attendance-training', 'fa-people-arrows')}
            ${attendanceSummaryCard('วันหยุดปกติ', counts.regular_holiday || 0, 'attendance-holiday', 'fa-calendar-day')}
            ${attendanceSummaryCard('วันหยุดบริษัท', counts.company_holiday || 0, 'attendance-company-holiday', 'fa-building-circle-check')}
        </div>`;

    renderAttendanceCalendar(res);
}

function countAttendanceReportStatuses(rows) {
    const counts = { present: 0, late: 0, absent: 0, missing_in: 0, missing_out: 0, holiday: 0, regular_holiday: 0, company_holiday: 0, leave: 0, training: 0 };

    rows.forEach(row => {
        counts[row.status] = (counts[row.status] || 0) + 1;
        if (row.training_name && row.status !== 'training') {
            counts.training += 1;
        }
        if (row.status === 'holiday') {
            if (String(row.holiday_name || '').trim()) {
                counts.company_holiday += 1;
            } else {
                counts.regular_holiday += 1;
            }
        }
    });

    return counts;
}

function attendanceSummaryCard(label, count, tone, icon) {
    const customTones = {
        'attendance-late': ['attendance-summary-card-late', ''],
        'attendance-incomplete': ['attendance-summary-card-incomplete', ''],
        'attendance-holiday': ['attendance-summary-card-holiday', ''],
        'attendance-company-holiday': ['attendance-summary-card-company-holiday', ''],
        'attendance-training': ['attendance-summary-card-training', ''],
    };
    if (customTones[tone]) {
        const [cardClass] = customTones[tone];
        return `
        <div class="col-6 col-md">
            <div class="rounded-3 p-3 ${cardClass} h-100">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <div>
                        <div class="small opacity-75">${label}</div>
                        <div class="fs-3 fw-bold lh-1 mt-1">${count}</div>
                        <div class="small opacity-75 mt-1">วัน</div>
                    </div>
                    <i class="fas ${icon} fs-5 opacity-75"></i>
                </div>
            </div>
        </div>`;
    }

    const textClass = tone === 'warning' || tone === 'light' || tone === 'info' ? 'text-dark' : 'text-white';
    const borderClass = tone === 'light' ? 'border' : 'border-0';
    return `
        <div class="col-6 col-md">
            <div class="rounded-3 p-3 bg-${tone} ${textClass} ${borderClass} h-100">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <div>
                        <div class="small ${tone === 'light' ? 'text-muted' : 'opacity-75'}">${label}</div>
                        <div class="fs-3 fw-bold lh-1 mt-1">${count}</div>
                        <div class="small ${tone === 'light' ? 'text-muted' : 'opacity-75'} mt-1">วัน</div>
                    </div>
                    <i class="fas ${icon} fs-5 ${tone === 'light' ? 'text-muted' : 'opacity-75'}"></i>
                </div>
            </div>
        </div>`;
}

function attendanceStatusBadge(status, label) {
    const classes = {
        present: 'bg-success',
        late: 'bg-warning text-dark',
        absent: 'bg-danger',
        missing_in: 'bg-secondary',
        missing_out: 'bg-secondary',
        holiday: 'bg-light text-dark border',
        leave: 'bg-info text-dark',
        training: 'bg-primary',
    };
    return `<span class="badge ${classes[status] || 'bg-secondary'}">${label}</span>`;
}

function renderAttendanceCalendar(res) {
    const calendarEl = document.getElementById('attendanceCalendar');
    const emptyEl = document.getElementById('attendanceCalendarEmpty');
    if (!calendarEl || typeof FullCalendar === 'undefined') return;

    if (emptyEl) emptyEl.classList.add('d-none');

    attendanceCalendarDayClassMap = buildAttendanceCalendarDayClassMap(res.data || []);
    const events = (res.data || []).map(buildAttendanceCalendarEvent);
    const startMonth = res.start_month || res.month;
    const endMonth = res.end_month || res.month;
    const monthCount = countAttendanceRangeMonths(startMonth, endMonth);
    if (attendanceCalendar) {
        attendanceCalendar.destroy();
    }

    attendanceCalendar = new FullCalendar.Calendar(calendarEl, buildAttendanceCalendarOptions(`${startMonth}-01`, events, monthCount));
    attendanceCalendar.render();
}

function buildAttendanceCalendarOptions(initialDate, events, monthCount = 1) {
    const safeMonthCount = Math.max(1, Math.min(12, Number(monthCount) || 1));
    const useMultiMonth = safeMonthCount > 1;
    return {
        initialView: useMultiMonth ? 'multiMonth' : 'dayGridMonth',
        initialDate,
        duration: useMultiMonth ? { months: safeMonthCount } : undefined,
        locale: 'th',
        firstDay: 1,
        height: 'auto',
        fixedWeekCount: false,
        multiMonthMaxColumns: Math.min(3, safeMonthCount),
        visibleRange: useMultiMonth ? () => {
            const start = new Date(`${initialDate}T00:00:00`);
            const end = new Date(start);
            end.setMonth(end.getMonth() + safeMonthCount);
            return { start, end };
        } : undefined,
        headerToolbar: {
            left: '',
            center: 'title',
            right: '',
        },
        events: events || [],
        dayMaxEvents: true,
        dayCellClassNames(info) {
            const status = attendanceCalendarDayClassMap[formatAttendanceDateKey(info.date)];
            return status ? [`attendance-day-${status}`] : [];
        },
        eventClick(info) {
            const row = info.event.extendedProps.row;
            Swal.fire({
                title: formatThaiDate(row.work_date),
                html: buildAttendanceCalendarDetails(row),
                icon: row.status === 'absent' ? 'warning' : 'info',
                confirmButtonText: 'ปิด',
            });
        },
    };
}

function buildAttendanceCalendarDayClassMap(rows) {
    return rows.reduce((map, row) => {
        if (row.work_date && row.status) {
            map[row.work_date] = attendanceCalendarPresentationStatus(row);
        }
        return map;
    }, {});
}

function formatAttendanceDateKey(date) {
    return [
        date.getFullYear(),
        String(date.getMonth() + 1).padStart(2, '0'),
        String(date.getDate()).padStart(2, '0'),
    ].join('-');
}

function buildAttendanceCalendarEvent(row) {
    const presentationStatus = attendanceCalendarPresentationStatus(row);
    const colors = attendanceCalendarStatusColor(presentationStatus);
    return {
        title: attendanceCalendarEventTitle(row),
        start: row.work_date,
        allDay: true,
        backgroundColor: colors.background,
        borderColor: colors.border,
        textColor: colors.text,
        classNames: [`attendance-event-${presentationStatus || 'unknown'}`],
        extendedProps: { row },
    };
}

function attendanceCalendarPresentationStatus(row) {
    if (row.status === 'holiday' && String(row.holiday_name || '').trim()) {
        return 'company_holiday';
    }
    return row.status || 'unknown';
}

function attendanceCalendarEventTitle(row) {
    const status = attendanceCalendarPresentationStatus(row);
    let title = row.status_label || '-';
    if (status === 'company_holiday') title = 'วันหยุดบริษัท';
    if (status === 'holiday') title = 'วันหยุดปกติ';

    const hourly = attendanceHourlyRequestLabels(row);
    if (hourly.length) {
        title += ` + ${hourly.map(label => label.replace('ไม่เกิน 1 ชม.', '').trim()).join(', ')}`;
    }
    return title;
}

function attendanceCalendarStatusColor(status) {
    const colors = {
        present: { background: '#bbf7d0', border: '#4ade80', text: '#14532d' },
        late: { background: '#fed7aa', border: '#fb923c', text: '#7c2d12' },
        absent: { background: '#fecaca', border: '#f87171', text: '#991b1b' },
        missing_in: { background: '#fef08a', border: '#eab308', text: '#713f12' },
        missing_out: { background: '#fef08a', border: '#eab308', text: '#713f12' },
        holiday: { background: '#e5e7eb', border: '#9ca3af', text: '#374151' },
        company_holiday: { background: '#bfdbfe', border: '#60a5fa', text: '#1e3a8a' },
        leave: { background: '#a5f3fc', border: '#22d3ee', text: '#164e63' },
        training: { background: '#ddd6fe', border: '#8b5cf6', text: '#4c1d95' },
    };
    return colors[status] || { background: '#f3f4f6', border: '#d1d5db', text: '#374151' };
}

function buildAttendanceCalendarDetails(row) {
    const note = row.holiday_name || row.leave_name || row.training_name || '-';
    const hourly = attendanceHourlyRequestLabels(row);
    const hourlyHtml = hourly.length
        ? `<div class="attendance-hourly-requests mt-3">
                <div class="fw-semibold mb-1">คำขอเวลา</div>
                <ul class="mb-0 ps-3">${hourly.map(label => `<li>${escapeHtml(label)}</li>`).join('')}</ul>
           </div>`
        : '';
    const overrideHtml = row.has_override ? `
        <div class="alert alert-info text-start mt-3 mb-0 py-2">
            <div class="fw-semibold">ปรับโดย HR</div>
            <div class="small">เหตุผล: ${escapeHtml(row.override_reason || '-')}</div>
            <div class="small">ผู้แก้: ${escapeHtml(row.override_updated_by_name || row.override_created_by_name || '-')}</div>
        </div>` : '';
    return `
        <div class="attendance-calendar-popup text-start">
            <div class="d-flex justify-content-between align-items-center gap-3 mb-3">
                <span class="text-muted">${formatAttendanceDay(row.day_name)}</span>
                ${attendanceStatusBadge(row.status, row.status_label || '-')}
            </div>
            <dl class="attendance-detail-list">
                <div><dt>เวลาเข้า</dt><dd>${formatAttendanceTime(row.check_in)}</dd></div>
                <div><dt>เวลาออก</dt><dd>${formatAttendanceTime(row.check_out)}</dd></div>
                <div><dt>รายละเอียด</dt><dd>${escapeHtml(note)}</dd></div>
            </dl>
            ${hourlyHtml}
            ${overrideHtml}
        </div>`;
}

function attendanceHourlyRequestLabels(row) {
    return Array.isArray(row.hourly_requests)
        ? row.hourly_requests.map(item => String(item || '').trim()).filter(Boolean)
        : [];
}

function formatAttendanceDay(value) {
    const days = { Mon: 'จันทร์', Tue: 'อังคาร', Wed: 'พุธ', Thu: 'พฤหัสบดี', Fri: 'ศุกร์', Sat: 'เสาร์', Sun: 'อาทิตย์' };
    return days[value] || value;
}

function formatAttendanceTime(value) {
    return value ? value.substring(0, 5) : '-';
}
