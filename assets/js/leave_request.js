/*
 * Logic สำหรับหน้ายื่นใบลา (leave_request.php)
 */

document.addEventListener('DOMContentLoaded', () => {
    const leaveRequestForm = document.getElementById('leaveRequestForm');
    
    if (leaveRequestForm) {
        loadLeaveOptions(); // โหลดประเภทการลาลง Dropdown
        setupDateCalculation(); // ตั้งค่าคำนวณวัน
        leaveRequestForm.addEventListener('submit', handleSubmitLeave);
    }
});

let leaveTypesData = []; // เก็บข้อมูลประเภทการลาไว้เช็คเงื่อนไข

async function loadLeaveOptions() {
    const select = document.getElementById('leaveTypeSelect');
    try {
        const response = await fetch('api/leave_request_api.php?action=get_leave_types');
        const res = await response.json();
        
        if (res.status === 'success') {
            leaveTypesData = res.data;
            res.data.forEach(type => {
                const opt = document.createElement('option');
                opt.value = type.id;
                opt.textContent = type.type_name;
                select.appendChild(opt);
            });
        }
    } catch (error) { console.error(error); }

    // ดักจับการเลือกประเภท เพื่อโชว์เงื่อนไขไฟล์แนบ
    select.addEventListener('change', function() {
        const selectedId = this.value;
        const conditionDiv = document.getElementById('leaveTypeCondition');
        const conditionText = document.getElementById('conditionText');
        const attachmentSection = document.getElementById('attachmentSection');
        const attachmentInput = document.getElementById('attachmentInput');

        const type = leaveTypesData.find(t => t.id == selectedId);
        
        if (type) {
            // แสดงเงื่อนไขจำนวนวัน
            conditionDiv.classList.remove('d-none');
            conditionText.textContent = `สิทธิ์ลาสูงสุด: ${type.days_per_year} วัน/ปี`;

            // เช็คว่าต้องแนบไฟล์ไหม
            if (type.requires_file == 1) {
                attachmentSection.classList.remove('d-none');
                attachmentInput.required = true;
            } else {
                attachmentSection.classList.add('d-none');
                attachmentInput.required = false;
                attachmentInput.value = ''; // เคลียร์ไฟล์
            }
        } else {
            conditionDiv.classList.add('d-none');
            attachmentSection.classList.add('d-none');
        }
    });
}

function setupDateCalculation() {
    const startInput = document.getElementById('startDate');
    const endInput = document.getElementById('endDate');
    const totalDisplay = document.getElementById('totalDaysDisplay');
    const totalInput = document.getElementById('totalDaysInput');

    function calculate() {
        const start = new Date(startInput.value);
        const end = new Date(endInput.value);

        if (start && end && !isNaN(start) && !isNaN(end)) {
            if (end < start) {
                totalDisplay.textContent = "วันที่ไม่ถูกต้อง";
                totalDisplay.classList.add('text-danger');
                return;
            }
            totalDisplay.classList.remove('text-danger');
            
            // คำนวณความต่างเวลา (ms) -> แปลงเป็นวัน
            const diffTime = Math.abs(end - start);
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1; // +1 เพราะนับวันเริ่มด้วย
            
            totalDisplay.textContent = diffDays + " วัน";
            totalInput.value = diffDays;
        } else {
            totalDisplay.textContent = "0";
            totalInput.value = 0;
        }
    }

    startInput.addEventListener('change', calculate);
    endInput.addEventListener('change', calculate);
}

async function handleSubmitLeave(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    formData.append('action', 'submit_leave');

    Swal.fire({
        title: 'ยืนยันการส่งใบลา?',
        text: "ตรวจสอบข้อมูลให้ถูกต้องก่อนส่ง",
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'ยืนยันส่ง',
        cancelButtonText: 'ยกเลิก'
    }).then(async (result) => {
        if (result.isConfirmed) {
            Swal.fire({ title: 'กำลังบันทึก...', didOpen: () => Swal.showLoading() });

            try {
                const response = await fetch('api/leave_request_api.php', {
                    method: 'POST',
                    body: formData
                });
                const res = await response.json();

                if (res.status === 'success') {
                    Swal.fire('สำเร็จ', res.message, 'success').then(() => {
                        // (Phase ต่อไป: ไปหน้าประวัติการลา)
                        // window.location.href = 'my_leaves.php'; 
                        location.reload(); // ชั่วคราว: รีโหลดหน้าเดิม
                    });
                } else {
                    Swal.fire('เกิดข้อผิดพลาด', res.message, 'error');
                }
            } catch (err) {
                Swal.fire('Error', err.message, 'error');
            }
        }
    });
}