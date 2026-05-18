<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.html");
    exit();
}

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];

$conn = new mysqli("localhost", "root", "", "item_exchange");
if ($conn->connect_error) {
    die("เชื่อมต่อฐานข้อมูลล้มเหลว: " . $conn->connect_error);
}

$message = "";
$messageType = ""; // 'success' or 'error'

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

$categoryResult = $conn->query("SELECT name FROM categories ORDER BY name ASC");
$categoryOptions = [];
while ($row = $categoryResult->fetch_assoc()) {
    $categoryOptions[] = $row['name'];
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $category = trim($_POST['category']);
    $price = isset($_POST['price']) && $_POST['price'] !== '' ? floatval($_POST['price']) : NULL;
    $description = trim($_POST['description']);
    $status = trim($_POST['status']);
    $contactName = trim($_POST['contact_name']);
    $contactInfo = trim($_POST['contact_info']);
    $locationText = trim($_POST['location_text']);
    $exchangeFor = trim($_POST['exchange_for']);
        if ($exchangeFor === '') {
            $exchangeFor = NULL; // จะเก็บ NULL ใน DB แทน 0
        }

    $estimatedPrice = isset($_POST['estimated_price']) && $_POST['estimated_price'] !== '' ? floatval($_POST['estimated_price']) : NULL;
    $conditionNotes = trim($_POST['condition_notes']);
    $purchaseYear = isset($_POST['purchase_year']) && $_POST['purchase_year'] !== '' ? intval($_POST['purchase_year']) : NULL;
    $latitude = isset($_POST['latitude']) && $_POST['latitude'] !== '' ? floatval($_POST['latitude']) : NULL;
    $longitude = isset($_POST['longitude']) && $_POST['longitude'] !== '' ? floatval($_POST['longitude']) : NULL;

    if (empty($name) || empty($category) || empty($description) || empty($status) || empty($contactName) || empty($contactInfo) || empty($locationText)) {
        $message = "โปรดกรอกข้อมูลที่จำเป็นทั้งหมด (ชื่อ, หมวดหมู่, รายละเอียด, สถานะ, ชื่อผู้ลงประกาศ, ช่องทางติดต่อ, สถานที่นัดรับ).";
        $messageType = 'error';
    } elseif (empty($_FILES['images']['name'][0])) {
        $message = "กรุณาเลือกรูปภาพอย่างน้อย 1 รูปสำหรับสิ่งของของคุณ.";
        $messageType = 'error';
    } else {
        $stmtInsert = $conn->prepare("INSERT INTO items (owner_id, name, category, price, estimated_price, description, condition_notes, purchase_year, status, contact_name, contact_info, location, latitude, longitude, exchange_for, created_at, is_approved) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 0)");

        if ($stmtInsert === false) {
            $message = "SQL prepare error: " . $conn->error;
            $messageType = 'error';
        } else {
            $stmtInsert->bind_param("issddssisssssds",
                $userId,
                $name,
                $category,
                $price,
                $estimatedPrice,
                $description,
                $conditionNotes,
                $purchaseYear,
                $status,
                $contactName,
                $contactInfo,
                $locationText,
                $latitude,
                $longitude,
                $exchangeFor
            );

            if ($stmtInsert->execute()) {
                $itemId = $stmtInsert->insert_id;

                $targetDir = "../uploads/item/";
                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0755, true);
                }

                $files = $_FILES['images'];
                $numFiles = count($files['name']);
                $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
                $uploadSuccess = true;
                $uploadedCount = 0;

                for ($i = 0; $i < $numFiles; $i++) {
                    // Check if a file was actually uploaded for this index
                    if ($files["error"][$i] === 0 && !empty($files["name"][$i])) {
                        $fileName = time() . "_" . uniqid() . "_" . basename($files["name"][$i]);
                        $targetFilePath = $targetDir . $fileName;
                        $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));

                        // Validate file type
                        if (in_array($fileType, $allowedTypes)) {
                            // Validate file size (e.g., max 5MB)
                            if ($files["size"][$i] > 5 * 1024 * 1024) { // 5 MB
                                $uploadSuccess = false;
                                $message = "รูปภาพ '{$files["name"][$i]}' มีขนาดใหญ่เกินไป (สูงสุด 5MB).";
                                $messageType = 'error';
                                break;
                            }
                            if (move_uploaded_file($files["tmp_name"][$i], $targetFilePath)) {
                                $stmtImg = $conn->prepare("INSERT INTO item_images (item_id, image) VALUES (?, ?)");
                                $stmtImg->bind_param("is", $itemId, $fileName);
                                $stmtImg->execute();
                                $uploadedCount++;
                            } else {
                                $uploadSuccess = false;
                                $message = "เกิดข้อผิดพลาดในการอัปโหลดรูปภาพ '{$files["name"][$i]}'.";
                                $messageType = 'error';
                                break;
                            }
                        } else {
                            $uploadSuccess = false;
                            $message = "ไฟล์ '{$files["name"][$i]}' ไม่ใช่รูปภาพที่รองรับ (JPG, JPEG, PNG, GIF).";
                            $messageType = 'error';
                            break;
                        }
                    }
                }

                if ($uploadSuccess && $uploadedCount > 0) {
                    $message = "เพิ่มรายการสำเร็จ! กรุณารอแอดมินอนุมัติก่อนที่รายการจะปรากฏบนหน้าเว็บไซต์ของคุณ.";
                    $messageType = 'success';
                } elseif ($uploadedCount === 0 && $uploadSuccess) { // No files uploaded despite no error
                    $message = "กรุณาเลือกรูปภาพอย่างน้อย 1 รูปสำหรับสิ่งของของคุณ.";
                    $messageType = 'error';
                }
            } else {
                $message = "เกิดข้อผิดพลาดในการบันทึกข้อมูลสิ่งของ: " . $stmtInsert->error;
                $messageType = 'error';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เพิ่มรายการสิ่งของ | แลกของ</title>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        :root {
            --primary-color: #FFC107;
            --primary-dark: #FFA000;
            --primary-light: #FFD54F;
            --text-color: #333;
            --text-dark: #333;
            --text-muted: #666;
            --light-text-color: #666;
            --border-color: #e0e0e0;
            --bg-light: #f5f5f5;
            --white: #fff;
            --shadow-light: rgba(0,0,0,0.08);
            --shadow-medium: rgba(0,0,0,0.12);
            --success-color: #28a745;
            --danger-color: #dc3545;
            --info-color: #007bff;
            --button-add-image: #17a2b8;
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

        .profile-dropdown.active .profile-dropdown-content,
        .profile-dropdown-content.show {
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

        /* Report Issue Button - Header */
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

        /* Enhanced Container with Better Organization */
        .container {
            max-width: 900px;
            margin: 2rem auto;
            padding: 3rem;
            background: var(--white);
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
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

        /* Enhanced Page Header */
        h2 {
            font-size: 2.5rem;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 2rem;
            font-weight: 700;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            position: relative;
        }
        
        h2::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 3px;
            background: linear-gradient(90deg, var(--primary-color), var(--primary-light));
            border-radius: 2px;
        }
        
        h2 i {
            filter: none;
            -webkit-text-fill-color: initial;
            color: var(--primary-color);
            font-size: 2.2rem;
        }

        /* Enhanced Message Styling */
        .message {
            margin-bottom: 2rem;
            padding: 20px;
            border-radius: 12px;
            font-weight: 500;
            text-align: center;
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-size: 1.1rem;
        }
        .message.success {
            background: linear-gradient(135deg, rgba(40,167,69,0.1) 0%, rgba(255,255,255,1) 100%);
            color: #155724;
            border: 2px solid rgba(40,167,69,0.3);
            position: relative;
            overflow: hidden;
        }
        .message.success::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(180deg, #28a745, #20c997);
        }
        .message.error {
            background: linear-gradient(135deg, rgba(220,53,69,0.1) 0%, rgba(255,255,255,1) 100%);
            color: #721c24;
            border: 2px solid rgba(220,53,69,0.3);
            position: relative;
            overflow: hidden;
        }
        .message.error::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(180deg, #dc3545, #e74c3c);
        }

        /* Enhanced Form Styling */
        form {
            display: grid;
            gap: 25px;
        }

        /* Form Section Grouping */
        .form-section {
            background: linear-gradient(135deg, rgba(255,193,7,0.03) 0%, rgba(255,255,255,1) 100%);
            padding: 25px;
            border-radius: 12px;
            border: 1px solid rgba(255,193,7,0.2);
            position: relative;
            overflow: hidden;
        }

        .form-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(180deg, var(--primary-color), var(--primary-light));
        }

        .form-section-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--text-color);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-section-title i {
            color: var(--primary-color);
            font-size: 1.2rem;
        }

        /* Enhanced Input Labels */
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-color);
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        label .required {
            color: var(--danger-color);
            font-weight: 700;
        }

        label .optional {
            color: var(--text-muted);
            font-weight: 400;
            font-size: 0.9rem;
        }

        /* Enhanced Form Controls */
        input[type="text"],
        input[type="number"],
        textarea,
        select {
            width: 100%;
            padding: 16px 20px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 1.1rem;
            font-family: 'Kanit', sans-serif;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background: var(--white);
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        input[type="text"]:focus,
        input[type="number"]:focus,
        textarea:focus,
        select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(255, 193, 7, 0.2), 0 4px 16px rgba(0,0,0,0.1);
            outline: none;
            transform: translateY(-1px);
        }

        textarea {
            resize: vertical;
            min-height: 120px;
            line-height: 1.6;
        }

        select {
            cursor: pointer;
            background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http://www.w3.org/2000/svg%22%20viewBox%3D%220%200%20256%20512%22%3E%3Cpath%20fill%3D%22%23FFC107%22%20d%3D%22M192%20256L64%20128v256l128-128z%22/%3E%3C/svg%3E');
            background-repeat: no-repeat;
            background-position: right 20px center;
            background-size: 16px;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
        }

        /* Enhanced File Upload Section */
        .file-upload-container {
            background: linear-gradient(135deg, rgba(23,162,184,0.05) 0%, rgba(255,255,255,1) 100%);
            padding: 25px;
            border-radius: 12px;
            border: 2px dashed rgba(23,162,184,0.3);
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .file-upload-container:hover {
            border-color: var(--button-add-image);
            background: linear-gradient(135deg, rgba(23,162,184,0.08) 0%, rgba(255,255,255,1) 100%);
        }

        .file-upload-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(180deg, var(--button-add-image), #20c997);
        }

        #image-upload-wrapper {
            margin-bottom: 15px;
        }

        input[type="file"] {
            padding: 12px 0;
            border: none;
            font-size: 1rem;
            width: 100%;
            margin-bottom: 10px;
        }

        /* Enhanced Map Section */
        .map-container {
            background: linear-gradient(135deg, rgba(0,123,255,0.05) 0%, rgba(255,255,255,1) 100%);
            padding: 25px;
            border-radius: 12px;
            border: 1px solid rgba(0,123,255,0.2);
            position: relative;
            overflow: hidden;
        }

        .map-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(180deg, var(--info-color), #17a2b8);
        }

        #map {
            height: 400px;
            width: 100%;
            margin-top: 15px;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        /* Enhanced Button Styling */
        .btn-add-image {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--button-add-image) 0%, #138496 100%);
            color: var(--white);
            padding: 14px 24px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            gap: 10px;
            box-shadow: 0 4px 16px rgba(23,162,184,0.3);
            position: relative;
            overflow: hidden;
        }

        .btn-add-image::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .btn-add-image:hover::before {
            left: 100%;
        }
        
        .btn-add-image:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 24px rgba(23,162,184,0.4);
        }

        button[type="submit"] {
            margin-top: 30px;
            padding: 18px 30px;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: var(--white);
            font-size: 1.3rem;
            font-weight: 700;
            width: 100%;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 6px 20px rgba(255,193,7,0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            position: relative;
            overflow: hidden;
        }

        button[type="submit"]::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        button[type="submit"]:hover::before {
            left: 100%;
        }
        
        button[type="submit"]:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 28px rgba(255,193,7,0.4);
        }

        button[type="submit"]:active {
            transform: translateY(0);
        }

        /* Enhanced Responsive Design */
        @media (max-width: 1024px) {
            .container {
                max-width: 95%;
                padding: 2.5rem;
            }
            
            h2 {
                font-size: 2.2rem;
            }
        }

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
                font-size: 2rem;
                flex-direction: column;
                gap: 10px;
            }
            
            .form-section {
                padding: 20px;
            }
            
            .btn-add-image {
                width: 100%;
                justify-content: center;
            }
            
            #map {
                height: 300px;
            }
        }

        @media (max-width: 600px) {
            .container {
                margin: 1rem;
                padding: 1.5rem;
            }
            
            h2 {
                font-size: 1.8rem;
            }
            
            input[type="text"],
            input[type="number"],
            textarea,
            select {
                padding: 14px 16px;
                font-size: 1rem;
            }
            
            button[type="submit"] {
                font-size: 1.1rem;
                padding: 16px 24px;
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
            
            h2 {
                font-size: 1.6rem;
            }
            
            .form-section {
                padding: 15px;
            }
            
            .form-section-title {
                font-size: 1.1rem;
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
            <a href="recommendations.php"><i class="fas fa-search-plus"></i> แนะนำการแลกเปลี่ยน</a>
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

<div class="container">
    <h2><i class="fas fa-plus-circle"></i> เพิ่มรายการสิ่งของ</h2>

    <?php if (!empty($message)): ?>
        <p class="message <?php echo $messageType; ?>">
            <?php
            if ($messageType === 'success') {
                echo '<i class="fas fa-check-circle"></i> ';
            } else {
                echo '<i class="fas fa-exclamation-circle"></i> ';
            }
            echo htmlspecialchars($message);
            ?>
        </p>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <!-- Basic Item Information Section -->
        <div class="form-section">
            <div class="form-section-title">
                <i class="fas fa-info-circle"></i>
                ข้อมูลพื้นฐานของสิ่งของ
            </div>
            
            <label for="name">
                <i class="fas fa-tag"></i>
                ชื่อสิ่งของ <span class="required">*</span>
            </label>
            <input type="text" id="name" name="name" placeholder="เช่น กล้องฟิล์ม, หนังสือการ์ตูน" required>

            <label for="category">
                <i class="fas fa-th-list"></i>
                หมวดหมู่ <span class="required">*</span>
            </label>
            <select id="category" name="category" required>
                <option value="">-- เลือกหมวดหมู่ --</option>
                <?php foreach ($categoryOptions as $cat): ?>
                    <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                <?php endforeach; ?>
            </select>

            <label for="description">
                <i class="fas fa-align-left"></i>
                รายละเอียดสินค้า <span class="required">*</span>
            </label>
            <textarea id="description" name="description" rows="5" placeholder="อธิบายคุณสมบัติ, การใช้งาน, หรือตำหนิของสิ่งของโดยละเอียด" required></textarea>
        </div>

        <!-- Pricing and Condition Section -->
        <div class="form-section">
            <div class="form-section-title">
                <i class="fas fa-dollar-sign"></i>
                ราคาและสภาพสิ่งของ
            </div>
            
            <label for="price">
                <i class="fas fa-receipt"></i>
                ราคาที่ซื้อมา (บาท) <span class="optional">(ไม่บังคับ)</span>
            </label>
            <input type="number" id="price" name="price" step="0.01" min="0" placeholder="ระบุราคาที่ซื้อมา">

            <label for="estimated_price">
                <i class="fas fa-chart-line"></i>
                ราคาประเมินปัจจุบัน (บาท) <span class="optional">(ไม่บังคับ)</span>
            </label>
            <input type="number" id="estimated_price" name="estimated_price" step="0.01" min="0" placeholder="ระบุราคาที่คาดว่าแลกได้">

            <label for="condition_notes">
                <i class="fas fa-clipboard-check"></i>
                ร่องรอยการใช้งาน / สภาพ <span class="optional">(ไม่บังคับ)</span>
            </label>
            <textarea id="condition_notes" name="condition_notes" placeholder="เช่น มีรอยขีดข่วนเล็กน้อย, สภาพเหมือนใหม่ 90%"></textarea>

            <label for="purchase_year">
                <i class="fas fa-calendar-alt"></i>
                ปีที่ซื้อ <span class="optional">(ไม่บังคับ)</span>
            </label>
            <input type="number" id="purchase_year" name="purchase_year" min="1900" max="<?php echo date('Y'); ?>" placeholder="ปีที่ซื้อ (เช่น 2023)">
        </div>

        <!-- Exchange Information Section -->
        <div class="form-section">
            <div class="form-section-title">
                <i class="fas fa-exchange-alt"></i>
                ข้อมูลการแลกเปลี่ยน
            </div>
            
            <label for="status">
                <i class="fas fa-flag"></i>
                สถานะ <span class="required">*</span>
            </label>
            <select id="status" name="status" required>
                <option value="">-- เลือกสถานะ --</option>
                <option value="แจกฟรี">🎁 แจกฟรี</option>
                <option value="แลกเปลี่ยน">🔄 แลกเปลี่ยน</option>
                <option value="ขายราคาถูก">💰 ขายราคาถูก</option>
            </select>

            <label for="exchange_for">
                <i class="fas fa-handshake"></i>
                สิ่งที่ต้องการแลก <span class="optional">(ถ้ามี)</span>
            </label>
            <input type="text" id="exchange_for" name="exchange_for" placeholder="เช่น หนังสือแนวแฟนตาซี, อุปกรณ์กีฬา">
        </div>

        <!-- Contact Information Section -->
        <div class="form-section">
            <div class="form-section-title">
                <i class="fas fa-address-book"></i>
                ข้อมูลการติดต่อ
            </div>
            
            <label for="contact_name">
                <i class="fas fa-user"></i>
                ชื่อผู้ลงประกาศ <span class="required">*</span>
            </label>
            <input type="text" id="contact_name" name="contact_name" value="<?php echo htmlspecialchars($username); ?>" required>

            <label for="contact_info">
                <i class="fas fa-phone"></i>
                ช่องทางติดต่อ <span class="required">*</span>
            </label>
            <input type="text" id="contact_info" name="contact_info" placeholder="เช่น Line ID: @myline, เบอร์โทร: 08XXXXXXXX" required>
        </div>

        <!-- Location Section -->
        <div class="map-container">
            <div class="form-section-title">
                <i class="fas fa-map-marker-alt"></i>
                สถานที่นัดรับ
            </div>
            
            <label for="location_text">
                <i class="fas fa-map-signs"></i>
                ชื่อสถานที่นัดรับ <span class="required">*</span>
            </label>
            <input type="text" id="location_text" name="location_text" placeholder="ระบุชื่อสถานที่ที่สะดวกนัดรับสิ่งของ (เช่น อาคาร 12, หน้ามหาวิทยาลัย)" required>

            <label>
                <i class="fas fa-crosshairs"></i>
                ปักหมุดจุดนัดรับ <span class="optional">(ลากหมุดเพื่อระบุตำแหน่ง)</span>
            </label>
            <div id="map"></div>
            <input type="hidden" name="latitude" id="latitude">
            <input type="hidden" name="longitude" id="longitude">
        </div>

        <!-- Image Upload Section -->
        <div class="file-upload-container">
            <div class="form-section-title">
                <i class="fas fa-camera"></i>
                รูปภาพสินค้า
            </div>
            
            <label>
                <i class="fas fa-images"></i>
                เพิ่มรูปภาพสินค้า <span class="required">*</span> <span class="optional">(อย่างน้อย 1 รูป)</span>
            </label>
            <div id="image-upload-wrapper">
                <input type="file" name="images[]" accept="image/*" required>
            </div>
            <button type="button" class="btn-add-image" onclick="addImageInput()">
                <i class="fas fa-plus"></i> เพิ่มรูปเพิ่มเติม
            </button>
        </div>

        <button type="submit" name="save">
            <i class="fas fa-save"></i> เพิ่มสิ่งของ
        </button>
    </form>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
function addImageInput() {
    const wrapper = document.getElementById('image-upload-wrapper');
    const input = document.createElement('input');
    input.type = 'file';
    input.name = 'images[]';
    input.accept = 'image/*';
    input.style.marginTop = '10px';
    wrapper.appendChild(input);
}

// Default location for Kham Riang, Maha Sarakham
const defaultLat = 16.24428;
const defaultLng = 103.24901;

let map = L.map('map').setView([defaultLat, defaultLng], 16);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '© OpenStreetMap contributors'
}).addTo(map);

let marker = L.marker([defaultLat, defaultLng], {draggable:true}).addTo(map);
document.getElementById("latitude").value = defaultLat;
document.getElementById("longitude").value = defaultLng;

marker.on('dragend', function (e) {
    const pos = marker.getLatLng();
    document.getElementById("latitude").value = pos.lat.toFixed(8);
    document.getElementById("longitude").value = pos.lng.toFixed(8);
});

// Optional: Get user's current location on page load
// if (navigator.geolocation) {
//     navigator.geolocation.getCurrentPosition(function(position) {
//         const userLat = position.coords.latitude;
//         const userLng = position.coords.longitude;
//         map.setView([userLat, userLng], 16);
//         marker.setLatLng([userLat, userLng]);
//         document.getElementById("latitude").value = userLat.toFixed(8);
//         document.getElementById("longitude").value = userLng.toFixed(8);
//     }, function(error) {
//         console.warn('Geolocation error:', error);
//         // Fallback to default location if geolocation fails or is denied
//     });
// }
</script>
<script>
    function toggleProfileDropdown() {
        const dropdown = document.getElementById('profileDropdown');
        dropdown.classList.toggle('show');
    }

    // ปิด dropdown เมื่อคลิกที่อื่น
    document.addEventListener('click', function(event) {
        const dropdown = document.getElementById('profileDropdown');
        const profileButton = document.querySelector('.profile-button');
        
        if (!profileButton || (!profileButton.contains(event.target) && !dropdown.contains(event.target))) {
            dropdown.classList.remove('show');
        }
    });
</script>

</body>
</html>

<?php $conn->close(); ?>