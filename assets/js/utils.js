/*
 * ไฟล์รวมฟังก์ชันส่วนกลาง (Utilities)
 */

// ฟังก์ชันสำหรับแสดง SweetAlert แบบมาตรฐาน
function showAlert(icon, title, text) {
    Swal.fire({
        icon: icon,
        title: title,
        text: text,
        confirmButtonColor: '#0d6efd'
    });
}