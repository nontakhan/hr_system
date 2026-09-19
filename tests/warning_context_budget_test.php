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
        if (str_contains($this->sql, 'SELECT e.id, e.citizen_id')) return new AuditResult([['id'=>1,'first_name_th'=>'Test','last_name_th'=>'Employee','start_time'=>'08:00:00','end_time'=>'17:00:00','work_days'=>'Mon,Tue,Wed,Thu,Fri']]);
        if (str_contains($this->sql, 'SELECT id FROM leave_types')) return new AuditResult([['id' => 1]]);
        return new AuditResult([]);
    }
    public function close() {}
}
$root = dirname(__DIR__);
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


require_once $root . '/includes/employee_warning_helpers.php';
require_once $root . '/includes/attendance_warning_source_helpers.php';
$cache = [];
$db->statements = [];
$a = attendanceWarningResolveContext($db, 1, '2026-09-01', 'admin', [], $cache);
$first = count($db->statements);
$b = attendanceWarningResolveContext($db, 1, '2026-09-02', 'admin', [], $cache);
if (count($db->statements) !== $first) throw new RuntimeException('Second date reloaded employee/month context');
if ($a['employee'] !== $b['employee'] || $b['record']['check_in'] !== null) throw new RuntimeException('Unexpected context');
$separateCache = [];
attendanceWarningResolveContext($db, 1, '2026-09-02', 'admin', [], $separateCache);
if (count($db->statements) === $first) throw new RuntimeException('Cache leaked across operations');
echo "PASS warning context reused only within one operation
";
