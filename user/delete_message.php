<?php
session_start();
$conn = new mysqli("202.28.34.205", "65011211056", "65011211056", "db65011211056");
if ($conn->connect_error) {
    http_response_code(500);
    exit("DB Error");
}

$userId = $_SESSION['user_id'] ?? 0;
$messageId = isset($_POST['message_id']) ? intval($_POST['message_id']) : 0;

if ($messageId === 0) {
    http_response_code(400);
    exit("Invalid message ID");
}

// ลบเฉพาะข้อความที่ผู้ใช้เป็นคนส่ง
$stmt = $conn->prepare("DELETE FROM messages WHERE id = ? AND sender_id = ?");
$stmt->bind_param("ii", $messageId, $userId);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false, "error" => "ไม่มีสิทธิ์ลบข้อความนี้"]);
}

$stmt->close();
$conn->close();
?>
