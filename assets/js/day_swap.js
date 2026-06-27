document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('daySwapForm')) {
        initDaySwapRequestPage();
    }
    if (document.getElementById('daySwapHistoryBody')) {
        initDaySwapHistoryPage();
    }
    if (document.getElementById('daySwapPendingBody')) {
        initDaySwapApprovalPage();
    }
});

let requesterHolidayCalendar = null;
let targetHolidayCalendar = null;
let requesterHolidayEvents = [];
let targetHolidayEvents = [];
const daySwapDataTables = {};

function initDaySwapRequestPage() {
    if (window.jQuery && jQuery.fn.select2) {
        jQuery('.day-swap-select2').select2({ width: '100%' });
    }

    initDaySwapCalendars();
    document.getElementById('requesterMonth').addEventListener('change', () => {
        clearDaySwapSelection('requester');
        loadRequesterHolidays();
    });
    document.getElementById('targetMonth').addEventListener('change', () => {
        clearDaySwapSelection('target');
        loadTargetHolidays();
    });
    bindDaySwapTargetEmployeeChange(document.getElementById('targetEmployee'), loadTargetHolidays);
    document.getElementById('daySwapForm').addEventListener('submit', submitDaySwapRequest);

    loadDaySwapEmployees();
    loadRequesterHolidays();
    loadTargetHolidays();
    loadDaySwapHistory();
}

function initDaySwapHistoryPage() {
    const refreshButton = document.getElementById('refreshDaySwapHistoryBtn');
    if (refreshButton) {
        refreshButton.addEventListener('click', loadDaySwapHistory);
    }
    loadDaySwapHistory();
}

function bindDaySwapTargetEmployeeChange(select, handler) {
    select.addEventListener('change', handler);

    if (window.jQuery && jQuery.fn.select2) {
        jQuery(select)
            .off('select2:select.daySwap select2:clear.daySwap')
            .on('select2:select.daySwap select2:clear.daySwap', handler);
    }
}

function initDaySwapCalendars() {
    if (!window.FullCalendar) return;

    requesterHolidayCalendar = new FullCalendar.Calendar(
        document.getElementById('requesterHolidayCalendar'),
        buildDaySwapCalendarOptions('requester')
    );
    targetHolidayCalendar = new FullCalendar.Calendar(
        document.getElementById('targetHolidayCalendar'),
        buildDaySwapCalendarOptions('target')
    );
    requesterHolidayCalendar.render();
    targetHolidayCalendar.render();
}

function buildDaySwapCalendarOptions(owner) {
    const monthInput = owner === 'requester'
        ? document.getElementById('requesterMonth')
        : document.getElementById('targetMonth');

    return {
        initialView: 'dayGridMonth',
        locale: 'th',
        firstDay: 1,
        height: 'auto',
        headerToolbar: false,
        initialDate: `${monthInput.value || new Date().toISOString().slice(0, 7)}-01`,
        events: [],
        dayCellClassNames: buildDaySwapCalendarDayClasses(''),
        eventClick(info) {
            const date = info.event.startStr;
            selectDaySwapDate(owner, date);
        },
    };
}

function buildDaySwapHolidayEvent(day, owner) {
    return {
        title: 'วันหยุด',
        start: day.date,
        allDay: true,
        backgroundColor: '#e5e7eb',
        borderColor: '#9ca3af',
        textColor: '#374151',
        classNames: ['day-swap-holiday-event'],
        extendedProps: { owner, day },
    };
}

function buildDaySwapCalendarDayClasses(selectedDate) {
    return (info) => {
        const dateKey = formatDaySwapDateKey(info.date);
        return dateKey === selectedDate ? ['day-swap-calendar-selected'] : [];
    };
}

function formatDaySwapDateKey(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function renderDaySwapCalendar(owner) {
    const calendar = owner === 'requester' ? requesterHolidayCalendar : targetHolidayCalendar;
    if (!calendar) return;

    const events = owner === 'requester' ? requesterHolidayEvents : targetHolidayEvents;
    const monthValue = owner === 'requester'
        ? document.getElementById('requesterMonth').value
        : document.getElementById('targetMonth').value;
    const selectedDate = document.getElementById(owner === 'requester' ? 'requesterDate' : 'targetDate').value;

    calendar.gotoDate(`${monthValue || new Date().toISOString().slice(0, 7)}-01`);
    calendar.removeAllEvents();
    events.forEach(event => calendar.addEvent(event));
    calendar.setOption('dayCellClassNames', buildDaySwapCalendarDayClasses(selectedDate));
    calendar.render();
}

function selectDaySwapDate(owner, date) {
    document.getElementById(owner === 'requester' ? 'requesterDate' : 'targetDate').value = date;
    updateDaySwapSelectedLabel(owner, date);
    renderDaySwapCalendar(owner);
    updateDaySwapSelectionSummary();
}

function clearDaySwapSelection(owner) {
    document.getElementById(owner === 'requester' ? 'requesterDate' : 'targetDate').value = '';
    updateDaySwapSelectedLabel(owner, '');
    updateDaySwapSelectionSummary();
}

function updateDaySwapSelectedLabel(owner, date) {
    const label = document.getElementById(owner === 'requester' ? 'requesterSelectedLabel' : 'targetSelectedLabel');
    label.textContent = date ? formatThaiDate(date) : 'ยังไม่เลือก';
    label.className = `badge ${date ? 'bg-primary' : 'bg-secondary'}`;
}

function updateDaySwapSelectionSummary() {
    const requesterDate = document.getElementById('requesterDate').value;
    const targetDate = document.getElementById('targetDate').value;
    const summary = document.getElementById('daySwapSelectionSummary');
    if (requesterDate && targetDate) {
        summary.className = 'alert alert-info border mb-3';
        summary.innerHTML = `<strong>วันที่เลือก:</strong> คุณ ${formatThaiDate(requesterDate)} ↔ คู่สลับ ${formatThaiDate(targetDate)}`;
        return;
    }
    summary.className = 'alert alert-light border mb-3';
    summary.textContent = 'เลือกวันหยุดจากปฏิทินทั้งสองฝั่งเพื่อสร้างคำขอ';
}

async function loadDaySwapEmployees() {
    const select = document.getElementById('targetEmployee');
    try {
        const response = await fetch('api/day_swap_api.php?action=employees');
        const res = await response.json();
        if (res.status !== 'success') throw new Error(res.message || 'Load failed');
        select.innerHTML = '<option value="">เลือกพนักงาน</option>' + res.data.map(emp => {
            const name = `${escapeHtml(emp.first_name_th)} ${escapeHtml(emp.last_name_th)}`;
            return `<option value="${emp.id}">${name} (${escapeHtml(emp.citizen_id || '')})</option>`;
        }).join('');
        refreshDaySwapSelect(select);
    } catch (err) {
        Swal.fire('ผิดพลาด', err.message, 'error');
    }
}

async function loadRequesterHolidays() {
    requesterHolidayEvents = await loadHolidayOptions(document.getElementById('requesterMonth').value, '', 'requester');
    renderDaySwapCalendar('requester');
}

async function loadTargetHolidays() {
    const targetId = document.getElementById('targetEmployee').value;
    if (!targetId) {
        targetHolidayEvents = [];
        clearDaySwapSelection('target');
        renderDaySwapCalendar('target');
        return;
    }
    clearDaySwapSelection('target');
    targetHolidayEvents = await loadHolidayOptions(document.getElementById('targetMonth').value, targetId, 'target');
    renderDaySwapCalendar('target');
}

async function loadHolidayOptions(month, employeeId = '', owner = 'requester') {
    try {
        const url = new URL('api/day_swap_api.php', window.location.href);
        url.searchParams.set('action', 'holidays');
        url.searchParams.set('month', month);
        if (employeeId) url.searchParams.set('employee_id', employeeId);

        const response = await fetch(url.toString());
        const res = await response.json();
        if (res.status !== 'success') throw new Error(res.message || 'Load failed');
        return res.data.map(day => buildDaySwapHolidayEvent(day, owner));
    } catch (err) {
        Swal.fire('ผิดพลาด', err.message, 'error');
        return [];
    }
}

async function submitDaySwapRequest(event) {
    event.preventDefault();
    const form = event.target;
    const data = Object.fromEntries(new FormData(form).entries());
    data.action = 'create';

    if (!data.requester_date || !data.target_date) {
        Swal.fire('กรุณาเลือกวันที่', 'เลือกวันหยุดจากปฏิทินทั้งสองฝั่งก่อนส่งคำขอ', 'warning');
        return;
    }

    try {
        const response = await fetch('api/day_swap_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data),
        });
        const res = await response.json();
        if (res.status !== 'success') throw new Error(res.message || 'Save failed');
        Swal.fire('สำเร็จ', res.message, 'success');
        form.reset();
        document.getElementById('requesterMonth').value = new Date().toISOString().slice(0, 7);
        document.getElementById('targetMonth').value = new Date().toISOString().slice(0, 7);
        refreshDaySwapSelect(document.getElementById('targetEmployee'));
        clearDaySwapSelection('requester');
        clearDaySwapSelection('target');
        loadRequesterHolidays();
        loadTargetHolidays();
        loadDaySwapHistory();
    } catch (err) {
        Swal.fire('ผิดพลาด', err.message, 'error');
    }
}

async function loadDaySwapHistory() {
    const tbody = document.getElementById('daySwapHistoryBody');
    if (!tbody) return;

    resetDaySwapDataTable('daySwapHistoryTable');
    tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">กำลังโหลด...</td></tr>';
    try {
        const response = await fetch('api/day_swap_api.php?action=my_requests');
        const res = await response.json();
        if (res.status !== 'success') throw new Error(res.message || 'Load failed');
        if (!res.data.length) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">ยังไม่มีคำขอ</td></tr>';
            return;
        }
        tbody.innerHTML = res.data.map(item => `
            <tr>
                <td>${formatThaiDate(item.created_at)}</td>
                <td>${escapeHtml(item.requester_name || '-')} ↔ ${escapeHtml(item.target_name || '-')}</td>
                <td>${formatThaiDate(item.requester_date)} ↔ ${formatThaiDate(item.target_date)}</td>
                <td>${renderDaySwapStatus(item.status)}</td>
            </tr>
        `).join('');
        initDaySwapDataTable('daySwapHistoryTable', [[0, 'desc']], [3]);
    } catch (err) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center text-danger py-4">โหลดข้อมูลไม่สำเร็จ</td></tr>';
    }
}

function initDaySwapApprovalPage() {
    document.getElementById('day-swap-pending-tab').addEventListener('shown.bs.tab', loadDaySwapPendingApprovals);
    document.getElementById('day-swap-history-tab').addEventListener('shown.bs.tab', loadDaySwapApprovalHistory);
    document.getElementById('daySwapApprovalForm').addEventListener('submit', submitDaySwapApproval);
    loadDaySwapPendingApprovals();
}

async function loadDaySwapPendingApprovals() {
    const tbody = document.getElementById('daySwapPendingBody');
    resetDaySwapDataTable('daySwapPendingTable');
    tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">กำลังโหลด...</td></tr>';
    try {
        const response = await fetch('api/day_swap_api.php?action=pending');
        const res = await response.json();
        if (res.status !== 'success') throw new Error(res.message || 'Load failed');
        if (!res.data.length) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">ไม่มีรายการรออนุมัติ</td></tr>';
            return;
        }
        tbody.innerHTML = res.data.map(item => `
            <tr>
                <td>${escapeHtml(item.requester_name || '-')}<br><small class="text-muted">${escapeHtml(item.requester_code || '')}</small><div class="mt-1">${renderDaySwapStatus(item.status)}</div></td>
                <td>${escapeHtml(item.target_name || '-')}<br><small class="text-muted">${escapeHtml(item.target_code || '')}</small></td>
                <td>${formatThaiDate(item.requester_date)} ↔ ${formatThaiDate(item.target_date)}</td>
                <td><small class="text-muted">${escapeHtml(item.reason || '-')}</small></td>
                <td>
                    <button class="btn btn-sm btn-success me-1" onclick="openDaySwapActionModal(${item.id}, 'approve', '${escapeAttr(item.requester_name || '')}')">
                        <i class="fas fa-check"></i> อนุมัติ
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="openDaySwapActionModal(${item.id}, 'reject', '${escapeAttr(item.requester_name || '')}')">
                        <i class="fas fa-times"></i> ไม่
                    </button>
                </td>
            </tr>
        `).join('');
        initDaySwapDataTable('daySwapPendingTable', [[2, 'asc']], [4]);
    } catch (err) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger py-4">โหลดข้อมูลไม่สำเร็จ</td></tr>';
    }
}

async function loadDaySwapApprovalHistory() {
    const tbody = document.getElementById('daySwapApprovalHistoryBody');
    resetDaySwapDataTable('daySwapApprovalHistoryTable');
    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">กำลังโหลด...</td></tr>';
    try {
        const response = await fetch('api/day_swap_api.php?action=history');
        const res = await response.json();
        if (res.status !== 'success') throw new Error(res.message || 'Load failed');
        if (!res.data.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">ยังไม่มีประวัติ</td></tr>';
            return;
        }
        tbody.innerHTML = res.data.map(item => `
            <tr>
                <td>${item.approval_date ? formatThaiDate(item.approval_date) : '-'}</td>
                <td>${escapeHtml(item.requester_name || '-')}</td>
                <td>${escapeHtml(item.target_name || '-')}</td>
                <td>${formatThaiDate(item.requester_date)} ↔ ${formatThaiDate(item.target_date)}</td>
                <td>${renderDaySwapStatus(item.status)}</td>
                <td><small class="text-muted">${escapeHtml(item.rejection_reason || '-')}</small></td>
            </tr>
        `).join('');
        initDaySwapDataTable('daySwapApprovalHistoryTable', [[0, 'desc']], []);
    } catch (err) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger py-4">โหลดข้อมูลไม่สำเร็จ</td></tr>';
    }
}

function resetDaySwapDataTable(tableId) {
    const selector = `#${tableId}`;
    if (daySwapDataTables[tableId]) {
        daySwapDataTables[tableId].destroy();
        delete daySwapDataTables[tableId];
    } else if (window.jQuery && jQuery.fn.DataTable && jQuery.fn.DataTable.isDataTable(selector)) {
        jQuery(selector).DataTable().destroy();
    }
}

function initDaySwapDataTable(tableId, order = [[0, 'desc']], unsortableTargets = []) {
    if (!window.jQuery || !jQuery.fn.DataTable || !document.getElementById(tableId)) {
        return;
    }

    const options = {
        language: { url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/th.json' },
        pageLength: 10,
        order,
    };
    if (unsortableTargets.length) {
        options.columnDefs = [{ orderable: false, targets: unsortableTargets }];
    }

    daySwapDataTables[tableId] = jQuery(`#${tableId}`).DataTable(options);
}

window.openDaySwapActionModal = function(id, action, name) {
    const modal = new bootstrap.Modal(document.getElementById('daySwapActionModal'));
    const isApprove = action === 'approve';
    document.getElementById('daySwapRequestId').value = id;
    document.getElementById('daySwapActionType').value = action;
    document.getElementById('daySwapActionTitle').textContent = isApprove ? 'ยืนยันการอนุมัติ' : 'ยืนยันการปฏิเสธ';
    document.getElementById('daySwapActionTitle').className = `modal-title ${isApprove ? 'text-success' : 'text-danger'}`;
    document.getElementById('daySwapActionMessage').innerHTML = `ต้องการ${isApprove ? 'อนุมัติ' : 'ไม่อนุมัติ'}คำขอสลับวันหยุดของ <strong>${escapeHtml(name)}</strong> ใช่หรือไม่?`;
    document.getElementById('daySwapConfirmBtn').className = `btn ${isApprove ? 'btn-success' : 'btn-danger'}`;
    document.getElementById('daySwapConfirmBtn').textContent = isApprove ? 'ยืนยันอนุมัติ' : 'ยืนยันไม่อนุมัติ';
    document.getElementById('daySwapRejectReasonWrap').style.display = isApprove ? 'none' : 'block';
    document.getElementById('daySwapRejectReason').required = !isApprove;
    document.getElementById('daySwapRejectReason').value = '';
    modal.show();
};

async function submitDaySwapApproval(event) {
    event.preventDefault();
    const data = Object.fromEntries(new FormData(event.target).entries());
    data.action = data.action_type;
    try {
        const response = await fetch('api/day_swap_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data),
        });
        const res = await response.json();
        if (res.status !== 'success') throw new Error(res.message || 'Save failed');
        Swal.fire('สำเร็จ', res.message, 'success');
        bootstrap.Modal.getInstance(document.getElementById('daySwapActionModal')).hide();
        loadDaySwapPendingApprovals();
    } catch (err) {
        Swal.fire('ผิดพลาด', err.message, 'error');
    }
}

function renderDaySwapStatus(status) {
    const map = {
        pending: ['รอหัวหน้างานอนุมัติ', 'warning text-dark'],
        pending_manager: ['รอหัวหน้างานอนุมัติ', 'warning text-dark'],
        pending_hr: ['รอ HR อนุมัติ', 'info text-dark'],
        approved: ['อนุมัติแล้ว', 'success'],
        rejected: ['ไม่อนุมัติ', 'danger'],
        cancelled: ['ยกเลิก', 'secondary'],
    };
    const item = map[status] || [status || '-', 'secondary'];
    return `<span class="badge bg-${item[1]}">${item[0]}</span>`;
}

function refreshDaySwapSelect(select) {
    if (window.jQuery && jQuery.fn.select2) {
        jQuery(select).trigger('change.select2');
    }
}
