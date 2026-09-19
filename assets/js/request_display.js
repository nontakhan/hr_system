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
