<?php
require 'config.php';

// Add reset_token and reset_token_expiry columns to users table
$sql1 = "ALTER TABLE users ADD COLUMN IF NOT EXISTS reset_token VARCHAR(255) NULL";
$sql2 = "ALTER TABLE users ADD COLUMN IF NOT EXISTS reset_token_expiry DATETIME NULL";

try {
    if ($conn->query($sql1) === TRUE) {
        echo "reset_token column added successfully or already exists<br>";
    } else {
        echo "Error adding reset_token column: " . $conn->error . "<br>";
    }
    
    if ($conn->query($sql2) === TRUE) {
        echo "reset_token_expiry column added successfully or already exists<br>";
    } else {
        echo "Error adding reset_token_expiry column: " . $conn->error . "<br>";
    }
    
    echo "Database update completed!";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

$conn->close();
?> 