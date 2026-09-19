**รายงานตรวจประสิทธิภาพและโค้ดซ้ำ — HR System — 19 กันยายน 2026**

ตรวจที่ `C:\xampp\htdocs\hr_system` บน `master` commit `f17e889` เริ่มต้น worktree สะอาด รอบนี้เป็นการตรวจและทำหลักฐาน ยังไม่ได้ปรับพฤติกรรมระบบ เปลี่ยน schema หรือ commit/push

พบโอกาสปรับปรุงหลัก 14 รายการ และปัญหาฐานทดสอบอีก 1 รายการ จุดเร่งด่วนที่สุดคือ DDL ที่ทำทุก request, DDL ภายใน transaction, การ backfill ระหว่างอ่านข้อมูล และ JavaScript ที่ประกาศชื่อฟังก์ชันชนกัน ข้อค้นพบบางรายการมีสาเหตุร่วมกัน จึงไม่ควรนำผลประหยัดที่คาดหวังมาบวกกัน

**ขอบเขตและระดับหลักฐาน**

สำรวจ PHP ของระบบ 81 ไฟล์ รวม 19 API, JavaScript แยกโมดูล 22 ไฟล์ และ `assets/main.js` อีก 1 ไฟล์ โดยค้นหารูปแบบ SQL, การวนลูป, session, การโหลด asset, query ใน helper และเส้นทางเรียกจากหน้าเว็บ ตรวจเชิงลึกใน attendance/report/import, leave/report/approval, employee/save, bulk warnings และ shared header/footer ส่วน master data, account, dashboard, activity และ holiday calendar ตรวจเส้นทางที่เกี่ยวกับต้นทุนและการใช้ helper ร่วม

ไม่ได้หมายถึงทดสอบทุก workflow ผ่าน browser จริง: ไม่มี authenticated browser run, production load test, network waterfall หรือ execution plan จากฐานข้อมูลจริง การเชื่อมต่อผ่าน configuration ของโครงการล้มเหลวด้วย `HY000/2002` ก่อนเริ่มอ่านข้อมูล จึงไม่มีจำนวนแถวจริง, ดัชนีจริง, SQL timing หรือข้อสรุปว่าเร็วขึ้นกี่เปอร์เซ็นต์ ไม่มีการเชื่อมต่อสำเร็จหรือเขียนฐานข้อมูลระหว่างการตรวจครั้งนี้

หลักฐานมี 3 ระดับ:
- **โค้ด:** ตรวจเส้นทางเรียกและเงื่อนไขจริง รวมตัวอย่างที่ทำดีอยู่แล้ว
- **จำลอง:** รันฟังก์ชันจริงกับ DOM/response/schema สมมติ ไม่มีข้อมูลบุคคล ไม่มีฐานข้อมูล
- **ต้องวัดเพิ่ม:** ประสิทธิภาพ production, index selectivity, browser rendering และ lock จริง

**ตัวเลขที่ตรวจได้**

| สิ่งที่ตรวจ | ผล | ความหมาย/ขอบเขต |
|---|---:|---|
| JavaScript ของโครงการที่ footer โหลดทุกหน้า | 19 ไฟล์, 381,305 bytes ≈ 372.4 KiB | ขนาด source ก่อนบีบอัด ไม่รวม CDN ไม่ใช่ขนาดดาวน์โหลดจริงทุกครั้ง |
| รายงานเวลา 1 เดือน | 24 SQL attempts | helper จริง + schema จำลองที่มีคอลัมน์ครบและข้อมูลรายงานว่าง |
| รายงานเวลา 12 เดือน | 288 SQL attempts | 120 SELECT, 60 CREATE, 12 INSERT backfill, 72 SHOW, 24 ALTER |
| Sidebar badges สำหรับ admin | 17 SQL attempts | 6 SELECT, 3 CREATE, 7 SHOW, 1 ALTER; ยังไม่รวม auth/page-specific SQL |
| เตรียมประเภทคำขอรายชั่วโมงที่มีอยู่แล้ว | 7 SQL attempts | 1 SHOW, 3 SELECT, 3 UPDATE |
| การ์ดยอดลาคาดการณ์เมื่อโหลดเฉพาะโมดูลลา | มีข้อความ “หลังส่ง” | ทดสอบด้วย fixture |
| การ์ดเดียวกันเมื่อโหลดตามลำดับ footer จริง | ไม่มีข้อความ “หลังส่ง” | ถูกฟังก์ชันชื่อซ้ำจากโมดูลประวัติทับ |
| รายงานสอง request ที่ตอบกลับย้อนลำดับ | ข้อมูลเก่าทับข้อมูลใหม่ | ควบคุมลำดับ response ใน Node VM |
| ชุดทดสอบ PHP เดิม | ผ่าน 28/28 ไฟล์ | ไม่เชื่อมต่อฐานข้อมูล |
| ชุดทดสอบ JavaScript เดิม | ผ่าน 33/36 ไฟล์ | รวมผ่าน 61/64 ไฟล์ ไม่ใช่จำนวน test cases |

จำนวน SQL ข้างต้นนับ query/execute ที่โค้ดพยายามส่ง ไม่รวม protocol round trips สำหรับ prepare และไม่ใช่คำสั่งที่ส่งไปฐานข้อมูลจริง ระยะเวลาที่ SQL แต่ละคำสั่งใช้และ lock ต้องตรวจเพิ่มกับ server จริง

**รายการที่ควรแก้ เรียงตามผลกระทบและความเสี่ยง**

**A01 · P1 · ALTER TABLE ทุกครั้ง แม้ schema ถูกต้องแล้ว — ยืนยันจากโค้ดและตัวจำลอง**

ตำแหน่ง: [leave_helpers.php:1196](C:/xampp/htdocs/hr_system/includes/leave_helpers.php:1196), จุดสำคัญ [บรรทัด 1217](C:/xampp/htdocs/hr_system/includes/leave_helpers.php:1217)

`leaveEnsureRequestPartColumns()` เก็บผล SHOW COLUMNS เป็น boolean แล้วถ้า `time_request_type` มีอยู่ จะเข้า `else` เพื่อรัน `ALTER TABLE leave_requests MODIFY ...` โดยไม่มีการตรวจ Type เดิม จึงไม่ได้ทำเฉพาะเมื่อขาด migration

เส้นทางเรียกครอบคลุมการลา/ประวัติ/อนุมัติ/รายงานเวลา และ sidebar ของ manager/HR/admin ผ่าน `leaveEnsureTwoStepApprovalColumns()` ตัวจำลองยืนยันรายงานเดือนเดียวเรียก ALTER 2 ครั้ง และ 12 เดือนเรียก 24 ครั้ง การเปิดเมนูอื่นที่มี sidebar ก็เจอ ALTER 1 ครั้ง

ผล: เพิ่ม DDL/metadata work โดยไม่จำเป็น และอาจต้องรอ metadata lock ไม่ยืนยันว่าทุกครั้ง rebuild ทั้งตารางหรือมี lock นานเท่าใด เพราะขึ้นกับ server/version/สถานการณ์

แนวแก้: แยก schema upgrade ออกเป็น migration ที่มี version และรันตอน deploy; ช่วงเปลี่ยนผ่านตรวจชนิด enum จริงก่อน ALTER และจำผล ensure ภายใน request เฉพาะเมื่อสำเร็จ การ memoize อย่างเดียวลดจำนวนแต่ยังเหลือ DDL ทุก request จึงไม่ใช่คำตอบสุดท้าย

เกณฑ์ตรวจ: schema ปัจจุบันต้องมี ALTER = 0 ทั้งการเปิดหน้าและ read API; schema เก่าทั้งกรณีไม่มีคอลัมน์/enum ไม่มี OT ต้อง upgrade ได้; ทดสอบลารายวัน/รายชั่วโมง/OT และสถานะเดิมครบ

**A02 · P1 · DDL อยู่ใน transaction ของการบันทึก — ประเด็นความถูกต้องที่ต้องแก้พร้อมความเร็ว**

ตำแหน่ง: [saveBulkAttendanceAdjustments:1300](C:/xampp/htdocs/hr_system/api/attendance_api.php:1300), [saveAttendanceOverrideRow:1272](C:/xampp/htdocs/hr_system/api/attendance_api.php:1272), [updateEmployee:385](C:/xampp/htdocs/hr_system/api/employee_api.php:385), [employeeShiftAssignmentsSyncCurrent:154](C:/xampp/htdocs/hr_system/includes/employee_shift_assignment_helpers.php:154), [submitLeaveRequest:79](C:/xampp/htdocs/hr_system/api/leave_request_api.php:79)

การแก้เวลาหลายคนเริ่ม transaction แล้ววนเรียก save ที่ CREATE TABLE ทุกคน การแก้ข้อมูลพนักงาน UPDATE ข้อมูลก่อนเรียก helper กะที่ CREATE TABLE และ backfill ส่วน submit ใบลาเรียก ensure/ALTER หลัง begin_transaction เช่นกัน

MariaDB ระบุว่า CREATE TABLE และ ALTER TABLE ทำ implicit commit จึงมีเส้นทางที่ rollback ภายหลังไม่สามารถคืนค่าทั้งชุดตามที่โค้ดตั้งใจได้ เป็นการอนุมานจากเส้นทางโค้ดกับสัญญาของฐานข้อมูล ยังไม่ได้ทำ failure injection บนฐานข้อมูลจริง [เอกสาร MariaDB](https://mariadb.com/docs/server/reference/sql-statements/transactions/sql-statements-that-cause-an-implicit-commit)

แนวแก้: ย้าย DDL/migration ออกจาก transaction งานธุรกิจทั้งหมด ตรวจ schema readiness ก่อนเริ่มงาน ตรวจ scope ทั้งชุด แล้วเตรียม statement ครั้งเดียว/ใช้ซ้ำใน transaction

เกณฑ์ตรวจ: ทำให้รายการที่ 2 หรือ 3 ล้มเหลวในฐานข้อมูลทดสอบและพิสูจน์ว่ารายการก่อนหน้าไม่ค้าง; ตรวจ `@@in_transaction` ระหว่างทาง; scope และ validation ต้องเหมือนเดิม ไม่ใช้การข้ามรายการที่ผิดแทน rollback โดยพลการ

**A03 · P1 · การอ่านกะหนึ่งคน backfill พนักงานทั้งระบบซ้ำ — ยืนยันจากโค้ด**

ตำแหน่ง: [employee_shift_assignment_helpers.php:3](C:/xampp/htdocs/hr_system/includes/employee_shift_assignment_helpers.php:3), [backfill:23](C:/xampp/htdocs/hr_system/includes/employee_shift_assignment_helpers.php:23), [fetch monthly:79](C:/xampp/htdocs/hr_system/includes/employee_shift_assignment_helpers.php:79)

ทุก `employeeShiftAssignmentsFetchForMonth()` เรียก ensure ที่ CREATE TABLE แล้วรัน INSERT ... SELECT จาก employees ทุกคนที่มีกะแต่ไม่มีประวัติ ไม่มี employee_id จำกัดใน backfill การเปิดรายงานรายบุคคลจึงมีงานระดับทั้งองค์กร แม้ไม่มีแถวใหม่ให้ insert ตัวจำลอง 12 เดือนพบ backfill 12 ครั้ง

แนวแก้: ทำ backfill ครั้งเดียวใน migration และ bootstrap เฉพาะพนักงานใหม่/พนักงานที่ปรับกะใน write path ให้ read path เป็นการอ่าน

เกณฑ์ตรวจ: รายงานไม่ส่ง INSERT/CREATE, ประวัติกะเก่ายังอยู่, พนักงานที่ยังไม่มี assignment ใช้ fallback เดิม และวันเริ่ม/สิ้นสุดกะกับ override ต้องให้ผลเดิม

**A04 · P1 · JavaScript ชื่อซ้ำทับกันจริง — ยืนยันด้วย fixture ตามลำดับ footer**

ตำแหน่ง: [footer.php:23](C:/xampp/htdocs/hr_system/includes/footer.php:23), [leave_request.js:194](C:/xampp/htdocs/hr_system/assets/js/leave_request.js:194), [my_leaves.js:163](C:/xampp/htdocs/hr_system/assets/js/my_leaves.js:163), [utils.js:15](C:/xampp/htdocs/hr_system/assets/js/utils.js:15), [company_holidays.js:133](C:/xampp/htdocs/hr_system/assets/js/company_holidays.js:133)

พบชื่อ global function ซ้ำ 14 ชื่อในไฟล์โมดูล ไม่ได้นับฟังก์ชันใน IIFE เป็น global ตัวอย่าง `renderTypeLeaveUsageCard` ของหน้าลารองรับ projected days แต่ถูกของหน้าประวัติทับภายหลัง จึงหายทั้งข้อความคาดการณ์และการเลือกค่าที่เกี่ยวกับ projected usage ใน fixture อีกตัวอย่าง `escapeHtml(null)` เปลี่ยนจากข้อความว่างเป็น “null” เมื่อโหลดรวม

แนวแก้: จำกัดขอบเขตฟังก์ชันของหน้าให้ชัดเจนด้วย module/IIFE/namespace และย้ายเฉพาะส่วนที่สัญญาเหมือนกันจริงไป helper กลาง ตัว renderer ของยอดปัจจุบันและยอดคาดการณ์ไม่ควรถูกรวมโดยทิ้งความต่าง ทดสอบ dependency ก่อนตัดไฟล์ออกจาก footer

เกณฑ์ตรวจ: ทดสอบ script ร่วมกันตามหน้าจริง ไม่ใช่แยกไฟล์เท่านั้น; ตรวจสิทธิ์ลาและยอดก่อน/หลังส่ง, ค่า null, วันที่ไทย, badge สถานะ และข้อมูลผู้สร้างแทน การแก้บั๊กคาดการณ์นี้ต้องแยกจากการอ้างว่าเป็น refactor ที่ไม่มีผลหน้าจอ

**A05 · P1 · Bulk warning โหลด context ใหม่ทีละเหตุการณ์ — ยืนยันจากเส้นทางโค้ด**

ตำแหน่ง: [employee_warning_bulk_helpers.php:288](C:/xampp/htdocs/hr_system/includes/employee_warning_bulk_helpers.php:288), [attendance_warning_source_helpers.php:136](C:/xampp/htdocs/hr_system/includes/attendance_warning_source_helpers.php:136)

`employeeWarningResolveBulkEvents()` วนทุก item แล้วอ่าน employee/scope, ประวัติกะ, override, swap, scanner, วันหยุด, วันลา และ activity ใหม่ทุกครั้ง รวมถึง backfill ตาม A03 เหตุการณ์มาสายเพิ่มการอ่านนาทีอนุมัติอีกครั้ง ระบบอนุญาตสูงสุด 500 items แม้เหตุการณ์หลายรายการเป็นพนักงานและเดือนเดียวกันก็ยังโหลดใหม่

ผล: จำนวน SQL เติบโตตามจำนวนเหตุการณ์ ไม่ใช่จำนวนพนักงาน/เดือน ส่วนการดึง source keys ที่เคยออกใบเตือนแล้วทำเป็นชุดอยู่แล้ว และ prepare insert นอกลูปดีอยู่แล้ว คอขวดหลักอยู่ตอน resolve context

แนวแก้: จัดกลุ่ม employee/month แล้วโหลด context เป็นชุดภายใน request จากนั้นใช้ evaluator เดิมตรวจเหตุการณ์แต่ละรายการ หลีกเลี่ยง cache ข้าม request ที่อาจทำให้สถานะต้นทางเก่า

เกณฑ์ตรวจ: 1/50/500 items, หลายเหตุการณ์คนเดียว, ต่างเดือน, เหตุการณ์ถูกยกเลิก, HR ต่าง scope, source ซ้ำ และ unique key ต้องให้ผลเดิม ห้ามเชื่อข้อมูลจาก browser แทนการตรวจต้นทางบน server

**A06 · P2 · รายงานลา N+1 ตามจำนวนพนักงาน และ copy array สะสม — ยืนยันจากโค้ด**

ตำแหน่ง: [leave_api.php:289](C:/xampp/htdocs/hr_system/api/leave_api.php:289), [ลูป workDaysByEmployee:337](C:/xampp/htdocs/hr_system/api/leave_api.php:337), [leaveFetchEmployeeWorkDays:1171](C:/xampp/htdocs/hr_system/includes/leave_helpers.php:1171)

รายงานโหลดคำขอลาครั้งเดียว แต่ทุก employee_id ที่ยังไม่อยู่ใน cache จะ query วันทำงานอีกหนึ่งครั้ง ถ้ามีพนักงาน E คนมีต้นทุนส่วนนี้เพิ่ม E queries; cache ปัจจุบันช่วยได้เมื่อคนเดิมมีหลายใบลา จึงไม่ใช่ query ทุกแถวใบลา

ในลูปเดียวกันใช้ `array_merge($rows, expandedRows)` ซ้ำ ทำให้ต้อง copy ผลสะสมมากขึ้นเมื่อรายงานใหญ่

แนวแก้: LEFT JOIN work_shifts เพื่อเอา work_days ใน query หลัก หรือ bulk lookup หนึ่งครั้ง แล้ว append แถวทีละรายการ การคำนวณต้องใช้กะ/วันทำงานตามสัญญาเดิม ไม่เปลี่ยนเป็นกฎกะย้อนหลังใหม่ระหว่าง optimize

เกณฑ์ตรวจ: approved-only, actual-leave-only, หนึ่งแถวต่อวัน, ครึ่งวัน, รายชั่วโมง, คร่อมเดือน, วันหยุด, ผล sort และ HR scope ตรงเดิม; query สำหรับ work_days ไม่โตตาม E

**A07 · P2 · รายงานหลายเดือนทำ query ชุดเดิมซ้ำทุกเดือน — ยืนยันด้วยตัวจำลอง**

ตำแหน่ง: [attendance_api.php:709](C:/xampp/htdocs/hr_system/api/attendance_api.php:709), [buildMonthlyAttendanceReport:458](C:/xampp/htdocs/hr_system/api/attendance_api.php:458)

`buildAttendanceReportRange()` วนเดือนแล้วเรียก monthly builder เต็มชุด จำนวน 24 → 288 SQL attempts จาก 1 → 12 เดือนใน fixture รวม schema work ตาม A01/A03 แม้เอา DDL ออก ยังอ่านหลายกลุ่มข้อมูลซ้ำและประกอบ array สะสม

แนวแก้: fetch ตามช่วง start/end ครั้งเดียวต่อชนิดข้อมูล แล้วแยก map ตามเดือน/วัน ใช้ฟังก์ชันคำนวณสถานะเดิมร่วมกัน เก็บ fallback, กะข้ามคืน, override, day swap, pending_cancel_hr และ partial leave ตามเงื่อนไขเดิม

เกณฑ์ตรวจ: เปรียบเทียบ response ทุก field ของวิธีเดิมกับใหม่สำหรับ 1/3/12 เดือน และขอบเดือน/ปี ยังคงขีดจำกัด 12 เดือนเดิม เป้าหมาย query count ควรขึ้นกับชนิดข้อมูลมากกว่าจำนวนเดือน

**A08 · P2 · ถือ session lock จนจบรายงาน/import — ยืนยันโค้ด ผลกระทบจริงต้องวัด**

ตำแหน่ง: [attendance_api.php:16](C:/xampp/htdocs/hr_system/api/attendance_api.php:16), [leave_request_api.php:12](C:/xampp/htdocs/hr_system/api/leave_request_api.php:12), [auth_check.php:9](C:/xampp/htdocs/hr_system/includes/auth_check.php:9)

พบ session_start ในทุก API แต่ไม่พบ session_write_close ใน runtime source ที่สำรวจ ตัว PHP CLI ที่ตรวจใช้ session handler “files”; ยังไม่ได้ยืนยันค่าของ Apache/production ตามเอกสาร PHP request ที่ใช้ session เดียวกันอาจต้องรอ lock จึงทำให้เปิดอีกแท็บหรือเรียกหลาย API แล้วรอกันได้ ไม่ใช่ lock ผู้ใช้ทุกคนรวมกัน [เอกสาร PHP](https://www.php.net/manual/en/function.session-write-close.php)

แนวแก้: หลังตรวจ login/scope และทำการเขียน session ที่จำเป็นเสร็จ ให้ release ก่อนงานอ่าน/ประมวลผลยาว ๆ แยก login, logout, refresh scope, change password และ profile update ที่ยังต้องเขียน session ห้ามเติม write_close แบบเหวี่ยงทั้งระบบ

เกณฑ์ตรวจ: ทดสอบสอง request ใน session เดียวกันและคนละ session; ตรวจ role/scope refresh และ logout; วัดเวลารอเริ่มงาน แยกจากเวลา SQL

**A09 · P2 · โหลด JS และ library ไม่เกี่ยวกับหน้าปัจจุบันทุกหน้า — ยืนยันขนาด source**

ตำแหน่ง: [footer.php:8](C:/xampp/htdocs/hr_system/includes/footer.php:8), [index.php:414](C:/xampp/htdocs/hr_system/index.php:414)

footer โหลด JS ของโครงการ 19 ไฟล์ 381,305 bytes รวม attendance ≈81 KB, employee ≈34 KB, leave request ≈34 KB แม้เปิดหน้าที่ไม่ใช้ Library Chart.js และ DataTables ก็โหลดทุกหน้า ส่วน Select2/FullCalendar มี conditional loading แล้ว

หน้า login ใช้ assets/main.js 29,345 bytes ซึ่งยังรวม logic master data และ employee เก่า จึงไม่ใช่ไฟล์ที่ลบได้ทันทีเพราะยังมี login handler ใช้งานอยู่

แนวแก้: common assets + mapping asset ต่อหน้า ใช้ pattern flag ที่มีอยู่แล้ว; แยก login script; ต้องแก้/ตรวจ A04 และ dependency แฝงก่อน เช่น renderer บางหน้ามี helper ที่ปัจจุบันอาศัยอีกไฟล์

เกณฑ์ตรวจ: ทุกหน้าที่ใช้งานไม่มี ReferenceError, login/logout ทำงาน, pagination/modal/date input/approval ยังครบ; เก็บ browser transfer size, parse time และ cache hit ก่อน/หลัง ไม่อ้างว่า 372.4 KiB จะถูกส่งใหม่ทุก navigation

**A10 · P2 · รายการหลายหน้าดึงทั้งหมดก่อนให้ DataTables แบ่งหน้า — ยืนยันโค้ด ต้องวัดขนาดข้อมูล**

ตำแหน่ง: [employee_api.php:598](C:/xampp/htdocs/hr_system/api/employee_api.php:598), [employee.js:100](C:/xampp/htdocs/hr_system/assets/js/employee.js:100), [leave_approval_api.php:38](C:/xampp/htdocs/hr_system/api/leave_approval_api.php:38), [leave_history_api.php:26](C:/xampp/htdocs/hr_system/api/leave_history_api.php:26)

รายการพนักงาน query ไม่มี LIMIT; API ประวัติและอนุมัติหลายรายการก็ส่งทุกแถวที่เข้าเงื่อนไข แล้ว JS สร้าง tbody ทั้งชุดก่อน initialize DataTables การเห็น 10 แถวต่อหน้าจึงไม่เท่ากับโหลดจาก server เพียง 10 แถว เมื่อประวัติสะสม JSON, DOM, sort และ memory จะโต

แนวแก้: เริ่มจากหน้าที่วัดว่ามีข้อมูลมาก ใช้ server-side search/sort/page และ count ที่สอดคล้องกัน ลด selected columns เฉพาะที่ไม่ใช้จริง รักษาหน้าตา/สิทธิ์/การค้นหาเดิม ไม่เพิ่ม LIMIT แล้วทำให้ผู้ใช้ค้นหาได้เฉพาะหน้าแรก

เกณฑ์ตรวจ: ค้นหาข้ามหน้า, sort ภาษาไทย, recordsTotal/Filtered, การเลือกหลายแถวข้ามหน้า, ปุ่มรายละเอียด/ยกเลิก และ HR scope ต้องครบ รายงานที่ export ทั้งชุดต้องมีเส้นทาง export ครบชุด

**A11 · P2 · กดโหลดรายงานซ้ำได้ และ response เก่าทับใหม่ — ยืนยันด้วย response จำลอง**

ตำแหน่ง: [attendance.js:492](C:/xampp/htdocs/hr_system/assets/js/attendance.js:492), [attendance.js:680](C:/xampp/htdocs/hr_system/assets/js/attendance.js:680), [leave_report.js:82](C:/xampp/htdocs/hr_system/assets/js/leave_report.js:82)

หน้ารายงาน missing/late-early/approved-leave ไม่มี in-flight guard หรือเลขลำดับ request ก่อนรับผล ทุกครั้งที่กดจะ fetch ใหม่และรับผลโดยไม่มีเงื่อนไข การจำลอง missing report เรียกเดือนเก่าแล้วเดือนใหม่ ให้ผลใหม่กลับก่อน จากนั้นผลเก่ากลับท้าย พบว่าตารางจบที่ผลเก่า

หน้า attendance รายบุคคลมี disable ปุ่มระหว่างโหลดอยู่แล้ว เป็น pattern ที่ใช้ต่อได้ ไม่ควรเหมารวมว่าไม่มีทุกหน้า

แนวแก้: ป้องกันโหลด request เดิมซ้ำ, ใช้ request sequence/token เพื่อรับเฉพาะผลล่าสุด, ใช้ AbortController สำหรับยกเลิกการรอเมื่อเหมาะสม แต่ abort ฝั่ง browser ไม่รับประกันว่า SQL บน server หยุดแล้ว

เกณฑ์ตรวจ: กดซ้ำ/เปลี่ยน filterเร็ว/responseย้อนลำดับ/error ของ requestเก่า; selection ใบเตือนต้องอิงชุดผลล่าสุด การ retry ต้องทำได้หลังผิดพลาด

**A12 · P2 · การสร้างแผนที่สลับวันวนข้อมูลทั้งหมดซ้ำต่อพนักงาน — ยืนยันโค้ดและ microbenchmark**

ตำแหน่ง: [attendance_api.php:1063](C:/xampp/htdocs/hr_system/api/attendance_api.php:1063), [attendance_helpers.php:304](C:/xampp/htdocs/hr_system/includes/attendance_helpers.php:304)

SQL ของรายงานรวมโหลด swap เป็นชุดแล้ว แต่ต่อจากนั้นวน employeeIds แล้วส่ง swap rows ทั้งหมดให้ helper ที่วน rows ซ้ำ จึงมีงาน E × S โดย E คือพนักงาน S คือ swap rows ในชุดนั้น

| Fixture | จำนวนการตรวจแถว | Median 5 รอบบน PHP CLI 8.2.12 |
|---|---:|---:|
| 100 คน / 200 swaps | 20,000 | 2.112 ms |
| 500 คน / 1,000 swaps | 500,000 | 52.256 ms |
| 1,000 คน / 2,000 swaps | 2,000,000 | 213.750 ms |

เป็นเวลาของ helper บนข้อมูลจำลองเท่านั้น ไม่ใช่เวลารายงานทั้งหน้าและไม่ใช่การวัดความเร็วหลังแก้

แนวแก้: group rows ตาม requester/target หนึ่งรอบ แล้วส่งเฉพาะ rows ที่เกี่ยวข้องให้ helperเดิม หรือ build map ทุก employee ใน traversal เดียว

เกณฑ์ตรวจ: requester/target, คนเดียวกันทั้งสองช่อง, วันซ้ำ, สลับข้ามเดือน, ลำดับที่รายการภายหลังทับวันก่อน และนโยบาย conflict ต้องเหมือนเดิม

**A13 · P2 · GET ประเภทการลาสั่ง UPDATE ค่าเริ่มต้นซ้ำ — ยืนยันตัวจำลอง**

ตำแหน่ง: [leave_helpers.php:50](C:/xampp/htdocs/hr_system/includes/leave_helpers.php:50), [leave_request_api.php:27](C:/xampp/htdocs/hr_system/api/leave_request_api.php:27), [proxy_request_api.php:29](C:/xampp/htdocs/hr_system/api/proxy_request_api.php:29)

`leaveEnsureHourlyRequestTypes()` อ่านประเภทคำขอสามรายการ แล้วเมื่อพบรายการเดิมกลับสั่ง UPDATE calculation_unit/hours_per_day/threshold ทุกครั้ง ไม่ได้เช็คว่าค่าเปลี่ยนหรือไม่ ตัวจำลองยืนยัน 3 SELECT + 3 UPDATE + 1 SHOW แม้ defaults มีครบ ข้อมูลบางเส้นทางยัง ensure คอลัมน์ซ้ำต่อท้ายด้วย

แนวแก้: แยก seed/repair เป็น migration และกำหนดให้ชัดว่าประเภทระบบแก้ค่าใดได้ สำหรับช่วงเปลี่ยนผ่านใช้ update เฉพาะค่าที่ต่าง แต่ยังไม่ควรให้ read API มีงานเขียนระยะยาว

เกณฑ์ตรวจ: เปิดฟอร์มไม่มี UPDATE; ค่า default และการแยก actual_leave/คำขอเวลาเหมือนเดิม; ตรวจ install ใหม่และข้อมูลเก่า โดยไม่เปลี่ยนโควตาผู้ใช้

**A14 · P2 เมื่อไฟล์ใหญ่ · CSV เก็บข้อมูลทั้งไฟล์หลายชั้นก่อนเริ่มเขียน — ยืนยันรูปแบบ memory ไม่ได้วัดไฟล์จริง**

ตำแหน่ง: [attendance_helpers.php:835](C:/xampp/htdocs/hr_system/includes/attendance_helpers.php:835), [attendance_api.php:275](C:/xampp/htdocs/hr_system/api/attendance_api.php:275)

อ่าน CSV ทั้งหมดเป็น rows, แปลงเป็น candidates, โหลด existingMap/pendingMap และสะสม writeRows ก่อน array_chunk ตอนเขียนจริง การแบ่ง SQL ครั้งละ 250 rows มีอยู่แล้วและดี แต่ไม่ได้จำกัด peak memory ฝั่ง parser/preparation ให้อยู่ที่ 250 rows; array_copy ของ PHP อาจแชร์ memory จนเริ่มแก้ ไม่ควรนับเป็นสำเนาเต็มทุกตัวทันที

แนวแก้เมื่อวัดว่าไฟล์ใหญ่มีปัญหา: streaming parse + bounded chunks/staging และลดช่วง existing-record lookup ให้ตรงชุด ต้องออกแบบความเป็น transaction และการนับ duplicate ข้าม chunk ให้เหมือนเดิม

เกณฑ์ตรวจ: ไฟล์เล็ก/ใหญ่, แถวไม่พบพนักงาน, วันที่ผิด, แถวซ้ำในไฟล์, นำเข้าซ้ำ, เติมเฉพาะ check-in/out ที่ขาด, จำนวน inserted/updated/skipped/unmatched และ rollback เหมือนเดิม

**A15 · P2 ก่อนเริ่ม refactor · ฐานทดสอบเดิมยังไม่เขียว และขาดหลักฐานข้ามชั้น**

ผลรัน ณ source เดิม:
- `tests/attendance_import_detail_ui_test.js`: `ReferenceError: window is not defined` ที่ utils.js:334 — test มี document mock แต่ไม่มี window
- `tests/holiday_calendar_ui_test.js`: `window.addEventListener is not a function` — มี window = {} แต่ไม่มี event API
- `tests/holiday_calendar_menu_test.js`: คาดว่า “ปฏิทินวันหยุด” แต่ header ปัจจุบันเป็น “ปฏิทินงานและวันหยุด”

สองข้อแรกเป็นข้อจำกัด test harness ไม่ใช่หลักฐานว่า browser จริง error แบบเดียวกัน ส่วนเมนูเป็น stale expectation ไม่ควรเปลี่ยนข้อความปัจจุบันเพียงเพื่อให้ test ผ่าน หลายไฟล์เป็น source-contract หรือ helper tests จึงไม่ยืนยัน SQL จริง, rollback หรือ script-load order

แนวแก้: ซ่อม baseline ให้สะท้อนสัญญาปัจจุบัน เพิ่ม tests ของ schema no-op, transaction rollback บน DBทดสอบ, script ตามหน้าจริง, response equality และ request count ห้ามลบ test สำคัญเพียงเพราะ refactor แล้ว source string เปลี่ยน

**โค้ดซ้ำและจุดรองที่ควรจัดระเบียบหลังงานหลัก**

| จุด | สิ่งที่ควรทำ | ข้อจำกัด |
|---|---|---|
| badge leave/time/OT 3 count queries | conditional aggregation ภายใต้ scope/stageเดียวกัน | คง request_unit/time_request_type และนิยาม badge เดิม |
| holiday lookups ใน attendance/day swap/holiday calendar | ส่ง context ที่โหลดแล้วเข้าฟังก์ชัน | อย่ารวมส่วนคำนวณที่ใช้ประวัติกะต่างกันจนพฤติกรรมเปลี่ยน |
| monthly/timeline/missing/late-early builders | แยก data loading/context ออกจาก evaluator | approved-only ของรายงานคำขอ กับ approved+pending_cancel_hr ของ attendance มีความต่างที่ตั้งใจไว้ |
| formatLeaveDuration, formatLeaveDateRange, formatLeaveDayNumber, renderProxyCreatorLine ฯลฯ | helperกลางสำหรับสัญญาที่เหมือนกัน; adapterเมื่อแตกต่าง | อย่ารวมเพียงเพราะชื่อเหมือน |
| fetchAttendanceAdjustmentFilterOptions / fetchApprovedLeaveReportFilters | พิจารณา DISTINCT ของชุดคอลัมน์ option แล้ว dedupe | รักษา scope และตัวเลือกที่มาจาก active/probation; วัดก่อน |
| getAllEmployees ช่วงหลัง return ของ HR | ลบ HR branches ที่ไปไม่ถึง | ลดความสับสนเป็นหลัก ไม่คาดหวังความเร็วมาก |
| renderAttendanceImportSummaryLegacy | ตรวจ references เพิ่มก่อนลบ | พบ declaration แต่ไม่พบการเรียกใน runtime source ที่ค้น |
| assets/main.js | แยก login ที่ใช้อยู่จาก module เก่า | มี index.php เรียกจริง ไม่ใช่ dead file |

**เรื่อง SQL/index ที่ต้องตรวจเพิ่ม ไม่ใช่ข้อสรุปว่าดัชนีหาย**

[attendance_api.php:452](C:/xampp/htdocs/hr_system/api/attendance_api.php:452) ใช้ `DATE_FORMAT(aro.work_date, '%Y-%m') = ?` ทั้งที่สามารถส่งช่วงวันที่ได้ ควรเปรียบเทียบ EXPLAIN กับ `employee_id = ? AND work_date >= ? AND work_date < ?` ในฐานข้อมูลจริง ไม่สรุปว่า query เดิม full table scan เพราะยังอาจใช้ employee_id prefix ได้

ไฟล์ schema มีดัชนี attendance employee/date และ employee/month อยู่แล้ว จึงไม่ควรเสนอเพิ่มซ้ำโดยไม่ดู SHOW INDEX ของฐานข้อมูลจริง Queries ของ leave/status/date, approval scope และ OR ของ day swaps ควรเลือก index จาก EXPLAIN และ distribution ก่อนเพิ่ม เพราะ index เพิ่มต้นทุนการเขียน/import ด้วย

ยังไม่ได้วัด OPcache ของ Apache, compression/cache headers, CDN latency, SQL slow log, จำนวนผู้ใช้พร้อมกัน หรือ query cardinality ไม่ควรเริ่มด้วยเปลี่ยน server configuration โดยไม่มี baseline

**สิ่งที่ทำดีอยู่แล้วและควรรักษา**

- รายงาน missing/late-early โหลด records, overrides, assignments, leave, training และ swap เป็นชุดก่อนวนคน/วัน ไม่ได้เรียก monthly report ทีละพนักงาน
- CSV import มี employee map, existing-record map และ upsert ครั้งละ 250 rows แล้ว ไม่ควรเสนอแก้จาก insert ทีละแถวทั้งที่ไม่ได้เป็นแบบนั้น
- รายงานรวมรอผู้ใช้กดแสดงข้อมูลตามสัญญาปัจจุบัน; ไม่ควรเพิ่ม auto load ขนาดใหญ่กลับเข้าไป
- Filter/สิทธิ์ HR ส่วนใหญ่มี helperกลางและ prepared bindings ใช้ต่อให้สม่ำเสมอ ไม่ลด permission checks เพื่อความเร็ว
- ช่วงรายงานเวลาไม่เกิน 12 เดือน; ปฏิทินใช้ช่วงเดือน; leave usage จำกัด fiscal year แล้ว ไม่ใช่ทุก query อ่านประวัติไม่จำกัด
- การยืนยันต้นทางใบเตือน, unique source และ cancellation history ต้องคงไว้ แม้จัดการ query ใหม่

**ลำดับปรับปรุงที่เสนอ**

| ชุดงาน | เนื้อหา | หลักฐานก่อนรับงาน |
|---|---|---|
| 1 | ซ่อม test baseline, แยก schema/seed/backfill ออกจาก runtime, แก้ transaction boundary (A01–03/A13/A15) | read requests ไม่มี DDL/backfill; failure injection rollback ทั้งชุด; schema upgrade ผ่าน |
| 2 | แก้ global helper collision แล้วโหลด asset เฉพาะหน้า (A04/A09) | หน้าเดิมครบทุก role, ไม่มี ReferenceError, projected usage ถูกต้อง, JS bytes/parse timeลด |
| 3 | รวม query รายงานลา/ช่วงเดือน/bulk warnings และ swap grouping (A05–07/A12) | response เทียบ fieldต่อfieldตรงเดิม, จำนวน SQL ไม่โตตามแถวที่ไม่จำเป็น |
| 4 | release session สำหรับ read flows และกัน fetchซ้ำ (A08/A11) | concurrent requests ใน sessionเดียวกันไม่รอเกินจำเป็น, ผลเก่าไม่ทับใหม่ |
| 5 | pagination/index/CSV streaming ตามปริมาณจริง (A10/A14/ข้อรอง) | browser+DB benchmark, HR scope, search/export/selection และ import countersเดิม |

ควรแยกแต่ละชุดเป็น patch ที่ตรวจย้อนกลับได้ ไม่รวมการปรับสูตรวันลา เปลี่ยนสถานะอนุมัติ หรือปรับหน้าตามาก ๆ เข้าไปกับงาน performance การเอา duplicate/helper/migration ออกมีผลข้ามหน้า จึงต้องเทียบผลเดิมก่อนขยายงาน

**วิธีพิสูจน์ว่า “เร็วขึ้นและฟังก์ชันเหมือนเดิม”**

ใช้ฐานข้อมูลทดสอบที่ทำข้อมูลนิรนามและมีปริมาณใกล้เคียงจริง เก็บ p50/p95 ของ API, เวลา SQL รวม/จำนวน SQL, peak memory, response bytes และเวลา render ไม่ต้อง log payload, ชื่อ, เลขบัตร, session/token, เอกสาร หรือ credentials

กรณีเปรียบเทียบต้องครอบคลุม employee/manager/HR/admin, HR ไม่มี scope/หลายscope, ครึ่งวัน/ชั่วโมง/OT, pending_cancel_hr, กะย้อนหลัง/override/ข้ามคืน, วันหยุด/สลับวัน/กิจกรรม, ลาคร่อมเดือน/ปีงบประมาณ, ใบเตือนซ้ำและถูกยกเลิก และ import ซ้ำ ก่อนรับงานต้องเปรียบเทียบผลลัพธ์ทุก field ที่ผู้ใช้/APIพึ่งพา ไม่ดูเฉพาะจำนวนแถว

เลือกเป้าหมายเชิงโครงสร้างที่ตรวจได้ก่อน เช่น read-only endpointไม่มี DDL/DML, query work_days ไม่เพิ่มตามพนักงาน, fetchต่อการกดหนึ่งครั้งไม่ซ้ำ และ responseล่าสุดเท่านั้นที่แสดง เปอร์เซ็นต์ความเร็วต้องกำหนดหลังวัด baseline จริง

**ไฟล์หลักฐานและคำสั่ง**

- [frontend-probes.js](C:/xampp/htdocs/hr_system/docs/audits/2026-09-19/frontend-probes.js): โหลด JS ตาม footer ใน Node VM และจำลอง responseย้อนลำดับ
- [query-count-probe.php](C:/xampp/htdocs/hr_system/docs/audits/2026-09-19/query-count-probe.php): เรียก PHP helper จริงกับ mysqli จำลอง ต้องรันด้วย `-n`; ไม่มี DB connection
- [algorithm-probe.php](C:/xampp/htdocs/hr_system/docs/audits/2026-09-19/algorithm-probe.php): microbenchmark ของ helperสลับวันด้วยข้อมูลสังเคราะห์
- [verification.json](C:/xampp/htdocs/hr_system/docs/audits/2026-09-19/verification.json): สรุปผลตรวจและข้อจำกัด

รันจาก root repository:

```powershell
node docs\audits\2026-09-19\frontend-probes.js
C:\xampp\php\php.exe -n docs\audits\2026-09-19\query-count-probe.php
C:\xampp\php\php.exe docs\audits\2026-09-19\algorithm-probe.php

Get-ChildItem tests\*_test.php | ForEach-Object {
    & C:\xampp\php\php.exe $_.FullName
}
Get-ChildItem tests\*_test.js | ForEach-Object {
    & node $_.FullName
}
git diff --check
```

ตรวจ syntax ด้วย `php -l`: source 81 + probe 2 = 83 ไฟล์ผ่าน; `node --check`: module22 + main1 + probe1 = 24 ไฟล์ผ่าน ชุดทดสอบเดิมมีผลตามที่รายงานข้างต้น ไม่ได้แก้ tests ในรอบตรวจนี้ สคริปต์ probe แสดงพฤติกรรม/ต้นทุนที่พบ ไม่ใช่ผลรับรอง production หรือ regression suite หลังแก้

ไฟล์ใหม่ทั้งหมดอยู่ใน `docs/audits/2026-09-19`; source ที่ระบบใช้งานจริงยังไม่มี diff
