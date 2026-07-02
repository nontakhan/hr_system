const fs = require('fs');

function assertIncludes(text, expected, message) {
    if (!text.includes(expected)) {
        console.error(message);
        console.error('Missing:', expected);
        process.exit(1);
    }
}

const header = fs.readFileSync('includes/header.php', 'utf8');
const favicon = fs.readFileSync('assets/img/nr-backoffice-favicon.svg', 'utf8');

assertIncludes(header, "function buildSystemPageTitle($pageTitle = '')", 'Header should centralize page title formatting.');
assertIncludes(header, "$systemTitle = 'ระบบ NR Backoffice';", 'System title should use the requested NR Backoffice brand.');
assertIncludes(header, "return $systemTitle . ' | ' . $pageTitle;", 'Page titles should append the current page heading after the brand prefix.');
assertIncludes(header, 'href="assets/img/nr-backoffice-favicon.svg"', 'Header should load the NR Backoffice favicon.');
assertIncludes(favicon, '#dc2626', 'Favicon should use the app red accent color.');
assertIncludes(favicon, '#b91c1c', 'Favicon should use the app primary color.');
assertIncludes(favicon, '>NR<', 'Favicon should carry the NR mark.');

console.log('app_branding_test passed');
