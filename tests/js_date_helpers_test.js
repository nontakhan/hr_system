const fs = require('fs');
const vm = require('vm');

const code = fs.readFileSync('assets/js/utils.js', 'utf8');
vm.runInThisContext(code);

function assertSame(expected, actual, message) {
    if (expected !== actual) {
        console.error(message);
        console.error('Expected:', expected);
        console.error('Actual:  ', actual);
        process.exit(1);
    }
}

assertSame('03/01/2569', formatThaiDate('2026-01-03'), 'Gregorian dates should display as Buddhist Era dates.');
assertSame('มกราคม 2569', formatThaiMonth('2026-01'), 'Gregorian months should display as Buddhist Era months.');
assertSame('2026', normalizeGregorianYearInput('2569'), 'Buddhist Era year input should be converted before API requests.');
assertSame('2026', normalizeGregorianYearInput('2026'), 'Gregorian year input should remain unchanged.');
assertSame('03/01/2569', toThaiDateInputValue('2026-01-03'), 'Date inputs should display Buddhist Era values.');
assertSame('2026-01-03', toGregorianDateInputValue('03/01/2569'), 'Buddhist Era date input should submit Gregorian values.');
assertSame('-', formatThaiDate(null), 'Empty dates should use the fallback.');

const nativeDateInput = {
    dataset: { nativeDatePicker: 'true' },
    type: 'date',
    value: '2026-01-03',
    inputMode: '',
    placeholder: '',
};
const thaiDateInput = {
    dataset: {},
    type: 'date',
    value: '2026-01-03',
    inputMode: '',
    placeholder: '',
    addEventListener() {},
};
setupThaiDateInputs({
    querySelectorAll() {
        return [nativeDateInput, thaiDateInput];
    },
});
assertSame('date', nativeDateInput.type, 'Native date picker inputs should not be converted to text.');
assertSame('text', thaiDateInput.type, 'Ordinary date inputs should still use Thai date text display.');
assertSame('true', thaiDateInput.dataset.thaiDatePicker, 'Ordinary date inputs should use the custom Thai date picker.');
setThaiDateInputValue(nativeDateInput, '03/01/2569');
assertSame('2026-01-03', nativeDateInput.value, 'Native date picker inputs should receive Gregorian date values.');

let fakePopover = null;
global.document = {
    body: {
        appendChild(node) {
            fakePopover = node;
        },
    },
    createElement() {
        return {
            className: '',
            hidden: true,
            style: {},
            innerHTML: '',
            handlers: {},
            addEventListener(type, handler) {
                this.handlers[type] = handler;
            },
            contains() {
                return false;
            },
        };
    },
};
global.window = { scrollX: 0, scrollY: 0 };

const interactiveThaiDateInput = {
    dataset: { thaiDatePicker: 'true' },
    value: '03/01/2569',
    getBoundingClientRect() {
        return { left: 0, bottom: 0, width: 280 };
    },
    dispatchEvent() {},
};
showThaiDatePicker(interactiveThaiDateInput);
assertSame(true, fakePopover.innerHTML.includes('มกราคม 2569') || fakePopover.innerHTML.includes('à¸¡à¸à¸£à¸²à¸„à¸¡ 2569'), 'Thai date picker should open on the current Buddhist Era month.');
let stoppedPropagation = false;
fakePopover.handlers.click({
    stopPropagation() {
        stoppedPropagation = true;
    },
    target: {
        closest(selector) {
            if (selector === '[data-thai-datepicker-action]') {
                return {
                    getAttribute(name) {
                        return name === 'data-thai-datepicker-action' ? 'next' : null;
                    },
                    dataset: {},
                };
            }
            return null;
        },
    },
});
assertSame(true, stoppedPropagation, 'Thai date picker navigation clicks should not bubble to the document close handler.');
assertSame(true, fakePopover.innerHTML.includes('กุมภาพันธ์ 2569') || fakePopover.innerHTML.includes('à¸à¸¸à¸¡à¸ à¸²à¸žà¸±à¸™à¸˜à¹Œ 2569'), 'Thai date picker next button should move to the next Buddhist Era month.');

console.log('js_date_helpers_test passed');
