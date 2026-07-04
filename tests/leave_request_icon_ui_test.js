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
const overtimeRequestPage = fs.readFileSync('overtime_request.php', 'utf8');
const overtimeHistoryPage = fs.readFileSync('overtime_history.php', 'utf8');
const leaveTypesPage = fs.readFileSync('leave_types.php', 'utf8');
const script = fs.readFileSync('assets/js/leave_request.js', 'utf8');
const myLeavesScript = fs.readFileSync('assets/js/my_leaves.js', 'utf8');
const timeRequestScript = fs.readFileSync('assets/js/late_early_request.js', 'utf8');
const leaveSettingsScript = fs.readFileSync('assets/js/leave.js', 'utf8');
const styles = fs.readFileSync('assets/style.css', 'utf8');

assertIncludes(page, 'type="hidden" name="leave_type_id" id="leaveTypeSelect"', 'Leave request page should submit leave_type_id through a hidden field.');
assertIncludes(page, 'id="leaveTypeIconGrid"', 'Leave request page should include an icon grid container.');
assertIncludes(page, 'id="leaveUsageSummaryGrid"', 'Leave request page should include the leave usage summary grid.');
assertIncludes(page, 'href="my_leaves.php"', 'Leave request page should include a back button to my leave history.');
assertIncludes(page, 'leave-request-back-link', 'Leave request back button should have a stable class.');
assertIncludes(timeRequestPage, 'id="lateEarlyRequestForm"', 'Late/early request page should include its own request form.');
assertIncludes(timeRequestPage, 'name="time_request_type"', 'Late/early request page should choose a time request type.');
assertIncludes(timeRequestPage, 'type="radio" name="time_request_type"', 'Late/early request page should use easy-tap radio buttons for request type.');
assertIncludes(timeRequestPage, 'class="btn-check time-request-type-option"', 'Late/early request type radios should use Bootstrap button styling.');
assertNotIncludes(timeRequestPage, '<select name="time_request_type"', 'Late/early request page should not use a dropdown for request type.');
assertIncludes(timeRequestPage, 'name="request_time"', 'Late/early request page should collect the requested time.');
assertNotIncludes(timeRequestPage, 'value="overtime_after_work"', 'Late/early request page should not show after-work OT.');
assertIncludes(overtimeRequestPage, 'value="overtime_after_work"', 'Overtime request page should submit after-work OT.');
assertIncludes(overtimeRequestPage, 'name="overtime_start_time"', 'Overtime request page should collect OT start time.');
assertIncludes(overtimeRequestPage, 'name="overtime_end_time"', 'Overtime request page should collect OT end time.');
assertNotIncludes(overtimeRequestPage, 'name="overtime_minutes"', 'Overtime request page should not collect manually typed OT duration.');
assertIncludes(overtimeRequestPage, "window.timeRequestFixedType = 'overtime_after_work';", 'Overtime request page should force OT type.');
assertIncludes(overtimeHistoryPage, "window.timeRequestHistoryType = 'overtime_after_work';", 'Overtime history page should load only OT history.');
assertIncludes(timeRequestScript, 'overtime_after_work', 'Time request script should handle after-work OT.');
assertIncludes(leaveTypesPage, 'id="leavePolicyTable"', 'Leave settings should list saved policy records.');
assertIncludes(leaveTypesPage, 'name="leave_max_requests_per_year"', 'Leave policy form should include a fiscal-year request limit input.');
assertIncludes(leaveTypesPage, 'name="vacation_min_months_before_leave"', 'Leave policy form should include a vacation tenure threshold input.');
assertIncludes(leaveTypesPage, 'name="is_actual_leave"', 'Leave type form should let admin mark which types are real leave for summary cards.');
assertIncludes(leaveTypesPage, 'name="calculation_unit"', 'Leave type form should let admin choose hourly leave calculation.');
assertIncludes(leaveTypesPage, 'name="hours_per_day"', 'Leave type form should configure how many hours equal one quota day.');
assertIncludes(leaveTypesPage, 'name="hour_full_day_threshold"', 'Leave type form should configure when hourly leave counts as one full day.');
assertIncludes(page, 'id="hourlyLeaveFields"', 'Leave request page should include fields for hourly leave types.');
assertIncludes(page, 'id="startDateField"', 'Leave request page should have a stable start-date layout wrapper.');
assertIncludes(page, 'name="request_start_time"', 'Leave request page should collect the hourly leave start time.');
assertIncludes(page, 'name="request_end_time"', 'Leave request page should collect the hourly leave end time.');
assertNotIncludes(page, 'name="request_hours"', 'Leave request page should not let employees manually type hourly leave duration.');
assertIncludes(leaveTypesPage, 'จำนวนวันลาที่ลาได้ต่อปีงบประมาณ', 'Leave policy form should describe the annual quota as leave days.');
assertIncludes(leaveTypesPage, 'จำนวนวันลา/ปีงบ', 'Leave policy table should describe the annual quota as leave days.');
assertIncludes(script, 'function renderLeaveTypeCards', 'Leave request JS should render leave type icon cards.');
assertIncludes(leaveSettingsScript, 'function renderLeavePolicyRows', 'Leave settings JS should render saved policy rows.');
assertIncludes(leaveSettingsScript, 'vacation_min_months_before_leave', 'Leave settings JS should save and render the vacation tenure threshold.');
assertIncludes(leaveSettingsScript, 'is_actual_leave', 'Leave settings JS should save and render the real-leave summary flag.');
assertIncludes(leaveSettingsScript, 'toggleLeaveTypeCalculationFields', 'Leave settings JS should toggle hourly calculation settings.');
assertIncludes(script, 'function selectLeaveType', 'Leave request JS should select a leave type card and sync the hidden field.');
assertIncludes(script, 'function isSelectedLeaveTypeHourly', 'Leave request JS should detect admin-configured hourly leave types.');
assertIncludes(script, 'function updateLeaveRequestMode', 'Leave request JS should switch between day and hour leave inputs.');
assertIncludes(script, "startField.classList.toggle('col-md-12', isHourly)", 'Hourly leave mode should make the date field span the full row.');
assertIncludes(script, 'function getHourlyLeaveDuration', 'Leave request JS should calculate hourly leave duration from start and end time.');
assertIncludes(script, 'request_start_time', 'Leave request JS should submit hourly leave start time.');
assertIncludes(script, 'request_end_time', 'Leave request JS should submit hourly leave end time.');
assertNotIncludes(script, "get('request_hours')", 'Leave request JS should not validate manually typed hourly leave hours.');
assertIncludes(script, 'function getLeaveTypePresentation', 'Leave request JS should map leave type names to icons and colors.');
assertIncludes(script, "attachmentSection.classList.remove('d-none');", 'Leave request JS should show the attachment field for leave types configured with evidence.');
assertNotIncludes(script, 'attachmentInput.required = true', 'Leave request attachments should be optional even when the leave type is configured to show evidence upload.');
assertIncludes(script, 'function renderLeaveUsageSummary', 'Leave request JS should render leave usage warnings.');
assertIncludes(script, 'function renderProjectedLeaveUsageSummary', 'Leave request JS should re-render summary cards with the in-progress leave projection.');
assertIncludes(script, 'function buildProjectedLeaveUsageSummary', 'Leave request JS should calculate projected card balances from the selected leave type.');
assertIncludes(script, 'function renderTypeLeaveUsageCard', 'Leave request JS should render per-type summary cards on the request page.');
assertIncludes(script, 'projectedSelectedDays', 'Leave request summary cards should display the selected hourly leave as projected quota usage.');
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
assertIncludes(requestApi, 'is_actual_leave = 1', 'Leave request API should only expose real leave types in leave options.');
assertIncludes(requestApi, 'calculation_unit', 'Leave request API should expose leave type calculation unit.');
assertIncludes(requestApi, 'leaveBuildTimedHourlyLeavePayload', 'Leave request API should build quota-counting hourly leave payloads from start and end time.');
assertNotIncludes(requestApi, "$data['request_hours']", 'Leave request API should not trust manually submitted hourly leave hours.');
assertIncludes(requestApi, 'leaveBuildVacationEligibilityStatus', 'Leave request API should enforce the active vacation tenure threshold before saving vacation leave.');
assertIncludes(requestApi, 'employees WHERE id = ?', 'Leave request API should check the employee start date for vacation eligibility.');
assertIncludes(requestApi, 'เมนูคำขอเวลา', 'Leave request API should reject old late/early submissions.');
assertNotIncludes(requestApi, "if ((int)$type_info['requires_file'] === 1)", 'Leave request API should not require an attachment just because the type is configured to show evidence upload.');
assertIncludes(historyApi, "lr.request_unit <> 'hour'", 'Leave history API should exclude late/early hourly requests.');
assertIncludes(styles, '.leave-type-grid', 'Styles should include the leave type icon grid.');
assertIncludes(styles, '.leave-type-card.is-selected', 'Styles should include a selected card state.');
assertIncludes(styles, '.leave-usage-card-near', 'Styles should include a near-limit leave usage state.');
assertIncludes(styles, '.leave-usage-card-over', 'Styles should include an over-limit leave usage state.');
assertIncludes(styles, '.leave-usage-entry-list', 'Styles should include counted leave entry list styling.');

console.log('leave_request_icon_ui_test passed');
