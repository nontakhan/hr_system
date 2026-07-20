const fs = require('fs');
const assert = require('assert');

const read = path => fs.readFileSync(path, 'utf8');
const stylesheet = read('assets/style.css');

assert(stylesheet.includes('.request-status-actions'), 'Shared stylesheet must define the request action layout');
assert(/\.request-status-actions\s*\{[^}]*display:\s*flex;/s.test(stylesheet), 'Request actions must use flex layout');
assert(/\.request-status-actions\s*\{[^}]*flex-wrap:\s*wrap;/s.test(stylesheet), 'Request actions must wrap on narrow screens');
assert(/\.request-status-actions\s*\{[^}]*gap:\s*(?!0(?:[;\s]))[^;]+;/s.test(stylesheet), 'Request actions must have a visible gap');
assert(stylesheet.includes('.request-cancel-button'), 'Shared stylesheet must define a compact cancellation button');
assert(/\.request-cancel-button\s*\{[^}]*font-size:\s*0\.75rem;/s.test(stylesheet), 'Cancellation buttons must visually match status badge text size');

const requestScripts = [
    ['leave history', read('assets/js/my_leaves.js')],
    ['time request history', read('assets/js/late_early_request.js')],
    ['day swap history', read('assets/js/day_swap.js')],
    ['training request history', read('assets/js/training_request.js')],
];

for (const [name, source] of requestScripts) {
    assert(source.includes('request-status-actions'), `${name} must use the shared request action layout`);
    assert(source.includes('request-cancel-button'), `${name} must use the compact cancellation button`);
}

for (const [name, source] of requestScripts.slice(1)) {
    assert(source.includes('request-cancellation-reason'), `${name} must render cancellation reasons on a separate row`);
    assert(source.includes('return `${action}${reason}`;'), `${name} must keep the action beside the status and place its reason afterward`);
}

console.log('request_cancellation_action_spacing_ui_test passed');
