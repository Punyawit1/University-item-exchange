<?php
// forgot_password.php
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ลืมรหัสผ่าน</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Kanit:wght@400;500;700&display=swap');
        body { font-family: 'Kanit', sans-serif; }
    </style>
</head>
<body class="bg-gray-100 flex justify-center items-center h-screen">
    <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-sm">
        <h2 class="text-2xl font-bold mb-6 text-center text-gray-800">ลืมรหัสผ่าน?</h2>
        <p class="text-sm text-center text-gray-600 mb-6">กรุณากรอกชื่อผู้ใช้และอีเมลที่ใช้ลงทะเบียน</p>

        <form action="send_verification_code.php" method="POST">
            <div class="mb-4">
                <input type="text" name="username" placeholder="ชื่อผู้ใช้" required class="w-full px-4 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-yellow-400">
            </div>
            <div class="mb-6">
                <input type="email" name="email" placeholder="อีเมล" required class="w-full px-4 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-yellow-400">
            </div>
            <button type="submit" class="w-full bg-yellow-400 text-white font-bold py-2 px-4 rounded-md hover:bg-yellow-500 focus:outline-none focus:ring-2 focus:ring-yellow-400">
                ดำเนินการต่อ
            </button>
        </form>

        <?php
            // แสดงข้อความสถานะหากมี
            if(isset($_GET['status'])) {
                if($_GET['status'] == 'error') {
                    echo '<p class="text-center text-red-500 mt-4">ชื่อผู้ใช้หรืออีเมลไม่ถูกต้อง</p>';
                }
                if($_GET['status'] == 'success') {
                    echo '<p class="text-center text-green-500 mt-4">ตรวจสอบอีเมลของคุณเพื่อดำเนินการต่อ</p>';
                }
            }
        ?>
    </div>
</body>
</html>
