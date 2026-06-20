const fs = require('fs');
const vm = require('vm');

global.document = {
    addEventListener() {},
};
global.window = {};

vm.runInThisContext(fs.readFileSync('assets/js/utils.js', 'utf8'));
vm.runInThisContext(fs.readFileSync('assets/js/holiday_calendar.js', 'utf8'));

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

const companyEvent = buildHolidayCalendarEvent({
    date: '2026-06-03',
    type: 'company_holiday',
    title: 'Company Foundation Day',
});
assertSame('Company Foundation Day', companyEvent.title, 'Company event should use configured title.');
assertSame('2026-06-03', companyEvent.start, 'Company event should start on its date.');
assertSame('#bfdbfe', companyEvent.backgroundColor, 'Company holidays should use blue background.');
assertIncludes(companyEvent.classNames.join(' '), 'holiday-calendar-company', 'Company holidays should have a class.');

const regularEvent = buildHolidayCalendarEvent({
    date: '2026-06-07',
    type: 'regular_holiday',
    title: 'วันหยุดประจำสัปดาห์',
});
assertSame('#bbf7d0', regularEvent.backgroundColor, 'Regular holidays should use green background.');
assertIncludes(regularEvent.classNames.join(' '), 'holiday-calendar-regular', 'Regular holidays should have a class.');

const approvedLeaveEvent = buildHolidayCalendarEvent({
    date: '2026-06-09',
    type: 'approved_leave',
    title: 'Annual Leave',
    reason: 'Family trip',
});
assertSame('#ddd6fe', approvedLeaveEvent.backgroundColor, 'Approved leave should use purple background.');
assertIncludes(approvedLeaveEvent.classNames.join(' '), 'holiday-calendar-approved-leave', 'Approved leave should have a class.');

holidayCalendarDayClassMap = buildHolidayCalendarDayClassMap([
    { date: '2026-06-03', type: 'company_holiday' },
    { date: '2026-06-09', type: 'approved_leave' },
    { date: '2026-06-07', type: 'regular_holiday' },
]);
const calendarOptions = buildHolidayCalendarOptions();
const companyDayClasses = calendarOptions.dayCellClassNames({ date: new Date(2026, 5, 3) });
const approvedLeaveDayClasses = calendarOptions.dayCellClassNames({ date: new Date(2026, 5, 9) });
const regularDayClasses = calendarOptions.dayCellClassNames({ date: new Date(2026, 5, 7) });
assertIncludes(companyDayClasses.join(' '), 'holiday-calendar-day-company', 'Company holiday cells should be fully colored.');
assertIncludes(approvedLeaveDayClasses.join(' '), 'holiday-calendar-day-approved-leave', 'Approved leave cells should be fully colored.');
assertIncludes(regularDayClasses.join(' '), 'holiday-calendar-day-regular', 'Regular holiday cells should be fully colored.');

const summaryHtml = buildHolidayCalendarSummaryHtml({
    company_holiday: 2,
    regular_holiday: 4,
    approved_leave: 1,
    total: 7,
});
assertIncludes(summaryHtml, 'วันหยุดบริษัท', 'Summary should include company holiday label.');
assertIncludes(summaryHtml, 'วันหยุดประจำสัปดาห์', 'Summary should include regular holiday label.');
assertIncludes(summaryHtml, 'holiday-calendar-summary-leave', 'Summary should include approved leave card.');
assertIncludes(summaryHtml, '7', 'Summary should include total count.');

const detailHtml = buildHolidayCalendarDetailHtml({
    type: 'approved_leave',
    title: 'Annual Leave',
    date: '2026-06-09',
    start_date: '2026-06-09',
    end_date: '2026-06-10',
    total_days: '2.00',
    reason: 'Family trip',
    status: 'approved',
});
assertIncludes(detailHtml, 'Annual Leave', 'Approved leave detail should include leave type.');
assertIncludes(detailHtml, 'Family trip', 'Approved leave detail should include reason.');
assertIncludes(detailHtml, '2', 'Approved leave detail should include total days.');

const loadingHtml = buildHolidayCalendarLoadingHtml();
assertIncludes(loadingHtml, 'spinner-border', 'Loading state should show a spinner.');

console.log('holiday_calendar_ui_test passed');
