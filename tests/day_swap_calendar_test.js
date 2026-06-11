const fs = require('fs');
const vm = require('vm');

global.document = {
    addEventListener() {},
};
global.window = {};

vm.runInThisContext(fs.readFileSync('assets/js/utils.js', 'utf8'));
vm.runInThisContext(fs.readFileSync('assets/js/day_swap.js', 'utf8'));

function assertSame(expected, actual, message) {
    if (expected !== actual) {
        console.error(message);
        console.error('Expected:', expected);
        console.error('Actual:  ', actual);
        process.exit(1);
    }
}

function assertIncludes(haystack, needle, message) {
    if (!String(haystack).includes(needle)) {
        console.error(message);
        console.error('Expected to include:', needle);
        console.error('Actual:             ', haystack);
        process.exit(1);
    }
}

const event = buildDaySwapHolidayEvent({ date: '2026-06-14', day_name: 'Sun' }, 'requester');
assertSame('2026-06-14', event.start, 'Holiday event should be placed on its date.');
assertSame(true, event.allDay, 'Holiday event should be all-day.');
assertSame('requester', event.extendedProps.owner, 'Holiday event should retain owner side.');
assertSame('#e5e7eb', event.backgroundColor, 'Holiday event should use the regular holiday color.');
assertIncludes(event.title, 'วันหยุด', 'Holiday event title should be explicit.');

const selectedClasses = buildDaySwapCalendarDayClasses('2026-06-14')({ date: new Date(2026, 5, 14) });
assertIncludes(selectedClasses.join(' '), 'day-swap-calendar-selected', 'Selected date should receive highlight class.');

const unselectedClasses = buildDaySwapCalendarDayClasses('2026-06-14')({ date: new Date(2026, 5, 15) });
assertSame('', unselectedClasses.join(''), 'Unselected date should not receive a highlight class.');

const boundEvents = [];
const mockSelect = {
    addEventListener(eventName) {
        boundEvents.push(eventName);
    },
};
global.window.jQuery = function () {
    return {
        off(eventNames) {
            boundEvents.push(`off:${eventNames}`);
            return this;
        },
        on(eventNames) {
            boundEvents.push(eventNames);
            return this;
        },
    };
};
global.window.jQuery.fn = { select2: true };
global.jQuery = global.window.jQuery;
bindDaySwapTargetEmployeeChange(mockSelect, () => {});
assertIncludes(boundEvents.join(' '), 'change', 'Target employee select should listen for native changes.');
assertIncludes(boundEvents.join(' '), 'select2:select.daySwap', 'Target employee select should listen for Select2 selections.');
assertIncludes(boundEvents.join(' '), 'select2:clear.daySwap', 'Target employee select should listen for Select2 clears.');

console.log('day_swap_calendar_test passed');
