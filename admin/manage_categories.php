<?php
// manage_categories.php
session_start();

// ตรวจสอบสิทธิ์การเข้าถึง: ต้องเป็น Admin เท่านั้น
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.html");
    exit();
}

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];

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


// เพิ่มหมวดหมู่
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_category'])) {
    $newCategory = trim($_POST['new_category']);
    if ($newCategory !== '') {
        $stmt = $conn->prepare("INSERT INTO categories (name) VALUES (?)");
        $stmt->bind_param("s", $newCategory);
        $stmt->execute();
    }
    header("Location: manage_categories.php");
    exit();
}

// ลบหมวดหมู่
if (isset($_GET['delete_id'])) {
    $deleteId = intval($_GET['delete_id']);

    // ตรวจสอบว่าหมวดหมู่ถูกใช้งานอยู่หรือไม่
    $checkStmt = $conn->prepare("SELECT COUNT(*) as total FROM items WHERE category = (SELECT name FROM categories WHERE id = ?)");
    $checkStmt->bind_param("i", $deleteId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result()->fetch_assoc();

    if ($checkResult['total'] == 0) {
        $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->bind_param("i", $deleteId);
        $stmt->execute();
    } else {
        echo "<script>alert('ไม่สามารถลบหมวดหมู่ที่ถูกใช้งานอยู่ได้');</script>";
    }
}

// ดึงหมวดหมู่ทั้งหมด
$categories = $conn->query("SELECT * FROM categories ORDER BY name ASC");
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>จัดการหมวดหมู่ | ระบบแลกเปลี่ยนสิ่งของ</title>
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
            max-width: 800px;
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
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
        }
        .user-info img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--admin-primary-color);
            flex-shrink: 0;
        }
        .user-info .greeting {
            font-weight: 500;
            font-size: 1rem;
            color: var(--white);
            white-space: nowrap;
        }
        .user-info .fa-caret-down {
            color: var(--white);
            font-size: 1.1rem;
            transition: transform 0.3s ease;
        }
        .user-info.show .fa-caret-down {
            transform: rotate(180deg);
        }
        .dropdown-menu {
            background-color: var(--header-bg-color);
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        .dropdown-menu .dropdown-item {
            color: var(--header-link-color);
            padding: 10px 15px;
            font-weight: 500;
            transition: background-color 0.2s ease, color 0.2s ease;
        }
        .dropdown-menu .dropdown-item:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: var(--admin-primary-color);
        }
        .dropdown-menu .dropdown-divider {
            border-color: rgba(255, 255, 255, 0.2);
        }
        @media (max-width: 1200px) {
            .header-nav { margin-left: 0; margin-top: 1rem; width: 100%; justify-content: center; }
        }
        @media (max-width: 992px) {
            .header-container { flex-direction: column; align-items: flex-start; }
            .header-left, .header-right { width: 100%; justify-content: center; margin-left: 0; }
            .header-nav { margin-top: 1rem; }
            .header-right { margin-top: 1rem; }
        }
        @media (max-width: 768px) {
            .header-nav, .header-nav .menu-group { flex-direction: column; align-items: stretch; }
            .header-right { justify-content: center; }
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
            padding: 30px;
            margin-top: 2rem;
        }
        h2 { 
            color: #333; 
            text-align: center;
            font-weight: 600;
            margin-bottom: 1.5rem;
        }
        form { 
            margin-bottom: 20px; 
            display: flex;
            gap: 10px;
        }
        input[type="text"] {
            flex: 1;
            padding: 10px;
            border-radius: 8px;
            border: 1px solid #ccc;
        }
        button {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            background: #2c3e50;
            color: white;
            cursor: pointer;
            transition: background 0.2s;
        }
        button:hover { background: #1c2833; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        th, td {
            padding: 12px;
            border-bottom: 1px solid #eee;
            text-align: left;
        }
        th {
            background: #e9ecef;
            font-weight: 600;
        }
        a.delete-link {
            color: #dc3545;
            text-decoration: none;
            font-weight: bold;
        }
        a.delete-link:hover {
            text-decoration: underline;
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
                <a href="manage_users.php"><i class="fas fa-users"></i> จัดการผู้ใช้</a>
            </div>
            <div class="menu-group">
                <a href="manage_items.php"><i class="fas fa-boxes"></i> จัดการรายการ</a>
                <a href="manage_categories.php" class="current-page"><i class="fas fa-tags"></i> หมวดหมู่</a>
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
    <h2><i class="fas fa-tags"></i> จัดการหมวดหมู่</h2>

    <form method="POST">
        <input type="text" name="new_category" placeholder="เพิ่มหมวดหมู่ใหม่..." class="form-control" required>
        <button type="submit" class="btn btn-primary">เพิ่ม</button>
    </form>

    <div class="table-responsive">
        <table class="table table-striped table-bordered align-middle">
            <thead class="table-dark">
                <tr>
                    <th>#</th>
                    <th>ชื่อหมวดหมู่</th>
                    <th>การจัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php $rowNumber = 1; ?>
                <?php while ($row = $categories->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $rowNumber++; ?></td>
                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                        <td>
                            <a class="delete-link" href="?delete_id=<?php echo $row['id']; ?>" onclick="return confirm('ยืนยันการลบหมวดหมู่นี้?')">
                                <i class="fas fa-trash-alt"></i> ลบ
                            </a>
                        </td>
                    </tr>
                <?php endwhile; ?>
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

<?php $conn->close(); ?>