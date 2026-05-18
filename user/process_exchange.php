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
    $conn = new mysqli("202.28.34.205", "65011211056", "65011211056", "db65011211056");
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

    // Ensure confirmation columns exist (add if missing)
    $res = $conn->query("SHOW COLUMNS FROM exchange_requests LIKE 'receiver_confirmed'");
    if ($res && $res->num_rows == 0) {
        $conn->query("ALTER TABLE exchange_requests ADD COLUMN receiver_confirmed TINYINT(1) DEFAULT 0");
    }
    $res = $conn->query("SHOW COLUMNS FROM exchange_requests LIKE 'requester_confirmed'");
    if ($res && $res->num_rows == 0) {
        $conn->query("ALTER TABLE exchange_requests ADD COLUMN requester_confirmed TINYINT(1) DEFAULT 0");
    }

    // ดึงข้อมูลคำขอแลกเปลี่ยนและข้อมูลเจ้าของสินค้าจากตาราง items
    $sql_check = "SELECT er.*, i.owner_id AS owner_user_id, i.id AS owner_item_id FROM exchange_requests er JOIN items i ON er.item_owner_id = i.id WHERE er.id = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("i", $requestId);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    if ($result_check->num_rows == 0) {
        header("Location: exchange_requests_received.php?message=ไม่พบคำขอ");
        exit();
    }

    $requestData = $result_check->fetch_assoc();
    $owner_user_id = $requestData['owner_user_id'];
    $your_item_id = $requestData['item_owner_id']; // id ของสิ่งของที่ถูกขอ (owner's item id)
    $requester_id = $requestData['requester_id'];
    $their_item_id = $requestData['item_request_id'];
    $current_status = $requestData['status'];
    $receiver_confirmed = isset($requestData['receiver_confirmed']) ? (int)$requestData['receiver_confirmed'] : 0;
    $requester_confirmed = isset($requestData['requester_confirmed']) ? (int)$requestData['requester_confirmed'] : 0;
    $stmt_check->close();

    // ตรวจสอบสิทธิ์: ผู้ที่มีสิทธิ์คือ เจ้าของสินค้าที่ถูกขอ (owner_user_id) และ ผู้ร้องขอ (requester_id)
    if ($userId !== (int)$owner_user_id && $userId !== (int)$requester_id) {
        header("Location: exchange_requests_received.php?message=คุณไม่มีสิทธิ์ในการจัดการคำขอนี้");
        exit();
    }

    // เริ่ม Transaction เพื่อให้การทำงานทั้งหมดเสร็จสมบูรณ์หรือล้มเหลวพร้อมกัน
    $conn->begin_transaction();
    $message = "";

    try {
        if ($action == 'choose_delivery') {
            // Receiver (owner) เลือกวิธีการจัดส่ง
            if ($userId !== (int)$owner_user_id) {
                throw new Exception("เฉพาะเจ้าของสินค้าสามารถเลือกวิธีการจัดส่งได้");
            }
            $deliveryMethod = $_POST['delivery_method'];
            if (empty($deliveryMethod)) {
                throw new Exception("กรุณาเลือกวิธีการจัดส่ง");
            }

            // อัปเดตสถานะเป็น shipping, บันทึกวิธีการจัดส่ง และ reset confirmations
            $sql_update_request = "UPDATE exchange_requests SET status = 'shipping', delivery_method = ?, receiver_confirmed = 0, requester_confirmed = 0 WHERE id = ?";
            $stmt_update_request = $conn->prepare($sql_update_request);
            $stmt_update_request->bind_param("si", $deliveryMethod, $requestId);
            $stmt_update_request->execute();
            if ($stmt_update_request->affected_rows === 0) {
                throw new Exception("ไม่สามารถอัปเดตสถานะคำขอได้");
            }
            $stmt_update_request->close();
            $message = "เลือกวิธีการจัดส่งเรียบร้อย รอการยืนยันจากทั้งสองฝ่าย";

        } elseif ($action == 'confirm_receiver') {
            // Receiver (owner) กดยืนยันการจัดส่ง
            if ($userId !== (int)$owner_user_id) {
                throw new Exception("เฉพาะเจ้าของสินค้าสามารถยืนยันในฐานะผู้รับได้");
            }

            // Set receiver_confirmed = 1
            $sql_update_request = "UPDATE exchange_requests SET receiver_confirmed = 1, status = 'confirm_receiver' WHERE id = ?";
            $stmt_update_request = $conn->prepare($sql_update_request);
            $stmt_update_request->bind_param("i", $requestId);
            $stmt_update_request->execute();
            $stmt_update_request->close();
            $message = "ผู้รับยืนยันแล้ว รอผู้ร้องขอยืนยัน";

            // Refresh flags
            $resFlags = $conn->prepare("SELECT receiver_confirmed, requester_confirmed FROM exchange_requests WHERE id = ?");
            $resFlags->bind_param("i", $requestId);
            $resFlags->execute();
            $flags = $resFlags->get_result()->fetch_assoc();
            $receiver_confirmed = (int)$flags['receiver_confirmed'];
            $requester_confirmed = (int)$flags['requester_confirmed'];
            $resFlags->close();

            if ($receiver_confirmed && $requester_confirmed) {
                // ทั้งสองฝ่ายยืนยัน -> ดำเนินการแลกเปลี่ยน
                $sql_accept = "UPDATE exchange_requests SET status = 'accepted' WHERE id = ?";
                $stmt_accept = $conn->prepare($sql_accept);
                $stmt_accept->bind_param("i", $requestId);
                $stmt_accept->execute();
                $stmt_accept->close();

                // อัปเดตสถานะสิ่งของทั้งหมดเป็นไม่พร้อมใช้งาน
                $sql_update_your_item = "UPDATE items SET is_available = 0 WHERE id = ?";
                $stmt_update_your_item = $conn->prepare($sql_update_your_item);
                $stmt_update_your_item->bind_param("i", $your_item_id);
                $stmt_update_your_item->execute();
                $stmt_update_your_item->close();

                if (!empty($their_item_id)) {
                    $sql_update_their_item = "UPDATE items SET is_available = 0 WHERE id = ?";
                    $stmt_update_their_item = $conn->prepare($sql_update_their_item);
                    $stmt_update_their_item->bind_param("i", $their_item_id);
                    $stmt_update_their_item->execute();
                    $stmt_update_their_item->close();
                }

                // สร้างบันทึกรีวิว 2 อันสำหรับทั้งสองฝ่าย
                $sql_insert_review = "INSERT INTO reviews (exchange_id, reviewer_id, reviewee_id, item_id, rating, comment) VALUES (?, ?, ?, ?, 0, NULL)";
                // review for owner -> reviewer = owner_user_id, reviewee = requester_id
                $stmt_insert_review = $conn->prepare($sql_insert_review);
                $stmt_insert_review->bind_param("iiii", $requestId, $owner_user_id, $requester_id, $your_item_id);
                $stmt_insert_review->execute();
                // review for requester -> reviewer = requester_id, reviewee = owner_user_id
                $stmt_insert_review->bind_param("iiii", $requestId, $requester_id, $owner_user_id, $their_item_id);
                $stmt_insert_review->execute();
                $stmt_insert_review->close();

                $message = "แลกเปลี่ยนสำเร็จ!";
            }

        } elseif ($action == 'confirm_requester') {
            // Requester กดยืนยันการรับของ
            if ($userId !== (int)$requester_id) {
                throw new Exception("เฉพาะผู้ร้องขอสามารถยืนยันในฐานะผู้ร้องขอได้");
            }

            // Set requester_confirmed = 1
            $sql_update_request = "UPDATE exchange_requests SET requester_confirmed = 1, status = 'confirm_requester' WHERE id = ?";
            $stmt_update_request = $conn->prepare($sql_update_request);
            $stmt_update_request->bind_param("i", $requestId);
            $stmt_update_request->execute();
            $stmt_update_request->close();
            $message = "ผู้ร้องขอยืนยันแล้ว รอผู้รับยืนยัน";

            // Refresh flags
            $resFlags = $conn->prepare("SELECT receiver_confirmed, requester_confirmed FROM exchange_requests WHERE id = ?");
            $resFlags->bind_param("i", $requestId);
            $resFlags->execute();
            $flags = $resFlags->get_result()->fetch_assoc();
            $receiver_confirmed = (int)$flags['receiver_confirmed'];
            $requester_confirmed = (int)$flags['requester_confirmed'];
            $resFlags->close();

            if ($receiver_confirmed && $requester_confirmed) {
                // ทั้งสองฝ่ายยืนยัน -> ดำเนินการแลกเปลี่ยน
                $sql_accept = "UPDATE exchange_requests SET status = 'accepted' WHERE id = ?";
                $stmt_accept = $conn->prepare($sql_accept);
                $stmt_accept->bind_param("i", $requestId);
                $stmt_accept->execute();
                $stmt_accept->close();

                // อัปเดตสถานะสิ่งของทั้งหมดเป็นไม่พร้อมใช้งาน
                $sql_update_your_item = "UPDATE items SET is_available = 0 WHERE id = ?";
                $stmt_update_your_item = $conn->prepare($sql_update_your_item);
                $stmt_update_your_item->bind_param("i", $your_item_id);
                $stmt_update_your_item->execute();
                $stmt_update_your_item->close();

                if (!empty($their_item_id)) {
                    $sql_update_their_item = "UPDATE items SET is_available = 0 WHERE id = ?";
                    $stmt_update_their_item = $conn->prepare($sql_update_their_item);
                    $stmt_update_their_item->bind_param("i", $their_item_id);
                    $stmt_update_their_item->execute();
                    $stmt_update_their_item->close();
                }

                // สร้างบันทึกรีวิว 2 อันสำหรับทั้งสองฝ่าย
                $sql_insert_review = "INSERT INTO reviews (exchange_id, reviewer_id, reviewee_id, item_id, rating, comment) VALUES (?, ?, ?, ?, 0, NULL)";
                $stmt_insert_review = $conn->prepare($sql_insert_review);
                $stmt_insert_review->bind_param("iiii", $requestId, $owner_user_id, $requester_id, $your_item_id);
                $stmt_insert_review->execute();
                $stmt_insert_review->bind_param("iiii", $requestId, $requester_id, $owner_user_id, $their_item_id);
                $stmt_insert_review->execute();
                $stmt_insert_review->close();

                $message = "แลกเปลี่ยนสำเร็จ!";
            }

        } elseif ($action == 'reject') {
            // อัปเดตสถานะคำขอเป็น 'rejected' (อนุญาตให้เจ้าของหรือผู้ร้องขอยกเลิก/ปฏิเสธ)
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
        // นำผู้ใช้กลับไปยังหน้าที่เหมาะสม (ถ้าเป็นเจ้าของ -> received, ถ้าเป็นผู้ร้องขอ -> sent)
        if ($userId === (int)$owner_user_id) {
            header("Location: exchange_requests_received.php?message=" . $message_param);
        } else {
            header("Location: exchange_requests_sent.php?message=" . $message_param);
        }
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