// Shared request display helpers. Page-specific balances keep separate renderers.
function renderProxyCreatorLine(item) {
    if (!item || item.created_via !== 'admin_proxy') return '';
    const name = item.proxy_creator_name || item.created_by_role || '';
    return `<div class="small text-muted mt-1">สร้างโดย HR/Admin${name ? `: ${escapeHtml(name)}` : ''}</div>`;
}

function formatLeaveDateRange(startDate, endDate, startPart, endPart) {
    const start = formatThaiDate(startDate);
    const end = formatThaiDate(endDate);
    const startLabel = getLeavePartLabel(startPart);
    const endLabel = getLeavePartLabel(endPart);

    if (!startDate || !endDate) return '';
    if (startDate === endDate) {
        const label = startLabel !== 'เต็มวัน' ? startLabel : endLabel;
        return `${start}${label !== 'เต็มวัน' ? ` (${label})` : ''}`;
    }

    return `${start}${startLabel !== 'เต็มวัน' ? ` (${startLabel})` : ''} - ${end}${endLabel !== 'เต็มวัน' ? ` (${endLabel})` : ''}`;
}

function getLeavePartLabel(part) {
    return {
        morning: 'ครึ่งวันเช้า',
        afternoon: 'ครึ่งวันบ่าย',
        full: 'เต็มวัน',
    }[part] || 'เต็มวัน';
}

function formatHourMinuteDuration(minutes) {
    const safeMinutes = Math.max(0, Number.parseInt(minutes || 0, 10) || 0);
    const hours = Math.floor(safeMinutes / 60);
    const remaining = safeMinutes % 60;
    const parts = [];
    if (hours > 0) parts.push(`${hours} ชม.`);
    if (remaining > 0 || !parts.length) parts.push(`${remaining} นาที`);
    return parts.join(' ');
}

// Request snapshots keep confirmation context independent of escaped table markup.
const approvalRequestSnapshots = new Map();
const approvalReasonDrafts = new Map();
const approvalSubmissions = new WeakSet();
function rememberApprovalRequest(kind, item) {
    approvalRequestSnapshots.set(kind + ':' + Number(item.id), { ...item });
}
function approvalRequestSummary(kind, id) {
    const item = approvalRequestSnapshots.get(kind + ':' + Number(id));
    if (!item) return '<p class="text-danger">ไม่พบรายละเอียดคำขอ กรุณาปิดแล้วโหลดรายการใหม่</p>';
    const fullName = item.employee_name || item.requester_name || [item.first_name_th, item.last_name_th].filter(Boolean).join(' ');
    const fields = [['พนักงาน', fullName], ['เลขคำขอ', '#' + Number(item.id)]];
    if (kind === 'day_swap') {
        fields.push(['ประเภท', 'สลับวันหยุด'], ['คู่สลับ', item.target_name], ['วันหยุดของผู้ขอ', formatThaiDate(item.requester_date)], ['วันหยุดของคู่สลับ', formatThaiDate(item.target_date)], ['เหตุผล', item.reason]);
    } else if (kind === 'training') {
        fields.push(['ประเภท', item.activity_type_name || 'กิจกรรม'], ['กิจกรรม', item.course_name], ['ช่วงวันที่', formatThaiDate(item.start_date) + ' – ' + formatThaiDate(item.end_date)], ['ช่วงวัน', [item.start_day_part, item.end_day_part].map(part => ({full:'เต็มวัน',morning:'ช่วงเช้า',afternoon:'ช่วงบ่าย'}[part] || 'เต็มวัน')).join(' – ')], ['สถานที่', item.location], ['วัตถุประสงค์', item.objective]);
    } else {
        fields.push(['ประเภท', item.type_name], ['ช่วงวันที่', formatThaiDate(item.start_date) + ' – ' + formatThaiDate(item.end_date)], ['ช่วงวัน', [item.start_day_part, item.end_day_part].map(part => ({full:'เต็มวัน',morning:'ช่วงเช้า',afternoon:'ช่วงบ่าย'}[part] || 'เต็มวัน')).join(' – ')], ['จำนวน / เวลา', formatLeaveDuration(item)], ['เหตุผล', item.reason]);
    }
    if (item.cancel_reason || item.cancellation_reason) fields.push(['เหตุผลขอยกเลิก', item.cancel_reason || item.cancellation_reason]);
    const rows = fields.map(([label,value]) => '<dt>' + escapeHtml(label) + '</dt><dd>' + escapeHtml(value || '-') + '</dd>').join('');
    const path = (item.file_path || item.attachment_path) ? attachmentUrl(kind === 'training' ? 'training_request' : 'leave', item.id) : '';
    const files = kind === 'leave' && Array.isArray(item.attachments) ? item.attachments : [];
    const attachment = files.length ? files.map((file,index) => '<div><a href="' + escapeAttr(attachmentUrl('leave', item.id, file.id)) + '" target="_blank" rel="noopener">เอกสารแนบ ' + (index + 1) + ': ' + escapeHtml(file.file_name || 'เปิดไฟล์') + ' (เปิดแท็บใหม่)</a></div>').join('') : path ? '<a href="' + escapeAttr(path) + '" target="_blank" rel="noopener">ดูเอกสารแนบ (เปิดแท็บใหม่)</a>' : '';
    return '<dl class="request-review-summary text-start border rounded p-3 mt-3">' + rows + '</dl>' + attachment;
}
function approvalRequestDetails(kind, id) {
    return '<details class="request-details"><summary>ดูรายละเอียดคำขอ</summary>' + approvalRequestSummary(kind,id) + '</details>';
}
function prepareApprovalReason(input, key) {
    if (input.dataset.approvalDraftKey) approvalReasonDrafts.set(input.dataset.approvalDraftKey, input.value);
    input.dataset.approvalDraftKey = key;
    input.value = approvalReasonDrafts.get(key) || '';
    if (!input.dataset.approvalDraftBound) {
        input.addEventListener('input', () => approvalReasonDrafts.set(input.dataset.approvalDraftKey, input.value));
        input.dataset.approvalDraftBound = 'true';
    }
}
function clearApprovalReason(form) {
    const input = form.querySelector('[data-approval-draft-key]');
    if (input) { approvalReasonDrafts.delete(input.dataset.approvalDraftKey); input.value = ''; }
}
function beginApprovalSubmission(form) {
    if (approvalSubmissions.has(form)) return null;
    approvalSubmissions.add(form);
    const buttons = [...form.querySelectorAll('button')].map(button => [button, button.disabled, button.textContent]);
    buttons.forEach(([button]) => { button.disabled = true; if (button.type === 'submit') button.textContent = 'กำลังบันทึก...'; });
    form.setAttribute('aria-busy', 'true');
    let finished = false;
    return () => {
        if (finished) return;
        finished = true;
        approvalSubmissions.delete(form);
        buttons.forEach(([button, disabled, label]) => { button.disabled = disabled; button.textContent = label; });
        form.removeAttribute('aria-busy');
    };
}
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.modal').forEach(modal => modal.addEventListener('hide.bs.modal', event => {
        const form = modal.querySelector('form');
        if (form && approvalSubmissions.has(form)) event.preventDefault();
    }));
});
