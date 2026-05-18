<?php
$host = "202.28.34.205";
$dbname = "db65011211056";
$username = "65011211056";
$password = "65011211056"; // แก้เป็นรหัสของคุณหากมี

$conn = new mysqli($host, $username, $password, $dbname);

if ($conn->connect_error) {
    die("<h2 style='color:red;'>เชื่อมต่อฐานข้อมูลล้มเหลว: " . $conn->connect_error . "</h2>");
}

// รับค่าจากฟอร์ม
$user = trim($_POST["username"]);
$email = trim($_POST["email"]);
$pass = $_POST["password"];
$confirm = $_POST["confirm_password"];

function showMessage($message, $success = false) {
    $color = $success ? "green" : "red";
    echo "<div style='
            max-width: 400px; 
            margin: 40px auto; 
            padding: 20px; 
            border: 1px solid $color; 
            border-radius: 10px; 
            font-family: Arial, sans-serif; 
            color: $color;
            text-align: center;
            background: " . ($success ? "#e0ffe0" : "#ffe0e0") . ";
          '>
          <h3>$message</h3>
          <a href='register.html' style='
            display: inline-block; 
            margin-top: 15px; 
            padding: 10px 20px; 
            background: #0066cc; 
            color: white; 
            text-decoration: none; 
            border-radius: 5px;
          '>กลับไปสมัครสมาชิก</a>
          </div>";
    exit();
}

// ตรวจสอบข้อมูลเบื้องต้น
if (empty($user) || empty($email) || empty($pass) || empty($confirm)) {
    showMessage("กรุณากรอกข้อมูลให้ครบทุกช่อง");
}

if ($pass !== $confirm) {
    showMessage("รหัสผ่านไม่ตรงกัน");
}

// ตรวจสอบเงื่อนไขรหัสผ่านเพิ่มเติม
if (strlen($pass) < 6) {
    showMessage("รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร");
}

if (is_numeric($pass)) {
    showMessage("รหัสผ่านต้องประกอบด้วยตัวอักษรหรือสัญลักษณ์ ไม่สามารถเป็นตัวเลขทั้งหมดได้");
}

// ตรวจสอบชื่อผู้ใช้หรืออีเมลซ้ำ
$stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
$stmt->bind_param("ss", $user, $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    showMessage("ชื่อผู้ใช้หรืออีเมลนี้มีอยู่ในระบบแล้ว");
}

// แฮชรหัสผ่าน
$hashed_password = password_hash($pass, PASSWORD_DEFAULT);

// บันทึกข้อมูล
$insert = $conn->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
$insert->bind_param("sss", $user, $email, $hashed_password);

if ($insert->execute()) {
    echo "<div style='
              max-width: 400px; 
              margin: 40px auto; 
              padding: 20px; 
              border: 1px solid green; 
              border-radius: 10px; 
              font-family: Arial, sans-serif; 
              color: green;
              text-align: center;
              background: #e0ffe0;
            '>
            <h2>สมัครสมาชิกสำเร็จ!</h2>
            <a href='login.html' style='
              display: inline-block; 
              margin-top: 15px; 
              padding: 10px 20px; 
              background: #0066cc; 
              color: white; 
              text-decoration: none; 
              border-radius: 5px;
            '>เข้าสู่ระบบ</a>
            </div>";
} else {
    showMessage("เกิดข้อผิดพลาด: " . $insert->error);
}

$stmt->close();
$insert->close();
$conn->close();
?>