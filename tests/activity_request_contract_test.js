const fs = require('fs');

function read(path) {
  return fs.readFileSync(path, 'utf8');
}

function assertFile(path) {
  if (!fs.existsSync(path)) {
    throw new Error(`Expected file to exist: ${path}`);
  }
}

function assertIncludes(content, needle, message) {
  if (!content.includes(needle)) {
    throw new Error(`${message}\nMissing: ${needle}`);
  }
}

[
  'includes/training_request_helpers.php',
  'api/training_request_api.php',
  'api/proxy_request_api.php',
  'training_request.php',
  'training_history.php',
  'training_approvals.php',
  'request_proxy.php',
  'activity_types.php',
  'api/activity_type_api.php',
  'assets/js/activity_types.js',
  'assets/js/training_request.js',
  'assets/js/proxy_request.js',
  'assets/style.css',
  'includes/header.php',
].forEach(assertFile);

const helper = read('includes/training_request_helpers.php');
const requestApi = read('api/training_request_api.php');
const proxyApi = read('api/proxy_request_api.php');
const requestPage = read('training_request.php');
const historyPage = read('training_history.php');
const approvalsPage = read('training_approvals.php');
const proxyPage = read('request_proxy.php');
const typePage = read('activity_types.php');
const typeApi = read('api/activity_type_api.php');
const typeScript = read('assets/js/activity_types.js');
const requestScript = read('assets/js/training_request.js');
const proxyScript = read('assets/js/proxy_request.js');
const style = read('assets/style.css');
const header = read('includes/header.php');

assertIncludes(helper, 'CREATE TABLE IF NOT EXISTS activity_types', 'Activity type master table should be bootstrapped.');
assertIncludes(helper, 'trainingRequestEnsureActivityColumns', 'Training request table should gain activity-specific columns.');
assertIncludes(helper, 'activity_type_id INT NULL', 'Training requests should reference the selected activity type.');
assertIncludes(helper, "start_day_part ENUM('full','morning','afternoon')", 'Activity requests should store the start-day part.');
assertIncludes(helper, "end_day_part ENUM('full','morning','afternoon')", 'Activity requests should store the end-day part.');
assertIncludes(helper, 'trainingRequestFetchActiveActivityTypes', 'Helper should expose active activity types for request choices.');
assertIncludes(helper, 'trainingRequestNormalizeDayPart', 'Helper should normalize full/morning/afternoon day parts.');
assertIncludes(helper, 'activity_type_name', 'Queries/history notes should expose the selected activity type name.');

assertIncludes(requestApi, "action === 'activity_types'", 'Employee request API should return active activity types.');
assertIncludes(requestApi, 'activity_type_id', 'Employee create API should save selected activity type.');
assertIncludes(requestApi, 'start_day_part', 'Employee create API should save the start-day part.');
assertIncludes(requestApi, 'end_day_part', 'Employee create API should save the end-day part.');
assertIncludes(requestApi, 'กรุณาเลือกประเภทกิจกรรม', 'Employee create API should validate activity type selection.');

assertIncludes(proxyApi, "action === 'activity_types'", 'Proxy API should return active activity types.');
assertIncludes(proxyApi, 'activity_type_id', 'Proxy create API should save selected activity type.');
assertIncludes(proxyApi, 'start_day_part', 'Proxy create API should save the start-day part.');
assertIncludes(proxyApi, 'end_day_part', 'Proxy create API should save the end-day part.');

assertIncludes(requestPage, 'ขอไปทำกิจกรรม', 'Employee request page should be relabeled as activity request.');
assertIncludes(requestPage, 'type="hidden" name="activity_type_id"', 'Employee request page should store selected activity type in a hidden input.');
assertIncludes(requestPage, 'class="col-12 activity-type-section"', 'Employee request page should show activity type choices at full form width.');
assertIncludes(requestPage, 'id="activityTypeButtonGrid"', 'Employee request page should render activity choices as tap-friendly buttons.');
assertIncludes(requestPage, 'role="radiogroup"', 'Activity button grid should expose radio semantics.');
assertIncludes(requestPage, 'name="start_day_part"', 'Employee request page should support start-day part.');
assertIncludes(requestPage, 'name="end_day_part"', 'Employee request page should support end-day part.');
assertIncludes(requestPage, 'class="col-12 training-day-part-field"', 'Employee request page should show the day-part selector at full form width.');
assertIncludes(requestPage, '<option value="">เลือกช่วงวัน</option>', 'Day-part selector should show a placeholder instead of defaulting to full day.');
assertIncludes(requestPage, 'type="hidden" name="end_day_part"', 'Employee request page should sync the end-day part without showing a second selector.');
assertIncludes(requestPage, 'activityTypeSelect', 'Employee request page should expose a stable activity type select id.');

assertIncludes(proxyPage, 'proxyActivityTypeId', 'Proxy page should expose a stable activity type select id.');
assertIncludes(proxyPage, 'name="activity_type_id"', 'Proxy page should select an activity type.');
assertIncludes(proxyPage, 'name="start_day_part"', 'Proxy page should support start-day part.');
assertIncludes(proxyPage, 'name="end_day_part"', 'Proxy page should support end-day part.');

assertIncludes(historyPage, 'ประวัติคำขอกิจกรรม', 'History page should be relabeled as activity request history.');
assertIncludes(approvalsPage, 'อนุมัติคำขอกิจกรรม', 'Approval page should be relabeled as activity approvals.');
assertIncludes(requestScript, 'loadTrainingActivityTypes', 'Request JS should load active activity types.');
assertIncludes(requestScript, 'renderTrainingActivityTypeButtons', 'Request JS should render activity types as buttons.');
assertIncludes(requestScript, 'selectTrainingActivityType', 'Request JS should update hidden activity type selection.');
assertIncludes(requestScript, 'syncTrainingEndDayPart', 'Request JS should sync the hidden end-day part from the visible start-day selector.');
assertIncludes(requestScript, 'updateTrainingDayPartVisibility', 'Request JS should hide half-day controls for multi-day activity requests.');
assertIncludes(requestScript, 'training-day-part-field', 'Request JS should target stable half-day field wrappers.');
assertIncludes(requestScript, 'formatTrainingRequestDateRangeWithParts', 'Request JS should display date ranges with full/morning/afternoon parts.');
assertIncludes(proxyScript, 'loadActivityTypes', 'Proxy JS should load active activity types.');
assertIncludes(style, '.activity-type-grid', 'Activity request page should have button-grid styling.');
assertIncludes(style, '.activity-type-card', 'Activity request page should style activity buttons.');

assertIncludes(typePage, 'id="activityTypesTable"', 'Activity type management page should render a table.');
assertIncludes(typePage, 'id="activityTypeForm"', 'Activity type management page should render a create/edit form.');
assertIncludes(typePage, 'assets/js/activity_types.js', 'Activity type management page should load its script.');
assertIncludes(typeApi, "action === 'list'", 'Activity type API should list types.');
assertIncludes(typeApi, "action === 'save'", 'Activity type API should create and update types.');
assertIncludes(typeApi, "action === 'delete'", 'Activity type API should delete unused types.');
assertIncludes(typeScript, 'loadActivityTypes', 'Activity type JS should load rows.');
assertIncludes(typeScript, 'activityTypeForm', 'Activity type JS should submit the form.');

assertIncludes(header, 'activity_types.php', 'Sidebar should include activity type management.');
assertIncludes(header, 'กิจกรรม', 'Sidebar/request labels should use activity wording.');

console.log('activity_request_contract_test passed');
