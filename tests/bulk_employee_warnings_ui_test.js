const fs = require('fs');

const script = fs.readFileSync('assets/js/bulk_employee_warnings.js', 'utf8');
const footer = fs.readFileSync('includes/footer.php', 'utf8');

function includes(text, needle, message) {
    if (!text.includes(needle)) {
        throw new Error(`${message}: ${needle}`);
    }
}

function excludes(text, needle, message) {
    if (text.includes(needle)) {
        throw new Error(`${message}: ${needle}`);
    }
}

includes(script, 'window.EmployeeWarningBulk', 'Shared module must publish one namespace');
includes(script, 'selectedKeys = new Set()', 'Selection must be independent of DataTable pages');
includes(script, 'toggleAllEligible', 'Controller must select the complete eligible result set');
includes(script, 'bulk_create', 'Controller must submit one batch request');
includes(script, 'already_warned', 'Duplicates must be excluded');
includes(script, 'disabled = true', 'Submit must be disabled during requests');
includes(script, 'syncCheckboxes', 'DataTable redraws must restore checkbox state');
includes(script, 'bulkEmployeeWarningSharedNote', 'Modal must expose one shared note field');
includes(script, 'uniqueEmployeeCount', 'Modal must show a unique employee count');
includes(script, 'shared_note', 'Bulk payload must send one shared note');
excludes(script, 'bulk_preview', 'Modal must not call the per-event preview API');
excludes(script, 'warningDetail', 'Modal must not render one textarea per event');
excludes(script, 'bulkEmployeeWarningPreviewRows', 'Modal must not render an event preview container');
includes(footer, 'assets/js/bulk_employee_warnings.js', 'Footer must load shared module');

console.log('bulk employee warnings UI contract passed');
