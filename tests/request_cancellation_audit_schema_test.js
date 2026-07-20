const fs = require('fs');
const assert = require('assert');

const read = path => fs.readFileSync(path, 'utf8');
const sources = [
    ['leave schema helper', read('includes/leave_helpers.php')],
    ['day swap schema helper', read('includes/day_swap_helpers.php')],
    ['training schema helper', read('includes/training_request_helpers.php')],
    ['deployment migration', read('database_add_request_cancellation_audit.sql')],
];

const auditColumns = [
    'cancelled_by_user_id',
    'cancelled_by_employee_id',
    'cancelled_by_role',
    'cancelled_at',
];

for (const [name, source] of sources) {
    for (const column of auditColumns) {
        assert(source.includes(column), `${name} missing ${column}`);
    }
}

const migration = sources[3][1];
for (const table of ['leave_requests', 'day_swap_requests', 'training_requests']) {
    assert(migration.includes(`ALTER TABLE ${table}`), `migration missing ${table}`);
}

console.log('request_cancellation_audit_schema_test passed');
