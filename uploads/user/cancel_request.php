<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.html");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'])) {
    $userId = $_SESSION['user_id'];
    $requestId = (int)$_POST['request_id'];

    $conn = new mysqli("localhost", "root", "", "item_exchange");
    if ($conn->connect_error) {
        die("การเชื่อมต่อฐานข้อมูลล้มเหลว: " . $conn->connect_error);
    }

    // ตรวจสอบว่าเจ้าของคำขอคือผู้ใช้คนนี้ และสถานะยังเป็น pending
    $stmt = $conn->prepare("SELECT * FROM exchange_requests WHERE id = ? AND requester_id = ? AND status = 'pending'");
    $stmt->bind_param("ii", $requestId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // ลบคำขอ
        $deleteStmt = $conn->prepare("DELETE FROM exchange_requests WHERE id = ?");
        $deleteStmt->bind_param("i", $requestId);
        $deleteStmt->execute();
        $deleteStmt->close();
    }

    $stmt->close();
    $conn->close();
}

// กลับไปยังหน้าคำขอที่คุณส่ง
header("Location: exchange_requests_sent.php");
exit();
?>
