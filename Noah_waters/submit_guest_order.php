<?php
session_start();
require 'config.php';
require 'check_store_status.php';

// Check if store is open
if (!isStoreOpen()) {
    $_SESSION['error'] = 'Store is currently closed. ' . getStoreMessage();
    header('Location: new_cart.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = $_POST['fullname'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $address = $_POST['address'] ?? '';
    $notes = $_POST['notes'] ?? '';
    $shippingMethod = $_POST['shipping_method'] ?? 'Delivery';
    
    // Fix pickup time logic - only set pickup time if shipping method is Pickup AND pickup_time is provided
    $pickupTime = null;
    if ($shippingMethod === 'Pickup' && isset($_POST['pickup_time']) && !empty($_POST['pickup_time'])) {
        $pickupTime = $_POST['pickup_time'];
    }

    if (empty($_SESSION['guest_cart'])) {
        die("Cart is empty.");
    }

    $total = 0;
    foreach ($_SESSION['guest_cart'] as $item) {
        $total += $item['price'] * $item['quantity'];
    }

    $sql = "INSERT INTO orders (user_id, fullname, phone, delivery_address, shipping_method, pickup_time, total_amount, usertype, notes) 
            VALUES (NULL, ?, ?, ?, ?, ?, ?, 'guest', ?)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("sssssds", $fullname, $phone, $address, $shippingMethod, $pickupTime, $total, $notes);

    if (!$stmt->execute()) {
        die("Execute failed: " . $stmt->error);
    }

    $orderId = $stmt->insert_id;
    $stmt->close();

    $itemStmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
    $checkProductStmt = $conn->prepare("SELECT category, is_borrowable FROM products WHERE id = ?");
    $borrowStmt = $conn->prepare("INSERT INTO borrowed_containers (user_id, order_id, container_id, borrowed_at, returned) VALUES (NULL, ?, ?, NOW(), 0)");

    if (!$itemStmt || !$checkProductStmt || !$borrowStmt) {
        die("Statement prepare failed: " . $conn->error);
    }

    foreach ($_SESSION['guest_cart'] as $item) {
        $productId = $item['product_id'];
        $quantity = $item['quantity'];
        $price = $item['price'];

        $itemStmt->bind_param("iiid", $orderId, $productId, $quantity, $price);
        if (!$itemStmt->execute()) {
            die("Failed to insert order item: " . $itemStmt->error);
        }

        // check kung borrowable ba 
        $checkProductStmt->bind_param("i", $productId);
        $checkProductStmt->execute();
        $checkProductStmt->bind_result($category, $isBorrowable);
        $checkProductStmt->fetch();
        $checkProductStmt->reset();

        if (strtolower($category) === 'container' && $isBorrowable) {
            //nag insert ng kada row kada may nagba borrow
            for ($i = 0; $i < $quantity; $i++) {
                $borrowStmt->bind_param("ii", $orderId, $productId);
                if (!$borrowStmt->execute()) {
                    die("Failed to insert into borrowed_containers: " . $borrowStmt->error);
                }
            }
        }
    }
    
    $itemStmt->close();
    $checkProductStmt->close();
    $borrowStmt->close();

    unset($_SESSION['guest_cart']);

    header("Location: thank_you.php");
    exit;
}
?>