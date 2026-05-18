<?php
require_once 'config/database.php';

echo "<h2>🔍 Debug Rating Button Issue</h2>";

// Check current exchange requests
echo "<h3>Current Exchange Requests:</h3>";
$sql = "SELECT er.id, er.status, er.shipping_status, er.owner_confirmed, er.requester_confirmed, 
               er.owner_id, er.requester_id, er.item_owner_id, er.item_request_id,
               i1.name as owner_item_name, i2.name as request_item_name,
               u1.name as owner_name, u2.name as requester_name
        FROM exchange_requests er
        LEFT JOIN items i1 ON er.item_owner_id = i1.id
        LEFT JOIN items i2 ON er.item_request_id = i2.id
        LEFT JOIN users u1 ON er.owner_id = u1.id
        LEFT JOIN users u2 ON er.requester_id = u2.id
        WHERE er.status != 'rejected'
        ORDER BY er.id DESC LIMIT 10";

$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%; font-size: 12px;'>";
    echo "<tr><th>ID</th><th>Status</th><th>Shipping</th><th>Owner Confirmed</th><th>Requester Confirmed</th><th>Owner</th><th>Requester</th><th>Owner Item</th><th>Request Item</th></tr>";
    while($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['status']}</td>";
        echo "<td>{$row['shipping_status']}</td>";
        echo "<td>" . ($row['owner_confirmed'] ? 'YES' : 'NO') . "</td>";
        echo "<td>" . ($row['requester_confirmed'] ? 'YES' : 'NO') . "</td>";
        echo "<td>{$row['owner_name']} (ID: {$row['owner_id']})</td>";
        echo "<td>{$row['requester_name']} (ID: {$row['requester_id']})</td>";
        echo "<td>{$row['owner_item_name']}</td>";
        echo "<td>{$row['request_item_name']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No exchange requests found.</p>";
}

// Check review records
echo "<h3>Review Records:</h3>";
$sql2 = "SELECT r.id, r.exchange_id, r.reviewer_id, r.reviewee_id, r.rating, r.item_id,
                u1.name as reviewer_name, u2.name as reviewee_name,
                er.status as exchange_status, er.shipping_status
         FROM reviews r
         LEFT JOIN users u1 ON r.reviewer_id = u1.id
         LEFT JOIN users u2 ON r.reviewee_id = u2.id
         LEFT JOIN exchange_requests er ON r.exchange_id = er.id
         ORDER BY r.id DESC LIMIT 15";

$result2 = $conn->query($sql2);
if ($result2 && $result2->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%; font-size: 12px;'>";
    echo "<tr><th>Review ID</th><th>Exchange ID</th><th>Reviewer</th><th>Reviewee</th><th>Rating</th><th>Exchange Status</th><th>Shipping Status</th></tr>";
    while($row = $result2->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['exchange_id']}</td>";
        echo "<td>{$row['reviewer_name']} (ID: {$row['reviewer_id']})</td>";
        echo "<td>{$row['reviewee_name']} (ID: {$row['reviewee_id']})</td>";
        echo "<td>" . ($row['rating'] == 0 ? '<strong style="color: blue;">Pending Rating</strong>' : $row['rating'] . ' stars') . "</td>";
        echo "<td>{$row['exchange_status']}</td>";
        echo "<td>{$row['shipping_status']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No review records found.</p>";
}

// Let's create a test scenario
echo "<h3>🧪 Create Test Scenario:</h3>";
echo "<form method='post' style='background: #f0f0f0; padding: 15px; border-radius: 5px;'>";
echo "<p>Create a test exchange request that's ready for rating:</p>";
echo "<button type='submit' name='create_test' value='1' style='background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px;'>Create Test Exchange</button>";
echo "</form>";

if (isset($_POST['create_test'])) {
    echo "<h4>🔨 Creating Test Data...</h4>";
    
    try {
        // Get users
        $users_sql = "SELECT id, name FROM users LIMIT 2";
        $users_result = $conn->query($users_sql);
        $users = [];
        while($user = $users_result->fetch_assoc()) {
            $users[] = $user;
        }
        
        if (count($users) < 2) {
            echo "<p style='color: red;'>❌ Need at least 2 users to create test exchange</p>";
        } else {
            // Get items
            $items_sql = "SELECT id, name, owner_id FROM items LIMIT 2";
            $items_result = $conn->query($items_sql);
            $items = [];
            while($item = $items_result->fetch_assoc()) {
                $items[] = $item;
            }
            
            if (count($items) < 2) {
                echo "<p style='color: red;'>❌ Need at least 2 items to create test exchange</p>";
            } else {
                // Create test exchange request
                $owner_id = $items[0]['owner_id'];
                $requester_id = $users[1]['id']; // Different user
                $item_owner_id = $items[0]['id'];
                $item_request_id = $items[1]['id'];
                
                $test_sql = "INSERT INTO exchange_requests (owner_id, requester_id, item_owner_id, item_request_id, status, shipping_status, owner_confirmed, requester_confirmed, created_at) 
                            VALUES (?, ?, ?, ?, 'accepted', 'shipped', 1, 0, NOW())";
                $stmt = $conn->prepare($test_sql);
                $stmt->bind_param("iiii", $owner_id, $requester_id, $item_owner_id, $item_request_id);
                
                if ($stmt->execute()) {
                    $test_exchange_id = $conn->insert_id;
                    
                    // Create review records immediately
                    $review_sql = "INSERT INTO reviews (exchange_id, reviewer_id, reviewee_id, item_id, rating, comment) VALUES (?, ?, ?, ?, 0, NULL)";
                    $review_stmt = $conn->prepare($review_sql);
                    
                    // Owner can rate requester
                    $review_stmt->bind_param("iiii", $test_exchange_id, $owner_id, $requester_id, $item_owner_id);
                    $review_stmt->execute();
                    
                    // Requester can rate owner
                    $review_stmt->bind_param("iiii", $test_exchange_id, $requester_id, $owner_id, $item_request_id);
                    $review_stmt->execute();
                    
                    echo "<p style='color: green;'>✅ Test exchange created successfully!</p>";
                    echo "<p><strong>Exchange ID:</strong> {$test_exchange_id}</p>";
                    echo "<p><strong>Owner:</strong> {$users[0]['name']} (ID: {$owner_id}) - Has confirmed receipt</p>";
                    echo "<p><strong>Requester:</strong> {$users[1]['name']} (ID: {$requester_id}) - Has NOT confirmed receipt</p>";
                    echo "<p><strong>Status:</strong> accepted, shipping_status: shipped</p>";
                    echo "<p>👆 <strong>Owner should see rating button now!</strong></p>";
                    
                    $review_stmt->close();
                } else {
                    echo "<p style='color: red;'>❌ Failed to create test exchange</p>";
                }
                $stmt->close();
            }
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    }
}

// Rating button conditions check
echo "<h3>📝 Rating Button Conditions:</h3>";
echo "<div style='background: #e8f4f8; padding: 15px; border-radius: 5px;'>";
echo "<p><strong>For exchange_requests_received.php (item owners):</strong></p>";
echo "<code>if ((\$shipping_status === 'delivered' || \$row['status'] === 'completed') || (\$shipping_status === 'shipped' && \$row['owner_confirmed'] == 1))</code>";
echo "<p><strong>For exchange_requests_sent.php (requesters):</strong></p>";
echo "<code>if ((\$shipping_status === 'delivered' || \$row['status'] === 'completed') || (\$shipping_status === 'shipped' && \$row['requester_confirmed'] == 1))</code>";
echo "</div>";

echo "<h3>🔍 How to Test:</h3>";
echo "<ol>";
echo "<li>Create test exchange using button above</li>";
echo "<li>Go to <a href='exchange_requests_received.php' target='_blank'>exchange_requests_received.php</a></li>";
echo "<li>Check browser 'View Source' to see DEBUG comments</li>";
echo "<li>Look for rating buttons on exchanges where owner_confirmed=1 and shipping_status='shipped'</li>";
echo "</ol>";

$conn->close();
?>

<style>
table { margin: 10px 0; }
th, td { padding: 8px; text-align: left; border: 1px solid #ddd; }
th { background-color: #f2f2f2; }
</style>