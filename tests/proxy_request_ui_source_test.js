const fs = require('fs');

function assertIncludes(file, needle) {
  const source = fs.readFileSync(file, 'utf8');
  if (!source.includes(needle)) {
    throw new Error(`${file} missing ${needle}`);
  }
}

assertIncludes('request_proxy.php', 'id="proxyEmployeeId"');
assertIncludes('request_proxy.php', '$use_select2 = true;');
assertIncludes('request_proxy.php', 'proxy-type-btn proxy-type-leave');
assertIncludes('request_proxy.php', 'proxy-type-btn proxy-type-time');
assertIncludes('request_proxy.php', 'proxy-type-btn proxy-type-ot');
assertIncludes('request_proxy.php', 'proxy-type-btn proxy-type-swap');
assertIncludes('request_proxy.php', 'proxy-type-btn proxy-type-training');
assertIncludes('request_proxy.php', 'fas fa-calendar-check');
assertIncludes('request_proxy.php', 'data-proxy-panel="leave"');
assertIncludes('request_proxy.php', 'data-proxy-panel="late_early"');
assertIncludes('request_proxy.php', 'data-proxy-panel="overtime"');
assertIncludes('request_proxy.php', 'name="overtime_start_time"');
assertIncludes('request_proxy.php', 'name="overtime_end_time"');
assertIncludes('request_proxy.php', 'id="proxyOvertimeDateContext"');
assertIncludes('request_proxy.php', 'data-proxy-panel="day_swap"');
assertIncludes('request_proxy.php', 'data-proxy-panel="training"');
assertIncludes('request_proxy.php', 'assets/js/proxy_request.js');
assertIncludes('assets/js/proxy_request.js', 'api/proxy_request_api.php?action=employees');
assertIncludes('assets/js/proxy_request.js', 'select2');
assertIncludes('assets/js/proxy_request.js', 'aria-pressed');
assertIncludes('assets/js/proxy_request.js', 'create_leave');
assertIncludes('assets/js/proxy_request.js', 'create_late_early');
assertIncludes('assets/js/proxy_request.js', 'create_overtime');
assertIncludes('assets/js/proxy_request.js', 'loadProxyOvertimeDateContext');
assertIncludes('assets/js/proxy_request.js', 'formatProxyOvertimeDuration');
assertIncludes('assets/js/proxy_request.js', 'create_day_swap');
assertIncludes('assets/js/proxy_request.js', 'create_training');
assertIncludes('includes/header.php', 'request_proxy.php');
