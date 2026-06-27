const fs = require('fs');

function assertIncludes(text, expected, message) {
    if (!text.includes(expected)) {
        console.error(message);
        console.error('Missing:', expected);
        process.exit(1);
    }
}

function assertNotIncludes(text, unexpected, message) {
    if (text.includes(unexpected)) {
        console.error(message);
        console.error('Unexpected:', unexpected);
        process.exit(1);
    }
}

const requestPage = fs.readFileSync('late_early_request.php', 'utf8');
const historyPage = fs.readFileSync('late_early_history.php', 'utf8');
const script = fs.readFileSync('assets/js/late_early_request.js', 'utf8');
const header = fs.readFileSync('includes/header.php', 'utf8');
const styles = fs.readFileSync('assets/style.css', 'utf8');

assertIncludes(requestPage, 'id="lateEarlyRequestForm"', 'Late/early request page should keep the submit form.');
assertNotIncludes(requestPage, 'id="lateEarlyHistoryBody"', 'Late/early request page should not render request history.');
assertNotIncludes(requestPage, 'id="refreshTimeRequestsBtn"', 'Late/early request page should not render the history refresh button.');
assertIncludes(requestPage, 'class="time-request-shell"', 'Late/early request page should use the balanced form shell.');
assertNotIncludes(requestPage, 'time-request-guidance', 'Late/early request page should not render the explanatory guidance panel.');
assertNotIncludes(requestPage, 'href="late_early_history.php"', 'Late/early request page should not be the workflow landing page.');
assertIncludes(styles, 'width: 80%;', 'Late/early request form shell should use 80 percent of the content area.');
assertIncludes(styles, 'margin-left: auto;', 'Late/early request form shell should be centered horizontally.');
assertIncludes(styles, 'margin-right: auto;', 'Late/early request form shell should be centered horizontally.');
assertIncludes(styles, 'grid-template-columns: 1fr;', 'Late/early request form shell should use a single-column layout.');

assertNotIncludes(historyPage, 'id="lateEarlyRequestForm"', 'Late/early history page should not render the submit form.');
assertIncludes(historyPage, 'id="lateEarlyHistoryBody"', 'Late/early history page should render the request history table.');
assertIncludes(historyPage, 'id="refreshTimeRequestsBtn"', 'Late/early history page should include a refresh button.');
assertIncludes(historyPage, 'href="late_early_request.php"', 'Late/early history page should expose the user request action.');
assertIncludes(historyPage, 'href="late_early_approvals.php"', 'Late/early history page should expose the approval action for manager/admin/hr users.');
assertIncludes(historyPage, 'time-request-dashboard-actions', 'Late/early history page should group top-right workflow actions.');
assertIncludes(historyPage, 'time-request-menu-button-request', 'Late/early user action should use the request button treatment.');
assertIncludes(historyPage, 'time-request-menu-button-approval', 'Late/early admin/HR action should use the approval button treatment.');
assertIncludes(historyPage, 'id="lateEarlyHistoryTable"', 'Late/early history table should have a stable DataTables id.');

assertIncludes(script, 'if (historyBody) loadTimeRequestHistory();', 'Late/early JS should load history when the history table exists.');
assertIncludes(script, 'if (form) {', 'Late/early JS should allow history-only pages without the submit form.');
assertIncludes(script, 'initLateEarlyHistoryDataTable', 'Late/early history JS should initialize DataTables for the history table.');
assertIncludes(script, 'resetLateEarlyHistoryDataTable', 'Late/early history JS should reset DataTables before replacing rows.');
assertIncludes(script, 'getTimeRequestHistoryTableSelector', 'Late/early history table should resolve its DataTables target through a shared helper.');
assertIncludes(script, "return '#lateEarlyHistoryTable';", 'Late/early history table should use DataTables.');
assertIncludes(script, '$(selector).DataTable', 'Late/early history table should initialize DataTables.');

assertIncludes(header, 'isActive(\'late_early_history.php\')', 'Sidebar should treat late/early history as an active time-request page.');
assertIncludes(header, 'href="late_early_history.php"', 'Sidebar should link the late/early menu directly to history.');
assertNotIncludes(header, 'href="#timeRequestSubmenu"', 'Sidebar should not use a late/early submenu collapse.');
assertNotIncludes(header, 'id="timeRequestSubmenu"', 'Sidebar should not render a late/early submenu container.');

console.log('late_early_history_page_test passed');
