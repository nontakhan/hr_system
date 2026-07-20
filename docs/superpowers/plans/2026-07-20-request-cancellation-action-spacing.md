# Request Cancellation Action Spacing Implementation Plan

> **For agentic workers:** Implement this plan task-by-task with test-first changes and verification after every task.

**Goal:** Make cancellation actions consistently spaced and responsive across every employee request-history workflow.

**Architecture:** Add one shared layout class in `assets/style.css`. Wrap each combined status/action cell with that class and mark cancellation reasons as full-width secondary content; preserve existing Bootstrap buttons, event handlers, permissions, and APIs.

**Tech Stack:** PHP-rendered pages, vanilla JavaScript templates, Bootstrap 5.3, shared CSS, Node.js contract tests.

## Global Constraints

- Preserve the existing Sarabun/Bootstrap visual language.
- Do not change cancellation state transitions, permissions, confirmation dialogs, APIs, or persistence.
- Support wrapping on narrow screens without overlapping or touching controls.
- Do not modify unrelated worktree changes and do not commit unless the user requests it.

---

### Task 1: Shared request-action layout contract

**Files:**
- Create: `tests/request_cancellation_action_spacing_ui_test.js`
- Modify: `assets/style.css`
- Modify: `assets/js/my_leaves.js`
- Modify: `assets/js/late_early_request.js`
- Modify: `assets/js/day_swap.js`
- Modify: `assets/js/training_request.js`

**Interfaces:**
- Consumes: existing Bootstrap status badges and `btn btn-sm btn-outline-danger` cancellation buttons.
- Produces: `.request-status-actions` as a wrapping flex container and `.request-cancellation-reason` as a full-width reason row.

- [ ] **Step 1: Write the failing contract test**

Create a Node.js test that reads the shared stylesheet and four request scripts. Assert that CSS defines `.request-status-actions`, `display: flex`, `flex-wrap: wrap`, and a positive `gap`; assert that all four scripts use the shared action class, and the three workflows with inline reasons use `.request-cancellation-reason`.

- [ ] **Step 2: Run the test and verify RED**

Run: `node tests\request_cancellation_action_spacing_ui_test.js`

Expected: FAIL because `.request-status-actions` is not present.

- [ ] **Step 3: Add the minimal shared CSS**

Append a scoped rule to `assets/style.css`:

```css
.request-status-actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.5rem;
}

.request-status-actions .request-cancellation-reason {
    flex: 0 0 100%;
    margin: 0;
}
```

- [ ] **Step 4: Apply the class to all request histories**

In `my_leaves.js`, mark the action cell with `class="request-status-actions"` so the same spacing contract applies if more row actions are added.

In `late_early_request.js`, `day_swap.js`, and `training_request.js`, wrap the status badge plus cancellation rendering in `<div class="request-status-actions">...</div>`. Replace reason-only `mt-1` markup with `request-cancellation-reason small text-danger` so reasons occupy their own row.

- [ ] **Step 5: Run the contract test and verify GREEN**

Run: `node tests\request_cancellation_action_spacing_ui_test.js`

Expected: PASS with `request_cancellation_action_spacing_ui_test passed`.

### Task 2: Workflow and syntax verification

**Files:**
- Verify: `assets/style.css`
- Verify: `assets/js/my_leaves.js`
- Verify: `assets/js/late_early_request.js`
- Verify: `assets/js/day_swap.js`
- Verify: `assets/js/training_request.js`
- Verify: `tests/request_cancellation_action_spacing_ui_test.js`

**Interfaces:**
- Consumes: the layout contract from Task 1.
- Produces: verified UI templates without workflow behavior changes.

- [ ] **Step 1: Check JavaScript syntax**

Run `node --check` separately for all five JavaScript files. Expected: exit code 0 for each.

- [ ] **Step 2: Run focused cancellation tests**

Run:

```powershell
node tests\request_cancellation_action_spacing_ui_test.js
node tests\leave_cancellation_request_test.js
node tests\request_cancellation_workflows_test.js
C:\xampp\php\php.exe tests\request_cancellation_helpers_test.php
```

Expected: every test prints its pass message and exits 0.

- [ ] **Step 3: Inspect the rendered workflows**

Open the leave, late/early/OT, day-swap, and training request-history pages through the normal XAMPP URL. At desktop and narrow widths verify visible space between status and cancellation action, clean wrapping, and a separate full-width cancellation-reason row.

- [ ] **Step 4: Review the patch**

Run `git diff --check`, `git diff -- assets/style.css assets/js/my_leaves.js assets/js/late_early_request.js assets/js/day_swap.js assets/js/training_request.js tests/request_cancellation_action_spacing_ui_test.js`, and `git status --short`. Expected: no whitespace errors and no unrelated files changed by this task.
