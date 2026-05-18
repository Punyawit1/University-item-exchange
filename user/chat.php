<?php
// chat.php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.html");
    exit();
}

// เชื่อมต่อฐานข้อมูล
$conn = new mysqli("202.28.34.205", "65011211056", "65011211056", "db65011211056");
if ($conn->connect_error) {
    die("เชื่อมต่อฐานข้อมูลล้มเหลว: " . $conn->connect_error);
}

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];

// ดึงรูปโปรไฟล์ของผู้ใช้จากฐานข้อมูล
$stmt = $conn->prepare("SELECT profile_image, score FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result_profile = $stmt->get_result();
$user = $result_profile->fetch_assoc();
$stmt->close();

// แก้ไข path รูปภาพโปรไฟล์ให้ถูกต้อง
$profileImage = (!empty($user['profile_image']) && file_exists("../uploads/profile/" . $user['profile_image']))
    ? "../uploads/profile/" . $user['profile_image']
    : "../uploads/profile/default.png";
    
$userScore = $user['score'];

// ดึงสถิติของผู้ใช้สำหรับ dropdown
$itemCountQuery = $conn->query("SELECT COUNT(*) as count FROM items WHERE owner_id = $userId");
$itemCount = $itemCountQuery->fetch_assoc()['count'];

$exchangeCountQuery = $conn->query("SELECT COUNT(*) as count FROM exchange_requests WHERE requester_id = $userId AND status = 'completed'");
$exchangeCount = $exchangeCountQuery->fetch_assoc()['count'];

// ดึงจำนวนคำขอที่ยังไม่อ่าน
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

$requestId = isset($_GET['request_id']) ? intval($_GET['request_id']) : 0;

// ปรับปรุงการตรวจสอบสิทธิ์การเข้าถึงห้องแชท
$stmtCheck = $conn->prepare("
    SELECT er.id 
    FROM exchange_requests er 
    JOIN items i1 ON er.item_owner_id = i1.id
    WHERE er.id = ? 
    AND (i1.owner_id = ? OR er.requester_id = ?)
");
$stmtCheck->bind_param("iii", $requestId, $userId, $userId);
$stmtCheck->execute();
$resCheck = $stmtCheck->get_result();
if ($resCheck->num_rows === 0) {
    die("ไม่มีสิทธิ์เข้าถึงห้องสนทนานี้");
}
$stmtCheck->close();

// บันทึกข้อความที่ส่งมาผ่าน POST
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['message'])) {
    $msg = trim($_POST['message']);
    if ($msg !== "") {
        $stmtSend = $conn->prepare("INSERT INTO messages (request_id, sender_id, message) VALUES (?, ?, ?)");
        $stmtSend->bind_param("iis", $requestId, $userId, $msg);
        $stmtSend->execute();
        $stmtSend->close();
        exit(); // ส่งผ่าน AJAX
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ห้องแชท</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

    <style>
        :root {
            --primary-color: #FFC107;
            --primary-dark: #FFA000;
            --primary-light: #FFD54F;
            --text-color: #333;
            --text-dark: #333;
            --text-muted: #666;
            --light-text-color: #666;
            --border-color: #e0e0e0;
            --bg-light: #f5f5f5;
            --white: #fff;
            --shadow-light: rgba(0,0,0,0.08);
            --shadow-medium: rgba(0,0,0,0.12);
            --success-color: #28a745;
            --danger-color: #dc3545;
            --exchange-tag-bg: #4CAF50;
            --giveaway-tag-bg: #2196F3;
            --sell-tag-bg: #FF9800;
            --primary: #FFC107;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Sarabun', sans-serif;
            background: var(--bg-light);
            color: var(--text-color);
            line-height: 1.6;
            margin: 0;
            padding-top: 80px;
        }

        .chat-page-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: calc(100vh - 80px);
            padding: 20px;
        }

        header {
            background: var(--primary-color);
            color: var(--white);
            padding: 1rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: nowrap;
            box-shadow: 0 2px 8px var(--shadow-medium);
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 1000;
            min-height: 70px;
        }

        header .header-left {
            display: flex;
            align-items: center;
            gap: 25px;
            flex-wrap: nowrap;
            min-width: fit-content;
        }

        header .header-left strong {
            font-size: 1.5rem;
            font-weight: 700;
            white-space: nowrap;
            min-width: fit-content;
        }

        header nav {
            display: flex;
            flex-wrap: nowrap;
            gap: 8px;
            margin-left: 0;
            align-items: center;
        }

        header nav a {
            color: var(--white);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.85rem;
            padding: 8px 12px;
            border-radius: 6px;
            position: relative;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(15px);
            margin: 0 1px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
            white-space: nowrap;
            min-height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        header nav a:hover {
            color: var(--white);
            background: rgba(255, 255, 255, 0.25);
            border-color: rgba(255, 255, 255, 0.5);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        header nav a.active {
            background: rgba(255, 255, 255, 0.35);
            border-color: rgba(255, 255, 255, 0.7);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.25);
            transform: translateY(-1px);
        }

        header nav a.active::after {
            width: 70%;
        }

        header nav a::after {
            content: '';
            position: absolute;
            width: 0;
            height: 3px;
            bottom: 6px;
            left: 50%;
            background-color: var(--white);
            transition: all 0.3s ease-out;
            transform: translateX(-50%);
            border-radius: 2px;
        }

        header nav a:hover::after {
            width: 70%;
        }

        header .header-right {
            display: flex;
            align-items: center;
            gap: 10px;
            white-space: nowrap;
            margin-top: 0;
            min-width: fit-content;
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

        /* Profile Dropdown Styles */
        .profile-dropdown {
            position: relative;
            display: inline-block;
        }

        .profile-button {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: var(--white);
            cursor: pointer;
            padding: 8px 12px;
            border-radius: 6px;
            transition: all 0.3s ease;
            font-size: 0.85rem;
            font-weight: 600;
            backdrop-filter: blur(15px);
            margin: 0 1px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
            white-space: nowrap;
            min-height: 36px;
        }

        .profile-button:hover {
            background: rgba(255, 255, 255, 0.25);
            border-color: rgba(255, 255, 255, 0.5);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .profile-button img {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--white);
        }

        .profile-button i {
            font-size: 0.8rem;
            transition: transform 0.3s ease;
        }

        .profile-dropdown.active .profile-button i {
            transform: rotate(180deg);
        }

        .profile-dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            background-color: var(--white);
            min-width: 250px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.2);
            border-radius: 8px;
            z-index: 1000;
            margin-top: 5px;
            overflow: hidden;
        }

        .profile-dropdown.active .profile-dropdown-content {
            display: block;
        }

        .profile-info {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px;
            background-color: #f8f9fa;
        }

        .profile-info img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #dee2e6;
        }

        .profile-name {
            font-weight: 600;
            font-size: 1rem;
            color: var(--text-dark);
        }

        .profile-score {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-top: 2px;
        }

        .profile-stats {
            display: flex;
            flex-direction: column;
            gap: 4px;
            margin-top: 8px;
        }

        .stat-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        .stat-item i {
            width: 12px;
            font-size: 0.75rem;
        }

        .dropdown-divider {
            height: 1px;
            background-color: #dee2e6;
            margin: 0;
        }

        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 15px;
            color: var(--text-dark);
            text-decoration: none;
            transition: background-color 0.3s ease;
            font-size: 0.9rem;
        }

        .dropdown-item:hover {
            background-color: #f8f9fa;
            color: var(--primary);
        }

        .dropdown-item i {
            width: 16px;
            text-align: center;
        }

        .logout-item {
            color: #dc3545 !important;
        }

        .logout-item:hover {
            background-color: #f8d7da !important;
            color: #721c24 !important;
        }

        .item-count-badge {
            background-color: var(--primary);
            color: white;
            font-size: 0.7rem;
            padding: 2px 6px;
            border-radius: 10px;
            margin-left: auto;
        }

        .notification-badge-small {
            background-color: #dc3545;
            color: white;
            font-size: 0.7rem;
            padding: 2px 6px;
            border-radius: 10px;
            margin-left: auto;
        }

        .add-item {
            background-color: #f8f9fa;
            color: var(--primary) !important;
            font-weight: 600;
        }

        .add-item:hover {
            background-color: var(--primary) !important;
            color: white !important;
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

        .nav-link-with-badge {
            position: relative;
            display: inline-block;
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -10px;
            background-color: #dc3545;
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

        .back-button {
            align-self: flex-start;
            margin-bottom: 20px;
            padding: 10px 20px;
            background: #6c757d;
            color: #fff;
            border: none;
            border-radius: 20px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .back-button:hover {
            background: #5a6268;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            transform: translateY(-1px);
        }

        .chat-container {
            width: 100%;
            max-width: 800px;
            display: flex;
            flex-direction: column;
            height: calc(100vh - 120px);
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-top: 20px;
        }

        .chat-header {
            background: var(--primary-color);
            color: white;
            padding: 20px;
            font-size: 20px;
            font-weight: 600;
            text-align: center;
        }

        .chat-messages {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            background: #f0f2f5;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .chat-input {
            display: flex;
            padding: 15px 20px;
            border-top: 1px solid #e0e0e0;
            background: #fff;
        }

        .chat-input input {
            flex: 1;
            padding: 12px 20px;
            border: 1px solid #e0e0e0;
            border-radius: 25px;
            font-size: 16px;
            background: #f8f8f8;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }

        .chat-input input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 5px rgba(255, 193, 7, 0.5);
        }

        .chat-input button {
            background: var(--primary-color);
            border: none;
            color: white;
            padding: 0 15px;
            margin-left: 8px;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            cursor: pointer;
            font-size: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.3s ease, transform 0.2s ease;
        }

        .chat-input button:hover {
            background: var(--primary-dark);
            transform: scale(1.05);
        }

        .chat-input i {
            pointer-events: none;
        }

        .message {
            position: relative;
            max-width: 75%;
            padding: 12px 18px;
            border-radius: 18px;
            line-height: 1.6;
            word-wrap: break-word;
            font-size: 16px;
            font-weight: 500;
        }

        .message.own {
            background: var(--primary-color);
            color: #000;
            align-self: flex-end;
            border-bottom-right-radius: 2px;
        }

        .message.other {
            background: #e0e0e0;
            color: #000;
            align-self: flex-start;
            border-bottom-left-radius: 2px;
        }

        .msg-time {
            font-size: 12px;
            color: #555;
            margin-top: 5px;
            display: block;
        }

        .message.own .msg-time {
            color: rgba(0, 0, 0, 0.6);
        }

        /* Modal styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 2000;
        }

        .modal-content {
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }

        .modal-content #map {
            height: 400px;
            width: 100%;
            margin-bottom: 15px;
        }

        .modal-content button {
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-family: 'Sarabun', sans-serif;
            font-size: 16px;
            margin-right: 10px;
        }

        #confirm-location {
            background-color: var(--success-color);
            color: white;
        }

        #close-modal {
            background-color: var(--danger-color);
            color: white;
        }

        .map-preview {
            width: 250px;
            height: 150px;
            border-radius: 8px;
            border: 1px solid #ccc;
            margin-top: 5px;
        }

        .map-link {
            display: block;
            margin-top: 8px;
            color: #007bff;
            text-decoration: underline;
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
            <a href="leaderboard.php"><i class="fas fa-trophy"></i> คะแนนผู้ใช้</a>
            <a href="report_issue.php" class="report-button">
                <i class="fas fa-exclamation-triangle"></i> แจ้งปัญหา
            </a>
            
            <!-- Profile Dropdown -->
            <div class="profile-dropdown">
                <button class="profile-button" onclick="toggleProfileDropdown()">
                    <img src="<?php echo htmlspecialchars($profileImage); ?>" alt="รูปโปรไฟล์">
                    <span><?php echo htmlspecialchars($username); ?></span>
                    <i class="fas fa-chevron-down"></i>
                </button>
                <div class="profile-dropdown-content" id="profileDropdown">
                    <div class="profile-info">
                        <img src="<?php echo htmlspecialchars($profileImage); ?>" alt="รูปโปรไฟล์">
                        <div>
                            <div class="profile-name"><?php echo htmlspecialchars($username); ?></div>
                            <div class="profile-score">คะแนน: <?php echo $userScore; ?></div>
                            <div class="profile-stats">
                                <span class="stat-item">
                                    <i class="fas fa-box"></i> <?php echo $itemCount; ?> รายการ
                                </span>
                                <span class="stat-item">
                                    <i class="fas fa-exchange-alt"></i> <?php echo $exchangeCount; ?> แลกเปลี่ยน
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="dropdown-divider"></div>
                    <a href="edit_profile.php" class="dropdown-item">
                        <i class="fas fa-user-cog"></i> จัดการโปรไฟล์
                    </a>
                    <a href="my_items.php" class="dropdown-item">
                        <i class="fas fa-box-open"></i> ของฉัน
                        <?php if ($itemCount > 0): ?>
                            <span class="item-count-badge"><?php echo $itemCount; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="exchange_requests_received.php" class="dropdown-item">
                        <i class="fas fa-inbox"></i> คำขอที่ได้รับ
                        <?php if ($unreadRequestsCount > 0): ?>
                            <span class="notification-badge-small"><?php echo $unreadRequestsCount; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="exchange_requests_sent.php" class="dropdown-item">
                        <i class="fas fa-paper-plane"></i> คำขอที่ส่ง
                    </a>
                    <a href="my_reviews.php" class="dropdown-item">
                        <i class="fas fa-star-half-alt"></i> รีวิวของฉัน
                    </a>
                    <a href="reviews_received.php" class="dropdown-item">
                        <i class="fas fa-star"></i> รีวิวที่ได้รับ
                    </a>
                    <a href="leaderboard.php" class="dropdown-item">
                        <i class="fas fa-trophy"></i> คะแนนผู้ใช้
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="add_item.php" class="dropdown-item add-item">
                        <i class="fas fa-plus-circle"></i> เพิ่มของใหม่
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="../logout.php" class="dropdown-item logout-item">
                        <i class="fas fa-sign-out-alt"></i> ออกจากระบบ
                    </a>
                </div>
            </div>
        </nav>
    </div>
</header>

<div class="chat-page-container">
    <a href="#" onclick="history.back()" class="back-button">
        <i class="fas fa-arrow-left"></i> ย้อนกลับ
    </a>

    <div class="chat-container">
        <div class="chat-header">
            <i class="fas fa-comments"></i> ห้องแชทแลกเปลี่ยน
        </div>
        <div id="chat-messages" class="chat-messages">
            </div>
        <form id="chat-form" class="chat-input">
            <input type="text" id="message" name="message" placeholder="พิมพ์ข้อความ..." autocomplete="off">
            
            <input type="file" id="image-input" accept="image/*" style="display:none;">
            <button type="button" id="send-image" title="ส่งรูป">
                <i class="fas fa-image"></i>
            </button>
            
            <button type="button" id="send-location" title="ปักหมุดตำแหน่ง">
                <i class="fas fa-map-marker-alt"></i>
            </button>

            <button type="submit" title="ส่ง">
                <i class="fas fa-paper-plane"></i>
            </button>
        </form>
    </div>

    <div id="map-modal" class="modal-overlay" style="display:none;">
        <div class="modal-content">
            <h4>คลิกบนแผนที่เพื่อปักหมุด</h4>
            <div id="map"></div>
            <button id="confirm-location" disabled>ยืนยันตำแหน่ง</button>
            <button id="close-modal">ยกเลิก</button>
        </div>
    </div>
</div>

<script>
    const userId = <?= json_encode($userId) ?>;
    const requestId = <?= json_encode($requestId) ?>;
    const chatBox = document.getElementById("chat-messages");
    const form = document.getElementById("chat-form");
    const messageInput = document.getElementById("message");

    // ✨ ส่วนของแผนที่ (Map)
    const mapModal = document.getElementById('map-modal');
    const sendLocationBtn = document.getElementById('send-location');
    const confirmLocationBtn = document.getElementById('confirm-location');
    const closeModalBtn = document.getElementById('close-modal');
    let map, marker, selectedCoords;

    function escapeHtml(str) {
        return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }

    function linkify(text) {
        const safeText = escapeHtml(text);
        const urlRegex = /(https?:\/\/[^\s]+)/g;
        return safeText.replace(urlRegex, url => `<a href="${url}" target="_blank" rel="noopener noreferrer">${url}</a>`);
    }

    // ✨ ฟังก์ชันสำหรับส่งข้อความ (แยกออกมาเพื่อใช้ซ้ำ)
    function sendMessage(messageText) {
        if (messageText.trim() === "") return;

        const formData = new FormData();
        formData.append('message', messageText);

        fetch(`chat.php?request_id=${requestId}`, {
            method: "POST",
            body: formData,
            headers: { "X-Requested-With": "XMLHttpRequest" }
        })
        .then(() => {
            messageInput.value = "";
            loadMessages();
        })
        .catch(error => console.error('Error sending message:', error));
    }


    let lastMessageId = 0;

    function appendMessage(msg) {
        if (msg.id <= lastMessageId) return;

        const msgDiv = document.createElement("div");
        msgDiv.className = "message " + (msg.sender_id == userId ? "own" : "other");
        let content = "";
        
        // ✨ ตรวจสอบและแสดงผลข้อความที่เป็นตำแหน่ง
        if (msg.message.startsWith('[MAP]')) {
            const coords = msg.message.replace('[MAP]', '').split(',');
            const lat = parseFloat(coords[0]);
            const lng = parseFloat(coords[1]);
            const mapId = `map-${msg.id}`;

            content = `
                <div>
                    <strong>📍 ตำแหน่งที่แชร์</strong>
                    <div id="${mapId}" class="map-preview"></div>
                    <a href="https://www.google.com/maps?q=${lat},${lng}" target="_blank" rel="noopener noreferrer" class="map-link">
                        🔗 เปิดใน Google Maps
                    </a>
                </div>`;
                
            // หน่วงเวลาเล็กน้อยเพื่อให้ Element ถูกสร้างใน DOM ก่อน แล้วจึงสร้างแผนที่
            setTimeout(() => {
                const previewMap = L.map(mapId, {
                    dragging: false,
                    touchZoom: false,
                    scrollWheelZoom: false,
                    doubleClickZoom: false,
                    boxZoom: false,
                    keyboard: false,
                    zoomControl: false,
                }).setView([lat, lng], 15);

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                }).addTo(previewMap);

                L.marker([lat, lng]).addTo(previewMap);
            }, 10);

        } else if (msg.message.match(/\.(jpeg|jpg|png|gif)$/i)) {
            content = `<div><img src="../${escapeHtml(msg.message)}" style="max-width:200px; border-radius:8px;"></div>`;
        } else {
            content = `<div>${linkify(msg.message)}</div>`;
        }

        if (msg.sender_id == userId) {
            content += `<button class="delete-btn" data-id="${msg.id}" style="margin-top:5px; font-size:12px; color:#fff; background:#dc3545; border:none; border-radius:4px; padding:2px 5px; cursor:pointer;">ลบ</button>`;
        }

        content += `<div class="msg-time">${msg.sent_at}</div>`;
        msgDiv.innerHTML = content;
        chatBox.appendChild(msgDiv);

        // จัดการปุ่มลบ
        const deleteBtn = msgDiv.querySelector(".delete-btn");
        if (deleteBtn) {
            deleteBtn.addEventListener("click", function() {
                const msgId = this.dataset.id;
                if (confirm("คุณต้องการลบข้อความนี้หรือไม่?")) {
                    fetch("delete_message.php", {
                        method: "POST",
                        body: new URLSearchParams({ message_id: msgId }),
                        headers: { "X-Requested-With": "XMLHttpRequest" }
                    }).then(res => res.json()).then(data => {
                        if (data.success) { msgDiv.remove(); } 
                        else { alert(data.error || "ลบข้อความไม่สำเร็จ"); }
                    });
                }
            });
        }
        
        chatBox.scrollTop = chatBox.scrollHeight;
        lastMessageId = msg.id;
    }

    function loadMessages() {
        fetch(`fetch_messages.php?request_id=${requestId}&after_id=${lastMessageId}`)
            .then(res => res.json())
            .then(data => {
                data.forEach(msg => appendMessage(msg));
            })
            .catch(error => console.error('Error loading messages:', error));
    }

    // --- ✨ Event Listeners และฟังก์ชันสำหรับแผนที่ ---
    
    // เปิด Modal แผนที่
    sendLocationBtn.addEventListener('click', () => {
        mapModal.style.display = 'flex';
        // หน่วงเวลาเพื่อให้ Modal แสดงผลเสร็จก่อนแล้วจึงสร้างแผนที่
        setTimeout(() => {
            if (!map) { // สร้างแผนที่ครั้งแรกเท่านั้น
                map = L.map('map').setView([16.244178543683713, 103.24927220104311], 13);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

                // พยายามหาตำแหน่งปัจจุบันของผู้ใช้
                navigator.geolocation.getCurrentPosition(position => {
                    const userPos = [position.coords.latitude, position.coords.longitude];
                    map.setView(userPos, 15);
                });

                // Event เมื่อคลิกบนแผนที่
                map.on('click', (e) => {
                    selectedCoords = e.latlng;
                    if (!marker) {
                        marker = L.marker(selectedCoords).addTo(map);
                    } else {
                        marker.setLatLng(selectedCoords);
                    }
                    confirmLocationBtn.disabled = false;
                });
            }
            map.invalidateSize(); // สั่งให้แผนที่วาดตัวเองใหม่ให้พอดีกับ container
        }, 100);
    });

    // ปิด Modal
    closeModalBtn.addEventListener('click', () => {
        mapModal.style.display = 'none';
    });

    // ยืนยันและส่งตำแหน่ง
    confirmLocationBtn.addEventListener('click', () => {
        if (selectedCoords) {
            const locationMsg = `[MAP]${selectedCoords.lat},${selectedCoords.lng}`;
            sendMessage(locationMsg); // ใช้ฟังก์ชันส่งข้อความ
            mapModal.style.display = 'none';
        }
    });

    // Event Listener สำหรับฟอร์มส่งข้อความ (เหมือนเดิม แต่เรียกใช้ sendMessage)
    form.addEventListener("submit", function(e) {
        e.preventDefault();
        sendMessage(messageInput.value);
    });

    // การทำงานอื่นๆ (เหมือนเดิม)
    document.getElementById("send-image").addEventListener("click", () => document.getElementById("image-input").click());
    document.getElementById("image-input").addEventListener("change", function() {
        const file = this.files[0];
        if (!file) return;
        const formData = new FormData();
        formData.append("image", file);
        formData.append("request_id", requestId);
        fetch("upload_image.php", { method: "POST", body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) { loadMessages(); } 
                else { alert("อัปโหลดรูปไม่สำเร็จ: " + data.error); }
            });
    });

    // เริ่มโหลดข้อความ
    loadMessages();
    setInterval(loadMessages, 3000);
    
    // Profile Dropdown functionality
    function toggleProfileDropdown() {
        const dropdown = document.querySelector('.profile-dropdown');
        dropdown.classList.toggle('active');
    }

    // Close dropdown when clicking outside
    document.addEventListener('click', function(event) {
        const dropdown = document.querySelector('.profile-dropdown');
        if (!dropdown.contains(event.target)) {
            dropdown.classList.remove('active');
        }
    });
    
    // Make toggleProfileDropdown globally accessible
    window.toggleProfileDropdown = toggleProfileDropdown;
</script>
</body>
</html>
<?php
$conn->close();
?>