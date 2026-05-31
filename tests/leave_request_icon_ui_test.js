const fs = require('fs');

function assertIncludes(text, expected, message) {
    if (!text.includes(expected)) {
        console.error(message);
        console.error('Missing:', expected);
        process.exit(1);
    }
}

const page = fs.readFileSync('leave_request.php', 'utf8');
const script = fs.readFileSync('assets/js/leave_request.js', 'utf8');
const styles = fs.readFileSync('assets/style.css', 'utf8');

assertIncludes(page, 'type="hidden" name="leave_type_id" id="leaveTypeSelect"', 'Leave request page should submit leave_type_id through a hidden field.');
assertIncludes(page, 'id="leaveTypeIconGrid"', 'Leave request page should include an icon grid container.');
assertIncludes(script, 'function renderLeaveTypeCards', 'Leave request JS should render leave type icon cards.');
assertIncludes(script, 'function selectLeaveType', 'Leave request JS should select a leave type card and sync the hidden field.');
assertIncludes(script, 'function getLeaveTypePresentation', 'Leave request JS should map leave type names to icons and colors.');
assertIncludes(styles, '.leave-type-grid', 'Styles should include the leave type icon grid.');
assertIncludes(styles, '.leave-type-card.is-selected', 'Styles should include a selected card state.');

console.log('leave_request_icon_ui_test passed');
