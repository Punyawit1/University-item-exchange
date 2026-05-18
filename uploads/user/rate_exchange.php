<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.html");
    exit();
}

$conn = new mysqli("localhost", "root", "", "item_exchange");
if ($conn->connect_error) {
    die("เชื่อมต่อฐานข้อมูลล้มเหลว: " . $conn->connect_error);
}

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];

$message = "";
$messageType = "";

// ดึงข้อมูล review ที่ต้องการแก้ไข
$reviewId = isset($_GET['review_id']) ? intval($_GET['review_id']) : 0;
$stmtReview = $conn->prepare("
    SELECT r.id, r.exchange_id, r.reviewer_id, r.reviewee_id, r.rating, r.comment,
           er.item_owner_id, er.item_request_id,
           u_reviewee.fullname AS reviewee_name,
           item_req.name AS item_requester_name,
           item_owner.name AS item_owner_name
    FROM reviews r
    JOIN exchange_requests er ON r.exchange_id = er.id
    JOIN users u_reviewee ON r.reviewee_id = u_reviewee.id
    LEFT JOIN items item_req ON er.item_request_id = item_req.id
    LEFT JOIN items item_owner ON er.item_owner_id = item_owner.id
    WHERE r.id = ? AND r.reviewer_id = ?
");
$stmtReview->bind_param("ii", $reviewId, $userId);
$stmtReview->execute();
$resultReview = $stmtReview->get_result();
$reviewData = $resultReview->fetch_assoc();
$stmtReview->close();

if (!$reviewData) {
    // Redirect เพื่อแสดงข้อความที่สวยงามกว่าเดิม
    header("Location: my_reviews.php?error=1&msg=" . urlencode("ไม่พบรายการให้คะแนนนี้ หรือคุณไม่มีสิทธิ์แก้ไข."));
    exit();
}

// ดึงข้อมูลผู้ใช้ปัจจุบันสำหรับ Header
$stmtUser = $conn->prepare("SELECT profile_image FROM users WHERE id = ?");
$stmtUser->bind_param("i", $userId);
$stmtUser->execute();
$resultUser = $stmtUser->get_result();
$user = $resultUser->fetch_assoc();
$stmtUser->close();

// กำหนดรูปโปรไฟล์สำหรับ Header
$profileImageHeader = (!empty($user['profile_image']) && file_exists("../uploads/profile/" . $user['profile_image']))
    ? "../uploads/profile/" . $user['profile_image']
    : "../uploads/profile/default.png";

// ดึงจำนวนคำขอแลกเปลี่ยนที่ยังไม่ได้ดู (pending) สำหรับ Header
$unreadRequestsCount = 0;
$sql_unread = "SELECT COUNT(*) AS unread_count FROM exchange_requests WHERE item_owner_id IN (SELECT id FROM items WHERE owner_id = ?) AND status = 'pending'";
$stmt_unread = $conn->prepare($sql_unread);
$stmt_unread->bind_param("i", $userId);
$stmt_unread->execute();
$result_unread = $stmt_unread->get_result();
$row_unread = $result_unread->fetch_assoc();
if ($row_unread) {
    $unreadRequestsCount = $row_unread['unread_count'];
}
$stmt_unread->close();

// ตรวจสอบว่ามีการส่งฟอร์มเพื่อบันทึกคะแนนหรือไม่
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_rating'])) {
    $newRating = isset($_POST['rating']) ? intval($_POST['rating']) : 0;
    $comment = trim($_POST['comment']);
    
    // ดึงคะแนนเดิมจากข้อมูลที่ดึงมาตั้งแต่แรก
    $oldRating = $reviewData['rating'];

    if ($newRating >= 1 && $newRating <= 5) {
        // อัปเดตตาราง reviews ก่อน
        $stmtUpdateReview = $conn->prepare("UPDATE reviews SET rating = ?, comment = ?, date_rated = NOW() WHERE id = ? AND reviewer_id = ?");
        $stmtUpdateReview->bind_param("isii", $newRating, $comment, $reviewId, $userId);
        
        if ($stmtUpdateReview->execute()) {
            // โค้ดที่แก้ไข: อัปเดตคะแนนของผู้ที่ถูกรีวิวโดยลบคะแนนเก่าออกและบวกคะแนนใหม่เข้าไป
            $revieweeId = $reviewData['reviewee_id'];
            $stmtUpdateScore = $conn->prepare("UPDATE users SET score = score - ? + ? WHERE id = ?");
            $stmtUpdateScore->bind_param("iii", $oldRating, $newRating, $revieweeId);

            if ($stmtUpdateScore->execute()) {
                $message = "บันทึกคะแนนและอัปเดตคะแนนเรียบร้อยแล้ว ✅";
                $messageType = 'success';
            } else {
                $message = "บันทึกคะแนนเรียบร้อยแล้ว แต่เกิดข้อผิดพลาดในการอัปเดตคะแนน ❌";
                $messageType = 'error';
            }
            $stmtUpdateScore->close();
        } else {
            $message = "เกิดข้อผิดพลาดในการบันทึกคะแนน ❌";
            $messageType = 'error';
        }
        $stmtUpdateReview->close();
    } else {
        $message = "กรุณาให้คะแนนระหว่าง 1 ถึง 5 ดาว ⚠️";
        $messageType = 'error';
    }
    // ดึงข้อมูล review ใหม่หลังจากอัปเดตเพื่อแสดงผลที่ถูกต้อง
    $stmtReview = $conn->prepare("
        SELECT r.id, r.exchange_id, r.reviewer_id, r.reviewee_id, r.rating, r.comment,
                er.item_owner_id, er.item_request_id,
                u_reviewee.fullname AS reviewee_name,
                item_req.name AS item_requester_name,
                item_owner.name AS item_owner_name
        FROM reviews r
        JOIN exchange_requests er ON r.exchange_id = er.id
        JOIN users u_reviewee ON r.reviewee_id = u_reviewee.id
        LEFT JOIN items item_req ON er.item_request_id = item_req.id
        LEFT JOIN items item_owner ON er.item_owner_id = item_owner.id
        WHERE r.id = ? AND r.reviewer_id = ?
    ");
    $stmtReview->bind_param("ii", $reviewId, $userId);
    $stmtReview->execute();
    $resultReview = $stmtReview->get_result();
    $reviewData = $resultReview->fetch_assoc();
    $stmtReview->close();
}

?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ให้คะแนนและแสดงความคิดเห็น | แลกของ</title>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <style>
        :root {
            --primary-color: #FFC107; /* Amber */
            --primary-dark: #FFA000;
            --text-color: #333;
            --light-text-color: #666;
            --bg-light: #f5f5f5;
            --white: #fff;
            --shadow-medium: rgba(0,0,0,0.12);
            --success-color: #28a745;
            --danger-color: #dc3545;
            --info-color: #007bff;
            --border-color: #e0e0e0;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Kanit', sans-serif;
            background: var(--bg-light);
            color: var(--text-color);
            line-height: 1.6;
        }

        header {
            background: var(--primary-color);
            color: var(--white);
            padding: 1rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            box-shadow: 0 2px 8px var(--shadow-medium);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        header .header-left {
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        header .header-left strong {
            font-size: 1.5rem;
            font-weight: 700;
            white-space: nowrap;
        }
        
        header nav {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-left: 20px;
        }
        
        header nav a {
            color: var(--white);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.95rem;
            padding: 5px 0;
            position: relative;
            transition: color 0.3s ease;
        }
        
        header nav a:hover {
            color: var(--primary-dark);
        }
        
        header nav a::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: -5px;
            left: 0;
            background-color: var(--primary-dark);
            transition: width 0.3s ease-out;
        }
        
        header nav a:hover::after {
            width: 100%;
        }

        .nav-link-with-badge {
            position: relative;
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -10px;
            background-color: var(--danger-color);
            color: white;
            font-size: 0.7em;
            font-weight: bold;
            padding: 2px 6px;
            border-radius: 50%;
            line-height: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            min-width: 20px;
            height: 20px;
        }

        header .header-right {
            display: flex;
            align-items: center;
            gap: 10px;
            white-space: nowrap;
            margin-top: 5px;
        }
        
        header .header-right img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--white);
        }
        
        header .header-right span {
            font-weight: 600;
            font-size: 1rem;
        }
        
        header .header-right a {
            color: var(--white);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }
        header .header-right a:hover {
            color: var(--primary-dark);
        }
        
        .report-button {
            display: inline-flex;
            align-items: center;
            padding: 8px 15px;
            background-color: var(--danger-color);
            color: var(--white);
            font-weight: 600;
            border-radius: 5px;
            text-decoration: none;
            font-size: 0.9rem;
            transition: background-color 0.3s ease;
            gap: 8px;
            white-space: nowrap;
        }
        .report-button:hover {
            background-color: #c82333;
        }

        .container {
            max-width: 700px;
            margin: 2rem auto;
            padding: 2.5rem;
            background: var(--white);
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            text-align: center;
        }

        h2 {
            font-size: 2rem;
            color: var(--text-color);
            margin-bottom: 1rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        h2 i {
            color: var(--primary-color);
        }

        p.review-info {
            font-size: 1.1rem;
            color: var(--light-text-color);
            margin-bottom: 2rem;
        }

        form {
            display: flex;
            flex-direction: column;
            gap: 15px;
            text-align: left;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: var(--text-color);
            font-size: 1rem;
        }

        textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 1rem;
            font-family: 'Kanit', sans-serif;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
            resize: vertical;
            min-height: 100px;
        }
        textarea:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(255, 193, 7, 0.2);
            outline: none;
        }

        button[type="submit"] {
            margin-top: 20px;
            padding: 12px 20px;
            background: var(--primary-color);
            color: var(--white);
            font-size: 1.1rem;
            font-weight: 600;
            width: 100%;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.3s ease, transform 0.2s ease, box-shadow 0.3s ease;
            box-shadow: 0 4px 10px rgba(0,0,0,0.08);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        button[type="submit"]:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0,0,0,0.12);
        }

        .back-button {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 20px;
            background-color: #6c757d;
            color: var(--white);
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: background-color 0.3s ease;
        }
        .back-button:hover {
            background-color: #5a6268;
        }

        .message {
            margin-bottom: 1.5rem;
            padding: 15px;
            border-radius: 8px;
            font-weight: 500;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .star-rating {
            display: flex;
            justify-content: center;
            direction: rtl; /* For right-to-left alignment */
            margin-bottom: 1rem;
        }
        .star-rating input[type="radio"] {
            display: none;
        }
        .star-rating label {
            font-size: 2.5rem;
            color: #ccc; /* Base color for empty stars */
            cursor: pointer;
            transition: color 0.2s ease;
            padding: 0 5px;
        }
        .star-rating input[type="radio"]:checked ~ label,
        .star-rating label:hover,
        .star-rating label:hover ~ label {
            color: var(--primary-color);
        }

        /* Mobile adjustments */
        @media (max-width: 768px) {
            .container {
                padding: 1.5rem;
                margin: 1.5rem auto;
            }
            .star-rating label {
                font-size: 2rem;
            }
            p.review-info {
                font-size: 1rem;
            }
            header nav {
                width: 100%;
                justify-content: center;
                margin-left: 0;
            }
            header .header-right {
                width: 100%;
                justify-content: center;
                margin-top: 10px;
            }
        }
    </style>
</head>
<body>

<header>
    <div class="header-left">
        <strong><i class="fas fa-handshake"></i> SWAPSPACE</strong>
        <nav>
            <a href="main.php"><i class="fas fa-home"></i> หน้าแรก</a>
            <a href="my_items.php"><i class="fas fa-box-open"></i> ของฉัน</a>
            <a href="exchange_requests_received.php" class="nav-link-with-badge">
                <i class="fas fa-inbox"></i> คำขอที่คุณได้รับ
                <?php if ($unreadRequestsCount > 0): ?>
                    <span class="notification-badge"><?php echo $unreadRequestsCount; ?></span>
                <?php endif; ?>
            </a>
            <a href="exchange_requests_sent.php"><i class="fas fa-paper-plane"></i> คำขอที่คุณส่ง</a>
            <a href="my_reviews.php"><i class="fas fa-star-half-alt"></i> รีวิวของฉัน</a>
            <a href="reviews_received.php"><i class="fas fa-star"></i> รีวิวที่คุณได้รับ</a>
            <a href="edit_profile.php"><i class="fas fa-user-cog"></i> จัดการโปรไฟล์</a>
            <a href="report_issue.php" class="report-button">
                <i class="fas fa-exclamation-triangle"></i> แจ้งปัญหา
            </a>
        </nav>
    </div>
    <div class="header-right">
        <img src="<?php echo htmlspecialchars($profileImageHeader); ?>" alt="รูปโปรไฟล์">
        <span>สวัสดี, <?php echo htmlspecialchars($username); ?></span> |
        <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> ออกจากระบบ</a>
    </div>
</header>

<div class="container">
    <h2><i class="fas fa-star"></i> ให้คะแนนและแสดงความคิดเห็น</h2>

    <?php if (!empty($message)): ?>
        <p class="message <?php echo $messageType; ?>">
            <?php echo htmlspecialchars($message); ?>
        </p>
    <?php endif; ?>

    <p class="review-info">คุณกำลังให้คะแนน "<?php echo htmlspecialchars($reviewData['reviewee_name']); ?>"<br>
    สำหรับการแลกเปลี่ยน: "<?php echo htmlspecialchars($reviewData['item_owner_name']); ?>"
    กับ "<?php echo htmlspecialchars($reviewData['item_requester_name']); ?>"</p>

    <form method="post">
        <label>ให้คะแนน (คลิกที่ดาวเพื่อเลือก)</label>
        <div class="star-rating">
            <input type="radio" id="star5" name="rating" value="5" <?php if ($reviewData['rating'] == 5) echo 'checked'; ?>><label for="star5" title="5 ดาว"><i class="fas fa-star"></i></label>
            <input type="radio" id="star4" name="rating" value="4" <?php if ($reviewData['rating'] == 4) echo 'checked'; ?>><label for="star4" title="4 ดาว"><i class="fas fa-star"></i></label>
            <input type="radio" id="star3" name="rating" value="3" <?php if ($reviewData['rating'] == 3) echo 'checked'; ?>><label for="star3" title="3 ดาว"><i class="fas fa-star"></i></label>
            <input type="radio" id="star2" name="rating" value="2" <?php if ($reviewData['rating'] == 2) echo 'checked'; ?>><label for="star2" title="2 ดาว"><i class="fas fa-star"></i></label>
            <input type="radio" id="star1" name="rating" value="1" <?php if ($reviewData['rating'] == 1) echo 'checked'; ?>><label for="star1" title="1 ดาว"><i class="fas fa-star"></i></label>
        </div>

        <label for="comment">ความคิดเห็น (ไม่บังคับ)</label>
        <textarea name="comment" id="comment" rows="4" placeholder="เขียนความคิดเห็นของคุณ..."><?php echo htmlspecialchars(isset($reviewData['comment']) ? $reviewData['comment'] : ''); ?></textarea>

        <button type="submit" name="save_rating">
            <i class="fas fa-save"></i> บันทึกคะแนน
        </button>
    </form>

    <a href="my_reviews.php" class="back-button">
        <i class="fas fa-arrow-left"></i> กลับไปหน้าการรีวิวของฉัน
    </a>
</div>

<script>
    // Simplified script for star rating interaction
    const starRating = document.querySelector('.star-rating');

    starRating.addEventListener('click', (e) => {
        if (e.target.tagName === 'LABEL' || e.target.closest('LABEL')) {
            const label = e.target.closest('LABEL');
            const radio = document.getElementById(label.getAttribute('for'));
            radio.checked = true;
        }
    });
</script>

</body>
</html>

<?php $conn->close(); ?>
