<?php
function hrPageAssets(string $page): array
{
    static $scripts = [
        'dashboard.php' => ['dashboard'],
        'employees.php' => ['employee'],
        'employee_add.php' => ['employee'],
        'employee_edit.php' => ['employee'],
        'employee_view.php' => ['employee'],
        'manage_master_data.php' => ['master_data'],
        'shifts.php' => ['shift'],
        'company_holidays.php' => ['company_holidays'],
        'holiday_calendar.php' => ['holiday_calendar'],
        'leave_types.php' => ['leave'],
        'leave_request.php' => ['request_display', 'leave_request'],
        'my_leaves.php' => ['request_display', 'my_leaves'],
        'leave_approvals.php' => ['request_display', 'leave_approval'],
        'late_early_approvals.php' => ['request_display', 'leave_approval'],
        'overtime_approvals.php' => ['request_display', 'leave_approval'],
        'late_early_request.php' => ['request_display', 'late_early_request'],
        'late_early_history.php' => ['request_display', 'late_early_request'],
        'overtime_request.php' => ['request_display', 'late_early_request'],
        'overtime_history.php' => ['request_display', 'late_early_request'],
        'day_swap_request.php' => ['request_display', 'day_swap'],
        'day_swap_history.php' => ['request_display', 'day_swap'],
        'day_swap_approvals.php' => ['request_display', 'day_swap'],
        'training_request.php' => ['request_display', 'training_request'],
        'training_history.php' => ['request_display', 'training_request'],
        'training_approvals.php' => ['request_display', 'training_request'],
        'attendance.php' => ['attendance'],
        'attendance_import.php' => ['attendance'],
        'attendance_adjustments.php' => ['attendance'],
        'attendance_missing_report.php' => ['bulk_employee_warnings', 'attendance'],
        'attendance_late_early_report.php' => ['bulk_employee_warnings', 'attendance'],
        'leave_report.php' => ['bulk_employee_warnings', 'leave_report'],
        'employee_request_attendance_report.php' => ['employee_request_attendance_report'],
        'employee_warnings.php' => ['employee_warnings'],
        'my_warnings.php' => ['employee_warnings'],
        'activity_types.php' => ['activity_types'],
        'request_proxy.php' => ['proxy_request'],
        'change_password.php' => [],
        'my_profile.php' => []
    ];
    $dataTablePages = ['employee_request_attendance_report.php','employees.php', 'manage_master_data.php', 'shifts.php', 'leave_types.php', 'my_leaves.php', 'leave_approvals.php', 'late_early_approvals.php', 'overtime_approvals.php', 'late_early_history.php', 'overtime_history.php', 'day_swap_history.php', 'day_swap_approvals.php', 'training_history.php', 'training_approvals.php', 'attendance_import.php', 'attendance_adjustments.php', 'attendance_missing_report.php', 'attendance_late_early_report.php', 'leave_report.php'];
    return [
        'scripts' => array_merge(['utils'], in_array($page, ['employees.php','leave_approvals.php','late_early_approvals.php','overtime_approvals.php','my_leaves.php','late_early_history.php','overtime_history.php','day_swap_history.php','day_swap_approvals.php','training_history.php','training_approvals.php'], true) ? ['paged_tables'] : [], $scripts[$page] ?? []),
        'datatables' => in_array($page, $dataTablePages, true),
        'chart' => $page === 'dashboard.php',
    ];
}
