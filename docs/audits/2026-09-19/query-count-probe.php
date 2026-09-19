<?php
// Counts SQL statements attempted by real helpers against a fake, fully migrated schema.
// MUST run with -n. This script has no database connection or network capability.
// C:\xampp\php\php.exe -n docs\audits\2026-09-19\query-count-probe.php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (extension_loaded('mysqli')) { fwrite(STDERR, "Run with php -n; mysqli must be disabled.\n"); exit(2); }
define('MYSQLI_ASSOC', 1);
class AuditResult {
    public int $num_rows;
    private int $offset = 0;
    public function __construct(private array $rows) { $this->num_rows = count($rows); }
    public function fetch_assoc() { return $this->rows[$this->offset++] ?? null; }
    public function fetch_all($mode = 1) { return $this->rows; }
}
class mysqli {
    public array $statements = [];
    public array $columns = [];
    public function query($sql) {
        $this->statements[] = $sql;
        if (str_starts_with($sql, 'SHOW COLUMNS')) return new AuditResult($this->columns);
        if (str_contains($sql, 'COUNT(*)')) return new AuditResult([['total' => 1]]);
        return true;
    }
    public function prepare($sql) { return new mysqli_stmt($this, $sql); }
}
class mysqli_stmt {
    public function __construct(private mysqli $db, private string $sql) {}
    public function bind_param($types, &...$values) { return true; }
    public function execute() { $this->db->statements[] = $this->sql; return true; }
    public function get_result() {
        if (str_contains($this->sql, 'SELECT id FROM leave_types')) return new AuditResult([['id' => 1]]);
        return new AuditResult([]);
    }
    public function close() {}
}
$root = dirname(__DIR__, 3);
foreach (['attendance_helpers','leave_helpers','day_swap_helpers','hr_scope_helpers',
          'employee_shift_assignment_helpers','training_request_helpers','approval_badge_helpers'] as $helper) {
    require_once $root . '/includes/' . $helper . '.php';
}
$db = new mysqli();
$columnNames = [];
foreach (glob($root . '/includes/*helpers.php') as $file) {
    preg_match_all('/\$columns\[\x27([^\x27]+)\x27\]/', file_get_contents($file), $matches);
    foreach ($matches[1] as $column) $columnNames[$column] = true;
}
foreach (['created_by_user_id', 'created_by_employee_id', 'created_by_role', 'created_via', 'proxy_note'] as $column) $columnNames[$column] = true;
foreach (array_keys($columnNames) as $column) {
    $db->columns[] = ['Field' => $column, 'Type' => "enum('pending_cancel_hr','overtime_after_work')"];
}
// Load definitions only, never the API request dispatcher.
$source = file_get_contents($root . '/api/attendance_api.php');
$offset = strpos($source, 'function resolveAttendanceEmployeeId(');
if ($offset === false) throw new RuntimeException('Cannot locate helper definitions');
eval(substr($source, $offset));
$employee = ['id'=>1, 'start_time'=>'08:00:00', 'end_time'=>'17:00:00',
    'late_tolerance_mins'=>0, 'work_days'=>'Mon,Tue,Wed,Thu,Fri'];
$cases = [
    'monthly_report_empty_fixture' => fn() => buildMonthlyAttendanceReport($db, $employee, '2026-07'),
    'twelve_month_report_empty_fixture' => fn() => buildAttendanceReportRange($db, $employee, '2026-01', '2026-12'),
    'sidebar_badges_admin' => fn() => approvalBadgeFetchCounts($db, 'admin', 1, []),
    'ensure_existing_hourly_types' => fn() => leaveEnsureHourlyRequestTypes($db),
];
foreach ($cases as $name => $run) {
    $db->statements = [];
    $run();
    $counts = [];
    foreach ($db->statements as $sql) {
        $verb = strtok(ltrim($sql), " \r\n\t");
        $counts[$verb] = ($counts[$verb] ?? 0) + 1;
    }
    echo json_encode(['probe' => $name, 'sql_attempts' => count($db->statements),
        'by_verb' => $counts, 'database_connected' => false, 'alter_sql_examples' => array_values(array_unique(array_filter($db->statements, fn($sql) => str_starts_with($sql, 'ALTER'))))]), PHP_EOL;
}
