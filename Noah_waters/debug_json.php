<?php
// Diagnostic script to identify JSON parsing issue
echo "=== JSON Output Diagnostic ===\n";

// Simulate POST request
$_POST = [
    'name' => 'Test User',
    'email' => 'test@example.com',
    'rating' => '5',
    'message' => 'Test message'
];

// Start session
session_start();

// Capture output with detailed analysis
ob_start();
include 'submit_feedback.php';
$output = ob_get_clean();

echo "Output length: " . strlen($output) . " characters\n";
echo "Output (hex): " . bin2hex($output) . "\n";
echo "Output (raw): '" . $output . "'\n";

// Find JSON end
$json_end = strrpos($output, '}');
if ($json_end !== false) {
    $json_part = substr($output, 0, $json_end + 1);
    $after_json = substr($output, $json_end + 1);
    
    echo "JSON part: '" . $json_part . "'\n";
    echo "After JSON: '" . $after_json . "'\n";
    echo "After JSON (hex): " . bin2hex($after_json) . "\n";
    
    if (!empty(trim($after_json))) {
        echo "❌ Extra content found after JSON!\n";
    } else {
        echo "✅ No extra content after JSON\n";
    }
}

// Test JSON parsing
$json_data = json_decode($output, true);
if ($json_data === null) {
    echo "❌ JSON Error: " . json_last_error_msg() . "\n";
    echo "❌ JSON Error position: " . json_last_error() . "\n";
} else {
    echo "✅ Valid JSON: " . print_r($json_data, true) . "\n";
}
?> 