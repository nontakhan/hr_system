const fs = require('fs'), vm = require('vm'), assert = require('assert');
const ctx = vm.createContext({ URL, URLSearchParams, window: {location:{href:'http://localhost/employees.php'}}, console });
vm.runInContext(fs.readFileSync('assets/js/paged_tables.js','utf8'),ctx);
const url=vm.runInContext("pagedTableUrl('api/employee_api.php?branch_id=8', {draw:2,start:20,length:10,search:{value:'สมชาย'},order:[{column:1,dir:'asc'}]})",ctx);
assert.strictEqual(url.searchParams.get('branch_id'),'8');
assert.strictEqual(url.searchParams.get('search[value]'),'สมชาย');
assert.strictEqual(url.searchParams.get('order[0][column]'),'1');
assert.strictEqual(url.searchParams.get('draw'),'2');
console.log('PASS paged table preserves filters and encodes DataTables protocol');
