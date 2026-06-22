const fs = require('fs');

const source = fs.readFileSync('assets/js/employee.js', 'utf8');

function assertIncludes(text, expected, message) {
    if (!String(text).includes(expected)) {
        console.error(message);
        console.error('Missing:', expected);
        process.exit(1);
    }
}

assertIncludes(
    source,
    'bindProvinceDistrictChange',
    'Employee address province/district filtering should use a dedicated change binding helper.'
);
assertIncludes(
    source,
    'select2:select.employeeAddress',
    'Employee address province select should handle Select2 selections.'
);
assertIncludes(
    source,
    'select2:clear.employeeAddress',
    'Employee address province select should handle Select2 clears.'
);
assertIncludes(
    source,
    "trigger('change.select2')",
    'Employee address district Select2 should refresh after replacing district options.'
);

console.log('employee_address_select_test passed');
