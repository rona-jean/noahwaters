<?php
// Debug file to test cart operations
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing cart operations...\n";

// Test database connection
require 'config.php';
echo "Database connection: " . ($conn ? "OK" : "FAILED") . "\n";

// Test store availability table
$result = $conn->query("SELECT * FROM store_availability WHERE id = 1");
if ($result) {
    $row = $result->fetch_assoc();
    echo "Store availability: " . ($row ? "Found" : "Not found") . "\n";
} else {
    echo "Store availability table error: " . $conn->error . "\n";
}

// Test products table
$result = $conn->query("SELECT COUNT(*) as count FROM products");
if ($result) {
    $row = $result->fetch_assoc();
    echo "Products count: " . $row['count'] . "\n";
} else {
    echo "Products table error: " . $conn->error . "\n";
}

// Test cart table
$result = $conn->query("SELECT COUNT(*) as count FROM cart");
if ($result) {
    $row = $result->fetch_assoc();
    echo "Cart items count: " . $row['count'] . "\n";
} else {
    echo "Cart table error: " . $conn->error . "\n";
}

echo "Debug complete.\n";
?> 