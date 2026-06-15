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
assertIncludes(requestPage, 'href="late_early_history.php"', 'Late/early request page should provide a direct history link.');
assertIncludes(styles, 'width: 80%;', 'Late/early request form shell should use 80 percent of the content area.');
assertIncludes(styles, 'margin-left: auto;', 'Late/early request form shell should be centered horizontally.');
assertIncludes(styles, 'margin-right: auto;', 'Late/early request form shell should be centered horizontally.');
assertIncludes(styles, 'grid-template-columns: 1fr;', 'Late/early request form shell should use a single-column layout.');

assertNotIncludes(historyPage, 'id="lateEarlyRequestForm"', 'Late/early history page should not render the submit form.');
assertIncludes(historyPage, 'id="lateEarlyHistoryBody"', 'Late/early history page should render the request history table.');
assertIncludes(historyPage, 'id="refreshTimeRequestsBtn"', 'Late/early history page should include a refresh button.');

assertIncludes(script, 'if (historyBody) loadTimeRequestHistory();', 'Late/early JS should load history when the history table exists.');
assertIncludes(script, 'if (form) {', 'Late/early JS should allow history-only pages without the submit form.');

assertIncludes(header, 'isActive(\'late_early_history.php\')', 'Sidebar should treat late/early history as an active time-request page.');
assertIncludes(header, 'href="late_early_history.php"', 'Sidebar should include a late/early history menu item.');

console.log('late_early_history_page_test passed');
