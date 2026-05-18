<?php
// exchange_report.php
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


// รายงานแลกเปลี่ยน 30 วันล่าสุด แยกตามสถานะ
$sql_exchange = "SELECT status, COUNT(*) AS total FROM exchange_requests WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY status";
$result_exchange = $conn->query($sql_exchange);

// รายงานการใช้งานระบบ (นับผู้ใช้ใหม่ 30 วันล่าสุด)
$sql_users = "SELECT COUNT(*) AS new_users FROM users WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
$result_users = $conn->query($sql_users);
$new_users = 0;
if ($result_users && $row = $result_users->fetch_assoc()) {
    $new_users = $row['new_users'];
}

// รายงานสินค้ายอดนิยม (สินค้าที่ถูกขอแลกมากที่สุด 10 อันดับ)
$sql_popular = "SELECT i.id, i.name, COUNT(er.id) AS request_count
                FROM items i
                LEFT JOIN exchange_requests er ON i.id = er.item_request_id
                GROUP BY i.id, i.name
                ORDER BY request_count DESC
                LIMIT 10";
$result_popular = $conn->query($sql_popular);
// เตรียมข้อมูลสำหรับกราฟ: สถานะการแลกเปลี่ยน
$exchangeChartData = [];
if ($result_exchange) {
    // Reset pointer and collect
    $result_exchange->data_seek(0);
    while ($r = $result_exchange->fetch_assoc()) {
        $exchangeChartData[] = $r;
    }
}

// เตรียมข้อมูลสำหรับกราฟ: สินค้ายอดนิยม
$popularChartData = [];
if ($result_popular) {
    while ($r = $result_popular->fetch_assoc()) {
        $popularChartData[] = $r;
    }
}

// รายงานหมวดหมู่ที่ถูกแลกเปลี่ยนมากที่สุด (นับจาก exchange_requests โดยเชื่อมกับ items.category)
$sql_top_categories = "SELECT i.category, COUNT(er.id) AS exchanges_count
                       FROM items i
                       JOIN exchange_requests er ON i.id = er.item_request_id
                       GROUP BY i.category
                       ORDER BY exchanges_count DESC
                       LIMIT 10";
$result_top_categories = $conn->query($sql_top_categories);
$topCategoriesData = [];
if ($result_top_categories) {
    while ($r = $result_top_categories->fetch_assoc()) {
        $topCategoriesData[] = $r;
    }
}

// Key metrics (already partially computed above)
$new_users_recent = $new_users; // ผู้ใช้ใหม่ 30 วัน

// Prepare arrays for table rendering to avoid using exhausted result sets
$exchangeTableRows = $exchangeChartData; // contains status/total
$popularTableRows = $popularChartData; // contains id/name/request_count

?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>รายงานสรุประบบ - ระบบแลกเปลี่ยนสิ่งของ</title>
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
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
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

        .header-nav .menu-group {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-right: 20px;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-left: auto;
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
            background: linear-gradient(135deg, #e8f5e8 0%, #c8e6c9 100%);
            border-left: 4px solid #4caf50;
            color: #2e7d32;
        }

        .profile-info img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #4caf50;
        }

        .profile-name {
            font-weight: 600;
            font-size: 1rem;
            color: #1b5e20;
            text-shadow: 0 1px 2px rgba(76, 175, 80, 0.1);
        }

        .profile-role {
            font-size: 0.85rem;
            color: #388e3c;
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

        .menu-section {
            padding: 0;
            background: linear-gradient(135deg, #f3e5f5 0%, #e1bee7 100%);
        }

        .menu-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: #4a148c;
            text-decoration: none;
            transition: all 0.2s ease;
            font-size: 0.9rem;
            font-weight: 500;
            border-left: 3px solid transparent;
            text-shadow: 0 1px 2px rgba(74, 20, 140, 0.1);
        }

        .menu-item:hover {
            background: linear-gradient(135deg, #e8eaf6 0%, #c5cae9 100%);
            color: #311b92;
            border-left-color: #673ab7;
            transform: translateX(2px);
        }

        .menu-item i {
            width: 16px;
            text-align: center;
            color: #673ab7;
        }

        .menu-item.logout {
            color: #b71c1c;
            border-top: 1px solid #ffcdd2;
            margin-top: 4px;
            background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%);
            text-shadow: 0 1px 2px rgba(183, 28, 28, 0.1);
        }

        .menu-item.logout:hover {
            background: linear-gradient(135deg, #ffcdd2 0%, #ef9a9a 100%);
            color: #d32f2f;
            border-left-color: #f44336;
            transform: translateX(2px);
        }

        .menu-item.logout i {
            color: #d32f2f;
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

        /* PAGE SPECIFIC STYLES */
        .container {
            padding-top: 2.5rem;
            padding-bottom: 2.5rem;
        }
        h2 {
            text-align: center;
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 2rem;
        }
        h4 {
            font-weight: 600;
            margin-bottom: 1rem;
            color: #495057;
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 0.5rem;
        }
        .badge-status-accepted {
            background-color: #28a745;
        }
        .badge-status-pending {
            background-color: #ffc107;
            color: #212529;
        }
        .badge-status-rejected {
            background-color: #dc3545;
        }
        table {
            background: white;
        }
        .btn-back {
            display: block;
            margin: 2rem auto 0;
            width: fit-content;
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
                <a href="exchange_report.php" class="current-page"><i class="fas fa-chart-line"></i> รายงานสรุป</a>
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
    <h2>📊 รายงานสรุประบบแลกเปลี่ยน</h2>

    <!-- Key metrics cards -->
    <div class="row mb-4">
        <div class="col-md-2 col-6 mb-2">
            <div class="card p-3">
                <div class="h6">ผู้ใช้ทั้งหมด</div>
                <div class="h4 fw-bold"><?php echo number_format($totalUsers); ?></div>
            </div>
        </div>
        <div class="col-md-2 col-6 mb-2">
            <div class="card p-3">
                <div class="h6">รายการทั้งหมด</div>
                <div class="h4 fw-bold"><?php echo number_format($totalItems); ?></div>
            </div>
        </div>
        <div class="col-md-2 col-6 mb-2">
            <div class="card p-3">
                <div class="h6">แลกเปลี่ยนสำเร็จ</div>
                <div class="h4 fw-bold"><?php echo number_format($totalExchanges); ?></div>
            </div>
        </div>
        <div class="col-md-2 col-6 mb-2">
            <div class="card p-3">
                <div class="h6">รอยืนยันรายการ</div>
                <div class="h4 fw-bold"><?php echo number_format($pendingItems); ?></div>
            </div>
        </div>
        <div class="col-md-2 col-6 mb-2">
            <div class="card p-3">
                <div class="h6">ผู้ใช้ใหม่ (30 วัน)</div>
                <div class="h4 fw-bold"><?php echo number_format($new_users_recent); ?></div>
            </div>
        </div>
        <div class="col-md-2 col-6 mb-2">
            <div class="card p-3">
                <div class="h6">รายงานปัญหา (24 ชม.)</div>
                <div class="h4 fw-bold"><?php echo number_format($recentReports); ?></div>
            </div>
        </div>
    </div>

    <section class="mb-5">
        <h4>1. รายงานการแลกเปลี่ยน 30 วันล่าสุด</h4>
        <div class="row mb-3">
            <div class="col-md-6">
                <canvas id="exchangeStatusChart" aria-label="Exchange status chart" role="img"></canvas>
            </div>
            <div class="col-md-6">
                <div class="table-responsive">
            <table class="table table-striped table-bordered align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>สถานะ</th>
                        <th>จำนวนครั้ง</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($exchangeTableRows)): ?>
                        <?php foreach ($exchangeTableRows as $row): ?>
                            <tr>
                                <td>
                                    <?php
                                    switch ($row['status']) {
                                        case 'accepted':
                                            echo '<span class="badge badge-status-accepted">ยอมรับแล้ว</span>';
                                            break;
                                        case 'pending':
                                            echo '<span class="badge badge-status-pending">รอดำเนินการ</span>';
                                            break;
                                        case 'rejected':
                                            echo '<span class="badge badge-status-rejected">ถูกปฏิเสธ</span>';
                                            break;
                                        default:
                                            echo htmlspecialchars($row['status']);
                                    }
                                    ?>
                                </td>
                                <td><?= htmlspecialchars($row['total']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="2" class="text-center">ไม่มีข้อมูล</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
                </div>
            </div>
        </div>
    </section>

    <section class="mb-5">
        <h4>2. รายงานการใช้งานระบบ (ผู้ใช้ใหม่ 30 วันล่าสุด)</h4>
        <p>จำนวนผู้ใช้ใหม่: <strong><?= number_format($new_users) ?></strong> คน</p>
    </section>

    <section>
        <h4>3. รายงานสินค้ายอดนิยม (10 อันดับ)</h4>
        <div class="row">
            <div class="col-md-6">
                <canvas id="popularItemsChart" aria-label="Popular items chart" role="img"></canvas>
            </div>
            <div class="col-md-6">
                <div class="table-responsive">
            <table class="table table-striped table-bordered align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>รหัสสินค้า</th>
                        <th>ชื่อสินค้า</th>
                        <th>จำนวนครั้งที่ถูกขอแลก</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($popularTableRows)): ?>
                        <?php foreach ($popularTableRows as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['id']) ?></td>
                                <td><?= htmlspecialchars($row['name']) ?></td>
                                <td><?= htmlspecialchars($row['request_count']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="3" class="text-center">ไม่มีข้อมูล</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
                </div>
            </div>
        </div>
    </section>

    <section class="mt-4 mb-5">
        <h4>4. หมวดหมู่ที่ถูกแลกเปลี่ยนมากที่สุด</h4>
        <div class="row">
            <div class="col-md-6">
                <canvas id="topCategoriesChart" aria-label="Top categories chart" role="img"></canvas>
            </div>
            <div class="col-md-6">
                <div class="table-responsive">
                    <table class="table table-striped table-bordered align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>หมวดหมู่</th>
                                <th>จำนวนการแลก</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($topCategoriesData)): ?>
                                <?php foreach ($topCategoriesData as $ct): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($ct['category']) ?></td>
                                        <td><?= htmlspecialchars($ct['exchanges_count']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="2" class="text-center">ไม่มีข้อมูล</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.3.0/dist/chart.umd.min.js"></script>
<script>
    // รับข้อมูลจาก PHP เป็น JSON
    const exchangeData = <?php echo json_encode($exchangeChartData, JSON_HEX_TAG); ?>;
    const popularData = <?php echo json_encode($popularChartData, JSON_HEX_TAG); ?>;
    const topCategories = <?php echo json_encode($topCategoriesData, JSON_HEX_TAG); ?>;

    // Exchange Status Pie/Doughnut Chart
    (function() {
        const ctx = document.getElementById('exchangeStatusChart').getContext('2d');
        const labels = exchangeData.map(r => {
            switch (r.status) {
                case 'accepted': return 'ยอมรับแล้ว';
                case 'pending': return 'รอดำเนินการ';
                case 'rejected': return 'ถูกปฏิเสธ';
                default: return r.status;
            }
        });
        const data = exchangeData.map(r => parseInt(r.total));
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    backgroundColor: ['#28a745', '#ffc107', '#dc3545', '#6c757d'],
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom' },
                    title: { display: true, text: 'การแลกเปลี่ยน (30 วันล่าสุด)'}
                }
            }
        });
    })();

    // Top Categories Bar Chart
    (function() {
        const ctx = document.getElementById('topCategoriesChart').getContext('2d');
        const labels = topCategories.map(r => r.category);
        const data = topCategories.map(r => parseInt(r.exchanges_count));
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'จำนวนการแลก',
                    data: data,
                    backgroundColor: '#28a745'
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                plugins: { legend: { display: false }, title: { display: true, text: 'หมวดหมู่ที่ถูกแลกเปลี่ยนมากที่สุด'} },
                scales: { x: { beginAtZero: true } }
            }
        });
    })();

    // Popular Items Bar Chart
    (function() {
        const ctx = document.getElementById('popularItemsChart').getContext('2d');
        const labels = popularData.map(r => r.name);
        const data = popularData.map(r => parseInt(r.request_count));
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'จำนวนครั้งที่ถูกขอแลก',
                    data: data,
                    backgroundColor: '#007bff'
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                plugins: { legend: { display: false }, title: { display: true, text: 'สินค้ายอดนิยม (10 อันดับ)'} },
                scales: { x: { beginAtZero: true } }
            }
        });
    })();
</script>
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