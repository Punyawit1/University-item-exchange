<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.html");
    exit();
}

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Database connection setup
$conn = new mysqli("202.28.34.205", "65011211056", "65011211056", "db65011211056");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch user's profile image and score for the header
$stmt = $conn->prepare("SELECT profile_image, score FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result_profile = $stmt->get_result();
$user = $result_profile->fetch_assoc();
$stmt->close();

$profileImage = (!empty($user['profile_image']) && file_exists("../uploads/profile/" . $user['profile_image']))
    ? "../uploads/profile/" . $user['profile_image']
    : "../uploads/profile/default.png";

$userScore = $user['score'];

// Get user statistics for dropdown
$itemCountQuery = $conn->query("SELECT COUNT(*) as count FROM items WHERE owner_id = $userId");
$itemCount = $itemCountQuery->fetch_assoc()['count'];

$exchangeCountQuery = $conn->query("SELECT COUNT(*) as count FROM exchange_requests WHERE requester_id = $userId AND status = 'completed'");
$exchangeCount = $exchangeCountQuery->fetch_assoc()['count'];

// Fetch unread exchange requests count for the notification badge
$unreadRequestsCount = 0;
$sql_unread = "SELECT COUNT(*) AS unread_count FROM exchange_requests WHERE item_owner_id IN (SELECT id FROM items WHERE owner_id = ?) AND status = 'pending'";
$stmt_unread = $conn->prepare($sql_unread);
$stmt_unread->bind_param("i", $userId);
$stmt_unread->execute();
$result_unread = $stmt_unread->get_result();
$row_unread = $result_unread->fetch_assoc();
if ($row_unread) {
    $unreadRequestsCount = $row_unread['unread_count'];
}
$stmt_unread->close();

// SQL command to fetch all users and their scores, ordered from highest to lowest
$sql = "SELECT id, fullname, score FROM users ORDER BY score DESC";
$result = $conn->query($sql);

// --- หาอันดับของผู้ใช้ปัจจุบัน ---
$userRank = 0;
if ($result->num_rows > 0) {
    $rank = 1;
    // สร้าง Array ของผู้ใช้ทั้งหมดพร้อมคะแนน เพื่อหาอันดับ
    $allUsers = [];
    $result->data_seek(0); // ย้ายตัวชี้กลับไปเริ่มต้นใหม่
    while($row = $result->fetch_assoc()) {
        if ($row['id'] == $userId) {
            $userRank = $rank;
        }
        $allUsers[] = $row; // เก็บข้อมูลผู้ใช้ทั้งหมดเพื่อนำไปแสดงในตาราง
        $rank++;
    }
}
$result->data_seek(0); // ย้ายตัวชี้กลับไปเริ่มต้นใหม่ (จำเป็นสำหรับการวนซ้ำในตาราง)
// ---------------------------------
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>หน้าคะแนนผู้ใช้ | SwapSpace</title>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <style>
        :root {
            --primary-color: #FFC107;
            --primary-dark: #FFA000;
            --primary-light: #FFD54F;
            --text-color: #333;
            --light-text-color: #666;
            --border-color: #e0e0e0;
            --bg-light: #f5f5f5;
            --white: #fff;
            --shadow-light: rgba(0,0,0,0.08);
            --shadow-medium: rgba(0,0,0,0.12);
            --success-color: #28a745;
            --danger-color: #dc3545;
            --exchange-tag-bg: #4CAF50;
            --giveaway-tag-bg: #2196F3;
            --sell-tag-bg: #FF9800;
            --text-dark: #333;
            --text-muted: #666;
            --primary: #FFC107;
            --user-highlight: #FFF8E1; /* สีพื้นหลังสำหรับเน้นผู้ใช้ปัจจุบัน */
            --user-highlight-border: #FFC107; /* สีเส้นขอบสำหรับเน้นผู้ใช้ปัจจุบัน */
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
            flex-wrap: nowrap;
            box-shadow: 0 2px 8px var(--shadow-medium);
            position: sticky;
            top: 0;
            z-index: 1000;
            min-height: 70px;
        }
        header .header-left {
            display: flex;
            align-items: center;
            gap: 25px;
            flex-wrap: nowrap;
            min-width: fit-content;
        }
        header .header-left strong {
            font-size: 1.5rem;
            font-weight: 700;
            white-space: nowrap;
            min-width: fit-content;
        }
        header nav {
            display: flex;
            flex-wrap: nowrap;
            gap: 8px;
            margin-left: 0;
            align-items: center;
        }
        header nav a {
            color: var(--white);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.85rem;
            padding: 8px 12px;
            border-radius: 6px;
            position: relative;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(15px);
            margin: 0 1px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
            white-space: nowrap;
            min-height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        header nav a:hover {
            color: var(--white);
            background: rgba(255, 255, 255, 0.25);
            border-color: rgba(255, 255, 255, 0.5);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }
        header nav a.active {
            background: rgba(255, 255, 255, 0.35);
            border-color: rgba(255, 255, 255, 0.7);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.25);
            transform: translateY(-1px);
        }
        header nav a.active::after {
            width: 70%;
        }
        header nav a::after {
            content: '';
            position: absolute;
            width: 0;
            height: 3px;
            bottom: 6px;
            left: 50%;
            background-color: var(--white);
            transition: all 0.3s ease-out;
            transform: translateX(-50%);
            border-radius: 2px;
        }
        header nav a:hover::after {
            width: 70%;
        }
        .report-button {
            display: inline-flex;
            align-items: center;
            padding: 8px 12px;
            background: rgba(220, 53, 69, 0.9);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: var(--white);
            font-weight: 600;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            gap: 6px;
            white-space: nowrap;
            backdrop-filter: blur(15px);
            margin: 0 1px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
            min-height: 36px;
        }
        .report-button:hover {
            background: rgba(200, 35, 51, 0.95);
            border-color: rgba(255, 255, 255, 0.5);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            color: var(--white);
        }
        .nav-link-with-badge {
            position: relative;
            display: inline-block;
        }
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -10px;
            background-color: #dc3545;
            color: white;
            font-size: 0.7em;
            font-weight: bold;
            padding: 2px 6px;
            border-radius: 50%;
            line-height: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            min-width: 20px;
            height: 20px;
        }

        /* Profile dropdown styles */
        .profile-dropdown {
            position: relative;
            display: inline-block;
        }

        .profile-button {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: var(--white);
            cursor: pointer;
            padding: 8px 12px;
            border-radius: 6px;
            transition: all 0.3s ease;
            font-size: 0.85rem;
            font-weight: 600;
            backdrop-filter: blur(15px);
            margin: 0 1px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
            white-space: nowrap;
            min-height: 36px;
        }

        .profile-button:hover {
            background: rgba(255, 255, 255, 0.25);
            border-color: rgba(255, 255, 255, 0.5);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
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

        .dropdown-header {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px;
            background-color: #f8f9fa;
        }

        .dropdown-header img {
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

        .dropdown-link {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 15px;
            color: var(--text-dark);
            text-decoration: none;
            transition: background-color 0.3s ease;
            font-size: 0.9rem;
        }

        .dropdown-link:hover {
            background-color: #f8f9fa;
            color: var(--primary);
        }

        .dropdown-link i {
            width: 16px;
            text-align: center;
        }

        .dropdown-logout {
            color: #dc3545 !important;
        }

        .dropdown-logout:hover {
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

        .nav-link-with-badge {
            position: relative;
            display: inline-block;
        }

        .notification-badge {
            position: absolute;
            top: -8px;
            right: -12px;
            background-color: #dc3545;
            color: white;
            font-size: 0.7em;
            font-weight: bold;
            padding: 3px 6px;
            border-radius: 10px;
            line-height: 1;
            min-width: 18px;
            text-align: center;
        }

        @media (max-width: 768px) {
            .profile-dropdown-content {
                min-width: 280px;
                right: -20px;
            }

            .dropdown-header {
                padding: 15px;
            }

            .dropdown-header img {
                width: 50px;
                height: 50px;
            }

            .dropdown-stat {
                padding: 12px 8px;
            }

            .dropdown-stat .number {
                font-size: 1.1rem;
            }

            .dropdown-stat .label {
                font-size: 0.75rem;
            }
        }

        @media (max-width: 576px) {
            .profile-dropdown-content {
                min-width: 260px;
                right: -30px;
            }

            .profile-info .username {
                font-size: 0.9rem;
            }

            .profile-info .score {
                font-size: 0.8rem;
            }
        }
        .container {
            max-width: 900px;
            margin: 2rem auto;
            background: var(--white);
            padding: 3rem;
            border-radius: 16px;
            box-shadow: 0 8px 25px var(--shadow-light);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .page-header {
            text-align: center;
            margin-bottom: 3rem;
            padding-bottom: 2rem;
            border-bottom: 2px solid var(--border-color);
        }
        
        .page-title {
            font-size: 2.5rem;
            color: var(--text-color);
            margin-bottom: 0.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .page-subtitle {
            font-size: 1.1rem;
            color: var(--light-text-color);
            margin-bottom: 1rem;
        }
        
        .leaderboard-stats {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin-bottom: 2rem;
            flex-wrap: wrap; /* ให้หดลงมาในมือถือ */
        }
        
        .stat-card {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            padding: 1rem 1.5rem;
            border-radius: 12px;
            text-align: center;
            min-width: 120px;
            border: 1px solid var(--border-color);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .stat-card.user-score-card {
            background: linear-gradient(135deg, var(--user-highlight), #FFE0B2); /* สีเน้นสำหรับผู้ใช้ปัจจุบัน */
            border: 2px solid var(--user-highlight-border);
            transform: scale(1.05);
            box-shadow: 0 6px 15px rgba(255, 193, 7, 0.3);
        }
        
        .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.25rem;
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: var(--light-text-color);
        }

        .stat-card.user-score-card .stat-number {
            color: var(--primary-dark);
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.1);
        }
        
        .table-container {
            background: var(--white);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border: 1px solid var(--border-color);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
        }
        
        th {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: var(--white);
            padding: 1.25rem 1rem;
            text-align: center;
            font-weight: 600;
            font-size: 1rem;
            border: none;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        td {
            padding: 1rem;
            text-align: center;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: middle;
            font-size: 0.95rem;
        }
        
        tbody tr {
            transition: all 0.3s ease;
        }
        
        tbody tr:nth-child(even) {
            background-color: #fafbfc;
        }
        
        tbody tr:hover {
            background: linear-gradient(135deg, #fff8e1, #fffbf0);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .user-highlight-row {
            background-color: var(--user-highlight) !important;
            border: 2px solid var(--user-highlight-border) !important;
            font-weight: 600;
        }

        .user-highlight-row:hover {
            background-color: #FFE0B2 !important;
            box-shadow: 0 4px 15px rgba(255, 193, 7, 0.4) !important;
            transform: scale(1.01);
        }
        
        .rank-cell {
            font-weight: 700;
            color: var(--primary-color);
            font-size: 1.1rem;
        }
        
        .rank-1 {
            color: #FFD700;
            font-size: 1.3rem;
        }
        
        .rank-1::before {
            content: '🏆 ';
        }
        
        .rank-2 {
            color: #C0C0C0;
            font-size: 1.2rem;
        }
        
        .rank-2::before {
            content: '🥈 ';
        }
        
        .rank-3 {
            color: #CD7F32;
            font-size: 1.15rem;
        }
        
        .rank-3::before {
            content: '🥉 ';
        }

        .username-cell {
            font-weight: 600;
            color: var(--text-color);
        }
        
        .score-cell {
            font-weight: 700;
            color: var(--primary-color);
            font-size: 1.05rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--light-text-color);
            font-size: 1.1rem;
        }
        
        .empty-state i {
            font-size: 3rem;
            color: var(--border-color);
            margin-bottom: 1rem;
        }
        @media (max-width: 992px) {
            .container {
                padding: 2rem;
                margin: 1.5rem;
            }
            
            .page-title {
                font-size: 2.2rem;
            }
            
            .leaderboard-stats {
                gap: 1rem;
            }
            
            .stat-card {
                min-width: 100px;
                padding: 0.8rem 1rem;
            }
            
            .stat-number {
                font-size: 1.5rem;
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
        }
        
        @media (max-width: 768px) {
            .page-title {
                font-size: 1.9rem;
            }
            
            .container {
                padding: 1.5rem;
                margin: 1rem;
            }
            
            .leaderboard-stats {
                flex-direction: column;
                align-items: center;
                gap: 0.8rem;
            }
            
            .stat-card {
                width: 200px;
            }
            
            th {
                padding: 1rem 0.5rem;
                font-size: 0.9rem;
            }
            
            td {
                padding: 0.8rem 0.5rem;
                font-size: 0.9rem;
            }
        }
        
        @media (max-width: 576px) {
            .container {
                padding: 1rem;
                margin: 0.5rem;
            }
            
            .page-title {
                font-size: 1.7rem;
            }
            
            .page-subtitle {
                font-size: 1rem;
            }
            
            th {
                padding: 0.8rem 0.3rem;
                font-size: 0.8rem;
            }
            
            td {
                padding: 0.7rem 0.3rem;
                font-size: 0.85rem;
            }
            
            .rank-1, .rank-2, .rank-3 {
                font-size: 1rem;
            }
            
            header {
                padding: 0.8rem 1rem;
            }
            
            header nav a {
                font-size: 0.85rem;
            }
            
            .notification-badge {
                right: -20px;
            }
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

            <a href="exchange_requests_received.php" class="nav-link-with-badge">
                <i class="fas fa-inbox"></i> คำขอที่คุณได้รับ
                <?php if ($unreadRequestsCount > 0): ?>
                    <span class="notification-badge"><?php echo $unreadRequestsCount; ?></span>
                <?php endif; ?>
            </a>
            <a href="exchange_requests_sent.php"><i class="fas fa-paper-plane"></i> คำขอที่คุณส่ง</a>
            <a href="my_reviews.php"><i class="fas fa-star-half-alt"></i> รีวิวของฉัน</a>
            <a href="reviews_received.php"><i class="fas fa-star"></i> รีวิวที่คุณได้รับ</a>
            <a href="leaderboard.php" class="active"><i class="fas fa-trophy"></i> คะแนนผู้ใช้</a>
            <a href="report_issue.php" class="report-button">
                <i class="fas fa-exclamation-triangle"></i> แจ้งปัญหา
            </a>
            
            <div class="profile-dropdown">
                <button class="profile-button" onclick="toggleProfileDropdown()">
                    <img src="<?php echo htmlspecialchars($profileImage); ?>" alt="รูปโปรไฟล์">
                    <span><?php echo htmlspecialchars($username); ?></span>
                    <i class="fas fa-chevron-down"></i>
                </button>
                <div class="profile-dropdown-content" id="profileDropdown">
                    <div class="dropdown-header">
                        <img src="<?php echo htmlspecialchars($profileImage); ?>" alt="รูปโปรไฟล์">
                        <div>
                            <div class="profile-name"><?php echo htmlspecialchars($username); ?></div>
                            <div class="profile-score">คะแนน: <?php echo number_format($userScore); ?></div>
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
                    <a href="edit_profile.php" class="dropdown-link">
                        <i class="fas fa-user-cog"></i> จัดการโปรไฟล์
                    </a>
                    <a href="my_items.php" class="dropdown-link">
                        <i class="fas fa-box-open"></i> ของฉัน
                        <?php if ($itemCount > 0): ?>
                            <span class="item-count-badge"><?php echo $itemCount; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="exchange_requests_received.php" class="dropdown-link">
                        <i class="fas fa-inbox"></i> คำขอที่ได้รับ
                        <?php if ($unreadRequestsCount > 0): ?>
                            <span class="notification-badge-small"><?php echo $unreadRequestsCount; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="exchange_requests_sent.php" class="dropdown-link">
                        <i class="fas fa-paper-plane"></i> คำขอที่ส่ง
                    </a>
                    <a href="my_reviews.php" class="dropdown-link">
                        <i class="fas fa-star-half-alt"></i> รีวิวของฉัน
                    </a>
                    <a href="reviews_received.php" class="dropdown-link">
                        <i class="fas fa-star"></i> รีวิวที่ได้รับ
                    </a>
                    <a href="leaderboard.php" class="dropdown-link">
                        <i class="fas fa-trophy"></i> คะแนนผู้ใช้
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="add_item.php" class="dropdown-link add-item">
                        <i class="fas fa-plus-circle"></i> เพิ่มของใหม่
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="../logout.php" class="dropdown-link dropdown-logout">
                        <i class="fas fa-sign-out-alt"></i> ออกจากระบบ
                    </a>
                </div>
            </div>
        </nav>
    </div>
</header>

<div class="container">
    <div class="page-header">
        <h1 class="page-title"><i class="fas fa-trophy"></i> อันดับคะแนนผู้ใช้</h1>
        <p class="page-subtitle">ติดตามอันดับคะแนนและความสำเร็จของสมาชิกทุกคนในชุมชน SwapSpace</p>
        
        <?php
        $totalUsersQuery = $conn->query("SELECT COUNT(*) as total FROM users");
        $totalUsers = $totalUsersQuery->fetch_assoc()['total'];
        
        $totalExchangesQuery = $conn->query("SELECT COUNT(*) as total FROM exchange_requests WHERE status = 'completed'");
        $totalExchanges = $totalExchangesQuery->fetch_assoc()['total'];
        
        $totalScoreQuery = $conn->query("SELECT SUM(score) as total FROM users");
        $totalScore = $totalScoreQuery->fetch_assoc()['total'] ?? 0;
        ?>
        
        <div class="leaderboard-stats">
            <div class="stat-card user-score-card">
                <div class="stat-number"><?php echo number_format($userScore); ?></div>
                <div class="stat-label">คะแนนของคุณ</div>
            </div>
            <div class="stat-card user-score-card">
                <div class="stat-number"><?php echo $userRank > 0 ? $userRank : '-'; ?></div>
                <div class="stat-label">อันดับของคุณ</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($totalUsers); ?></div>
                <div class="stat-label">สมาชิกทั้งหมด</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($totalExchanges); ?></div>
                <div class="stat-label">การแลกเปลี่ยนสำเร็จ</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($totalScore); ?></div>
                <div class="stat-label">คะแนนรวมทั้งหมด</div>
            </div>
        </div>
    </div>
    
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th><i class="fas fa-medal"></i> อันดับ</th>
                    <th><i class="fas fa-user"></i> ชื่อผู้ใช้</th>
                    <th><i class="fas fa-star"></i> คะแนน</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if (!empty($allUsers)) {
                    $rank = 1;
                    foreach($allUsers as $row) {
                        $isCurrentUser = ($row['id'] == $userId);
                        $rowClass = $isCurrentUser ? 'user-highlight-row' : '';
                        
                        $rankClass = '';
                        if ($rank == 1) $rankClass = 'rank-1';
                        else if ($rank == 2) $rankClass = 'rank-2';
                        else if ($rank == 3) $rankClass = 'rank-3';
                        
                        echo "<tr class='$rowClass'>";
                        echo "<td class='rank-cell $rankClass'>" . $rank . "</td>";
                        echo "<td class='username-cell'>" . htmlspecialchars($row["fullname"]) . ($isCurrentUser ? " (คุณ)" : "") . "</td>";
                        echo "<td class='score-cell'>" . number_format($row["score"]) . " คะแนน</td>";
                        echo "</tr>";
                        $rank++;
                    }
                } else {
                    echo "<tr><td colspan='3' class='empty-state'>";
                    echo "<i class='fas fa-users'></i><br>ไม่พบข้อมูลผู้ใช้ในระบบ";
                    echo "</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function toggleProfileDropdown() {
    const dropdown = document.querySelector('.profile-dropdown');
    dropdown.classList.toggle('active');
}

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    const dropdown = document.querySelector('.profile-dropdown');
    // ใช้ .profile-button แทน .profile-trigger เพราะไม่มีในโค้ด
    const trigger = document.querySelector('.profile-button'); 
    
    if (!dropdown.contains(event.target)) {
        dropdown.classList.remove('active');
    }
});

// Prevent dropdown from closing when clicking inside
document.addEventListener('click', function(event) {
    if (event.target.closest('.profile-dropdown-content')) {
        event.stopPropagation();
    }
});

// Close dropdown on escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const dropdown = document.querySelector('.profile-dropdown');
        dropdown.classList.remove('active');
    }
});
</script>

</body>
</html>

<?php
$conn->close();
?>