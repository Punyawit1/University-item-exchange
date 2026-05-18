<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(["success" => false, "error" => "ไม่ได้เข้าสู่ระบบ"]);
    exit();
}

$conn = new mysqli("202.28.34.205", "65011211056", "65011211056", "db65011211056");
if ($conn->connect_error) {
    echo json_encode(["success" => false, "error" => "DB error"]);
    exit();
}

$userId = $_SESSION['user_id'];
$requestId = intval($_POST['request_id']);

// ตรวจสอบไฟล์
if (!isset($_FILES['image']) || $_FILES['image']['error'] != 0) {
    echo json_encode(["success" => false, "error" => "ไม่มีไฟล์"]);
    exit();
}

$targetDir = "../uploads/chat/";
if (!is_dir($targetDir)) {
    mkdir($targetDir, 0777, true);
}

$ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
$allowed = ["jpg","jpeg","png","gif"];
if (!in_array($ext, $allowed)) {
    echo json_encode(["success" => false, "error" => "ไฟล์ไม่รองรับ"]);
    exit();
}

$fileName = uniqid("chat_", true) . "." . $ext;
$targetFile = $targetDir . $fileName;

if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
    $fileUrl = "uploads/chat/" . $fileName;
    $stmt = $conn->prepare("INSERT INTO messages (request_id, sender_id, message) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $requestId, $userId, $fileUrl);
    $stmt->execute();
    $stmt->close();

    echo json_encode(["success" => true, "url" => $fileUrl]);
} else {
    echo json_encode(["success" => false, "error" => "อัปโหลดไม่สำเร็จ"]);
}
$conn->close();
?>
