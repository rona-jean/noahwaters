<?php
// Start session first, before any output
session_start();

// Start output buffering to prevent any extra output
ob_start();

require 'config.php';

// Clear any output that might have been generated
ob_clean();

header('Content-Type: application/json');

// Function to validate email
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Function to sanitize input
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

try {
    // Check if it's a POST request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Get and validate input
    $name = isset($_POST['name']) ? sanitizeInput($_POST['name']) : '';
    $email = isset($_POST['email']) ? sanitizeInput($_POST['email']) : '';
    $rating = isset($_POST['rating']) ? intval($_POST['rating']) : 0;
    $message = isset($_POST['message']) ? sanitizeInput($_POST['message']) : '';

    // Validate required fields
    if (empty($name)) {
        throw new Exception('Name is required');
    }
    if (empty($email)) {
        throw new Exception('Email is required');
    }
    if (!isValidEmail($email)) {
        throw new Exception('Invalid email format');
    }
    if ($rating < 1 || $rating > 5) {
        throw new Exception('Invalid rating value');
    }
    if (empty($message)) {
        throw new Exception('Message is required');
    }

    // Get user_id from session (already started)
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

    // Check if user has already submitted feedback
    $stmt = $conn->prepare("SELECT COUNT(*) as feedback_count FROM feedback WHERE email = ? OR (user_id IS NOT NULL AND user_id = ?)");
    $stmt->bind_param("si", $email, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if ($row['feedback_count'] > 0) {
        // Return success with informational message instead of error
        echo json_encode([
            'success' => true,
            'message' => 'You have already submitted feedback. Thank you for your input!'
        ]);
        exit; // Exit immediately to prevent any extra output
    }

    // Prepare and execute the SQL statement
    $stmt = $conn->prepare("INSERT INTO feedback (name, email, rating, message, user_id, is_approved) VALUES (?, ?, ?, ?, ?, 1)");
    
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }

    $stmt->bind_param("ssisi", $name, $email, $rating, $message, $user_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Error saving feedback: ' . $stmt->error);
    }

    // Success response
    echo json_encode([
        'success' => true,
        'message' => 'Thank you for your feedback!'
    ]);
    exit; // Exit immediately to prevent any extra output

} catch (Exception $e) {
    // Error response
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit; // Exit immediately to prevent any extra output
} finally {
    // Close statement and connection
    if (isset($stmt)) {
        $stmt->close();
    }
    if (isset($conn)) {
        $conn->close();
    }
    // End output buffering and send response
    ob_end_flush();
} 