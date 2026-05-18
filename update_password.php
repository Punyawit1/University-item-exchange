<?php
// update_password.php

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // 1. เชื่อมต่อฐานข้อมูล
    $conn = new mysqli("202.28.34.205", "65011211056", "65011211056", "db65011211056");
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // 2. รับข้อมูลจากฟอร์ม
    $token = $_POST['token'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // 3. ตรวจสอบว่ารหัสผ่านใหม่ตรงกันหรือไม่
    if ($new_password !== $confirm_password) {
        // ควร redirect กลับไปหน้าเดิมพร้อมข้อความ error ที่ชัดเจนกว่านี้
        // ตัวอย่าง: header("Location: reset_password.php?token=$token&error=mismatch");
        die("รหัสผ่านไม่ตรงกัน"); 
    }

    // 4. ตรวจสอบ Token อีกครั้ง
    $stmt = $conn->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expires >= ?");
    $now = date("U");
    $stmt->bind_param("si", $token, $now);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($result->num_rows > 0) {
        // 5. Hash รหัสผ่านใหม่และอัปเดตในฐานข้อมูล
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt_update = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
        $stmt_update->bind_param("si", $hashed_password, $user['id']);
        $stmt_update->execute();
        
        // 6. เปลี่ยนเส้นทางไปยังหน้าแสดงความสำเร็จ
        header("Location: password_success.html");
        exit();
    } else {
        // ลิงก์ไม่ถูกต้อง
        header("Location: forgot_password.php?status=error"); 
        exit();
    }

    $conn->close();
}
?>