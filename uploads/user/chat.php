<?php
// chat.php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.html");
    exit();
}

// เชื่อมต่อฐานข้อมูล
$conn = new mysqli("localhost", "root", "", "item_exchange");
if ($conn->connect_error) {
    die("เชื่อมต่อฐานข้อมูลล้มเหลว: " . $conn->connect_error);
}

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];

// ดึงรูปโปรไฟล์ของผู้ใช้จากฐานข้อมูล
$stmt = $conn->prepare("SELECT profile_image FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result_profile = $stmt->get_result();
$user = $result_profile->fetch_assoc();

// แก้ไข path รูปภาพโปรไฟล์ให้ถูกต้อง
$profileImage = (!empty($user['profile_image']) && file_exists("../uploads/profile/" . $user['profile_image']))
    ? "../uploads/profile/" . $user['profile_image']
    : "../uploads/profile/default.png";

// ดึงจำนวนคำขอที่ยังไม่อ่าน
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

$requestId = isset($_GET['request_id']) ? intval($_GET['request_id']) : 0;

// ปรับปรุงการตรวจสอบสิทธิ์การเข้าถึงห้องแชท
$stmtCheck = $conn->prepare("
    SELECT er.id 
    FROM exchange_requests er 
    JOIN items i1 ON er.item_owner_id = i1.id
    WHERE er.id = ? 
    AND (i1.owner_id = ? OR er.requester_id = ?)
");
$stmtCheck->bind_param("iii", $requestId, $userId, $userId);
$stmtCheck->execute();
$resCheck = $stmtCheck->get_result();
if ($resCheck->num_rows === 0) {
    die("ไม่มีสิทธิ์เข้าถึงห้องสนทนานี้");
}
$stmtCheck->close();

// บันทึกข้อความที่ส่งมาผ่าน POST
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['message'])) {
    $msg = trim($_POST['message']);
    if ($msg !== "") {
        $stmtSend = $conn->prepare("INSERT INTO messages (request_id, sender_id, message) VALUES (?, ?, ?)");
        $stmtSend->bind_param("iis", $requestId, $userId, $msg);
        $stmtSend->execute();
        $stmtSend->close();
        exit(); // ส่งผ่าน AJAX
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ห้องแชท</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        /* CSS สำหรับ Header จาก main.php */
        :root {
            --primary-color: #FFC107;
            --primary-dark: #FFA000;
            --primary-light: #FFD54F;
            --text-color: #333;
            --light-text-color: #666;
            --border-color: #e0e0e0;
            --bg-light: #f5f5f5;
            --white: #fff;
            --shadow-light: rgba(0,0,0,0.08);
            --shadow-medium: rgba(0,0,0,0.12);
            --success-color: #28a745;
            --danger-color: #dc3545;
        }

        body {
            font-family: 'Sarabun', sans-serif;
            background: var(--bg-light);
            color: var(--text-color);
            line-height: 1.6;
            padding-top: 80px; /* เพื่อไม่ให้เนื้อหาถูกซ่อนใต้ Header */
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
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 1000;
        }
        
        header .header-left { display: flex; align-items: center; gap: 20px; flex-wrap: wrap; }
        header .header-left strong { font-size: 1.5rem; font-weight: 700; white-space: nowrap; }
        header nav { display: flex; flex-wrap: wrap; gap: 15px; margin-left: 20px; }
        header nav a { color: var(--white); text-decoration: none; font-weight: 500; font-size: 0.95rem; padding: 5px 0; position: relative; transition: color 0.3s ease; }
        header nav a:hover { color: var(--primary-dark); }
        header nav a::after { content: ''; position: absolute; width: 0; height: 2px; bottom: -5px; left: 0; background-color: var(--primary-dark); transition: width 0.3s ease-out; }
        header nav a:hover::after { width: 100%; }
        header .header-right { display: flex; align-items: center; gap: 10px; white-space: nowrap; margin-top: 5px; }
        header .header-right img { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid var(--white); }
        header .header-right span { font-weight: 600; font-size: 1rem; }
        header .header-right a { color: var(--white); text-decoration: none; font-weight: 500; transition: color 0.3s ease; }
        header .header-right a:hover { color: var(--primary-dark); }
        .report-button { display: inline-flex; align-items: center; padding: 8px 15px; background-color: var(--danger-color); color: var(--white); font-weight: 600; border-radius: 5px; text-decoration: none; font-size: 0.9rem; transition: background-color 0.3s ease; gap: 8px; white-space: nowrap; }
        .report-button:hover { background-color: #c82333; }
        .nav-link-with-badge { position: relative; display: inline-block; }
        .notification-badge { position: absolute; top: -5px; right: -10px; background-color: #dc3545; color: white; font-size: 0.7em; font-weight: bold; padding: 2px 6px; border-radius: 50%; line-height: 1; display: flex; justify-content: center; align-items: center; min-width: 20px; height: 20px; }

        @media (max-width: 992px) {
            header nav { margin-top: 15px; margin-left: 0; justify-content: center; width: 100%; }
            header .header-right { width: 100%; justify-content: center; margin-top: 15px; }
            .header-left strong { width: 100%; text-align: center; }
            .report-button { margin-top: 10px; }
        }
        @media (max-width: 576px) {
            header { padding: 0.8rem 1rem; }
            header nav a { font-size: 0.85rem; }
            .report-button { font-size: 0.8rem; padding: 6px 10px; }
            .notification-badge { right: -20px; }
        }
        @media (max-width: 768px) {
            header .header-right {
                display: flex;
                flex-direction: column;
                align-items: center;
                text-align: center;
                width: 100%;
                margin-top: 10px;
            }
            
            header .header-right span,
            header .header-right a {
                font-size: 0.9rem;
            }

            header .header-right a {
                margin-top: 5px;
            }
            
            header nav {
                justify-content: center;
            }
        }

        /* CSS เดิมสำหรับหน้าแชท */
        :root {
            --shopee-orange: #FFC107;
            --shopee-orange-dark: #FFC107;
            --shopee-green: #2ecc71;
            --shopee-blue: #3498db;
            --background-color: #f4f7f9;
            --chat-bg-color: #ffffff;
            --input-bg-color: #f0f2f5;
            --sent-msg-bg: #dcf8c6;
            --received-msg-bg: #e5e5ea;
            --text-color: #333;
            --gray-text: #888;
            --shadow-light: rgba(0,0,0,0.1);
        }

        body {
            /* font-family: 'Sarabun', sans-serif; (ถูกกำหนดไว้แล้ว) */
            margin: 0;
            background: var(--background-color);
            color: var(--text-color);
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        .back-button {
            align-self: flex-start;
            margin-bottom: 20px;
            padding: 10px 20px;
            background: #6c757d; /* เปลี่ยนสีเป็นสีเทา */
            color: #fff;
            border: none;
            border-radius: 20px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .back-button:hover {
            background: #5a6268; /* สีเทาเข้มขึ้น */
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            transform: translateY(-1px);
        }
        .chat-container {
            width: 100%;
            max-width: 800px;
            display: flex;
            flex-direction: column;
            height: calc(100vh - 120px); /* ปรับความสูงให้หัก header และ padding */
            background: var(--chat-bg-color);
            border-radius: 12px;
            box-shadow: 0 4px 20px var(--shadow-light);
            overflow: hidden;
            margin-top: 20px;
        }

        .chat-header {
            background: var(--shopee-orange);
            color: white;
            padding: 20px;
            font-size: 20px;
            font-weight: 600;
            text-align: center;
        }

        .chat-messages {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            background: var(--input-bg-color);
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .chat-input {
            display: flex;
            padding: 15px 20px;
            border-top: 1px solid #e0e0e0;
            background: #fff;
        }

        .chat-input input {
            flex: 1;
            padding: 12px 20px;
            border: 1px solid #e0e0e0;
            border-radius: 25px;
            font-size: 16px;
            background: #f8f8f8;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }

        .chat-input input:focus {
            outline: none;
            border-color: var(--shopee-orange);
            box-shadow: 0 0 5px rgba(255, 193, 7, 0.5);
        }

        .chat-input button {
            background: var(--shopee-orange);
            border: none;
            color: white;
            padding: 0 20px;
            margin-left: 10px;
            border-radius: 25px;
            cursor: pointer;
            font-size: 18px;
            transition: background 0.3s ease, transform 0.2s ease;
        }

        .chat-input button:hover {
            background: var(--shopee-orange-dark);
            transform: scale(1.05);
        }

        .chat-input button {
    background: var(--shopee-orange);
    border: none;
    color: white;
    padding: 0 15px;
    margin-left: 8px;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    cursor: pointer;
    font-size: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.3s ease, transform 0.2s ease;
}

.chat-input button:hover {
    background: var(--shopee-orange-dark);
    transform: scale(1.05);
}

.chat-input i {
    pointer-events: none;
}


        .message {
    position: relative;
    max-width: 75%;
    padding: 12px 18px;
    border-radius: 18px;
    line-height: 1.6; /* เพิ่มความสูงบรรทัดให้อ่านง่าย */
    word-wrap: break-word;
    font-size: 16px; /* ขนาดตัวอักษรใหญ่ขึ้น */
    font-weight: 500;
}

/* ข้อความของเรา */
.message.own {
    background: var(--shopee-orange);
    color: #000; /* เปลี่ยนเป็นดำเพื่ออ่านง่าย */
    align-self: flex-end;
    border-bottom-right-radius: 2px;
}

/* ข้อความคนอื่น */
.message.other {
    background: #e0e0e0;
    color: #000; /* เปลี่ยนเป็นดำ */
    align-self: flex-start;
    border-bottom-left-radius: 2px;
}


        .msg-time {
    font-size: 12px;
    color: #555; /* สีเทาเข้มขึ้น อ่านง่าย */
    margin-top: 5px;
    display: block;
}

.message.own .msg-time {
    color: rgba(0, 0, 0, 0.6); /* สำหรับข้อความเรา */
}



    </style>
</head>
<body>
    <a href="#" onclick="history.back()" class="back-button">
    <i class="fas fa-arrow-left"></i> ย้อนกลับ
</a>

<header>
    <div class="header-left">
        <strong><i class="fas fa-handshake"></i> SWAPSPACE</strong>
        <nav>
            <a href="main.php"><i class="fas fa-home"></i> หน้าแรก</a>
            <a href="my_items.php"><i class="fas fa-box-open"></i> ของฉัน</a>
            <a href="exchange_requests_received.php" class="nav-link-with-badge">
                <i class="fas fa-inbox"></i> คำขอที่คุณได้รับ
                <?php if ($unreadRequestsCount > 0): ?>
                    <span class="notification-badge"><?php echo $unreadRequestsCount; ?></span>
                <?php endif; ?>
            </a>
            <a href="exchange_requests_sent.php"><i class="fas fa-paper-plane"></i> คำขอที่คุณส่ง</a>
            <a href="my_reviews.php"><i class="fas fa-star-half-alt"></i> รีวิวของฉัน</a>
            <a href="reviews_received.php"><i class="fas fa-star"></i> รีวิวที่คุณได้รับ</a>
            <a href="edit_profile.php"><i class="fas fa-user-cog"></i> จัดการโปรไฟล์</a>
            <a href="report_issue.php" class="report-button">
                <i class="fas fa-exclamation-triangle"></i> แจ้งปัญหา
            </a>
        </nav>
    </div>
    <div class="header-right">
        <img src="<?php echo htmlspecialchars($profileImage); ?>" alt="รูปโปรไฟล์">
        <span>สวัสดี, <?php echo htmlspecialchars($username); ?></span> |
        <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> ออกจากระบบ</a>
    </div>
</header>

<a href="#" onclick="history.back()" class="back-button">
    <i class="fas fa-arrow-left"></i> ย้อนกลับ
</a>

<div class="chat-container">
    <div class="chat-header">
        <i class="fas fa-comments"></i> ห้องแชทแลกเปลี่ยน
    </div>
    <div id="chat-messages" class="chat-messages">
       
    </div>
    <form id="chat-form" class="chat-input">
    <input type="text" id="message" name="message" placeholder="พิมพ์ข้อความ..." autocomplete="off">
    
    <!-- ปุ่มอัปโหลดรูป -->
    <input type="file" id="image-input" accept="image/*" style="display:none;">
    <button type="button" id="send-image" title="ส่งรูป">
        <i class="fas fa-image"></i>
    </button>

    <!-- ปุ่มส่งตำแหน่ง -->
    <button type="button" id="send-location" title="ส่งตำแหน่ง">
        <i class="fas fa-map-marker-alt"></i>
    </button>
    
    <!-- ปุ่มส่งข้อความ -->
    <button type="submit">
        <i class="fas fa-paper-plane"></i>
    </button>
</form>


</div>

<script>
    const userId = <?= json_encode($userId) ?>;
    const requestId = <?= json_encode($requestId) ?>;
    const chatBox = document.getElementById("chat-messages");
    const form = document.getElementById("chat-form");
    const messageInput = document.getElementById("message");

    function escapeHtml(str) {
        return str
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
    

    function linkify(text) {
    // ✅ ตรวจสอบถ้าเป็นลิงก์ Google Maps
    if (text.includes("https://www.google.com/maps?q=")) {
        const coords = text.split("q=")[1];
        const safeUrl = escapeHtml(text.match(/https:\/\/www\.google\.com\/maps\?q=[^ ]+/)[0]);

        return `
            <div>
                <strong>📍 ตำแหน่งของฉัน</strong><br>
                <iframe 
                    src="https://www.google.com/maps?q=${coords}&output=embed" 
                    width="250" height="200" 
                    style="border:0; margin-top:5px; border-radius:10px;" 
                    allowfullscreen="" loading="lazy">
                </iframe><br>
                <a href="${safeUrl}" target="_blank" rel="noopener noreferrer" style="color:#007bff; text-decoration:underline;">
                    🔗 เปิดใน Google Maps
                </a>
            </div>
        `;
    }

    // ✅ กรณีข้อความทั่วไป → escape และทำ link ปกติ
    const safeText = escapeHtml(text);
    const urlRegex = /(https?:\/\/[^\s]+)/g;
    return safeText.replace(urlRegex, url => {
        return `<a href="${url}" target="_blank" rel="noopener noreferrer">${url}</a>`;
    });
}


let lastMessageId = 0; // เก็บ ID ของข้อความล่าสุด

function appendMessage(msg) {
    if (msg.id <= lastMessageId) return;

    const msgDiv = document.createElement("div");
    msgDiv.className = "message " + (msg.sender_id == userId ? "own" : "other");

    let content = "";
    if (msg.message.match(/\.(jpeg|jpg|png|gif)$/i)) {
        content = `<div><img src="../${escapeHtml(msg.message)}" style="max-width:200px; border-radius:8px;"></div>`;
    } else {
        content = `<div>${linkify(msg.message)}</div>`;
    }

    // เพิ่มปุ่มลบเฉพาะข้อความของเรา
    if (msg.sender_id == userId) {
        content += `<button class="delete-btn" data-id="${msg.id}" style="margin-top:5px; font-size:12px; color:#fff; background:#dc3545; border:none; border-radius:4px; padding:2px 5px; cursor:pointer;">ลบ</button>`;
    }

    content += `<div class="msg-time">${msg.sent_at}</div>`;
    msgDiv.innerHTML = content;

    chatBox.appendChild(msgDiv);
    chatBox.scrollTop = chatBox.scrollHeight;
    lastMessageId = msg.id;

    // เพิ่ม event listener ให้ปุ่มลบ
    const deleteBtn = msgDiv.querySelector(".delete-btn");
    if (deleteBtn) {
        deleteBtn.addEventListener("click", function() {
            const msgId = this.dataset.id;
            if (confirm("คุณต้องการลบข้อความนี้หรือไม่?")) {
                fetch("delete_message.php", {
                    method: "POST",
                    body: new URLSearchParams({ message_id: msgId }),
                    headers: { "X-Requested-With": "XMLHttpRequest" }
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        msgDiv.remove();
                    } else {
                        alert(data.error || "ลบข้อความไม่สำเร็จ");
                    }
                });
            }
        });
    }
}


function loadMessages() {
    fetch("fetch_messages.php?request_id=" + requestId + "&after_id=" + lastMessageId)
        .then(res => res.json())
        .then(data => {
            data.forEach(msg => appendMessage(msg));
        })
        .catch(error => console.error('Error loading messages:', error));
}


// เรียกโหลดครั้งแรก
loadMessages(); 

// โหลดข้อความใหม่เท่านั้นทุก 3 วินาที
setInterval(loadMessages, 3000);


document.getElementById("send-location").addEventListener("click", function() {
    const btn = this;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

    if (!navigator.geolocation) {
        alert("เบราว์เซอร์ของคุณไม่รองรับการแชร์ตำแหน่ง");
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-map-marker-alt"></i>';
        return;
    }

    navigator.geolocation.getCurrentPosition(function(position) {
        const lat = position.coords.latitude;
        const lon = position.coords.longitude;
        const locationMsg = `📍 ตำแหน่งของฉัน: https://www.google.com/maps?q=${lat},${lon}`;

        const formData = new FormData();
        formData.append("message", locationMsg);

        fetch("chat.php?request_id=" + requestId, {
            method: "POST",
            body: formData,
            headers: { "X-Requested-With": "XMLHttpRequest" }
        })
        .then(() => loadMessages())
        .catch(error => console.error('Error sending location:', error))
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-map-marker-alt"></i>';
        });
    }, function(error) {
        alert("ไม่สามารถดึงตำแหน่งได้: " + error.message);
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-map-marker-alt"></i>';
    });
});

    // เมื่อกดปุ่ม 📷 → เปิดเลือกไฟล์
document.getElementById("send-image").addEventListener("click", function() {
    document.getElementById("image-input").click();
});

// เมื่อเลือกไฟล์ → อัปโหลดไปที่ upload_image.php
document.getElementById("image-input").addEventListener("change", function() {
    const file = this.files[0];
    if (!file) return;

    const formData = new FormData();
    formData.append("image", file);
    formData.append("request_id", requestId);

    fetch("upload_image.php", {
        method: "POST",
        body: formData,
        headers: { "X-Requested-With": "XMLHttpRequest" }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            loadMessages();
        } else {
            alert("อัปโหลดรูปไม่สำเร็จ: " + data.error);
        }
    })
    .catch(err => console.error("Upload error:", err));
});


    form.addEventListener("submit", function(e) {
        e.preventDefault();
        if (messageInput.value.trim() === "") return;

        const formData = new FormData(form);
        fetch("chat.php?request_id=" + requestId, {
            method: "POST",
            body: formData,
            headers: { "X-Requested-With": "XMLHttpRequest" }
        })
        .then(() => {
            messageInput.value = "";
            loadMessages();
        })
        .catch(error => console.error('Error sending message:', error));
    });

    loadMessages();
    setInterval(loadMessages, 3000); // Poll for new messages every 3 seconds
</script>
</body>
</html>
<?php
// ปิดการเชื่อมต่อฐานข้อมูล
$conn->close();
?>