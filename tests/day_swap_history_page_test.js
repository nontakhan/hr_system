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
const script = fs.readFileSync('assets/js/day_swap.js', 'utf8');
const header = fs.readFileSync('includes/header.php', 'utf8');

assertIncludes(requestPage, 'id="daySwapForm"', 'Day-swap request page should keep the request form.');
assertNotIncludes(requestPage, 'id="daySwapHistoryBody"', 'Day-swap request page should not render request history.');
assertIncludes(requestPage, 'href="day_swap_history.php"', 'Day-swap request page should provide a direct history link.');

assertNotIncludes(historyPage, 'id="daySwapForm"', 'Day-swap history page should not render the request form.');
assertIncludes(historyPage, 'id="daySwapHistoryBody"', 'Day-swap history page should render the request history table.');
assertIncludes(historyPage, 'href="day_swap_request.php"', 'Day-swap history page should link back to the request page.');

assertIncludes(script, 'if (document.getElementById(\'daySwapHistoryBody\')) {', 'Day-swap JS should initialize history-only pages.');
assertIncludes(script, 'loadDaySwapHistory();', 'Day-swap JS should load my request history.');

assertIncludes(header, 'isActive(\'day_swap_history.php\')', 'Sidebar should treat day-swap history as an active day-swap page.');
assertIncludes(header, 'href="day_swap_history.php"', 'Sidebar should include a day-swap history menu item.');

console.log('day_swap_history_page_test passed');
