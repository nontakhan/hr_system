const {execFileSync} = require('child_process');
const path = require('path');
exports.footer = page => execFileSync(process.env.PHP_BINARY || (process.platform === 'win32' ? 'C:/xampp/php/php.exe' : 'php'), [path.join(__dirname,'render_footer.php'), page], {encoding:'utf8'});
