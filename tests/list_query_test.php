<?php
require_once __DIR__ . '/../includes/list_helpers.php';
$base = 'SELECT id, name FROM employees WHERE company_id = ? ORDER BY id DESC';
$plan = hrListQueryPlan($base, 'i', [8], ['name'], ['name','id'], ['draw'=>'7','start'=>-50,'length'=>50000,'search'=>['value'=>"ก' OR 1=1 --"],'order'=>[['column'=>'0; DROP TABLE x','dir'=>'desc; --']]]);
if ($plan['length'] !== 100 || $plan['start'] !== 0 || $plan['draw'] !== 7) throw new RuntimeException('Unsafe page bounds');
if (str_contains($plan['sql'], 'DROP') || str_contains($plan['sql'], 'OR 1=1')) throw new RuntimeException('Input leaked to SQL');
if ($plan['params'] !== [8, "%ก'%", '%OR%', '%1==1%', '%--%']) throw new RuntimeException('Scope/search binding changed');
if (!str_contains($plan['sql'], 'company_id = ?')) throw new RuntimeException('Scope lost');
if (hrListIsPaged([]) || !hrListIsPaged(['draw'=>0])) throw new RuntimeException('Legacy mode changed');
echo "PASS bounded and scoped list query
";
