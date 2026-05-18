<?php
session_start();

// ตรวจสอบการล็อกอิน
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.html");
    exit();
}

// เชื่อมต่อฐานข้อมูล
$conn = new mysqli("202.28.34.205", "65011211056", "65011211056", "db65011211056");
if ($conn->connect_error) {
    die("เชื่อมต่อฐานข้อมูลล้มเหลว: " . $conn->connect_error);
}

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];

// ดึงรูปโปรไฟล์และคะแนนของผู้ใช้
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

// ดึงสถิติของผู้ใช้สำหรับ dropdown
$itemCountQuery = $conn->query("SELECT COUNT(*) as count FROM items WHERE owner_id = $userId");
$itemCount = $itemCountQuery->fetch_assoc()['count'];

$exchangeCountQuery = $conn->query("SELECT COUNT(*) as count FROM exchange_requests WHERE requester_id = $userId AND status = 'completed'");
$exchangeCount = $exchangeCountQuery->fetch_assoc()['count'];

// ดึงจำนวนคำขอแลกเปลี่ยนที่ยังไม่ได้ดู (อยู่ในสถานะ pending)
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

// รับค่า itemId จาก URL
$itemId = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($itemId === 0) {
    echo "ไม่พบรหัสสิ่งของ";
    exit();
}

// ดึงข้อมูลสิ่งของ (แก้ไข: เปลี่ยนจาก price เป็น estimated_price)
$stmtItem = $conn->prepare("
    SELECT i.name, i.category, i.created_at, i.description, i.owner_id,
           u.username AS fullname,
           i.status, i.exchange_for, i.contact_info, i.location, i.estimated_price,
           i.latitude, i.longitude
    FROM items i
    JOIN users u ON i.owner_id = u.id
    WHERE i.id = ?
");
$stmtItem->bind_param("i", $itemId);
$stmtItem->execute();
$resultItem = $stmtItem->get_result();
$item = $resultItem->fetch_assoc();
$stmtItem->close();

if (!$item) {
    echo "ไม่พบรายการสิ่งของ";
    exit();
}

// ดึงรูปภาพ
$stmtImg = $conn->prepare("SELECT image FROM item_images WHERE item_id = ?");
$stmtImg->bind_param("i", $itemId);
$stmtImg->execute();
$resultImg = $stmtImg->get_result();

$images = [];
while ($row = $resultImg->fetch_assoc()) {
    $images[] = $row['image'];
}
$stmtImg->close();

// ถ้าส่งฟอร์มแลกเปลี่ยน/ซื้อ
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $myItemId = isset($_POST['my_item_id']) ? intval($_POST['my_item_id']) : null;
    $status = $item['status'];
    
    // กำหนด item_request_id เป็น NULL ถ้าสถานะเป็น "ขายราคาถูก" หรือ "แจกฟรี"
    $itemRequestId = ($status == 'ขายราคาถูก' || $status == 'แจกฟรี') ? null : $myItemId;

    // ตรวจสอบเงื่อนไขการส่งคำขอสำหรับสถานะ 'แลกเปลี่ยน'
    if ($status == 'แลกเปลี่ยน' && ($myItemId === null || $myItemId === 0)) {
        echo "<script>alert('กรุณาเลือกของของคุณเพื่อแลกเปลี่ยน'); window.location='item_detail.php?id={$itemId}';</script>";
        exit();
    }
    
    // ตรวจสอบว่ามี itemRequestId ที่ถูกต้องสำหรับสถานะแลกเปลี่ยนหรือไม่
    if ($status == 'แลกเปลี่ยน' && $itemRequestId === null) {
        echo "<script>alert('เกิดข้อผิดพลาด: ข้อมูลของที่ต้องการแลกไม่ถูกต้อง'); window.location='item_detail.php?id={$itemId}';</script>";
        exit();
    }

    // แก้ไข: เพิ่ม receiver_id เข้าไปในการ INSERT
    $receiverId = $item['owner_id'];
    $stmtRequest = $conn->prepare("INSERT INTO exchange_requests (item_owner_id, item_request_id, requester_id, receiver_id, status) VALUES (?, ?, ?, ?, 'pending')");
    $stmtRequest->bind_param("iiii", $itemId, $itemRequestId, $userId, $receiverId);
    
    if ($stmtRequest->execute()) {
        echo "<script>alert('ส่งคำขอเรียบร้อยแล้ว'); window.location='main.php';</script>";
        exit();
    } else {
        echo "<script>alert('เกิดข้อผิดพลาดในการส่งคำขอ');</script>";
    }
    $stmtRequest->close();
}

// ดึงสิ่งของของผู้ใช้เองสำหรับฟอร์ม
$myItems = [];
$stmtMyItems = $conn->prepare("
    SELECT id, name
    FROM items 
    WHERE owner_id = ? 
    AND is_available = 1 
    AND is_exchanged = 0
");
$stmtMyItems->bind_param("i", $userId);
$stmtMyItems->execute();
$resultMyItems = $stmtMyItems->get_result();

while ($row = $resultMyItems->fetch_assoc()) {
    $myItems[] = $row;
}
$stmtMyItems->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายละเอียดสิ่งของ - <?php echo htmlspecialchars($item['name']); ?></title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <style>
        /* CSS เดิมของคุณ */
        :root {
            --primary-color: #FFC107; /* Your chosen Amber color */
            --primary-dark: #FFA000;  /* Slightly darker Amber */
            --primary-light: #FFD54F; /* Lighter Amber for accents */
            --text-color: #333;
            --text-dark: #333;
            --text-muted: #666;
            --light-text-color: #666;
            --border-color: #e0e0e0;
            --bg-light: #f5f5f5;
            --white: #fff;
            --shadow-light: rgba(0,0,0,0.08);
            --shadow-medium: rgba(0,0,0,0.12);
            --success-color: #28a745; /* For Add Item button */
            --danger-color: #dc3545; /* For Report Issue button */
            --exchange-tag-bg: #4CAF50;
            --giveaway-tag-bg: #2196F3;
            --sell-tag-bg: #FF9800;
            --primary: #FFC107;
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

        /* Header & Navbar */
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

        .nav-link-with-badge {
            position: relative;
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -10px;
            background-color: var(--danger-color);
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

        header .header-right {
            display: flex;
            align-items: center;
            gap: 10px;
            white-space: nowrap;
            margin-top: 0;
            min-width: fit-content;
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

        /* Responsive Header */
        @media (max-width: 992px) {
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
                font-size: 1.2rem;
            }
            .report-button {
                margin-top: 10px;
            }
        }
        @media (max-width: 768px) {
            .container {
                padding: 20px;
            }
        }
        @media (max-width: 576px) {
            header {
                padding: 0.8rem 1rem;
            }
            header nav a {
                font-size: 0.85rem;
            }
            .report-button {
                font-size: 0.8rem;
                padding: 6px 10px;
            }
        }

        /* Enhanced Container with Better Organization */
        .container {
            max-width: 1200px;
            margin: 20px auto;
            background: var(--white);
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            display: flex;
            flex-wrap: wrap;
            padding: 30px;
            gap: 40px;
            position: relative;
            overflow: hidden;
        }

        .container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--primary-light), var(--primary-color));
        }

        /* Enhanced Product Media Section */
        .product-media {
            flex: 1 1 500px;
            display: flex;
            flex-direction: column;
            gap: 20px;
            position: relative;
        }

        .main-image-display {
            width: 100%;
            height: 500px;
            object-fit: contain;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
            background: linear-gradient(135deg, #fafafa 0%, #ffffff 100%);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .main-image-display:hover {
            transform: scale(1.02);
            box-shadow: 0 12px 36px rgba(0,0,0,0.15);
        }

        .thumbnail-images {
            display: flex;
            gap: 12px;
            justify-content: center;
            overflow-x: auto;
            padding: 10px 0;
            background: var(--bg-light);
            border-radius: 8px;
            margin-top: 10px;
        }

        .thumbnail-images img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
            border: 3px solid transparent;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .thumbnail-images img:hover,
        .thumbnail-images img.active {
            border-color: var(--primary-color);
            transform: translateY(-4px) scale(1.05);
            box-shadow: 0 8px 20px rgba(255,193,7,0.3);
        }

        /* Enhanced Product Details Section */
        .product-details {
            flex: 1 1 500px;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .product-title {
            font-size: 2.8em;
            color: var(--text-color);
            font-weight: 700;
            margin-bottom: 15px;
            background: linear-gradient(135deg, var(--text-color) 0%, var(--primary-color) 100%);
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            line-height: 1.2;
        }

        .price-display {
            font-size: 3em;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-weight: 800;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .price-display::before {
            content: '💰';
            font-size: 0.8em;
            filter: none;
            -webkit-text-fill-color: initial;
        }

        /* Enhanced Status Badge */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            border-radius: 25px;
            font-weight: 600;
            font-size: 1.1em;
            margin-bottom: 20px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.1);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-badge.แลกเปลี่ยน {
            background: linear-gradient(135deg, var(--exchange-tag-bg), #45a049);
            color: white;
        }

        .status-badge.แจกฟรี {
            background: linear-gradient(135deg, var(--giveaway-tag-bg), #1976d2);
            color: white;
        }

        .status-badge.ขายราคาถูก {
            background: linear-gradient(135deg, var(--sell-tag-bg), #f57c00);
            color: white;
        }

        /* Enhanced Info Cards */
        .info-cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 16px;
            margin-bottom: 25px;
        }

        .info-card {
            background: linear-gradient(135deg, rgba(255,193,7,0.05) 0%, rgba(255,255,255,1) 100%);
            padding: 20px;
            border-radius: 12px;
            border: 1px solid rgba(255,193,7,0.2);
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .info-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(180deg, var(--primary-color), var(--primary-dark));
        }

        .info-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
            border-color: var(--primary-color);
        }

        .info-card-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
            color: var(--text-color);
            font-weight: 600;
            font-size: 1.1em;
        }

        .info-card-header i {
            color: var(--primary-color);
            font-size: 1.2em;
            width: 24px;
            text-align: center;
        }

        .info-card-content {
            color: var(--text-color);
            font-size: 1em;
            line-height: 1.5;
        }

        .info-card-content.highlight {
            font-weight: 600;
            color: var(--primary-color);
            font-size: 1.1em;
        }

        .info-section {
            background: var(--bg-light);
            padding: 15px;
            border-radius: 6px;
            border: 1px solid var(--border-color);
            margin-bottom: 15px;
        }

        .info-section p {
            margin: 0 0 8px 0;
            font-size: 1.05em;
        }

        .info-section p:last-child {
            margin-bottom: 0;
        }

        .info-section strong {
            color: var(--light-text-color);
            font-weight: 500;
        }

        /* Enhanced Section Headers */
        .section-header {
            font-size: 1.8em;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-weight: 700;
            margin-top: 35px;
            margin-bottom: 20px;
            position: relative;
            padding-left: 40px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .section-header::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 28px;
            height: 28px;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            border-radius: 50%;
            box-shadow: 0 4px 12px rgba(255,193,7,0.3);
        }

        .section-header i {
            position: absolute;
            left: 7px;
            top: 50%;
            transform: translateY(-50%);
            color: white;
            font-size: 0.7em;
            z-index: 1;
        }

        /* Enhanced Owner Info */
        .owner-info {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 20px;
            margin: 20px 0;
            background: linear-gradient(135deg, rgba(76,175,80,0.1) 0%, rgba(255,255,255,1) 100%);
            border-radius: 12px;
            border: 1px solid rgba(76,175,80,0.3);
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
            position: relative;
            overflow: hidden;
        }

        .owner-info::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(180deg, #4CAF50, #45a049);
        }

        .owner-info .owner-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2em;
            box-shadow: 0 4px 16px rgba(255,193,7,0.3);
        }

        .owner-info .username {
            font-weight: 700;
            color: var(--text-color);
            font-size: 1.2em;
        }

        /* Enhanced Description Section */
        .description-section {
            background: linear-gradient(135deg, rgba(33,150,243,0.05) 0%, rgba(255,255,255,1) 100%);
            padding: 25px;
            border-radius: 12px;
            border: 1px solid rgba(33,150,243,0.2);
            margin: 20px 0;
            position: relative;
            overflow: hidden;
        }

        .description-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(180deg, #2196F3, #1976D2);
        }

        .description-section p {
            line-height: 1.8;
            color: var(--text-color);
            font-size: 1.1em;
            margin: 0;
        }

        /* Enhanced Map Section */
        #map {
            height: 400px;
            width: 100%;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            box-shadow: 0 8px 24px rgba(0,0,0,0.1);
            margin-top: 20px;
            overflow: hidden;
        }

        /* Enhanced Exchange Form */
        .exchange-form {
            background: linear-gradient(135deg, rgba(255,193,7,0.05) 0%, rgba(255,255,255,1) 100%);
            padding: 30px;
            border-radius: 16px;
            border: 2px solid rgba(255,193,7,0.3);
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            margin-top: 30px;
            position: relative;
            overflow: hidden;
        }

        .exchange-form::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--primary-light), var(--primary-color));
        }

        .exchange-form::after {
            content: '🔄';
            position: absolute;
            top: 20px;
            right: 20px;
            font-size: 2em;
            opacity: 0.1;
        }

        .exchange-form label {
            display: block;
            margin-bottom: 15px;
            font-weight: 600;
            color: var(--text-color);
            font-size: 1.2em;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .exchange-form label::before {
            content: '📦';
            font-size: 1.2em;
        }

        .exchange-form select {
            width: 100%;
            padding: 16px 20px;
            margin-bottom: 25px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 1.1em;
            background-color: var(--white);
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http://www.w3.org/2000/svg%22%20viewBox%3D%220%200%20256%20512%22%3E%3Cpath%20fill%3D%22%23FFC107%22%20d%3D%22M192%20256L64%20128v256l128-128z%22/%3E%3C/svg%3E');
            background-repeat: no-repeat;
            background-position: right 20px center;
            background-size: 16px;
            cursor: pointer;
        }

        .exchange-form select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(255,193,7,0.2);
        }

        .exchange-form button {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 18px 30px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1.3em;
            font-weight: 700;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            width: 100%;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            box-shadow: 0 6px 20px rgba(255,193,7,0.3);
            position: relative;
            overflow: hidden;
        }

        .exchange-form button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .exchange-form button:hover::before {
            left: 100%;
        }

        .exchange-form button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 28px rgba(255,193,7,0.4);
        }

        .exchange-form button:active {
            transform: translateY(0);
        }

        /* Enhanced Owner Message */
        .owner-message {
            background: linear-gradient(135deg, rgba(156,39,176,0.1) 0%, rgba(255,255,255,1) 100%);
            color: var(--text-color);
            padding: 25px;
            border-radius: 12px;
            font-size: 1.2em;
            font-weight: 600;
            text-align: center;
            border: 2px solid rgba(156,39,176,0.3);
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
            margin-top: 30px;
            position: relative;
            overflow: hidden;
        }

        .owner-message::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #9C27B0, #E91E63, #9C27B0);
        }



        /* Enhanced Back Button */
        .back-button-wrapper {
            max-width: 1200px;
            margin: 20px auto 0;
            padding: 0 30px;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 24px;
            background: linear-gradient(135deg, var(--text-color) 0%, #555 100%);
            color: var(--white);
            text-decoration: none;
            border-radius: 25px;
            font-weight: 600;
            font-size: 1em;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 16px rgba(0,0,0,0.1);
            position: relative;
            overflow: hidden;
        }

        .back-button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .back-button:hover::before {
            left: 100%;
        }

        .back-button:hover {
            transform: translateX(-5px);
            box-shadow: 0 6px 24px rgba(0,0,0,0.15);
            color: var(--white);
        }

        .back-button i {
            transition: transform 0.3s ease;
        }

        .back-button:hover i {
            transform: translateX(-3px);
        }

        /* Enhanced Mobile Responsiveness */
        @media (max-width: 1024px) {
            .container {
                max-width: 95%;
                gap: 30px;
                padding: 25px;
            }
            
            .info-cards-grid {
                grid-template-columns: 1fr;
            }
            
            .product-title {
                font-size: 2.4em;
            }
            
            .price-display {
                font-size: 2.6em;
            }
        }

        @media (max-width: 900px) {
            .container {
                flex-direction: column;
                padding: 25px;
                gap: 25px;
            }
            
            .product-media,
            .product-details {
                flex: 1 1 100%;
            }
            
            .main-image-display {
                height: 400px;
            }
            
            .thumbnail-images img {
                width: 70px;
                height: 70px;
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 20px;
                gap: 20px;
            }
            
            .main-image-display {
                height: 350px;
            }
            
            .product-title {
                font-size: 2em;
                text-align: center;
            }
            
            .price-display {
                font-size: 2.2em;
                justify-content: center;
            }
            
            .section-header {
                font-size: 1.5em;
                padding-left: 35px;
            }
            
            .back-button-wrapper {
                padding: 0 20px;
            }
            
            .thumbnail-images img {
                width: 60px;
                height: 60px;
            }
        }

        @media (max-width: 600px) {
            .container {
                margin: 10px;
                padding: 15px;
                border-radius: 12px;
            }
            
            .main-image-display {
                height: 280px;
            }
            
            .product-title {
                font-size: 1.8em;
            }
            
            .price-display {
                font-size: 2em;
            }
            
            .exchange-form {
                padding: 20px;
            }
            
            .exchange-form button {
                padding: 16px;
                font-size: 1.1em;
            }
            
            .thumbnail-images img {
                width: 50px;
                height: 50px;
            }
        }

        @media (max-width: 400px) {
            .container {
                padding: 12px;
            }
            
            .main-image-display {
                height: 200px;
            }
            
            .thumbnail-images img {
                width: 45px;
                height: 45px;
            }
            
            .product-title {
                font-size: 1.5em;
            }
            
            .price-display {
                font-size: 1.8em;
            }
            
            .back-button-wrapper {
                padding: 0 15px;
            }
            
            .section-header {
                font-size: 1.3em;
                padding-left: 30px;
            }
            
            .exchange-form {
                padding: 15px;
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
            <a href="leaderboard.php"><i class="fas fa-trophy"></i> คะแนนผู้ใช้</a>
            <a href="report_issue.php" class="report-button">
                <i class="fas fa-exclamation-triangle"></i> แจ้งปัญหา
            </a>
            
            <!-- Profile Dropdown -->
            <div class="profile-dropdown">
                <button class="profile-button" onclick="toggleProfileDropdown()">
                    <img src="<?php echo htmlspecialchars($profileImage); ?>" alt="รูปโปรไฟล์">
                    <span><?php echo htmlspecialchars($username); ?></span>
                    <i class="fas fa-chevron-down"></i>
                </button>
                <div class="profile-dropdown-content" id="profileDropdown">
                    <div class="profile-info">
                        <img src="<?php echo htmlspecialchars($profileImage); ?>" alt="รูปโปรไฟล์">
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
                        <?php if ($unreadRequestsCount > 0): ?>
                            <span class="notification-badge-small"><?php echo $unreadRequestsCount; ?></span>
                        <?php endif; ?>
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

<div class="back-button-wrapper">
    <a href="javascript:history.back()" class="back-button">
        <i class="fas fa-arrow-left"></i> ย้อนกลับ
    </a>
</div>

<div class="container">
    <div class="product-media">
    <?php if (count($images) > 0): ?>
        <img id="mainImage" src="../uploads/item/<?php echo htmlspecialchars($images[0]); ?>" class="main-image-display" alt="รูปสิ่งของ">
        <div class="thumbnail-images">
            <?php foreach ($images as $index => $img): ?>
                <img src="../uploads/item/<?php echo htmlspecialchars($img); ?>"
                     onclick="changeMainImage(this)"
                     class="<?php echo ($index === 0) ? 'active' : ''; ?>">
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <img src="https://via.placeholder.com/450x450?text=No+Image" class="main-image-display" alt="ไม่มีรูปภาพ">
        <p style="text-align: center; color: var(--light-text-color);">ยังไม่มีรูปภาพสำหรับสิ่งของนี้</p>
    <?php endif; ?>
    </div>

    <div class="product-details">
    <h1 class="product-title"><?php echo htmlspecialchars($item['name']); ?></h1>

    <!-- Status Badge -->
    <?php if (!empty($item['status'])): ?>
        <div class="status-badge <?php echo htmlspecialchars($item['status']); ?>">
            <?php 
            $statusIcon = '';
            switch($item['status']) {
                case 'แลกเปลี่ยน': $statusIcon = '🔄'; break;
                case 'แจกฟรี': $statusIcon = '🎁'; break;
                case 'ขายราคาถูก': $statusIcon = '💰'; break;
            }
            echo $statusIcon . ' ' . htmlspecialchars($item['status']);
            ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($item['estimated_price']) && $item['estimated_price'] > 0): ?>
        <div class="price-display">
            ฿<?php echo number_format($item['estimated_price'], 2); ?>
        </div>
    <?php endif; ?>

    <!-- Enhanced Info Cards Grid -->
    <div class="info-cards-grid">
        <div class="info-card">
            <div class="info-card-header">
                <i class="fas fa-tags"></i>
                หมวดหมู่
            </div>
            <div class="info-card-content highlight">
                <?php echo htmlspecialchars($item['category']); ?>
            </div>
        </div>

        <div class="info-card">
            <div class="info-card-header">
                <i class="fas fa-calendar-plus"></i>
                วันที่เพิ่ม
            </div>
            <div class="info-card-content">
                <?php echo date("d/m/Y", strtotime($item['created_at'])); ?>
            </div>
        </div>

        <?php if (!empty($item['exchange_for'])): ?>
        <div class="info-card">
            <div class="info-card-header">
                <i class="fas fa-exchange-alt"></i>
                ต้องการแลกเปลี่ยน
            </div>
            <div class="info-card-content highlight">
                <?php echo htmlspecialchars($item['exchange_for']); ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($item['contact_info'])): ?>
        <div class="info-card">
            <div class="info-card-header">
                <i class="fas fa-phone"></i>
                ช่องทางติดต่อ
            </div>
            <div class="info-card-content">
                <?php echo htmlspecialchars($item['contact_info']); ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($item['location'])): ?>
        <div class="info-card">
            <div class="info-card-header">
                <i class="fas fa-map-marker-alt"></i>
                สถานที่นัดรับ
            </div>
            <div class="info-card-content">
                <?php echo htmlspecialchars($item['location']); ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Enhanced Owner Info -->
    <div class="owner-info">
        <div class="owner-avatar">
            👤
        </div>
        <div>
            <strong>เจ้าของ:</strong> 
            <span class="username"><?php echo htmlspecialchars($item['fullname']); ?></span>
        </div>
    </div>

    <?php if (!empty($item['description'])): ?>
        <h3 class="section-header">
            <i class="fas fa-file-alt"></i>
            รายละเอียดสิ่งของ
        </h3>
        <div class="description-section">
            <p><?php echo nl2br(htmlspecialchars($item['description'])); ?></p>
        </div>
    <?php endif; ?>

    <?php if (!empty($item['latitude']) && !empty($item['longitude'])): ?>
        <h3 class="section-header">
            <i class="fas fa-map-marked-alt"></i>
            จุดนัดรับบนแผนที่
        </h3>
        <div id="map"></div>
        <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
        <script>
            document.addEventListener("DOMContentLoaded", function () {
                const lat = <?php echo floatval($item['latitude']); ?>;
                const lng = <?php echo floatval($item['longitude']); ?>;

                const map = L.map('map').setView([lat, lng], 16);

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                }).addTo(map);

                L.marker([lat, lng]).addTo(map)
                    .bindPopup("จุดนัดรับที่นี่")
                    .openPopup();
            });
        </script>
    <?php endif; ?>

    <?php if ($item['owner_id'] != $userId): ?>
        <div class="exchange-form">
            <form method="post">
                <?php if ($item['status'] === 'ขายราคาถูก' || $item['status'] === 'แจกฟรี'): ?>
                    <button type="submit">
                        <?php if ($item['status'] === 'แจกฟรี'): ?>
                            🎁 ส่งคำขอ
                        <?php else: ?>
                            🛒 ส่งคำขอซื้อ
                        <?php endif; ?>
                    </button>
                    <input type="hidden" name="my_item_id" value="0">
                <?php elseif ($item['status'] === 'แลกเปลี่ยน'): ?>
                    <label for="my_item_id">เลือกของของคุณเพื่อแลกเปลี่ยน:</label>
                    <select name="my_item_id" id="my_item_id" required>
                        <option value="">-- เลือกของของคุณ --</option>
                        <?php 
                        if (!empty($myItems)):
                            foreach ($myItems as $myItem): ?>
                                <option value="<?php echo htmlspecialchars($myItem['id']); ?>">
                                    <?php echo htmlspecialchars($myItem['name']); ?>
                                </option>
                        <?php endforeach;
                        endif; ?>
                    </select>
                    <button type="submit">📤 ส่งคำขอแลกเปลี่ยน</button>
                <?php endif; ?>
            </form>
        </div>
    <?php else: ?>
        <p class="owner-message">🔒 คุณเป็นเจ้าของสิ่งของนี้</p>
    <?php endif; ?>

    </div>
</div>

<script>
    function changeMainImage(thumbnail) {
        document.getElementById('mainImage').src = thumbnail.src;
        document.querySelectorAll('.thumbnail-images img').forEach(img => {
            img.classList.remove('active');
        });
        thumbnail.classList.add('active');
    }

    function toggleProfileDropdown() {
        const dropdown = document.querySelector('.profile-dropdown');
        dropdown.classList.toggle('active');
    }

    // Close dropdown when clicking outside
    document.addEventListener('click', function(event) {
        const dropdown = document.querySelector('.profile-dropdown');
        const profileButton = document.querySelector('.profile-button');
        
        if (!dropdown.contains(event.target)) {
            dropdown.classList.remove('active');
        }
    });

    document.addEventListener("DOMContentLoaded", function() {
        const firstThumbnail = document.querySelector('.thumbnail-images img');
        if (firstThumbnail) {
            firstThumbnail.classList.add('active');
        }
    });
</script>

</body>
</html>
