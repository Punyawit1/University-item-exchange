<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_status'] !== 'admin') {
    header("Location: ../login.html");
    exit();
}

$conn = new mysqli("202.28.34.205", "65011211056", "65011211056", "db65011211056");
if ($conn->connect_error) {
    die("เชื่อมต่อฐานข้อมูลล้มเหลว: " . $conn->connect_error);
}

// อนุมัติรายการ
if (isset($_GET['approve'])) {
    $itemId = intval($_GET['approve']);
    $conn->query("UPDATE items SET is_approved = 1 WHERE id = $itemId");
}

// ลบรายการ
if (isset($_GET['delete'])) {
    $itemId = intval($_GET['delete']);

    // ลบรูปภาพ
    $imgQuery = $conn->query("SELECT image FROM item_images WHERE item_id = $itemId");
    while ($img = $imgQuery->fetch_assoc()) {
        $path = "../uploads/item/" . $img['image'];
        if (file_exists($path)) unlink($path);
    }

    $conn->query("DELETE FROM item_images WHERE item_id = $itemId");
    $conn->query("DELETE FROM items WHERE id = $itemId");
}

// ดึงรายการรออนุมัติ
$sql = "
SELECT i.*, u.fullname 
FROM items i 
JOIN users u ON i.owner_id = u.id 
WHERE i.is_approved = 0 
ORDER BY i.created_at DESC
";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>อนุมัติรายการสิ่งของ</title>
  <style>
    body { font-family: sans-serif; background: #f5f5f5; padding: 20px; }
    h2 { color: #333; }
    table { width: 100%; border-collapse: collapse; margin-top: 20px; background: #fff; }
    th, td { padding: 10px; border: 1px solid #ccc; text-align: left; }
    th { background: #007bff; color: white; }
    tr:nth-child(even) { background: #f9f9f9; }
    a.button {
      padding: 5px 10px; color: white; border-radius: 5px;
      text-decoration: none; font-size: 14px;
    }
    .approve { background: #28a745; }
    .delete { background: #dc3545; }
  </style>
</head>
<body>

<h2>📋 รายการสิ่งของที่รออนุมัติ</h2>

<?php if ($result->num_rows > 0): ?>
  <table>
    <tr>
      <th>ชื่อสิ่งของ</th>
      <th>หมวดหมู่</th>
      <th>เจ้าของ</th>
      <th>วันที่ลง</th>
      <th>จัดการ</th>
    </tr>
    <?php while ($item = $result->fetch_assoc()): ?>
      <tr>
        <td><?php echo htmlspecialchars($item['name']); ?></td>
        <td><?php echo htmlspecialchars($item['category']); ?></td>
        <td><?php echo htmlspecialchars($item['fullname']); ?></td>
        <td><?php echo date("d/m/Y H:i", strtotime($item['created_at'])); ?></td>
        <td>
          <a class="button approve" href="?approve=<?php echo $item['id']; ?>" onclick="return confirm('อนุมัติรายการนี้?')">✔ อนุมัติ</a>
          <a class="button delete" href="?delete=<?php echo $item['id']; ?>" onclick="return confirm('ลบรายการนี้?')">🗑 ลบ</a>
        </td>
      </tr>
    <?php endwhile; ?>
  </table>
<?php else: ?>
  <p>ไม่มีรายการที่รออนุมัติในขณะนี้</p>
<?php endif; ?>

<a href="main.php" style="display:inline-block; margin-top:20px;">⬅ กลับหน้าหลัก</a>

</body>
</html>

<?php $conn->close(); ?>
