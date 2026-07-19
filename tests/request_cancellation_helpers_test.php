<?php
require_once __DIR__ . '/../includes/request_cancellation_helpers.php';

function assertCancellationSame($expected, $actual, $message) {
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' expected=' . var_export($expected, true) . ' actual=' . var_export($actual, true));
    }
}

foreach (['pending', 'pending_manager', 'pending_hr'] as $status) {
    assertCancellationSame('cancelled', requestCancellationEmployeeTransition($status), "{$status} must cancel immediately");
}
assertCancellationSame('pending_cancel_hr', requestCancellationEmployeeTransition('approved'), 'approved must require HR/Admin review');
assertCancellationSame(null, requestCancellationEmployeeTransition('pending_cancel_hr'), 'duplicate cancellation must be rejected');
assertCancellationSame('cancelled', requestCancellationReviewerTransition('pending_cancel_hr', 'approve', 'hr'), 'HR approval must cancel');
assertCancellationSame('approved', requestCancellationReviewerTransition('pending_cancel_hr', 'reject', 'admin'), 'Admin rejection must restore approval');
assertCancellationSame(null, requestCancellationReviewerTransition('pending_cancel_hr', 'approve', 'manager'), 'Manager must not review cancellation');

echo "request_cancellation_helpers_test passed\n";
