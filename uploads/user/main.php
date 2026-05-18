<?php
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

// ดึงหมวดหมู่จากฐานข้อมูล
$categoryQuery = $conn->query("SELECT name FROM categories ORDER BY name ASC");
$categories = ["all" => "ทั้งหมด"];
while ($row = $categoryQuery->fetch_assoc()) {
    $categories[$row['name']] = $row['name'];
}

// ดึงประกาศที่เปิดใช้งานล่าสุด 1 รายการ
$announcement = null;
$sql_announcement = "SELECT title, message FROM announcements WHERE is_active = 1 ORDER BY created_at DESC LIMIT 1";
$result_announcement = $conn->query($sql_announcement);
if ($result_announcement && $result_announcement->num_rows > 0) {
    $announcement = $result_announcement->fetch_assoc();
}

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

// รับค่าหมวดหมู่และสถานะจากฟอร์ม
$filterCategory = isset($_GET['category']) ? $_GET['category'] : "all";
$filterStatus = isset($_GET['status']) ? $_GET['status'] : "all";
$search_query = isset($_GET['q']) ? trim($_GET['q']) : '';

// Function เพื่อสร้าง SQL query สำหรับการดึงสิ่งของ
function buildItemQuery($conn, $userId, $filterCategory, $filterStatus, $search_query, $isRecommended = false, $limit = null) {
    $sql = "SELECT i.id, i.name, i.category, i.status, i.exchange_for, i.estimated_price, i.price, i.condition_notes, i.purchase_year, i.description, i.exchange_estimated_price, img.image, u.username AS owner_name, u.score AS owner_score, i.created_at
        FROM items i
        LEFT JOIN (
            SELECT item_id, MIN(image) AS image
            FROM item_images
            GROUP BY item_id
        ) img ON i.id = img.item_id
        JOIN users u ON i.owner_id = u.id
        WHERE i.is_approved = 1 AND i.is_available = 1";

    $params = [];
    $types = "";

    if ($isRecommended) {
        $sql .= " AND i.owner_id != ?";
        $params[] = $userId;
        $types .= "i";
    }

    if ($filterCategory !== "all") {
        $sql .= " AND i.category = ?";
        $params[] = $filterCategory;
        $types .= "s";
    }

    if ($filterStatus !== "all") {
        $sql .= " AND i.status = ?";
        $params[] = $filterStatus;
        $types .= "s";
    }

    if (!empty($search_query)) {
        $sql .= " AND (i.name LIKE ? OR i.description LIKE ?)";
        $search_param = "%" . $search_query . "%";
        $params[] = $search_param;
        $params[] = $search_param;
        $types .= "ss";
    }

    if ($isRecommended) {
        $sql .= " ORDER BY RAND()";
    } else {
        $sql .= " ORDER BY i.created_at DESC";
    }

    if ($limit) {
        $sql .= " LIMIT " . (int)$limit;
    }

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        die("เกิดข้อผิดพลาดในการเตรียม SQL: " . $conn->error);
    }
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    return $stmt->get_result();
}

// ดึงสิ่งของที่แนะนำ
$recommendedItems = buildItemQuery($conn, $userId, $filterCategory, $filterStatus, $search_query, true, 3);

// ดึงสิ่งของทั้งหมด
$allItems = buildItemQuery($conn, $userId, $filterCategory, $filterStatus, $search_query, false);

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
    <title>หน้าหลักผู้ใช้งาน | แลกของ</title>
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

        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 2.5rem;
            background: var(--white);
            border-radius: 12px;
            box-shadow: 0 4px 15px var(--shadow-light);
        }
        
        .user-info-container {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .user-info-text {
            flex-grow: 1;
        }

        .user-score-container {
            background-color: var(--primary-color);
            color: var(--text-color);
            padding: 1rem 1.5rem;
            border-radius: 10px;
            text-align: center;
            font-size: 1.2rem;
            font-weight: bold;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 10px;
            white-space: nowrap;
        }
        
        .user-score-container i {
            font-size: 1.5rem;
            color: #fff;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.2);
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

        .filter-section {
            background-color: var(--bg-light);
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }

        .filter-section label {
            font-size: 1rem;
            font-weight: 500;
            color: var(--text-color);
            margin-bottom: 10px;
        }

        .filter-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 1rem;
        }
        .filter-button {
            padding: 10px 20px;
            border-radius: 20px;
            text-decoration: none;
            font-weight: 600;
            color: var(--white);
            transition: transform 0.2s ease;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .filter-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 10px rgba(0,0,0,0.15);
        }
        .filter-button.active {
            filter: brightness(1.1);
        }

        .filter-button.all { background-color: #2196F3; }
        .filter-button.แลกเปลี่ยน { background-color: var(--exchange-tag-bg); }
        .filter-button.แจกฟรี { background-color: var(--giveaway-tag-bg); }
        .filter-button.ขายราคาถูก { background-color: var(--sell-tag-bg); }

        .filter-button.inactive {
            background-color: #ccc;
            box-shadow: none;
        }

        .category-select {
            padding: 10px 15px;
            font-size: 1rem;
            border-radius: 5px;
            border: 1px solid var(--border-color);
            background-color: var(--white);
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23666'%3E%3Cpath d='M7 10l5 5 5-5z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 15px center;
            background-size: 20px;
            cursor: pointer;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
            margin-top: 10px;
        }
        .category-select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(255,193,7,0.15);
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

        /* Enhanced Item Card Styles */
        .items-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
            grid-auto-rows: 1fr;
        }

        .item-card {
            background: var(--white);
            border: 1px solid rgba(255, 193, 7, 0.2);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            position: relative;
            background: linear-gradient(135deg, rgba(255,255,255,1) 0%, rgba(255,248,225,0.3) 100%);
        }

        .item-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary-color), var(--primary-light), var(--primary-color));
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .item-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 12px 40px rgba(255,193,7,0.25);
            border-color: var(--primary-color);
        }

        .item-card:hover::before {
            opacity: 1;
        }

        .item-image-container {
            position: relative;
            width: 100%;
            height: 220px;
            overflow: hidden;
            background: linear-gradient(45deg, #f0f0f0, #e0e0e0);
        }

        .item-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .item-card:hover img {
            transform: scale(1.1);
        }

        .item-card-content {
            padding: 1.5rem;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .item-card-header {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 12px;
        }

        .item-card h4 {
            margin: 0;
            font-size: 1.4rem;
            color: var(--text-color);
            font-weight: 700;
            line-height: 1.3;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
            min-height: 2.6rem;
        }

        .item-category-badge {
            background: linear-gradient(135deg, rgba(255,193,7,0.15) 0%, rgba(255,193,7,0.05) 100%);
            color: var(--primary-dark);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            border: 1px solid rgba(255,193,7,0.3);
            width: fit-content;
        }

        .item-owner-info {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-muted);
            font-size: 0.9rem;
            margin-bottom: 8px;
        }

        .item-owner-info i {
            color: var(--primary-color);
            font-size: 0.85rem;
        }

        .item-details {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .item-detail-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 8px 12px;
            background: rgba(255,193,7,0.05);
            border-radius: 8px;
            border-left: 3px solid var(--primary-color);
        }

        .item-detail-row strong {
            font-weight: 600;
            color: var(--text-color);
            font-size: 0.9rem;
            min-width: fit-content;
            margin-right: 12px;
        }

        .item-detail-row span {
            text-align: right;
            font-size: 0.9rem;
            color: var(--text-muted);
            word-break: break-word;
            line-height: 1.3;
        }

        .item-price-highlight {
            background: linear-gradient(135deg, rgba(76,175,80,0.1) 0%, rgba(255,255,255,1) 100%);
            border-left-color: #4CAF50 !important;
        }

        .item-price-highlight span {
            color: #2E7D32;
            font-weight: 700;
            font-size: 1rem;
        }

        .item-card-footer {
            margin-top: auto;
            padding-top: 12px;
            border-top: 1px solid rgba(255,193,7,0.2);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .item-date {
            font-size: 0.8rem;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .item-date i {
            color: var(--primary-color);
        }

        /* Enhanced Status Tag Styles */
        .item-status-tag {
            position: absolute;
            top: 12px;
            right: 12px;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: var(--white);
            padding: 8px 16px;
            border-radius: 25px;
            font-size: 0.85rem;
            font-weight: 700;
            z-index: 10;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: 2px solid rgba(255,255,255,0.9);
            backdrop-filter: blur(10px);
        }

        .item-status-tag.แลกเปลี่ยน {
            background: linear-gradient(135deg, var(--exchange-tag-bg) 0%, #388E3C 100%);
            box-shadow: 0 4px 12px rgba(76,175,80,0.3);
        }
        
        .item-status-tag.แจกฟรี {
            background: linear-gradient(135deg, var(--giveaway-tag-bg) 0%, #1976D2 100%);
            box-shadow: 0 4px 12px rgba(33,150,243,0.3);
        }
        
        .item-status-tag.ขายราคาถูก {
            background: linear-gradient(135deg, var(--sell-tag-bg) 0%, #F57C00 100%);
            box-shadow: 0 4px 12px rgba(255,152,0,0.3);
        }

        /* Enhanced Free Item Styling */
        .free-item-badge {
            background: linear-gradient(135deg, rgba(33,150,243,0.15) 0%, rgba(33,150,243,0.05) 100%);
            color: #1976D2;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 700;
            border: 1px solid rgba(33,150,243,0.3);
            width: fit-content;
            display: flex;
            align-items: center;
            gap: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .free-item-highlight {
            background: linear-gradient(135deg, rgba(33,150,243,0.1) 0%, rgba(255,255,255,1) 100%);
            border-left-color: #2196F3 !important;
        }

        .free-item-highlight span {
            color: #1976D2;
            font-weight: 600;
        }

        /* Enhanced Exchange Item Styling */
        .exchange-item-badge {
            background: linear-gradient(135deg, rgba(76,175,80,0.15) 0%, rgba(76,175,80,0.05) 100%);
            color: #2E7D32;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 700;
            border: 1px solid rgba(76,175,80,0.3);
            width: fit-content;
            display: flex;
            align-items: center;
            gap: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .exchange-highlight {
            background: linear-gradient(135deg, rgba(76,175,80,0.1) 0%, rgba(255,255,255,1) 100%);
            border-left-color: #4CAF50 !important;
        }

        .exchange-highlight span {
            color: #2E7D32;
            font-weight: 600;
        }

        /* Enhanced Sale Item Styling */
        .sale-item-badge {
            background: linear-gradient(135deg, rgba(255,152,0,0.15) 0%, rgba(255,152,0,0.05) 100%);
            color: #F57C00;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 700;
            border: 1px solid rgba(255,152,0,0.3);
            width: fit-content;
            display: flex;
            align-items: center;
            gap: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .sale-price-highlight {
            background: linear-gradient(135deg, rgba(255,152,0,0.1) 0%, rgba(255,255,255,1) 100%);
            border-left-color: #FF9800 !important;
        }

        .sale-price-highlight span {
            color: #F57C00;
            font-weight: 700;
            font-size: 1rem;
        }

        .original-price-highlight {
            background: linear-gradient(135deg, rgba(158,158,158,0.08) 0%, rgba(255,255,255,1) 100%);
            border-left-color: #9E9E9E !important;
        }

        .original-price-highlight span {
            color: #757575;
            font-weight: 500;
        }

        .condition-info {
            background: linear-gradient(135deg, rgba(76,175,80,0.08) 0%, rgba(255,255,255,1) 100%);
            border-left-color: #4CAF50 !important;
        }

        .condition-info span {
            color: #2E7D32;
            font-weight: 500;
        }

        .owner-rating {
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 0.8rem;
        }

        .owner-rating .rating-stars {
            color: #FFD700;
        }

        .owner-rating .rating-score {
            color: var(--text-muted);
            font-weight: 600;
        }

        .no-items-message {
            text-align: center;
            padding: 3rem;
            font-size: 1.1rem;
            color: #888;
            background: linear-gradient(135deg, rgba(255,193,7,0.05) 0%, rgba(255,255,255,1) 100%);
            border: 1px solid rgba(255,193,7,0.2);
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            grid-column: 1 / -1;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
        }

        .no-items-message i {
            font-size: 3rem;
            color: var(--primary-color);
            opacity: 0.6;
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

        /* Search input with clear button styles */
        .search-container {
            position: relative;
            width: 100%;
        }

        .search-container .form-control {
            padding-right: 2.5rem; /* Space for the clear button */
        }

        .clear-search {
            position: absolute;
            top: 50%;
            right: 4.5rem; /* Adjusted to sit to the left of the search button */
            transform: translateY(-50%);
            cursor: pointer;
            color: #999;
            font-size: 1.5rem;
            font-weight: bold;
            display: none; /* Hide by default */
        }

        .clear-search:hover {
            color: #666;
        }
        
        @media (max-width: 992px) {
            .container {
                padding: 2rem;
            }
            .items-grid {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 1.5rem;
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
                grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
                gap: 1.2rem;
            }
            .item-image-container {
                height: 180px;
            }
            .item-card-content {
                padding: 1.2rem;
            }
            .item-card h4 {
                font-size: 1.2rem;
            }
            .floating-button {
                bottom: 20px;
                right: 20px;
                padding: 12px 20px;
                font-size: 1rem;
            }
            .clear-search {
                right: 3.5rem;
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
                gap: 1rem;
            }
            .item-image-container {
                height: 200px;
            }
            .item-card-content {
                padding: 1rem;
            }
            .item-card h4 {
                font-size: 1.1rem;
            }
            .item-status-tag {
                padding: 6px 12px;
                font-size: 0.75rem;
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
<?php if ($announcement): ?>
<div class="modal fade" id="announcementModal" tabindex="-1" aria-labelledby="announcementModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 15px; box-shadow: 0 0 15px rgba(0,0,0,0.2);">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="announcementModalLabel"><i class="fas fa-bullhorn"></i> <?= htmlspecialchars($announcement['title']) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" style="font-size: 1.1rem; line-height: 1.5;">
                <?= nl2br(htmlspecialchars($announcement['message'])) ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-warning" data-bs-dismiss="modal">ปิด</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

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
    <div class="user-info-container">
        <div class="user-info-text">
            <h2>สวัสดีคุณ <?php echo htmlspecialchars($username); ?>! 👋</h2>
            <p>ยินดีต้อนรับเข้าสู่ระบบแลกเปลี่ยนสิ่งของ มาค้นหาสิ่งที่คุณสนใจกันเถอะ!</p>
        </div>
        </div>

    <div class="filter-section">
        <label for="category"><i class="fas fa-filter"></i> กรอง:</label>
        <form method="GET" action="main.php" id="filter-form" class="d-flex flex-column">
            <div class="filter-buttons">
                <?php
                $status_options = ['all' => 'ทั้งหมด', 'แลกเปลี่ยน' => 'แลกเปลี่ยน', 'แจกฟรี' => 'แจกฟรี', 'ขายราคาถูก' => 'ขายราคาถูก'];
                foreach ($status_options as $key => $label) {
                    $isActive = $filterStatus === $key ? 'active' : 'inactive';
                    echo '<a href="main.php?category=' . htmlspecialchars($filterCategory) . '&status=' . htmlspecialchars($key) . '&q=' . htmlspecialchars($search_query) . '" class="filter-button ' . htmlspecialchars($key) . ' ' . $isActive . '">' . htmlspecialchars($label) . '</a>';
                }
                ?>
            </div>
            
            <div class="input-group my-3 search-container">
                <input type="text" class="form-control" name="q" id="search-input" placeholder="ค้นหาสิ่งของ..." value="<?php echo htmlspecialchars($search_query); ?>">
                <span class="clear-search">×</span>
                <button class="btn btn-warning" type="submit"><i class="fas fa-search"></i> ค้นหา</button>
            </div>

            <select name="category" id="category" class="category-select" onchange="document.getElementById('filter-form').submit()">
                <?php foreach($categories as $key => $label): ?>
                    <option value="<?php echo htmlspecialchars($key); ?>" <?php if ($filterCategory === $key) echo "selected"; ?>>
                        หมวดหมู่: <?php echo htmlspecialchars($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="hidden" name="status" value="<?php echo htmlspecialchars($filterStatus); ?>">
        </form>
    </div>

    

<h3><i class="fas fa-star"></i> แนะนำสำหรับคุณ</h3>
<div class="items-grid">
    <?php if ($recommendedItems->num_rows > 0): ?>
        <?php while($rec = $recommendedItems->fetch_assoc()): ?>
            <a href="item_detail.php?id=<?php echo $rec['id']; ?>" class="item-card">
                <span class="item-status-tag <?php echo htmlspecialchars($rec['status']); ?>"><?php echo htmlspecialchars($rec['status']); ?></span>
                <div class="item-image-container">
                    <img src="<?php echo htmlspecialchars(getItemImagePath($rec['image'])); ?>" alt="ภาพแนะนำ">
                </div>
                <div class="item-card-content">
                    <div class="item-card-header">
                        <h4><?php echo htmlspecialchars($rec['name']); ?></h4>
                        <div class="item-category-badge">
                            <i class="fas fa-tag"></i> <?php echo htmlspecialchars($rec['category']); ?>
                        </div>
                        <div class="item-owner-info">
                            <i class="fas fa-user"></i>
                            <span><?php echo htmlspecialchars($rec['owner_name']); ?></span>
                        </div>
                    </div>
                    
                    <div class="item-details">
                        <?php if ($rec['status'] === 'แลกเปลี่ยน'): ?>
                            <!-- Exchange Item Essential Details -->
                            <div class="exchange-item-badge">
                                <i class="fas fa-exchange-alt"></i> พร้อมแลกเปลี่ยน
                            </div>
                            <?php if (!empty($rec['exchange_for'])): ?>
                                <div class="item-detail-row exchange-highlight">
                                    <strong><i class="fas fa-handshake"></i> ต้องการแลก:</strong>
                                    <span><?php echo htmlspecialchars($rec['exchange_for']); ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if (isset($rec['estimated_price']) && $rec['estimated_price'] > 0): ?>
                                <div class="item-detail-row item-price-highlight">
                                    <strong><i class="fas fa-chart-line"></i> ราคาประเมิน:</strong>
                                    <span><?php echo number_format($rec['estimated_price'], 0); ?> บาท</span>
                                </div>
                            <?php endif; ?>
                        <?php elseif ($rec['status'] === 'ขายราคาถูก'): ?>
                            <!-- Sale Item Essential Details -->
                            <div class="sale-item-badge">
                                <i class="fas fa-tags"></i> ขายราคาพิเศษ
                            </div>
                            <?php if (!empty($rec['estimated_price'])): ?>
                                <div class="item-detail-row sale-price-highlight">
                                    <strong><i class="fas fa-money-bill-wave"></i> ราคาขาย:</strong>
                                    <span><?php echo number_format($rec['estimated_price'], 0); ?> บาท</span>
                                </div>
                            <?php endif; ?>
                            <?php if (isset($rec['price']) && $rec['price'] > 0 && $rec['price'] > $rec['estimated_price']): ?>
                                <div class="item-detail-row original-price-highlight">
                                    <strong><i class="fas fa-tag"></i> ราคาเดิม:</strong>
                                    <span style="text-decoration: line-through; color: #999;"><?php echo number_format($rec['price'], 0); ?> บาท</span>
                                </div>
                            <?php endif; ?>
                        <?php elseif ($rec['status'] === 'แจกฟรี'): ?>
                            <!-- Free Item Essential Details -->
                            <div class="free-item-badge">
                                <i class="fas fa-gift"></i> แจกฟรี 100%
                            </div>
                            <?php if (isset($rec['price']) && $rec['price'] > 0): ?>
                                <div class="item-detail-row free-item-highlight">
                                    <strong><i class="fas fa-tags"></i> ราคาเดิม:</strong>
                                    <span><?php echo number_format($rec['price'], 0); ?> บาท</span>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($rec['condition_notes'])): ?>
                                <div class="item-detail-row condition-info">
                                    <strong><i class="fas fa-info-circle"></i> สภาพ:</strong>
                                    <span><?php echo htmlspecialchars(substr($rec['condition_notes'], 0, 30)) . (strlen($rec['condition_notes']) > 30 ? '...' : ''); ?></span>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <div class="item-card-footer">
                        <div class="item-date">
                            <i class="fas fa-calendar-alt"></i>
                            <span><?php echo date("d/m/Y", strtotime($rec['created_at'])); ?></span>
                        </div>
                    </div>
                </div>
            </a>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="no-items-message">
            <i class="fas fa-search"></i>
            <div>
                <strong>😕 ยังไม่มีคำแนะนำสำหรับคุณในขณะนี้</strong>
                <p>ลองเพิ่มสิ่งของของคุณเพื่อให้ระบบแนะนำสิ่งของที่เหมาะสม</p>
            </div>
        </div>
    <?php endif; ?>
</div>
    
---
    
    <h3><i class="fas fa-box-open"></i> รายการสิ่งของทั้งหมด</h3>
<div class="items-grid">
    <?php if ($allItems->num_rows > 0): ?>
        <?php while($item = $allItems->fetch_assoc()): ?>
            <a href="item_detail.php?id=<?php echo $item['id']; ?>" class="item-card">
                <span class="item-status-tag <?php echo htmlspecialchars($item['status']); ?>"><?php echo htmlspecialchars($item['status']); ?></span>
                <div class="item-image-container">
                    <img src="<?php echo htmlspecialchars(getItemImagePath($item['image'])); ?>" alt="ภาพสินค้า">
                </div>
                <div class="item-card-content">
                    <div class="item-card-header">
                        <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                        <div class="item-category-badge">
                            <i class="fas fa-tag"></i> <?php echo htmlspecialchars($item['category']); ?>
                        </div>
                        <div class="item-owner-info">
                            <i class="fas fa-user"></i>
                            <span><?php echo htmlspecialchars($item['owner_name']); ?></span>
                        </div>
                    </div>
                    
                    <div class="item-details">
                        <?php if ($item['status'] === 'แลกเปลี่ยน'): ?>
                            <!-- Exchange Item Essential Details -->
                            <div class="exchange-item-badge">
                                <i class="fas fa-exchange-alt"></i> พร้อมแลกเปลี่ยน
                            </div>
                            <?php if (!empty($item['exchange_for'])): ?>
                                <div class="item-detail-row exchange-highlight">
                                    <strong><i class="fas fa-handshake"></i> ต้องการแลก:</strong>
                                    <span><?php echo htmlspecialchars($item['exchange_for']); ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if (isset($item['estimated_price']) && $item['estimated_price'] > 0): ?>
                                <div class="item-detail-row item-price-highlight">
                                    <strong><i class="fas fa-chart-line"></i> ราคาประเมิน:</strong>
                                    <span><?php echo number_format($item['estimated_price'], 0); ?> บาท</span>
                                </div>
                            <?php endif; ?>
                        <?php elseif ($item['status'] === 'ขายราคาถูก'): ?>
                            <!-- Sale Item Essential Details -->
                            <div class="sale-item-badge">
                                <i class="fas fa-tags"></i> ขายราคาพิเศษ
                            </div>
                            <?php if (!empty($item['estimated_price'])): ?>
                                <div class="item-detail-row sale-price-highlight">
                                    <strong><i class="fas fa-money-bill-wave"></i> ราคาขาย:</strong>
                                    <span><?php echo number_format($item['estimated_price'], 0); ?> บาท</span>
                                </div>
                            <?php endif; ?>
                            <?php if (isset($item['price']) && $item['price'] > 0 && $item['price'] > $item['estimated_price']): ?>
                                <div class="item-detail-row original-price-highlight">
                                    <strong><i class="fas fa-tag"></i> ราคาเดิม:</strong>
                                    <span style="text-decoration: line-through; color: #999;"><?php echo number_format($item['price'], 0); ?> บาท</span>
                                </div>
                            <?php endif; ?>
                        <?php elseif ($item['status'] === 'แจกฟรี'): ?>
                            <!-- Free Item Essential Details -->
                            <div class="free-item-badge">
                                <i class="fas fa-gift"></i> แจกฟรี 100%
                            </div>
                            <?php if (isset($item['price']) && $item['price'] > 0): ?>
                                <div class="item-detail-row free-item-highlight">
                                    <strong><i class="fas fa-tags"></i> ราคาเดิม:</strong>
                                    <span><?php echo number_format($item['price'], 0); ?> บาท</span>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($item['condition_notes'])): ?>
                                <div class="item-detail-row condition-info">
                                    <strong><i class="fas fa-info-circle"></i> สภาพ:</strong>
                                    <span><?php echo htmlspecialchars(substr($item['condition_notes'], 0, 30)) . (strlen($item['condition_notes']) > 30 ? '...' : ''); ?></span>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <div class="item-card-footer">
                        <div class="item-date">
                            <i class="fas fa-calendar-alt"></i>
                            <span><?php echo date("d/m/Y", strtotime($item['created_at'])); ?></span>
                        </div>
                    </div>
                </div>
            </a>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="no-items-message">
            <i class="fas fa-search"></i>
            <div>
                <strong>😅 ยังไม่พบสิ่งของที่ตรงกับเงื่อนไข</strong>
                <p>ลองปรับเปลี่ยนตัวกรองหรือค้นหาด้วยคำค้นอื่น</p>
            </div>
        </div>
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

<?php if ($announcement): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var announcementModal = new bootstrap.Modal(document.getElementById('announcementModal'));
        announcementModal.show();
    });
</script>
<?php endif; ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('search-input');
        const clearBtn = document.querySelector('.clear-search');

        // Function to show/hide the clear button
        function toggleClearButton() {
            if (searchInput.value.length > 0) {
                clearBtn.style.display = 'block';
            } else {
                clearBtn.style.display = 'none';
            }
        }

        // Show/hide button on page load and when user types
        searchInput.addEventListener('input', toggleClearButton);
        toggleClearButton();

        // Clear input and submit form on button click
        clearBtn.addEventListener('click', function() {
            searchInput.value = '';
            // Manually trigger the form submission to refresh the page
            document.getElementById('filter-form').submit();
        });
    });
</script>

</body>
</html>
<?php $conn->close(); ?>
