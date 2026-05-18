<?php
// reset_password.php

// 1. เชื่อมต่อฐานข้อมูล
$conn = new mysqli("202.28.34.205", "65011211056", "65011211056", "db65011211056");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 2. จัดการข้อมูลจากฟอร์มที่ส่งมา (POST)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $verification_code = $_POST['verification_code'];
    $new_password = $_POST['new_password'];
    $confirm_new_password = $_POST['confirm_new_password'];

    // ตรวจสอบว่ารหัสผ่านใหม่ตรงกันหรือไม่
    if ($new_password !== $confirm_new_password) {
        header("Location: reset_password.php?status=error&message=" . urlencode("รหัสผ่านใหม่ไม่ตรงกัน"));
        exit();
    }
    
    // *** START: เพิ่มโค้ดตรวจสอบเงื่อนไขรหัสผ่านใหม่ที่นี่ ***
    if (strlen($new_password) < 6) {
        header("Location: reset_password.php?status=error&message=" . urlencode("รหัสผ่านใหม่ต้องมีความยาวอย่างน้อย 6 ตัวอักษร"));
        exit();
    }

    if (is_numeric($new_password)) {
        header("Location: reset_password.php?status=error&message=" . urlencode("รหัสผ่านใหม่ต้องประกอบด้วยตัวอักษรหรือสัญลักษณ์ ไม่สามารถเป็นตัวเลขทั้งหมดได้"));
        exit();
    }
    // *** END: เพิ่มโค้ดตรวจสอบเงื่อนไขรหัสผ่านใหม่ที่นี่ ***
    
    // 3. ใช้ prepared statement เพื่อตรวจสอบรหัสยืนยันและอีเมล
    $stmt = $conn->prepare("SELECT id, verification_code, verification_expires FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $user_id = $user['id'];
        $stored_code = $user['verification_code'];
        $expires = $user['verification_expires'];

        // 4. ตรวจสอบรหัสยืนยัน
        if ($verification_code === $stored_code) {
            // 5. ตรวจสอบว่ารหัสหมดอายุหรือยัง
            if (time() < $expires) {
                
                // เข้ารหัสรหัสผ่านใหม่ก่อนบันทึก
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

                // 6. อัปเดตรหัสผ่านในฐานข้อมูลและลบรหัสยืนยัน
                $stmt_update = $conn->prepare("UPDATE users SET password = ?, verification_code = NULL, verification_expires = NULL WHERE id = ?");
                $stmt_update->bind_param("si", $hashed_password, $user_id);
                $stmt_update->execute();
                
                // 7. เปลี่ยนเส้นทางไปยังหน้าเดิมพร้อมข้อความสำเร็จ
                header("Location: reset_password.php?status=success&message=" . urlencode("เปลี่ยนรหัสผ่านสำเร็จแล้ว"));
                exit();
                
            } else {
                // รหัสหมดอายุ
                header("Location: reset_password.php?status=error&message=" . urlencode("รหัสยืนยันหมดอายุแล้ว"));
                exit();
            }
        } else {
            // รหัสไม่ถูกต้อง
            header("Location: reset_password.php?status=error&message=" . urlencode("รหัสยืนยันไม่ถูกต้อง"));
            exit();
        }
    } else {
        // ไม่พบอีเมลในฐานข้อมูล
        header("Location: reset_password.php?status=error&message=" . urlencode("ไม่พบผู้ใช้งานด้วยอีเมลนี้"));
        exit();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>เปลี่ยนรหัสผ่าน</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 font-sans flex items-center justify-center min-h-screen">
    <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-sm">
        <h2 class="text-2xl font-bold mb-6 text-center text-gray-800">เปลี่ยนรหัสผ่าน</h2>
        
        <form action="reset_password.php" method="POST">
            <div class="mb-4">
                <label for="email" class="block text-gray-700 text-sm font-bold mb-2">อีเมล:</label>
                <input type="email" id="email" name="email" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>
            <div class="mb-4">
                <label for="verification_code" class="block text-gray-700 text-sm font-bold mb-2">รหัสยืนยัน:</label>
                <input type="text" id="verification_code" name="verification_code" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>
            <div class="mb-4">
                <label for="new_password" class="block text-gray-700 text-sm font-bold mb-2">รหัสผ่านใหม่:</label>
                <input type="password" id="new_password" name="new_password" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>
            <div class="mb-6">
                <label for="confirm_new_password" class="block text-gray-700 text-sm font-bold mb-2">ยืนยันรหัสผ่านใหม่:</label>
                <input type="password" id="confirm_new_password" name="confirm_new_password" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>
            <div class="flex items-center justify-between">
                <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline w-full">
                    ยืนยันการเปลี่ยนรหัสผ่าน
                </button>
            </div>
        </form>
    </div>

    <div id="statusModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full flex items-center justify-center">
        <div class="relative p-8 w-96 mx-auto rounded-lg shadow-xl">
            <div id="modalContent" class="text-center">
                </div>
        </div>
    </div>

    <script>
        let shouldRedirect = false;

        // Check for URL parameters on page load to display the modal
        window.onload = function() {
            const urlParams = new URLSearchParams(window.location.search);
            const status = urlParams.get('status');
            const message = urlParams.get('message');

            if (status && message) {
                const modal = document.getElementById('statusModal');
                const modalContent = document.getElementById('modalContent');
                const modalBody = modal.querySelector('.relative');

                let contentHtml = '';

                // Determine modal styling based on status
                if (status === 'success') {
                    shouldRedirect = true;
                    contentHtml = `
                        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4" role="alert">
                            <p class="font-bold">สำเร็จ!</p>
                            <p>${decodeURIComponent(message)}</p>
                        </div>
                        <div class="mt-4 flex justify-center">
                            <button onclick="closeModal()" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                                ตกลง
                            </button>
                        </div>
                    `;
                } else if (status === 'error') {
                    contentHtml = `
                        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4" role="alert">
                            <p class="font-bold">ผิดพลาด!</p>
                            <p>${decodeURIComponent(message)}</p>
                        </div>
                        <div class="mt-4 flex justify-center">
                            <button onclick="closeModal()" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded">
                                ตกลง
                            </button>
                        </div>
                    `;
                }

                modalContent.innerHTML = contentHtml;
                modal.classList.remove('hidden');
            }
        };

        function closeModal() {
            const modal = document.getElementById('statusModal');
            modal.classList.add('hidden');
            
            if (shouldRedirect) {
                // Redirect to login page on success
                window.location.href = 'login.html';
            } else {
                // Remove the URL parameters for a cleaner URL on error
                window.history.replaceState({}, document.title, window.location.pathname);
            }
        }
    </script>
</body>
</html>