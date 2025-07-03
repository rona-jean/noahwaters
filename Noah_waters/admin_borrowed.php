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
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin'])) {
    header("Location: login.php");
    exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['borrow_id'], $_POST['returned_status'])) {
    $borrowId = intval($_POST['borrow_id']);
    $status = $_POST['returned_status'];

    // Debug: Log the incoming data
    error_log("POST Data - borrow_id: $borrowId, status: $status");
    error_log("Raw POST data: " . print_r($_POST, true));

    // Handle different statuses appropriately
    if ($status === 'Borrowed') {
        $returned = 0; // Mark as not returned
        $penalty = 0; // No penalty for borrowed items
        $penaltyPaid = 0; // No penalty to pay
    } else {
        // All other statuses (Returned, Lost, Damaged) mark the item as returned
        $returned = 1; // All these statuses mean the item is no longer borrowed
        $penalty = in_array($status, ['Lost', 'Damaged']) ? 100 : 0;
        
        // Set penalty_paid status based on the new status
        if (in_array($status, ['Lost', 'Damaged'])) {
            $penaltyPaid = 1; // Set to paid when penalty is applied
        } else {
            $penaltyPaid = 0; // No penalty for returned items
        }
    }

    // Debug: Log the calculated values
    error_log("Calculated values - returned: $returned, penalty: $penalty, penaltyPaid: $penaltyPaid, status: $status");

    // Update only the fields that exist in the database
    $stmt = $conn->prepare("UPDATE borrowed_containers SET returned = ?, penalty = ?, penalty_paid = ?, status = ? WHERE id = ?");
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        $_SESSION['update_error'] = "Database prepare failed: " . $conn->error;
        header("Location: admin_borrowed.php");
        exit;
    }
    
    // Fix parameter binding - correct types: i (returned), i (penalty), i (penalty_paid), s (status), i (borrowId)
    $stmt->bind_param("iiisi", $returned, $penalty, $penaltyPaid, $status, $borrowId);
    $result = $stmt->execute();
    
    if (!$result) {
        error_log("Execute failed: " . $stmt->error);
        $_SESSION['update_error'] = "Database update failed: " . $stmt->error;
    } else {
        error_log("Update successful - affected rows: " . $stmt->affected_rows);
        if ($stmt->affected_rows == 0) {
            error_log("No rows were affected - check if borrow_id exists: $borrowId");
            $_SESSION['update_error'] = "No changes made. Container ID $borrowId may not exist.";
        } else {
            $_SESSION['update_message'] = "Container status updated successfully to: " . $status;
            
            // Verify the update by checking the database
            $verify_sql = "SELECT status, penalty, penalty_paid FROM borrowed_containers WHERE id = ?";
            $verify_stmt = $conn->prepare($verify_sql);
            $verify_stmt->bind_param("i", $borrowId);
            $verify_stmt->execute();
            $verify_result = $verify_stmt->get_result();
            $verify_data = $verify_result->fetch_assoc();
            $verify_stmt->close();
            
            error_log("Verification - Status: " . $verify_data['status'] . ", Penalty: " . $verify_data['penalty'] . ", PenaltyPaid: " . $verify_data['penalty_paid']);
        }
    }
    
    $stmt->close();

    header("Location: admin_borrowed.php");
    exit;
}

$sql = "SELECT
    bc.id,
    bc.order_id,
    bc.container_id,
    bc.borrowed_at,
    bc.returned,
    bc.penalty,
    bc.penalty_paid,
    bc.status,
    p.name AS container_name,
    COALESCE(u.fullname, o.fullname) AS borrower_name,
    o.usertype
FROM borrowed_containers bc
LEFT JOIN products p ON bc.container_id = p.id
LEFT JOIN orders o ON bc.order_id = o.id
LEFT JOIN users u ON o.user_id = u.id
ORDER BY bc.borrowed_at DESC";

// Add status filter
$statusFilter = isset($_GET['status_filter']) && $_GET['status_filter'] !== '' ? $_GET['status_filter'] : null;

if ($statusFilter) {
    $sql = "SELECT
        bc.id,
        bc.order_id,
        bc.container_id,
        bc.borrowed_at,
        bc.returned,
        bc.penalty,
        bc.penalty_paid,
        bc.status,
        p.name AS container_name,
        COALESCE(u.fullname, o.fullname) AS borrower_name,
        o.usertype
    FROM borrowed_containers bc
    LEFT JOIN products p ON bc.container_id = p.id
    LEFT JOIN orders o ON bc.order_id = o.id
    LEFT JOIN users u ON o.user_id = u.id
    WHERE bc.status = '$statusFilter'
    ORDER BY bc.borrowed_at DESC";
}

$result = $conn->query($sql);

if (!$result) {
    die("SQL Error: " . $conn->error);
}

$borrowedContainers = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Borrowed Containers</title>
    <link href="https://fonts.googleapis.com/css2?family=Boogaloo&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
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
            max-width: 1000px;
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
            max-width: 900px;
            margin-left: auto;
            margin-right: auto;
        }
        h2 {
            color: white;
            font-size: 2em;
            margin-bottom: 30px;
            text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.2);
            font-family: "Boogaloo", sans-serif;
        }
        .table {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 25px;
        }
        .table th {
            background-color: #0f65b4;
            color: white;
            font-family: "Boogaloo", sans-serif;
            font-size: 1.1em;
        }
        .table td {
            font-family: "Boogaloo", sans-serif;
            font-size: 1.1em;
            color: #333;
        }
        .table tbody tr:hover {
            background-color: rgba(255, 255, 255, 0.15);
        }
        .btn-primary, .btn-success, .btn-secondary {
            font-family: "Boogaloo", sans-serif;
            font-size: 1.1em;
        }
        .btn-primary {
            background-color: #0f65b4;
            border: none;
            padding: 8px 20px;
            border-radius: 8px;
            transition: background-color 0.3s;
        }
        .btn-primary:hover {
            background-color: #0d4d8c;
        }
        .btn-success {
            background-color: #28a745;
            border: none;
            padding: 6px 15px;
            border-radius: 6px;
        }
        .btn-secondary {
            background-color: #6c757d;
            border: none;
            padding: 6px 15px;
            border-radius: 6px;
        }
        .status-badge {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.9em;
            font-weight: bold;
        }
        .status-returned {
            background-color: #28a745;
            color: white;
        }
        .status-borrowed {
            background-color: #dc3545;
            color: white;
        }
        .form-select {
            background-color: rgba(255, 255, 255, 0.9);
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 1.1em;
            font-family: "Boogaloo", sans-serif;
            width: auto;
            min-width: 150px;
        }
        @media (max-width: 768px) {
            .container-box {
                margin: 20px 15px;
                padding: 15px;
            }
            .table td, .table th {
                font-size: 1em;
            }
            .form-select, .btn {
                font-size: 1em;
            }
        }
    </style>
</head>
<body>
<?php include 'navbar_admin.php'; ?>

<div class="container container-box">
    <h2 class="text-center mb-4">Borrowed Containers</h2>

    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Container</th>
                    <th>Borrower</th>
                    <th>Order ID</th>
                    <th>Borrowed Date</th>
                    <th>Status Update</th>
                    <th>Penalty</th>
                    <th>Penalty Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($borrowedContainers as $container): ?>
                    <tr>
                        <td><?= htmlspecialchars($container['container_name']) ?></td>
                        <td>
                            <div>
                                <strong><?= htmlspecialchars($container['borrower_name']) ?></strong>
                                <br>
                                <small class="text-muted">
                                    <?= ucfirst($container['usertype']) ?> User
                                </small>
                            </div>
                        </td>
                        <td>#<?= $container['order_id'] ?></td>
                        <td><?= date('M d, Y h:i A', strtotime($container['borrowed_at'])) ?></td>
                        <td>
                            <?php 
                            $status = $container['status'] ?: 'Borrowed';
                            $statusClass = 'status-returned'; // Default green for borrowed
                            
                            if (in_array(strtolower($status), ['lost', 'damaged'])) {
                                $statusClass = 'status-borrowed'; // Red only for lost and damaged
                            }
                            // Borrowed and Returned will use green badge (status-returned)
                            ?>
                            <span class="status-badge <?= $statusClass ?>">
                                <?= ucfirst(htmlspecialchars($status)) ?>
                            </span>
                        </td>
                        <td>
                            <?= $container['penalty'] > 0 ? '₱' . $container['penalty'] : '-' ?>
                        </td>
                        <td>
                            <?php if ($container['penalty'] > 0): ?>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="borrow_id" value="<?= $container['id'] ?>">
                                    <input type="hidden" name="toggle_penalty_paid" value="1">
                                    <button type="submit" class="btn btn-<?= $container['penalty_paid'] ? 'success' : 'secondary' ?> btn-sm">
                                        <?= $container['penalty_paid'] ? 'Paid' : 'Unpaid' ?>
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="text-muted">No Penalty</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="borrow_id" value="<?= $container['id'] ?>">
                                <select name="returned_status" class="form-select d-inline w-auto">
                                    <option value="Borrowed" <?= $container['status'] === 'Borrowed' ? 'selected' : '' ?>>Borrowed</option>
                                    <option value="Returned" <?= $container['status'] === 'Returned' ? 'selected' : '' ?>>Returned</option>
                                    <option value="Lost" <?= $container['status'] === 'Lost' ? 'selected' : '' ?>>Lost</option>
                                    <option value="Damaged" <?= $container['status'] === 'Damaged' ? 'selected' : '' ?>>Damaged</option>
                                </select>
                                <button type="submit" class="btn btn-success btn-sm">Update</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>