<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.html");
    exit();
}

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];

$conn = new mysqli("202.28.34.205", "65011211056", "65011211056", "db65011211056");
if ($conn->connect_error) {
    die("เชื่อมต่อฐานข้อมูลล้มเหลว: " . $conn->connect_error);
}

// ดึงรูปโปรไฟล์ของผู้ใช้
$stmt = $conn->prepare("SELECT profile_image FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

$profileImage = (!empty($user['profile_image']) && file_exists("../uploads/profile/" . $user['profile_image']))
    ? "../uploads/profile/" . $user['profile_image']
    : "../uploads/profile/default.png";

// ฟังก์ชันคืน path รูป
function getItemImagePath($filename) {
    $filepath = "../uploads/item/" . $filename;
    if (!empty($filename) && file_exists($filepath)) {
        return $filepath;
    }
    return "../uploads/item/default.png";
}

$userItems = [];
$matchedItems = [];
$selectedUserItemId = isset($_GET['my_item_id']) ? (int)$_GET['my_item_id'] : 0;
$selectedItemCategory = "";

// ดึงรายการสิ่งของของผู้ใช้ปัจจุบัน
$userItemsQuery = $conn->prepare("SELECT id, name, category FROM items WHERE owner_id = ? AND is_approved = 1 ORDER BY name ASC");
$userItemsQuery->bind_param("i", $userId);
$userItemsQuery->execute();
$userItemsResult = $userItemsQuery->get_result();
while ($row = $userItemsResult->fetch_assoc()) {
    $userItems[] = $row;
    if ($row['id'] == $selectedUserItemId) {
        $selectedItemCategory = $row['category'];
    }
}
$userItemsQuery->close();

// ถ้ามีการเลือกสิ่งของของผู้ใช้ ให้ค้นหาสิ่งของที่สามารถแลกเปลี่ยนได้
if ($selectedUserItemId > 0 && !empty($selectedItemCategory)) {
    $sql = "SELECT i.id, i.name, i.category, i.created_at, img.image, u.username AS owner_name
            FROM items i
            LEFT JOIN (
                SELECT item_id, MIN(image) AS image
                FROM item_images
                GROUP BY item_id
            ) img ON i.id = img.item_id
            JOIN users u ON i.owner_id = u.id
            WHERE i.owner_id != ? AND i.category = ? AND i.is_approved = 1
            ORDER BY i.created_at DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $userId, $selectedItemCategory);
    $stmt->execute();
    $matchedItemsResult = $stmt->get_result();
    while ($item = $matchedItemsResult->fetch_assoc()) {
        $matchedItems[] = $item;
    }
    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8" />
    <title>ค้นหาสิ่งของที่สามารถแลกเปลี่ยนได้</title>
    <style>
        body { font-family: sans-serif; background: #f9f9f9; margin: 0; }
        header {
            background: #0066cc; color: white;
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .username { font-weight: bold; }
        nav a { margin: 0 10px; color: white; text-decoration: none; }
        .container {
            max-width: 1000px; margin: 30px auto; padding: 20px;
            background: white; border-radius: 10px;
        }
        form.filter-form {
            margin-bottom: 20px;
        }
        form.filter-form select {
            padding: 5px 10px; font-size: 1rem;
        }
        form.filter-form button {
            padding: 6px 12px; font-size: 1rem; margin-left: 8px; cursor: pointer;
        }
        .items-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .item-card {
            background: #fff; border: 1px solid #ddd; border-radius: 8px;
            overflow: hidden; padding: 10px; text-align: center;
        }
        .item-card img {
            width: 100%; height: 150px; object-fit: cover; border-radius: 5px;
        }
        .item-card h4 { margin: 10px 0 5px; }
    </style>
</head>
<body>

<header>
    <div>
        <strong>ระบบแลกของ</strong>
        <nav>
            <a href="main.php">หน้าแรก</a>
            <a href="my_items.php">ของของฉัน</a>
            <a href="exchange_requests_received.php">คำขอแลกเปลี่ยนที่คุณได้รับ</a>
            <a href="exchange_requests_sent.php">คำขอแลกเปลี่ยนที่คุณส่ง</a>
            <a href="my_reviews.php"><i class="fas fa-star-half-alt"></i> รีวิวของฉัน</a>
            <a href="reviews_received.php"><i class="fas fa-star"></i> รีวิวที่คุณได้รับ</a>
            <a href="edit_profile.php">จัดการโปรไฟล์</a>
        </nav>
    </div>
    <div class="username">
        <img src="<?php echo htmlspecialchars($profileImage); ?>" style="width:35px; height:35px; border-radius:50%; vertical-align:middle;" alt="รูปโปรไฟล์">
        <span style="margin-left:5px;"><?php echo htmlspecialchars($username); ?></span> |
        <a href="../logout.php" style="color: yellow;">ออกจากระบบ</a>
    </div>
</header>

<div class="container">
    <h2>ค้นหาสิ่งของที่สามารถแลกเปลี่ยนได้</h2>
    <p>เลือกสิ่งของของคุณเพื่อค้นหาสิ่งของอื่น ๆ ที่อยู่ในหมวดหมู่เดียวกัน</p>

    <form method="get" class="filter-form">
        <label for="my_item_id">สิ่งของของคุณ:</label>
        <select name="my_item_id" id="my_item_id" onchange="this.form.submit()">
            <option value="">เลือกสิ่งของของคุณ</option>
            <?php foreach ($userItems as $item): ?>
                <option value="<?php echo htmlspecialchars($item['id']); ?>" <?php if ($selectedUserItemId === $item['id']) echo "selected"; ?>>
                    <?php echo htmlspecialchars($item['name']); ?> (<?php echo htmlspecialchars($item['category']); ?>)
                </option>
            <?php endforeach; ?>
        </select>
        <noscript><button type="submit">ค้นหา</button></noscript>
    </form>

    <h3>🔍 รายการสิ่งของที่สามารถแลกเปลี่ยนได้</h3>
    <?php if ($selectedUserItemId > 0): ?>
        <?php if (!empty($matchedItems)): ?>
            <div class="items-grid">
                <?php foreach ($matchedItems as $item): ?>
                    <a href="item_detail.php?id=<?php echo $item['id']; ?>" style="text-decoration: none; color: inherit;">
                        <div class="item-card">
                            <img src="<?php echo htmlspecialchars(getItemImagePath($item['image'])); ?>" alt="ภาพสินค้า">
                            <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                            <p>หมวดหมู่: <?php echo htmlspecialchars($item['category']); ?></p>
                            <p>เจ้าของ: <?php echo htmlspecialchars($item['owner_name']); ?></p>
                            <small>ลงเมื่อ: <?php echo date("d/m/Y", strtotime($item['created_at'])); ?></small>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p>ไม่พบสิ่งของอื่น ๆ ที่สามารถแลกเปลี่ยนได้ในหมวดหมู่เดียวกัน</p>
        <?php endif; ?>
    <?php else: ?>
        <p>กรุณาเลือกสิ่งของของคุณเพื่อเริ่มต้นการค้นหา</p>
    <?php endif; ?>
</div>

</body>
</html>