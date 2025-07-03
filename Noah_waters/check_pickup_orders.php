<?php
require 'config.php';

echo "Checking pickup orders specifically...\n\n";

// Check for orders with pickup method
$sql = "SELECT id, fullname, shipping_method, pickup_time, created_at 
        FROM orders 
        WHERE LOWER(shipping_method) = 'pickup' 
        ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->execute();
$pickup_orders = $stmt->get_result();

echo "Pickup orders found:\n";
$count = 0;
while ($order = $pickup_orders->fetch_assoc()) {
    $count++;
    echo "Order #{$order['id']}:\n";
    echo "  - Fullname: {$order['fullname']}\n";
    echo "  - Shipping Method: '{$order['shipping_method']}'\n";
    echo "  - Pickup Time: '{$order['pickup_time']}'\n";
    echo "  - Created: {$order['created_at']}\n";
    echo "  - Pickup Time Length: " . strlen($order['pickup_time']) . "\n";
    echo "  - Pickup Time Empty: " . (empty($order['pickup_time']) ? 'Yes' : 'No') . "\n";
    echo "  - Pickup Time Null: " . (is_null($order['pickup_time']) ? 'Yes' : 'No') . "\n";
    echo "\n";
}

if ($count == 0) {
    echo "No pickup orders found in the database.\n";
}

// Check for any orders with 'Pickup' (case sensitive)
$sql2 = "SELECT id, fullname, shipping_method, pickup_time, created_at 
         FROM orders 
         WHERE shipping_method = 'Pickup' 
         ORDER BY created_at DESC";

$stmt2 = $conn->prepare($sql2);
$stmt2->execute();
$pickup_orders_case = $stmt2->get_result();

echo "Orders with exact 'Pickup' method:\n";
$count2 = 0;
while ($order = $pickup_orders_case->fetch_assoc()) {
    $count2++;
    echo "Order #{$order['id']}:\n";
    echo "  - Fullname: {$order['fullname']}\n";
    echo "  - Shipping Method: '{$order['shipping_method']}'\n";
    echo "  - Pickup Time: '{$order['pickup_time']}'\n";
    echo "  - Created: {$order['created_at']}\n";
    echo "\n";
}

if ($count2 == 0) {
    echo "No orders with exact 'Pickup' method found.\n";
}

$stmt->close();
$stmt2->close();
$conn->close();
?> 