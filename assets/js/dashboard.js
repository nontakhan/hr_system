/*
 * Logic สำหรับหน้า Dashboard 
 * (Updated: Replace top stats with Company/Branch Breakdown)
 */

// สีชุดเดิมสำหรับกราฟ
const CHART_COLORS = ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', '#858796', '#6610f2', '#fd7e14', '#20c997'];
let dbCompanyColors = {};

document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('companyBranchStatsContainer') || document.getElementById('employeeDashboardContainer')) {
        loadDashboardData();
    }
});

async function loadDashboardData() {
    try {
        const response = await fetch('api/dashboard_api.php');
        const res = await response.json();

        if (res.status === 'success') {
            const data = res.data;
            dbCompanyColors = data.company_colors_map || {};

            if (document.getElementById('employeeDashboardContainer')) {
                renderEmployeeDashboard(data.personal_dashboard);
                return;
            }

            // 1. (NEW) Render Company & Branch Stats (แทนที่ Top Cards เดิม)
            renderCompanyBranchCards(data.branch_summary);

            // 2. Render Chart (เหมือนเดิม)
            renderEmployeeTypeChart(data.employee_types_by_company);

            // 3. Render Right List (เหมือนเดิม)
            renderCompanySummary(data.employee_types_by_company);
        }
    } catch (err) { console.error('Dashboard Error:', err); }
}

// --- Helper Functions ---
function getPaletteColor(index) { return CHART_COLORS[index % CHART_COLORS.length]; }

function getCompanyColor(name) {
    if (dbCompanyColors[name]) return dbCompanyColors[name];
    let hash = 0;
    for (let i = 0; i < name.length; i++) hash = name.charCodeAt(i) + ((hash << 5) - hash);
    return CHART_COLORS[Math.abs(hash) % CHART_COLORS.length];
}

function hexToRgba(hex, alpha) {
    const r = parseInt(hex.slice(1, 3), 16);
    const g = parseInt(hex.slice(3, 5), 16);
    const b = parseInt(hex.slice(5, 7), 16);
    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}

// --- (NEW) ฟังก์ชันสร้างการ์ดสรุป สาขาแยกตามบริษัท ---
function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, char => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    }[char]));
}

function formatMonthLabel(month) {
    if (!month || !/^\d{4}-\d{2}$/.test(month)) return '-';
    const [year, monthNumber] = month.split('-').map(Number);
    return `${String(monthNumber).padStart(2, '0')}/${year + 543}`;
}

function formatDateLabel(dateText) {
    if (!dateText) return '-';
    const parts = dateText.split('-');
    if (parts.length !== 3) return escapeHtml(dateText);
    return `${parts[2]}/${parts[1]}/${parseInt(parts[0], 10) + 543}`;
}

function getLeaveStatusBadge(status) {
    const map = {
        pending: ['รออนุมัติ', 'warning'],
        approved: ['อนุมัติแล้ว', 'success'],
        rejected: ['ไม่อนุมัติ', 'danger'],
        cancelled: ['ยกเลิก', 'secondary']
    };
    const [label, color] = map[status] || [status || '-', 'secondary'];
    return `<span class="badge bg-${color}">${escapeHtml(label)}</span>`;
}

function renderEmployeeDashboard(data) {
    const container = document.getElementById('employeeDashboardContainer');
    if (!container) return;

    if (!data) {
        container.innerHTML = '<div class="col-12 text-center text-muted py-4">ไม่พบข้อมูลส่วนตัว</div>';
        return;
    }

    const profile = data.profile || {};
    const attendance = data.attendance || {};
    const leaveSummary = data.leave_summary || {};
    const recentLeaves = data.recent_leaves || [];
    const recentLeavesHtml = recentLeaves.length
        ? recentLeaves.map(item => `
            <div class="list-group-item px-0 d-flex justify-content-between align-items-start">
                <div>
                    <div class="fw-semibold">${escapeHtml(item.type_name)}</div>
                    <small class="text-muted">${formatDateLabel(item.start_date)} - ${formatDateLabel(item.end_date)} (${escapeHtml(item.total_days)} วัน)</small>
                </div>
                ${getLeaveStatusBadge(item.status)}
            </div>
        `).join('')
        : '<div class="text-muted py-3">ยังไม่มีประวัติการลา</div>';

    container.innerHTML = `
        <div class="col-xl-4 col-lg-6">
            <div class="card shadow-sm border-0 h-100 dashboard-personal-card">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 text-primary"><i class="fas fa-id-card me-2"></i>ข้อมูลของฉัน</h5>
                </div>
                <div class="card-body">
                    <h4 class="mb-1">${escapeHtml(profile.full_name)}</h4>
                    <div class="text-muted mb-3">${escapeHtml(profile.position_name)}</div>
                    <div class="dashboard-profile-list">
                        <div><span>บริษัท</span><strong>${escapeHtml(profile.company_name)}</strong></div>
                        <div><span>สาขา</span><strong>${escapeHtml(profile.branch_name)}</strong></div>
                        <div><span>แผนก</span><strong>${escapeHtml(profile.department_name)}</strong></div>
                        <div><span>หัวหน้างาน</span><strong>${escapeHtml(profile.supervisor_name)}</strong></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-lg-6">
            <div class="card shadow-sm border-0 h-100 dashboard-personal-card">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 text-primary"><i class="fas fa-clock me-2"></i>ลงเวลาเดือนนี้</h5>
                    <small class="text-muted">${formatMonthLabel(attendance.month)}</small>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="personal-stat-box">
                                <span>วันที่มีข้อมูล</span>
                                <strong>${escapeHtml(attendance.recorded_days || 0)}</strong>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="personal-stat-box warning">
                                <span>ข้อมูลไม่ครบ</span>
                                <strong>${escapeHtml(attendance.incomplete_days || 0)}</strong>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3 text-muted small">ลงเวลาล่าสุด: ${formatDateLabel(attendance.latest_work_date)}</div>
                    <a href="attendance.php" class="btn btn-outline-danger btn-sm mt-3"><i class="fas fa-calendar-check me-1"></i> ดูรายละเอียดลงเวลา</a>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-lg-12">
            <div class="card shadow-sm border-0 h-100 dashboard-personal-card">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 text-primary"><i class="fas fa-calendar-alt me-2"></i>สรุปการลาของฉัน</h5>
                </div>
                <div class="card-body">
                    <div class="leave-summary-grid mb-3">
                        <div><span>รออนุมัติ</span><strong>${escapeHtml(leaveSummary.pending || 0)}</strong></div>
                        <div><span>อนุมัติแล้ว</span><strong>${escapeHtml(leaveSummary.approved || 0)}</strong></div>
                        <div><span>ไม่อนุมัติ</span><strong>${escapeHtml(leaveSummary.rejected || 0)}</strong></div>
                        <div><span>ยกเลิก</span><strong>${escapeHtml(leaveSummary.cancelled || 0)}</strong></div>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="leave_request.php" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i> ยื่นใบลา</a>
                        <a href="my_leaves.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-history me-1"></i> ประวัติการลา</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0"><i class="fas fa-list-check me-2"></i>รายการลาล่าสุด</h5>
                </div>
                <div class="card-body pt-2">
                    <div class="list-group list-group-flush">${recentLeavesHtml}</div>
                </div>
            </div>
        </div>
    `;
}

function renderCompanyBranchCards(data) {
    const container = document.getElementById('companyBranchStatsContainer');
    if (!container) return;
    
    container.innerHTML = '';

    if (Object.keys(data).length === 0) {
        container.innerHTML = '<div class="col-12 text-center text-muted">ไม่พบข้อมูล</div>';
        return;
    }

    // วนลูปแต่ละบริษัท สร้างเป็น Card
    for (const [companyName, branches] of Object.entries(data)) {
        const color = getCompanyColor(companyName);
        const totalEmp = branches.reduce((sum, b) => sum + parseInt(b.count), 0);
        
        // สร้างลิสต์สาขาภายในการ์ด
        let branchListHtml = '';
        branches.forEach(b => {
            branchListHtml += `
                <div class="d-flex justify-content-between align-items-center mb-1 border-bottom pb-1">
                    <small class="text-muted text-truncate" style="max-width: 70%;">${b.branch}</small>
                    <span class="badge bg-light text-dark border">${b.count}</span>
                </div>
            `;
        });

        const html = `
            <div class="col-md-6 col-xl-3">
                <div class="card h-100 shadow-sm border-0" style="border-top: 4px solid ${color} !important;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h6 class="fw-bold mb-0 text-dark text-truncate" title="${companyName}">${companyName}</h6>
                                <small class="text-muted">พนักงานรวม</small>
                            </div>
                            <span class="badge rounded-pill fs-5" style="background-color: ${color};">${totalEmp}</span>
                        </div>
                        
                        <div class="mt-3 pt-2">
                            ${branchListHtml}
                        </div>
                    </div>
                </div>
            </div>
        `;
        container.innerHTML += html;
    }
}

// --- (ฟังก์ชันเดิม: Chart & List Summary) ---
function renderEmployeeTypeChart(groupedData) {
    const container = document.getElementById('employeeTypeSummaryContainer');
    if (!container) return;
    container.style.height = '350px'; 
    container.innerHTML = '<canvas id="empTypeChart"></canvas>';
    const ctx = document.getElementById('empTypeChart').getContext('2d');
    
    if (Object.keys(groupedData).length === 0) return;

    const companies = Object.keys(groupedData);
    const allTypes = new Set();
    Object.values(groupedData).forEach(types => types.forEach(t => allTypes.add(t.type)));
    const uniqueTypes = Array.from(allTypes);

    const datasets = uniqueTypes.map((type, typeIndex) => {
        const color = getPaletteColor(typeIndex);
        const data = companies.map(comp => {
            const found = groupedData[comp].find(t => t.type === type);
            return found ? parseInt(found.count) : 0;
        });
        return {
            label: type, data: data, backgroundColor: color, borderWidth: 0, stack: 'Stack 0',
        };
    });

    new Chart(ctx, {
        type: 'bar',
        data: { labels: companies, datasets: datasets },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } } },
            scales: { x: { stacked: true, grid: { display: false } }, y: { stacked: true, beginAtZero: true } }
        }
    });
}

function renderCompanySummary(groupedData) {
    const container = document.getElementById('todayLeaveList');
    if (!container) return;
    container.innerHTML = '';
    
    const cardHeader = container.closest('.card')?.querySelector('.card-header h5');
    if (cardHeader) cardHeader.innerHTML = '<i class="fas fa-chart-pie me-2"></i> สรุปภาพรวม';
    const dateLabel = container.closest('.card')?.querySelector('.card-header small');
    if (dateLabel) dateLabel.style.display = 'none';

    for (const [companyName, types] of Object.entries(groupedData)) {
        const total = types.reduce((sum, t) => sum + parseInt(t.count), 0);
        const color = getCompanyColor(companyName);
        const bgColor = hexToRgba(color, 0.1);

        container.innerHTML += `
            <div class="list-group-item d-flex justify-content-between align-items-center py-3 px-3 border-0 border-bottom">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle me-3 d-flex align-items-center justify-content-center" 
                         style="background-color: ${bgColor}; color: ${color}; width: 40px; height: 40px; font-weight:bold;">
                         ${companyName.charAt(0)}
                    </div>
                    <div>
                        <h6 class="mb-0 text-dark">${companyName}</h6>
                        <small class="text-muted" style="font-size: 0.8rem;">รวมทุกประเภท</small>
                    </div>
                </div>
                <span class="badge rounded-pill" style="background-color: ${color};">${total} คน</span>
            </div>
        `;
    }
}
