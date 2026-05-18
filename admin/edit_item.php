<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.html");
    exit();
}

if (!isset($_GET['id'])) {
    echo "ไม่พบรหัสสินค้า";
    exit();
}

$itemId = intval($_GET['id']);

$conn = new mysqli("202.28.34.205", "65011211056", "65011211056", "db65011211056");
if ($conn->connect_error) {
    die("เชื่อมต่อล้มเหลว: " . $conn->connect_error);
}

// ดึงรายการหมวดหมู่
$categories = [];
$catSql = "SELECT name FROM categories ORDER BY name";
$catResult = $conn->query($catSql);
if ($catResult) {
    while ($row = $catResult->fetch_assoc()) {
        $categories[] = $row['name'];
    }
}

// ดึงรายละเอียดสินค้าปัจจุบัน
$sql = "SELECT * FROM items WHERE id = ?";
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
$imgStmt = $conn->prepare("SELECT id, image FROM item_images WHERE item_id = ?");
if ($imgStmt === false) {
    die("Error preparing image statement: " . $conn->error);
}
$imgStmt->bind_param("i", $itemId);
$imgStmt->execute();
$imgResult = $imgStmt->get_result();
while ($imgRow = $imgResult->fetch_assoc()) {
    $images[] = [
        'id' => $imgRow['id'],
        'path' => "../uploads/item/" . $imgRow['image']
    ];
}
$imgStmt->close();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $itemName = $_POST['name'];
    $category = $_POST['category'];
    $description = $_POST['description'];
    $exchangeFor = $_POST['exchange_for'];
    $price = $_POST['price'] ? floatval($_POST['price']) : null;
    $estimatedPrice = $_POST['estimated_price'] ? floatval($_POST['estimated_price']) : null;
    $contactInfo = $_POST['contact_info'];
    $conditionNotes = $_POST['condition_notes'];
    $purchaseYear = $_POST['purchase_year'] ? intval($_POST['purchase_year']) : null;
    $status = $_POST['status'];
    $isApproved = isset($_POST['is_approved']) ? 1 : 0;
    
    // ลบส่วนที่เกี่ยวข้องกับ is_exchanged ออก
    // $isExchanged = isset($_POST['is_exchanged']) ? 1 : 0;

    $updateSql = "UPDATE items SET 
                  name = ?, category = ?, price = ?, estimated_price = ?, description = ?,
                  condition_notes = ?, purchase_year = ?, status = ?, exchange_for = ?,
                  contact_info = ?, is_approved = ? 
                  WHERE id = ?";
    $updateStmt = $conn->prepare($updateSql);
    if ($updateStmt === false) {
        die("Error preparing update statement: " . $conn->error);
    }

    // ลบ 'i' ตัวสุดท้ายออกจาก bind_param
    $updateStmt->bind_param("ssddssisssii", 
        $itemName, $category, $price, $estimatedPrice, $description,
        $conditionNotes, $purchaseYear, $status, $exchangeFor,
        $contactInfo, $isApproved, $itemId);

    if ($updateStmt->execute()) {
        if (!empty($_FILES['new_images']['name'][0])) {
            $uploadDir = "../uploads/item/";
            foreach ($_FILES['new_images']['tmp_name'] as $key => $tmpName) {
                if (!empty($tmpName)) {
                    $fileName = basename($_FILES['new_images']['name'][$key]);
                    $uniqueFileName = time() . '_' . uniqid() . '_' . $fileName;
                    $uploadPath = $uploadDir . $uniqueFileName;
                    if (move_uploaded_file($tmpName, $uploadPath)) {
                        $insertImgSql = "INSERT INTO item_images (item_id, image) VALUES (?, ?)";
                        $insertImgStmt = $conn->prepare($insertImgSql);
                        $insertImgStmt->bind_param("is", $itemId, $uniqueFileName);
                        $insertImgStmt->execute();
                        $insertImgStmt->close();
                    }
                }
            }
        }
        
        if (!empty($_POST['delete_images'])) {
            $uploadDir = "../uploads/item/";
            foreach ($_POST['delete_images'] as $imgId) {
                $deleteImgSql = "SELECT image FROM item_images WHERE id = ?";
                $deleteImgStmt = $conn->prepare($deleteImgSql);
                $deleteImgStmt->bind_param("i", $imgId);
                $deleteImgStmt->execute();
                $imgResult = $deleteImgStmt->get_result();
                $imgRow = $imgResult->fetch_assoc();
                if ($imgRow) {
                    $filePath = $uploadDir . $imgRow['image'];
                    if (file_exists($filePath)) {
                        unlink($filePath);
                    }
                }
                $deleteImgStmt->close();
                
                $deleteImgSql = "DELETE FROM item_images WHERE id = ?";
                $deleteImgStmt = $conn->prepare($deleteImgSql);
                $deleteImgStmt->bind_param("i", $imgId);
                $deleteImgStmt->execute();
                $deleteImgStmt->close();
            }
        }
        
        // แก้ไขการเปลี่ยนเส้นทางให้กลับไปหน้ารายละเอียดเดิม
        header("Location: item_detail.php?id=$itemId&success=updated");
        exit();

    } else {
        $error_message = "เกิดข้อผิดพลาดในการอัปเดตข้อมูล: " . $updateStmt->error;
    }
    $updateStmt->close();
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>แก้ไขสินค้า (Admin)</title>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <style>
        body {
            font-family: 'Kanit', sans-serif;
            background: #f4f7f9;
            color: #333;
        }
        .container {
            max-width: 900px;
            margin-top: 30px;
            margin-bottom: 50px;
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        h2 {
            text-align: center;
            color: #2c3e50;
            font-weight: 700;
            margin-bottom: 30px;
        }
        .form-label {
            font-weight: 500;
            color: #555;
        }
        .btn-primary {
            background-color: #ffc107;
            border-color: #ffc107;
            color: #333;
            font-weight: 600;
        }
        .btn-primary:hover {
            background-color: #e0a800;
            border-color: #e0a800;
        }
        .btn-secondary {
            background-color: #6c757d;
            border-color: #6c757d;
            font-weight: 600;
        }
        .image-preview {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 15px;
        }
        .image-container {
            position: relative;
        }
        .image-container img {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid #ddd;
        }
        .image-container .delete-btn {
            position: absolute;
            top: 5px;
            right: 5px;
            background: rgba(220, 53, 69, 0.8);
            color: white;
            border: none;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            font-size: 14px;
            cursor: pointer;
            transition: background 0.3s;
        }
        .image-container .delete-btn:hover {
            background: #dc3545;
        }
    </style>
</head>
<body>

<div class="container">
    <h2>แก้ไขรายการสินค้า</h2>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger" role="alert">
            <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>

    <form action="edit_item.php?id=<?php echo $itemId; ?>" method="post" enctype="multipart/form-data">
        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="name" class="form-label">ชื่อสินค้า</label>
                <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($item['name']); ?>" required>
            </div>
            <div class="col-md-6 mb-3">
                <label for="category" class="form-label">หมวดหมู่</label>
                <select class="form-select" id="category" name="category" required>
                    <option value="">เลือกหมวดหมู่</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo ($item['category'] === $cat) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="price" class="form-label">ราคา</label>
                <input type="number" step="0.01" class="form-control" id="price" name="price" value="<?php echo htmlspecialchars($item['price']); ?>">
            </div>
            <div class="col-md-6 mb-3">
                <label for="estimated_price" class="form-label">ราคาประเมิน</label>
                <input type="number" step="0.01" class="form-control" id="estimated_price" name="estimated_price" value="<?php echo htmlspecialchars($item['estimated_price']); ?>">
            </div>
        </div>

        <div class="mb-3">
            <label for="description" class="form-label">รายละเอียด</label>
            <textarea class="form-control" id="description" name="description" rows="3" required><?php echo htmlspecialchars($item['description']); ?></textarea>
        </div>

        <div class="mb-3">
            <label for="exchange_for" class="form-label">สิ่งที่ต้องการแลกเปลี่ยน</label>
            <textarea class="form-control" id="exchange_for" name="exchange_for" rows="2"><?php echo htmlspecialchars($item['exchange_for']); ?></textarea>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="condition_notes" class="form-label">สภาพสินค้า</label>
                <textarea class="form-control" id="condition_notes" name="condition_notes" rows="2"><?php echo htmlspecialchars($item['condition_notes']); ?></textarea>
            </div>
            <div class="col-md-6 mb-3">
                <label for="purchase_year" class="form-label">ปีที่ซื้อ</label>
                <input type="number" class="form-control" id="purchase_year" name="purchase_year" value="<?php echo htmlspecialchars($item['purchase_year']); ?>">
            </div>
        </div>

        <div class="mb-3">
            <label for="contact_info" class="form-label">ช่องทางติดต่อ</label>
            <input type="text" class="form-control" id="contact_info" name="contact_info" value="<?php echo htmlspecialchars($item['contact_info']); ?>" required>
        </div>

        <div class="row mb-3">
            <div class="col-md-6">
                <label for="status" class="form-label">สถานะ</label>
                <select class="form-select" id="status" name="status" required>
                    <option value="แจกฟรี" <?php echo ($item['status'] === 'แจกฟรี') ? 'selected' : ''; ?>>แจกฟรี</option>
                    <option value="แลกเปลี่ยน" <?php echo ($item['status'] === 'แลกเปลี่ยน') ? 'selected' : ''; ?>>แลกเปลี่ยน</option>
                    <option value="ขายราคาถูก" <?php echo ($item['status'] === 'ขายราคาถูก') ? 'selected' : ''; ?>>ขายราคาถูก</option>
                </select>
            </div>
            <div class="col-md-6 mt-4 mt-md-0">
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" id="is_approved" name="is_approved" value="1" <?php echo ($item['is_approved'] == 1) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="is_approved">อนุมัติแล้ว</label>
                </div>
                </div>
        </div>

        <hr />
        
        <h4>จัดการรูปภาพ</h4>
        <p>รูปภาพปัจจุบัน: </p>
        <div class="image-preview">
            <?php foreach ($images as $img): ?>
                <div class="image-container">
                    <img src="<?php echo htmlspecialchars($img['path']); ?>" alt="รูปสินค้า" />
                    <button type="button" class="delete-btn" onclick="deleteImage(this, '<?php echo $img['id']; ?>')">x</button>
                    <input type="hidden" name="delete_images[]" id="delete_img_<?php echo $img['id']; ?>" value="">
                </div>
            <?php endforeach; ?>
        </div>

        <div class="mb-3 mt-4">
            <label for="new_images" class="form-label">เพิ่มรูปภาพใหม่</label>
            <input type="file" class="form-control" id="new_images" name="new_images[]" multiple accept="image/*">
        </div>

        <div class="d-flex justify-content-between mt-4">
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> บันทึกการเปลี่ยนแปลง</button>
            <a href="item_detail.php?id=<?php echo $itemId; ?>" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> ยกเลิก</a>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function deleteImage(button, imgId) {
        if (confirm('คุณแน่ใจหรือไม่ที่ต้องการลบรูปภาพนี้?')) {
            button.closest('.image-container').style.display = 'none';
            document.getElementById('delete_img_' + imgId).value = imgId;
        }
    }
</script>

</body>
</html>