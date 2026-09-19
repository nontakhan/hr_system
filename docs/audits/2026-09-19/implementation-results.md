# ผลการปรับปรุงประสิทธิภาพ HR — 19 กันยายน 2026

ปรับโค้ดตามข้อ A01–A15 ใน performance-review.md แล้ว บน branch `codex/performance-review-20260919` จากฐาน `f17e889` โดยรักษากฎคำนวณ สิทธิ์ HR การอนุมัติและประวัติเดิม งานอยู่ใน working tree ยังไม่มี commit, push หรือ deployment

## สิ่งที่แก้

| ข้อ | การเปลี่ยนแปลง | ไฟล์หลัก |
|---|---|---|
| A01–A03, A13 | ย้าย DDL, seed และ backfill ทั้งระบบออกจาก request ปกติ; เตรียม schema ผ่าน CLI ที่มี lock และ version marker; แก้ enum check; ป้องกัน implicit commit ระหว่างบันทึกแบบชุด | includes/schema_helpers.php, scripts/prepare_schema.php, includes/*_helpers.php, includes/db_connect.php |
| A04 | รวม display helpers ที่มีสัญญาเดียวกัน แยกชื่อ renderer ที่แสดงยอดคนละแบบ และใช้ escapeHtml กลาง | assets/js/request_display.js, utils.js, my_leaves.js, leave_approval.js |
| A05 | ใช้ employee/month context ร่วมกันภายใน bulk warning หนึ่งคำสั่ง ไม่แชร์ข้ามผู้ใช้หรือการทำงาน | includes/attendance_warning_source_helpers.php, employee_warning_bulk_helpers.php |
| A06 | join work_days ในรายงานลา ลด N+1 และ append แถวแทน copy array สะสม | api/leave_api.php |
| A07 | โหลด 9 ชุดข้อมูลครั้งเดียวสำหรับช่วง 1–12 เดือน แล้วใช้ evaluator เดิม; รายงาน timeline ใช้ work_days/holidays ที่อ่านไว้แล้ว | api/attendance_api.php |
| A08 | เขียน session ที่จำเป็นให้เสร็จแล้วปล่อย lock ก่อนงาน DB/รายงาน/import; ไม่เปิด lock กลับใน header/proxy authorization | includes/session_helpers.php, auth_check.php, header.php, API entrypoints |
| A09 | โหลด module/DataTables/Chart ตามหน้า; แยก login script และลบ renderer เก่าที่ไม่มีผู้เรียก | includes/page_assets.php, footer.php, header.php, assets/js/login.js |
| A10 | server pagination แบบ opt-in พร้อม scoped totals/search/order สำหรับพนักงานและรายการอนุมัติ/ประวัติ; legacy response ยังใช้ได้ | includes/list_helpers.php, assets/js/paged_tables.js และ API/JS ของแต่ละรายการ |
| A11 | กันส่งคำขอรายงานพารามิเตอร์เดิมซ้ำ ยกเลิกคำขอเก่า และไม่ให้ response เก่าเขียนทับสถานะใหม่ | assets/js/utils.js, attendance.js, leave_report.js |
| A12 | จัดกลุ่มรายการสลับวันตามพนักงานครั้งเดียวก่อนประเมิน | includes/attendance_helpers.php |
| A14 | อ่าน CSV แบบ generator ครั้งละไม่เกิน 250 candidates, lookup เฉพาะ employee/date ที่ใช้ และรักษา transaction ครอบทั้งไฟล์ | includes/attendance_helpers.php, api/attendance_api.php |
| A15 | ซ่อม browser mocks/ข้อความคาดหวังเดิม เพิ่ม query budget, scope, rollback, race, pagination และ MariaDB integration tests | tests/, scripts/run-tests.cjs |

ข้อรองที่ทำร่วมกัน: badge ใช้ conditional aggregation, filter options ใช้ DISTINCT, query attendance overrides ใช้ช่วงวันที่, bulk adjustment เตรียม statement ครั้งเดียว, รวมการเริ่ม/โหลด DataTables และลบ initializer เก่าที่ไม่มีผู้เรียก ไม่เพิ่ม index ซ้ำโดยไม่มี EXPLAIN ของฐานจริง

## ผลที่วัดได้

| ตัววัด | ก่อน | หลัง |
|---|---:|---:|
| SQL attempts ใน helper รายงาน 1 เดือน | 24 (10 SELECT) | 9 SELECT |
| SQL attempts ใน helper รายงาน 12 เดือน | 288 (120 SELECT) | 9 SELECT |
| SQL attempts ใน helper sidebar badges | 17 (6 SELECT) | 3 SELECT |
| JS ภายในโปรเจกต์ที่หน้า dashboard โหลด ไม่รวม vendor | 381,305 bytes / 19 modules | 27,792 bytes / 2 modules |
| Parser CSV สังเคราะห์ 100,000 แถว: peak memory | 138 MiB | 2 MiB |
| Swap mapping 1,000 พนักงาน / 2,000 รายการ: median 5 รอบ | 159.724 ms | 1.080 ms |

SQL counts มาจากการเรียก helper จริงกับ mysqli จำลอง ไม่ใช่เวลา SQL จริง และไม่รวม schema-version SELECT หนึ่งครั้งต่อ connection, authentication หรือ query อื่นของ HTTP request. CSV วัด parser ใน process แยก ไม่ใช่ peak memory ของ import ทั้งระบบ; checksum ของข้อมูลและ swap maps ตรงกัน จึงไม่ใช้ตัวเลขเหล่านี้อ้างเปอร์เซ็นต์ความเร็ว production

## การยืนยันพฤติกรรม

- Regression ผ่าน **76/76 ไฟล์**, ไม่มี skip เมื่อใช้ MariaDB fixture ที่แยกบน loopback
- Syntax ผ่าน PHP **134 ไฟล์** และ JavaScript/CJS **67 ไฟล์** รวม tests/probes
- Schema preparation รันซ้ำได้: SCHEMA_READY → SCHEMA_ALREADY_READY และไม่เพิ่ม shift backfill ซ้ำ
- ทดสอบ failure injection: bulk adjustment คนที่สองล้มเหลว ย้อนคนแรกได้; CSV ชุดที่สองล้มเหลว ย้อน 250 แถวแรกได้
- CSV ซ้ำข้ามชุด: 250 inserted / 1 updated / 1 skipped; นำเข้าซ้ำ 252 skipped และไม่ทับ scan เดิม
- รายงานรายเดือนและช่วงเดือนเท่ากันทุก field; ตรวจครึ่งวัน วันหยุด pending_cancel_hr กะข้ามคืน กะย้อนหลัง และ override เฉพาะวัน
- Timeline ยังคง approved-only; attendance ยังคงนับ pending_cancel_hr ตามกติกาเดิม
- Paged/legacy APIs, HR scope, ค้นหาหลายคำ, วันที่ พ.ศ., เรียงสถานะ และ employee ที่ไม่มีสิทธิ์อนุมัติผ่าน
- ทดสอบ session release แล้ว context/ข้อมูล session ยังคงอยู่ และ authorization ไม่เปิด lock กลับ
- ตรวจ projected balance, null escaping และ response ที่กลับย้อนลำดับด้วย regression tests

ตรวจเบราว์เซอร์จริงผ่าน Playwright CLI กับ PHP server/ฐานข้อมูลสังเคราะห์และ admin session จำลอง: พนักงาน 42 ราย แบ่งหน้า 10 รายและเลื่อนไป 11–20 ได้; ค้นหา “สมชาย Officer” เหลือ 1 ราย; ประวัติลา 35 รายค้น “02/01/2569” เหลือ 1 ราย; ประวัติกิจกรรมเรียงสถานะได้; หน้ารออนุมัติลา/สลับวัน/กิจกรรมสลับไปประวัติได้ (18 pending / 17 history); รายงานรายบุคคลแสดง 40 เหตุการณ์ด้วย DataTables. ไม่พบ console error ในจังหวะที่ตรวจ รายละเอียดอยู่ใน implementation-verification.json

การตรวจ browser ใช้ GET-only router จึงไม่ยืนยันการส่งฟอร์มหรือ login ด้วยบัญชีจริงทุก role; การเขียนและ rollback ตรวจผ่าน MariaDB fixture

## ก่อนติดตั้งโค้ดนี้

**ต้องเตรียม schema ก่อนเปิดให้ request ใช้ release นี้** เพราะ runtime จะตอบ 503 ถ้ายังไม่มี version marker ที่พร้อมใช้งาน

ใช้ฐาน schema เดิมของ HR, ตั้ง DB configuration ให้ชี้ฐานเป้าหมายที่ถูกต้อง, สำรองฐานข้อมูลที่กู้คืนได้และซ้อมบนสำเนาก่อน จาก root ของ release รัน:

```powershell
C:\xampp\php\php.exe scripts\prepare_schema.php
```

บน Linux ใช้ `php scripts/prepare_schema.php`. สคริปต์ใช้ DB configuration เดียวกับแอป ต้องได้ SCHEMA_READY หรือ SCHEMA_ALREADY_READY จึงค่อยเปิดใช้งาน release. สคริปต์ไม่สร้าง base schema ใหม่และไม่รับคำสั่งผ่าน HTTP

DDL ของ MariaDB ไม่ได้ rollback ด้วย transaction; หาก preparation ล้มเหลว version จะไม่เลื่อน ต้องตรวจสาเหตุแล้วรันซ้ำ. เก็บ release เดิมและ backup ไว้สำหรับ rollback; การคืน DB ต้องคำนึงถึงรายการใหม่หลัง backup ไม่คืนทับข้อมูลที่ใช้งานไปแล้วโดยไม่ตรวจสอบ

Legacy callers ยังคง limit ประวัติคำขอเวลา 50 / กิจกรรม 100 รายการตามเดิม ส่วน paged UI อ่านประวัติที่มีสิทธิ์ได้ครบผ่านการเปลี่ยนหน้า. รายงานที่มี export/bulk selection ยังโหลดชุดข้อมูลครบตามตัวกรองเดิม

## รันทดสอบซ้ำ

```powershell
node scripts\run-tests.cjs
C:\xampp\php\php.exe -n docs\audits\2026-09-19\after-probes.php
C:\xampp\php\php.exe docs\audits\2026-09-19\csv-memory-probe.php
git diff --check
```

Integration test จะระบุ SKIP หากไม่มี HR_TEST_DB_PORT. สำหรับการทดสอบครั้งนี้ใช้ MariaDB ที่แยกบน 127.0.0.1:33079; fixture ใช้ root/password สำหรับ instance ทดสอบตาม tests/support/performance_fixture.php เท่านั้น สร้างและลบเฉพาะฐานสุ่ม hr_perf_fixture_*. ห้ามตั้ง port นี้ให้ชี้ instance ที่มีข้อมูลใช้งานจริง

ยังไม่มีผล production p50/p95, concurrent user load, live EXPLAIN/index distribution, OPcache/CDN หรือการติดตั้งบนเครื่องจริง การปรับ index/server configuration จึงคงรอข้อมูลวัดจากระบบจริงตามข้อเสนอเดิม

หลักฐาน: performance-review.md และ verification.json เป็น baseline ก่อนแก้; implementation-verification.json เป็นผลหลังแก้; after-probes.php และ csv-memory-probe.php ใช้รันวัดซ้ำ
