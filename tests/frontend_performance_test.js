// Read-only audit probes. No browser, network, database, or application writes.
// Run: node docs/audits/2026-09-19/frontend-probes.js
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
const read = file => fs.readFileSync(path.join(root, file), 'utf8');
const scripts = ['utils.js', ...fs.readdirSync(path.join(root, 'assets/js')).filter(f => f.endsWith('.js') && !['utils.js','proxy_request.js','activity_types.js','employee_warnings.js','login.js'].includes(f))].map(f => 'assets/js/' + f);
function context() {
    return vm.createContext({
        document: {addEventListener() {}, getElementById() { return null; }, querySelectorAll() { return []; }, querySelector() { return null; }},
        window: {addEventListener() {}}, console, URLSearchParams, URL, setTimeout, clearTimeout,
    });
}
function load(ctx, file) { vm.runInContext(read(file), ctx, {filename: file}); }
async function main() {
    const single = context();
    load(single, 'assets/js/utils.js');
    load(single, 'assets/js/leave_request.js');
    const combined = context();
    scripts.forEach(file => load(combined, file));
    const item = {type_name: 'Annual leave', limit_days: 10, approved_days: 2,
        pending_days: 0, usage_percent: 20, remaining_days: 8,
        projectedSelectedDays: 1, projected_total_days: 3,
        projected_usage_percent: 30, projected_remaining_days: 7};
    single.probeItem = item;
    combined.probeItem = item;
    const isolatedHtml = vm.runInContext('renderTypeLeaveUsageCard(probeItem)', single);
    const combinedHtml = vm.runInContext('renderTypeLeaveUsageCard(probeItem)', combined);
    if (!combinedHtml.includes('หลังส่ง') || vm.runInContext('escapeHtml(null)', combined) !== '') throw new Error('Global helper collision');
    console.log(JSON.stringify({probe: 'footer_load_order', script_count: scripts.length,
        raw_bytes: scripts.reduce((n, file) => n + fs.statSync(path.join(root, file)).size, 0),
        isolated_projection_visible: isolatedHtml.includes('หลังส่ง'),
        combined_projection_visible: combinedHtml.includes('หลังส่ง'),
        isolated_escape_null: vm.runInContext('escapeHtml(null)', single),
        combined_escape_null: vm.runInContext('escapeHtml(null)', combined)}));
    // Complete two synthetic report requests in reverse order, without a browser.
    const report = context();
    const elements = {attendanceMissingSummary: {innerHTML: ''}, attendanceMissingAppliedFilters: {textContent: ''}, attendanceMissingRows: {innerHTML: ''}, attendanceMissingMonth: {value: '2026-07'}};
    report.document.getElementById = id => elements[id] || null;
    const pending = [];
    report.fetch = url => new Promise(resolve => pending.push({url, resolve}));
    load(report, 'assets/js/utils.js');
    load(report, 'assets/js/attendance.js');
    vm.runInContext("resetAttendanceMissingDataTable = () => {}; renderAttendanceMissingSummary = () => {}; renderAttendanceMissingRows = rows => { globalThis.probeRendered = rows[0].marker; };", report);
    const first = vm.runInContext('loadAttendanceMissingReport()', report);
    elements.attendanceMissingMonth.value = '2026-08';
    const second = vm.runInContext('loadAttendanceMissingReport()', report);
    elements.attendanceMissingMonth.value = '2026-09';
    const response = marker => ({text: async () => JSON.stringify({status: 'success', data: [{marker}], summary: {}})});
    pending[1].resolve(response('newer'));
    await second;
    pending[0].resolve(response('older'));
    await first;
    if (!elements.attendanceMissingAppliedFilters.textContent.includes('2026-08') || elements.attendanceMissingAppliedFilters.textContent.includes('2026-09')) throw new Error('Applied filter snapshot must match the rendered request, not edited controls');
    if (report.probeRendered !== 'newer') throw new Error('Stale report overwritten newer response');
    console.log(JSON.stringify({probe: 'out_of_order_report', requests: pending.length,
        final_render: report.probeRendered, stale_response_overwrites_newer: report.probeRendered === 'older'}));
}
main().catch(error => { console.error(error); process.exitCode = 1; });
