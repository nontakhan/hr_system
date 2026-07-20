<?php

function requestCancellationEmployeeTransition(string $status): ?string {
    if (in_array($status, ['pending', 'pending_manager', 'pending_hr'], true)) {
        return 'cancelled';
    }

    return $status === 'approved' ? 'pending_cancel_hr' : null;
}

function requestCancellationReviewerTransition(string $status, string $action, string $role): ?string {
    if ($status !== 'pending_cancel_hr' || !in_array($role, ['hr', 'admin'], true)) {
        return null;
    }
    if ($action === 'approve') {
        return 'cancelled';
    }

    return $action === 'reject' ? 'approved' : null;
}

function requestCancellationReviewerDirectTransition(string $status, string $role): ?string {
    if ($status !== 'approved' || !in_array($role, ['hr', 'admin'], true)) {
        return null;
    }

    return 'cancelled';
}
