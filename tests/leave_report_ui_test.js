const fs = require('fs');

function assertIncludes(text, expected, message) {
    if (!text.includes(expected)) {
        throw new Error(`${message}\nMissing: ${expected}`);
    }
}

const page = fs.readFileSync('leave_report.php', 'utf8');
const script = fs.readFileSync('assets/js/leave_report.js', 'utf8');
const footer = fs.readFileSync('includes/footer.php', 'utf8');

[
    'approvedLeaveReportPage',
    'approvedLeaveReportMonth',
    'approvedLeaveReportCompany',
    'approvedLeaveReportBranch',
    'approvedLeaveReportType',
    'approvedLeaveReportRows',
    'approvedLeaveWarningBulkBtn',
    'approvedLeaveWarningSelectedCount',
    'approvedLeaveWarningSelectAll',
].forEach((id) => assertIncludes(page, `id="${id}"`, `Report page should provide ${id}.`));
assertIncludes(page, 'colspan="10"', 'Report states should span all ten columns.');
assertIncludes(page, 'รายงานการลา', 'Report should include its Thai title.');
assertIncludes(script, "action: 'approved_leave_report'", 'Renderer should call the report action.');
assertIncludes(script, 'approved_leave_report_filters', 'Renderer should load scoped options.');
assertIncludes(script, 'response.text()', 'Renderer should detect empty responses.');
assertIncludes(script, 'escapeHtml', 'Renderer should escape database values.');
assertIncludes(script, 'DataTable', 'Renderer should support DataTables.');
assertIncludes(script, 'approvedLeaveWarningBulk', 'Leave report needs a shared-controller adapter.');
assertIncludes(script, 'warning_source_key', 'Leave report selection must use request-day keys.');
assertIncludes(script, 'leave_date', 'Generated warning detail must identify the expanded leave day.');
assertIncludes(script, 'approvedLeaveWarningBulk?.replaceRows([])', 'Leave report errors must discard stale selectable rows.');
assertIncludes(footer, 'assets/js/leave_report.js', 'Shared footer should load the report script.');

console.log('leave_report_ui_test passed');
