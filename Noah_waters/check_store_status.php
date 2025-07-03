<?php
function isStoreOpen() {
    global $conn;
    $result = $conn->query("SELECT is_open FROM store_availability WHERE id = 1");
    if ($result && $row = $result->fetch_assoc()) {
        return (bool)$row['is_open'];
    }
    return true; // Default to open if there's an error
}

function getStoreMessage() {
    global $conn;
    $result = $conn->query("SELECT message FROM store_availability WHERE id = 1");
    if ($result && $row = $result->fetch_assoc()) {
        return $row['message'];
    }
    return 'Welcome to Noah Waters! We are open for business.';
}
?> 