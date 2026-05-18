<?php
// edit_user.php
session_start();

// ตรวจสอบสิทธิ์การเข้าถึง: ต้องเป็น Admin เท่านั้น
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.html");
    exit();
}

// ตรวจสอบว่ามีการส่ง user ID มาหรือไม่
if (!isset($_GET['id'])) {
    header("Location: manage_users.php");
    exit();
}

$edit_user_id = (int)$_GET['id'];
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
$stmtProfile->close();

$profileImageHeader = (!empty($userProfile['profile_image']) && file_exists("../uploads/profile/" . $userProfile['profile_image']))
    ? "../uploads/profile/" . $userProfile['profile_image']
    : "../uploads/profile/default.png";

// ดึงข้อมูลผู้ใช้ที่จะแก้ไข
$stmt = $conn->prepare("SELECT id, username, fullname, email, phone, role, address FROM users WHERE id = ?");
$stmt->bind_param("i", $edit_user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_to_edit = $result->fetch_assoc();
$stmt->close();

if (!$user_to_edit) {
    // ถ้าไม่พบผู้ใช้ ให้กลับไปหน้าจัดการผู้ใช้
    header("Location: manage_users.php");
    exit();
}

// ตรวจสอบว่าผู้ดูแลระบบพยายามแก้ไขตัวเองหรือไม่
$can_edit_role = ($edit_user_id != $_SESSION['user_id']);

// การจัดการเมื่อมีการส่งฟอร์มเพื่ออัปเดตข้อมูล
$message = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // แก้ไขส่วนนี้: ใช้ isset() แทน ??
    $fullname = isset($_POST['fullname']) ? $_POST['fullname'] : null;
    $email = isset($_POST['email']) ? $_POST['email'] : null;
    $phone = isset($_POST['phone']) ? $_POST['phone'] : null;
    $address = isset($_POST['address']) ? $_POST['address'] : null;
    
    $role = $user_to_edit['role']; // ค่าเริ่มต้นเป็นบทบาทเดิม
    if ($can_edit_role) {
        $role = isset($_POST['role']) ? $_POST['role'] : $user_to_edit['role'];
    }

    $stmt_update = $conn->prepare("UPDATE users SET fullname = ?, email = ?, phone = ?, address = ?, role = ? WHERE id = ?");
    $stmt_update->bind_param("sssssi", $fullname, $email, $phone, $address, $role, $edit_user_id);

    if ($stmt_update->execute()) {
        $message = '<div class="alert alert-success mt-4" role="alert">อัปเดตข้อมูลผู้ใช้เรียบร้อยแล้ว!</div>';
        // อัปเดตข้อมูลใน $user_to_edit เพื่อให้แสดงผลล่าสุดในฟอร์ม
        $user_to_edit['fullname'] = $fullname;
        $user_to_edit['email'] = $email;
        $user_to_edit['phone'] = $phone;
        $user_to_edit['address'] = $address;
        $user_to_edit['role'] = $role;
    } else {
        $message = '<div class="alert alert-danger mt-4" role="alert">เกิดข้อผิดพลาดในการอัปเดตข้อมูล: ' . $stmt_update->error . '</div>';
    }
    $stmt_update->close();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>แก้ไขผู้ใช้ - ระบบแลกเปลี่ยนสิ่งของ</title>
<link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
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
        max-width: 1200px;
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
        text-align: center;
        margin-bottom: 1.5rem !important;
        font-size: 2rem;
        font-weight: 700;
    }
    .card {
        padding: 2rem;
        border-radius: 12px;
        box-shadow: 0 4px 15px var(--shadow-light);
    }
    .form-group label {
        font-weight: 600;
    }
    .btn-container {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 20px;
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
    <h2 class="mb-4">แก้ไขข้อมูลผู้ใช้</h2>
    
    <?php echo $message; ?>

    <div class="card p-4">
        <form action="edit_user.php?id=<?php echo htmlspecialchars($edit_user_id); ?>" method="post">
            <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user_to_edit['id']); ?>">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="username" class="form-label">ชื่อผู้ใช้</label>
                    <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($user_to_edit['username']); ?>" disabled>
                </div>
                <div class="col-md-6">
                    <label for="fullname" class="form-label">ชื่อ-นามสกุล</label>
                    <input type="text" class="form-control" id="fullname" name="fullname" value="<?php echo htmlspecialchars($user_to_edit['fullname']); ?>">
                </div>
                <div class="col-md-6">
                    <label for="email" class="form-label">อีเมล</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user_to_edit['email']); ?>">
                </div>
                <div class="col-md-6">
                    <label for="phone" class="form-label">เบอร์โทรศัพท์</label>
                    <input type="text" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($user_to_edit['phone']); ?>">
                </div>
                <div class="col-12">
                    <label for="address" class="form-label">ที่อยู่</label>
                    <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($user_to_edit['address']); ?></textarea>
                </div>
                <div class="col-md-6">
                    <label for="role" class="form-label">บทบาท</label>
                    <select class="form-select" id="role" name="role" <?php echo $can_edit_role ? '' : 'disabled'; ?>>
                        <option value="user" <?php echo ($user_to_edit['role'] == 'user') ? 'selected' : ''; ?>>ผู้ใช้ทั่วไป</option>
                        <option value="admin" <?php echo ($user_to_edit['role'] == 'admin') ? 'selected' : ''; ?>>ผู้ดูแลระบบ</option>
                    </select>
                    <?php if (!$can_edit_role): ?>
                        <div class="form-text text-danger">ไม่สามารถแก้ไขบทบาทของตัวเองได้</div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="btn-container">
                <a href="manage_users.php" class="btn btn-secondary">ย้อนกลับ</a>
                <button type="submit" class="btn btn-primary">บันทึกการเปลี่ยนแปลง</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
$conn->close();
?>