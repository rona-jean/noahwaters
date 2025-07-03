<?php
// Simple test to verify JSON output
header('Content-Type: application/json');

$testData = [
    'success' => true,
    'message' => 'Test successful',
    'timestamp' => time()
];

echo json_encode($testData);
?> 