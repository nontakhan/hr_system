# ผลแก้ UX เร่งด่วน: สถานะ สิทธิ์ และจุดที่ทำงานต่อไม่ได้

รายงานนี้เป็นรอบเร่งด่วนก่อนหน้า ดูผลแก้รายการคงเหลือล่าสุดใน [Complete UX fixes](complete-ux-fixes.md)

วันที่ 20 กันยายน 2026 — working tree บน `main` จาก `e785754`

ผู้ใช้อนุมัติให้แก้จุดเร่งด่วนโดยคงโครงสร้างเมนูเดิม และยืนยันว่า HR สร้าง/รีเซ็ตรหัสผ่านได้เฉพาะบัญชี employee ในขอบเขตตนเอง รายงานนี้บันทึกผลตรวจก่อนขั้นตอน commit/push และยังไม่มีการ deploy

## สิ่งที่เปลี่ยน

### สถานะ dashboard

- รวม `pending`, `pending_manager`, `pending_hr` ในยอดรออนุมัติ และแสดงรออนุมัติยกเลิกแยกจากยอดที่ยกเลิกแล้ว
- แสดงชื่อขั้นตอนเป็นภาษาไทย พร้อมตัวอักษรสีเข้มบนป้ายสถานะสีเหลือง
- ยอดรวมและรายการล่าสุดใช้เกณฑ์เดียวกับประวัติลา ไม่รวมคำขอมาสาย/ออกก่อน/OT ที่เก็บในตารางเดียวกัน
- โหลดข้อมูลไม่สำเร็จมีข้อความและปุ่มลองใหม่ แทนหน้าจอค้างว่าโหลดอยู่

ไฟล์: `api/dashboard_api.php`, `includes/dashboard_helpers.php`, `assets/js/dashboard.js`

### เข้าสู่ระบบและการกู้คืนแบบฟอร์ม

- รวมการควบคุมปุ่มเข้าสู่ระบบไว้จุดเดียว ป้องกันส่งซ้ำ และคืนปุ่มเมื่อรหัสไม่ถูกต้อง/การเชื่อมต่อล้มเหลว/หมดเวลารอ 15 วินาที โดยไม่ส่งซ้ำอัตโนมัติ
- ปุ่มลืมรหัสผ่านเปิดคำแนะนำให้ติดต่อ HR/admin และปุ่มแสดงรหัสผ่านใช้คีย์บอร์ดพร้อมชื่อและสถานะที่อ่านได้
- แบบฟอร์มลาแสดง loading/error/empty/retry ของประเภทลาและสรุปสิทธิ์ ปุ่มส่งรอข้อมูลประเภทลาที่จำเป็น ส่วนสรุปสิทธิ์เป็นข้อมูลเตือนและไม่บล็อกการกรอก
- หน้าทำรายการแทนมี retry แยกสำหรับพนักงาน ประเภทลา และประเภทกิจกรรม การโหลดหมวดหนึ่งล้มเหลวไม่ล้างค่าที่กรอกในฟอร์ม
- ข้อความ retry ของสรุปสิทธิ์คงอยู่เมื่อผู้ใช้กรอกหรือคำนวณใบลาต่อ

ไฟล์: `index.php`, `assets/js/login.js`, `leave_request.php`, `assets/js/leave_request.js`, `request_proxy.php`, `assets/js/proxy_request.js`

### สิทธิ์หน้าและ API ข้อมูลพนักงาน

| บทบาท | ผลที่บังคับใช้ |
|---|---|
| employee / manager | เปิดหน้าจัดการพนักงานหรือเรียก management API โดยตรงไม่ได้; self-profile และเส้นทางอนุมัติเดิมยังใช้ได้ |
| HR | ดู/แก้พนักงานเฉพาะ union ของบริษัทและสาขาที่ได้รับสิทธิ์; ตัวเลือกบริษัท/สาขา/หัวหน้างานสอดคล้องกับ scope |
| HR บัญชีผู้ใช้ | สร้าง/รีเซ็ตเฉพาะ employee ใน scope; เปลี่ยนบัญชีสิทธิ์สูงหรือกำหนด HR scopes ไม่ได้ |
| admin | จัดการบุคลากร บัญชี สิทธิ์ และลบพนักงานได้ตามเดิม |

- ใช้ role และ scope จากฐานข้อมูลใน management request เพื่อให้การเพิกถอนสิทธิ์มีผลในคำขอถัดไป
- ครอบคลุมรายการ รายละเอียด ประวัติย้ายงาน ประวัติอบรม เพิ่ม/แก้พนักงาน อัปโหลดรูป ย้ายหน่วยงาน และเพิ่ม/ลบประวัติอบรม
- ตรวจพนักงานต้นทางและหน่วยงานปลายทาง บริษัทต้องตรงกับสาขา; การแก้วันที่ในประวัติย้ายงานต้องไม่ทำให้พนักงานหลุด scope และ rollback เมื่อไม่ผ่าน
- HR ไม่เห็นปุ่มลบที่ API อนุญาตเฉพาะ admin; หน้าไม่มีสิทธิ์มีข้อความและลิงก์กลับหน้าหลัก
- HR ที่ยังไม่มี scope จะไม่ถูกพาเข้าฟอร์มเพิ่มพนักงานที่เลือกหน่วยงานไม่ได้
- personnel-only save ไม่เปลี่ยนบัญชีสิทธิ์สูง; รองรับข้อมูลเก่าที่พนักงานมีหลายบัญชีด้วยการตรวจทุกบัญชี และเขียนบัญชีที่เลือกด้วย `users.id`
- ไม่สร้าง migration ใหม่ ไม่เปลี่ยนโครงเมนูหรือขั้นตอนอนุมัติ

ไฟล์: `includes/employee_access_helpers.php`, `api/employee_api.php`, `employees.php`, `employee_add.php`, `employee_edit.php`, `employee_view.php`, `assets/js/employee.js`

## หลักฐานตรวจสอบ

- `HR_TEST_DB_PORT=33079 node scripts/run-tests.cjs`: **82/82 ผ่าน ไม่มี skip**
- PHP lint 17 ไฟล์ และ JavaScript syntax 8 ไฟล์ที่เกี่ยวข้องผ่าน
- `git diff --check` ผ่าน
- ฐานข้อมูลทดสอบเป็น MariaDB ที่ตั้งขึ้นเฉพาะบน loopback 33079; แต่ละ integration test สร้างฐานข้อมูลชื่อสุ่มและลบเมื่อเสร็จ ไม่ใช้ฐานข้อมูลระบบจริง
- ทดสอบล้มเหลวก่อนแก้ แล้วทดสอบผ่านหลังแก้สำหรับสถานะ/ยอดลา, login recovery, option retry, ขอบเขตสิทธิ์, บัญชีหลายรายการ และ HR ไม่มี scope
- ผู้ช่วยตรวจสิทธิ์ก่อนแก้ และ reviewer อีกตัวตรวจ patch อย่างอิสระ; ข้อกังวลเรื่องหลายบัญชีได้รับการแก้และตรวจซ้ำแล้ว

ชุด regression ใหม่:

- `tests/dashboard_status_summary_test.php`
- `tests/dashboard_leave_integration_test.php`
- `tests/urgent_ui_recovery_test.js`
- `tests/request_option_recovery_test.js`
- `tests/employee_access_integration_test.php` พร้อม CLI fixture harness `tests/support/employee_access_request.php`
- `tests/employee_actions_ui_test.js`

ปรับ fixture เดิมให้ใช้ HR role/scope ในฐานข้อมูลจริง และปรับ username contract ให้ตรวจการเขียนด้วย account ID ที่ผ่าน authorization

## Browser verification

ตรวจด้วย Chromium ผ่าน Playwright บน PHP fixture server ที่ใช้ฐานข้อมูลจำลองและปิด HTTP writes:

- Failed login และ network error คืนปุ่มให้ลองใหม่; username คงอยู่; help/password toggle ใช้งานได้; mocked success พาไป dashboard
- Dashboard แสดงขั้นตอนและยอดที่ตรงกับ fixture; failed fetch กดลองใหม่แล้วกลับมาแสดงข้อมูลได้
- ฟอร์มพนักงานและ proxy HR ลองโหลดข้อมูลใหม่สำเร็จ โดยข้อความที่กรอกไม่หาย รวมประเภทกิจกรรมในแท็บกิจกรรม
- HR เห็น role employee เท่านั้นและไม่มีปุ่มลบ; admin มีปุ่มลบ; employee/manager และ HR นอก scope ได้ HTTP 403 พร้อมทางกลับ
- ตรวจ desktop 1365×900 และ mobile 390×844; dashboard ที่ตรวจไม่มี horizontal overflow

ภาพและ browser-check scripts อยู่ใน `output/playwright/urgent-ux-fixes/` ซึ่งถูก ignore โดย Git:
`login-error-desktop.png`, `login-help-mobile.png`, `dashboard-desktop.png`, `dashboard-mobile.png`, `leave-retry-mobile.png`, `proxy-retry-desktop.png`, `employees-hr-desktop.png`, `outside-scope.png`

## ขอบเขตของหลักฐาน

ผลนี้เป็น local/fixture verification ไม่ใช่การยืนยัน production หรือ login ด้วยบัญชีจริง การบันทึกข้อมูลและการปฏิเสธสิทธิ์ทดสอบผ่าน PHP/API integration กับข้อมูลจำลอง; การล็อกอินใน browser ใช้ API จำลอง ไม่ได้พิสูจน์การรับรองตัวตนใน production

การป้องกันนี้ครอบคลุมหน้าและ API ข้อมูลพนักงาน ไม่ได้เปลี่ยนหรือทดสอบการเข้าถึง URL ไฟล์แนบเก่าโดยตรงใน uploads และไม่ใช่การสแกนความปลอดภัยทั้งระบบ

งานจัดกลุ่มเมนูใหม่ รายละเอียดกล่องอนุมัติ และการปรับ layout/accessibility ทั้งระบบยังเป็นรายการจาก UX audit สำหรับรอบถัดไป ไม่อยู่ในขอบเขตเร่งด่วนที่อนุมัติครั้งนี้