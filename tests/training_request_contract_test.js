const fs = require('fs');

function read(path) {
  return fs.readFileSync(path, 'utf8');
}

function assertIncludes(content, needle, message) {
  if (!content.includes(needle)) {
    throw new Error(`${message}\nMissing: ${needle}`);
  }
}

function assertFile(path) {
  if (!fs.existsSync(path)) {
    throw new Error(`Expected file to exist: ${path}`);
  }
}

[
  'includes/training_request_helpers.php',
  'api/training_request_api.php',
  'training_request.php',
  'training_history.php',
  'training_approvals.php',
  'assets/js/training_request.js',
].forEach(assertFile);

const helper = read('includes/training_request_helpers.php');
const api = read('api/training_request_api.php');
const header = read('includes/header.php');
const footer = read('includes/footer.php');
const requestPage = read('training_request.php');
const historyPage = read('training_history.php');
const approvalsPage = read('training_approvals.php');
const script = read('assets/js/training_request.js');
const badges = read('includes/approval_badge_helpers.php');

assertIncludes(helper, 'CREATE TABLE IF NOT EXISTS training_requests', 'Training requests should have their own table.');
assertIncludes(helper, "status ENUM('pending','pending_manager','pending_hr','approved','rejected','cancelled')", 'Training requests should use the two-step approval status set.');
assertIncludes(helper, 'trainingRequestCreateHistoryRecord', 'Helper should expose auto history creation after HR approval.');
assertIncludes(helper, 'employee_training_records', 'Approving a training request should write to employee training history.');

assertIncludes(api, "'pending_manager'", 'New employee requests should start at manager approval.');
assertIncludes(api, "SET status = 'pending_hr'", 'Manager approval should advance to HR.');
assertIncludes(api, "SET status = ?", 'HR approval should finalize the request.');
assertIncludes(api, 'trainingRequestCreateHistoryRecord', 'HR approval should create a training history record automatically.');
assertIncludes(api, "action === 'my_requests'", 'API should expose employee request history.');
assertIncludes(api, "action === 'pending' || $action === 'history'", 'API should expose approval queues.');

assertIncludes(requestPage, 'id="trainingRequestForm"', 'Request page should render the employee training request form.');
assertIncludes(requestPage, 'href="training_history.php"', 'Request page should link to training request history.');
assertIncludes(historyPage, 'id="trainingRequestHistoryBody"', 'History page should render request history table.');
assertIncludes(approvalsPage, "in_array($_SESSION['role'], ['manager', 'hr', 'admin']", 'Approvals page should be limited to approver roles.');
assertIncludes(approvalsPage, 'id="trainingRequestPendingBody"', 'Approvals page should render pending approval table.');

assertIncludes(script, 'initTrainingRequestPage', 'Frontend should initialize request page.');
assertIncludes(script, 'loadTrainingRequestHistory', 'Frontend should load employee history.');
assertIncludes(script, 'loadTrainingRequestPendingApprovals', 'Frontend should load pending approvals.');
assertIncludes(script, 'renderTrainingRequestStatus', 'Frontend should render Thai status labels.');
assertIncludes(script, 'pending_hr', 'Frontend should show HR pending status.');

assertIncludes(header, "'training' => 0", 'Sidebar badge counts should include training.');
assertIncludes(header, "href=\"training_request.php\"", 'Sidebar should link to training request page.');
assertIncludes(header, "href=\"training_history.php\"", 'Sidebar should link to training history page.');
assertIncludes(header, "href=\"training_approvals.php\"", 'Sidebar should link to training approvals page.');
assertIncludes(header, "$approvalBadgeCounts['training']", 'Sidebar should render training approval badge.');
assertIncludes(footer, 'assets/js/training_request.js', 'Footer should load training request JS.');

assertIncludes(badges, 'approvalBadgeCountTrainingRequests', 'Badge helper should count training requests.');
assertIncludes(badges, "'training'", 'Badge normalization should include training counts.');

console.log('training_request_contract_test passed');
