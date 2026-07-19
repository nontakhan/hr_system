# Request Cancellation Approval Design

## Objective

Extend the existing leave-cancellation behavior to the other employee request workflows:

- late-arrival and early-departure requests;
- overtime-after-work requests;
- day-swap requests; and
- training/activity requests.

Every cancellation requires an employee-provided reason. A request that has not completed approval is cancelled immediately. A request that is already approved remains effective while HR/Admin reviews the cancellation request.

## Existing Architecture

Late/early and overtime requests are stored in `leave_requests` and distinguished by `request_unit = 'hour'` and `time_request_type`. They already share the leave approval pipeline but are intentionally separated from ordinary leave history and UI.

Day-swap requests are stored in `day_swap_requests`. Training/activity requests are stored in `training_requests`. Both use the two-stage `pending_manager -> pending_hr -> approved` approval model and already support a terminal `cancelled` status, but neither supports cancellation requests after approval.

Ordinary leave is the reference behavior. It uses `pending_cancel_hr`, stores `cancellation_reason`, keeps an approved request effective during cancellation review, and allows HR/Admin to approve or reject the cancellation.

## State Contract

The same state transitions apply to all four request families:

| Current state | Employee action | New state | Reviewer action |
| --- | --- | --- | --- |
| `pending`, `pending_manager`, or `pending_hr` | Submit a non-empty cancellation reason | `cancelled` | None |
| `approved` | Submit a non-empty cancellation reason | `pending_cancel_hr` | HR/Admin review required |
| `pending_cancel_hr` | HR/Admin approves cancellation | `cancelled` | Cancellation takes effect |
| `pending_cancel_hr` | HR/Admin rejects cancellation | `approved` | Original approval remains effective |

Manager-only users must not approve or reject cancellation requests. HR and Admin may review them. Repeated or stale actions must fail safely through status-qualified updates rather than overwriting a newer state.

## Persistence Design

`leave_requests` already contains the required `pending_cancel_hr` enum member and `cancellation_reason` column. Late/early and overtime requests will reuse these fields without adding another table.

The schema assurance helpers for `day_swap_requests` and `training_requests` will:

- add `pending_cancel_hr` to the status enum; and
- add nullable `cancellation_reason TEXT`.

Schema assurance must be idempotent and preserve existing rows and status values. No separate cancellation table is introduced.

## Employee History and Cancellation Flow

Cancellation is exposed only from the employee's own history screen:

- `late_early_history.php` and `overtime_history.php`, backed by `assets/js/late_early_request.js` and `api/late_early_request_api.php`;
- `day_swap_history.php`, backed by `assets/js/day_swap.js` and `api/day_swap_api.php`; and
- `training_history.php`, backed by `assets/js/training_request.js` and `api/training_request_api.php`.

Rows in `pending`, `pending_manager`, `pending_hr`, or `approved` show a cancellation action. The dialog requires a reason and changes its confirmation copy for approved requests to explain that HR/Admin review is required. Rows in `pending_cancel_hr`, `rejected`, or `cancelled` cannot submit another cancellation.

Each API must verify all of the following before changing state:

- the authenticated employee owns the request;
- the request belongs to the endpoint's request family;
- the current status is cancellable; and
- the trimmed cancellation reason is non-empty.

For `leave_requests`, the late/early endpoint accepts only `late_arrival` and `early_departure`; the overtime endpoint mode accepts only `overtime_after_work`. This prevents cross-family cancellation by changing an ID or request type in the browser.

## HR/Admin Review Flow

Existing approval pages remain the review surfaces:

- `late_early_approvals.php` and `overtime_approvals.php` use the shared leave approval API/script, filtered by `time_request_type`;
- `day_swap_approvals.php` uses the day-swap API/script; and
- `training_approvals.php` uses the training API/script.

Their pending queues include `pending_cancel_hr` for HR/Admin, display the cancellation reason, and use explicit Thai actions: `อนุมัติยกเลิก` and `ไม่อนุมัติยกเลิก`. Manager queues exclude cancellation requests. Approval-history views include `cancelled` rows.

HR/Admin badge counts must include cancellation requests in the correct request-family badge. Manager badge counts remain limited to the original manager approval stages.

## Effective-Before-Approval Rule

An approved request in `pending_cancel_hr` remains effective until cancellation is approved:

- late/early and overtime continue to affect attendance calculations and calendar/context displays;
- day swaps continue to affect work-date/holiday resolution; and
- training/activity continues to appear as approved attendance context.

Every query that currently treats only `approved` as effective for these request families must be reviewed and changed to include `pending_cancel_hr` where appropriate. Once status becomes `cancelled`, the request no longer affects those consumers.

## UI and Localization

Use the existing Bootstrap/Sarabun design language. Status labels are:

- `pending_cancel_hr`: `รอ HR/Admin อนุมัติยกเลิก`;
- `cancelled`: `ยกเลิกแล้ว`.

The employee history row shows the stored cancellation reason for pending and completed cancellations. Reviewer queues show `เหตุผลขอยกเลิก` close to the request identity and dates. All dynamic values are escaped before rendering.

## Error Handling and Concurrency

APIs return a validation error when the reason is blank, the request is not owned by the caller, the request type does not match the endpoint, or the status is no longer cancellable. Status transitions use an expected-status predicate in the `UPDATE` statement and verify the affected-row count so simultaneous review actions cannot both succeed.

Rejecting a cancellation restores only `approved`; it does not erase the employee's cancellation reason. This retains an audit-visible explanation in history. Cancellation review reuses the existing `hr_approver_id`, `hr_approval_date`, `approver_id`, and `approval_date` fields in the same way as ordinary leave. A rejection stores the HR/Admin explanation in `rejection_reason`; an approval clears `rejection_reason`. No new cancellation-review audit columns are added.

## Security and Authorization

- Employee cancellation endpoints require an authenticated employee identity and row ownership.
- Cancellation review requires HR or Admin; manager approval alone is insufficient.
- Request-family filters are enforced in SQL, not only JavaScript.
- SQL input uses prepared statements.
- Rendered reasons and request data are HTML-escaped.
- Proxy-created requests remain cancellable only by the employee who owns the resulting request; this design does not add cancellation on the HR proxy-entry screen.

## Testing and Acceptance

Focused regression coverage must prove:

1. each request family requires a non-empty reason;
2. pending-stage requests cancel immediately;
3. approved requests enter `pending_cancel_hr`;
4. employees cannot cancel another employee's request or a mismatched request type;
5. HR/Admin can approve or reject cancellation while managers cannot;
6. cancellation approval produces `cancelled`, while rejection restores `approved`;
7. reviewer queues, badges, employee history, Thai status text, and reasons render correctly;
8. `pending_cancel_hr` remains effective in attendance, calendar, day-swap, and training consumers; and
9. status-qualified updates reject stale or repeated actions.

Verification includes PHP syntax checks for every changed PHP file, JavaScript syntax checks for every changed script, focused PHP/Node regression tests, existing approval and attendance/calendar tests affected by the state expansion, and `git diff --check`.

## Out of Scope

- changing the ordinary leave cancellation contract;
- adding manager review before HR/Admin cancellation review;
- allowing employees to withdraw a pending cancellation request;
- adding cancellation controls to HR proxy-entry screens;
- building a new central cancellation table or a new combined approval page; and
- committing or pushing changes without an explicit user request.
