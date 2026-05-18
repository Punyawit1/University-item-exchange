<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.html");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['review_id'])) {
    $reviewId = intval($_POST['review_id']);
    
    $conn = new mysqli("202.28.34.205", "65011211056", "65011211056", "db65011211056");
    if ($conn->connect_error) {
        die("เชื่อมต่อฐานข้อมูลล้มเหลว: " . $conn->connect_error);
    }

    $stmt = $conn->prepare("DELETE FROM reviews WHERE id = ?");
    $stmt->bind_param("i", $reviewId);
    $stmt->execute();

    $stmt->close();
    $conn->close();
}

header("Location: manage_reviews.php");
exit();
?>
