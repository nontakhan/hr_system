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
].forEach((id) => assertIncludes(page, `id="${id}"`, `Report page should provide ${id}.`));
assertIncludes(page, 'colspan="9"', 'Report states should span all nine columns.');
assertIncludes(page, 'รายงานการลา', 'Report should include its Thai title.');
assertIncludes(script, "action: 'approved_leave_report'", 'Renderer should call the report action.');
assertIncludes(script, 'approved_leave_report_filters', 'Renderer should load scoped options.');
assertIncludes(script, 'response.text()', 'Renderer should detect empty responses.');
assertIncludes(script, 'escapeHtml', 'Renderer should escape database values.');
assertIncludes(script, 'DataTable', 'Renderer should support DataTables.');
assertIncludes(footer, 'assets/js/leave_report.js', 'Shared footer should load the report script.');

console.log('leave_report_ui_test passed');
