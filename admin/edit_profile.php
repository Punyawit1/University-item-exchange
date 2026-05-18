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

// ตัวแปรสำหรับสถานะการแก้ไข
$statusMessage = "";
$statusType = "";

// ส่วนที่ 1: ดึงข้อมูลผู้ใช้ปัจจุบันมาแสดงในฟอร์ม
$stmt = $conn->prepare("SELECT fullname, email, phone, profile_image FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$userProfile = $result->fetch_assoc();
$stmt->close();

if (!$userProfile) {
    // หากไม่พบข้อมูลผู้ใช้
    header("Location: admin_main.php");
    exit();
}

$profileImageHeader = (!empty($userProfile['profile_image']) && file_exists("../uploads/profile/" . $userProfile['profile_image']))
    ? "../uploads/profile/" . $userProfile['profile_image']
    : "../uploads/profile/default.png";

// ส่วนที่ 2: จัดการเมื่อมีการส่งฟอร์มเพื่อแก้ไขข้อมูล
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $newFullname = $_POST['fullname'];
    $newEmail = $_POST['email'];
    $newPhone = $_POST['phone'];
    $newPassword = $_POST['password'];

    // เริ่มต้นคำสั่ง SQL
    $sql = "UPDATE users SET fullname = ?, email = ?, phone = ?";
    $params = [$newFullname, $newEmail, $newPhone];
    $types = "ssi";

    // ตรวจสอบว่ามีการอัปโหลดรูปภาพใหม่หรือไม่
    if (!empty($_FILES['profile_image']['name'])) {
        $targetDir = "../uploads/profile/";
        $fileName = basename($_FILES["profile_image"]["name"]);
        $fileType = pathinfo($fileName, PATHINFO_EXTENSION);
        $newFileName = uniqid() . '.' . $fileType;
        $targetFilePath = $targetDir . $newFileName;

        $allowTypes = array('jpg', 'png', 'jpeg', 'gif');
        if (in_array($fileType, $allowTypes)) {
            if (move_uploaded_file($_FILES["profile_image"]["tmp_name"], $targetFilePath)) {
                // อัปเดตรูปภาพในฐานข้อมูล
                $sql .= ", profile_image = ?";
                $params[] = $newFileName;
                $types .= "s";
            } else {
                $statusMessage = "อัปโหลดรูปภาพไม่สำเร็จ";
                $statusType = "error";
            }
        } else {
            $statusMessage = "ขออภัย, อนุญาตเฉพาะไฟล์ JPG, JPEG, PNG, & GIF เท่านั้น";
            $statusType = "error";
        }
    }

    // ตรวจสอบว่ามีการเปลี่ยนรหัสผ่านหรือไม่
    if (!empty($newPassword)) {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $sql .= ", password = ?";
        $params[] = $hashedPassword;
        $types .= "s";
    }

    $sql .= " WHERE id = ?";
    $params[] = $userId;
    $types .= "i";

    if ($statusType !== "error") {
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param($types, ...$params);
            if ($stmt->execute()) {
                $statusMessage = "แก้ไขข้อมูลส่วนตัวสำเร็จ!";
                $statusType = "success";
                // อัปเดตข้อมูลใน session และโหลดหน้าใหม่
                $_SESSION['username'] = $newFullname;
                header("Refresh: 2; url=edit_profile.php"); // โหลดหน้าใหม่เพื่อให้ข้อมูลอัปเดต
                exit();
            } else {
                $statusMessage = "ไม่สามารถแก้ไขข้อมูลได้: " . $stmt->error;
                $statusType = "error";
            }
            $stmt->close();
        } else {
            $statusMessage = "การเตรียมคำสั่ง SQL ล้มเหลว: " . $conn->error;
            $statusType = "error";
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แก้ไขข้อมูลส่วนตัว | แลกของ</title>
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
        }

        body {
            font-family: 'Kanit', sans-serif;
            background: var(--bg-light);
            color: var(--text-color);
            line-height: 1.6;
            margin: 0;
            padding: 0;
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

        /* ----- MAIN CONTENT STYLES ----- */
        .container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2.5rem;
            background: var(--white);
            border-radius: 12px;
            box-shadow: 0 4px 15px var(--shadow-light);
        }
        .profile-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }
        .profile-img-container {
            position: relative;
            width: 150px;
            height: 150px;
            margin-bottom: 20px;
        }
        .profile-img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--admin-primary-color);
        }
        .profile-img-container .edit-icon {
            position: absolute;
            bottom: 0;
            right: 0;
            background-color: var(--admin-primary-color);
            color: var(--header-bg-color);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 1.2rem;
            cursor: pointer;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            transition: transform 0.2s ease;
        }
        .profile-img-container .edit-icon:hover {
            transform: scale(1.1);
        }
        .form-label {
            font-weight: 600;
        }
        .form-control {
            border-radius: 8px;
            padding: 12px;
            border-color: var(--border-color);
        }
        .form-control:focus {
            box-shadow: 0 0 0 0.25rem rgba(255, 193, 7, 0.25);
            border-color: var(--admin-primary-color);
        }
        .btn-primary {
            background-color: var(--admin-primary-color);
            border-color: var(--admin-primary-color);
            color: var(--header-bg-color);
            font-weight: 600;
            padding: 10px 20px;
            border-radius: 8px;
        }
        .btn-primary:hover {
            background-color: var(--admin-primary-dark);
            border-color: var(--admin-primary-dark);
        }
        .alert-container {
            margin-top: 20px;
        }
        .alert-success { background-color: var(--success-color); color: var(--white); border-color: var(--success-color); }
        .alert-danger { background-color: #dc3545; color: var(--white); border-color: #dc3545; }
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

<main class="container">
    <div class="profile-container">
        <h2><i class="fas fa-user-edit"></i> แก้ไขข้อมูลส่วนตัว</h2>
        
        <?php if (!empty($statusMessage)): ?>
            <div class="alert alert-<?php echo ($statusType == 'success') ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                <?php echo $statusMessage; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <form action="edit_profile.php" method="post" enctype="multipart/form-data" class="w-100">
            <div class="profile-img-container" onclick="document.getElementById('profile_image_upload').click()">
                <img src="<?php echo htmlspecialchars($profileImageHeader); ?>" alt="รูปโปรไฟล์" class="profile-img" />
                <div class="edit-icon"><i class="fas fa-camera"></i></div>
            </div>
            <input type="file" name="profile_image" id="profile_image_upload" style="display:none;" />
            
            <div class="mb-3">
                <label for="fullname" class="form-label">ชื่อ-นามสกุล:</label>
                <input type="text" class="form-control" id="fullname" name="fullname" value="<?php echo htmlspecialchars($userProfile['fullname']); ?>" required />
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">อีเมล:</label>
                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($userProfile['email']); ?>" required />
            </div>
            <div class="mb-3">
                <label for="phone" class="form-label">เบอร์โทรศัพท์:</label>
                <input type="text" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($userProfile['phone']); ?>" />
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">รหัสผ่านใหม่ (ไม่ต้องกรอกหากไม่ต้องการเปลี่ยน):</label>
                <input type="password" class="form-control" id="password" name="password" />
            </div>
            
            <button type="submit" class="btn btn-primary w-100 mt-4">บันทึกการแก้ไข</button>
        </form>
    </div>
</main>

<footer>
    &copy; <?php echo date("Y"); ?> ระบบแลกของมหาวิทยาลัย
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>