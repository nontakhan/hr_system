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

const header = fs.readFileSync('includes/header.php', 'utf8');
const link = 'href="holiday_calendar.php" class="list-group-item list-group-item-action bg-transparent <?php echo isActive(\'holiday_calendar.php\'); ?>"';

assertIncludes(header, link, 'Holiday calendar should be a top-level sidebar item.');
assertIncludes(header, '<i class="fas fa-calendar-days me-2"></i> ปฏิทินวันหยุด', 'Holiday calendar menu should have a clear top-level label.');
assertNotIncludes(header, 'ps-5 <?php echo isActive(\'holiday_calendar.php\'); ?>">\n                    <small>ปฏิทินวันหยุด</small>', 'Holiday calendar should not be hidden inside the leave submenu.');

console.log('holiday_calendar_menu_test passed');
