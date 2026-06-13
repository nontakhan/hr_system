<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

function sendHolidayCalendarJson($payload) {
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit();
}

function sendHolidayCalendarError($message) {
    sendHolidayCalendarJson(['status' => 'error', 'message' => $message]);
}

try {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

    require_once '../includes/db_connect.php';
    require_once '../includes/attendance_helpers.php';
    require_once '../includes/day_swap_helpers.php';
    require_once '../includes/holiday_calendar_helpers.php';

    header('Content-Type: application/json');

    if (!isset($_SESSION['user_id'])) {
        sendHolidayCalendarError('Login Required');
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        sendHolidayCalendarError('Method Not Allowed');
    }

    $month = $_GET['month'] ?? date('Y-m');
    if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
        sendHolidayCalendarError('Invalid month');
    }

    $employeeId = (int)($_SESSION['employee_id'] ?? 0);
    if ($employeeId <= 0) {
        sendHolidayCalendarError('Employee profile not found');
    }

    $companyHolidays = holidayCalendarFetchCompanyHolidaysForMonth($mysqli, $month);
    $regularHolidays = daySwapBuildHolidayOptions($mysqli, $employeeId, $month);
    $events = holidayCalendarBuildEvents($companyHolidays, $regularHolidays);

    sendHolidayCalendarJson([
        'status' => 'success',
        'month' => $month,
        'summary' => holidayCalendarBuildSummary($events),
        'data' => $events,
    ]);
} catch (Throwable $e) {
    error_log($e->getMessage());
    sendHolidayCalendarError('System Error');
}
