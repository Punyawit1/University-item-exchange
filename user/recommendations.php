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

// ดึงรายการสิ่งของทั้งหมดของผู้ใช้ปัจจุบัน
$myItems = [];
$myItemsSql = "SELECT id, name, category, estimated_price FROM items WHERE owner_id = ? AND is_available = 1 AND is_approved = 1 ORDER BY created_at DESC";
$stmt = $conn->prepare($myItemsSql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $myItems[] = $row;
}
$stmt->close();

// ตรวจสอบว่ามีการเลือกสิ่งของเพื่อแนะนำหรือไม่
$selectedItemId = isset($_GET['item_id']) ? intval($_GET['item_id']) : null;
$selectedItem = null;
$recommendedItems = [];

function getItemImagePath($filename) {
    $filepath = "../uploads/item/" . $filename;
    return (!empty($filename) && file_exists($filepath)) ? $filepath : "../uploads/item/default.png";
}

if ($selectedItemId && !empty($myItems)) {
    // ค้นหาสิ่งของที่เลือกจากรายการสิ่งของของผู้ใช้
    foreach ($myItems as $item) {
        if ($item['id'] === $selectedItemId) {
            $selectedItem = $item;
            break;
        }
    }

    // ถ้าพบสิ่งของที่เลือก ให้หาของแนะนำ
    if ($selectedItem) {
    // เพิ่มบรรทัดนี้เพื่อดึงรูปภาพของสิ่งของที่เลือก
    $sql_image = "SELECT image FROM item_images WHERE item_id = ? LIMIT 1";
    $stmt_image = $conn->prepare($sql_image);
    $stmt_image->bind_param("i", $selectedItemId);
    $stmt_image->execute();
    $result_image = $stmt_image->get_result();
    $selectedItemImage = $result_image->fetch_assoc();
    $selectedItemImagePath = $selectedItemImage ? getItemImagePath($selectedItemImage['image']) : "../uploads/item/default.png";
    $stmt_image->close();

    $selectedCategory = $selectedItem['category'];
    $selectedPrice = $selectedItem['estimated_price'];
    
    // กำหนดช่วงราคาสำหรับของแนะนำ ( +/- 20% )
    $minPrice = $selectedPrice > 0 ? $selectedPrice * 0.8 : 0;
    $maxPrice = $selectedPrice > 0 ? $selectedPrice * 1.2 : 0;
        
        $sql = "SELECT i.id, i.name, i.category, i.status, i.exchange_for, i.estimated_price, img.image, u.username AS owner_name, i.created_at
                FROM items i
                LEFT JOIN (
                    SELECT item_id, MIN(image) AS image
                    FROM item_images
                    GROUP BY item_id
                ) img ON i.id = img.item_id
                JOIN users u ON i.owner_id = u.id
                WHERE i.is_approved = 1 AND i.is_available = 1
                AND i.owner_id != ?
                AND i.category = ?
                AND i.status NOT IN ('แจกฟรี', 'ขายราคาถูก')"; // เพิ่มเงื่อนไขนี้
        
        // === START: แก้ไขข้อผิดพลาด Fatal error ที่นี่ ===
        // เตรียมตัวแปรสำหรับ bind_param
        $param_types = "is";
        $params = [$userId, $selectedCategory];

        // ถ้าของที่เลือกมีราคาประเมินมากกว่า 0 ให้ใช้ช่วงราคา
        if ($selectedPrice > 0) {
            $sql .= " AND i.estimated_price BETWEEN ? AND ?";
            $param_types .= "dd";
            $params[] = $minPrice;
            $params[] = $maxPrice;
        }

        $sql .= " ORDER BY RAND() LIMIT 6"; // แสดงของแนะนำ 6 ชิ้นแบบสุ่ม

        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            die("เกิดข้อผิดพลาดในการเตรียม SQL: " . $conn->error);
        }

        // แก้ไข: ใช้ Splat Operator (`...`) เพื่อส่งค่าจากอาร์เรย์เป็นพารามิเตอร์แบบอ้างอิง
        // การใช้ ...$params จะส่งค่าทั้งหมดในอาร์เรย์ $params เข้าไปใน bind_param
        $stmt->bind_param($param_types, ...$params);

        // === END: แก้ไขข้อผิดพลาด ===

        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $recommendedItems[] = $row;
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>แนะนำการแลกเปลี่ยน | แลกของ</title>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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

        h3 {
            font-size: 1.5rem;
            color: var(--text-color);
            margin-top: 3rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        h3 i {
            color: var(--primary-color);
        }

        .items-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
            grid-auto-rows: 1fr;
        }

        .item-card {
            background: var(--white);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px var(--shadow-light);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            position: relative;
        }

        .item-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 15px var(--shadow-medium);
        }

        .item-card img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-bottom: 1px solid var(--border-color);
        }

        .item-card-content {
            padding: 0.8rem;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .item-card h4 {
            margin: 0 0 0.5rem;
            font-size: 1.25rem;
            color: var(--text-color);
            word-break: break-word;
            line-height: 1.3;
            text-align: center;
            font-weight: 600;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .item-card-content p {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            margin-bottom: 0.3rem;
            line-height: 1.2;
        }

        .item-card-content p strong {
            font-weight: 600;
            flex-shrink: 0;
            padding-right: 5px;
        }

        .item-card-content p span {
            text-align: right;
            flex-grow: 1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .item-card small {
            display: block;
            margin-top: 0.5rem;
            font-size: 0.8rem;
            color: #999;
        }

        /* Status Tag Styles */
        .item-status-tag {
            position: absolute;
            top: 10px;
            right: 10px;
            background-color: var(--primary-color);
            color: var(--white);
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.8em;
            font-weight: 600;
            z-index: 5;
        }

        .item-status-tag.แลกเปลี่ยน {
            background-color: var(--exchange-tag-bg);
        }
        .item-status-tag.แจกฟรี {
            background-color: var(--giveaway-tag-bg);
        }
        .item-status-tag.ขายราคาถูก {
            background-color: var(--sell-tag-bg);
        }

        .no-items-message {
            text-align: center;
            padding: 3rem;
            font-size: 1.1rem;
            color: #888;
            background-color: var(--white);
            border-radius: 10px;
            box-shadow: 0 2px 10px var(--shadow-light);
            grid-column: 1 / -1;
        }

        .floating-button {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background-color: var(--success-color);
            color: var(--white);
            padding: 15px 25px;
            border-radius: 50px;
            text-decoration: none;
            font-size: 1.1rem;
            font-weight: 600;
            box-shadow: 0 6px 15px rgba(0,0,0,0.25);
            transition: background-color 0.3s ease, transform 0.2s ease, box-shadow 0.3s ease;
            z-index: 1000;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .floating-button:hover {
            background-color: #218838;
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.3);
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

        /* Recommendations Page Specific Styles */
        .selected-item-box {
            background-color: #f7f7f7;
            border-left: 5px solid var(--primary-color);
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .selected-item-box h4 {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .selected-item-box p {
            margin: 0;
            color: var(--light-text-color);
        }
        
        @media (max-width: 992px) {
            .container {
                padding: 2rem;
            }
            .items-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
            h3 {
                font-size: 1.3rem;
            }
            .items-grid {
                grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            }
            .item-card img {
                width: 100%;
                height: 180px;
            }
            .floating-button {
                bottom: 20px;
                right: 20px;
                padding: 12px 20px;
                font-size: 1rem;
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
            .items-grid {
                grid-template-columns: 1fr;
            }
            .item-card img {
                height: 200px;
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
    <h2><i class="fas fa-search-plus"></i> แนะนำการแลกเปลี่ยน</h2>
    <p>เลือกสิ่งของของคุณด้านล่าง เพื่อดูสิ่งของที่เหมาะสมกับการแลกเปลี่ยน</p>

    <form method="GET" action="recommendations.php">
        <div class="mb-4">
            <label for="my_item" class="form-label">เลือกสิ่งของของคุณ:</label>
            <select name="item_id" id="my_item" class="form-select" onchange="this.form.submit()">
                <option value="">-- เลือกสิ่งของของคุณ --</option>
                <?php foreach ($myItems as $item): ?>
                    <option value="<?php echo htmlspecialchars($item['id']); ?>"
                        <?php echo ($selectedItemId == $item['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($item['name']); ?> (หมวดหมู่: <?php echo htmlspecialchars($item['category']); ?>, ราคาประเมิน: <?php echo number_format($item['estimated_price'], 0); ?> บาท)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>
    
    <?php if ($selectedItem): ?>
<div class="selected-item-box mb-4">
    <div style="display: flex; gap: 20px; align-items: center;">
        <img src="<?php echo htmlspecialchars($selectedItemImagePath); ?>" alt="รูปภาพสิ่งของที่เลือก" style="width: 150px; height: 150px; object-fit: cover; border-radius: 8px;">
        <div>
            <h4>สิ่งของที่คุณเลือก: <?php echo htmlspecialchars($selectedItem['name']); ?></h4>
            <p><strong>หมวดหมู่:</strong> <?php echo htmlspecialchars($selectedItem['category']); ?></p>
            <p><strong>ราคาประเมิน:</strong> <?php echo number_format($selectedItem['estimated_price'], 0); ?> บาท</p>
        </div>
    </div>
</div>

        
        <h3>สิ่งของที่แนะนำสำหรับแลกเปลี่ยน</h3>
        <div class="items-grid">
            <?php if (!empty($recommendedItems)): ?>
                <?php foreach ($recommendedItems as $recItem): ?>
                    <a href="item_detail.php?id=<?php echo $recItem['id']; ?>" class="item-card">
                        <span class="item-status-tag <?php echo htmlspecialchars($recItem['status']); ?>"><?php echo htmlspecialchars($recItem['status']); ?></span>
                        <img src="<?php echo htmlspecialchars(getItemImagePath($recItem['image'])); ?>" alt="ภาพแนะนำ">
                        <div class="item-card-content">
                            <h4><?php echo htmlspecialchars($recItem['name']); ?></h4>
                            <p><strong>หมวดหมู่:</strong> <span><?php echo htmlspecialchars($recItem['category']); ?></span></p>
                            <p><strong>เจ้าของ:</strong> <span><?php echo htmlspecialchars($recItem['owner_name']); ?></span></p>
                            <?php if ($recItem['status'] === 'แลกเปลี่ยน'): ?>
                                <p><strong>สิ่งที่ต้องการเเลกเปลี่ยน:</strong> <span><?php echo htmlspecialchars($recItem['exchange_for']); ?></span></p>
                                <p><strong>ราคาประเมิน:</strong> <span><?php echo number_format($recItem['estimated_price'], 0); ?> บาท</span></p>
                            <?php elseif ($recItem['status'] === 'ขายราคาถูก'): ?>
                                <p><strong>ราคา:</strong> <span><?php echo number_format($recItem['estimated_price'], 0); ?> บาท</span></p>
                            <?php endif; ?>
                            <small>ลงเมื่อ: <?php echo date("d/m/Y", strtotime($recItem['created_at'])); ?></small>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="no-items-message">😕 ไม่พบสิ่งของที่เหมาะสมกับการแลกเปลี่ยนในขณะนี้ ลองเลือกสิ่งของอื่นดูสิ</p>
            <?php endif; ?>
        </div>
    <?php elseif (empty($myItems)): ?>
        <p class="no-items-message">คุณยังไม่มีสิ่งของในรายการของคุณเลย ไปเพิ่มของใหม่กันเถอะ! <a href="add_item.php">เพิ่มของใหม่</a></p>
    <?php else: ?>
        <p class="no-items-message">กรุณาเลือกสิ่งของของคุณจากรายการด้านบนเพื่อดูคำแนะนำ</p>
    <?php endif; ?>

</div>

<a href="add_item.php" class="floating-button">
    <i class="fas fa-plus-circle"></i> เพิ่มของใหม่
</a>

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
<?php $conn->close(); ?>
