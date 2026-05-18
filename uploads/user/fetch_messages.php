<?php
session_start();
$conn = new mysqli("localhost", "root", "", "item_exchange");
if ($conn->connect_error) {
    http_response_code(500);
    exit("DB Error");
}

$requestId = isset($_GET['request_id']) ? intval($_GET['request_id']) : 0;
$afterId   = isset($_GET['after_id']) ? intval($_GET['after_id']) : 0; // โหลดเฉพาะข้อความที่ใหม่กว่า

$sql = "SELECT m.id, m.sender_id, u.fullname, m.message, m.sent_at
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE m.request_id = ? AND m.id > ?
        ORDER BY m.id ASC"; // เรียงตาม id เพื่อ append ใน JS
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $requestId, $afterId);
$stmt->execute();
$result = $stmt->get_result();

$messages = [];
while ($row = $result->fetch_assoc()) {
    $messages[] = [
        "id" => $row['id'],               // เพิ่ม id เพื่อใช้ตรวจสอบใน JS
        "sender_id" => $row['sender_id'],
        "fullname" => $row['fullname'],
        "message" => $row['message'],
        "sent_at" => $row['sent_at']
    ];
}

header('Content-Type: application/json');
echo json_encode($messages);
?>
