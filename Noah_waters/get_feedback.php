<?php
// Start output buffering to prevent any extra output
ob_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


require 'config.php';

// Clear any output that might have been generated
ob_clean();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

try {
    // Check if feedback table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'feedback'");
    if ($table_check->num_rows == 0) {
        throw new Exception('Feedback table does not exist');
    }

    // Get average rating for approved feedback
    $avg_sql = "SELECT AVG(rating) as average_rating, COUNT(*) as total_ratings 
                FROM feedback 
                WHERE is_approved = 1";
    $avg_result = $conn->query($avg_sql);
    
    if (!$avg_result) {
        throw new Exception('Database error in average query: ' . $conn->error);
    }
    
    $avg_data = $avg_result->fetch_assoc();
    
    // Get approved feedback
    $sql = "SELECT name, rating, message 
            FROM feedback 
            WHERE is_approved = 1 
            ORDER BY id DESC 
            LIMIT 10";
            
    $result = $conn->query($sql);
    
    if (!$result) {
        throw new Exception('Database error in feedback query: ' . $conn->error);
    }

    $feedback = [];
    while ($row = $result->fetch_assoc()) {
        $feedback[] = [
            'name' => htmlspecialchars($row['name']),
            'rating' => (int)$row['rating'],
            'message' => htmlspecialchars($row['message']),
            'date' => 'Recently' // Since there's no created_at column
        ];
    }

    echo json_encode([
        'success' => true,
        'average_rating' => round($avg_data['average_rating'], 1),
        'total_ratings' => (int)$avg_data['total_ratings'],
        'feedback' => $feedback
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
    // End output buffering and send response
    ob_end_flush();
}
?>