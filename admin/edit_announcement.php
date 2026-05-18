<?php
// edit_announcement.php
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


// ตรวจสอบว่ามีค่า ID ส่งมาหรือไม่
if (!isset($_GET['id'])) {
    header("Location: manage_announcements.php");
    exit();
}

$announcementId = intval($_GET['id']);

// --- ส่วนที่ 1: จัดการการอัปเดตข้อมูลเมื่อมีการส่ง POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $message = $_POST['message'];
    
    $sql_update = "UPDATE announcements SET title = ?, message = ? WHERE id = ?";
    $stmt = $conn->prepare($sql_update);
    $stmt->bind_param("ssi", $title, $message, $announcementId);
    
    if ($stmt->execute()) {
        header("Location: manage_announcements.php");
        exit();
    } else {
        echo "Error: " . $stmt->error;
    }
    $stmt->close();
}

// --- ส่วนที่ 2: ดึงข้อมูลเดิมของประกาศเพื่อนำมาแสดงในฟอร์ม ---
$sql_select = "SELECT * FROM announcements WHERE id = ?";
$stmt = $conn->prepare($sql_select);
$stmt->bind_param("i", $announcementId);
$stmt->execute();
$result = $stmt->get_result();
$announcement = $result->fetch_assoc();
$stmt->close();

// ถ้าไม่พบประกาศในฐานข้อมูล
if (!$announcement) {
    echo "ไม่พบประกาศที่ต้องการแก้ไข";
    exit();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>แก้ไขประกาศ</title>
<link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
<style>
    /* ใช้ CSS จาก manage_announcements.php */
    :root {
        --admin-primary-color: #ffc107;
        --admin-primary-dark: #e0a800;
        --header-bg-color: #2c3e50;
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
        gap: 10px;
        flex-wrap: wrap;
    }
    .header-nav a {
        text-decoration: none;
        padding: 10px 15px;
        background-color: transparent;
        color: var(--header-link-color);
        border-radius: 5px;
        display: inline-block;
        white-space: nowrap;
        transition: background-color 0.3s ease, color 0.3s ease;
        font-weight: 500;
        border: 1px solid transparent;
    }
    .header-nav a:hover {
        background-color: rgba(255, 255, 255, 0.1);
        color: var(--admin-primary-color);
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
    h2.mb-4 {
        font-weight: 700;
    }
</style>
</head>
<body>
<header>
    <div class="header-container">
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
                    <a href="admin_reports.php"><i class="fas fa-flag"></i> รายงานปัญหา</a>
                </div>
            </nav>
        </div>
        <div class="header-right">
            <div class="user-info dropdown" data-bs-toggle="dropdown" aria-expanded="false">
                <img src="<?php echo htmlspecialchars($profileImageHeader); ?>" alt="รูปโปรไฟล์" />
                <span class="greeting"><?php echo htmlspecialchars($username); ?></span>
                <i class="fas fa-caret-down"></i>
            </div>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profileDropdown">
                <li><a class="dropdown-item" href="edit_profile.php"><i class="fas fa-user-edit"></i> แก้ไขข้อมูลส่วนตัว</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt"></i> ออกจากระบบ</a></li>
            </ul>
        </div>
    </div>
</header>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-edit"></i> แก้ไขประกาศ</h2>
    </div>
    <form method="POST">
        <div class="mb-3">
            <label for="title" class="form-label">หัวข้อประกาศ:</label>
            <input type="text" class="form-control" id="title" name="title" required maxlength="255" value="<?= htmlspecialchars($announcement['title']) ?>" />
        </div>
        <div class="mb-3">
            <label for="message" class="form-label">ข้อความประกาศ:</label>
            <textarea class="form-control" id="message" name="message" rows="4" required><?= htmlspecialchars($announcement['message']) ?></textarea>
        </div>
        <button type="submit" class="btn btn-success">บันทึกการแก้ไข</button>
        <a href="manage_announcements.php" class="btn btn-secondary">ยกเลิก</a>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
$conn->close();
?>