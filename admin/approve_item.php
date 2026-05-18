<?php
session_start();

// ตรวจสอบว่าล็อกอินและเป็นแอดมินเท่านั้น
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.html");
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ไม่พบ ID รายการที่ต้องการอนุมัติ");
}

$itemId = (int)$_GET['id'];

// เชื่อมต่อฐานข้อมูล
$conn = new mysqli("202.28.34.205", "65011211056", "65011211056", "db65011211056");
if ($conn->connect_error) {
    die("เชื่อมต่อฐานข้อมูลล้มเหลว: " . $conn->connect_error);
}

// อัพเดตสถานะอนุมัติเป็น 1
$stmt = $conn->prepare("UPDATE items SET is_approved = 1 WHERE id = ?");
$stmt->bind_param("i", $itemId);

if ($stmt->execute()) {
    // กลับไปหน้า admin_main.php หรือหน้าที่ต้องการ พร้อมส่งข้อความสำเร็จผ่าน GET parameter
    header("Location: admin_main.php?msg=approved");
    exit();
} else {
    echo "เกิดข้อผิดพลาดในการอนุมัติรายการ: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>
