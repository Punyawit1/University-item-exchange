<?php
session_start();

// ตรวจสอบสิทธิ์การเข้าถึง: ต้องเป็น Admin เท่านั้น
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.html");
    exit();
}
// ตรวจสอบว่ามี id ของสินค้าส่งมาหรือไม่
if (!isset($_GET['id'])) {
    echo "ไม่พบรหัสสินค้า";
    exit();
}

$itemId = intval($_GET['id']);
$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];

// เชื่อมต่อฐานข้อมูล
$conn = new mysqli("202.28.34.205", "65011211056", "65011211056", "db65011211056");
if ($conn->connect_error) {
    die("เชื่อมต่อล้มเหลว: " . $conn->connect_error);
}

// ดึงรูปโปรไฟล์ของผู้ใช้สำหรับ Header
$stmtProfile = $conn->prepare("SELECT profile_image FROM users WHERE id = ?");
if ($stmtProfile === false) {
    die("Error preparing profile statement: " . $conn->error);
}
$stmtProfile->bind_param("i", $userId);
$stmtProfile->execute();
$resultProfile = $stmtProfile->get_result();
$userProfile = $resultProfile->fetch_assoc();
$profileImageHeader = (!empty($userProfile['profile_image']) && file_exists("../uploads/profile/" . $userProfile['profile_image']))
    ? "../uploads/profile/" . $userProfile['profile_image']
    : "../uploads/profile/default.png";
$stmtProfile->close();

// ดึงรายละเอียดสินค้าและข้อมูลเจ้าของ
$sql = "SELECT i.*, u.fullname, u.username, u.email
        FROM items i
        JOIN users u ON i.owner_id = u.id
        WHERE i.id = ?";
$stmt = $conn->prepare($sql);

if ($stmt === false) {
    die("Error preparing item statement: " . $conn->error);
}

$stmt->bind_param("i", $itemId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "ไม่พบสินค้า";
    exit();
}

$item = $result->fetch_assoc();
$stmt->close();

// โหลดรูปภาพสินค้า
$images = [];
$imgStmt = $conn->prepare("SELECT image FROM item_images WHERE item_id = ?");
if ($imgStmt === false) {
    die("Error preparing image statement: " . $conn->error);
}
$imgStmt->bind_param("i", $itemId);
$imgStmt->execute();
$imgResult = $imgStmt->get_result();
while ($imgRow = $imgResult->fetch_assoc()) {
    $imgPath = "../uploads/item/" . $imgRow['image'];
    if (!empty($imgRow['image']) && file_exists($imgPath)) {
        $images[] = $imgPath;
    }
}
if (empty($images)) {
    $images[] = "../uploads/item/default.png";
}
$imgStmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>รายละเอียดสินค้า (Admin)</title>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
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
            --success-color: #28a745;
            --warning-color: #ffc107;
            --info-color: #007bff;
            --button-view: #007bff;
            --button-approve: #28a745;
            --button-edit: #ffc107;
            --button-delete: #dc3545;
            --card-border: #f0f0f0;
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
        /* --- HEADER STYLES (unchanged) --- */
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
            flex-shrink: 0;
        }
        .user-info img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--admin-primary-color);
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
            header { flex-direction: column; align-items: flex-start; gap: 1rem; }
            .header-left { width: 100%; justify-content: space-between; flex-grow: 0; }
            .header-nav { flex-grow: 0; width: 100%; justify-content: center; gap: 8px; }
        }
        @media (max-width: 768px) {
            .header-nav { flex-direction: column; }
            .header-nav a { width: 100%; text-align: center; }
        }
        /* --- END OF HEADER STYLES --- */
        
        /* --- MAIN CONTENT STYLES --- */
        .container {
            max-width: 900px;
            margin: 20px auto;
            background: var(--white);
            padding: 25px 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px var(--shadow-light);
        }
        .info-section {
            background: var(--bg-light);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid var(--card-border);
        }
        .info-section h3 {
            font-weight: 600;
            color: var(--header-bg-color);
            margin-bottom: 15px;
            border-bottom: 2px solid var(--admin-primary-color);
            display: inline-block;
            padding-bottom: 5px;
        }
        .info-section p {
            margin: 8px 0;
            font-size: 1rem;
            line-height: 1.5;
            color: var(--text-color);
        }
        .info-section p strong {
            color: #222;
            min-width: 150px;
            display: inline-block;
        }
        .info-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        .info-item strong {
            flex-shrink: 0;
        }
        .info-item i {
            font-size: 1.1em;
            color: var(--admin-primary-dark);
            width: 20px;
        }
        .images {
            display: flex;
            gap: 15px;
            overflow-x: auto;
            margin-bottom: 25px;
            justify-content: center;
            padding-bottom: 5px;
            border-bottom: 1px solid var(--border-color);
        }
        .images img {
            width: 220px;
            height: 160px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid var(--card-border);
            flex-shrink: 0;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .images img:hover {
            transform: scale(1.03);
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        h2 {
            color: #2c3e50;
            text-align: center;
            font-weight: 700;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        h2 i {
            color: var(--admin-primary-color);
        }
        .actions {
            text-align: center;
            margin-top: 30px;
            display: flex;
            justify-content: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        .actions a {
            display: inline-block;
            padding: 10px 18px;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            color: #fff;
            cursor: pointer;
            transition: background-color 0.3s ease;
            user-select: none;
        }
        .actions a[href*="approve_item.php"] {
            background-color: var(--success-color);
        }
        .actions a[href*="approve_item.php"]:hover {
            background-color: #218838;
        }
        .actions a[href*="edit_item.php"] {
            background-color: var(--warning-color);
            color: var(--text-color);
        }
        .actions a[href*="edit_item.php"]:hover {
            background-color: #e0a800;
        }
        .actions a.delete {
            background-color: var(--button-delete);
        }
        .actions a.delete:hover {
            background-color: #c82333;
        }
        .back-link {
            display: block;
            text-align: center;
            margin-top: 35px;
            color: #666;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }
        .back-link:hover {
            color: #dc3545;
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
</header>
 
<div class="container">
    <h2><i class="fas fa-box-open"></i> รายละเอียดสินค้า</h2>

    <div class="images">
    <?php foreach ($images as $img): ?>
        <img src="<?php echo htmlspecialchars($img); ?>" alt="รูปสินค้า" />
    <?php endforeach; ?>
    </div>

    <div class="info-section">
        <h3>ข้อมูลสินค้า</h3>
        <p class="info-item"><i class="fas fa-tag"></i> <strong>ชื่อสินค้า:</strong> <?php echo htmlspecialchars($item['name']); ?></p>
        <p class="info-item"><i class="fas fa-list-alt"></i> <strong>หมวดหมู่:</strong> <?php echo htmlspecialchars($item['category']); ?></p>
        <p class="info-item"><i class="fas fa-info-circle"></i> <strong>รายละเอียด:</strong> <?php echo nl2br(htmlspecialchars($item['description'])); ?></p>
        <p class="info-item"><i class="fas fa-calendar-alt"></i> <strong>วันที่ลงประกาศ:</strong> <?php echo date("d/m/Y", strtotime($item['created_at'])); ?></p>
        <p class="info-item"><i class="fas fa-check-circle"></i> <strong>สถานะสินค้า:</strong> 
            <?php if ($item['is_approved'] == 1): ?>
                <span style="color: var(--success-color); font-weight: bold;">อนุมัติแล้ว</span>
            <?php else: ?>
                <span style="color: var(--warning-color); font-weight: bold;">รอตรวจสอบ</span>
            <?php endif; ?>
        </p>
    </div>

    <div class="info-section">
        <h3>ข้อมูลการแลกเปลี่ยน</h3>
        <p class="info-item"><i class="fas fa-exchange-alt"></i> <strong>สิ่งที่ต้องการแลกเปลี่ยน:</strong> <?php echo htmlspecialchars($item['exchange_for']); ?></p>
        <p class="info-item"><i class="fas fa-coins"></i> <strong>ราคา:</strong> <?php echo $item['price'] ? number_format($item['price'], 2) . " บาท" : "-"; ?></p>
        <p class="info-item"><i class="fas fa-phone-alt"></i> <strong>ช่องทางติดต่อ:</strong> <?php echo htmlspecialchars($item['contact_info']); ?></p>
        <p class="info-item"><i class="fas fa-map-marker-alt"></i> <strong>สถานที่นัดรับ:</strong> <?php echo htmlspecialchars($item['location']); ?></p>
    </div>

    <div class="info-section">
        <h3>ข้อมูลเจ้าของ</h3>
        <p class="info-item"><i class="fas fa-user"></i> <strong>ชื่อ:</strong> <?php echo htmlspecialchars($item['fullname']); ?> (<?php echo htmlspecialchars($item['username']); ?>)</p>
        <p class="info-item"><i class="fas fa-envelope"></i> <strong>อีเมล:</strong> <?php echo htmlspecialchars($item['email']); ?></p>
    </div>

    <div class="actions">
        <a href="edit_item.php?id=<?php echo $itemId; ?>" class="btn btn-warning">
            <i class="fas fa-edit"></i> แก้ไข
        </a>
        <?php if ($item['is_approved'] == 0): ?>
            <a href="approve_item.php?id=<?php echo $itemId; ?>" class="btn btn-success">
                <i class="fas fa-check-circle"></i> อนุมัติ
            </a>
        <?php endif; ?>
        <a href="delete_item.php?id=<?php echo $itemId; ?>" class="btn btn-danger" onclick="return confirm('คุณแน่ใจหรือไม่ที่ต้องการลบรายการนี้?');">
            <i class="fas fa-trash-alt"></i> ลบ
        </a>
    </div>

    <a href="manage_items.php" class="back-link"><i class="fas fa-arrow-left"></i> กลับไปหน้าจัดการรายการ</a>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>