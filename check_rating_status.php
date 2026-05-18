<?php
require_once 'config/database.php';

echo "<h2>Checking Exchange Status for Rating Buttons</h2>";

// Check for completed exchanges
echo "<h3>Completed Exchanges:</h3>";
$sql = "SELECT er.id, er.status, er.shipping_status, er.owner_confirmed, er.requester_confirmed, 
               i.name as item_name, u1.name as owner_name, u2.name as requester_name
        FROM exchange_requests er
        JOIN items i ON er.item_id = i.id
        JOIN users u1 ON er.owner_id = u1.id
        JOIN users u2 ON er.requester_id = u2.id
        WHERE er.status = 'completed' OR er.shipping_status = 'delivered'
        ORDER BY er.id DESC LIMIT 5";

$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Status</th><th>Shipping</th><th>Owner Confirmed</th><th>Requester Confirmed</th><th>Item</th><th>Owner</th><th>Requester</th></tr>";
    while($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['status']}</td>";
        echo "<td>{$row['shipping_status']}</td>";
        echo "<td>{$row['owner_confirmed']}</td>";
        echo "<td>{$row['requester_confirmed']}</td>";
        echo "<td>{$row['item_name']}</td>";
        echo "<td>{$row['owner_name']}</td>";
        echo "<td>{$row['requester_name']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No completed exchanges found.</p>";
}

// Check for review records
echo "<h3>Review Records:</h3>";
$sql2 = "SELECT r.id, r.exchange_id, r.reviewer_id, r.reviewee_id, r.rating, 
                u1.name as reviewer_name, u2.name as reviewee_name
         FROM reviews r
         JOIN users u1 ON r.reviewer_id = u1.id
         JOIN users u2 ON r.reviewee_id = u2.id
         ORDER BY r.id DESC LIMIT 10";

$result2 = $conn->query($sql2);
if ($result2 && $result2->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Review ID</th><th>Exchange ID</th><th>Reviewer</th><th>Reviewee</th><th>Rating</th></tr>";
    while($row = $result2->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['exchange_id']}</td>";
        echo "<td>{$row['reviewer_name']}</td>";
        echo "<td>{$row['reviewee_name']}</td>";
        echo "<td>" . ($row['rating'] == 0 ? 'Pending Rating' : $row['rating'] . ' stars') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No review records found.</p>";
}

// Rating button visibility logic explanation
echo "<h3>Updated Rating Button Logic:</h3>";
echo "<p><strong>Rating buttons now show when:</strong></p>";
echo "<ul>";
echo "<li>Exchange status is 'completed' OR shipping status is 'delivered' (both parties confirmed)</li>";
echo "<li><strong>OR</strong> when shipping status is 'shipped' and the current user has confirmed receipt</li>";
echo "<li>AND there is a review record with rating = 0 (pending rating)</li>";
echo "</ul>";

echo "<p><strong>✨ New Feature:</strong> Rating buttons now appear immediately when you click 'confirm receipt', even before the other party confirms!</p>";
echo "<p><strong>Note:</strong> Review records are now created immediately when someone confirms receipt (not only when both parties confirm).</p>";

$conn->close();
?>