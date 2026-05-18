<?php
session_start();

// ตรวจสอบว่าผู้ใช้เข้าสู่ระบบและมี session ID
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.html");
    exit();
}

$userId = $_SESSION['user_id'];

// ตรวจสอบว่ามีการส่งข้อมูลแบบ POST มาหรือไม่
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // เชื่อมต่อฐานข้อมูล
    $conn = new mysqli("localhost", "root", "", "item_exchange");
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // รับค่าจากฟอร์ม
    $requestId = $_POST['request_id'];
    $action = $_POST['action'];

    // ตรวจสอบข้อมูลที่จำเป็น
    if (empty($requestId) || empty($action)) {
        header("Location: exchange_requests_received.php?message=ข้อมูลไม่ครบถ้วน");
        exit();
    }

    // ดึงข้อมูลคำขอแลกเปลี่ยนเพื่อตรวจสอบว่าเป็นเจ้าของจริงหรือไม่
    $sql_check_owner = "SELECT er.item_owner_id, i.owner_id, er.requester_id, er.item_request_id
                        FROM exchange_requests er
                        JOIN items i ON er.item_owner_id = i.id
                        WHERE er.id = ? AND i.owner_id = ?";
    $stmt_check = $conn->prepare($sql_check_owner);
    $stmt_check->bind_param("ii", $requestId, $userId);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    if ($result_check->num_rows == 0) {
        header("Location: exchange_requests_received.php?message=ไม่พบคำขอหรือคุณไม่มีสิทธิ์ในการจัดการคำขอนี้");
        exit();
    }

    $requestData = $result_check->fetch_assoc();
    $your_item_id = $requestData['item_owner_id'];
    $requester_id = $requestData['requester_id'];
    $their_item_id = $requestData['item_request_id'];
    $stmt_check->close();

    // เริ่มต้น Transaction เพื่อให้การทำงานทั้งหมดเสร็จสมบูรณ์หรือล้มเหลวพร้อมกัน
    $conn->begin_transaction();
    $success = true;
    $message = "";

    try {
        if ($action == 'accept') {
            // ดึงวิธีการจัดส่งจากฟอร์ม modal
            $deliveryMethod = $_POST['delivery_method'];
            if (empty($deliveryMethod)) {
                throw new Exception("กรุณาเลือกวิธีการจัดส่ง");
            }

            // 1. อัปเดตสถานะคำขอเป็น 'accepted' และบันทึกวิธีการจัดส่ง
            $sql_update_request = "UPDATE exchange_requests SET status = 'accepted', delivery_method = ? WHERE id = ?";
            $stmt_update_request = $conn->prepare($sql_update_request);
            $stmt_update_request->bind_param("si", $deliveryMethod, $requestId);
            $stmt_update_request->execute();
            if ($stmt_update_request->affected_rows === 0) {
                throw new Exception("ไม่สามารถอัปเดตสถานะคำขอได้");
            }
            $stmt_update_request->close();

            // 2. อัปเดตสถานะสิ่งของของคุณให้เป็น 'ไม่พร้อมใช้งาน' (is_available = 0)
            $sql_update_your_item = "UPDATE items SET is_available = 0 WHERE id = ?";
            $stmt_update_your_item = $conn->prepare($sql_update_your_item);
            $stmt_update_your_item->bind_param("i", $your_item_id);
            $stmt_update_your_item->execute();
            $stmt_update_your_item->close();

            // 3. ถ้าเป็นการแลกเปลี่ยน (their_item_id มีค่า) ให้อัปเดตสถานะสิ่งของของอีกฝ่ายด้วย
            if (!empty($their_item_id)) {
                $sql_update_their_item = "UPDATE items SET is_available = 0 WHERE id = ?";
                $stmt_update_their_item = $conn->prepare($sql_update_their_item);
                $stmt_update_their_item->bind_param("i", $their_item_id);
                $stmt_update_their_item->execute();
                $stmt_update_their_item->close();
            }

            // 4. สร้างบันทึกรีวิว 2 อันสำหรับทั้งสองฝ่าย
            $sql_insert_review = "INSERT INTO reviews (exchange_id, reviewer_id, reviewee_id, item_id, rating, comment) VALUES (?, ?, ?, ?, 0, NULL)";
            $stmt_insert_review = $conn->prepare($sql_insert_review);
            
            // บันทึกรีวิวสำหรับเจ้าของสิ่งของ (คุณ) เพื่อให้คะแนนผู้ร้องขอ
            $stmt_insert_review->bind_param("iiii", $requestId, $userId, $requester_id, $your_item_id);
            $stmt_insert_review->execute();

            // บันทึกรีวิวสำหรับผู้ร้องขอเพื่อให้คะแนนเจ้าของสิ่งของ (คุณ)
            $stmt_insert_review->bind_param("iiii", $requestId, $requester_id, $userId, $their_item_id);
            $stmt_insert_review->execute();
            $stmt_insert_review->close();

            $message = "ตอบรับคำขอแลกเปลี่ยนเรียบร้อยแล้ว!";

        } elseif ($action == 'reject') {
            // 1. อัปเดตสถานะคำขอเป็น 'rejected'
            $sql_update_request = "UPDATE exchange_requests SET status = 'rejected' WHERE id = ?";
            $stmt_update_request = $conn->prepare($sql_update_request);
            $stmt_update_request->bind_param("i", $requestId);
            $stmt_update_request->execute();
            if ($stmt_update_request->affected_rows === 0) {
                throw new Exception("ไม่สามารถอัปเดตสถานะคำขอได้");
            }
            $stmt_update_request->close();

            $message = "ปฏิเสธคำขอแลกเปลี่ยนเรียบร้อยแล้ว!";
        }

        // หากทุกอย่างสำเร็จ ให้ Commit การเปลี่ยนแปลงลงฐานข้อมูล
        $conn->commit();
        $message_param = urlencode($message);
        header("Location: exchange_requests_received.php?message=" . $message_param);
        exit();

    } catch (Exception $e) {
        // หากเกิดข้อผิดพลาด ให้ Rollback การเปลี่ยนแปลง
        $conn->rollback();
        $message = "เกิดข้อผิดพลาด: " . $e->getMessage();
        $message_param = urlencode($message);
        header("Location: exchange_requests_received.php?message=" . $message_param);
        exit();
    }

    $conn->close();

} else {
    // ถ้าไม่มีการส่งข้อมูล POST มา ให้ redirect กลับไป
    header("Location: exchange_requests_received.php");
    exit();
}
?>