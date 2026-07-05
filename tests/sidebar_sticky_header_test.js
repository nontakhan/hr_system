const fs = require('fs');

function assertIncludes(text, expected, message) {
    if (!text.includes(expected)) {
        console.error(message);
        console.error('Missing:', expected);
        process.exit(1);
    }
}

const css = fs.readFileSync('assets/style.css', 'utf8');
const headingRuleStart = css.indexOf('#sidebar-wrapper .sidebar-heading {');
const headingRuleEnd = css.indexOf('}', headingRuleStart);

if (headingRuleStart === -1 || headingRuleEnd === -1) {
    console.error('Sidebar heading rule should exist in the shared stylesheet.');
    process.exit(1);
}

const headingRule = css.slice(headingRuleStart, headingRuleEnd);

assertIncludes(headingRule, 'position: sticky;', 'Sidebar heading should stay pinned while the sidebar menu scrolls.');
assertIncludes(headingRule, 'top: 0;', 'Sticky sidebar heading should pin to the top of the sidebar viewport.');
assertIncludes(headingRule, 'z-index:', 'Sticky sidebar heading should stay above scrolling menu items.');

console.log('sidebar_sticky_header_test passed');
