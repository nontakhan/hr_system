// Run focused PHP/JavaScript regressions from the repository root.
const fs = require('fs');
const path = require('path');
const cp = require('child_process');
const root = path.resolve(__dirname, '..');
const php = process.env.HR_TEST_PHP || (process.platform === 'win32' ? 'C:/xampp/php/php.exe' : 'php');
const results = fs.readdirSync(path.join(root, 'tests'))
    .filter(file => /_test\.(php|js)$/.test(file)).map(file => {
        const isPhp = file.endsWith('.php');
        const source = fs.readFileSync(path.join(root, 'tests', file), 'utf8');
        const noIni = isPhp && source.includes("if (extension_loaded('mysqli'))");
        const result = cp.spawnSync(isPhp ? php : process.execPath,
            [...(noIni ? ['-n'] : []), path.join(root, 'tests', file)], { cwd: root, encoding: 'utf8' });
        const output = (result.stdout || '') + (result.stderr || '') + (result.error ? String(result.error) : '');
        return { file, passed: result.status === 0, skipped: /^SKIP /m.test(output), output };
    });
const failures = results.filter(result => !result.passed);
console.log(JSON.stringify({
    total: results.length,
    passed: results.filter(result => result.passed && !result.skipped).length,
    skipped: results.filter(result => result.skipped).map(result => result.file),
    failures
}, null, 2));
process.exitCode = failures.length ? 1 : 0;
