<?php
session_start();
$userLoggedIn = isset($_SESSION['user_id']);

require 'config.php';
require 'check_store_status.php';

// Get store status
$storeOpen = isStoreOpen();
$storeMessage = getStoreMessage();

//check kung is_new_user yung naglogged in para sa borrow container
$isNewUser = !$userLoggedIn; 
$hasBorrowed = false;
if ($userLoggedIn) {
    $stmt = $conn->prepare("SELECT is_new_user FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $stmt->bind_result($isNewUserFlag);
    $stmt->fetch();
    $stmt->close();
    $isNewUser = ($isNewUserFlag == 1);

    //ito nagchecheck if nakapagborrow n ba yung new user
    $sql = "SELECT COUNT(*) FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            JOIN orders o ON oi.order_id = o.id
            WHERE o.user_id = ? AND p.category = 'container' AND p.is_borrowable = 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $stmt->bind_result($borrowedCount);
    $stmt->fetch();
    $stmt->close();
    $hasBorrowed = ($borrowedCount > 0);
}

//fetch/check products table sa database
// Check if is_out_of_stock column exists
$checkColumn = $conn->query("SHOW COLUMNS FROM products LIKE 'is_out_of_stock'");
$hasOutOfStockColumn = $checkColumn->num_rows > 0;

if ($hasOutOfStockColumn) {
    $sql = "SELECT id, name, price, image, category, is_borrowable, is_out_of_stock FROM products ORDER BY category, name";
} else {
    $sql = "SELECT id, name, price, image, category, is_borrowable FROM products ORDER BY category, name";
}

$result = $conn->query($sql);

if (!$result) {
  die("Database query failed: " . $conn->error);
}

$containers = [];
$bottles = [];

while ($row = $result->fetch_assoc()) {
    $cat = strtolower(trim($row['category']));
    // Add default is_out_of_stock value if column doesn't exist
    if (!$hasOutOfStockColumn) {
        $row['is_out_of_stock'] = 0;
    }
    // Debug information
    error_log("Product: " . $row['name'] . ", is_out_of_stock: " . $row['is_out_of_stock']);
    
    if ($cat === 'container') {
        $containers[] = $row;
    } elseif ($cat === 'bottle') {
        $bottles[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Menu - Noah Waters</title>
  <link rel="stylesheet" href="style.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Boogaloo&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
  <script src="cart.js"></script>
  <style>

    .notification {
        position: fixed;
        bottom: 20px;
        right: 20px;
        background-color: #112752;
        color: #79c7ff;
        padding: 15px 25px;
        border-radius: 25px;
        font-size: 1rem;
        animation: slideIn 0.3s ease-out;
        z-index: 1000;
    }

    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }

    .cart-count {
      background: red;
      border-radius: 50%;
      color: white;
      padding: 2px 7px;
      font-size: 0.9rem;
      position: relative;
      top: -10px;
      left: -10px;
      display: none;
    }

    .container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 15px;
    }

    .menu-title {
        color: #0f65b4;
        font-size: 2.2em;
        margin: 10px 0;
        text-align: center;
        font-weight: bold;
    }

    .menu-container {
        padding: 10px;
        width: 100%;
        margin: 0 auto;
    }

    .section-title {
        color: #0f65b4;
        font-size: 1.8em;
        margin: 30px 0 20px;
        text-align: center;
        font-weight: bold;
    }

    .products-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 70px;
        margin-bottom: 40px;
    }

    .product-card {
        background: white;
        border-radius: 10px;
        padding: 15px;
        text-align: center;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        transition: transform 0.3s ease;
        width: 100%;
        position: relative;
    }

    .product-card:hover {
        transform: translateY(-5px);
    }

    .product-image {
        width: 100%;
        height: 200px;
        object-fit: contain;
        border-radius: 8px;
        margin-bottom: 10px;
        position: relative;
    }

    .product-info h3 {
        margin: 10px 0;
        font-size: 1.2em;
        color: #333;
    }

    .price {
        color: #0f65b4;
        font-weight: bold;
        font-size: 1.1em;
        margin: 10px 0;
    }

    .order-now-btn {
        background-color: #0f65b4;
        color: white;
        border: none;
        padding: 15px 20px;
        border-radius: 5px;
        cursor: pointer;
        width: 100%;
        font-size: 1.2em;
        transition: background-color 0.3s ease;
    }

    .order-now-btn:hover:not(:disabled) {
        background-color: #0d4d8c;
    }

    .order-now-btn:disabled {
        background-color: #6c757d;
        cursor: not-allowed;
    }
    
    @media (max-width: 992px) {
        .products-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
    }
    
    @media (max-width: 576px) {
        .products-grid {
            grid-template-columns: 1fr;
            gap: 15px;
        }
    }

    .out-of-stock-overlay {
        position: absolute;
        top: 10px;
        left: 10px;
        right: 10px;
        z-index: 2;
        text-align: center;
        pointer-events: none;
    }

    .out-of-stock-badge {
        background: rgba(255,255,255,0.85);
        color: #dc3545;
        font-size: 1.1em;
        font-weight: bold;
        padding: 4px 10px;
        border-radius: 8px;
        display: inline-block;
    }

    .out-of-stock {
        color: #dc3545;
        font-weight: bold;
        margin: 5px 0;
    }

    .menu-title {
        color: #0f65b4;
        font-size: 2.5em;
        margin: 30px 0;
        text-align: center;
        font-weight: bold;
    }
  </style>
</head>
<body>
  <!--eto nagdedetermine anong navigation bar ididisplay kung user or guest-->
  <?php if ($userLoggedIn) {
      include 'navbar_loggedin_users.php';
    } else {
      include 'navbar_guest.php';
    }
  ?>

  <div class="container">
    <div class="container-box">
        <?php include 'store_status.php'; ?>
        
        <h2 class="menu-title">Our Menu</h2>

        <div class="menu-container">
            <h3 class="section-title">Water Containers</h3>
            <div class="products-grid">
                <?php foreach ($containers as $p): ?>
                    <div class="product-card">
                        <?php if ($p['is_out_of_stock']): ?>
                            <div class="out-of-stock-overlay">
                                <span class="out-of-stock-badge">
                                    Out of Stock <br><span style="font-size:1.3em;">Unavailable</span>
                                </span>
                            </div>
                        <?php endif; ?>
                        <img src="<?= htmlspecialchars($p['image']) ?>" alt="<?= htmlspecialchars($p['name']) ?>" class="product-image" />
                        <div class="product-info">
                            <h3><?= htmlspecialchars($p['name']) ?></h3>
                            <p class="price">₱<?= number_format($p['price'], 2) ?></p>
                            <?php if ($storeOpen): ?>
                                <?php if ($p['is_out_of_stock']): ?>
                                    <button class="order-now-btn" disabled style="background-color: #dc3545; cursor: not-allowed;">
                                        Out of Stock
                                    </button>
                                <?php else: ?>
                                    <button class="order-now-btn"
                                            data-product-id="<?= $p['id'] ?>"
                                            data-product-name="<?= htmlspecialchars($p['name']) ?>"
                                            data-product-price="<?= $p['price'] ?>"
                                            data-product-image="<?= htmlspecialchars($p['image']) ?>">
                                        Add to Cart
                                    </button>
                                <?php endif; ?>
                            <?php else: ?>
                                <button class="order-now-btn" disabled style="background-color: #6c757d; cursor: not-allowed;" 
                                        title="<?= htmlspecialchars($storeMessage) ?>">
                                    Store is Closed
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <h3 class="section-title">Water Bottles</h3>
            <div class="products-grid">
                <?php foreach ($bottles as $p): ?>
                    <div class="product-card">
                        <?php if ($p['is_out_of_stock']): ?>
                            <div class="out-of-stock-overlay">
                                <span class="out-of-stock-badge">
                                    Out of Stock <br><span style="font-size:1.3em;">Unavailable</span>
                                </span>
                            </div>
                        <?php endif; ?>
                        <img src="<?= htmlspecialchars($p['image']) ?>" alt="<?= htmlspecialchars($p['name']) ?>" class="product-image" />
                        <div class="product-info">
                            <h3><?= htmlspecialchars($p['name']) ?></h3>
                            <p class="price">₱<?= number_format($p['price'], 2) ?></p>
                            <?php if ($storeOpen): ?>
                                <?php if ($p['is_out_of_stock']): ?>
                                    <button class="order-now-btn" disabled style="background-color: #dc3545; cursor: not-allowed;">
                                        Out of Stock
                                    </button>
                                <?php else: ?>
                                    <button class="order-now-btn"
                                            data-product-id="<?= $p['id'] ?>"
                                            data-product-name="<?= htmlspecialchars($p['name']) ?>"
                                            data-product-price="<?= $p['price'] ?>"
                                            data-product-image="<?= htmlspecialchars($p['image']) ?>">
                                        Add to Cart
                                    </button>
                                <?php endif; ?>
                            <?php else: ?>
                                <button class="order-now-btn" disabled style="background-color: #6c757d; cursor: not-allowed;" 
                                        title="<?= htmlspecialchars($storeMessage) ?>">
                                    Store is Closed
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
  </div>

  <script>
    const isLoggedIn = <?= json_encode($userLoggedIn) ?>;

    document.addEventListener('DOMContentLoaded', () => {
        // Load cart count on page load
        loadCart();
        
        document.querySelectorAll('.order-now-btn').forEach(button => {
            button.addEventListener('click', () => {
                const pid = button.getAttribute('data-product-id');
                const pname = button.getAttribute('data-product-name');
                const price = button.getAttribute('data-product-price');
                const image = button.getAttribute('data-product-image');
                addToCart(pid, pname, price, image);
            });
        });
    });
  </script>
</body>
</html>
