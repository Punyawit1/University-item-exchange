<?php
session_start();
$conn = new mysqli("localhost", "root", "", "item_exchange");
if ($conn->connect_error) {
    die("DB Error: " . $conn->connect_error);
}

// ตรวจสอบว่ามีการเลือกหมวดหมู่หรือไม่
$status = isset($_GET['status']) ? $_GET['status'] : '';

$sql = "SELECT items.*, 
        (SELECT image FROM item_images WHERE item_images.item_id = items.id LIMIT 1) as image 
        FROM items 
        WHERE is_approved = 1 AND is_available = 1";

if ($status != '') {
    $sql .= " AND status = '" . $conn->real_escape_string($status) . "'";
}

$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ตลาดแลกเปลี่ยน</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">

<div class="container mt-4">
    <h2 class="mb-3">เลือกหมวดหมู่</h2>
    <div class="mb-4">
        <a href="market.php?status=แลกเปลี่ยน" class="btn btn-primary">แลกเปลี่ยน</a>
        <a href="market.php?status=ขายราคาถูก" class="btn btn-success">ขายราคาถูก</a>
        <a href="market.php?status=แจกฟรี" class="btn btn-warning">แจกฟรี</a>
        <a href="market.php" class="btn btn-secondary">ดูทั้งหมด</a>
    </div>

    <div class="row">
        <?php while($row = $result->fetch_assoc()): ?>
        <div class="col-md-3 mb-4">
            <div class="card shadow-sm">
                <?php if ($row['image']): ?>
                    <img src="uploads/items/<?php echo $row['image']; ?>" class="card-img-top" style="height:180px;object-fit:cover;">
                <?php else: ?>
                    <img src="uploads/no-image.png" class="card-img-top" style="height:180px;object-fit:cover;">
                <?php endif; ?>
                <div class="card-body">
                    <h5 class="card-title"><?php echo htmlspecialchars($row['name']); ?></h5>
                    <p class="card-text text-muted">สถานะ: <?php echo $row['status']; ?></p>
                    <p class="card-text"><?php echo $row['description']; ?></p>
                    <a href="item_detail.php?id=<?php echo $row['id']; ?>" class="btn btn-outline-primary btn-sm">ดูรายละเอียด</a>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
</div>

</body>
</html>
