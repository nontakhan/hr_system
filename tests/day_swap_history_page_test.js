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

const requestPage = fs.readFileSync('day_swap_request.php', 'utf8');
const historyPage = fs.existsSync('day_swap_history.php') ? fs.readFileSync('day_swap_history.php', 'utf8') : '';
const approvalsPage = fs.readFileSync('day_swap_approvals.php', 'utf8');
const script = fs.readFileSync('assets/js/day_swap.js', 'utf8');
const header = fs.readFileSync('includes/header.php', 'utf8');

assertIncludes(requestPage, 'id="daySwapForm"', 'Day-swap request page should keep the request form.');
assertNotIncludes(requestPage, 'id="daySwapHistoryBody"', 'Day-swap request page should not render request history.');
assertIncludes(requestPage, 'href="day_swap_history.php"', 'Day-swap request page should include a back button to the history landing page.');
assertIncludes(requestPage, 'day-swap-request-back-link', 'Day-swap request back button should have a stable class.');

assertNotIncludes(historyPage, 'id="daySwapForm"', 'Day-swap history page should not render the request form.');
assertIncludes(historyPage, 'id="daySwapHistoryBody"', 'Day-swap history page should render the request history table.');
assertIncludes(historyPage, 'href="day_swap_request.php"', 'Day-swap history page should link back to the request page.');
assertIncludes(historyPage, 'href="day_swap_approvals.php"', 'Day-swap history page should link to the approval page for approvers.');
assertIncludes(historyPage, 'day-swap-dashboard-actions', 'Day-swap history page should expose top-right action buttons.');
assertIncludes(historyPage, 'day-swap-menu-button-request', 'Day-swap request action should have a stable user button class.');
assertIncludes(historyPage, 'day-swap-menu-button-approval', 'Day-swap approval action should have a stable admin button class.');
assertIncludes(historyPage, 'id="daySwapHistoryTable"', 'Day-swap history table should have a stable DataTables id.');

assertIncludes(approvalsPage, 'href="day_swap_history.php"', 'Day-swap approval page should include a back button to the history landing page.');
assertIncludes(approvalsPage, 'day-swap-approval-back-link', 'Day-swap approval back button should have a stable class.');
assertIncludes(approvalsPage, 'id="daySwapPendingTable"', 'Day-swap pending approval table should have a stable DataTables id.');
assertIncludes(approvalsPage, 'id="daySwapApprovalHistoryTable"', 'Day-swap approval history table should have a stable DataTables id.');

assertIncludes(script, 'if (document.getElementById(\'daySwapHistoryBody\')) {', 'Day-swap JS should initialize history-only pages.');
assertIncludes(script, 'loadDaySwapHistory();', 'Day-swap JS should load my request history.');
assertIncludes(script, 'initDaySwapDataTable', 'Day-swap JS should initialize DataTables for day-swap tables.');
assertIncludes(script, "initDaySwapDataTable('daySwapHistoryTable'", 'Day-swap history should use DataTables.');
assertIncludes(script, "initDaySwapDataTable('daySwapPendingTable'", 'Day-swap pending approvals should use DataTables.');
assertIncludes(script, "initDaySwapDataTable('daySwapApprovalHistoryTable'", 'Day-swap approval history should use DataTables.');

assertIncludes(header, 'isActive(\'day_swap_history.php\')', 'Sidebar should treat day-swap history as an active day-swap page.');
assertIncludes(header, 'href="day_swap_history.php"', 'Sidebar should link directly to the day-swap history landing page.');
assertNotIncludes(header, 'daySwapSubmenu', 'Day-swap sidebar menu should not use a submenu.');

console.log('day_swap_history_page_test passed');
