<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (extension_loaded('mysqli')) { fwrite(STDERR,"Run with php -n. No database connection is used.\n"); exit(2); }
ob_start();
require dirname(__DIR__,3) . '/tests/performance_query_budget_test.php';
ob_end_clean();
$counts=[];
foreach (['month'=>['2026-01','2026-01'],'twelve_months'=>['2026-01','2026-12']] as $name=>$dates) {
    $db->statements=[];
    buildAttendanceReportRange($db,$employee,...$dates);
    $counts[$name]=count($db->statements);
}
$db->statements=[]; approvalBadgeFetchCounts($db,'admin',1,[]); $counts['badges']=count($db->statements);
require dirname(__DIR__,3) . '/includes/page_assets.php';
$assets=[];
foreach (['dashboard.php','employees.php','my_leaves.php','attendance.php','leave_request.php'] as $page) {
    $list=hrPageAssets($page);
    $bytes=0; foreach ($list['scripts'] as $script) $bytes+=filesize(dirname(__DIR__,3).'/assets/js/'.$script.'.js');
    $assets[$page]=['modules'=>count($list['scripts']),'raw_bytes'=>$bytes,'datatables'=>$list['datatables'],'chart'=>$list['chart']];
}
$rows=[];for($i=0;$i<2000;$i++)$rows[]=['requester_employee_id'=>($i%1000)+1,'target_employee_id'=>(($i+1)%1000)+1,'requester_date'=>'2026-07-04','target_date'=>'2026-07-05'];
$ids=range(1,1000);$times=[];
foreach(['legacy','grouped'] as $mode) {
    $samples=[];
    for($i=0;$i<5;$i++) {
        $start=hrtime(true);
        if($mode==='legacy') { $maps=[];foreach($ids as $id)$maps[$id]=attendanceBuildApprovedDaySwapMap($rows,$id,'2026-07'); }
        else $maps=attendanceBuildApprovedDaySwapMaps($rows,$ids,'2026-07');
        $samples[]=(hrtime(true)-$start)/1e6;
    }
    sort($samples);$times[$mode]=['median_ms'=>round($samples[2],3),'trials'=>5,'checksum'=>hash('sha256',json_encode($maps))];
}
if($times['legacy']['checksum']!==$times['grouped']['checksum'])throw new RuntimeException('Swap maps differ');
echo json_encode(['simulated_dataset_sql'=>$counts,'scope'=>'Helper statement attempts only, excluding database bootstrap and actual DB latency','page_assets_excluding_vendors'=>$assets,'synthetic_swaps_1000_employees_2000_rows'=>$times],JSON_PRETTY_PRINT),PHP_EOL;
