let approvedLeaveReportOptions = { companies: [], branches: [], leave_types: [] };
let approvedLeaveReportDataTable = null;
let approvedLeaveReportRows = [];
let approvedLeaveWarningBulk = null;

document.addEventListener('DOMContentLoaded', () => {
    if (!document.getElementById('approvedLeaveReportPage')) return;
    initApprovedLeaveReport();
});

function initApprovedLeaveReport() {
    approvedLeaveWarningBulk = window.EmployeeWarningBulk?.create({
        pageId: 'approvedLeaveReportPage',
        sourceType: 'approved_leave',
        actionButtonId: 'approvedLeaveWarningBulkBtn',
        selectedCountId: 'approvedLeaveWarningSelectedCount',
        selectAllId: 'approvedLeaveWarningSelectAll',
        getRows: () => approvedLeaveReportRows,
        getDataTable: () => approvedLeaveReportDataTable,
        buildEvent: buildApprovedLeaveWarningEvent,
        onCompleted: (result) => completeApprovedLeaveWarnings(result),
    }) || null;
    const month = document.getElementById('approvedLeaveReportMonth');
    if (month && !month.value) month.value = new Date().toISOString().slice(0, 7);
    initializeApprovedLeaveReportSelect2();

    const company = document.getElementById('approvedLeaveReportCompany');
    const branch = document.getElementById('approvedLeaveReportBranch');
    company?.addEventListener('change', () => {
        updateApprovedLeaveReportBranches();
        loadApprovedLeaveReport();
    });
    branch?.addEventListener('change', loadApprovedLeaveReport);
    document.getElementById('approvedLeaveReportType')?.addEventListener('change', loadApprovedLeaveReport);
    month?.addEventListener('change', loadApprovedLeaveReport);
    document.getElementById('approvedLeaveReportLoadBtn')?.addEventListener('click', loadApprovedLeaveReport);
    loadApprovedLeaveReportOptions();
}

function initializeApprovedLeaveReportSelect2() {
    if (!window.jQuery || !jQuery.fn.select2) return;
    jQuery('.leave-report-select2').each(function () {
        const element = jQuery(this);
        if (element.hasClass('select2-hidden-accessible')) element.select2('destroy');
        element.select2({ width: '100%', allowClear: true, placeholder: element.data('placeholder') || '' });
    });
}

async function loadApprovedLeaveReportOptions() {
    try {
        const response = await fetch('api/leave_api.php?action=approved_leave_report_filters');
        const responseText = await response.text();
        if (!responseText.trim()) throw new Error('เซิร์ฟเวอร์ไม่ส่งข้อมูลกลับ กรุณาลองใหม่อีกครั้ง');
        const res = JSON.parse(responseText);
        if (res.status !== 'success') throw new Error(res.message || 'โหลดตัวกรองไม่สำเร็จ');
        approvedLeaveReportOptions = {
            companies: res.data?.companies || [],
            branches: res.data?.branches || [],
            leave_types: res.data?.leave_types || [],
        };
        fillApprovedLeaveReportSelect('approvedLeaveReportCompany', approvedLeaveReportOptions.companies, 'บริษัททั้งหมด');
        fillApprovedLeaveReportSelect('approvedLeaveReportType', approvedLeaveReportOptions.leave_types, 'ประเภทการลาทั้งหมด');
        updateApprovedLeaveReportBranches();
        loadApprovedLeaveReport();
    } catch (error) {
        renderApprovedLeaveReportError(error.message);
    }
}

function fillApprovedLeaveReportSelect(id, options, placeholder) {
    const select = document.getElementById(id);
    if (!select) return;
    const selected = select.value;
    select.innerHTML = `<option value="">${escapeHtml(placeholder)}</option>` + options.map((option) =>
        `<option value="${Number(option.id)}">${escapeHtml(option.label || '-')}</option>`
    ).join('');
    if (options.some((option) => String(option.id) === selected)) select.value = selected;
    if (window.jQuery && jQuery.fn.select2) jQuery(select).trigger('change.select2');
}

function updateApprovedLeaveReportBranches() {
    const companyId = document.getElementById('approvedLeaveReportCompany')?.value || '';
    const branches = companyId
        ? approvedLeaveReportOptions.branches.filter((branch) => String(branch.company_id) === companyId)
        : approvedLeaveReportOptions.branches;
    fillApprovedLeaveReportSelect('approvedLeaveReportBranch', branches, companyId ? 'สาขาทั้งหมดในบริษัท' : 'สาขาทั้งหมด');
}

async function loadApprovedLeaveReport() {
    const rowsEl = document.getElementById('approvedLeaveReportRows');
    const month = document.getElementById('approvedLeaveReportMonth')?.value || '';
    if (!rowsEl || !month) return;
    approvedLeaveWarningBulk?.clearSelection();

    const params = new URLSearchParams({
        action: 'approved_leave_report',
        month,
        company_id: document.getElementById('approvedLeaveReportCompany')?.value || '',
        branch_id: document.getElementById('approvedLeaveReportBranch')?.value || '',
        leave_type_id: document.getElementById('approvedLeaveReportType')?.value || '',
    });
    resetApprovedLeaveReportDataTable();
    rowsEl.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-4">กำลังโหลดรายงาน...</td></tr>';
    try {
        const response = await fetch(`api/leave_api.php?${params.toString()}`);
        const responseText = await response.text();
        if (!responseText.trim()) throw new Error('เซิร์ฟเวอร์ไม่ส่งข้อมูลกลับ กรุณาลองใหม่อีกครั้ง');
        const res = JSON.parse(responseText);
        if (res.status !== 'success') throw new Error(res.message || 'โหลดรายงานไม่สำเร็จ');
        approvedLeaveReportRows = res.data || [];
        renderApprovedLeaveReportSummary(res.summary || {});
        renderApprovedLeaveReportRows(approvedLeaveReportRows);
        approvedLeaveWarningBulk?.replaceRows(approvedLeaveReportRows);
    } catch (error) {
        renderApprovedLeaveReportError(error.message);
    }
}

function renderApprovedLeaveReportSummary(summary) {
    const target = document.getElementById('approvedLeaveReportSummary');
    if (!target) return;
    const cards = [
        ['รายการวันลา', summary.total_rows || 0, 'primary', 'fa-list'],
        ['จำนวนวันลารวม', Number(summary.total_days || 0).toLocaleString('th-TH'), 'success', 'fa-calendar-check'],
        ['เจ้าหน้าที่ที่ลา', summary.employee_count || 0, 'info', 'fa-users'],
    ];
    target.innerHTML = cards.map(([label, value, color, icon]) => `
        <div class="col-md-4"><div class="card shadow-sm border-0 h-100"><div class="card-body d-flex align-items-center">
            <div class="text-${color} fs-3 me-3"><i class="fas ${icon}"></i></div>
            <div><div class="text-muted small">${label}</div><div class="h4 mb-0">${value}</div></div>
        </div></div></div>`).join('');
}

function renderApprovedLeaveReportRows(rows) {
    const rowsEl = document.getElementById('approvedLeaveReportRows');
    if (!rowsEl) return;
    resetApprovedLeaveReportDataTable();
    if (!rows.length) {
        rowsEl.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-4">ไม่พบข้อมูลการลาในเงื่อนไขนี้</td></tr>';
        return;
    }
    rowsEl.innerHTML = rows.map((row) => {
        const dateRange = row.start_date === row.end_date
            ? formatThaiDate(row.start_date)
            : `${formatThaiDate(row.start_date)} - ${formatThaiDate(row.end_date)}`;
        const part = row.leave_days === 0.5 && row.day_part_label ? ` (${escapeHtml(row.day_part_label)})` : '';
        const warningKey = row.warning_source_key || '';
        const warningDisabled = row.already_warned ? ' disabled' : '';
        const warningBadge = row.already_warned ? '<span class="badge bg-secondary ms-1">ออกใบเตือนแล้ว</span>' : '';
        return `<tr>
            <td class="text-center"><input type="checkbox" class="form-check-input employee-warning-row-select" data-warning-source-key="${escapeHtml(warningKey)}" aria-label="เลือกเหตุการณ์เพื่อเพิ่มใบเตือน"${warningDisabled}></td>
            <td data-order="${escapeHtml(row.leave_date || '')}">${escapeHtml(formatThaiDate(row.leave_date))}</td>
            <td>${escapeHtml(row.full_name || '-')}<div class="small text-muted">${escapeHtml(row.citizen_id || '-')}</div></td>
            <td>${escapeHtml(row.position_name_th || '-')}</td>
            <td>${escapeHtml(row.company_name_th || '-')}</td>
            <td>${escapeHtml(row.branch_name_th || '-')}</td>
            <td>${escapeHtml(row.leave_type_name || '-')}${warningBadge}</td>
            <td>${escapeHtml(dateRange)}</td>
            <td>${Number(row.leave_days || 0).toLocaleString('th-TH')} วัน${part}</td>
            <td>${escapeHtml(row.reason || '-')}</td>
        </tr>`;
    }).join('');
    initApprovedLeaveReportDataTable(rows.length);
}

function renderApprovedLeaveReportError(message) {
    resetApprovedLeaveReportDataTable();
    approvedLeaveReportRows = [];
    approvedLeaveWarningBulk?.replaceRows([]);
    const rowsEl = document.getElementById('approvedLeaveReportRows');
    if (rowsEl) rowsEl.innerHTML = `<tr><td colspan="10" class="text-center text-danger py-4">${escapeHtml(message || 'เกิดข้อผิดพลาด')}</td></tr>`;
}

function resetApprovedLeaveReportDataTable() {
    const table = document.getElementById('approvedLeaveReportTable');
    if (approvedLeaveReportDataTable) {
        approvedLeaveReportDataTable.destroy();
        approvedLeaveReportDataTable = null;
    } else if (window.jQuery && table && jQuery.fn.DataTable.isDataTable(table)) {
        jQuery(table).DataTable().destroy();
    }
}

function initApprovedLeaveReportDataTable(rowCount) {
    const table = document.getElementById('approvedLeaveReportTable');
    if (!table || rowCount <= 10 || !window.jQuery || !jQuery.fn.DataTable) return;
    approvedLeaveReportDataTable = jQuery(table).DataTable({
        pageLength: 25,
        order: [[1, 'asc'], [2, 'asc']],
        language: { search: 'ค้นหา:', lengthMenu: 'แสดง _MENU_ รายการ', info: 'แสดง _START_ ถึง _END_ จาก _TOTAL_ รายการ', paginate: { previous: 'ก่อนหน้า', next: 'ถัดไป' } },
    });
    jQuery(table).off('draw.dt.warningBulk').on('draw.dt.warningBulk', () => approvedLeaveWarningBulk?.syncCheckboxes());
}

function buildApprovedLeaveWarningEvent(row) {
    return {
        employee_id: Number(row.employee_id || 0),
        source_type: row.warning_source_type || 'approved_leave',
        source_key: row.warning_source_key || '',
        leave_date: row.leave_date || '',
        already_warned: Boolean(row.already_warned),
    };
}

function completeApprovedLeaveWarnings(result) {
    const completed = new Set([...(result.created_keys || []), ...(result.duplicate_keys || [])].map(String));
    approvedLeaveReportRows.forEach((row) => {
        if (completed.has(String(row.warning_source_key || ''))) row.already_warned = true;
    });
    approvedLeaveWarningBulk?.clearSelection();
    renderApprovedLeaveReportRows(approvedLeaveReportRows);
    approvedLeaveWarningBulk?.replaceRows(approvedLeaveReportRows);
}
