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
assertIncludes(helper, 'e.profile_img_url AS employee_profile_img_url', 'Training approval API should return employee profile images.');

assertIncludes(api, "'pending_manager'", 'New employee requests should start at manager approval.');
assertIncludes(api, "SET status = 'pending_hr'", 'Manager approval should advance to HR.');
assertIncludes(api, "SET status = ?", 'HR approval should finalize the request.');
assertIncludes(api, 'trainingRequestCreateHistoryRecord', 'HR approval should create a training history record automatically.');
assertIncludes(api, "action === 'my_requests'", 'API should expose employee request history.');
assertIncludes(api, "action === 'pending' || $action === 'history'", 'API should expose approval queues.');

assertIncludes(requestPage, 'id="trainingRequestForm"', 'Request page should render the employee training request form.');
assertIncludes(requestPage, 'href="training_history.php"', 'Training request page should include a back button to the history landing page.');
assertIncludes(requestPage, 'training-request-back-link', 'Training request back button should have a stable class.');
assertIncludes(historyPage, 'id="trainingRequestHistoryBody"', 'History page should render request history table.');
assertIncludes(historyPage, 'id="trainingRequestHistoryTable"', 'Training history table should have a stable DataTables id.');
assertIncludes(historyPage, 'href="training_request.php"', 'Training history page should link to the request page.');
assertIncludes(historyPage, 'href="training_approvals.php"', 'Training history page should link to the approval page for approvers.');
assertIncludes(historyPage, 'training-dashboard-actions', 'Training history page should expose top-right action buttons.');
assertIncludes(historyPage, 'training-menu-button-request', 'Training request action should have a stable user button class.');
assertIncludes(historyPage, 'training-menu-button-approval', 'Training approval action should have a stable admin button class.');
assertIncludes(approvalsPage, "in_array($_SESSION['role'], ['manager', 'hr', 'admin']", 'Approvals page should be limited to approver roles.');
assertIncludes(approvalsPage, 'id="trainingRequestPendingBody"', 'Approvals page should render pending approval table.');
assertIncludes(approvalsPage, 'href="training_history.php"', 'Training approval page should include a back button to the history landing page.');
assertIncludes(approvalsPage, 'training-approval-back-link', 'Training approval back button should have a stable class.');
assertIncludes(approvalsPage, 'id="trainingRequestPendingTable"', 'Training pending approval table should have a stable DataTables id.');
assertIncludes(approvalsPage, 'id="trainingRequestApprovalHistoryTable"', 'Training approval history table should have a stable DataTables id.');

assertIncludes(script, 'initTrainingRequestPage', 'Frontend should initialize request page.');
assertIncludes(script, 'loadTrainingRequestHistory', 'Frontend should load employee history.');
assertIncludes(script, 'loadTrainingRequestPendingApprovals', 'Frontend should load pending approvals.');
assertIncludes(script, 'renderTrainingRequestStatus', 'Frontend should render Thai status labels.');
assertIncludes(script, 'renderEmployeeAvatar(item.employee_profile_img_url)', 'Training approval rows should render employee photos through the shared default-image fallback.');
assertIncludes(script, 'pending_hr', 'Frontend should show HR pending status.');
assertIncludes(script, 'initTrainingRequestDataTable', 'Frontend should initialize DataTables for training request tables.');
assertIncludes(script, "initTrainingRequestDataTable('trainingRequestHistoryTable'", 'Training request history should use DataTables.');
assertIncludes(script, "initTrainingRequestDataTable('trainingRequestPendingTable'", 'Training pending approvals should use DataTables.');
assertIncludes(script, "initTrainingRequestDataTable('trainingRequestApprovalHistoryTable'", 'Training approval history should use DataTables.');

assertIncludes(header, "'training' => 0", 'Sidebar badge counts should include training.');
assertIncludes(header, "href=\"training_history.php\"", 'Sidebar should link directly to the training history landing page.');
if (header.includes('trainingSubmenu')) {
  throw new Error('Training sidebar menu should not use a submenu.');
}
assertIncludes(header, "isActive('training_request.php')", 'Training request page should keep the direct sidebar item active.');
assertIncludes(header, "isActive('training_approvals.php')", 'Training approval page should keep the direct sidebar item active.');
assertIncludes(header, "$approvalBadgeCounts['training']", 'Sidebar should render training approval badge.');
assertIncludes(footer, 'assets/js/training_request.js', 'Footer should load training request JS.');

assertIncludes(badges, 'approvalBadgeCountTrainingRequests', 'Badge helper should count training requests.');
assertIncludes(badges, "'training'", 'Badge normalization should include training counts.');

console.log('training_request_contract_test passed');
