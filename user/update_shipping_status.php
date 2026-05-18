<?php
session_start();

// ตรวจสอบว่าผู้ใช้เข้าสู่ระบบแล้วหรือไม่
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

    // ดึงข้อมูลคำขอแลกเปลี่ยนเพื่อตรวจสอบสิทธิ์ (รวมคอลัมน์ยืนยันใหม่)
    $sql_check = "SELECT er.*, i.owner_id 
                  FROM exchange_requests er 
                  JOIN items i ON er.item_owner_id = i.id 
                  WHERE er.id = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("i", $requestId);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    if ($result_check->num_rows == 0) {
        header("Location: exchange_requests_received.php?message=ไม่พบคำขอนี้");
        exit();
    }

    $requestData = $result_check->fetch_assoc();
    $stmt_check->close();

    // ตรวจสอบสิทธิ์ในการดำเนินการ
    $isOwner = ($requestData['owner_id'] == $userId);
    $isRequester = ($requestData['requester_id'] == $userId);

    $message = "";
    
    try {
        if ($action == 'mark_shipped' && $isOwner) {
            // เจ้าของสิ่งของยืนยันการจัดส่ง
            if ($requestData['status'] != 'accepted') {
                throw new Exception("สามารถจัดส่งได้เฉพาะคำขอที่ได้รับการยอมรับแล้ว");
            }
            
            if ($requestData['shipping_status'] != 'not_started') {
                throw new Exception("สิ่งของนี้ได้ถูกจัดส่งไปแล้ว");
            }

            $sql_update = "UPDATE exchange_requests 
                          SET shipping_status = 'shipped', shipped_at = NOW() 
                          WHERE id = ?";
            $stmt_update = $conn->prepare($sql_update);
            $stmt_update->bind_param("i", $requestId);
            
            if ($stmt_update->execute()) {
                $message = "ยืนยันการจัดส่งเรียบร้อยแล้ว! สถานะเปลี่ยนเป็น 'กำลังส่งของ'";
            } else {
                throw new Exception("เกิดข้อผิดพลาดในการอัปเดตสถานะ");
            }
            $stmt_update->close();

        } elseif ($action == 'confirm_delivery' && ($isRequester || $isOwner)) {
            // ทั้งผู้รับและผู้ส่งสามารถยืนยันการได้รับของได้
            if ($requestData['shipping_status'] != 'shipped') {
                throw new Exception("สามารถยืนยันรับของได้เฉพาะเมื่อสิ่งของอยู่ในสถานะ 'กำลังส่งของ'");
            }

            // เริ่ม transaction
            $conn->begin_transaction();

            // ตรวจสอบว่าเป็นใครที่ยืนยัน และอัปเดตสถานะการยืนยันของฝ่ายนั้น
            if ($isOwner) {
                // เจ้าของสิ่งของ (receiver) ยืนยัน
                if (isset($requestData['receiver_confirmed']) && $requestData['receiver_confirmed']) {
                    throw new Exception("คุณได้ยืนยันการรับของไปแล้ว");
                }
                $sql_update = "UPDATE exchange_requests SET receiver_confirmed = TRUE WHERE id = ?";
            } else {
                // ผู้ร้องขอยืนยัน
                if ($requestData['requester_confirmed']) {
                    throw new Exception("คุณได้ยืนยันการรับของไปแล้ว");
                }
                $sql_update = "UPDATE exchange_requests SET requester_confirmed = TRUE WHERE id = ?";
            }
            
            $stmt_update = $conn->prepare($sql_update);
            $stmt_update->bind_param("i", $requestId);
            $stmt_update->execute();
            $stmt_update->close();
            
            // ตรวจสอบว่าทั้งสองฝ่ายยืนยันแล้วหรือไม่
            $sql_check_both = "SELECT receiver_confirmed, requester_confirmed FROM exchange_requests WHERE id = ?";
            $stmt_check_both = $conn->prepare($sql_check_both);
            $stmt_check_both->bind_param("i", $requestId);
            $stmt_check_both->execute();
            $result_both = $stmt_check_both->get_result();
            $confirmation_data = $result_both->fetch_assoc();
            $stmt_check_both->close();
            
            // ถ้าทั้งสองฝ่ายยืนยันแล้ว ให้เปลี่ยนสถานะเป็น delivered และ completed
            if ($confirmation_data['receiver_confirmed'] && $confirmation_data['requester_confirmed']) {
                $sql_complete = "UPDATE exchange_requests 
                               SET shipping_status = 'delivered', delivered_at = NOW(), status = 'completed' 
                               WHERE id = ?";
                $stmt_complete = $conn->prepare($sql_complete);
                $stmt_complete->bind_param("i", $requestId);
                $stmt_complete->execute();
                $stmt_complete->close();
                
                $exchange_completed = true;
            } else {
                $exchange_completed = false;
            }

            // สร้างบันทึกรีวิวทันทีเมื่อผู้ใช้ยืนยันรับของ (Immediate Rating)
            $sql_check_reviews = "SELECT COUNT(*) as count FROM reviews WHERE exchange_id = ?";
            $stmt_check_reviews = $conn->prepare($sql_check_reviews);
            $stmt_check_reviews->bind_param("i", $requestId);
            $stmt_check_reviews->execute();
            $result_reviews = $stmt_check_reviews->get_result();
            $review_count = $result_reviews->fetch_assoc()['count'];
            $stmt_check_reviews->close();

            if ($review_count == 0) {
                $sql_insert_review = "INSERT INTO reviews (exchange_id, reviewer_id, reviewee_id, item_id, rating, comment) VALUES (?, ?, ?, ?, 0, NULL)";
                $stmt_insert_review = $conn->prepare($sql_insert_review);
                
                // บันทึกรีวิวสำหรับเจ้าของสิ่งของเพื่อให้คะแนนผู้ร้องขอ
                $stmt_insert_review->bind_param("iiii", $requestId, $requestData['owner_id'], $requestData['requester_id'], $requestData['item_owner_id']);
                $stmt_insert_review->execute();

                // บันทึกรีวิวสำหรับผู้ร้องขอเพื่อให้คะแนนเจ้าของสิ่งของ
                $stmt_insert_review->bind_param("iiii", $requestId, $requestData['requester_id'], $requestData['owner_id'], $requestData['item_request_id']);
                $stmt_insert_review->execute();
                $stmt_insert_review->close();
            }

            $conn->commit();
            
            $confirmer_role = $isOwner ? "ผู้ส่ง" : "ผู้รับ";
            if ($exchange_completed) {
                $message = "ยืนยันการรับของเรียบร้อยแล้ว ({$confirmer_role})! ทั้งสองฝ่ายได้ยืนยันแล้ว การแลกเปลี่ยนเสร็จสมบูรณ์ คุณสามารถให้คะแนนได้แล้ว";
            } else {
                $other_party = $isOwner ? "ผู้รับ" : "ผู้ส่ง";
                $message = "ยืนยันการรับของเรียบร้อยแล้ว ({$confirmer_role})! คุณสามารถให้คะแนนได้แล้ว รออีกฝ่าย ({$other_party}) ยืนยันเพื่อให้การแลกเปลี่ยนเสร็จสมบูรณ์";
            }

        } else {
            throw new Exception("คุณไม่มีสิทธิ์ในการดำเนินการนี้");
        }

    } catch (Exception $e) {
        if (isset($conn) && $conn->connect_errno == 0) {
            $conn->rollback();
        }
        $message = "เกิดข้อผิดพลาด: " . $e->getMessage();
    }

    $conn->close();
    
    // Redirect กลับไปหน้าเดิมพร้อมข้อความ
    $redirect_page = $isOwner ? "exchange_requests_received.php" : "exchange_requests_sent.php";
    $message_param = urlencode($message);
    header("Location: " . $redirect_page . "?message=" . $message_param);
    exit();

} else {
    // ถ้าไม่มีการส่งข้อมูล POST มา ให้ redirect กลับไป
    header("Location: exchange_requests_received.php");
    exit();
}
?>