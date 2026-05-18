<?php
session_start();

// Connect to the database
$conn = new mysqli("202.28.34.205", "65011211056", "65011211056", "db65011211056");
if ($conn->connect_error) {
    die("เชื่อมต่อฐานข้อมูลล้มเหลว: " . $conn->connect_error);
}

// Fetch featured/recommended items (random selection of recent items)
$featuredSql = "SELECT i.id, i.name, i.category, i.status, i.exchange_for, i.estimated_price, i.price, i.condition_notes, i.description, i.created_at, MIN(img.image) AS image, u.username AS owner_name
                FROM items i
                LEFT JOIN item_images img ON i.id = img.item_id
                JOIN users u ON i.owner_id = u.id
                WHERE i.is_approved = 1 AND i.is_exchanged = 0 AND i.is_available = 1
                GROUP BY i.id, i.name, i.category, i.status, i.exchange_for, i.estimated_price, i.price, i.condition_notes, i.description, i.created_at, u.username
                ORDER BY RAND()
                LIMIT 6";

$featuredResult = $conn->query($featuredSql);

// Fetch all categories from the categories table
$categories = ["all" => "ทั้งหมด"];
$catQuery = $conn->query("SELECT name FROM categories ORDER BY name ASC");
while ($row = $catQuery->fetch_assoc()) {
    $categories[$row['name']] = $row['name'];
}

// Get the selected category
$selectedCategory = isset($_GET['category']) ? $_GET['category'] : "all";

// Build SQL query based on the selected category
$params = [];
// เพิ่มเงื่อนไข `AND i.is_exchanged = 0` และ `AND i.is_available = 1`
$where = "WHERE i.is_approved = 1 AND i.is_exchanged = 0 AND i.is_available = 1";
if ($selectedCategory !== "all") {
    $where .= " AND i.category = ?";
    $params[] = $selectedCategory;
}

$sql = "
SELECT i.id, i.name, i.category, MIN(img.image) AS image
FROM items i
LEFT JOIN item_images img ON i.id = img.item_id
$where
GROUP BY i.id, i.name, i.category
ORDER BY i.created_at DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param("s", ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Function to check if image exists
function getItemImagePath($filename) {
    $path = 'uploads/item/' . $filename;
    if (!empty($filename) && file_exists($path)) {
        return $path;
    }
    return 'uploads/item/default.png'; // Fallback to a default image
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>แลกเปลี่ยนของกันเถอะ! - หน้าหลัก</title>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <style>
        :root {
            --primary-color: #FFC107; /* Amber */
            --primary-dark: #FFA000; /* Darker Amber */
            --secondary-color: #4CAF50; /* Green for action/buttons */
            --text-color: #333;
            --light-bg: #f9f9f9;
            --white: #fff;
            --shadow-light: rgba(0,0,0,0.08);
            --shadow-medium: rgba(0,0,0,0.15);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Kanit', sans-serif;
            background: var(--light-bg);
            color: var(--text-color);
            line-height: 1.6;
        }

        header {
            background-color: var(--primary-color);
            color: var(--white);
            padding: 1rem 0;
            box-shadow: 0 4px 8px var(--shadow-medium);
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 1.5rem;
            flex-wrap: wrap; /* Allow wrapping on smaller screens */
        }

        .header-content h1 {
            margin: 0;
            font-size: 1.8rem;
            font-weight: 700;
            display: flex;
            align-items: center;
        }

        .header-content h1 i {
            margin-right: 10px;
            color: var(--white);
        }

        nav {
            display: flex;
            gap: 20px;
        }

        nav a {
            color: var(--white);
            text-decoration: none;
            font-weight: 600;
            font-size: 1rem;
            transition: color 0.3s ease, transform 0.2s ease;
            padding: 5px 0;
            position: relative;
        }

        nav a:hover {
            color: var(--primary-dark);
            transform: translateY(-2px);
        }
        
        nav a::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: 0;
            left: 0;
            background-color: var(--primary-dark);
            transition: width 0.3s ease-out;
        }

        nav a:hover::after {
            width: 100%;
        }

        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }

        .filter-section {
            background-color: var(--white);
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px var(--shadow-light);
            margin-bottom: 2.5rem;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .filter-section label {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-color);
        }

        .filter-section select {
            padding: 10px 18px;
            font-size: 1rem;
            border-radius: 8px;
            border: 1px solid #ddd;
            background-color: var(--white);
            appearance: none; /* Remove default select arrow */
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23666'%3E%3Cpath d='M7 10l5 5 5-5z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 15px center;
            background-size: 20px;
            cursor: pointer;
            transition: border-color 0.3s ease;
        }

        .filter-section select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(255,193,7,0.2);
        }

        .items-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
        }

        .item-card {
            background-color: var(--white);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 12px var(--shadow-light);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            display: flex;
            flex-direction: column;
            text-decoration: none;
            color: inherit;
        }

        .item-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 8px 20px var(--shadow-medium);
        }

        .item-card img {
            width: 100%;
            height: 220px;
            object-fit: cover;
            border-top-left-radius: 15px;
            border-top-right-radius: 15px;
            display: block;
        }

        .item-content {
            padding: 1rem 1.2rem;
            flex-grow: 1; /* Allows content to push footer down */
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .item-content h4 {
            margin: 0 0 0.5rem;
            font-size: 1.3rem;
            color: var(--text-color);
            word-break: break-word; /* Prevents long words from overflowing */
        }

        .item-content p {
            margin: 0;
            font-size: 0.95rem;
            color: #666;
        }

        .no-items-message {
            text-align: center;
            padding: 3rem;
            font-size: 1.2rem;
            color: #888;
            background-color: var(--white);
            border-radius: 12px;
            box-shadow: 0 2px 10px var(--shadow-light);
            grid-column: 1 / -1; /* Make it span all columns */
        }

        /* Recommendations Section Styles */
        .recommendations-section {
            background: linear-gradient(135deg, rgba(255,193,7,0.08) 0%, rgba(255,255,255,1) 100%);
            border: 2px solid rgba(255,193,7,0.3);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 3rem;
            box-shadow: 0 8px 25px rgba(255,193,7,0.15);
            position: relative;
            overflow: hidden;
        }

        .recommendations-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), #FFD54F, var(--primary-color));
        }

        .recommendations-section h3 {
            margin: 0 0 1rem 0;
            color: var(--primary-dark);
            text-align: center;
            font-size: 1.8rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }

        .recommendations-section p {
            text-align: center;
            margin-bottom: 2rem;
            color: #666;
            font-size: 1rem;
        }

        /* Enhanced Item Card for Recommendations */
        .rec-item-card {
            background: var(--white);
            border: 1px solid rgba(255, 193, 7, 0.2);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            position: relative;
            background: linear-gradient(135deg, rgba(255,255,255,1) 0%, rgba(255,248,225,0.3) 100%);
        }

        .rec-item-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary-color), #FFD54F, var(--primary-color));
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .rec-item-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 12px 40px rgba(255,193,7,0.25);
            border-color: var(--primary-color);
        }

        .rec-item-card:hover::before {
            opacity: 1;
        }

        .rec-item-card img {
            width: 100%;
            height: 220px;
            object-fit: cover;
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .rec-item-card:hover img {
            transform: scale(1.1);
        }

        .rec-item-content {
            padding: 1.5rem;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .rec-item-content h4 {
            margin: 0;
            font-size: 1.4rem;
            color: var(--text-color);
            font-weight: 700;
            line-height: 1.3;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
            min-height: 2.6rem;
            text-align: center;
        }

        .rec-item-badge {
            background: linear-gradient(135deg, rgba(255,193,7,0.15) 0%, rgba(255,193,7,0.05) 100%);
            color: var(--primary-dark);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            border: 1px solid rgba(255,193,7,0.3);
            width: fit-content;
            display: flex;
            align-items: center;
            gap: 6px;
            margin: 0 auto;
        }

        .rec-item-details {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 12px;
        }

        .rec-item-detail {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            font-size: 0.9rem;
            color: #666;
            padding: 8px 12px;
            background: rgba(255,248,225,0.5);
            border-radius: 8px;
            border-left: 3px solid var(--primary-color);
        }

        .rec-item-detail strong {
            color: var(--text-color);
            font-weight: 600;
            min-width: fit-content;
            margin-right: 8px;
            font-size: 0.85rem;
        }

        .rec-item-detail span {
            text-align: right;
            font-weight: 500;
        }

        .rec-item-meta {
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px solid rgba(255,193,7,0.2);
        }

        .rec-item-meta .rec-item-detail {
            background: rgba(245,245,245,0.8);
            border-left-color: #999;
        }

        /* Status-specific styling */
        .price-highlight {
            color: #F57C00;
            font-weight: 700;
            font-size: 1rem;
        }

        .original-price {
            text-decoration: line-through;
            color: #999;
        }

        .free-highlight {
            color: #1976D2;
            font-weight: 700;
            font-size: 1rem;
        }

        /* Status Tags for Recommendations */
        .rec-status-tag {
            position: absolute;
            top: 12px;
            right: 12px;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: var(--white);
            padding: 8px 16px;
            border-radius: 25px;
            font-size: 0.85rem;
            font-weight: 700;
            z-index: 10;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: 2px solid rgba(255,255,255,0.9);
            backdrop-filter: blur(10px);
        }

        .rec-status-tag.แลกเปลี่ยน {
            background: linear-gradient(135deg, #4CAF50 0%, #388E3C 100%);
            box-shadow: 0 4px 12px rgba(76,175,80,0.3);
        }
        
        .rec-status-tag.แจกฟรี {
            background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
            box-shadow: 0 4px 12px rgba(33,150,243,0.3);
        }
        
        .rec-status-tag.ขายราคาถูก {
            background: linear-gradient(135deg, #FF9800 0%, #F57C00 100%);
            box-shadow: 0 4px 12px rgba(255,152,0,0.3);
        }

        footer {
            background-color: var(--primary-color);
            color: var(--white);
            text-align: center;
            padding: 1.5rem;
            margin-top: 4rem;
            font-size: 0.95rem;
            box-shadow: 0 -4px 8px var(--shadow-medium);
        }

        footer a {
            color: var(--white);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }

        footer a:hover {
            color: var(--primary-dark);
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }

            nav {
                flex-direction: column;
                gap: 10px;
            }

            .header-content h1 {
                font-size: 1.5rem;
            }

            .filter-section {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-section select {
                width: 100%;
            }

            .items-grid {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            }
        }

        @media (max-width: 480px) {
            .items-grid {
                grid-template-columns: 1fr; /* Single column on very small screens */
            }

            .item-card img {
                height: 180px;
            }
        }
    </style>
</head>
<body>

    <header>
        <div class="header-content">
            <h1><i class="fas fa-handshake"></i> แลกเปลี่ยนของกันเถอะ!</h1>
            <nav>
                <a href="login.html"><i class="fas fa-sign-in-alt"></i> เข้าสู่ระบบ</a>
                <a href="register.html"><i class="fas fa-user-plus"></i> สมัครสมาชิก</a>
            </nav>
        </div>
    </header>

    <div class="container">
        <div class="filter-section">
            <form method="get" class="filter-form">
                <label for="category">เลือกดูตามหมวดหมู่:</label>
                <select name="category" id="category" onchange="this.form.submit()">
                    <?php foreach ($categories as $key => $label): ?>
                        <option value="<?php echo htmlspecialchars($key); ?>" <?php if ($selectedCategory === $key) echo "selected"; ?>>
                            <?php echo htmlspecialchars($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <noscript><button type="submit">ค้นหา</button></noscript>
            </form>
        </div>

        <!-- แนะนำสำหรับคุณ Section -->
        <div class="recommendations-section">
            <h3><i class="fas fa-star"></i> แนะนำสำหรับคุณ</h3>
            <p>สิ่งของยอดนิยมและแนะนำที่กำลังได้รับความสนใจจากสมาชิก</p>
            <div class="items-grid">
                <?php if ($featuredResult && $featuredResult->num_rows > 0): ?>
                    <?php while($rec = $featuredResult->fetch_assoc()): ?>
                        <a href="user/item_detail.php?id=<?php echo $rec['id']; ?>" class="rec-item-card">
                            <?php if (!empty($rec['status'])): ?>
                                <span class="rec-status-tag <?php echo htmlspecialchars($rec['status']); ?>"><?php echo htmlspecialchars($rec['status']); ?></span>
                            <?php endif; ?>
                            <?php $recImgSrc = getItemImagePath($rec['image']); ?>
                            <img src="<?php echo htmlspecialchars($recImgSrc); ?>" alt="<?php echo htmlspecialchars($rec['name']); ?>" />
                            <div class="rec-item-content">
                                <h4><?php echo htmlspecialchars($rec['name']); ?></h4>
                                <div class="rec-item-badge">
                                    <i class="fas fa-tag"></i> <?php echo htmlspecialchars($rec['category']); ?>
                                </div>
                                
                                <div class="rec-item-details">
                                    <?php if ($rec['status'] === 'แลกเปลี่ยน'): ?>
                                        <?php if (!empty($rec['exchange_for'])): ?>
                                            <div class="rec-item-detail">
                                                <strong><i class="fas fa-handshake"></i> ต้องการแลก:</strong>
                                                <span><?php echo htmlspecialchars($rec['exchange_for']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (isset($rec['estimated_price']) && $rec['estimated_price'] > 0): ?>
                                            <div class="rec-item-detail">
                                                <strong><i class="fas fa-chart-line"></i> ราคาประเมิน:</strong>
                                                <span><?php echo number_format($rec['estimated_price'], 0); ?> บาท</span>
                                            </div>
                                        <?php endif; ?>
                                    <?php elseif ($rec['status'] === 'ขายราคาถูก'): ?>
                                        <?php if (!empty($rec['estimated_price'])): ?>
                                            <div class="rec-item-detail">
                                                <strong><i class="fas fa-money-bill-wave"></i> ราคาขาย:</strong>
                                                <span class="price-highlight"><?php echo number_format($rec['estimated_price'], 0); ?> บาท</span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (isset($rec['price']) && $rec['price'] > 0 && $rec['price'] > $rec['estimated_price']): ?>
                                            <div class="rec-item-detail">
                                                <strong><i class="fas fa-tag"></i> ราคาเดิม:</strong>
                                                <span class="original-price"><?php echo number_format($rec['price'], 0); ?> บาท</span>
                                            </div>
                                        <?php endif; ?>
                                    <?php elseif ($rec['status'] === 'แจกฟรี'): ?>
                                        <div class="rec-item-detail">
                                            <strong><i class="fas fa-gift"></i> แจกฟรี:</strong>
                                            <span class="free-highlight">100% ฟรี</span>
                                        </div>
                                        <?php if (isset($rec['price']) && $rec['price'] > 0): ?>
                                            <div class="rec-item-detail">
                                                <strong><i class="fas fa-tags"></i> ราคาเดิม:</strong>
                                                <span><?php echo number_format($rec['price'], 0); ?> บาท</span>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <!-- Owner and Date Information -->
                                    <div class="rec-item-meta">
                                        <div class="rec-item-detail">
                                            <strong><i class="fas fa-user"></i> เจ้าของ:</strong>
                                            <span><?php echo htmlspecialchars($rec['owner_name']); ?></span>
                                        </div>
                                        <div class="rec-item-detail">
                                            <strong><i class="fas fa-calendar-alt"></i> ลงเมื่อ:</strong>
                                            <span><?php echo date("d/m/Y", strtotime($rec['created_at'])); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </a>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="no-items-message">
                        <strong>😕 ยังไม่มีสิ่งของแนะนำในขณะนี้</strong>
                        <p>ลองเข้าสู่ระบบและเพิ่มสิ่งของของคุณเพื่อแลกเปลี่ยน!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <section class="items-grid">
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while ($item = $result->fetch_assoc()): ?>
                    <?php $imgSrc = getItemImagePath($item['image']); ?>
                    <a href="user/item_detail.php?id=<?php echo $item['id']; ?>" class="item-card">
                        <img src="<?php echo htmlspecialchars($imgSrc); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" />
                        <div class="item-content">
                            <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                            <p>หมวดหมู่: **<?php echo htmlspecialchars($item['category']); ?>**</p>
                        </div>
                    </a>
                <?php endwhile; ?>
            <?php else: ?>
                <p class="no-items-message">😕 ไม่มีรายการสิ่งของในหมวดหมู่ที่เลือก หรือยังไม่มีสิ่งของในระบบเลย</p>
            <?php endif; ?>
        </section>
    </div>

    <footer>
        <p>&copy; 2025 แลกเปลี่ยนของกันเถอะ! | <a href="about.html">เกี่ยวกับเรา</a></p>
    </footer>

</body>
</html>

<?php
$conn->close();
?>