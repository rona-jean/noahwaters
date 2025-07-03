<?php
require 'config.php';

echo "Checking database tables...\n";

// Check store_availability table
$result = $conn->query("SHOW TABLES LIKE 'store_availability'");
if ($result->num_rows > 0) {
    echo "✓ store_availability table exists\n";
    
    // Check structure
    $result = $conn->query("DESCRIBE store_availability");
    while ($row = $result->fetch_assoc()) {
        echo "  - {$row['Field']}: {$row['Type']}\n";
    }
} else {
    echo "✗ store_availability table missing\n";
}

// Check products table
$result = $conn->query("SHOW TABLES LIKE 'products'");
if ($result->num_rows > 0) {
    echo "✓ products table exists\n";
    
    // Check structure
    $result = $conn->query("DESCRIBE products");
    while ($row = $result->fetch_assoc()) {
        echo "  - {$row['Field']}: {$row['Type']}\n";
    }
} else {
    echo "✗ products table missing\n";
}

// Check cart table
$result = $conn->query("SHOW TABLES LIKE 'cart'");
if ($result->num_rows > 0) {
    echo "✓ cart table exists\n";
    
    // Check structure
    $result = $conn->query("DESCRIBE cart");
    while ($row = $result->fetch_assoc()) {
        echo "  - {$row['Field']}: {$row['Type']}\n";
    }
} else {
    echo "✗ cart table missing\n";
}

// Check if store_availability has data
$result = $conn->query("SELECT * FROM store_availability WHERE id = 1");
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo "✓ store_availability has data: is_open = {$row['is_open']}\n";
} else {
    echo "✗ store_availability missing data\n";
}

echo "Table check complete.\n";
?> 