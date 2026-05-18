<?php
// verify_code.php

// 1. เชื่อมต่อฐานข้อมูล
$conn = new mysqli("202.28.34.205", "65011211056", "65011211056", "db65011211056");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 2. รับข้อมูลจาก URL หรือฟอร์ม
$status = '';
$email = '';
$message = '';
$show_form = true;

// รับค่าอีเมลจาก GET หรือ POST
if (isset($_GET['email'])) {
    $email = $_GET['email'];
} elseif (isset($_POST['email'])) {
    $email = $_POST['email'];
}

// 3. จัดการข้อมูลจากฟอร์มที่ส่งมา (POST)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $code_from_form = $_POST['verification_code'];

    // 4. ตรวจสอบรหัสยืนยันและเวลาหมดอายุในฐานข้อมูล
    $stmt = $conn->prepare("SELECT reset_token FROM users WHERE email = ? AND verification_code = ? AND verification_expires >= ?");
    $now = time();
    $stmt->bind_param("ssi", $email, $code_from_form, $now);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $reset_token = $user['reset_token'];
        
        // 5. หากรหัสถูกต้อง ให้เปลี่ยนเส้นทางไปยังหน้า reset_password.php
        header("Location: reset_password.php?token=" . urlencode($reset_token));
        exit();
    } else {
        // 6. ถ้ารหัสไม่ถูกต้อง
        $status = 'error';
        $message = "รหัสยืนยันไม่ถูกต้องหรือหมดอายุแล้ว กรุณาลองอีกครั้ง";
    }
} elseif (isset($_GET['status'])) {
    // ถ้ารับข้อมูลจาก URL (GET) ในการเข้าหน้าครั้งแรก
    $status = $_GET['status'];
    if ($status == 'success') {
        $message = "เราได้ส่งรหัสยืนยันไปยังอีเมลของคุณแล้ว กรุณาตรวจสอบอีเมล";
    } elseif ($status == 'error') {
        $message = "รหัสยืนยันไม่ถูกต้องหรือหมดอายุแล้ว";
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ยืนยันรหัส</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Kanit:wght@400;500;700&display=swap');
        body { font-family: 'Kanit', sans-serif; }
    </style>
</head>
<body class="bg-gray-100 flex justify-center items-center h-screen">
    <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-sm">
        <h2 class="text-2xl font-bold mb-6 text-center text-gray-800">ยืนยันรหัส</h2>
        
        <?php if ($message): ?>
            <?php if ($status == 'success'): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                    <p><?php echo htmlspecialchars($message); ?></p>
                </div>
            <?php elseif ($status == 'error'): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                    <p><?php echo htmlspecialchars($message); ?></p>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($show_form): ?>
            <form action="verify_code.php" method="POST">
                <p class="text-sm text-center text-gray-600 mb-6">กรุณากรอกรหัสยืนยัน 6 หลักที่ได้รับทางอีเมล</p>
                <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                <div class="mb-4">
                    <input type="text" name="verification_code" placeholder="รหัสยืนยัน" required class="w-full px-4 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-yellow-400">
                </div>
                <button type="submit" class="w-full bg-yellow-400 text-white font-bold py-2 px-4 rounded-md hover:bg-yellow-500 focus:outline-none focus:ring-2 focus:ring-yellow-400">
                    ยืนยัน
                </button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
