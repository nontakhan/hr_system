const fs = require('fs');

function includes(source, needle, message) {
    if (!source.includes(needle)) {
        throw new Error(`${message}\nMissing: ${needle}`);
    }
}

const page = fs.readFileSync('employee_warnings.php', 'utf8');
const script = fs.readFileSync('assets/js/employee_warnings.js', 'utf8');
const helper = fs.readFileSync('includes/employee_warning_helpers.php', 'utf8');

includes(page, 'id="employeeWarningId"', 'Warning form needs a stable edit id');
includes(page, 'id="employeeWarningModalTitle"', 'Warning modal needs an editable title hook');
includes(page, 'id="employeeWarningSubmitLabel"', 'Warning modal needs an editable submit label hook');
includes(page, 'จัดการ', 'Warning detail table needs an actions column');
includes(page, 'id="employeeWarningSearchForm"', 'Warning page needs a name-search form');
includes(page, 'id="employeeWarningSearchName"', 'Warning page needs a name-search input');
includes(page, 'id="clearEmployeeWarningSearchBtn"', 'Warning page needs a clear-search button');
includes(script, 'btn-employee-warning-edit', 'Warning details need an edit action');
includes(script, 'btn-employee-warning-delete', 'Warning details need a delete action');
includes(script, "data.action = data.id ? 'update_warning' : 'create_warning'", 'Warning form must select update mode from its id');
includes(script, "method: 'DELETE'", 'Warning delete must use DELETE');
includes(script, "action: 'delete_warning'", 'Warning delete must call the record action');
includes(script, 'search_employee_warnings', 'Warning search must call the all-month API');
includes(script, 'employee_warning_history', 'History detail mode must call the all-month detail API');
includes(script, 'searchEmployeeWarnings', 'Warning page needs a search handler');
includes(script, 'clearEmployeeWarningSearch', 'Warning page needs a clear-search handler');
includes(script, "mode = 'month'", 'Warning detail context must distinguish month and history modes');
includes(helper, 'ew.warning_type_id', 'Warning detail response needs warning type id');
includes(helper, 'ew.employee_id', 'Warning detail response needs employee id');

console.log('employee warning edit/delete UI contract passed');
