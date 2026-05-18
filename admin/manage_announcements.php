<?php
// manage_announcements.php
session_start();

// ตรวจสอบสิทธิ์การเข้าถึงว่าเป็น 'admin'
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


// --- ส่วนที่ 1: จัดการการลบประกาศ ---
if (isset($_GET['delete_id'])) {
    $id = intval($_GET['delete_id']);
    $sql_delete = "DELETE FROM announcements WHERE id = ?";
    $stmt = $conn->prepare($sql_delete);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: manage_announcements.php");
    exit();
}

// --- ส่วนที่ 2: จัดการการสลับสถานะ (Toggle) ---
if (isset($_GET['toggle_id'])) {
    $id = intval($_GET['toggle_id']);
    $sql_toggle = "UPDATE announcements SET is_active = NOT is_active WHERE id = ?";
    $stmt = $conn->prepare($sql_toggle);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: manage_announcements.php");
    exit();
}

// --- ส่วนที่ 3: จัดการการเพิ่มประกาศใหม่ ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['title'], $_POST['message'])) {
    $title = $_POST['title'];
    $message = $_POST['message'];
    $sql_insert = "INSERT INTO announcements (title, message) VALUES (?, ?)";
    $stmt = $conn->prepare($sql_insert);
    $stmt->bind_param("ss", $title, $message);
    $stmt->execute();
    $stmt->close();
    header("Location: manage_announcements.php");
    exit();
}

// --- ส่วนที่ 4: ดึงประกาศทั้งหมดเพื่อแสดงผล ---
$sql = "SELECT * FROM announcements ORDER BY created_at DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>จัดการประกาศระบบ</title>
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
    h2.mb-4 {
        font-weight: 700;
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
                <a href="manage_announcements.php" class="current-page"><i class="fas fa-bullhorn"></i> ประกาศ</a>
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
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-bullhorn"></i> จัดการประกาศ</h2>
    </div>
    <form method="POST" class="mb-4">
        <div class="mb-3">
            <label for="title" class="form-label">หัวข้อประกาศ:</label>
            <input type="text" class="form-control" id="title" name="title" required maxlength="255" />
        </div>
        <div class="mb-3">
            <label for="message" class="form-label">ข้อความประกาศ:</label>
            <textarea class="form-control" id="message" name="message" rows="4" required></textarea>
        </div>
        <button type="submit" class="btn btn-primary">เพิ่มประกาศ</button>
    </form>
    
    <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle">
            <thead class="table-dark">
                <tr>
                    <th>#</th>
                    <th>หัวข้อ</th>
                    <th>ข้อความ</th>
                    <th>วันที่ประกาศ</th>
                    <th>สถานะ</th>
                    <th>จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['id']) ?></td>
                        <td><?= htmlspecialchars($row['title']) ?></td>
                        <td><?= nl2br(htmlspecialchars($row['message'])) ?></td>
                        <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($row['created_at']))) ?></td>
                        <td><?= $row['is_active'] ? '<span class="badge bg-success">แสดง</span>' : '<span class="badge bg-secondary">ซ่อน</span>' ?></td>
                        <td>
                            <div class="btn-group" role="group" aria-label="Announcement Actions">
                                <a href="edit_announcement.php?id=<?= htmlspecialchars($row['id']) ?>" 
                                   class="btn btn-sm btn-info">แก้ไข</a>
                                <a href="?toggle_id=<?= htmlspecialchars($row['id']) ?>" 
                                   class="btn btn-sm <?= $row['is_active'] ? 'btn-danger' : 'btn-success' ?>">
                                    <?= $row['is_active'] ? 'ซ่อน' : 'แสดง' ?>
                                </a>
                                <a href="?delete_id=<?= htmlspecialchars($row['id']) ?>"
                                   class="btn btn-sm btn-warning" onclick="return confirm('ยืนยันการลบประกาศนี้?');">ลบ</a>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="text-center">ไม่มีประกาศ</td></tr>
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