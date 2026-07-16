const assert = require('assert');
const fs = require('fs');
const vm = require('vm');

class FakeElement {
    constructor(id = '') {
        this.id = id;
        this.listeners = new Map();
        this.children = [];
        this.parent = null;
        this.dataset = {};
        this.checked = false;
        this.disabled = false;
        this.indeterminate = false;
        this.textContent = '';
    }

    addEventListener(type, handler, options = false) {
        if (!this.listeners.has(type)) this.listeners.set(type, []);
        this.listeners.get(type).push({ handler, capture: options === true || Boolean(options?.capture) });
    }

    appendChild(child) {
        child.parent = this;
        this.children.push(child);
    }

    replaceChild(next, previous) {
        const index = this.children.indexOf(previous);
        if (index >= 0) this.children[index] = next;
        next.parent = this;
        previous.parent = null;
    }

    dispatchEvent(event) {
        event.target ||= this;
        event.stopPropagation ||= () => { event.propagationStopped = true; };
        const path = [];
        for (let current = this; current; current = current.parent) path.push(current);
        for (let index = path.length - 1; index > 0 && !event.propagationStopped; index -= 1) {
            (path[index].listeners.get(event.type) || [])
                .filter((listener) => listener.capture)
                .forEach((listener) => listener.handler(event));
        }
        if (!event.propagationStopped) {
            (this.listeners.get(event.type) || []).forEach((listener) => listener.handler(event));
        }
        if (event.bubbles) {
            for (let index = 1; index < path.length && !event.propagationStopped; index += 1) {
                (path[index].listeners.get(event.type) || [])
                    .filter((listener) => !listener.capture)
                    .forEach((listener) => listener.handler(event));
            }
        }
    }

    closest(selector) {
        if (selector === '.employee-warning-row-select' && this.isRowCheckbox) return this;
        return null;
    }

    querySelectorAll(selector) {
        if (selector !== '.employee-warning-row-select') return [];
        return this.visibleRowCheckboxes || [];
    }
}

const page = new FakeElement('reportPage');
const table = new FakeElement('reportTable');
const oldSelectAll = new FakeElement('warningSelectAll');
const actionButton = new FakeElement('warningAction');
const countElement = new FakeElement('warningCount');
page.appendChild(table);
table.appendChild(oldSelectAll);

const elements = new Map([
    [page.id, page],
    [oldSelectAll.id, oldSelectAll],
    [actionButton.id, actionButton],
    [countElement.id, countElement],
]);

const document = {
    body: new FakeElement('body'),
    getElementById(id) { return elements.get(id) || null; },
    querySelectorAll() { return []; },
};
const window = {};
const context = vm.createContext({ window, document, fetch: async () => { throw new Error('unexpected fetch'); }, console });
vm.runInContext(fs.readFileSync('assets/js/bulk_employee_warnings.js', 'utf8'), context);

const controller = window.EmployeeWarningBulk.create({
    pageId: page.id,
    actionButtonId: actionButton.id,
    selectedCountId: countElement.id,
    selectAllId: oldSelectAll.id,
    buildEvent: (row) => row,
});
const rows = Array.from({ length: 30 }, (_, index) => ({
    employee_id: index + 1,
    source_type: 'attendance_missing',
    source_key: `event-${index + 1}`,
    already_warned: false,
}));
controller.replaceRows(rows);

// DataTables may rebuild/move the table header after initialization.
const currentSelectAll = new FakeElement('warningSelectAll');
table.replaceChild(currentSelectAll, oldSelectAll);
elements.set(currentSelectAll.id, currentSelectAll);

// DataTables handles a bubbled header click as sorting and redraws the header
// before the checkbox change event can reach the report controller.
table.addEventListener('click', (event) => {
    const redrawnSelectAll = new FakeElement('warningSelectAll');
    table.replaceChild(redrawnSelectAll, event.target);
    elements.set(redrawnSelectAll.id, redrawnSelectAll);
});
currentSelectAll.checked = true;
currentSelectAll.dispatchEvent({ type: 'click', bubbles: true });
if (currentSelectAll.parent) currentSelectAll.dispatchEvent({ type: 'change', bubbles: true });

assert.strictEqual(countElement.textContent, '30', 'select-all must include rows on every DataTables page');

const pageTwoCheckbox = new FakeElement();
pageTwoCheckbox.isRowCheckbox = true;
pageTwoCheckbox.dataset.warningSourceKey = 'event-26';
page.visibleRowCheckboxes = [pageTwoCheckbox];
controller.syncCheckboxes();
assert.strictEqual(pageTwoCheckbox.checked, true, 'a checkbox drawn on a later page must restore selected state');

console.log('bulk employee warning pagination test passed');
