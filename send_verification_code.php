<?php
// send_verification_code.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// โปรดแก้ไข 3 บรรทัดนี้ให้ตรงกับโฟลเดอร์ PHPMailer ที่คุณให้มา
// Path from send_verification_code.php to PHPMailer-master/src
require 'PHPMailer-master/src/Exception.php';
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';

// 2. เชื่อมต่อฐานข้อมูล
$conn = new mysqli("202.28.34.205", "65011211056", "65011211056", "db65011211056");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 3. จัดการข้อมูลจากฟอร์มที่ส่งมา (POST)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $email = $_POST['email'];

    // 4. ตรวจสอบว่าชื่อผู้ใช้และอีเมลตรงกันในฐานข้อมูลหรือไม่
    $stmt = $conn->prepare("SELECT id, reset_token FROM users WHERE username = ? AND email = ?");
    $stmt->bind_param("ss", $username, $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $user_id = $user['id'];
        $reset_token = $user['reset_token'];

        // 5. สร้างรหัสยืนยัน 6 หลัก
        $code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires = time() + 600; // รหัสจะหมดอายุใน 10 นาที (600 วินาที)

        // 6. บันทึกรหัสยืนยันและเวลาหมดอายุลงในฐานข้อมูล
        $stmt_update = $conn->prepare("UPDATE users SET verification_code = ?, verification_expires = ? WHERE id = ?");
        $stmt_update->bind_param("ssi", $code, $expires, $user_id);
        $stmt_update->execute();
        
        // 7. ส่งอีเมลด้วย PHPMailer
        $mail = new PHPMailer(true);
        try {
            // ตั้งค่าเซิร์ฟเวอร์ SMTP
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com'; // สำหรับ Gmail
            $mail->SMTPAuth   = true;
            $mail->Username   = 'valakonkalajit@gmail.com'; // เปลี่ยนเป็นอีเมลของคุณ
            $mail->Password   = 'mwwp uira fjwy dgko';    // เปลี่ยนเป็น App Password ของคุณ
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = 465;

            // *** เพิ่มบรรทัดนี้เพื่อตั้งค่าการเข้ารหัสเป็น UTF-8 ***
            $mail->CharSet = 'UTF-8';

            // ตั้งค่าผู้ส่งและผู้รับ
            $mail->setFrom('valakonkalajit@gmail.com', 'Item Exchange');
            $mail->addAddress($email, $username);

            // ตั้งค่าเนื้อหาอีเมล
            $mail->isHTML(false); // ตั้งค่าอีเมลเป็นข้อความธรรมดา
            $mail->Subject = 'รหัสยืนยันการเปลี่ยนรหัสผ่านของคุณ';
            $mail->Body     = "สวัสดีคุณ " . $username . ",\n\n"
                            . "รหัสยืนยันของคุณคือ: " . $code . "\n"
                            . "รหัสนี้จะหมดอายุใน 10 นาที\n\n"
                            . "หากคุณไม่ได้ร้องขอการเปลี่ยนรหัสผ่านนี้ โปรดไม่ต้องดำเนินการใดๆ\n\n"
                            . "ขอแสดงความนับถือ,\n"
                            . "ทีมงาน Item Exchange";

            $mail->send();
            
            // 8. เปลี่ยนเส้นทางไปยังหน้าถัดไปพร้อมสถานะความสำเร็จ
            header("Location: verify_code.php?status=success&email=" . urlencode($email) . "&token=" . urlencode($reset_token));
            exit();

        } catch (Exception $e) {
            // หากมีข้อผิดพลาดในการส่งอีเมล
            header("Location: forgot_password.php?status=error&message=" . urlencode("ไม่สามารถส่งรหัสยืนยันได้: {$mail->ErrorInfo}"));
            exit();
        }
        
    } else {
        // 9. ถ้าไม่พบข้อมูลที่ตรงกัน ให้เปลี่ยนเส้นทางกลับไปยังหน้าก่อนหน้าพร้อมสถานะผิดพลาด
        header("Location: forgot_password.php?status=error");
        exit();
    }
}

$conn->close();
?>
