const fs = require('fs');

function assertIncludes(text, expected, message) {
    if (!text.includes(expected)) {
        console.error(message);
        console.error('Missing:', expected);
        process.exit(1);
    }
}

function assertNotIncludes(text, expected, message) {
    if (text.includes(expected)) {
        console.error(message);
        console.error('Unexpected:', expected);
        process.exit(1);
    }
}

const page = fs.readFileSync('leave_request.php', 'utf8');
const timeRequestPage = fs.readFileSync('late_early_request.php', 'utf8');
const leaveTypesPage = fs.readFileSync('leave_types.php', 'utf8');
const script = fs.readFileSync('assets/js/leave_request.js', 'utf8');
const timeRequestScript = fs.readFileSync('assets/js/late_early_request.js', 'utf8');
const leaveSettingsScript = fs.readFileSync('assets/js/leave.js', 'utf8');
const styles = fs.readFileSync('assets/style.css', 'utf8');

assertIncludes(page, 'type="hidden" name="leave_type_id" id="leaveTypeSelect"', 'Leave request page should submit leave_type_id through a hidden field.');
assertIncludes(page, 'id="leaveTypeIconGrid"', 'Leave request page should include an icon grid container.');
assertIncludes(page, 'id="leaveUsageSummaryGrid"', 'Leave request page should include the leave usage summary grid.');
assertIncludes(timeRequestPage, 'id="lateEarlyRequestForm"', 'Late/early request page should include its own request form.');
assertIncludes(timeRequestPage, 'name="time_request_type"', 'Late/early request page should choose a time request type.');
assertIncludes(timeRequestPage, 'type="radio" name="time_request_type"', 'Late/early request page should use easy-tap radio buttons for request type.');
assertIncludes(timeRequestPage, 'class="btn-check time-request-type-option"', 'Late/early request type radios should use Bootstrap button styling.');
assertNotIncludes(timeRequestPage, '<select name="time_request_type"', 'Late/early request page should not use a dropdown for request type.');
assertIncludes(timeRequestPage, 'name="request_time"', 'Late/early request page should collect the requested time.');
assertIncludes(leaveTypesPage, 'id="leavePolicyTable"', 'Leave settings should list saved policy records.');
assertIncludes(leaveTypesPage, 'name="leave_max_requests_per_year"', 'Leave policy form should include a fiscal-year request limit input.');
assertIncludes(leaveTypesPage, 'จำนวนวันลาที่ลาได้ต่อปีงบประมาณ', 'Leave policy form should describe the annual quota as leave days.');
assertIncludes(leaveTypesPage, 'จำนวนวันลา/ปีงบ', 'Leave policy table should describe the annual quota as leave days.');
assertIncludes(script, 'function renderLeaveTypeCards', 'Leave request JS should render leave type icon cards.');
assertIncludes(leaveSettingsScript, 'function renderLeavePolicyRows', 'Leave settings JS should render saved policy rows.');
assertIncludes(script, 'function selectLeaveType', 'Leave request JS should select a leave type card and sync the hidden field.');
assertIncludes(script, 'function getLeaveTypePresentation', 'Leave request JS should map leave type names to icons and colors.');
assertIncludes(script, 'function renderLeaveUsageSummary', 'Leave request JS should render leave usage warnings.');
assertIncludes(script, 'function renderLeaveUsageEntries', 'Leave request JS should render every counted leave entry for comparison.');
assertIncludes(script, 'function renderOverallLeaveUsageCard', 'Leave request JS should render one overall leave usage card.');
assertIncludes(script, 'วัน', 'Leave request usage summary should display annual quota in days.');
assertNotIncludes(script, '${item.request_limit} ครั้ง', 'Leave request usage summary should not display annual quota as request counts.');
assertNotIncludes(script, 'จากสิทธิ์ ${usage.request_limit} ครั้ง/ปีงบ', 'Leave request condition text should not describe quota as request counts.');
assertNotIncludes(script, 'projectedRequests', 'Leave request projection should calculate with projected leave days.');
assertNotIncludes(leaveSettingsScript, '${policy.leave_max_requests_per_year} ครั้ง', 'Leave settings rows should not display annual quota as request counts.');
assertIncludes(timeRequestScript, 'calculateTimeRequest', 'Late/early request JS should calculate minutes before submit.');
assertIncludes(timeRequestScript, 'loadTimeRequestHistory', 'Late/early request JS should load its own history.');
assertNotIncludes(script, "request_unit: 'hour'", 'Leave request JS should no longer calculate hourly requests.');
const requestApi = fs.readFileSync('api/leave_request_api.php', 'utf8');
const historyApi = fs.readFileSync('api/leave_history_api.php', 'utf8');
assertIncludes(requestApi, 'leaveDetectHourlyRequestType($row', 'Leave request API should filter late/early types out of leave options.');
assertIncludes(requestApi, 'เมนูคำขอเวลา', 'Leave request API should reject old late/early submissions.');
assertIncludes(historyApi, "lr.request_unit <> 'hour'", 'Leave history API should exclude late/early hourly requests.');
assertIncludes(styles, '.leave-type-grid', 'Styles should include the leave type icon grid.');
assertIncludes(styles, '.leave-type-card.is-selected', 'Styles should include a selected card state.');
assertIncludes(styles, '.leave-usage-card-near', 'Styles should include a near-limit leave usage state.');
assertIncludes(styles, '.leave-usage-card-over', 'Styles should include an over-limit leave usage state.');
assertIncludes(styles, '.leave-usage-entry-list', 'Styles should include counted leave entry list styling.');

console.log('leave_request_icon_ui_test passed');
