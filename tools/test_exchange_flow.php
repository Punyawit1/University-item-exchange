<?php
// Simple test harness to simulate mutual confirmation flow.
// USAGE: Run from command line: php tools/test_exchange_flow.php
// WARNING: This will insert rows into your local database `item_exchange` and modify items' is_available.

$mysqli = new mysqli('localhost', 'root', '', 'item_exchange');
if ($mysqli->connect_errno) {
    echo "DB connect error: " . $mysqli->connect_error . PHP_EOL;
    exit(1);
}

function q($m, $sql, $types = null, $params = []){
    $stmt = $m->prepare($sql);
    if (!$stmt) { echo "prepare failed: $sql\n"; var_dump($m->error); exit; }
    if ($types) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt;
}

// 1) create two users
$q = q($mysqli, "INSERT INTO users (username, fullname, password, profile_image, score) VALUES ('test_requester', 'Requester Test', 'pass', '', 0)");
$requester_id = $mysqli->insert_id;
$q = q($mysqli, "INSERT INTO users (username, fullname, password, profile_image, score) VALUES ('test_owner', 'Owner Test', 'pass', '', 0)");
$owner_id = $mysqli->insert_id;

// 2) create two items
$q = q($mysqli, "INSERT INTO items (owner_id, name, description, is_available) VALUES (?, 'Requester Item', 'test', 1)", 'i', [$requester_id]);
$their_item_id = $mysqli->insert_id;
$q = q($mysqli, "INSERT INTO items (owner_id, name, description, is_available) VALUES (?, 'Owner Item', 'test', 1)", 'i', [$owner_id]);
$owner_item_id = $mysqli->insert_id;

// 3) insert exchange_requests row (status pending)
$sql = "INSERT INTO exchange_requests (requester_id, item_owner_id, item_request_id, status, created_at) VALUES (?, ?, ?, 'pending', NOW())";
$q = q($mysqli, $sql, 'iii', [$requester_id, $owner_item_id, $their_item_id]);
$exchange_id = $mysqli->insert_id;

echo "Created test exchange id=$exchange_id, requester=$requester_id, owner=$owner_id\n";

// 4) simulate owner selecting delivery -> this normally sets status=shipping and resets confirmations
$q = q($mysqli, "UPDATE exchange_requests SET status='shipping', delivery_method='pickup', receiver_confirmed=0, requester_confirmed=0 WHERE id = ?", 'i', [$exchange_id]);

// 5) owner confirms (set receiver_confirmed)
$q = q($mysqli, "UPDATE exchange_requests SET receiver_confirmed = 1, status = 'confirm_receiver' WHERE id = ?", 'i', [$exchange_id]);

// Show DB state
$res = $mysqli->query("SELECT * FROM exchange_requests WHERE id = $exchange_id");
$row = $res->fetch_assoc();
print_r($row);

// 6) requester confirms
$q = q($mysqli, "UPDATE exchange_requests SET requester_confirmed = 1, status = 'confirm_requester' WHERE id = ?", 'i', [$exchange_id]);

// After both confirmed, simulate acceptance logic (this normally runs in process_exchange.php)
// If both are 1 -> set status to accepted, update items
$res = $mysqli->query("SELECT receiver_confirmed, requester_confirmed, item_owner_id, item_request_id FROM exchange_requests WHERE id = $exchange_id");
$r = $res->fetch_assoc();
if ($r['receiver_confirmed'] && $r['requester_confirmed']) {
    $mysqli->query("UPDATE exchange_requests SET status = 'accepted' WHERE id = $exchange_id");
    $mysqli->query("UPDATE items SET is_available = 0 WHERE id = " . intval($r['item_owner_id']));
    if (!empty($r['item_request_id'])) $mysqli->query("UPDATE items SET is_available = 0 WHERE id = " . intval($r['item_request_id']));
    echo "Both confirmed -> accepted.\n";
}

$res = $mysqli->query("SELECT * FROM exchange_requests WHERE id = $exchange_id");
print_r($res->fetch_assoc());

echo "Check items: \n";
$res = $mysqli->query("SELECT id, is_available FROM items WHERE id IN ($owner_item_id,$their_item_id)");
while ($it = $res->fetch_assoc()) { print_r($it); }

echo "Test finished. CLEANUP is NOT performed by this script. Remove test rows manually if needed.\n";

$mysqli->close();

?>