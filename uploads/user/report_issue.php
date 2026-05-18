<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.html");
    exit();
}

$userId = $_SESSION['user_id'];
$username = $_SESSION['username']; // ดึง username จาก session

$conn = new mysqli("localhost", "root", "", "item_exchange");
if ($conn->connect_error) {
    die("การเชื่อมต่อฐานข้อมูลล้มเหลว: " . $conn->connect_error);
}

// ดึงรูปโปรไฟล์สำหรับ Header
$stmtProfile = $conn->prepare("SELECT profile_image FROM users WHERE id = ?");
$stmtProfile->bind_param("i", $userId);
$stmtProfile->execute();
$resultProfile = $stmtProfile->get_result();
$userProfile = $resultProfile->fetch_assoc();
$profileImageHeader = (!empty($userProfile['profile_image']) && file_exists("../uploads/profile/" . $userProfile['profile_image']))
    ? "../uploads/profile/" . $userProfile['profile_image']
    : "../uploads/profile/default.png";
$stmtProfile->close();

// ดึงคะแนนผู้ใช้
$stmt_score = $conn->prepare("SELECT score FROM users WHERE id = ?");
$stmt_score->bind_param("i", $userId);
$stmt_score->execute();
$result_score = $stmt_score->get_result();
$userScore = $result_score->fetch_assoc()['score'];
$stmt_score->close();

// ดึงสถิติของผู้ใช้สำหรับ dropdown
$itemCountQuery = $conn->query("SELECT COUNT(*) as count FROM items WHERE owner_id = $userId");
$itemCount = $itemCountQuery->fetch_assoc()['count'];

$exchangeCountQuery = $conn->query("SELECT COUNT(*) as count FROM exchange_requests WHERE requester_id = $userId AND status = 'completed'");
$exchangeCount = $exchangeCountQuery->fetch_assoc()['count'];

$message = "";
$messageType = ""; // 'success', 'error', 'warning'

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $description = trim($_POST['description']);

    if (!empty($description)) {
        $stmt = $conn->prepare("INSERT INTO reports (user_id, description, report_date) VALUES (?, ?, NOW())");
        if ($stmt === false) {
            $message = "❌ เกิดข้อผิดพลาดในการเตรียมคำสั่ง: " . $conn->error;
            $messageType = 'error';
        } else {
            $stmt->bind_param("is", $userId, $description);
            if ($stmt->execute()) {
                $message = "✅ ส่งรายงานเรียบร้อยแล้ว ขอบคุณสำหรับการแจ้งปัญหา";
                $messageType = 'success';
            } else {
                $message = "❌ เกิดข้อผิดพลาดในการส่งรายงาน: " . $stmt->error;
                $messageType = 'error';
            }
            $stmt->close();
        }
    } else {
        $message = "⚠️ กรุณากรอกรายละเอียดปัญหา";
        $messageType = 'warning';
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แจ้งปัญหา | แลกของ</title>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <style>
        :root {
            --primary-color: #FFC107; /* Your chosen Amber color */
            --primary-dark: #FFA000;   /* Slightly darker Amber */
            --primary-light: #FFD54F; /* Lighter Amber for accents */
            --text-color: #333;
            --light-text-color: #666;
            --border-color: #e0e0e0;
            --bg-light: #f5f5f5;
            --white: #fff;
            --shadow-light: rgba(0,0,0,0.08);
            --shadow-medium: rgba(0,0,0,0.12);
            --success-color: #28a745;
            --danger-color: #dc3545;
            --info-color: #007bff;
            --warning-color: #ffc107; /* Use primary color for warning */
            --button-back: #6c757d; /* Grey for back button */
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

        /* Report Issue Button - Header (self-referential, style is for the link to this page) */
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

        .container {
            max-width: 700px;
            margin: 2rem auto;
            padding: 2.5rem;
            background: var(--white);
            border-radius: 12px;
            box-shadow: 0 4px 15px var(--shadow-light);
        }

        h2 {
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
        h2 i {
            color: var(--danger-color); /* Red for alert icon */
        }

        .message {
            margin-bottom: 1.5rem;
            padding: 15px;
            border-radius: 8px;
            font-weight: 500;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .message.warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }
        .message i {
            font-size: 1.2rem;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: var(--text-color);
            font-size: 1rem;
        }

        textarea {
            width: 100%;
            height: 150px;
            font-size: 1rem;
            padding: 12px;
            margin-bottom: 15px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-family: 'Kanit', sans-serif;
            resize: vertical;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        textarea:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(255, 193, 7, 0.2);
            outline: none;
        }

        button[type="submit"] {
            padding: 12px 20px;
            background-color: var(--danger-color);
            color: var(--white);
            font-size: 1.1rem;
            font-weight: 600;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            width: 100%;
            transition: background-color 0.3s ease, transform 0.2s ease, box-shadow 0.3s ease;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        button[type="submit"]:hover {
            background-color: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0,0,0,0.15);
        }

        .back-button {
            display: block; /* Ensures it takes full width if needed */
            margin-top: 20px;
            text-decoration: none;
            background-color: var(--button-back);
            color: var(--white);
            padding: 12px 20px;
            border-radius: 8px;
            text-align: center;
            font-size: 1.1rem;
            font-weight: 600;
            transition: background-color 0.3s ease, transform 0.2s ease, box-shadow 0.3s ease;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .back-button:hover {
            background-color: #5a6268;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        /* Responsive Adjustments */
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
            h2 {
                font-size: 1.5rem;
            }
            button[type="submit"], .back-button {
                font-size: 1rem;
                padding: 10px 15px;
            }
            .report-button {
                font-size: 0.8rem;
                padding: 6px 10px;
            }
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
        <strong><i class="fas fa-handshake"></i> SWAPSPACE</strong>
        <nav>
            <a href="main.php"><i class="fas fa-home"></i> หน้าแรก</a>
            <a href="my_items.php"><i class="fas fa-box-open"></i> ของฉัน</a>
            <a href="recommendations.php"><i class="fas fa-search-plus"></i> แนะนำการแลกเปลี่ยน</a>
            <a href="exchange_requests_received.php"><i class="fas fa-inbox"></i> คำขอที่คุณได้รับ</a>
            <a href="exchange_requests_sent.php"><i class="fas fa-paper-plane"></i> คำขอที่คุณส่ง</a>
            <a href="my_reviews.php"><i class="fas fa-star-half-alt"></i> รีวิวของฉัน</a>
            <a href="reviews_received.php"><i class="fas fa-star"></i> รีวิวที่คุณได้รับ</a>
            <a href="report_issue.php" class="report-button">
                <i class="fas fa-exclamation-triangle"></i> แจ้งปัญหา
            </a>
            
            <!-- Profile Dropdown -->
            <div class="profile-dropdown">
                <button class="profile-button" onclick="toggleProfileDropdown()">
                    <img src="<?php echo htmlspecialchars($profileImageHeader); ?>" alt="รูปโปรไฟล์">
                    <span><?php echo htmlspecialchars($username); ?></span>
                    <i class="fas fa-chevron-down"></i>
                </button>
                <div class="profile-dropdown-content" id="profileDropdown">
                    <div class="profile-info">
                        <img src="<?php echo htmlspecialchars($profileImageHeader); ?>" alt="รูปโปรไฟล์">
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
    <h2><i class="fas fa-exclamation-triangle"></i> แจ้งปัญหาเกี่ยวกับการแลกเปลี่ยน</h2>

    <?php if (!empty($message)): ?>
        <div class="message <?php echo $messageType; ?>">
            <?php
            if ($messageType === 'success') {
                echo '<i class="fas fa-check-circle"></i> ';
            } elseif ($messageType === 'error') {
                echo '<i class="fas fa-times-circle"></i> ';
            } elseif ($messageType === 'warning') {
                echo '<i class="fas fa-exclamation-circle"></i> ';
            }
            echo htmlspecialchars($message);
            ?>
        </div>
    <?php endif; ?>

    <form method="post">
        <label for="description">กรอกรายละเอียดปัญหาของคุณ (เช่น ปัญหาการติดต่อ, การจัดส่ง, รายการไม่ตรงปก):</label>
        <textarea name="description" id="description" rows="7" required placeholder="ตัวอย่าง: ผู้ใช้ ID 123 ไม่ส่งของตามที่ตกลงกันไว้หลังจากการแลกเปลี่ยนสำเร็จไปแล้ว หรือ สินค้าที่ได้รับมีสภาพไม่ตรงตามที่ระบุไว้..."></textarea>
        <button type="submit">
            <i class="fas fa-paper-plane"></i> ส่งรายงาน
        </button>
    </form>

    <a href="main.php" class="back-button">
        <i class="fas fa-arrow-alt-circle-left"></i> กลับหน้าแรก
    </a>
</div>

<footer>
    &copy; <?php echo date("Y"); ?> ระบบแลกเปลี่ยนสิ่งของ. All rights reserved.
</footer>

<script>
    function toggleProfileDropdown() {
        const dropdown = document.querySelector('.profile-dropdown');
        dropdown.classList.toggle('active');
    }

    // Close dropdown when clicking outside
    document.addEventListener('click', function(event) {
        const dropdown = document.querySelector('.profile-dropdown');
        if (!dropdown.contains(event.target)) {
            dropdown.classList.remove('active');
        }
    });
</script>

</body>
</html>

<?php $conn->close(); ?>