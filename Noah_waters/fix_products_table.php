<?php
require 'config.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Database Diagnostic Tool</h2>";

// 1. Check database connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
echo "<p>✓ Database connection successful</p>";

// 2. Check if products table exists
$result = $conn->query("SHOW TABLES LIKE 'products'");
if ($result->num_rows == 0) {
    die("Error: products table does not exist!");
}
echo "<p>✓ Products table exists</p>";

// 3. Show current table structure
echo "<h3>Current Table Structure:</h3>";
$result = $conn->query("DESCRIBE products");
echo "<pre>";
while ($row = $result->fetch_assoc()) {
    print_r($row);
}
echo "</pre>";

// 4. Try to add the column
echo "<h3>Attempting to add is_out_of_stock column:</h3>";

// First, try to drop the column if it exists (to avoid duplicate column errors)
try {
    $conn->query("ALTER TABLE products DROP COLUMN IF EXISTS is_out_of_stock");
    echo "<p>✓ Dropped existing is_out_of_stock column (if it existed)</p>";
} catch (Exception $e) {
    echo "<p style='color: orange;'>! Warning: " . $e->getMessage() . "</p>";
}

// Now add the column
try {
    $sql = "ALTER TABLE products ADD COLUMN is_out_of_stock TINYINT(1) NOT NULL DEFAULT 0";
    if ($conn->query($sql) === TRUE) {
        echo "<p style='color: green;'>✓ Successfully added is_out_of_stock column</p>";
    } else {
        echo "<p style='color: red;'>✗ Error adding column: " . $conn->error . "</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Exception: " . $e->getMessage() . "</p>";
}

// 5. Show updated table structure
echo "<h3>Updated Table Structure:</h3>";
$result = $conn->query("DESCRIBE products");
echo "<pre>";
while ($row = $result->fetch_assoc()) {
    print_r($row);
}
echo "</pre>";

// 6. Verify the column exists
$checkColumn = $conn->query("SHOW COLUMNS FROM products LIKE 'is_out_of_stock'");
if ($checkColumn->num_rows > 0) {
    echo "<p style='color: green;'>✓ Verified: is_out_of_stock column exists in the table</p>";
} else {
    echo "<p style='color: red;'>✗ Warning: is_out_of_stock column was not found after adding it</p>";
}

$conn->close();
?> 