<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.html");
    exit();
}

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];

$conn = new mysqli("202.28.34.205", "65011211056", "65011211056", "db65011211056");
if ($conn->connect_error) {
    die("เชื่อมต่อฐานข้อมูลล้มเหลว: " . $conn->connect_error);
}

// ดึงรูปโปรไฟล์และคะแนนของผู้ใช้
$stmt = $conn->prepare("SELECT profile_image, score FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result_profile = $stmt->get_result();
$user = $result_profile->fetch_assoc();
$stmt->close();

$profileImage = (!empty($user['profile_image']) && file_exists("../uploads/profile/" . $user['profile_image']))
    ? "../uploads/profile/" . $user['profile_image']
    : "../uploads/profile/default.png";
$userScore = $user['score'];

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

// ฟังก์ชันดึงรูปภาพแรกของสินค้า
function getItemImage($conn, $itemId) {
    if (!$itemId) {
        return "../uploads/item/default.png";
    }
    $stmt = $conn->prepare("SELECT image FROM item_images WHERE item_id = ? LIMIT 1");
    $stmt->bind_param("i", $itemId);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (!empty($res['image']) && file_exists("../uploads/item/" . $res['image']))
        ? "../uploads/item/" . $res['image']
        : "../uploads/item/default.png";
}

// *** แก้ไขคำสั่ง SQL เพื่อรองรับรายการ 'แจกฟรี' และ 'ขายราคาถูก' โดยใช้ LEFT JOIN ***
$sql = "
SELECT er.id, er.status, er.receiver_confirmed, er.requester_confirmed, u.fullname AS owner_name, er.item_owner_id, i2.owner_id AS actual_owner_id,
    i1.id AS your_item_id, i1.name AS your_item_name,
    i2.id AS their_item_id, i2.name AS their_item_name
FROM exchange_requests er
LEFT JOIN items i1 ON er.item_request_id = i1.id -- ใช้ LEFT JOIN สำหรับสิ่งของของผู้ขอ
JOIN items i2 ON er.item_owner_id = i2.id -- ยังคงใช้ JOIN สำหรับสิ่งของที่ถูกขอ
JOIN users u ON i2.owner_id = u.id -- u คือเจ้าของสิ่งของที่ถูกขอ
WHERE er.requester_id = ?
ORDER BY er.created_at DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>คำขอแลกเปลี่ยนที่คุณส่ง | แลกของ</title>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <style>
        :root {
            --primary-color: #FFC107; /* Your chosen Amber color */
            --primary-dark: #FFA000;  /* Slightly darker Amber */
            --primary-light: #FFD54F; /* Lighter Amber for accents */
            --text-color: #333;
            --text-dark: #333;
            --text-muted: #666;
            --light-text-color: #666;
            --border-color: #e0e0e0;
            --bg-light: #f5f5f5;
            --white: #fff;
            --shadow-light: rgba(0,0,0,0.08);
            --shadow-medium: rgba(0,0,0,0.12);
            --danger-color: #dc3545; /* For Report button */
            --info-color: #17a2b8; /* For pending status */
            --chat-color: #007bff; /* For Chat button */
            --accepted-color: #28a745; /* For accepted status */
            --rejected-color: #dc3545; /* For rejected status */
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
            flex-wrap: nowrap;
            box-shadow: 0 2px 8px var(--shadow-medium);
            position: sticky;
            top: 0;
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

        .report-button {
            display: inline-flex;
            align-items: center;
            padding: 8px 12px;
            background: rgba(220, 53, 69, 0.9);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: var(--white);
            font-weight: 600;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            gap: 6px;
            white-space: nowrap;
            backdrop-filter: blur(15px);
            margin: 0 1px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
            min-height: 36px;
        }
        .report-button:hover {
            background: rgba(200, 35, 51, 0.95);
            border-color: rgba(255, 255, 255, 0.5);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            color: var(--white);
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
            margin-bottom: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        h2 i {
            color: var(--primary-color);
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
        

        .request-card {
            display: flex;
            flex-wrap: wrap; /* Allow wrapping on smaller screens */
            border: 1px solid var(--border-color);
            border-radius: 10px;
            margin-bottom: 1.5rem;
            padding: 1.5rem;
            gap: 1.5rem;
            align-items: center;
            background-color: var(--white);
            box-shadow: 0 2px 10px var(--shadow-light);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .request-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px var(--shadow-medium);
        }

        .request-card img {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid #ddd;
            flex-shrink: 0; /* Prevent image from shrinking */
        }

        .info {
            flex: 1;
            min-width: 250px; /* Minimum width before wrapping */
        }
        .info h4 {
            margin: 0 0 10px;
            color: var(--text-color);
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .info p {
            margin: 5px 0;
            font-size: 0.95rem;
            color: var(--light-text-color);
        }
        .info p a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }
        .info p a:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        .status-text {
            font-weight: bold;
            padding: 4px 8px;
            border-radius: 5px;
            display: inline-block;
            margin-top: 5px;
        }
        .status-pending {
            background-color: #fff3cd; /* Light yellow */
            color: var(--info-color);
        }
        .status-accepted {
            background-color: #d4edda; /* Light green */
            color: var(--accepted-color);
        }
        .status-rejected {
            background-color: #f8d7da; /* Light red */
            color: var(--rejected-color);
        }
        .status-completed { /* For completed status, if applicable */
            background-color: #e2e3e5; /* Light grey */
            color: var(--light-text-color);
        }

        .actions {
            display: flex;
            flex-direction: column; /* Changed to column for vertical stacking */
            gap: 10px;
            min-width: 150px;
        }
        .actions button {
            width: 100%;
            padding: 10px 15px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            transition: background 0.3s ease, transform 0.2s ease, box-shadow 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .actions button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        .chat { background: var(--chat-color); color: var(--white); }
        .chat:hover { background: #0056b3; }
        .rate-button { background: var(--primary-color); color: var(--text-color); }
        .rate-button:hover { background-color: var(--primary-dark); }
        .rated-button { background-color: #6c757d; color: var(--white); cursor: not-allowed; opacity: 0.8;}
        .rated-button:hover { background-color: #6c757d; transform: none; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }

        .no-requests-message {
            text-align: center;
            padding: 3rem;
            font-size: 1.1rem;
            color: #888;
            background-color: var(--white);
            border-radius: 10px;
            box-shadow: 0 2px 10px var(--shadow-light);
        }

        /* Responsive Adjustments */
        @media (max-width: 992px) {
            .container {
                padding: 2rem;
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
            .report-button {
                margin-top: 10px;
            }
        }

        @media (max-width: 768px) {
            h2 {
                font-size: 1.7rem;
            }
            .request-card {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }
            .request-card img {
                width: 100%;
                max-width: 200px; /* Limit image size on small screens */
                height: auto;
                margin-bottom: 1rem;
            }
            .info {
                min-width: unset;
                width: 100%;
            }
            .actions {
                width: 100%;
                flex-direction: row; /* Buttons side-by-side on small screens */
                justify-content: center;
                flex-wrap: wrap;
            }
            .actions button {
                width: auto; /* Let buttons size naturally */
                flex-grow: 1; /* Allow buttons to grow */
                min-width: 120px;
            }
        }

        @media (max-width: 576px) {
            .container {
                padding: 1.5rem;
                margin: 1.5rem auto;
            }
            header {
                padding: 0.8rem 1rem;
            }
            header nav a {
                font-size: 0.85rem;
            }
            .actions button {
                font-size: 0.9rem;
                padding: 8px 10px;
            }
            .report-button {
                font-size: 0.8rem;
                padding: 6px 10px;
            }
            .notification-badge {
                right: -20px;
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
            <a href="exchange_requests_sent.php" class="active"><i class="fas fa-paper-plane"></i> คำขอที่คุณส่ง</a>
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
    <h2><i class="fas fa-paper-plane"></i> คำขอแลกเปลี่ยนที่คุณส่ง</h2>

    <?php if ($result->num_rows > 0): ?>
        <?php while($row = $result->fetch_assoc()): ?>
            <div class="request-card">
                <img src="<?php echo getItemImage($conn, $row['their_item_id']); ?>" alt="ของเขา">
                <div class="info">
                    <h4>📨 คุณส่งคำขอแลกกับ <strong><?php echo htmlspecialchars($row['owner_name']); ?></strong></h4>
                    <p>
                        <strong>สิ่งของของคุณ:</strong>
                        <?php if ($row['your_item_name']): ?>
                            <a href="item_detail.php?id=<?php echo $row['your_item_id']; ?>">
                                <?php echo htmlspecialchars($row['your_item_name']); ?>
                            </a>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </p>
                    <p>
                        <strong>สิ่งของของเขา:</strong>
                        <a href="item_detail.php?id=<?php echo $row['their_item_id']; ?>">
                            <?php echo htmlspecialchars($row['their_item_name']); ?>
                        </a>
                    </p>
                    <p>
                        <strong>สถานะคำขอ:</strong>
                        <span class="status-text <?php
                            if ($row['status'] == 'pending') echo 'status-pending';
                            else if ($row['status'] == 'accepted') echo 'status-accepted';
                            else if ($row['status'] == 'rejected') echo 'status-rejected';
                            else echo 'status-completed';
                        ?>">
                            <?php echo htmlspecialchars(ucfirst($row['status'])); ?>
                        </span>
                    </p>
                </div>
                <div class="actions">
                    <?php
                    if ($row['status'] === 'accepted') {
                        $stmtCheckRating = $conn->prepare("SELECT id FROM reviews WHERE exchange_id = ? AND reviewer_id = ? AND reviewee_id = ? AND rating = 0");
                        $stmtCheckRating->bind_param("iii", $row['id'], $userId, $row['actual_owner_id']);
                        $stmtCheckRating->execute();
                        $resCheckRating = $stmtCheckRating->get_result();

                        if ($resCheckRating->num_rows > 0) {
                            $reviewToRate = $resCheckRating->fetch_assoc();
                            echo '<a href="rate_exchange.php?review_id=' . $reviewToRate['id'] . '"><button class="rate-button"><i class="fas fa-star"></i> ให้คะแนน</button></a>';
                        } else {
                            $stmtCheckRated = $conn->prepare("SELECT id FROM reviews WHERE exchange_id = ? AND reviewer_id = ? AND reviewee_id = ? AND rating > 0");
                            $stmtCheckRated->bind_param("iii", $row['id'], $userId, $row['actual_owner_id']);
                            $stmtCheckRated->execute();
                            $resCheckRated = $stmtCheckRated->get_result();
                            if ($resCheckRated->num_rows > 0) {
                                echo '<button class="rated-button" disabled><i class="fas fa-check"></i> ให้คะแนนแล้ว</button>';
                            }
                        }
                    }
                    ?>

                    <a href="chat.php?request_id=<?php echo $row['id']; ?>">
                        <button class="chat"><i class="fas fa-comments"></i> แชท</button>
                    </a>

                    <?php if ($row['status'] === 'pending'): ?>
                    <form method="POST" action="cancel_request.php" onsubmit="return confirm('คุณแน่ใจหรือไม่ว่าต้องการยกเลิกคำขอนี้?');">
                        <input type="hidden" name="request_id" value="<?php echo $row['id']; ?>">
                        <button type="submit" style="background-color: #dc3545; color: white;">
                            <i class="fas fa-times-circle"></i> ยกเลิกคำขอ
                        </button>
                    </form>
                    <?php endif; ?>

                    <?php if ($row['status'] === 'shipping' || $row['status'] === 'confirm_receiver' || $row['status'] === 'confirm_requester'): ?>
                        <div style="display:flex; gap:8px; flex-direction:column;">
                        <!-- Show whether owner confirmed -->
                        <?php if ($row['receiver_confirmed']): ?>
                            <button class="accept" disabled><i class="fas fa-check"></i> เจ้าของยืนยันแล้ว</button>
                        <?php else: ?>
                            <button class="reject" disabled style="background:#ffc107; color:#333;">รอเจ้าของยืนยัน</button>
                        <?php endif; ?>

                        <!-- Requester can confirm -->
                        <?php if ($row['requester_confirmed']): ?>
                            <button class="accept" disabled><i class="fas fa-check-double"></i> คุณยืนยันแล้ว</button>
                        <?php else: ?>
                            <form method="post" action="process_exchange.php">
                                <input type="hidden" name="request_id" value="<?php echo $row['id']; ?>">
                                <button type="submit" name="action" value="confirm_requester" class="accept">
                                    <i class="fas fa-check-double"></i> ยืนยันการรับของ (ผู้ร้องขอ)
                                </button>
                            </form>
                        <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <p class="no-requests-message">
            <i class="fas fa-paper-plane" style="font-size: 2.5rem; color: #ccc; margin-bottom: 15px; display: block;"></i>
            คุณยังไม่มีคำขอแลกเปลี่ยนที่ส่งไปในขณะนี้
        </p>
    <?php endif; ?>
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
// ปิดการเชื่อมต่อฐานข้อมูลหลังจากใช้งานเสร็จ
$conn->close(); 
?>