<?php
// โค้ด PHP สำหรับ Header และการเตรียมข้อมูล
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.html");
    exit();
}

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];

$conn = new mysqli("localhost", "root", "", "item_exchange");
if ($conn->connect_error) {
    die("เชื่อมต่อฐานข้อมูลล้มเหลว: " . $conn->connect_error);
}

// ดึงรูปโปรไฟล์ของผู้ใช้
$stmt = $conn->prepare("SELECT profile_image FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result_profile = $stmt->get_result();
$user = $result_profile->fetch_assoc();
$stmt->close();

$profileImage = (!empty($user['profile_image']) && file_exists("../uploads/profile/" . $user['profile_image']))
    ? "../uploads/profile/" . htmlspecialchars($user['profile_image'])
    : "../uploads/profile/default.png";

// ดึงคะแนนผู้ใช้
$stmt_score = $conn->prepare("SELECT score FROM users WHERE id = ?");
$stmt_score->bind_param("i", $userId);
$stmt_score->execute();
$result_score = $stmt_score->get_result();
$userScore = $result_score->fetch_assoc()['score'];
$stmt_score->close();

// ดึงสถิติของผู้ใช้สำหรับ dropdown
$itemCountQuery = $conn->query("SELECT COUNT(*) as count FROM items WHERE owner_id = $userId");
$itemCount = $itemCountQuery->fetch_assoc()['count'];

$exchangeCountQuery = $conn->query("SELECT COUNT(*) as count FROM exchange_requests WHERE requester_id = $userId AND status = 'completed'");
$exchangeCount = $exchangeCountQuery->fetch_assoc()['count'];

// ดึงจำนวนคำขอแลกเปลี่ยนที่ยังไม่ได้ดู (อยู่ในสถานะ pending)
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

// ลบสิ่งของ
if (isset($_GET['delete_id'])) {
    $deleteId = intval($_GET['delete_id']);

    $stmtCheck = $conn->prepare("SELECT id FROM items WHERE id = ? AND owner_id = ?");
    $stmtCheck->bind_param("ii", $deleteId, $userId);
    $stmtCheck->execute();
    $resultCheck = $stmtCheck->get_result();

    if ($resultCheck->num_rows > 0) {
        // ลบรูปภาพและไฟล์ภาพ
        $stmtImgs = $conn->prepare("SELECT image FROM item_images WHERE item_id = ?");
        $stmtImgs->bind_param("i", $deleteId);
        $stmtImgs->execute();
        $resultImgs = $stmtImgs->get_result();

        while ($img = $resultImgs->fetch_assoc()) {
            $filePath = "../uploads/item/" . $img['image'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
        $stmtImgs->close();

        // ลบข้อมูลรูปภาพ
        $stmtDelImgs = $conn->prepare("DELETE FROM item_images WHERE item_id = ?");
        $stmtDelImgs->bind_param("i", $deleteId);
        $stmtDelImgs->execute();
        $stmtDelImgs->close();

        // ลบข้อมูล exchange_requests ทั้ง 2 ฟิลด์ที่เชื่อมกับ items.id
        $stmtDelRequestsOwner = $conn->prepare("DELETE FROM exchange_requests WHERE item_owner_id = ?");
        $stmtDelRequestsOwner->bind_param("i", $deleteId);
        $stmtDelRequestsOwner->execute();
        $stmtDelRequestsOwner->close();

        $stmtDelRequestsRequest = $conn->prepare("DELETE FROM exchange_requests WHERE item_request_id = ?");
        $stmtDelRequestsRequest->bind_param("i", $deleteId);
        $stmtDelRequestsRequest->execute();
        $stmtDelRequestsRequest->close();

        // ลบรายการสิ่งของ
        $stmtDelItem = $conn->prepare("DELETE FROM items WHERE id = ?");
        $stmtDelItem->bind_param("i", $deleteId);
        $stmtDelItem->execute();
        $stmtDelItem->close();

        header("Location: my_items.php?deleted=1");
        exit();
    } else {
        // หากผู้ใช้พยายามลบสิ่งของที่ไม่ได้เป็นของตนเอง
        header("Location: my_items.php?deleted=0");
        exit();
    }
}

// ดึงรายการของผู้ใช้ พร้อมดึงรูปภาพแรกของแต่ละรายการ
// แก้ไข: เพิ่มเงื่อนไขเพื่อไม่แสดงสิ่งของที่แลกเปลี่ยนสำเร็จแล้ว
$sql = "
    SELECT i.id, i.name, i.category, i.created_at, i.is_approved,
           (SELECT image FROM item_images WHERE item_id = i.id LIMIT 1) as main_image
    FROM items i
    WHERE i.owner_id = ?
    AND i.id NOT IN (
        SELECT item_owner_id FROM exchange_requests WHERE status = 'accepted'
    )
    AND i.id NOT IN (
        SELECT item_request_id FROM exchange_requests WHERE status = 'accepted'
    )
    ORDER BY i.created_at DESC
";

$stmtItems = $conn->prepare($sql);
$stmtItems->bind_param("i", $userId);
$stmtItems->execute();
$resultItems = $stmtItems->get_result();

function getItemImagePath($filename) {
    $filepath = "../uploads/item/" . $filename;
    return (!empty($filename) && file_exists($filepath)) ? $filepath : "../uploads/item/default.png";
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>รายการสิ่งของของฉัน | แลกของ</title>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
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
            --info-color: #17a2b8;
            --primary: #FFC107;
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

        /* Profile Dropdown Styles */
        .profile-dropdown {
            position: relative;
            display: inline-block;
        }

        .profile-button {
            display: flex;
            align-items: center;
            gap: 8px;
            background: none;
            border: none;
            color: var(--white);
            cursor: pointer;
            padding: 5px 10px;
            border-radius: 6px;
            transition: background-color 0.3s ease;
            font-size: 0.95rem;
            font-weight: 500;
        }

        .profile-button:hover {
            background-color: rgba(255, 255, 255, 0.1);
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
            max-width: 1200px;
            margin: 2rem auto;
            padding: 2.5rem;
            background: var(--white);
            border-radius: 12px;
            box-shadow: 0 4px 15px var(--shadow-light);
        }

        h2 {
            font-size: 2rem;
            color: var(--text-color);
            margin-bottom: 0.5rem;
            font-weight: 700;
        }

        p {
            font-size: 1rem;
            color: var(--light-text-color);
            margin-bottom: 2rem;
        }

        .add-item-button {
            display: inline-flex;
            align-items: center;
            padding: 12px 20px;
            background: var(--success-color);
            color: var(--white);
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
            transition: background 0.3s ease, transform 0.2s ease;
            gap: 8px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .add-item-button:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .items-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.5rem;
            /* แก้ไข: เพิ่มโค้ดนี้เพื่อไม่ให้ไอเทมขยายเต็มหน้าจอเมื่อมีเพียงชิ้นเดียว */
            justify-content: start;
        }

        .item-card {
            background: var(--white);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            overflow: hidden;
            text-align: center;
            box-shadow: 0 2px 10px var(--shadow-light);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
            display: flex;
            flex-direction: column;
        }

        .item-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 15px var(--shadow-medium);
        }

        .item-card-link {
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            flex-grow: 1;
        }

        /* ปรับขนาดรูปภาพในรายการสิ่งของของฉันให้คงที่ */
        .item-card img {
            width: 100%; /* ให้รูปภาพมีความกว้างเต็มการ์ด */
            height: 200px;
            object-fit: cover;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 0.8rem;
        }

        .item-card-content {
            padding: 0 0.8rem 0.8rem;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .item-card h4 {
            margin: 0 0 0.5rem;
            font-size: 1.15rem;
            color: var(--text-color);
            word-break: break-word;
            line-height: 1.3;
        }

        .item-card p {
            margin: 0;
            font-size: 0.9rem;
            color: var(--light-text-color);
            line-height: 1.4;
            margin-bottom: 0.5rem;
        }
        .item-card small {
            display: block;
            margin-top: 0.5rem;
            font-size: 0.8rem;
            color: #999;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.85rem;
            font-weight: 500;
            margin-top: 5px;
        }

        .status-approved {
            background-color: #e6ffe6;
            color: var(--success-color);
        }

        .status-pending {
            background-color: #fff3cd;
            color: var(--info-color);
        }

        .delete-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background: var(--danger-color);
            color: var(--white);
            border: none;
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: background 0.3s ease, transform 0.2s ease;
            box-shadow: 0 2px 5px rgba(0,0,0,0.15);
        }
        .delete-btn:hover {
            background: #a71d2a;
            transform: translateY(-2px);
        }

        .no-items-message {
            text-align: center;
            padding: 3rem;
            font-size: 1.1rem;
            color: #888;
            background-color: var(--white);
            border-radius: 10px;
            box-shadow: 0 2px 10px var(--shadow-light);
            /* แก้ไข: เพื่อให้ข้อความนี้อยู่กลางเสมอเมื่อมีเพียงชิ้นเดียว */
            grid-column: 1 / -1; 
        }
        
        @media (max-width: 992px) {
            .container { padding: 2rem; }
            .items-grid { 
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                justify-content: start; /* เพิ่มโค้ดนี้ */
            }
            header nav {
                margin-top: 15px;
                margin-left: 0;
                justify-content: center;
                width: 100%;
            }
            header .header-right {
                width: 100%;
                justify-content: center;
                margin-top: 15px;
            }
            .header-left strong {
                width: 100%;
                text-align: center;
            }
            .report-button { margin-top: 10px; }
        }

        @media (max-width: 768px) {
            h2 { font-size: 1.7rem; }
            .items-grid { 
                grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
                justify-content: start; /* เพิ่มโค้ดนี้ */
            }
            .item-card img { height: 140px; }
            .add-item-button {
                padding: 10px 15px;
                font-size: 0.9rem;
            }
            .delete-btn {
                padding: 6px 10px;
                font-size: 0.8rem;
            }
        }

        @media (max-width: 576px) {
            .container {
                padding: 1.5rem;
                margin: 1.5rem auto;
            }
            header { padding: 0.8rem 1rem; }
            header nav a { font-size: 0.85rem; }
            .items-grid { grid-template-columns: 1fr; }
            .item-card img { height: 200px; }
            .report-button {
                font-size: 0.8rem;
                padding: 6px 10px;
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
            <a href="recommendations.php"><i class="fas fa-search-plus"></i> แนะนำการแลกเปลี่ยน</a>
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

<div class="container">
    <h2><i class="fas fa-boxes"></i> รายการสิ่งของของฉัน</h2>
    <p>จัดการสิ่งของที่คุณลงประกาศไว้ที่นี่</p>

    <a href="add_item.php" class="add-item-button">
        <i class="fas fa-plus"></i> เพิ่มสิ่งของใหม่
    </a>

    <div class="items-grid">
        <?php if ($resultItems->num_rows > 0): ?>
            <?php while ($item = $resultItems->fetch_assoc()): ?>
                <div class="item-card">
                    <a href="edit_item.php?id=<?php echo $item['id']; ?>" class="item-card-link">
                        <?php
                        $mainImg = getItemImagePath($item['main_image']);
                        ?>
                        <img src="<?php echo $mainImg; ?>" alt="ภาพสินค้า">
                        <div class="item-card-content">
                            <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                            <p>หมวดหมู่: **<?php echo htmlspecialchars($item['category']); ?>**</p>
                            <p>
                                สถานะ:
                                <span class="status-badge <?php echo $item['is_approved'] ? 'status-approved' : 'status-pending'; ?>">
                                    <?php echo $item['is_approved'] ? '✅ อนุมัติแล้ว' : '⏳ รอการอนุมัติ'; ?>
                                </span>
                            </p>
                            <small>ลงเมื่อ: <?php echo date("d/m/Y", strtotime($item['created_at'])); ?></small>
                        </div>
                    </a>
                    <a href="my_items.php?delete_id=<?php echo $item['id']; ?>" class="delete-btn" onclick="return confirm('คุณแน่ใจหรือไม่ว่าต้องการลบรายการนี้? การดำเนินการนี้ไม่สามารถย้อนกลับได้');">
                        <i class="fas fa-trash-alt"></i> ลบ
                    </a>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p class="no-items-message">
                <i class="fas fa-box-open" style="font-size: 2.5rem; color: #ccc; margin-bottom: 15px; display: block;"></i>
                คุณยังไม่มีรายการสิ่งของในระบบ ลองเพิ่มสิ่งของใหม่ดูสิ!
            </p>
        <?php endif; ?>
    </div>
</div>

<script>
    function toggleProfileDropdown() {
        const dropdown = document.querySelector('.profile-dropdown');
        dropdown.classList.toggle('active');
        
        // Debug: Log the active state
        console.log('Dropdown active:', dropdown.classList.contains('active'));
    }

    // Close dropdown when clicking outside
    document.addEventListener('click', function(event) {
        const dropdown = document.querySelector('.profile-dropdown');
        const profileButton = document.querySelector('.profile-button');
        
        // Check if the click was outside the dropdown and not on the profile button or its children
        if (!dropdown.contains(event.target)) {
            dropdown.classList.remove('active');
        }
    });

    // Prevent dropdown from closing when clicking inside the dropdown content
    document.addEventListener('DOMContentLoaded', function() {
        const dropdownContent = document.querySelector('.profile-dropdown-content');
        if (dropdownContent) {
            dropdownContent.addEventListener('click', function(event) {
                // Only prevent if clicking on non-link elements
                if (!event.target.closest('a')) {
                    event.stopPropagation();
                }
            });
        }
    });
</script>

</body>
</html>

<?php 
$stmtItems->close();
$conn->close();
?>