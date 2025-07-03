<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    if (ob_get_level()) {
        ob_end_clean();
    }

    //redirect to login 
    header("Location: login.php");
    exit();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Check if table exists
$table_exists = $conn->query("SHOW TABLES LIKE 'store_availability'");
if ($table_exists->num_rows == 0) {
    // Create table if it doesn't exist
    $create_table_sql = "CREATE TABLE store_availability (
        id INT PRIMARY KEY AUTO_INCREMENT,
        is_open BOOLEAN DEFAULT true,
        message TEXT,
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    $conn->query($create_table_sql);
}

// Insert initial record if not exists
$insert_sql = "INSERT INTO store_availability (id, is_open, message) 
               SELECT 1, true, 'Welcome to Noah Waters! We are open for business.'
               WHERE NOT EXISTS (SELECT 1 FROM store_availability WHERE id = 1)";
$conn->query($insert_sql);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $is_open = isset($_POST['is_open']) ? 1 : 0;
    $message = trim($_POST['message']);
    
    $stmt = $conn->prepare("UPDATE store_availability SET is_open = ?, message = ? WHERE id = 1");
    $stmt->bind_param("is", $is_open, $message);
    
    if ($stmt->execute()) {
        $success = "Store availability updated successfully!";
    } else {
        $error = "Error updating store availability: " . $conn->error;
    }
    $stmt->close();
}

// Get current availability status
try {
    $result = $conn->query("SELECT * FROM store_availability WHERE id = 1");
    if ($result) {
        $availability = $result->fetch_assoc();
    } else {
        // If query fails, set default values
        $availability = [
            'is_open' => true,
            'message' => 'Welcome to Noah Waters! We are open for business.',
            'last_updated' => date('Y-m-d H:i:s')
        ];
    }
} catch (Exception $e) {
    // If there's an error, set default values
    $availability = [
        'is_open' => true,
        'message' => 'Welcome to Noah Waters! We are open for business.',
        'last_updated' => date('Y-m-d H:i:s')
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Store Availability - Noah Waters</title>
    <link href="https://fonts.googleapis.com/css2?family=Boogaloo&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #79c7ff;
            margin: 0;
            padding: 0;
            font-family: "Boogaloo", sans-serif;
            background-image: url('back.webp');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 0 15px;
        }
        .container-box {
            background: rgba(3, 0, 0, 0.1);
            border-radius: 15px;
            padding: 25px;
            margin-top: 30px;
            box-shadow: 0 0 15px rgba(255, 255, 255, 0.52);
            color: white;
        }
        h2 {
            color: white;
            font-size: 2em;
            margin-bottom: 30px;
            text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.2);
        }
        .form-label {
            color: white;
            font-size: 1.2em;
        }
        .form-control, .form-select {
            background-color: rgba(255, 255, 255, 0.9);
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 1.1em;
        }
        .form-check-input {
            width: 3em;
            height: 1.5em;
            margin-top: 0.25em;
            vertical-align: top;
            background-color: #fff;
            background-repeat: no-repeat;
            background-position: center;
            background-size: contain;
            border: 1px solid rgba(0, 0, 0, 0.25);
            appearance: none;
            color-adjust: exact;
            print-color-adjust: exact;
        }
        .form-check-input:checked {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }
        .btn-primary {
            background-color: #0f65b4;
            border: none;
            padding: 10px 25px;
            border-radius: 8px;
            font-size: 1.2em;
        }
        .btn-primary:hover {
            background-color: #0d4d8c;
        }
        .alert {
            font-size: 1.1em;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .status-indicator {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 1.1em;
            margin-bottom: 20px;
        }
        .status-open {
            background-color: #28a745;
            color: white;
        }
        .status-closed {
            background-color: #dc3545;
            color: white;
        }
    </style>
</head>
<body>
<?php include 'navbar_admin.php'; ?>

<div class="container">
    <div class="container-box">
        <h2 class="text-center mb-4">Store Availability</h2>

        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>

        <div class="text-center mb-4">
            <span class="status-indicator <?= $availability['is_open'] ? 'status-open' : 'status-closed' ?>">
                <?= $availability['is_open'] ? 'Store is Open' : 'Store is Closed' ?>
            </span>
            <p class="text-white">Last updated: <?= date('M d, Y h:i A', strtotime($availability['last_updated'])) ?></p>
        </div>

        <form method="POST">
            <div class="mb-4">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="is_open" name="is_open" 
                           <?= $availability['is_open'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="is_open">Store is Open</label>
                </div>
            </div>

            <div class="mb-4">
                <label for="message" class="form-label">Status Message</label>
                <textarea class="form-control" id="message" name="message" rows="3" 
                          placeholder="Enter a message to display to customers"><?= htmlspecialchars($availability['message']) ?></textarea>
            </div>

            <div class="text-center">
                <button type="submit" class="btn btn-primary">Update Availability</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 