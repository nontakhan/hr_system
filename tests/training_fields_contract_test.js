const fs = require('fs');

function read(path) {
  return fs.readFileSync(path, 'utf8');
}

function assertNotIncludes(content, needle, message) {
  if (content.includes(needle)) {
    throw new Error(`${message}\nUnexpected: ${needle}`);
  }
}

function assertIncludes(content, needle, message) {
  if (!content.includes(needle)) {
    throw new Error(`${message}\nMissing: ${needle}`);
  }
}

function sliceBetween(content, startNeedle, endNeedle) {
  const start = content.indexOf(startNeedle);
  const end = content.indexOf(endNeedle, start);
  if (start === -1 || end === -1) {
    throw new Error(`Cannot find source slice from ${startNeedle} to ${endNeedle}`);
  }
  return content.slice(start, end);
}

const employeeView = read('employee_view.php');
const employeeApi = read('api/employee_api.php');
const employeeScript = read('assets/js/employee.js');
const requestPage = read('training_request.php');
const historyPage = read('training_history.php');
const requestApi = read('api/training_request_api.php');
const requestHelper = read('includes/training_request_helpers.php');
const requestScript = read('assets/js/training_request.js');
const proxyPage = read('request_proxy.php');
const proxyApi = read('api/proxy_request_api.php');
const employeeTrainingTable = sliceBetween(employeeView, 'id="trainingHistoryTable"', '<!-- Training Modal -->');

[
  ['employee training form', employeeView],
  ['employee training API', employeeApi],
  ['employee training JS', employeeScript],
  ['training request page', requestPage],
  ['training request API', requestApi],
  ['training request helper', requestHelper],
  ['training request JS', requestScript],
  ['proxy training page', proxyPage],
  ['proxy training API', proxyApi],
].forEach(([label, content]) => {
  assertNotIncludes(content, 'name="provider"', `${label} should not expose provider inputs.`);
  assertNotIncludes(content, 'name="training_type"', `${label} should not expose training type inputs.`);
  assertNotIncludes(content, 'name="estimated_cost"', `${label} should not expose estimated cost inputs.`);
  assertNotIncludes(content, 'trainingProvider', `${label} should not reference the removed provider field.`);
  assertNotIncludes(content, 'trainingType', `${label} should not reference the removed training type field.`);
  assertNotIncludes(content, 'estimated_cost', `${label} should not reference the removed estimated cost field.`);
});

assertNotIncludes(employeeTrainingTable, '<th>ผู้จัด/สถาบัน</th>', 'Employee training history table should not show provider.');
assertNotIncludes(employeeTrainingTable, '<th>ประเภท</th>', 'Employee training history table should not show training type.');
assertNotIncludes(historyPage, '<th>ผู้จัด/สถานที่</th>', 'Training request history should not combine provider with location.');
assertIncludes(historyPage, '<th>สถานที่/รูปแบบ</th>', 'Training request history should keep location visible.');
assertIncludes(requestHelper, 'employee_id, training_date, course_name, result_status', 'History records created from requests should only persist kept training fields.');

console.log('training_fields_contract_test passed');
