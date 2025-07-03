<?php
require 'config.php';

// Get current store availability
try {
    $result = $conn->query("SELECT * FROM store_availability WHERE id = 1");
    if ($result) {
        $availability = $result->fetch_assoc();
    } else {
        // If table doesn't exist or query fails, set default values
        $availability = [
            'is_open' => true,
            'message' => 'Welcome to Noah Waters! We are open for business.'
        ];
    }
} catch (Exception $e) {
    // If there's an error, set default values
    $availability = [
        'is_open' => true,
        'message' => 'Welcome to Noah Waters! We are open for business.'
    ];
}
?>

<div class="store-status-container mb-4">
    <div class="store-status <?= $availability['is_open'] ? 'status-open' : 'status-closed' ?>">
        <h3 class="mb-2"><?= $availability['is_open'] ? 'Store is Open' : 'Store is Closed' ?></h3>
        <?php if (!empty($availability['message'])): ?>
            <p class="mb-0"><?= htmlspecialchars($availability['message']) ?></p>
        <?php endif; ?>
    </div>
</div>

<style>
.store-status-container {
    text-align: center;
    padding: 15px;
    border-radius: 10px;
    margin: 20px auto;
    max-width: 600px;
}

.store-status {
    padding: 15px;
    border-radius: 8px;
    color: white;
}

.status-open {
    background-color: rgba(40, 167, 69, 0.9);
}

.status-closed {
    background-color: rgba(220, 53, 69, 0.9);
}

.store-status h3 {
    font-size: 1.5em;
    margin: 0;
    font-weight: bold;
}

.store-status p {
    font-size: 1.1em;
    margin-top: 5px;
}
</style> 