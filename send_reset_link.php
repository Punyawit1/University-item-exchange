<?php
// send_reset_link.php

// 1. เชื่อมต่อฐานข้อมูล
$conn = new mysqli("202.28.34.205", "65011211056", "65011211056", "db65011211056");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 2. รับข้อมูลจากฟอร์ม
$username = $_POST['username'];
$email = $_POST['email'];

// 3. ตรวจสอบว่าชื่อผู้ใช้และอีเมลมีอยู่ในระบบและตรงกันหรือไม่
$stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND email = ?");
$stmt->bind_param("ss", $username, $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    $user_id = $user['id'];
    
    // สร้าง Token สำหรับการรีเซ็ต
    $token = bin2hex(random_bytes(32)); 
    $expires = date("U") + 3600; // Token มีอายุ 1 ชั่วโมง

    // บันทึก Token ลงในฐานข้อมูล
    $stmt_update = $conn->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?");
    $stmt_update->bind_param("sii", $token, $expires, $user_id);
    $stmt_update->execute();
    
    // นำผู้ใช้ไปยังหน้าเปลี่ยนรหัสผ่านทันที
    header("Location: reset_password.php?token=" . $token);
    exit();

} else {
    // ไม่พบข้อมูลที่ตรงกัน
    header("Location: forgot_password.php?status=error");
    exit();
}

$conn->close();
?>