<?php
// Synthetic parser benchmark only; no database or user data.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__, 3) . '/includes/attendance_helpers.php';
if (isset($argv[1], $argv[2])) {
    $start = hrtime(true);
    $rows = $argv[1] === 'stream' ? attendanceIterateCsvRows($argv[2]) : attendanceReadCsvRows($argv[2]);
    $count = 0;
    $hash = hash_init('sha256');
    foreach ($rows as $row) { $count++; hash_update($hash, json_encode($row)); }
    echo json_encode(['mode'=>$argv[1], 'rows'=>$count, 'checksum'=>hash_final($hash),
        'peak_bytes'=>memory_get_peak_usage(true), 'elapsed_ms'=>round((hrtime(true)-$start)/1e6,2)]);
    exit;
}
$file = tempnam(sys_get_temp_dir(), 'hr-csv-memory-');
try {
    $handle = fopen($file,'w');
    fputcsv($handle,['header']);
    $row = array_fill(0,20,''); $row[0]='synthetic-fixture'; $row[3]='05/01/2026'; $row[10]='08:00'; $row[18]='17:00';
    for ($i=0;$i<100000;$i++) fputcsv($handle,$row);
    fclose($handle);
    $results=[];
    foreach (['materialized','stream'] as $mode) {
        $pipes=[];
        $process=proc_open([PHP_BINARY,__FILE__,$mode,$file],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        fclose($pipes[0]); $out=stream_get_contents($pipes[1]); fclose($pipes[1]); $err=stream_get_contents($pipes[2]); fclose($pipes[2]);
        if (proc_close($process)!==0) throw new RuntimeException($err);
        $results[]=json_decode($out,true,512,JSON_THROW_ON_ERROR);
    }
    if ($results[0]['rows']!==100000 || $results[0]['checksum']!==$results[1]['checksum']) throw new RuntimeException('Parser results differ');
    echo json_encode(['synthetic_rows'=>100000,'results'=>$results,'scope'=>'Parser peak memory only; excludes database and import writes'],JSON_PRETTY_PRINT),PHP_EOL;
} finally { unlink($file); }
