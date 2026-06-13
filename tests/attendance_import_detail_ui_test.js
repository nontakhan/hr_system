const fs = require('fs');
const vm = require('vm');

global.document = {
    addEventListener() {},
};

vm.runInThisContext(fs.readFileSync('assets/js/utils.js', 'utf8'));
vm.runInThisContext(fs.readFileSync('assets/js/attendance.js', 'utf8'));
const page = fs.readFileSync('attendance_import.php', 'utf8');

function assertIncludes(text, expected, message) {
    if (!String(text).includes(expected)) {
        console.error(message);
        console.error('Missing:', expected);
        console.error('Actual: ', text);
        process.exit(1);
    }
}

function assertNotIncludes(text, unexpected, message) {
    if (String(text).includes(unexpected)) {
        console.error(message);
        console.error('Unexpected:', unexpected);
        console.error('Actual:    ', text);
        process.exit(1);
    }
}

const rowHtml = buildAttendanceImportDetailRowHtml({
    employee_id: 7,
    full_name: 'Somchai Jaidee',
    citizen_id: '1234567890123',
    position_name_th: 'Developer',
    branch_name_th: 'Bangkok',
    company_name_th: 'ACME',
    record_count: 22,
    first_work_date: '2026-05-01',
    latest_work_date: '2026-05-31',
});

assertIncludes(rowHtml, 'Somchai Jaidee', 'Import detail row should show employee name.');
assertIncludes(rowHtml, 'Developer', 'Import detail row should show employee position.');
assertIncludes(rowHtml, 'Bangkok', 'Import detail row should show employee branch.');
assertIncludes(rowHtml, 'ACME', 'Import detail row should show employee company.');
assertIncludes(rowHtml, '1234567890123', 'Import detail row should keep citizen ID.');
assertNotIncludes(rowHtml, 'employee_id', 'Import detail row should not expose the employee ID field.');
assertNotIncludes(rowHtml, 'รหัสพนักงาน', 'Import detail row should not show employee code text.');
assertIncludes(page, 'modal-dialog modal-xl modal-dialog-scrollable', 'Import detail modal should be extra wide for dense employee data.');

console.log('attendance_import_detail_ui_test passed');
