<?php
session_start();

// ตรวจสอบสิทธิ์การเข้าถึง: ต้องเป็น Admin เท่านั้น
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.html");
    exit();
}

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];

$conn = new mysqli("202.28.34.205", "65011211056", "65011211056", "db65011211056");
if ($conn->connect_error) {
    die("เชื่อมต่อฐานข้อมูลล้มเหลว: " . $conn->connect_error);
}

// ดึงรูปโปรไฟล์ของผู้ใช้สำหรับ Header
$stmtProfile = $conn->prepare("SELECT profile_image FROM users WHERE id = ?");
$stmtProfile->bind_param("i", $userId);
$stmtProfile->execute();
$resultProfile = $stmtProfile->get_result();
$userProfile = $resultProfile->fetch_assoc();

$profileImageHeader = (!empty($userProfile['profile_image']) && file_exists("../uploads/profile/" . $userProfile['profile_image']))
    ? "../uploads/profile/" . $userProfile['profile_image']
    : "../uploads/profile/default.png";
$stmtProfile->close();

// ดึงข้อมูลสำคัญสำหรับ Admin Dropdown พร้อม error handling
$totalUsersResult = $conn->query("SELECT COUNT(*) as count FROM users");
$totalUsers = $totalUsersResult ? $totalUsersResult->fetch_assoc()['count'] : 0;

$totalItemsResult = $conn->query("SELECT COUNT(*) as count FROM items");
$totalItems = $totalItemsResult ? $totalItemsResult->fetch_assoc()['count'] : 0;

$pendingItemsResult = $conn->query("SELECT COUNT(*) as count FROM items WHERE is_approved = 0");
$pendingItems = $pendingItemsResult ? $pendingItemsResult->fetch_assoc()['count'] : 0;

$totalExchangesResult = $conn->query("SELECT COUNT(*) as count FROM exchange_requests WHERE status = 'completed'");
$totalExchanges = $totalExchangesResult ? $totalExchangesResult->fetch_assoc()['count'] : 0;

// ตรวจสอบว่าตาราง reports มีอยู่หรือไม่ก่อนทำ query
$recentReports = 0;
$checkReportsTable = $conn->query("SHOW TABLES LIKE 'reports'");
if ($checkReportsTable && $checkReportsTable->num_rows > 0) {
    // แก้ไข: เปลี่ยน 'created_at' เป็น 'report_date' หรือชื่อคอลัมน์ที่ถูกต้อง
$recentReportsResult = $conn->query("SELECT COUNT(*) as count FROM reports WHERE report_date >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    if ($recentReportsResult) {
        $recentReports = $recentReportsResult->fetch_assoc()['count'];
    }
}

// รับค่าหมวดหมู่จากฟอร์ม (ถ้ามี)
$filterCategory = isset($_GET['category']) ? $_GET['category'] : "all"; // Default to "all"

// สร้างเงื่อนไข SQL
$whereClause = "WHERE i.is_exchanged = 0 AND i.is_available = 1"; // เพิ่มเงื่อนไขเพื่อซ่อนรายการที่ถูกแลกเปลี่ยนและไม่พร้อมให้แลกเปลี่ยน
$params = [];
$types = "";

if ($filterCategory !== "" && $filterCategory !== "all") {
    $whereClause .= " AND i.category = ?";
    $types = "s";
    $params[] = $filterCategory;
}

// SQL ดึงรายการสินค้าที่รอการอนุมัติ (is_approved = 0)
$sqlPending = "SELECT i.id, i.name, i.category, i.created_at, i.is_approved, img.image, u.fullname AS owner_name
        FROM items i
        LEFT JOIN (
            SELECT item_id, MIN(image) AS image
            FROM item_images
            GROUP BY item_id
        ) img ON i.id = img.item_id
        JOIN users u ON i.owner_id = u.id
        $whereClause AND i.is_approved = 0
        ORDER BY i.created_at DESC";

// SQL ดึงรายการสินค้าที่อนุมัติแล้ว (is_approved = 1)
$sqlApproved = "SELECT i.id, i.name, i.category, i.created_at, i.is_approved, img.image, u.fullname AS owner_name
        FROM items i
        LEFT JOIN (
            SELECT item_id, MIN(image) AS image
            FROM item_images
            GROUP BY item_id
        ) img ON i.id = img.item_id
        JOIN users u ON i.owner_id = u.id
        $whereClause AND i.is_approved = 1
        ORDER BY i.created_at DESC";

// เตรียมและดำเนินการ query สำหรับรายการที่รอการอนุมัติ
$stmtPending = $conn->prepare($sqlPending);
if ($stmtPending === false) {
    die("การเตรียมคำสั่ง SQL สำหรับรายการรอการอนุมัติล้มเหลว: " . $conn->error);
}
if (!empty($params)) {
    $stmtPending->bind_param($types, ...$params);
}
$stmtPending->execute();
$resultPending = $stmtPending->get_result();

// เตรียมและดำเนินการ query สำหรับรายการที่อนุมัติแล้ว
$stmtApproved = $conn->prepare($sqlApproved);
if ($stmtApproved === false) {
    die("การเตรียมคำสั่ง SQL สำหรับรายการที่อนุมัติแล้วล้มเหลว: " . $conn->error);
}
if (!empty($params)) {
    $stmtApproved->bind_param($types, ...$params);
}
$stmtApproved->execute();
$resultApproved = $stmtApproved->get_result();

// นับจำนวนรายการในแต่ละหมวด
$pendingCount = $resultPending->num_rows;
$approvedCount = $resultApproved->num_rows;

function getItemImagePath($filename) {
    $filepath = "../uploads/item/" . $filename;
    if (!empty($filename) && file_exists($filepath)) {
        return $filepath;
    }
    return "../uploads/item/default.png"; // Fallback default image
}

// ดึงหมวดหมู่ทั้งหมดจากฐานข้อมูลสำหรับ Dropdown Filter
$categoryResult = $conn->query("SELECT name FROM categories ORDER BY name ASC");
$categories = ["all" => "ทั้งหมด"]; // Add "ทั้งหมด" option
while ($row = $categoryResult->fetch_assoc()) {
    $categories[$row['name']] = $row['name'];
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>หน้าหลักผู้ดูแลระบบ | แลกของ</title>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />

    <style>
        :root {
            --admin-primary-color: #ffc107;
            --admin-primary-dark: #e0a800;
            --header-bg-color: #2c3e50; /* Dark blue/gray for a professional look */
            --header-link-color: #ecf0f1;
            --text-color: #333;
            --light-text-color: #666;
            --border-color: #e0e0e0;
            --bg-light: #f5f5f5;
            --white: #fff;
            --shadow-light: rgba(0,0,0,0.08);
            --shadow-medium: rgba(0,0,0,0.12);
            --success-color: #28a745;
            --warning-color: #ffc107;
            --info-color: #007bff;
            --button-view: #007bff;
            --button-approve: #28a745;
            --button-edit: #ffc107;
            --button-delete: #dc3545;
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

        /* ----- HEADER STYLES (UPDATED) ----- */
        header {
            background: var(--header-bg-color);
            color: var(--header-link-color);
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

        .header-left {
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .header-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.5rem;
            font-weight: 700;
            white-space: nowrap;
            color: var(--admin-primary-color);
        }

        .header-title i {
            font-size: 1.7rem;
            color: var(--admin-primary-color);
        }

        .header-nav {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-left: 20px;
        }

        .header-nav .menu-group {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-right: 20px;
        }

        .header-nav a {
            text-decoration: none;
            padding: 5px 0;
            background-color: transparent;
            color: var(--header-link-color);
            border-radius: 6px;
            display: inline-block;
            white-space: nowrap;
            transition: background-color 0.3s ease, color 0.3s ease;
            font-weight: 500;
            font-size: 0.95rem;
            border: 1px solid transparent;
            position: relative;
        }

        .header-nav a:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: var(--admin-primary-color);
        }

        .header-nav a::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: -5px;
            left: 0;
            background-color: var(--admin-primary-color);
            transition: width 0.3s ease-out;
        }

        .header-nav a:hover::after {
            width: 100%;
        }

        .header-nav a.current-page {
            font-weight: 600;
            background-color: var(--admin-primary-color);
            color: var(--header-bg-color);
            border-color: var(--admin-primary-color);
        }

        .header-nav a.current-page:hover {
            background-color: var(--admin-primary-dark);
            color: var(--header-bg-color);
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-left: auto;
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
            color: var(--header-link-color);
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
            border: 2px solid var(--header-link-color);
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
            min-width: 300px;
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
            color: var(--text-color);
        }

        .profile-role {
            font-size: 0.85rem;
            color: var(--admin-primary-color);
            margin-top: 2px;
            font-weight: 500;
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
            color: var(--text-color);
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            position: relative;
            overflow: hidden;
        }

        .dropdown-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 0;
            background: linear-gradient(135deg, var(--admin-primary-color), var(--admin-primary-dark));
            transition: width 0.3s ease;
        }

        .dropdown-item:hover {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            color: var(--admin-primary-color);
            transform: translateX(4px);
            padding-left: 19px;
        }

        .dropdown-item:hover::before {
            width: 4px;
        }

        .dropdown-item i {
            width: 16px;
            text-align: center;
            transition: transform 0.3s ease;
        }

        .dropdown-item:hover i {
            transform: scale(1.1);
        }

        .logout-item {
            color: #dc3545 !important;
        }

        .logout-item:hover {
            background-color: #f8d7da !important;
            color: #721c24 !important;
        }

        /* Admin Info Section */
        .admin-info {
            padding: 12px 15px;
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }

        .info-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-color);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-title i {
            color: var(--admin-primary-color);
            font-size: 0.9rem;
        }

        .info-stats {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .stat-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 12px;
            background: linear-gradient(135deg, var(--white) 0%, #f8f9fa 100%);
            border-radius: 6px;
            border: 1px solid #e9ecef;
            font-size: 0.8rem;
            position: relative;
            transition: all 0.3s ease;
            cursor: default;
        }

        .stat-item:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            border-color: var(--admin-primary-color);
        }

        .stat-item.urgent {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border-color: var(--warning-color);
            animation: urgentPulse 2s infinite;
        }

        @keyframes urgentPulse {
            0%, 100% { box-shadow: 0 2px 8px rgba(255, 193, 7, 0.3); }
            50% { box-shadow: 0 4px 16px rgba(255, 193, 7, 0.5); }
        }

        .stat-label {
            color: var(--text-color);
            font-weight: 500;
        }

        .stat-value {
            color: var(--admin-primary-color);
            font-weight: 600;
        }

        .stat-item.urgent .stat-value {
            color: #856404;
            font-weight: 700;
        }

        .quick-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 20px;
            height: 20px;
            background: linear-gradient(135deg, var(--admin-primary-color), var(--admin-primary-dark));
            color: var(--white);
            text-decoration: none;
            font-size: 0.7rem;
            border-radius: 50%;
            margin-left: 8px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .quick-link:hover {
            transform: scale(1.15);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            color: var(--white);
            background: linear-gradient(135deg, var(--admin-primary-dark), #cc9a00);
        }

        /* Reorganized Dropdown Styles */
        .stats-section {
            padding: 16px;
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--admin-primary-color);
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .section-title i {
            color: var(--admin-primary-color);
        }

        .section-title.urgent {
            color: #dc3545;
        }

        .section-title.urgent i {
            color: #dc3545;
            animation: urgentBlink 2s infinite;
        }

        @keyframes urgentBlink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.6; }
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
        }

        .stat-box {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px;
            background: var(--white);
            border-radius: 8px;
            border: 1px solid #e9ecef;
            transition: all 0.2s ease;
        }

        .stat-box:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(255, 193, 7, 0.2);
            border-color: var(--admin-primary-color);
        }

        .stat-box i {
            font-size: 1.2rem;
            color: var(--admin-primary-color);
            width: 20px;
            text-align: center;
        }

        .stat-info {
            display: flex;
            flex-direction: column;
        }

        .stat-number {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--admin-primary-color);
            line-height: 1;
        }

        .stat-label {
            font-size: 0.75rem;
            color: var(--light-text-color);
            font-weight: 500;
        }

        /* Urgent Section */
        .urgent-section {
            padding: 16px;
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border-left: 4px solid var(--warning-color);
        }

        .urgent-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .urgent-task {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            background: rgba(255,255,255,0.8);
            border-radius: 6px;
            text-decoration: none;
            color: #856404;
            transition: all 0.2s ease;
            border: 1px solid rgba(255, 193, 7, 0.3);
        }

        .urgent-task:hover {
            background: var(--white);
            color: #856404;
            transform: translateX(4px);
            box-shadow: 0 2px 8px rgba(255, 193, 7, 0.3);
        }

        .urgent-task i:first-child {
            font-size: 1rem;
            width: 16px;
            text-align: center;
        }

        .urgent-task span {
            flex: 1;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .urgent-task i:last-child {
            font-size: 0.8rem;
            opacity: 0.6;
            transition: all 0.2s ease;
        }

        .urgent-task:hover i:last-child {
            opacity: 1;
            transform: translateX(2px);
        }

        /* Menu Section */
        .menu-section {
            padding: 0;
        }

        .menu-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: var(--text-color);
            text-decoration: none;
            transition: all 0.2s ease;
            font-size: 0.9rem;
            font-weight: 500;
            border-left: 3px solid transparent;
        }

        .menu-item:hover {
            background-color: #f8f9fa;
            color: var(--admin-primary-color);
            border-left-color: var(--admin-primary-color);
        }

        .menu-item i {
            width: 16px;
            text-align: center;
            color: var(--admin-primary-color);
        }

        .menu-item.logout {
            color: #dc3545;
            border-top: 1px solid #dee2e6;
            margin-top: 4px;
        }

        .menu-item.logout:hover {
            background-color: #f8d7da;
            color: #721c24;
            border-left-color: #dc3545;
        }

        .menu-item.logout i {
            color: #dc3545;
        }

        /* Mobile responsiveness for reorganized dropdown */
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 8px;
            }
            
            .stat-box {
                padding: 10px;
            }
            
            .section-title {
                font-size: 0.8rem;
            }
        }



        /* Report Button Styles */
        .report-button {
            display: inline-flex;
            align-items: center;
            padding: 8px 15px;
            background-color: #dc3545;
            color: var(--header-link-color);
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
            color: var(--header-link-color);
        }

        /* Responsive Adjustments for Header */
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
            .header-left strong {
                width: 100%;
                text-align: center;
            }
            .report-button {
                margin-top: 10px;
            }
        }
        
        @media (max-width: 768px) {
            .header-title {
                font-size: 1.3rem;
            }
            
            .header-nav a {
                font-size: 0.9rem;
                padding: 8px 12px;
            }
            
            .report-button {
                font-size: 0.85rem;
                padding: 6px 12px;
            }
            
            .profile-button {
                font-size: 0.9rem;
            }
            
            .profile-dropdown-content {
                min-width: 200px;
            }
        }
        /* ----- END OF HEADER STYLES ----- */

        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 2.5rem;
            background: var(--white);
            border-radius: 12px;
            box-shadow: 0 4px 15px var(--shadow-light);
        }

        h2, h3 {
            font-size: 2rem;
            color: var(--text-color);
            margin-bottom: 1.5rem;
            font-weight: 700;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        h2 i, h3 i {
            color: var(--admin-primary-color);
        }
        h3 {
            font-size: 1.7rem;
            margin-top: 2rem;
            justify-content: flex-start;
        }
        h3 i {
            color: var(--text-color);
        }

        p {
            text-align: center;
            margin-bottom: 1.5rem;
            color: var(--light-text-color);
        }

        form.filter-form {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 25px;
            justify-content: center;
            flex-wrap: wrap;
        }
        form.filter-form label {
            font-weight: 600;
            color: var(--text-color);
            white-space: nowrap;
        }
        form.filter-form select {
            padding: 10px 15px;
            font-size: 1rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background-color: var(--white);
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%23666%22%20d%3D%22M287%2069.9H5.4c-6.8%200-10.4%207.2-6.1%2012.7l138.8%20140c4.3%204.3%2011.3%204.3%2015.6%200l138.8-140c4.3-5.5.7-12.7-6.1-12.7z%22%2F%3E%3C%2Fsvg%3E');
            background-repeat: no-repeat;
            background-position: right 10px top 50%;
            background-size: 12px auto;
            cursor: pointer;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        form.filter-form select:focus {
            border-color: var(--admin-primary-color);
            box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.2);
            outline: none;
        }

        /* Category Section Styles */
        .items-section {
            margin-bottom: 3rem;
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 1.5rem;
            padding: 15px 20px;
            background: linear-gradient(135deg, var(--admin-primary-color) 0%, var(--admin-primary-dark) 100%);
            color: var(--white);
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(255, 193, 7, 0.3);
        }

        .section-header.pending {
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
            box-shadow: 0 4px 12px rgba(243, 156, 18, 0.3);
        }

        .section-header.approved {
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
            box-shadow: 0 4px 12px rgba(39, 174, 96, 0.3);
        }

        .section-header h3 {
            margin: 0;
            font-size: 1.4rem;
            font-weight: 700;
        }

        .section-count {
            background: rgba(255, 255, 255, 0.2);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            margin-left: auto;
        }

        .items-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }

        .item-card {
            background: var(--white);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            overflow: hidden;
            padding: 15px;
            text-align: center;
            position: relative;
            box-shadow: 0 4px 10px var(--shadow-light);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .item-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 15px var(--shadow-medium);
        }

        .item-card img {
            width: 100%;
            height: 180px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 10px;
        }

        .item-card h4 {
            margin: 10px 0 5px;
            font-size: 1.2rem;
            color: var(--text-color);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .item-card p {
            font-size: 0.95rem;
            color: var(--light-text-color);
            margin-bottom: 5px;
        }
        .item-card small {
            font-size: 0.85rem;
            color: #999;
        }

        .badge {
            position: absolute;
            top: 15px;
            left: 15px;
            background: var(--warning-color);
            color: var(--white);
            padding: 5px 10px;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .badge.approved {
            background: var(--success-color) !important;
        }

        .actions {
            margin-top: 15px;
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 8px;
        }
        .actions a {
            padding: 8px 12px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            transition: background-color 0.2s ease, color 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .actions a.view-detail {
            background-color: var(--button-view);
            color: var(--white);
        }
        .actions a.view-detail:hover {
            background-color: #0056b3;
        }
        .actions a.approve-btn {
            background-color: var(--button-approve);
            color: var(--white);
        }
        .actions a.approve-btn:hover {
            background-color: #218838;
        }
        .actions a.edit-btn {
            background-color: var(--button-edit);
            color: var(--white);
        }
        .actions a.edit-btn:hover {
            background-color: #e0a800;
        }
        .actions a.delete-btn {
            background-color: var(--button-delete);
            color: var(--white);
        }
        .actions a.delete-btn:hover {
            background-color: #c82333;
        }

        .no-items-message {
            text-align: center;
            padding: 30px;
            color: var(--light-text-color);
            font-size: 1.1rem;
            background: var(--bg-light);
            border-radius: 8px;
            border: 1px dashed var(--border-color);
            margin-top: 20px;
        }

        .no-items-message i {
            display: block;
            margin-bottom: 10px;
        }

        .no-items-message p {
            margin: 0;
            font-weight: 500;
        }

        footer {
            text-align: center;
            padding: 20px;
            margin-top: 30px;
            color: var(--light-text-color);
            font-size: 0.9rem;
        }

    </style>
</head>
<body>
<header>
    <div class="header-left">
        <div class="header-title">
            <i class="fas fa-user-shield"></i>
            <span>ผู้ดูแลระบบ</span>
        </div>
        <nav class="header-nav">
            <div class="menu-group">
                <a href="admin_main.php" class="current-page"><i class="fas fa-home"></i> หน้าหลัก</a>
                <a href="manage_users.php"><i class="fas fa-users"></i> จัดการผู้ใช้</a>
            </div>
            <div class="menu-group">
                <a href="manage_items.php"><i class="fas fa-boxes"></i> จัดการรายการ</a>
                <a href="manage_categories.php"><i class="fas fa-tags"></i> หมวดหมู่</a>
                <a href="manage_announcements.php"><i class="fas fa-bullhorn"></i> ประกาศ</a>
                <a href="manage_reviews.php"><i class="fas fa-comments"></i> รีวิว</a>
            </div>
            <div class="menu-group">
                <a href="exchange_history.php"><i class="fas fa-history"></i> ประวัติแลกเปลี่ยน</a>
                <a href="exchange_report.php"><i class="fas fa-chart-line"></i> รายงานสรุป</a>
            </div>
            
            <!-- Report Issues Button -->
            <a href="admin_reports.php" class="report-button">
                <i class="fas fa-flag"></i> รายงานปัญหา
            </a>
            
            <!-- Profile Dropdown -->
            <div class="profile-dropdown">
                <button class="profile-button" onclick="toggleProfileDropdown()">
                    <img src="<?php echo htmlspecialchars($profileImageHeader); ?>" alt="รูปโปรไฟล์">
                    <span><?php echo htmlspecialchars($username); ?></span>
                    <i class="fas fa-chevron-down"></i>
                </button>
                <div class="profile-dropdown-content" id="profileDropdown">
                    <!-- Profile Section -->
                    <div class="profile-info">
                        <img src="<?php echo htmlspecialchars($profileImageHeader); ?>" alt="รูปโปรไฟล์">
                        <div>
                            <div class="profile-name"><?php echo htmlspecialchars($username); ?></div>
                            <div class="profile-role">ผู้ดูแลระบบ</div>
                        </div>
                    </div>
                    
                    <!-- System Statistics -->
                    <div class="stats-section">
                        <div class="section-title">
                            <i class="fas fa-chart-bar"></i> สถิติระบบ
                        </div>
                        <div class="stats-grid">
                            <div class="stat-box">
                                <i class="fas fa-users"></i>
                                <div class="stat-info">
                                    <span class="stat-number"><?php echo $totalUsers; ?></span>
                                    <span class="stat-label">ผู้ใช้</span>
                                </div>
                            </div>
                            <div class="stat-box">
                                <i class="fas fa-boxes"></i>
                                <div class="stat-info">
                                    <span class="stat-number"><?php echo $totalItems; ?></span>
                                    <span class="stat-label">สินค้า</span>
                                </div>
                            </div>
                            <div class="stat-box">
                                <i class="fas fa-exchange-alt"></i>
                                <div class="stat-info">
                                    <span class="stat-number"><?php echo $totalExchanges; ?></span>
                                    <span class="stat-label">แลกเปลี่ยน</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Urgent Tasks -->
                    <?php if ($pendingItems > 0 || $recentReports > 0): ?>
                    <div class="urgent-section">
                        <div class="section-title urgent">
                            <i class="fas fa-exclamation-triangle"></i> ต้องดำเนินการ
                        </div>
                        <div class="urgent-list">
                            <?php if ($pendingItems > 0): ?>
                            <a href="admin_main.php?filter=pending" class="urgent-task">
                                <i class="fas fa-clock"></i>
                                <span>รออนุมัติ <?php echo $pendingItems; ?> รายการ</span>
                                <i class="fas fa-arrow-right"></i>
                            </a>
                            <?php endif; ?>
                            
                            <?php if ($recentReports > 0): ?>
                            <a href="admin_reports.php" class="urgent-task">
                                <i class="fas fa-flag"></i>
                                <span>รายงานใหม่ <?php echo $recentReports; ?> รายการ</span>
                                <i class="fas fa-arrow-right"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="dropdown-divider"></div>
                    
                    <!-- Navigation Menu -->
                    <div class="menu-section">
                        <a href="edit_profile.php" class="menu-item">
                            <i class="fas fa-user-cog"></i>
                            <span>จัดการโปรไฟล์</span>
                        </a>
                        <a href="exchange_report.php" class="menu-item">
                            <i class="fas fa-chart-line"></i>
                            <span>รายงานสรุป</span>
                        </a>
                    </div>
                    
                    <div class="dropdown-divider"></div>
                    
                    <!-- Logout -->
                    <a href="../logout.php" class="menu-item logout">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>ออกจากระบบ</span>
                    </a>
                </div>
            </div>
        </nav>
    </div>
</header>


<main class="container">
    <h2><i class="fas fa-home"></i> หน้าหลักผู้ดูแลระบบ</h2>

    <form class="filter-form" method="get" action="admin_main.php">
        <label for="category">กรองตามหมวดหมู่:</label>
        <select name="category" id="category" onchange="this.form.submit()">
            <?php
            foreach ($categories as $key => $val) {
                $selected = ($filterCategory === $key) ? "selected" : "";
                echo "<option value=\"" . htmlspecialchars($key) . "\" $selected>" . htmlspecialchars($val) . "</option>";
            }
            ?>
        </select>
        <noscript><input type="submit" value="กรอง"></noscript>
    </form>

    <!-- รายการที่รอการอนุมัติ -->
    <div class="items-section">
        <div class="section-header pending">
            <i class="fas fa-clock"></i>
            <h3>รายการรอการอนุมัติ</h3>
            <span class="section-count"><?php echo $pendingCount; ?> รายการ</span>
        </div>
        
        <?php if ($pendingCount > 0): ?>
            <div class="items-grid">
                <?php while ($row = $resultPending->fetch_assoc()): ?>
                    <div class="item-card">
                        <img src="<?php echo getItemImagePath($row['image']); ?>" alt="<?php echo htmlspecialchars($row['name']); ?>" />
                        <h4 title="<?php echo htmlspecialchars($row['name']); ?>"><?php echo htmlspecialchars($row['name']); ?></h4>
                        <p>หมวดหมู่: <?php echo htmlspecialchars($row['category']); ?></p>
                        <p>เจ้าของ: <?php echo htmlspecialchars($row['owner_name']); ?></p>
                        <small>วันที่เพิ่ม: <?php echo date('d/m/Y', strtotime($row['created_at'])); ?></small>
                        <span class="badge">รอดำเนินการ</span>

                        <div class="actions">
                            <a href="item_detail.php?id=<?php echo $row['id']; ?>" class="view-detail" title="ดูรายละเอียด"><i class="fas fa-eye"></i> ดู</a>
                            <a href="approve_item.php?id=<?php echo $row['id']; ?>" class="approve-btn" title="อนุมัติ"><i class="fas fa-check"></i> อนุมัติ</a>
                            <a href="edit_item.php?id=<?php echo $row['id']; ?>" class="edit-btn" title="แก้ไข"><i class="fas fa-edit"></i> แก้ไข</a>
                            <a href="delete_item.php?id=<?php echo $row['id']; ?>" class="delete-btn" title="ลบ"><i class="fas fa-trash"></i> ลบ</a>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="no-items-message">
                <i class="fas fa-check-circle" style="font-size: 2rem; color: #28a745; margin-bottom: 10px;"></i>
                <p>ไม่มีรายการที่รอการอนุมัติ</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- รายการที่อนุมัติแล้ว -->
    <div class="items-section">
        <div class="section-header approved">
            <i class="fas fa-check-circle"></i>
            <h3>รายการที่อนุมัติแล้ว</h3>
            <span class="section-count"><?php echo $approvedCount; ?> รายการ</span>
        </div>
        
        <?php if ($approvedCount > 0): ?>
            <div class="items-grid">
                <?php while ($row = $resultApproved->fetch_assoc()): ?>
                    <div class="item-card">
                        <img src="<?php echo getItemImagePath($row['image']); ?>" alt="<?php echo htmlspecialchars($row['name']); ?>" />
                        <h4 title="<?php echo htmlspecialchars($row['name']); ?>"><?php echo htmlspecialchars($row['name']); ?></h4>
                        <p>หมวดหมู่: <?php echo htmlspecialchars($row['category']); ?></p>
                        <p>เจ้าของ: <?php echo htmlspecialchars($row['owner_name']); ?></p>
                        <small>วันที่เพิ่ม: <?php echo date('d/m/Y', strtotime($row['created_at'])); ?></small>
                        <span class="badge approved">อนุมัติแล้ว</span>

                        <div class="actions">
                            <a href="item_detail.php?id=<?php echo $row['id']; ?>" class="view-detail" title="ดูรายละเอียด"><i class="fas fa-eye"></i> ดู</a>
                            <a href="edit_item.php?id=<?php echo $row['id']; ?>" class="edit-btn" title="แก้ไข"><i class="fas fa-edit"></i> แก้ไข</a>
                            <a href="delete_item.php?id=<?php echo $row['id']; ?>" class="delete-btn" title="ลบ"><i class="fas fa-trash"></i> ลบ</a>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="no-items-message">
                <i class="fas fa-inbox" style="font-size: 2rem; color: #6c757d; margin-bottom: 10px;"></i>
                <p>ไม่มีรายการที่อนุมัติแล้ว</p>
            </div>
        <?php endif; ?>
    </div>

</main>

<footer>
    &copy; <?php echo date("Y"); ?> ระบบแลกของมหาวิทยาลัย
</footer>

<?php
$stmtPending->close();
$stmtApproved->close();
$conn->close();
?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    function toggleProfileDropdown() {
        const dropdown = document.querySelector('.profile-dropdown');
        dropdown.classList.toggle('active');
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

<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmModalLabel">ยืนยันการดำเนินการ</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
            </div>
            <div class="modal-body" id="modalBodyText">
                คุณแน่ใจว่าจะดำเนินการนี้หรือไม่?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                <a href="#" class="btn btn-danger" id="confirmModalConfirmBtn">ตกลง</a>
            </div>
        </div>
    </div>
</div>

<script>
    // ฟังก์ชันเปิด modal แล้วตั้งลิงก์ในปุ่มยืนยันให้ตรงกับที่ต้องการ
    function showConfirmModal(message, href) {
        document.getElementById('modalBodyText').textContent = message;
        const confirmBtn = document.getElementById('confirmModalConfirmBtn');
        confirmBtn.href = href;
        const modal = new bootstrap.Modal(document.getElementById('confirmModal'));
        modal.show();
    }

    // ตัวอย่างการใช้งานเปลี่ยนปุ่มลบใน .delete-btn เป็นเปิด modal แทน confirm
    document.querySelectorAll('.delete-btn').forEach(btn => {
        btn.addEventListener('click', function(event) {
            event.preventDefault(); // ยกเลิกลิงก์เดิม
            const href = this.getAttribute('href');
            showConfirmModal('คุณแน่ใจว่าจะลบสินค้านี้หรือไม่?', href);
        });
    });
</script>

</body>
</html>