<?php
session_start();

// ถ้ายังไม่ล็อกอินให้ redirect ไป login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ยินดีต้อนรับ | SWAPSPACE</title>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        :root {
            --primary-color: #FFC107;
            --primary-dark: #FFA000;
            --text-color: #333;
            --bg-light: #f5f5f5;
            --white: #fff;
            --shadow-medium: rgba(0,0,0,0.12);
            --exchange-tag-bg: #4CAF50;
            --giveaway-tag-bg: #2196F3;
            --sell-tag-bg: #FF9800;
        }

        body {
            font-family: 'Kanit', sans-serif;
            background: var(--bg-light);
            color: var(--text-color);
            line-height: 1.6;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        .welcome-box {
            background: var(--white);
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 15px var(--shadow-medium);
            text-align: center;
            max-width: 500px;
            width: 100%;
        }

        .welcome-box h2 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-dark);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .category-buttons {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .category-buttons a {
            text-decoration: none;
            color: var(--white);
            font-weight: 600;
            padding: 15px;
            border-radius: 8px;
            font-size: 1.1rem;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .category-buttons a:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        }

        .btn-exchange {
            background-color: var(--exchange-tag-bg);
        }
        .btn-giveaway {
            background-color: var(--giveaway-tag-bg);
        }
        .btn-sell {
            background-color: var(--sell-tag-bg);
        }
        .btn-all {
            background-color: var(--primary-color);
        }
        
        @media (max-width: 576px) {
            .welcome-box {
                padding: 30px;
            }
            .welcome-box h2 {
                font-size: 1.75rem;
            }
        }
    </style>
</head>
<body>
    <div class="welcome-box">
        <h2><i class="fas fa-bars"></i> เลือกสถานะสิ่งของ</h2>
        <div class="category-buttons">
            <a href="main.php?status=แลกเปลี่ยน" class="btn-exchange">
                <i class="fas fa-sync-alt"></i> แลกเปลี่ยน
            </a>
            <a href="main.php?status=ขายราคาถูก" class="btn-sell">
                <i class="fas fa-tags"></i> ขายราคาถูก
            </a>
            <a href="main.php?status=แจกฟรี" class="btn-giveaway">
                <i class="fas fa-gift"></i> แจกฟรี
            </a>
            <a href="main.php?status=all" class="btn-all">
                <i class="fas fa-list-alt"></i> ดูทั้งหมด
            </a>
        </div>
    </div>
</body>
</html>