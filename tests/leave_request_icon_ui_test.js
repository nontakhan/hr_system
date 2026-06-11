const fs = require('fs');

function assertIncludes(text, expected, message) {
    if (!text.includes(expected)) {
        console.error(message);
        console.error('Missing:', expected);
        process.exit(1);
    }
}

const page = fs.readFileSync('leave_request.php', 'utf8');
const leaveTypesPage = fs.readFileSync('leave_types.php', 'utf8');
const script = fs.readFileSync('assets/js/leave_request.js', 'utf8');
const leaveSettingsScript = fs.readFileSync('assets/js/leave.js', 'utf8');
const styles = fs.readFileSync('assets/style.css', 'utf8');

assertIncludes(page, 'type="hidden" name="leave_type_id" id="leaveTypeSelect"', 'Leave request page should submit leave_type_id through a hidden field.');
assertIncludes(page, 'id="leaveTypeIconGrid"', 'Leave request page should include an icon grid container.');
assertIncludes(page, 'id="leaveUsageSummaryGrid"', 'Leave request page should include the leave usage summary grid.');
assertIncludes(page, 'name="request_unit" id="requestUnitInput"', 'Leave request page should submit the request unit.');
assertIncludes(page, 'id="hourlyRequestNotice"', 'Leave request page should include fixed one-hour request guidance.');
assertIncludes(leaveTypesPage, 'id="leavePolicyTable"', 'Leave settings should list saved policy records.');
assertIncludes(leaveTypesPage, 'name="leave_max_requests_per_year"', 'Leave policy form should include a fiscal-year request limit input.');
assertIncludes(script, 'function renderLeaveTypeCards', 'Leave request JS should render leave type icon cards.');
assertIncludes(leaveSettingsScript, 'function renderLeavePolicyRows', 'Leave settings JS should render saved policy rows.');
assertIncludes(script, 'function selectLeaveType', 'Leave request JS should select a leave type card and sync the hidden field.');
assertIncludes(script, 'function getLeaveTypePresentation', 'Leave request JS should map leave type names to icons and colors.');
assertIncludes(script, 'function renderLeaveUsageSummary', 'Leave request JS should render leave usage warnings.');
assertIncludes(script, 'function renderLeaveUsageEntries', 'Leave request JS should render every counted leave entry for comparison.');
assertIncludes(script, 'function renderOverallLeaveUsageCard', 'Leave request JS should render one overall leave usage card.');
assertIncludes(script, 'function isHourlyLeaveType', 'Leave request JS should detect fixed one-hour leave types.');
assertIncludes(script, 'function updateHourlyRequestMode', 'Leave request JS should switch late/early requests to fixed one-hour mode.');
assertIncludes(script, "request_unit: 'hour'", 'Leave request JS should calculate hourly requests without adding leave days.');
const requestApi = fs.readFileSync('api/leave_request_api.php', 'utf8');
assertIncludes(requestApi, 'leaveEnsureHourlyRequestTypes($mysqli)', 'Leave request API should seed fixed one-hour leave types before rendering options.');
assertIncludes(styles, '.leave-type-grid', 'Styles should include the leave type icon grid.');
assertIncludes(styles, '.leave-type-card.is-selected', 'Styles should include a selected card state.');
assertIncludes(styles, '.leave-usage-card-near', 'Styles should include a near-limit leave usage state.');
assertIncludes(styles, '.leave-usage-card-over', 'Styles should include an over-limit leave usage state.');
assertIncludes(styles, '.leave-usage-entry-list', 'Styles should include counted leave entry list styling.');

console.log('leave_request_icon_ui_test passed');
