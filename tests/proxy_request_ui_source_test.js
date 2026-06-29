const fs = require('fs');

function assertIncludes(file, needle) {
  const source = fs.readFileSync(file, 'utf8');
  if (!source.includes(needle)) {
    throw new Error(`${file} missing ${needle}`);
  }
}

assertIncludes('request_proxy.php', 'id="proxyEmployeeId"');
assertIncludes('request_proxy.php', 'data-proxy-panel="leave"');
assertIncludes('request_proxy.php', 'data-proxy-panel="late_early"');
assertIncludes('request_proxy.php', 'data-proxy-panel="overtime"');
assertIncludes('request_proxy.php', 'data-proxy-panel="day_swap"');
assertIncludes('request_proxy.php', 'data-proxy-panel="training"');
assertIncludes('request_proxy.php', 'assets/js/proxy_request.js');
assertIncludes('assets/js/proxy_request.js', 'api/proxy_request_api.php?action=employees');
assertIncludes('assets/js/proxy_request.js', 'create_leave');
assertIncludes('assets/js/proxy_request.js', 'create_late_early');
assertIncludes('assets/js/proxy_request.js', 'create_overtime');
assertIncludes('assets/js/proxy_request.js', 'create_day_swap');
assertIncludes('assets/js/proxy_request.js', 'create_training');
assertIncludes('includes/header.php', 'request_proxy.php');
