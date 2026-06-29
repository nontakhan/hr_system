const fs = require('fs');

const apiFiles = [
  'api/leave_history_api.php',
  'api/late_early_request_api.php',
  'api/day_swap_api.php',
  'api/training_request_api.php',
];

for (const file of apiFiles) {
  const source = fs.readFileSync(file, 'utf8');
  if (!source.includes('created_via') || !source.includes('proxy_creator_name')) {
    throw new Error(`${file} must expose created_via and proxy_creator_name`);
  }
}

const jsFiles = [
  'assets/js/my_leaves.js',
  'assets/js/late_early_request.js',
  'assets/js/day_swap.js',
  'assets/js/training_request.js',
];

for (const file of jsFiles) {
  const source = fs.readFileSync(file, 'utf8');
  if (!source.includes('created_via') || !source.includes('สร้างโดย HR/Admin')) {
    throw new Error(`${file} must render proxy creator text`);
  }
}
