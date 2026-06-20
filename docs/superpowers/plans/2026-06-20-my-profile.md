# My Profile Page Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a self-service profile page where logged-in employees can view their own HR profile and update only low-risk personal/contact fields.

**Architecture:** Add a new `my_profile.php` page that reads only `$_SESSION['employee_id']`, a narrow `update_my_profile` action in `api/employee_api.php`, and a topbar link in `includes/header.php`. Protect the update path with a whitelist and never reuse the full HR employee update action for employee self-service.

**Tech Stack:** PHP 8 style procedural pages, MySQLi prepared statements, Bootstrap 5, SweetAlert2, existing `assets/js/utils.js` helpers, focused PHP contract tests.

---

## File Structure

- Create `my_profile.php`: authenticated self-service page, profile fetch, read-only cards, editable form, page-local submit script.
- Modify `api/employee_api.php`: route `update_my_profile` and implement a session-bound update function.
- Modify `includes/header.php`: point the existing profile dropdown item to `my_profile.php`.
- Create `tests/my_profile_contract_test.php`: static contract test for page, menu, and API whitelist behavior.

### Task 1: Contract Test

**Files:**
- Create: `tests/my_profile_contract_test.php`

- [ ] **Step 1: Write the failing contract test**

Create `tests/my_profile_contract_test.php`:

```php
<?php
$root = dirname(__DIR__);
$pagePath = $root . '/my_profile.php';
$apiPath = $root . '/api/employee_api.php';
$headerPath = $root . '/includes/header.php';

function assertContainsText($haystack, $needle, $message) {
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, "Assertion failed: {$message}\nMissing: {$needle}\n");
        exit(1);
    }
}

function assertNotContainsText($haystack, $needle, $message) {
    if (strpos($haystack, $needle) !== false) {
        fwrite(STDERR, "Assertion failed: {$message}\nUnexpected: {$needle}\n");
        exit(1);
    }
}

function sliceFunction($source, $functionName) {
    $needle = 'function ' . $functionName;
    $start = strpos($source, $needle);
    if ($start === false) {
        fwrite(STDERR, "Assertion failed: function {$functionName} should exist\n");
        exit(1);
    }

    $next = strpos($source, "\nfunction ", $start + strlen($needle));
    if ($next === false) {
        return substr($source, $start);
    }
    return substr($source, $start, $next - $start);
}

if (!file_exists($pagePath)) {
    fwrite(STDERR, "Assertion failed: my_profile.php should exist\n");
    exit(1);
}

$page = file_get_contents($pagePath);
$api = file_get_contents($apiPath);
$header = file_get_contents($headerPath);
$selfUpdate = sliceFunction($api, 'updateMyProfile');

assertContainsText($page, "require_once 'includes/auth_check.php';", 'My profile page should require login.');
assertContainsText($page, "\$_SESSION['employee_id']", 'My profile page should use the current session employee id.');
assertNotContainsText($page, "\$_GET['id']", 'My profile page should not accept an employee id from the URL.');
assertContainsText($page, 'id="myProfileForm"', 'My profile page should render the self-service form.');
assertContainsText($page, 'action=update_my_profile', 'My profile form should submit to the self-service API action.');

foreach ([
    'education_level',
    'phone_number',
    'current_address',
    'province',
    'district',
    'postal_code',
    'emergency_contact_name',
    'emergency_contact_phone',
] as $field) {
    assertContainsText($page, 'name="' . $field . '"', "My profile form should include {$field}.");
    assertContainsText($selfUpdate, "'" . $field . "'", "Self-service API should update {$field}.");
}

assertContainsText($header, 'href="my_profile.php"', 'Topbar profile link should open my_profile.php.');
assertContainsText($api, "\$action === 'update_my_profile'", 'Employee API should route update_my_profile.');
assertContainsText($selfUpdate, "\$_SESSION['employee_id']", 'Self-service update should use the session employee id.');
assertContainsText($selfUpdate, 'ensureEmployeePostalCodeColumn($mysqli);', 'Self-service update should ensure postal_code exists.');
assertNotContainsText($selfUpdate, "getVal(\$data, 'id'", 'Self-service update should ignore posted employee id.');

foreach ([
    'company_id',
    'branch_id',
    'department_id',
    'position_id',
    'supervisor_id',
    'default_shift_id',
    'status',
    'role',
    'password',
    'username',
    'citizen_id',
    'birth_date',
    'first_name_th',
    'last_name_th',
] as $protectedField) {
    assertNotContainsText($selfUpdate, $protectedField . '=?', "Self-service update should not update {$protectedField}.");
    assertNotContainsText($selfUpdate, "'" . $protectedField . "'", "Self-service update should not read {$protectedField} from posted data.");
}

echo "My profile contract checks passed.\n";
```

- [ ] **Step 2: Run test to verify it fails**

Run: `C:\xampp\php\php.exe tests\my_profile_contract_test.php`

Expected: FAIL because `my_profile.php` does not exist.

### Task 2: Self-Service API

**Files:**
- Modify: `api/employee_api.php`

- [ ] **Step 1: Add the route**

In the main action router, add:

```php
elseif ($action === 'update_my_profile') {
    echo json_encode(updateMyProfile($mysqli, $_POST));
}
```

- [ ] **Step 2: Add the update function**

Add this function near the other employee update helpers:

```php
function updateMyProfile($mysqli, $data) {
    try {
        $employeeId = (int)($_SESSION['employee_id'] ?? 0);
        if ($employeeId <= 0) {
            throw new Exception('ไม่พบข้อมูลพนักงานของผู้ใช้งานนี้');
        }

        ensureEmployeePostalCodeColumn($mysqli);

        $profileData = [
            getVal($data, 'phone_number'),
            getVal($data, 'current_address'),
            getVal($data, 'district'),
            getVal($data, 'province'),
            getVal($data, 'postal_code'),
            getVal($data, 'education_level'),
            getVal($data, 'emergency_contact_name'),
            getVal($data, 'emergency_contact_phone'),
            $employeeId,
        ];

        $stmt = $mysqli->prepare("UPDATE employees SET
            phone_number=?,
            current_address=?,
            district=?,
            province=?,
            postal_code=?,
            education_level=?,
            emergency_contact_name=?,
            emergency_contact_phone=?
            WHERE id=?");
        if (!$stmt) {
            throw new Exception('Prepare self-service profile update failed: ' . $mysqli->error);
        }

        $stmt->bind_param('ssssssssi', ...$profileData);
        if (!$stmt->execute()) {
            throw new Exception('Self-service profile update failed: ' . $stmt->error);
        }

        if ($stmt->affected_rows < 0) {
            throw new Exception('ไม่สามารถบันทึกข้อมูลส่วนตัวได้');
        }

        return ['status' => 'success', 'message' => 'บันทึกข้อมูลส่วนตัวเรียบร้อยแล้ว'];
    } catch (Exception $e) {
        return ['status' => 'error', 'message' => $e->getMessage()];
    }
}
```

- [ ] **Step 3: Run contract test**

Run: `C:\xampp\php\php.exe tests\my_profile_contract_test.php`

Expected: FAIL because page and navigation are still missing.

### Task 3: My Profile Page

**Files:**
- Create: `my_profile.php`

- [ ] **Step 1: Create the page**

Create `my_profile.php` with:

```php
<?php
require_once 'includes/auth_check.php';
require_once 'includes/date_helpers.php';

$employeeId = (int)($_SESSION['employee_id'] ?? 0);
$emp = null;

if ($employeeId > 0) {
    $sql = "SELECT
                e.*,
                c.company_name_th,
                b.branch_name_th,
                d.dept_name_th,
                p.position_name_th,
                et.type_name AS employment_type_name,
                ws.shift_name,
                ws.start_time AS shift_start_time,
                ws.end_time AS shift_end_time,
                CONCAT(s.first_name_th, ' ', s.last_name_th) AS supervisor_name,
                u.username
            FROM employees e
            LEFT JOIN companies c ON e.company_id = c.id
            LEFT JOIN branches b ON e.branch_id = b.id
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN positions p ON e.position_id = p.id
            LEFT JOIN employment_types et ON e.employment_type_id = et.id
            LEFT JOIN work_shifts ws ON e.default_shift_id = ws.id
            LEFT JOIN employees s ON e.supervisor_id = s.id
            LEFT JOIN users u ON e.id = u.employee_id
            WHERE e.id = ?
            LIMIT 1";
    $stmt = $mysqli->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('i', $employeeId);
        $stmt->execute();
        $emp = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

function myProfileValue($value) {
    $value = trim((string)($value ?? ''));
    return $value !== '' ? htmlspecialchars($value) : '-';
}

function myProfileDate($date) {
    if (empty($date) || $date === '0000-00-00') {
        return '-';
    }
    return htmlspecialchars(formatThaiDate($date));
}

function myProfileGender($gender) {
    $labels = ['male' => 'ชาย', 'female' => 'หญิง', 'other' => 'อื่นๆ'];
    return $labels[$gender] ?? '-';
}

function myProfileStatus($status) {
    $labels = ['active' => 'ปฏิบัติงาน', 'probation' => 'ทดลองงาน', 'resigned' => 'ลาออก'];
    return $labels[$status] ?? myProfileValue($status);
}

function myProfileMaritalStatus($status) {
    $labels = ['single' => 'โสด', 'married' => 'สมรส', 'divorced' => 'หย่าร้าง', 'widowed' => 'หม้าย'];
    return $labels[$status] ?? '-';
}

$page_title = 'ข้อมูลของฉัน';
require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-start mb-3">
    <div>
        <h1 class="h3 mb-1">ข้อมูลของฉัน</h1>
        <p class="text-muted mb-0">ตรวจสอบข้อมูลพนักงาน และแก้ไขข้อมูลติดต่อที่จำเป็นได้ด้วยตัวเอง</p>
    </div>
</div>

<?php if (!$emp): ?>
    <div class="alert alert-warning">
        ไม่พบข้อมูลพนักงานที่ผูกกับบัญชีผู้ใช้งานนี้ กรุณาติดต่อ HR เพื่อตรวจสอบบัญชี
    </div>
<?php else: ?>
    <?php
        $imgSrc = (!empty($emp['profile_img_url']) && $emp['profile_img_url'] !== 'default.png') ? $emp['profile_img_url'] : 'assets/img/user.png';
        $fullNameTh = trim(($emp['prefix_th'] ?? '') . ' ' . ($emp['first_name_th'] ?? '') . ' ' . ($emp['last_name_th'] ?? ''));
        $fullNameEn = trim(($emp['prefix_en'] ?? '') . ' ' . ($emp['first_name_en'] ?? '') . ' ' . ($emp['last_name_en'] ?? ''));
        $shiftLabel = trim((string)($emp['shift_name'] ?? ''));
        if ($shiftLabel !== '' && !empty($emp['shift_start_time']) && !empty($emp['shift_end_time'])) {
            $shiftLabel .= ' (' . substr((string)$emp['shift_start_time'], 0, 5) . '-' . substr((string)$emp['shift_end_time'], 0, 5) . ')';
        }
    ?>

    <div class="row g-3 mb-3">
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-body text-center">
                    <img src="<?php echo htmlspecialchars($imgSrc); ?>" class="img-thumbnail rounded-circle mb-3" style="width: 140px; height: 140px; object-fit: cover;" onerror="this.onerror=null;this.src='assets/img/user.png';" alt="Profile image">
                    <h2 class="h5 mb-1"><?php echo myProfileValue($fullNameTh); ?></h2>
                    <div class="text-muted"><?php echo myProfileValue($emp['position_name_th'] ?? ''); ?></div>
                    <div class="small text-muted mt-2">รหัสเข้าสู่ระบบ: <?php echo myProfileValue($emp['username'] ?? ''); ?></div>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header bg-light">ข้อมูลพนักงาน</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6"><div class="text-muted small">ชื่อภาษาอังกฤษ</div><div class="fw-semibold"><?php echo myProfileValue($fullNameEn); ?></div></div>
                        <div class="col-md-6"><div class="text-muted small">ชื่อเล่น</div><div class="fw-semibold"><?php echo myProfileValue($emp['nickname'] ?? ''); ?></div></div>
                        <div class="col-md-6"><div class="text-muted small">เลขบัตรประชาชน</div><div class="fw-semibold"><?php echo myProfileValue($emp['citizen_id'] ?? ''); ?></div></div>
                        <div class="col-md-6"><div class="text-muted small">วันเกิด</div><div class="fw-semibold"><?php echo myProfileDate($emp['birth_date'] ?? null); ?></div></div>
                        <div class="col-md-4"><div class="text-muted small">เพศ</div><div class="fw-semibold"><?php echo myProfileGender($emp['gender'] ?? ''); ?></div></div>
                        <div class="col-md-4"><div class="text-muted small">สถานภาพ</div><div class="fw-semibold"><?php echo myProfileMaritalStatus($emp['marital_status'] ?? ''); ?></div></div>
                        <div class="col-md-4"><div class="text-muted small">กรุ๊ปเลือด</div><div class="fw-semibold"><?php echo myProfileValue($emp['blood_group'] ?? ''); ?></div></div>
                        <div class="col-md-6"><div class="text-muted small">ศาสนา</div><div class="fw-semibold"><?php echo myProfileValue($emp['religion'] ?? ''); ?></div></div>
                        <div class="col-md-6"><div class="text-muted small">สถานะพนักงาน</div><div class="fw-semibold"><?php echo myProfileStatus($emp['status'] ?? ''); ?></div></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header bg-light">ข้อมูลการทำงาน</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4"><div class="text-muted small">บริษัท</div><div class="fw-semibold"><?php echo myProfileValue($emp['company_name_th'] ?? ''); ?></div></div>
                <div class="col-md-4"><div class="text-muted small">สาขา</div><div class="fw-semibold"><?php echo myProfileValue($emp['branch_name_th'] ?? ''); ?></div></div>
                <div class="col-md-4"><div class="text-muted small">แผนก</div><div class="fw-semibold"><?php echo myProfileValue($emp['dept_name_th'] ?? ''); ?></div></div>
                <div class="col-md-4"><div class="text-muted small">ประเภทพนักงาน</div><div class="fw-semibold"><?php echo myProfileValue($emp['employment_type_name'] ?? ''); ?></div></div>
                <div class="col-md-4"><div class="text-muted small">หัวหน้างาน</div><div class="fw-semibold"><?php echo myProfileValue($emp['supervisor_name'] ?? ''); ?></div></div>
                <div class="col-md-4"><div class="text-muted small">กะทำงาน</div><div class="fw-semibold"><?php echo myProfileValue($shiftLabel); ?></div></div>
                <div class="col-md-4"><div class="text-muted small">วันเริ่มงาน</div><div class="fw-semibold"><?php echo myProfileDate($emp['start_date'] ?? null); ?></div></div>
            </div>
        </div>
    </div>

    <form id="myProfileForm" class="card mb-4" data-province="<?php echo htmlspecialchars($emp['province'] ?? ''); ?>" data-district="<?php echo htmlspecialchars($emp['district'] ?? ''); ?>">
        <div class="card-header bg-light">ข้อมูลที่แก้ไขเองได้</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">ระดับการศึกษา</label>
                    <select name="education_level" class="form-select">
                        <option value="">-- ระบุระดับการศึกษา --</option>
                        <?php foreach (['ต่ำกว่าปริญญาตรี','ปวช.','ปวส.','ปริญญาตรี','ปริญญาโท','ปริญญาเอก'] as $level): ?>
                            <option value="<?php echo htmlspecialchars($level); ?>" <?php echo (($emp['education_level'] ?? '') === $level) ? 'selected' : ''; ?>><?php echo htmlspecialchars($level); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">เบอร์โทรศัพท์มือถือ</label>
                    <input type="text" class="form-control" name="phone_number" maxlength="10" inputmode="tel" value="<?php echo htmlspecialchars($emp['phone_number'] ?? ''); ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">ที่อยู่</label>
                    <textarea class="form-control" name="current_address" rows="2"><?php echo htmlspecialchars($emp['current_address'] ?? ''); ?></textarea>
                </div>
                <div class="col-md-4">
                    <label class="form-label">จังหวัด</label>
                    <select id="provinceSelect" name="province" class="form-select">
                        <option value="">-- เลือกจังหวัด --</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">อำเภอ/เขต</label>
                    <select id="districtSelect" name="district" class="form-select">
                        <option value="">-- เลือกอำเภอ --</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">รหัสไปรษณีย์</label>
                    <input type="text" class="form-control" name="postal_code" maxlength="10" inputmode="numeric" value="<?php echo htmlspecialchars($emp['postal_code'] ?? ''); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">บุคคลติดต่อฉุกเฉิน</label>
                    <input type="text" class="form-control" name="emergency_contact_name" value="<?php echo htmlspecialchars($emp['emergency_contact_name'] ?? ''); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">เบอร์โทรผู้ติดต่อฉุกเฉิน</label>
                    <input type="text" class="form-control" name="emergency_contact_phone" maxlength="10" inputmode="tel" value="<?php echo htmlspecialchars($emp['emergency_contact_phone'] ?? ''); ?>">
                </div>
            </div>
        </div>
        <div class="card-footer text-end">
            <button type="submit" class="btn btn-primary px-4">
                <i class="fas fa-save me-1"></i> บันทึกข้อมูล
            </button>
        </div>
    </form>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>

<?php if ($emp): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('myProfileForm');
    if (!form) return;

    if (typeof initProvinceDistrictSelectors === 'function') {
        initProvinceDistrictSelectors({
            provinceSelectId: 'provinceSelect',
            districtSelectId: 'districtSelect',
            selectedProvince: form.dataset.province || '',
            selectedDistrict: form.dataset.district || ''
        });
    }

    form.addEventListener('submit', async function(event) {
        event.preventDefault();
        const submitButton = form.querySelector('button[type="submit"]');
        if (submitButton) submitButton.disabled = true;

        try {
            const response = await fetch('api/employee_api.php?action=update_my_profile', {
                method: 'POST',
                body: new FormData(form)
            });
            const result = await response.json();

            if (result.status === 'success') {
                await Swal.fire({ icon: 'success', title: 'บันทึกข้อมูลแล้ว', text: result.message, timer: 1600, showConfirmButton: false });
                window.location.reload();
                return;
            }

            Swal.fire({ icon: 'error', title: 'บันทึกไม่สำเร็จ', text: result.message || 'ไม่สามารถบันทึกข้อมูลได้' });
        } catch (error) {
            console.error('My Profile Save Error:', error);
            Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: 'ไม่สามารถเชื่อมต่อ API ได้' });
        } finally {
            if (submitButton) submitButton.disabled = false;
        }
    });
});
</script>
<?php endif; ?>
```

- [ ] **Step 2: Run contract test**

Run: `C:\xampp\php\php.exe tests\my_profile_contract_test.php`

Expected: FAIL because header navigation still points to `#`.

### Task 4: Navigation

**Files:**
- Modify: `includes/header.php`

- [ ] **Step 1: Update topbar profile link**

Change the profile dropdown item from:

```php
<li><a class="dropdown-item" href="#"><i class="fas fa-id-card me-2 text-muted"></i> โปรไฟล์</a></li>
```

to:

```php
<li><a class="dropdown-item" href="my_profile.php"><i class="fas fa-id-card me-2 text-muted"></i> โปรไฟล์</a></li>
```

- [ ] **Step 2: Run contract test**

Run: `C:\xampp\php\php.exe tests\my_profile_contract_test.php`

Expected: PASS with `My profile contract checks passed.`

### Task 5: Syntax And Diff Verification

**Files:**
- Verify: `my_profile.php`
- Verify: `api/employee_api.php`
- Verify: `includes/header.php`
- Verify: `tests/my_profile_contract_test.php`

- [ ] **Step 1: Run PHP syntax checks**

Run:

```powershell
C:\xampp\php\php.exe -l my_profile.php
C:\xampp\php\php.exe -l api\employee_api.php
C:\xampp\php\php.exe -l includes\header.php
C:\xampp\php\php.exe -l tests\my_profile_contract_test.php
```

Expected: each command reports `No syntax errors detected`.

- [ ] **Step 2: Run contract test again**

Run: `C:\xampp\php\php.exe tests\my_profile_contract_test.php`

Expected: PASS with `My profile contract checks passed.`

- [ ] **Step 3: Run whitespace check**

Run: `git diff --check`

Expected: no output and exit code 0.
