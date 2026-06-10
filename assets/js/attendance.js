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
});

let attendanceCalendar = null;
let attendanceCalendarDayClassMap = {};

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
            `เพิ่มใหม่ ${res.inserted} รายการ, ข้าม ${res.skipped} รายการ, ไม่พบพนักงาน ${res.unmatched} รายการ`,
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

async function initAttendanceReport(canManage) {
    const employeeSelect = document.getElementById('attendanceEmployee');
    const monthSelect = document.getElementById('attendanceMonth');
    const loadBtn = document.getElementById('attendanceLoadBtn');

    if (canManage) {
        await loadAttendanceEmployees(employeeSelect);
    } else {
        await loadAttendanceMonths(monthSelect, '');
    }
    initializeAttendanceSelect2();
    if (canManage) {
        bindAttendanceEmployeeChange(employeeSelect, monthSelect);
    }

    loadBtn.addEventListener('click', () => {
        const employeeId = canManage ? employeeSelect.value : '';
        loadAttendanceReport(employeeId, monthSelect.value);
    });
}

function bindAttendanceEmployeeChange(employeeSelect, monthSelect) {
    const loadSelectedEmployeeMonths = () => loadAttendanceMonths(monthSelect, employeeSelect.value);
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

async function loadAttendanceMonths(select, employeeId) {
    select.innerHTML = '<option value="">กำลังโหลด...</option>';
    initializeAttendanceSelect2(select);
    const url = new URL('api/attendance_api.php', window.location.href);
    url.searchParams.set('action', 'months');
    if (employeeId) url.searchParams.set('employee_id', employeeId);

    const response = await fetch(url);
    const res = await response.json();
    select.innerHTML = '<option value="">เลือกเดือน</option>';
    if (res.status !== 'success') {
        select.innerHTML = '<option value="">โหลดเดือนไม่สำเร็จ</option>';
        initializeAttendanceSelect2(select);
        return;
    }

    if (!res.data.length) {
        select.innerHTML = '<option value="">ยังไม่มีข้อมูลเดือน</option>';
    }
    res.data.forEach(item => {
        select.innerHTML += `<option value="${item.import_month}">${formatThaiMonth(item.import_month)}</option>`;
    });
    initializeAttendanceSelect2(select);
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

async function loadAttendanceReport(employeeId, month) {
    if (!month) {
        Swal.fire('แจ้งเตือน', 'กรุณาเลือกเดือน', 'warning');
        return;
    }

    const url = new URL('api/attendance_api.php', window.location.href);
    url.searchParams.set('action', 'report');
    url.searchParams.set('month', month);
    if (employeeId) url.searchParams.set('employee_id', employeeId);

    const response = await fetch(url);
    const res = await response.json();
    if (res.status !== 'success') {
        Swal.fire('Error', res.message || 'โหลดข้อมูลไม่สำเร็จ', 'error');
        return;
    }

    renderAttendanceReport(res);
}

function renderAttendanceReport(res) {
    const summary = document.getElementById('attendanceSummary');
    const counts = countAttendanceReportStatuses(res.data || []);

    const holidayTotal = (counts.regular_holiday || 0) + (counts.company_holiday || 0);
    const workdayTotal = res.data.length - holidayTotal;
    const incompleteTotal = (counts.missing_in || 0) + (counts.missing_out || 0);
    summary.innerHTML = `
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <div class="fw-bold fs-5">${res.employee.first_name_th} ${res.employee.last_name_th}</div>
                <div class="text-muted small">${formatThaiMonth(res.month)} | วันทำงาน ${workdayTotal} วัน</div>
            </div>
            <span class="badge bg-light text-dark border">รวม ${res.data.length} วัน</span>
        </div>
        <div class="row g-3">
            ${attendanceSummaryCard('ปกติ', counts.present || 0, 'success', 'fa-check-circle')}
            ${attendanceSummaryCard('สาย', counts.late || 0, 'attendance-late', 'fa-clock')}
            ${attendanceSummaryCard('ขาด', counts.absent || 0, 'danger', 'fa-circle-xmark')}
            ${attendanceSummaryCard('สแกนไม่ครบ', incompleteTotal, 'attendance-incomplete', 'fa-triangle-exclamation')}
            ${attendanceSummaryCard('ลา', counts.leave || 0, 'info', 'fa-file-signature')}
            ${attendanceSummaryCard('วันหยุดปกติ', counts.regular_holiday || 0, 'attendance-holiday', 'fa-calendar-day')}
            ${attendanceSummaryCard('วันหยุดบริษัท', counts.company_holiday || 0, 'attendance-company-holiday', 'fa-building-circle-check')}
        </div>`;

    renderAttendanceCalendar(res);
}

function countAttendanceReportStatuses(rows) {
    const counts = { present: 0, late: 0, absent: 0, missing_in: 0, missing_out: 0, holiday: 0, regular_holiday: 0, company_holiday: 0, leave: 0 };

    rows.forEach(row => {
        counts[row.status] = (counts[row.status] || 0) + 1;
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
    if (attendanceCalendar) {
        attendanceCalendar.destroy();
    }

    attendanceCalendar = new FullCalendar.Calendar(calendarEl, buildAttendanceCalendarOptions(`${res.month}-01`, events));
    attendanceCalendar.render();
}

function buildAttendanceCalendarOptions(initialDate, events) {
    return {
        initialView: 'dayGridMonth',
        initialDate,
        locale: 'th',
        firstDay: 1,
        height: 'auto',
        fixedWeekCount: false,
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
    if (status === 'company_holiday') return 'วันหยุดบริษัท';
    if (status === 'holiday') return 'วันหยุดปกติ';
    return row.status_label || '-';
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
    };
    return colors[status] || { background: '#f3f4f6', border: '#d1d5db', text: '#374151' };
}

function buildAttendanceCalendarDetails(row) {
    const note = row.holiday_name || row.leave_name || '-';
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
        </div>`;
}

function formatAttendanceDay(value) {
    const days = { Mon: 'จันทร์', Tue: 'อังคาร', Wed: 'พุธ', Thu: 'พฤหัสบดี', Fri: 'ศุกร์', Sat: 'เสาร์', Sun: 'อาทิตย์' };
    return days[value] || value;
}

function formatAttendanceTime(value) {
    return value ? value.substring(0, 5) : '-';
}
