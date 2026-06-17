const fs = require('fs');
const vm = require('vm');

global.document = { addEventListener() {} };

vm.runInThisContext(fs.readFileSync('assets/js/utils.js', 'utf8'));
vm.runInThisContext(fs.readFileSync('assets/js/attendance.js', 'utf8'));

const page = fs.readFileSync('attendance_adjustments.php', 'utf8');
const header = fs.readFileSync('includes/header.php', 'utf8');

function assertIncludes(text, expected, message) {
    if (!String(text).includes(expected)) {
        console.error(message);
        console.error('Missing:', expected);
        process.exit(1);
    }
}

const row = buildAttendanceAdjustmentEmployeeRowHtml({
    employee_id: 15,
    first_name_th: 'Somchai',
    last_name_th: 'Jaidee',
    citizen_id: '1234567890123',
    position_name_th: 'Technician',
    branch_name_th: 'Bangkok',
    company_name_th: 'ACME',
    raw_check_in: null,
    raw_check_out: '17:05:00',
    override_check_in: '08:00:00',
    override_check_out: null,
    override_reason: 'Scanner failed',
});

assertIncludes(row, 'Somchai Jaidee', 'Adjustment row should show full employee name.');
assertIncludes(row, 'Technician', 'Adjustment row should show position.');
assertIncludes(row, 'Bangkok', 'Adjustment row should show branch.');
assertIncludes(row, 'ACME', 'Adjustment row should show company.');
assertIncludes(row, '17:05', 'Adjustment row should show raw checkout.');
assertIncludes(row, '08:00', 'Adjustment row should show existing override check-in.');
assertIncludes(row, 'Scanner failed', 'Adjustment row should show existing override reason.');
assertIncludes(row, 'type="checkbox"', 'Adjustment row should be selectable for bulk saves.');

assertIncludes(page, 'attendanceAdjustmentPage', 'Adjustment page should expose the JS mount point.');
assertIncludes(page, 'data-native-date-picker="true"', 'Adjustment date fields should keep native date picker behavior.');
assertIncludes(page, 'attendanceBulkSaveBtn', 'Adjustment page should include a bulk save button.');
assertIncludes(page, 'attendanceSingleSaveForm', 'Adjustment page should include a single save form.');
assertIncludes(header, 'attendance_adjustments.php', 'Sidebar should link to attendance adjustments.');
assertIncludes(fs.readFileSync('assets/js/attendance.js', 'utf8'), 'loadAttendanceAdjustmentFilterOptions', 'Adjustment JS should load usable filter options.');

console.log('attendance_adjustments_ui_test passed');
