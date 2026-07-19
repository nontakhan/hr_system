let employeeRequestAttendanceReportRows = [];
let employeeRequestAttendanceReportTable = null;

document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('employeeRequestAttendanceReportPage')) initEmployeeRequestAttendanceReport();
});

async function initEmployeeRequestAttendanceReport() {
    const employee = document.getElementById('employeeRequestAttendanceReportEmployee');
    if (window.jQuery && jQuery.fn.select2) {
        jQuery(employee).select2({ width: '100%', placeholder: 'เลือกพนักงาน', allowClear: true });
    }
    document.getElementById('employeeRequestAttendanceReportLoad').addEventListener('click', loadEmployeeRequestAttendanceReport);
    document.getElementById('employeeRequestAttendanceReportType').addEventListener('change', renderEmployeeRequestAttendanceReportRows);
    document.getElementById('employeeRequestAttendanceReportSource').addEventListener('change', renderEmployeeRequestAttendanceReportRows);
    await loadEmployeeRequestAttendanceReportEmployees();
}

async function loadEmployeeRequestAttendanceReportEmployees() {
    const select = document.getElementById('employeeRequestAttendanceReportEmployee');
    const params = new URLSearchParams();
    params.set('action', 'employees');
    try {
        const payload = await fetchEmployeeRequestAttendanceJson(`api/attendance_api.php?${params}`);
        if (payload.status !== 'success') throw new Error(payload.message || 'โหลดรายชื่อพนักงานไม่สำเร็จ');
        select.innerHTML = '<option value=""></option>' + (payload.data || []).map(item => {
            const name = `${item.first_name_th || ''} ${item.last_name_th || ''}`.trim();
            const citizen = item.citizen_id ? ` (${item.citizen_id})` : '';
            return `<option value="${Number(item.id)}">${escapeEmployeeRequestAttendanceHtml(name + citizen)}</option>`;
        }).join('');
        if (window.jQuery && jQuery.fn.select2) jQuery(select).trigger('change.select2');
    } catch (error) {
        setEmployeeRequestAttendanceStatus(error.message, 'danger');
    }
}

async function loadEmployeeRequestAttendanceReport() {
    const employeeId = document.getElementById('employeeRequestAttendanceReportEmployee').value;
    const month = document.getElementById('employeeRequestAttendanceReportMonth').value;
    if (!employeeId || !month) {
        setEmployeeRequestAttendanceStatus('กรุณาเลือกพนักงานและเดือน', 'danger');
        return;
    }
    setEmployeeRequestAttendanceStatus('กำลังโหลดรายงาน...', 'muted');
    setEmployeeRequestAttendanceLoading();
    const params = new URLSearchParams();
    params.set('action', 'employee_request_attendance_report');
    params.set('employee_id', employeeId);
    params.set('month', month);
    try {
        const payload = await fetchEmployeeRequestAttendanceJson(`api/attendance_api.php?${params}`);
        if (payload.status !== 'success') throw new Error(payload.message || 'โหลดรายงานไม่สำเร็จ');
        employeeRequestAttendanceReportRows = Array.isArray(payload.data) ? payload.data : [];
        renderEmployeeRequestAttendanceSummary(payload.summary || {});
        populateEmployeeRequestAttendanceTypes(employeeRequestAttendanceReportRows);
        renderEmployeeRequestAttendanceReportRows();
        setEmployeeRequestAttendanceStatus(employeeRequestAttendanceReportRows.length ? `พบ ${employeeRequestAttendanceReportRows.length} เหตุการณ์` : 'ไม่พบข้อมูลในเดือนที่เลือก', 'muted');
    } catch (error) {
        employeeRequestAttendanceReportRows = [];
        renderEmployeeRequestAttendanceSummary({});
        renderEmployeeRequestAttendanceState(error.message, 'danger');
        setEmployeeRequestAttendanceStatus(error.message, 'danger');
    }
}

async function fetchEmployeeRequestAttendanceJson(url) {
    const response = await fetch(url);
    const text = await response.text();
    if (!text.trim()) throw new Error('เซิร์ฟเวอร์ไม่ส่งข้อมูลกลับ');
    try {
        return JSON.parse(text);
    } catch (error) {
        throw new Error('รูปแบบข้อมูลจากเซิร์ฟเวอร์ไม่ถูกต้อง');
    }
}

function populateEmployeeRequestAttendanceTypes(rows) {
    const select = document.getElementById('employeeRequestAttendanceReportType');
    const types = [...new Set(rows.map(row => row.event_type).filter(Boolean))];
    select.innerHTML = '<option value="">ทั้งหมด</option>' + types.map(type => `<option value="${escapeEmployeeRequestAttendanceHtml(type)}">${escapeEmployeeRequestAttendanceHtml(employeeRequestAttendanceTypeLabel(type))}</option>`).join('');
    select.disabled = rows.length === 0;
    document.getElementById('employeeRequestAttendanceReportSource').disabled = rows.length === 0;
}

function renderEmployeeRequestAttendanceReportRows() {
    const type = document.getElementById('employeeRequestAttendanceReportType').value;
    const source = document.getElementById('employeeRequestAttendanceReportSource').value;
    const rows = employeeRequestAttendanceReportRows.filter(row => (!type || row.event_type === type) && (!source || row.source === source));
    if (!rows.length) {
        renderEmployeeRequestAttendanceState('ไม่พบข้อมูลตามตัวกรอง');
        return;
    }
    destroyEmployeeRequestAttendanceDataTable();
    document.getElementById('employeeRequestAttendanceReportRows').innerHTML = rows.map(row => `
        <tr>
            <td data-order="${escapeEmployeeRequestAttendanceHtml(row.event_date || '')}">${escapeEmployeeRequestAttendanceHtml(formatEmployeeRequestAttendanceDate(row.event_date))}</td>
            <td><span class="badge bg-secondary">${escapeEmployeeRequestAttendanceHtml(employeeRequestAttendanceTypeLabel(row.event_type))}</span></td>
            <td>${escapeEmployeeRequestAttendanceHtml(row.source === 'scanner' ? 'เครื่องสแกน' : 'คำขออนุมัติ')}</td>
            <td>${escapeEmployeeRequestAttendanceHtml(row.time_label || '-')}</td>
            <td>${escapeEmployeeRequestAttendanceHtml(formatEmployeeRequestAttendanceAmount(row))}</td>
            <td class="text-wrap" style="min-width: 240px;">${escapeEmployeeRequestAttendanceHtml(formatEmployeeRequestAttendanceDetail(row))}</td>
            <td><span class="badge ${row.source === 'scanner' ? 'bg-primary' : 'bg-success'}">${escapeEmployeeRequestAttendanceHtml(row.status_label || '-')}</span></td>
        </tr>`).join('');
    if (rows.length > 10 && window.jQuery && jQuery.fn.DataTable) {
        employeeRequestAttendanceReportTable = jQuery('#employeeRequestAttendanceReportTable').DataTable({ pageLength: 25, order: [[0, 'asc']], language: { search: 'ค้นหา:', zeroRecords: 'ไม่พบข้อมูล', info: 'แสดง _START_ ถึง _END_ จาก _TOTAL_ รายการ', paginate: { previous: 'ก่อนหน้า', next: 'ถัดไป' } } });
    }
}

function renderEmployeeRequestAttendanceState(message, tone = 'muted') {
    destroyEmployeeRequestAttendanceDataTable();
    document.getElementById('employeeRequestAttendanceReportRows').innerHTML = `<tr><td colspan="7" class="text-center text-${escapeEmployeeRequestAttendanceHtml(tone)} py-4">${escapeEmployeeRequestAttendanceHtml(message)}</td></tr>`;
}

function setEmployeeRequestAttendanceLoading() {
    renderEmployeeRequestAttendanceState('กำลังโหลดข้อมูล...');
}

function destroyEmployeeRequestAttendanceDataTable() {
    if (employeeRequestAttendanceReportTable) {
        employeeRequestAttendanceReportTable.destroy();
        employeeRequestAttendanceReportTable = null;
    }
}

function renderEmployeeRequestAttendanceSummary(summary) {
    document.getElementById('employeeRequestAttendanceReportTotal').textContent = Number(summary.total_events || 0).toLocaleString('th-TH');
    document.getElementById('employeeRequestAttendanceReportApproved').textContent = Number(summary.approved_requests || 0).toLocaleString('th-TH');
    document.getElementById('employeeRequestAttendanceReportScanner').textContent = Number(summary.scanner_events || 0).toLocaleString('th-TH');
    document.getElementById('employeeRequestAttendanceReportOvertime').textContent = Number(summary.actual_overtime_minutes || 0).toLocaleString('th-TH');
}

function setEmployeeRequestAttendanceStatus(message, tone) {
    const status = document.getElementById('employeeRequestAttendanceReportStatus');
    status.className = `small text-${tone || 'muted'} mb-3`;
    status.textContent = message;
}

function employeeRequestAttendanceTypeLabel(type) {
    return ({ leave: 'การลา', late_request: 'ขอมาสาย', actual_late: 'มาสายจริง', early_request: 'ขอออกก่อน', actual_early: 'ออกก่อนจริง', activity: 'กิจกรรม', shift_swap: 'สลับเวร', overtime_request: 'คำขอ OT', actual_overtime: 'OT จริง' })[type] || type || '-';
}

function formatEmployeeRequestAttendanceAmount(row) {
    const amount = Number(row.amount || 0).toLocaleString('th-TH', { maximumFractionDigits: 2 });
    return `${amount} ${row.amount_unit === 'day' ? 'วัน' : row.amount_unit === 'minute' ? 'นาที' : 'รายการ'}`;
}

function formatEmployeeRequestAttendanceDetail(row) {
    const actualOt = row.event_type === 'overtime_request' && Number(row.actual_overtime_minutes || 0) > 0 ? ` | OT จริง ${Number(row.actual_overtime_minutes).toLocaleString('th-TH')} นาที` : '';
    return `${row.detail || '-'}${actualOt}`;
}

function formatEmployeeRequestAttendanceDate(value) {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(value || '')) return value || '-';
    const [year, month, day] = value.split('-').map(Number);
    return new Intl.DateTimeFormat('th-TH', { day: 'numeric', month: 'short', year: 'numeric' }).format(new Date(year, month - 1, day));
}

function escapeEmployeeRequestAttendanceHtml(value) {
    return String(value ?? '').replace(/[&<>'"]/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[char]);
}
