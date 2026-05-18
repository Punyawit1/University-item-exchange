<?php
session_start();

// ตั้งค่าการเชื่อมต่อฐานข้อมูล
$host = "202.28.34.205";
$dbname = "db65011211056";
$dbuser = "65011211056";
$dbpass = "65011211056";

// เชื่อมต่อฐานข้อมูล
$conn = new mysqli($host, $dbuser, $dbpass, $dbname);
if ($conn->connect_error) {
    die("เชื่อมต่อฐานข้อมูลล้มเหลว: " . $conn->connect_error);
}

// ตรวจสอบว่ามีการส่งค่าจากฟอร์มหรือไม่
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    // ตรวจสอบว่าไม่ได้กรอกชื่อผู้ใช้
    if (empty($username)) {
        $error_message = "กรุณากรอกชื่อผู้ใช้งาน";
        displayErrorPage($error_message);
        exit();
    }
    
    // ตรวจสอบว่าไม่ได้กรอกรหัสผ่าน
    if (empty($password)) {
        $error_message = "กรุณากรอกรหัสผ่าน";
        displayErrorPage($error_message);
        exit();
    }

    // เตรียม statement ป้องกัน SQL Injection
    $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // ตรวจสอบรหัสผ่าน
        if (password_verify($password, $user['password'])) {
            // บันทึกข้อมูล session และ redirect
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            if ($user['role'] === 'admin') {
                header("Location: admin/admin_main.php");
                exit();
            } else {
                header("Location: user/welcome.php");
                exit();
            }
        } else {
            // รหัสผ่านไม่ถูกต้อง
            $error_message = "รหัสผ่านไม่ถูกต้อง";
            displayErrorPage($error_message);
            exit();
        }
    } else {
        // ไม่พบบัญชีผู้ใช้
        $error_message = "ไม่พบบัญชีผู้ใช้นี้ในระบบ";
        displayErrorPage($error_message);
        exit();
    }

    $stmt->close();
} else {
    // หากเข้ามาโดยไม่ได้ส่งข้อมูล POST ให้ redirect ไปหน้า login
    header("Location: login.html");
    exit();
}

$conn->close();

/**
 * ฟังก์ชันสำหรับแสดงหน้า UI แจ้งเตือนข้อผิดพลาด
 * @param string $message ข้อความที่ต้องการแสดง
 */
function displayErrorPage($message) {
    ?>
    <!DOCTYPE html>
    <html lang="th">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>เกิดข้อผิดพลาดในการเข้าสู่ระบบ</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                background-color: #f0f2f5;
                display: flex;
                justify-content: center;
                align-items: center;
                height: 100vh;
                margin: 0;
            }
            .alert-box {
                background-color: #fff;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
                padding: 40px;
                text-align: center;
                width: 400px;
                animation: fadeIn 0.5s ease-in-out;
            }
            .alert-box h2 {
                color: #d9534f;
                margin-bottom: 20px;
            }
            .alert-box p {
                color: #555;
                font-size: 16px;
                line-height: 1.6;
                margin-bottom: 30px;
            }
            .alert-box .btn {
                background-color: #d9534f;
                color: white;
                border: none;
                padding: 12px 25px;
                border-radius: 5px;
                cursor: pointer;
                text-decoration: none;
                font-size: 16px;
                transition: background-color 0.3s ease;
            }
            .alert-box .btn:hover {
                background-color: #c9302c;
            }
            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(-20px); }
                to   { opacity: 1; transform: translateY(0); }
            }
        </style>
    </head>
    <body>
        <div class="alert-box">
            <h2>⛔ เกิดข้อผิดพลาด</h2>
            <p><?php echo htmlspecialchars($message); ?></p>
            <a href="login.html" class="btn">กลับไปยังหน้าล็อกอิน</a>
        </div>
    </body>
    </html>
    <?php
}
?>
