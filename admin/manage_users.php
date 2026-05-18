<?php
// manage_users.php
session_start();

// ตรวจสอบสิทธิ์การเข้าถึง: ต้องเป็น Admin เท่านั้น
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.html");
    exit();
}

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];

$conn = new mysqli("202.28.34.205", "65011211056", "65011211056", "db65011211056");
// เชื่อมต่อฐานข้อมูล
$servername = "202.28.34.205";
$db_username = "65011211056";
$db_password = "65011211056";
$dbname = "db65011211056";

$conn = new mysqli($servername, $db_username, $db_password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
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


// ลบผู้ใช้ (ถ้ามีการส่ง id มาใน URL ?delete_id=)
if (isset($_GET['delete_id'])) {
        // ดึง item id ทั้งหมดที่ผู้ใช้เป็นเจ้าของ
        $item_ids = [];
        $stmt_items = $conn->prepare("SELECT id FROM items WHERE owner_id = ?");
        $stmt_items->bind_param("i", $delete_id);
        $stmt_items->execute();
        $result_items = $stmt_items->get_result();
        while ($row = $result_items->fetch_assoc()) {
            $item_ids[] = $row['id'];
        }
        $stmt_items->close();

        // ลบ exchange_requests ที่ item_owner_id เป็น item id ที่ผู้ใช้เป็นเจ้าของ
        if (!empty($item_ids)) {
            $in = implode(',', array_fill(0, count($item_ids), '?'));
            $types = str_repeat('i', count($item_ids));
            $stmt_del_owner_requests = $conn->prepare("DELETE FROM exchange_requests WHERE item_owner_id IN ($in)");
            $stmt_del_owner_requests->bind_param($types, ...$item_ids);
            $stmt_del_owner_requests->execute();
            $stmt_del_owner_requests->close();

            // ลบ exchange_requests ที่ item_id เป็น item id ที่ผู้ใช้เป็นเจ้าของ
            $stmt_del_item_requests = $conn->prepare("DELETE FROM exchange_requests WHERE item_id IN ($in)");
            $stmt_del_item_requests->bind_param($types, ...$item_ids);
            $stmt_del_item_requests->execute();
            $stmt_del_item_requests->close();
        }

        // ลบ items ที่ owner_id เป็น id ที่ต้องการลบ
        $stmt_del_items = $conn->prepare("DELETE FROM items WHERE owner_id = ?");
        $stmt_del_items->bind_param("i", $delete_id);
        $stmt_del_items->execute();
        $stmt_del_items->close();
    $delete_id = (int)$_GET['delete_id'];

    // ป้องกันลบ admin หรือตัวเอง
    $sql_check_role = "SELECT role FROM users WHERE id = ?";
    $stmt_check = $conn->prepare($sql_check_role);
    $stmt_check->bind_param("i", $delete_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    $user_to_delete = $result_check->fetch_assoc();

    if ($user_to_delete['role'] !== 'admin' && $delete_id != $_SESSION['user_id']) {
    // ลบ exchange_requests ที่ requester_id เป็น id ที่ต้องการลบก่อน
    $stmt_del_requests = $conn->prepare("DELETE FROM exchange_requests WHERE requester_id = ?");
    $stmt_del_requests->bind_param("i", $delete_id);
    $stmt_del_requests->execute();
    $stmt_del_requests->close();

    // ลบ exchange_requests ที่ item_owner_id เป็น id ที่ต้องการลบก่อน
    // ดึง item id ทั้งหมดที่ผู้ใช้เป็นเจ้าของ
    $item_ids = [];
    $stmt_items = $conn->prepare("SELECT id FROM items WHERE owner_id = ?");
    $stmt_items->bind_param("i", $delete_id);
    $stmt_items->execute();
    $result_items = $stmt_items->get_result();
    while ($row = $result_items->fetch_assoc()) {
        $item_ids[] = $row['id'];
    }
    $stmt_items->close();

    // ลบ exchange_requests ที่ item_owner_id เป็น item id ที่ผู้ใช้เป็นเจ้าของ
    if (!empty($item_ids)) {
        $in = implode(',', array_fill(0, count($item_ids), '?'));
        $types = str_repeat('i', count($item_ids));
        $stmt_del_owner_requests = $conn->prepare("DELETE FROM exchange_requests WHERE item_owner_id IN ($in)");
        $stmt_del_owner_requests->bind_param($types, ...$item_ids);
        $stmt_del_owner_requests->execute();
        $stmt_del_owner_requests->close();
    }

    // จากนั้นค่อยลบผู้ใช้
    $sql_delete = "DELETE FROM users WHERE id = ?";
    $stmt_delete = $conn->prepare($sql_delete);
    $stmt_delete->bind_param("i", $delete_id);
    $stmt_delete->execute();
    $stmt_delete->close();
    }
    
    header("Location: manage_users.php");
    exit;
}

// ดึงข้อมูลผู้ใช้ทั้งหมด
$sql = "SELECT id, username, fullname, email, phone, role, profile_image FROM users ORDER BY username ASC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>จัดการผู้ใช้ - ระบบแลกเปลี่ยนสิ่งของ</title>
<link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
    }
    body {
        font-family: 'Kanit', sans-serif;
        background-color: var(--bg-light);
        color: var(--text-color);
        line-height: 1.6;
        margin: 0;
        padding: 0;
    }
    .container {
        max-width: 1200px;
        margin: 2rem auto;
        padding: 2.5rem;
        background: var(--white);
        border-radius: 12px;
        box-shadow: 0 4px 15px var(--shadow-light);
    }
    /* ----- HEADER STYLES ----- */
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
    .header-container {
        display: flex;
        width: 100%;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
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
        gap: 15px;
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
            
            .profile-dropdown-content {
                min-width: 200px;
            }
        }
    @media (max-width: 1200px) {
        .header-nav { margin-left: 0; margin-top: 1rem; width: 100%; justify-content: center; }
    }
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
        .header-left {
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
            padding: 3px 0;
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
        .header-nav, .header-nav .menu-group { flex-direction: column; align-items: stretch; }
    }

    /* ----- PAGE SPECIFIC STYLES ----- */
    h2.mb-4 {
        text-align: center;
        margin-bottom: 1.5rem !important;
        font-size: 2rem;
        font-weight: 700;
    }
    .profile-img {
        width: 50px;
        height: 50px;
        object-fit: cover;
        border-radius: 50%;
    }
    .table-responsive {
        margin-top: 20px;
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
                <a href="admin_main.php"><i class="fas fa-home"></i> หน้าหลัก</a>
                <a href="manage_users.php" class="current-page"><i class="fas fa-users"></i> จัดการผู้ใช้</a>
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

<div class="container">
    <h2 class="mb-4">จัดการผู้ใช้</h2>

    <div class="table-responsive">
        <table class="table table-striped table-bordered align-middle">
            <thead class="table-dark">
                <tr>
                    <th>#</th>
                    <th>รูปภาพ</th>
                    <th>ชื่อผู้ใช้</th>
                    <th>ชื่อ-นามสกุล</th>
                    <th>อีเมล</th>
                    <th>เบอร์โทร</th>
                    <th>บทบาท</th>
                    <th>จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($user = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['id']); ?></td>
                            <td>
                                <?php
                                // ตรวจสอบไฟล์รูปภาพในโฟลเดอร์ uploads/profile
                                $imgPath = __DIR__ . '/../uploads/profile/' . $user['profile_image'];
                                if (!empty($user['profile_image']) && file_exists($imgPath)) {
                                    $imgUrl = '../uploads/profile/' . rawurlencode($user['profile_image']);
                                } else {
                                    $imgUrl = '../uploads/profile/default.png';
                                }
                                ?>
                                <img src="<?php echo $imgUrl; ?>" alt="Profile" class="profile-img" />
                            </td>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo htmlspecialchars($user['fullname'] ?: '-'); ?></td>
                            <td><?php echo htmlspecialchars($user['email'] ?: '-'); ?></td>
                            <td><?php echo htmlspecialchars($user['phone'] ?: '-'); ?></td>
                            <td>
                                <?php
                                // แสดงบทบาทแบบอ่านง่าย
                                if ($user['role'] === 'admin') {
                                    echo '<span class="badge bg-danger">ผู้ดูแลระบบ</span>';
                                } else {
                                    echo '<span class="badge bg-secondary">ผู้ใช้ทั่วไป</span>';
                                }
                                ?>
                            </td>
                            <td>
                                <?php if ($user['role'] !== 'admin' && $user['id'] != $_SESSION['user_id']): ?>
                                    <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-primary">แก้ไข</a>
                                    <a href="manage_users.php?delete_id=<?php echo $user['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('ยืนยันการลบผู้ใช้นี้?');">ลบ</a>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-secondary" disabled>แก้ไข</button>
                                    <button class="btn btn-sm btn-secondary" disabled>ลบ</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="text-center">ไม่มีข้อมูลผู้ใช้</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

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
</body>
</html>
<?php
$conn->close();
?>