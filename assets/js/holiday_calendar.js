document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('holidayCalendar')) {
        initHolidayCalendarPage();
    }
});

let holidayCalendar = null;
let holidayCalendarDayClassMap = {};

function initHolidayCalendarPage() {
    const monthInput = document.getElementById('holidayCalendarMonth');
    const loadBtn = document.getElementById('holidayCalendarLoadBtn');

    if (window.FullCalendar) {
        holidayCalendar = new FullCalendar.Calendar(document.getElementById('holidayCalendar'), buildHolidayCalendarOptions());
        holidayCalendar.render();
    }

    loadBtn.addEventListener('click', loadHolidayCalendar);
    monthInput.addEventListener('change', loadHolidayCalendar);
    loadHolidayCalendar();
}

function buildHolidayCalendarOptions() {
    const month = document.getElementById?.('holidayCalendarMonth')?.value || new Date().toISOString().slice(0, 7);
    return {
        initialView: 'dayGridMonth',
        locale: 'th',
        firstDay: 1,
        height: 'auto',
        headerToolbar: false,
        initialDate: `${month}-01`,
        events: [],
        eventClick(info) {
            const item = info.event.extendedProps?.item;
            if (item) {
                showHolidayCalendarDetail(item);
            }
        },
        dayCellClassNames(info) {
            const dateKey = formatHolidayCalendarDateKey(info.date);
            return holidayCalendarDayClassMap[dateKey] ? [holidayCalendarDayClassMap[dateKey]] : [];
        },
    };
}

function holidayCalendarColors(type) {
    if (type === 'company_holiday') {
        return {
            background: '#bfdbfe',
            border: '#60a5fa',
            text: '#1e3a8a',
            className: 'holiday-calendar-company',
        };
    }

    if (type === 'approved_leave') {
        return {
            background: '#ddd6fe',
            border: '#8b5cf6',
            text: '#4c1d95',
            className: 'holiday-calendar-approved-leave',
        };
    }

    return {
        background: '#bbf7d0',
        border: '#22c55e',
        text: '#14532d',
        className: 'holiday-calendar-regular',
    };
}

function formatHolidayCalendarDateKey(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function buildHolidayCalendarDayClassMap(rows) {
    return rows.reduce((map, item) => {
        if (item.type === 'company_holiday') {
            map[item.date] = 'holiday-calendar-day-company';
        } else if (item.type === 'approved_leave' && map[item.date] !== 'holiday-calendar-day-company') {
            map[item.date] = 'holiday-calendar-day-approved-leave';
        } else if (item.type === 'regular_holiday') {
            map[item.date] = map[item.date] || 'holiday-calendar-day-regular';
        }
        return map;
    }, {});
}

function buildHolidayCalendarEvent(item) {
    const colors = holidayCalendarColors(item.type);
    return {
        title: item.title || 'วันหยุด',
        start: item.date,
        allDay: true,
        backgroundColor: colors.background,
        borderColor: colors.border,
        textColor: colors.text,
        classNames: [colors.className],
        extendedProps: { item },
    };
}

function buildHolidayCalendarSummaryHtml(summary) {
    const company = Number(summary?.company_holiday || 0);
    const regular = Number(summary?.regular_holiday || 0);
    const leave = Number(summary?.approved_leave || 0);
    const total = Number(summary?.total || 0);
    return `
        <div class="row g-3 holiday-calendar-summary">
            ${holidayCalendarSummaryCard('วันหยุดบริษัท', company, 'fa-building-circle-check', 'company')}
            ${holidayCalendarSummaryCard('วันหยุดประจำสัปดาห์', regular, 'fa-calendar-day', 'regular')}
            ${holidayCalendarSummaryCard('วันลาอนุมัติ', leave, 'fa-person-walking-arrow-right', 'leave')}
            ${holidayCalendarSummaryCard('รวมวันหยุด', total, 'fa-calendar-check', 'total')}
        </div>
    `;
}

function holidayCalendarSummaryCard(label, value, icon, tone) {
    return `
        <div class="col-md-3 col-sm-6">
            <div class="holiday-calendar-summary-card holiday-calendar-summary-${tone}">
                <div>
                    <div class="text-muted small">${label}</div>
                    <div class="h4 mb-0">${value}</div>
                </div>
                <i class="fas ${icon}"></i>
            </div>
        </div>
    `;
}

function holidayCalendarStatusLabel(status) {
    if (status === 'pending_cancel_hr') return 'รอยืนยันการยกเลิก';
    if (status === 'approved') return 'อนุมัติแล้ว';
    return status || '-';
}

function formatHolidayCalendarDayCount(value) {
    const number = Number.parseFloat(value || 0);
    if (!Number.isFinite(number) || number <= 0) return '-';
    const text = number.toFixed(2).replace(/0+$/, '').replace(/\.$/, '');
    return text;
}

function buildHolidayCalendarDetailHtml(item) {
    if (item.type !== 'approved_leave') {
        return `
            <div class="holiday-calendar-detail text-start">
                <div class="fw-semibold mb-2">${escapeHtml(item.title || '')}</div>
                <div class="small text-muted">${escapeHtml(item.description || '')}</div>
                <div class="mt-2"><strong>วันที่:</strong> ${escapeHtml(item.date || '-')}</div>
            </div>
        `;
    }

    const range = item.start_date && item.end_date && item.start_date !== item.end_date
        ? `${item.start_date} - ${item.end_date}`
        : (item.date || item.start_date || '-');

    return `
        <div class="holiday-calendar-detail text-start">
            <div><strong>ประเภทลา:</strong> ${escapeHtml(item.title || '-')}</div>
            <div><strong>วันที่:</strong> ${escapeHtml(range)}</div>
            <div><strong>จำนวนวัน:</strong> ${escapeHtml(formatHolidayCalendarDayCount(item.total_days))}</div>
            <div><strong>สถานะ:</strong> ${escapeHtml(holidayCalendarStatusLabel(item.status))}</div>
            <div><strong>เหตุผล:</strong> ${escapeHtml(item.reason || '-')}</div>
        </div>
    `;
}

function showHolidayCalendarDetail(item) {
    const title = item.type === 'approved_leave' ? 'รายละเอียดวันลา' : 'รายละเอียด';
    const html = buildHolidayCalendarDetailHtml(item);

    if (window.Swal) {
        window.Swal.fire({
            title,
            html,
            icon: item.type === 'approved_leave' ? 'info' : undefined,
            confirmButtonText: 'ปิด',
        });
        return;
    }

    alert(`${item.title || ''}\n${item.date || ''}`);
}

function buildHolidayCalendarLoadingHtml() {
    return `
        <div class="d-flex align-items-center gap-2 text-muted">
            <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
            <span>กำลังโหลดปฏิทินวันหยุด...</span>
        </div>
    `;
}

async function loadHolidayCalendar() {
    const month = document.getElementById('holidayCalendarMonth').value || new Date().toISOString().slice(0, 7);
    const summary = document.getElementById('holidayCalendarSummary');
    const empty = document.getElementById('holidayCalendarEmpty');

    summary.innerHTML = buildHolidayCalendarLoadingHtml();
    empty.style.display = 'flex';
    empty.textContent = 'กำลังโหลดปฏิทินวันหยุด...';

    try {
        const url = new URL('api/holiday_calendar_api.php', window.location.href);
        url.searchParams.set('month', month);
        const response = await fetch(url.toString());
        const res = await response.json();
        if (res.status !== 'success') throw new Error(res.message || 'Load failed');

        renderHolidayCalendar(month, res.data || []);
        summary.innerHTML = buildHolidayCalendarSummaryHtml(res.summary || {});
        empty.style.display = (res.data || []).length ? 'none' : 'flex';
        empty.textContent = (res.data || []).length ? '' : 'เดือนนี้ยังไม่มีวันหยุดที่แสดงในปฏิทิน';
    } catch (err) {
        summary.innerHTML = `<div class="alert alert-danger mb-0">โหลดปฏิทินวันหยุดไม่สำเร็จ: ${escapeHtml(err.message)}</div>`;
        empty.style.display = 'flex';
        empty.textContent = 'โหลดข้อมูลไม่สำเร็จ';
    }
}

function renderHolidayCalendar(month, rows) {
    if (!holidayCalendar) return;

    holidayCalendarDayClassMap = buildHolidayCalendarDayClassMap(rows);
    holidayCalendar.gotoDate(`${month}-01`);
    holidayCalendar.removeAllEvents();
    rows.map(buildHolidayCalendarEvent).forEach(event => holidayCalendar.addEvent(event));
    holidayCalendar.setOption('dayCellClassNames', buildHolidayCalendarOptions().dayCellClassNames);
    holidayCalendar.render();
}
