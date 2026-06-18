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

const source = fs.readFileSync('assets/js/attendance.js', 'utf8');
const filteredBranches = buildAttendanceBranchOptionsForCompany([
    { id: 1, label: 'Bangkok', company_id: 10 },
    { id: 2, label: 'Hat Yai', company_id: 20 },
], '10');
if (filteredBranches.length !== 1 || filteredBranches[0].id !== 1) {
    console.error('Branch options should be filtered by selected company.');
    process.exit(1);
}
const noCompanyBranches = buildAttendanceBranchOptionsForCompany([
    { id: 1, label: 'Bangkok', company_id: 10 },
], '');
if (noCompanyBranches.length !== 0) {
    console.error('Branch options should stay empty until a company is selected.');
    process.exit(1);
}

const adjustmentCompanySelect = { value: '10' };
const adjustmentBranchSelect = { value: '', disabled: true, innerHTML: '' };
const originalDocument = global.document;
const originalJquery = global.$;
global.document = {
    getElementById(id) {
        if (id === 'attendanceAdjustmentCompany') return adjustmentCompanySelect;
        if (id === 'attendanceAdjustmentBranch') return adjustmentBranchSelect;
        return null;
    },
};
global.$ = element => ({
    prop(name, value) {
        element[name] = value;
        return this;
    },
    hasClass() {
        return false;
    },
    select2() {
        element.select2Refreshed = true;
        return this;
    },
    trigger(eventName) {
        element.lastTriggeredEvent = eventName;
        return this;
    },
});
global.$.fn = { select2: true };
attendanceAdjustmentFilterOptions = {
    companies: [],
    branches: [
        { id: 1, label: 'Bangkok', company_id: 10 },
        { id: 2, label: 'Hat Yai', company_id: 20 },
    ],
    positions: [],
};
updateAttendanceAdjustmentBranchOptions();
if (adjustmentBranchSelect.disabled || !adjustmentBranchSelect.innerHTML.includes('Bangkok') || adjustmentBranchSelect.innerHTML.includes('Hat Yai')) {
    console.error('Branch select should be enabled and filtered after selecting a company.');
    console.error('Actual:', adjustmentBranchSelect);
    process.exit(1);
}
if (!adjustmentBranchSelect.select2Refreshed || adjustmentBranchSelect.lastTriggeredEvent !== 'change.select2') {
    console.error('Branch Select2 should be refreshed after changing company options.');
    process.exit(1);
}
adjustmentCompanySelect.value = '';
updateAttendanceAdjustmentBranchOptions();
if (!adjustmentBranchSelect.disabled) {
    console.error('Branch select should be disabled until a company is selected.');
    process.exit(1);
}
global.document = originalDocument;
global.$ = originalJquery;

resetAttendanceAdjustmentSelectedEmployeeIds();
setAttendanceAdjustmentEmployeeSelected(15, true);
setAttendanceAdjustmentEmployeeSelected(16, true);
setAttendanceAdjustmentEmployeeSelected(15, false);
const selectedIds = getSelectedAttendanceAdjustmentEmployeeIds();
if (selectedIds.length !== 1 || selectedIds[0] !== '16') {
    console.error('Bulk selected IDs should persist independently from the current rendered table page.');
    console.error('Actual:', selectedIds);
    process.exit(1);
}

attendanceAdjustmentRows = [
    { employee_id: 21 },
    { employee_id: 22 },
    { employee_id: 23 },
];
attendanceAdjustmentDataTable = {
    rows(options) {
        if (!options || options.search !== 'applied' || options.page !== 'all') {
            console.error('Select all should use DataTable filtered rows when available.');
            process.exit(1);
        }
        return {
            nodes() {
                return [
                    { querySelector: () => ({ value: '21' }) },
                    { querySelector: () => ({ value: '23' }) },
                ];
            },
        };
    },
};
const dataTableSelectAll = { checked: false, indeterminate: false };
global.document = { querySelectorAll() { return []; }, getElementById(id) { return id === 'attendanceAdjustmentSelectAll' ? dataTableSelectAll : null; } };
resetAttendanceAdjustmentSelectedEmployeeIds();
setAllAttendanceAdjustmentEmployeesSelected(true);
const dataTableSelectedIds = getSelectedAttendanceAdjustmentEmployeeIds();
if (dataTableSelectedIds.length !== 2 || !dataTableSelectedIds.includes('21') || !dataTableSelectedIds.includes('23') || dataTableSelectedIds.includes('22')) {
    console.error('Select all should select every filtered DataTable row across pages, not hidden filtered-out rows.');
    console.error('Actual:', dataTableSelectedIds);
    process.exit(1);
}
if (!dataTableSelectAll.checked || dataTableSelectAll.indeterminate) {
    console.error('Select all checkbox should be checked when every filtered DataTable row is selected.');
    console.error('Actual:', dataTableSelectAll);
    process.exit(1);
}
attendanceAdjustmentDataTable = null;
global.document = originalDocument;

let adjustmentEmployeeFetchCount = 0;
const initElements = {};
[
    'attendanceSingleDate',
    'attendanceBulkDate',
    'attendanceAdjustmentLoadBtn',
    'attendanceAdjustmentRows',
    'attendanceAdjustmentCompany',
    'attendanceAdjustmentBranch',
    'attendanceAdjustmentPosition',
    'attendanceAdjustmentSelectAll',
    'attendanceSingleSaveForm',
    'attendanceBulkSaveForm',
].forEach(id => {
    initElements[id] = {
        id,
        value: '',
        addEventListener() {},
        querySelectorAll() { return []; },
    };
});
global.document = {
    getElementById(id) {
        return initElements[id] || null;
    },
    querySelectorAll() {
        return [];
    },
};
global.$ = selector => ({
    select2() { return this; },
    off() { return this; },
    on() { return this; },
    prop() { return this; },
    trigger() { return this; },
});
global.$.fn = { select2: true };
global.fetch = async url => {
    if (String(url).includes('action=adjustment_employees')) {
        adjustmentEmployeeFetchCount += 1;
    }
    return new Promise(() => {});
};
initAttendanceAdjustments();
if (adjustmentEmployeeFetchCount !== 0) {
    console.error('Bulk adjustment employees should not load on first page initialization.');
    console.error('Actual fetch count:', adjustmentEmployeeFetchCount);
    process.exit(1);
}
global.document = originalDocument;
global.$ = originalJquery;

assertIncludes(page, 'attendanceAdjustmentPage', 'Adjustment page should expose the JS mount point.');
assertIncludes(page, 'data-native-date-picker="true"', 'Adjustment date fields should keep native date picker behavior.');
assertIncludes(page, 'attendanceBulkSaveBtn', 'Adjustment page should include a bulk save button.');
assertIncludes(page, 'attendanceSingleSaveForm', 'Adjustment page should include a single save form.');
assertIncludes(header, 'attendance_adjustments.php', 'Sidebar should link to attendance adjustments.');
assertIncludes(source, 'loadAttendanceAdjustmentFilterOptions', 'Adjustment JS should load usable filter options.');
assertIncludes(source, 'resetAttendanceAdjustmentForm', 'Adjustment JS should clear saved form fields after successful save.');
assertIncludes(source, "on('draw", 'Adjustment DataTable should re-sync selected checkboxes when changing pages.');
assertIncludes(source, 'syncAttendanceAdjustmentRenderedCheckboxes', 'Adjustment JS should reapply selected state to rendered rows.');
assertIncludes(source, 'bindAttendanceAdjustmentSelectAll', 'Adjustment select-all checkbox should have a dedicated click binding.');
assertIncludes(source, 'columnDefs', 'Adjustment DataTable should configure non-sortable columns.');
assertIncludes(source, 'orderable: false', 'Adjustment select-all column should not be treated as a sortable DataTable header.');
assertIncludes(source, 'event.stopPropagation()', 'Adjustment select-all checkbox should not bubble clicks into DataTable sorting.');
assertIncludes(page, 'attendance-adjustment-select-all-cell', 'Adjustment select-all header should expose a wider clickable cell.');
assertIncludes(page, 'aria-label="เลือกพนักงานทั้งหมด"', 'Adjustment select-all checkbox should have an accessible label.');
assertIncludes(source, ".prop('disabled'", 'Adjustment branch Select2 should refresh disabled state through jQuery prop.');
assertIncludes(source, "select2('destroy')", 'Adjustment branch Select2 should be rebuilt after company changes so stale disabled state is cleared.');
assertIncludes(source, 'refreshAttendanceAdjustmentSelect2', 'Adjustment JS should centralize Select2 refresh after replacing options.');
assertIncludes(source, 'bindAttendanceAdjustmentFilterChange', 'Adjustment filters should share one change binding helper.');
assertIncludes(source, 'select2:select.attendanceAdjustment', 'Adjustment Select2 filters should handle Select2 select events.');
assertIncludes(source, 'select2:clear.attendanceAdjustment', 'Adjustment Select2 filters should handle Select2 clear events.');
assertIncludes(fs.readFileSync('includes/footer.php', 'utf8'), "attendance.js?v=", 'Footer should cache-bust attendance.js after adjustment UI fixes.');

console.log('attendance_adjustments_ui_test passed');
