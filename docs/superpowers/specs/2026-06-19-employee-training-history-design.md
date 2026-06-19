# Employee Training History Design

## Goal

Add an employee training-history feature to `employee_view.php` so HR/admin users can record and review each employee's training records from the employee profile page.

## Selected UX

Use the same interaction pattern as the existing transfer workflow.

- Add a `บันทึกอบรม` button beside `โยกย้าย/ปรับตำแหน่ง` for `admin` and `hr` users.
- Open a Bootstrap modal for create/edit training records.
- Add a `ประวัติการฝึกอบรม` card in `employee_view.php`, near the existing transfer-history card.
- Show training records in a table with refresh, edit, delete, and optional attachment link actions.
- Non-HR users can view the training-history card if they can access the employee profile, but create/edit/delete controls remain limited to `admin` and `hr`.

## Data Model

Create a new table named `employee_training_records`.

Core fields:

- `id`
- `employee_id`
- `training_date`
- `course_name`
- `provider`
- `training_type`
- `result_status`
- `certificate_expiry_date`
- `attachment_path`
- `notes`
- `created_by`
- `updated_by`
- `created_at`
- `updated_at`

Rules:

- `employee_id`, `training_date`, and `course_name` are required.
- `attachment_path` is nullable. Training records can be saved without a file.
- Keep one optional attachment per training record in the first version.
- Use a foreign key to `employees.id` where the local schema supports it.

## API Shape

Extend the existing employee API pattern rather than adding page-local POST handling.

Recommended actions:

- `training_history`: list records for an employee.
- `save_training`: create or update one record.
- `delete_training`: delete one record.

Validation:

- Require `admin` or `hr` for save/delete.
- Require a valid employee id.
- Validate dates as `Y-m-d`.
- Limit uploaded files to safe document/image types such as PDF, JPG, PNG, and WEBP.
- Store uploads under an employee-training subdirectory outside user-controlled filenames.

## Frontend Flow

In `employee_view.php`:

- Render the new button beside the transfer button.
- Render the training-history card and empty loading row.
- Render the training modal with fields for the data model above.
- Load training history on `DOMContentLoaded`.

In `assets/js/employee.js`:

- Add `loadTrainingHistory(employeeId)`.
- Add training-form submit handling with `FormData` so file upload works.
- Add edit handling that populates the modal from a selected row.
- Add delete handling with confirmation.
- Refresh the table after save/delete.

## Display

Training-history table columns:

- วันที่อบรม
- หลักสูตร
- ผู้จัด/สถาบัน
- ประเภท
- ผลลัพธ์
- ใบรับรองหมดอายุ
- เอกสาร
- หมายเหตุ
- จัดการ

For missing optional values, show `-`. For attachments, show a compact `เปิดไฟล์` link only when a file exists.

## Error Handling

- Show a loading row while fetching history.
- Show an empty-state row when no training records exist.
- Show user-friendly alerts for validation or upload failures.
- Do not remove an existing attachment when editing unless a new file is uploaded. The first version will not include a separate remove-attachment action.

## Testing

Manual and focused verification:

- PHP lint changed PHP files with `C:\xampp\php\php.exe -l`.
- Run `node --check assets/js/employee.js`.
- Create a training record with no attachment.
- Create or edit a training record with an attachment.
- Verify list refresh, edit prefill, attachment link display, and delete.
- Verify non-HR users do not see create/edit/delete controls if their profile access allows viewing.
