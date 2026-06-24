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
const myLeavesScript = fs.readFileSync('assets/js/my_leaves.js', 'utf8');
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
assertIncludes(leaveTypesPage, 'name="calculation_unit"', 'Leave type form should let admin choose hourly leave calculation.');
assertIncludes(leaveTypesPage, 'name="hours_per_day"', 'Leave type form should configure how many hours equal one quota day.');
assertIncludes(leaveTypesPage, 'name="hour_full_day_threshold"', 'Leave type form should configure when hourly leave counts as one full day.');
assertIncludes(page, 'id="hourlyLeaveFields"', 'Leave request page should include fields for hourly leave types.');
assertIncludes(page, 'name="request_hours"', 'Leave request page should submit requested hours for hourly leave types.');
assertIncludes(leaveTypesPage, 'จำนวนวันลาที่ลาได้ต่อปีงบประมาณ', 'Leave policy form should describe the annual quota as leave days.');
assertIncludes(leaveTypesPage, 'จำนวนวันลา/ปีงบ', 'Leave policy table should describe the annual quota as leave days.');
assertIncludes(script, 'function renderLeaveTypeCards', 'Leave request JS should render leave type icon cards.');
assertIncludes(leaveSettingsScript, 'function renderLeavePolicyRows', 'Leave settings JS should render saved policy rows.');
assertIncludes(leaveSettingsScript, 'toggleLeaveTypeCalculationFields', 'Leave settings JS should toggle hourly calculation settings.');
assertIncludes(script, 'function selectLeaveType', 'Leave request JS should select a leave type card and sync the hidden field.');
assertIncludes(script, 'function isSelectedLeaveTypeHourly', 'Leave request JS should detect admin-configured hourly leave types.');
assertIncludes(script, 'function updateLeaveRequestMode', 'Leave request JS should switch between day and hour leave inputs.');
assertIncludes(script, 'request_hours', 'Leave request JS should validate requested hours before submit.');
assertIncludes(script, 'function getLeaveTypePresentation', 'Leave request JS should map leave type names to icons and colors.');
assertIncludes(script, "attachmentSection.classList.remove('d-none');", 'Leave request JS should show the attachment field for leave types configured with evidence.');
assertNotIncludes(script, 'attachmentInput.required = true', 'Leave request attachments should be optional even when the leave type is configured to show evidence upload.');
assertIncludes(script, 'function renderLeaveUsageSummary', 'Leave request JS should render leave usage warnings.');
assertIncludes(script, 'function renderLeaveUsageEntries', 'Leave request JS should render every counted leave entry for comparison.');
assertNotIncludes(myLeavesScript, 'renderLeaveUsageEntries', 'My leaves summary cards should not duplicate leave entries already shown in the table.');
assertIncludes(script, 'function renderOverallLeaveUsageCard', 'Leave request JS should render one overall leave usage card.');
assertIncludes(script, 'leaveUsageSummary.items.find', 'Leave request condition text should compare usage against the selected leave type limit.');
assertIncludes(script, 'วัน', 'Leave request usage summary should display annual quota in days.');
assertIncludes(script, 'เกินสิทธิ์', 'Leave request usage summary should explain over-limit leave as a warning.');
assertIncludes(myLeavesScript, 'เกินสิทธิ์', 'My leaves summary cards should explain over-limit leave as a warning.');
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
assertIncludes(requestApi, 'calculation_unit', 'Leave request API should expose leave type calculation unit.');
assertIncludes(requestApi, 'leaveBuildHourlyLeavePayload', 'Leave request API should build quota-counting hourly leave payloads.');
assertIncludes(requestApi, 'เมนูคำขอเวลา', 'Leave request API should reject old late/early submissions.');
assertNotIncludes(requestApi, "if ((int)$type_info['requires_file'] === 1)", 'Leave request API should not require an attachment just because the type is configured to show evidence upload.');
assertIncludes(historyApi, "lr.request_unit <> 'hour'", 'Leave history API should exclude late/early hourly requests.');
assertIncludes(styles, '.leave-type-grid', 'Styles should include the leave type icon grid.');
assertIncludes(styles, '.leave-type-card.is-selected', 'Styles should include a selected card state.');
assertIncludes(styles, '.leave-usage-card-near', 'Styles should include a near-limit leave usage state.');
assertIncludes(styles, '.leave-usage-card-over', 'Styles should include an over-limit leave usage state.');
assertIncludes(styles, '.leave-usage-entry-list', 'Styles should include counted leave entry list styling.');

console.log('leave_request_icon_ui_test passed');
