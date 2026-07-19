const fs = require('fs');
const assert = require('assert');

const attendance = fs.readFileSync('assets/js/attendance.js', 'utf8');
const leave = fs.readFileSync('assets/js/leave_report.js', 'utf8');
const missingPage = fs.readFileSync('attendance_missing_report.php', 'utf8');
const lateEarlyPage = fs.readFileSync('attendance_late_early_report.php', 'utf8');
const leavePage = fs.readFileSync('leave_report.php', 'utf8');

function functionBody(source, name) {
    const marker = `function ${name}(`;
    const start = source.indexOf(marker);
    assert.notStrictEqual(start, -1, `${name} should exist`);
    const open = source.indexOf('{', start);
    let depth = 0;

    for (let index = open; index < source.length; index += 1) {
        if (source[index] === '{') depth += 1;
        if (source[index] === '}') depth -= 1;
        if (depth === 0) return source.slice(open + 1, index);
    }

    throw new Error(`Could not parse ${name}`);
}

function assertManualSearch(source, config) {
    const init = functionBody(source, config.init);
    const options = functionBody(source, config.options);
    const loaderReferences = init.match(new RegExp(`\\b${config.loader}\\b`, 'g')) || [];

    assert.ok(
        init.includes(`getElementById('${config.button}')?.addEventListener('click', ${config.loader})`)
            || init.includes(`if (loadBtn) loadBtn.addEventListener('click', ${config.loader})`),
        `${config.init} should load the report from its button`
    );
    assert.strictEqual(
        loaderReferences.length,
        1,
        `${config.init} should reference ${config.loader} only in the load-button binding`
    );
    assert.ok(!options.includes(`${config.loader}()`), `${config.options} must not auto-load report rows`);
}

assertManualSearch(attendance, {
    init: 'initAttendanceMissingReport',
    options: 'loadAttendanceMissingFilterOptions',
    button: 'attendanceMissingLoadBtn',
    loader: 'loadAttendanceMissingReport',
});
assertManualSearch(attendance, {
    init: 'initAttendanceLateEarlyReport',
    options: 'loadAttendanceLateEarlyFilterOptions',
    button: 'attendanceLateEarlyLoadBtn',
    loader: 'loadAttendanceLateEarlyReport',
});
assertManualSearch(leave, {
    init: 'initApprovedLeaveReport',
    options: 'loadApprovedLeaveReportOptions',
    button: 'approvedLeaveReportLoadBtn',
    loader: 'loadApprovedLeaveReport',
});

assert.ok(functionBody(attendance, 'initAttendanceMissingReport').includes('updateAttendanceMissingBranchOptions()'));
assert.ok(functionBody(attendance, 'initAttendanceLateEarlyReport').includes('updateAttendanceLateEarlyBranchOptions()'));
assert.ok(functionBody(leave, 'initApprovedLeaveReport').includes('updateApprovedLeaveReportBranches()'));

[missingPage, lateEarlyPage, leavePage].forEach((page) => {
    assert.ok(page.includes('เลือกเดือนแล้วแสดงรายงาน'), 'Each report should retain its initial manual-search instruction');
});

console.log('report_manual_search_ui_test passed');
