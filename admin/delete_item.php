<?php
session_start();

// ตรวจสอบสิทธิ์ admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.html");
    exit();
}

// ตรวจสอบว่ามีการส่งค่า id มาหรือไม่
if (!isset($_GET['id'])) {
    die("ไม่พบรหัสสินค้าที่ต้องการลบ");
}

$itemId = intval($_GET['id']);

// เชื่อมต่อฐานข้อมูล
$conn = new mysqli("202.28.34.205", "65011211056", "65011211056", "db65011211056");
if ($conn->connect_error) {
    die("เชื่อมต่อฐานข้อมูลล้มเหลว: " . $conn->connect_error);
}

// 1. ลบ exchange_requests ที่เกี่ยวข้องกับสินค้านี้ทั้ง owner และ request
$deleteRequestsOwner = $conn->prepare("DELETE FROM exchange_requests WHERE item_owner_id = ?");
$deleteRequestsOwner->bind_param("i", $itemId);
$deleteRequestsOwner->execute();
$deleteRequestsOwner->close();

$deleteRequestsRequest = $conn->prepare("DELETE FROM exchange_requests WHERE item_request_id = ?");
$deleteRequestsRequest->bind_param("i", $itemId);
$deleteRequestsRequest->execute();
$deleteRequestsRequest->close();

// 2. ลบภาพสินค้า
$deleteImages = $conn->prepare("DELETE FROM item_images WHERE item_id = ?");
$deleteImages->bind_param("i", $itemId);
$deleteImages->execute();
$deleteImages->close();

// 3. ลบรายการสินค้า
$deleteItem = $conn->prepare("DELETE FROM items WHERE id = ?");
$deleteItem->bind_param("i", $itemId);

if ($deleteItem->execute()) {
    $deleteItem->close();
    $conn->close();
    header("Location: admin_main.php?msg=deleted");
    exit();
} else {
    echo "เกิดข้อผิดพลาดในการลบสินค้า: " . $deleteItem->error;
    $deleteItem->close();
    $conn->close();
}
?>
