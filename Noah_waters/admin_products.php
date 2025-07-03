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

// chinecheck kung admin or user ba yung nagla login
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin'])) {
    header("Location: login.php");
    exit;
}

$errors = [];
$success = "";

// debug logging para saa POST data
error_log("POST data: " . print_r($_POST, true));

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add_product') {
            $name = $_POST['name'];
            $category = $_POST['category'];
            $price = $_POST['price'];
            $is_borrowable = isset($_POST['is_borrowable']) ? 1 : 0;
            
            // Handle image upload
            $image = '';
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = 'uploads/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                $fileExtension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                $newFileName = uniqid() . '.' . $fileExtension;
                $uploadFile = $uploadDir . $newFileName;
                
                // Check if image file is a actual image
                $check = getimagesize($_FILES['image']['tmp_name']);
                if ($check !== false) {
                    // Check file size (5MB max)
                    if ($_FILES['image']['size'] <= 5000000) {
                        // Allow certain file formats
                        if (in_array($fileExtension, ['jpg', 'jpeg', 'png', 'gif'])) {
                            if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadFile)) {
                                $image = $uploadFile;
                            }
                        }
                    }
                }
            }
            
            if ($image) {
                $stmt = $conn->prepare("INSERT INTO products (name, category, price, image, is_borrowable) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("ssdsi", $name, $category, $price, $image, $is_borrowable);
                $stmt->execute();
                $stmt->close();
            }
        } elseif ($_POST['action'] === 'edit') {
            $id = $_POST['id'];
            $name = $_POST['name'];
            $price = $_POST['price'];
            $category = $_POST['category'];
            $is_borrowable = isset($_POST['is_borrowable']) ? 1 : 0;
            $is_out_of_stock = isset($_POST['is_out_of_stock']) ? 1 : 0;
            
            // Handle image upload if a new image is provided
            if (!empty($_FILES['image']['name'])) {
                $target_dir = "uploads/";
                $target_file = $target_dir . basename($_FILES["image"]["name"]);
                $imageFileType = strtolower(pathinfo($target_file,PATHINFO_EXTENSION));
                
                // Check if image file is a actual image or fake image
                $check = getimagesize($_FILES["image"]["tmp_name"]);
                if($check !== false) {
                    if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                        $sql = "UPDATE products SET name=?, price=?, category=?, image=?, is_borrowable=?, is_out_of_stock=? WHERE id=?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("sdssiii", $name, $price, $category, $target_file, $is_borrowable, $is_out_of_stock, $id);
                    }
                }
            } else {
                $sql = "UPDATE products SET name=?, price=?, category=?, is_borrowable=?, is_out_of_stock=? WHERE id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sdssii", $name, $price, $category, $is_borrowable, $is_out_of_stock, $id);
            }
            
            if ($stmt->execute()) {
                echo "<script>alert('Product updated successfully!');</script>";
            } else {
                echo "<script>alert('Error updating product: " . $stmt->error . "');</script>";
            }
        }
        // ... rest of the existing code ...
    }
}

// code sa pag edit ng product
if (isset($_POST['edit_product'])) {
    $id = intval($_POST['id']);
    $name = trim($_POST['name']);
    $category = $_POST['category'];
    $price = floatval($_POST['price']);
    $image = trim($_POST['image']);
    // borrowable button ulit
    $isBorrowable = isset($_POST['is_borrowable']) && $_POST['is_borrowable'] == '1' ? 1 : 0;
    
    error_log("Edit Product - isBorrowable value: " . $isBorrowable);

    // validate ulit ng pag input ng product kung tama ba yung nilagay
    if (empty($name)) $errors[] = "Product name is required.";
    if (!in_array($category, ['container', 'bottle'])) $errors[] = "Invalid category selected.";
    if (!is_numeric($price) || $price < 0) $errors[] = "Price must be a non-negative number.";
    if (!preg_match('/\.(jpg|jpeg|png)$/i', $image)) $errors[] = "Only JPG, JPEG, and PNG images are allowed.";

    if (empty($errors)) {
        $stmt = $conn->prepare("UPDATE products SET name = ?, category = ?, price = ?, image = ?, is_borrowable = ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("ssdsii", $name, $category, $price, $image, $isBorrowable, $id);
            if ($stmt->execute()) {
                $success = "Product updated successfully!";
                error_log("Product updated with isBorrowable: " . $isBorrowable);
                header("Location: admin_products.php");
                exit;
            } else {
                $errors[] = "Error updating product: " . $stmt->error;
                error_log("Error updating product: " . $stmt->error);
            }
            $stmt->close();
        } else {
            $errors[] = "Database error: " . $conn->error;
            error_log("Database error: " . $conn->error);
        }
    }
}

// code sa pagdelete ng product
if (isset($_POST['delete'])) {
    $id = intval($_POST['id']);
    if ($id) {
        $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $success = "Product deleted successfully!";
            } else {
                $errors[] = "Error deleting product: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $errors[] = "Database error: " . $conn->error;
        }
        header("Location: admin_products.php");
        exit;
    }
}

// eto yung pagdisplay ng mga products
// Check if is_out_of_stock column exists
$checkColumn = $conn->query("SHOW COLUMNS FROM products LIKE 'is_out_of_stock'");
$hasOutOfStockColumn = $checkColumn->num_rows > 0;

if ($hasOutOfStockColumn) {
    $result = $conn->query("SELECT id, name, category, price, image, is_borrowable, is_out_of_stock FROM products ORDER BY id DESC");
} else {
    $result = $conn->query("SELECT id, name, category, price, image, is_borrowable FROM products ORDER BY id DESC");
}

if (!$result) {
    $errors[] = "Error fetching products: " . $conn->error;
}
$products = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// Add is_out_of_stock to products that don't have it
if (!$hasOutOfStockColumn) {
    foreach ($products as &$product) {
        $product['is_out_of_stock'] = 0;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Admin Products</title>
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
        .form-label {
            color: white;
            font-size: 1.1em;
            margin-bottom: 8px;
            font-family: "Boogaloo", sans-serif;
        }
        .row {
            max-width: 900px;
            margin-left: auto;
            margin-right: auto;
        }
        .form-select, .form-control {
            background-color: rgba(255, 255, 255, 0.9);
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 1.1em;
            font-family: "Boogaloo", sans-serif;
            width: 100%;
            min-width: 200px;
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
            height: 150px;
            vertical-align: middle;
        }
        .table tbody tr:hover {
            background-color: rgba(255, 255, 255, 0.15);
        }
        .product-img {
            width: 120px;
            height: 120px;
            object-fit: contain;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }
        .toggle-lg {
            width: 3em;
            height: 1.5em;
        }
        .toggle-lg:checked {
            background-color: #0f65b4;
            border-color: #0f65b4;
        }
        .form-check-input.toggle-lg:focus {
            border-color: #0f65b4;
            box-shadow: 0 0 0 0.25rem rgba(15, 101, 180, 0.25);
        }
        .alert {
            font-family: "Boogaloo", sans-serif;
            font-size: 1.1em;
            border-radius: 10px;
            margin-bottom: 18px;
            padding: 12px 18px;
        }
    </style>
</head>
<body>
    <!--navigation bar sa taas para sa admin-->
<?php include 'navbar_admin.php'; ?>

<div class="container container-box">
    <h2 class="mb-4 text-center">Manage Products</h2>

    <!-- product form -->
    <form method="POST" class="mb-5" enctype="multipart/form-data">
        <input type="hidden" name="action" value="add_product">
        <div class="row g-3 align-items-end">
            <div class="col-md-3">
                <label for="name" class="form-label">Name</label>
                <input required type="text" name="name" id="name" class="form-control" placeholder="Product name" />
            </div>
            <div class="col-md-3">
                <label for="category" class="form-label">Category</label>
                <select required name="category" id="category" class="form-select">
                    <option value="">Select category</option>
                    <option value="container">Container</option>
                    <option value="bottle">Bottle</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="price" class="form-label">Price (₱)</label>
                <input required type="number" step="0.01" min="0" name="price" id="price" class="form-control" placeholder="0.00" />
            </div>
            <div class="col-md-3">
                <label for="image" class="form-label">Image</label>
                <input required type="file" name="image" id="image" class="form-control" accept="image/*" />
            </div>
        </div>
        <div class="row mt-4 justify-content-center align-items-center">
            <div class="col-auto">
                <div class="form-check form-switch">
                    <input class="form-check-input toggle-lg" type="checkbox" name="is_borrowable" id="is_borrowable" value="1">
                    <label class="form-check-label fs-5 ms-2 mb-0" for="is_borrowable">Borrowable</label>
                </div>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary btn-lg px-4">Add Product</button>
            </div>
        </div>
    </form>

    <!--display ng products -->
    <table class="table table-hover text-white align-middle">
        <thead>
            <tr>
                <th>ID</th>
                <th>Image</th>
                <th>Name</th>
                <th>Price</th>
                <th>Category</th>
                <th>Borrowable</th>
                <th>Stock Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($products)): ?>
                <tr><td colspan="6" class="text-center">No products found.</td></tr>
            <?php else: ?>
                <?php foreach ($products as $product): ?>
                <tr>
                    <td><?= $product['id'] ?></td>
                    <td>
                        <img src="<?= htmlspecialchars($product['image']) ?>" alt="<?= htmlspecialchars($product['name']) ?>" class="product-img">
                    </td>
                    <td><?= htmlspecialchars($product['name']) ?></td>
                    <td>₱<?= number_format($product['price'], 2) ?></td>
                    <td><?= ucfirst(htmlspecialchars($product['category'])) ?></td>
                    <td>
                        <?= $product['is_borrowable'] ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>' ?>
                    </td>
                    <td>
                        <?= $product['is_out_of_stock'] ? '<span class="badge bg-danger">Out of Stock</span>' : '<span class="badge bg-success">In Stock</span>' ?>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-primary edit-btn" 
                                data-bs-toggle="modal" 
                                data-bs-target="#editModal"
                                data-id="<?= $product['id'] ?>"
                                data-name="<?= htmlspecialchars($product['name']) ?>"
                                data-price="<?= $product['price'] ?>"
                                data-category="<?= htmlspecialchars($product['category']) ?>"
                                data-borrowable="<?= $product['is_borrowable'] ?>"
                                data-out-of-stock="<?= $product['is_out_of_stock'] ?>">
                            Edit
                        </button>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this product?');">
                            <input type="hidden" name="id" value="<?= $product['id'] ?>">
                            <input type="hidden" name="action" value="delete">
                            <button type="submit" name="delete" class="btn btn-sm btn-danger delete-btn">Delete</button>
                        </form>
                    </td>
                </tr>

                <!-- Edit Product Modal -->
                <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header bg-primary text-white">
                                <h5 class="modal-title" id="editModalLabel">Edit Product</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <form id="editForm" method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="action" value="edit">
                                    <input type="hidden" name="id" id="edit_id">
                                    <input type="hidden" name="current_image" id="edit_current_image">
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="edit_name" class="form-label">Name</label>
                                                <input type="text" class="form-control" id="edit_name" name="name" required>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="edit_category" class="form-label">Category</label>
                                                <select class="form-select" id="edit_category" name="category" required>
                                                    <option value="container">Container</option>
                                                    <option value="bottle">Bottle</option>
                                                </select>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="edit_price" class="form-label">Price (₱)</label>
                                                <input type="number" step="0.01" min="0" class="form-control" id="edit_price" name="price" required>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input toggle-lg" type="checkbox" id="edit_borrowable" name="is_borrowable" value="1">
                                                    <label class="form-check-label fs-5 ms-2 mb-0" for="edit_borrowable" style="color:#0f65b4">Borrowable</label>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input toggle-lg" type="checkbox" id="edit_out_of_stock" name="is_out_of_stock" value="1">
                                                    <label class="form-check-label fs-5 ms-2 mb-0" for="edit_out_of_stock" style="color:#0f65b4">Out of Stock</label>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="edit_image" class="form-label">Image</label>
                                                <input type="file" class="form-control" id="edit_image" name="image" accept="image/*">
                                                <small class="text-muted">Leave empty to keep current image</small>
                                                <div class="mt-3 text-center">
                                                    <img id="edit_current_image_preview" src="" alt="Current image" class="img-thumbnail" style="max-width: 100%; height: auto;">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" form="editForm" class="btn btn-primary">Save Changes</button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelector("form").addEventListener("submit", function (e) {
    const imageInput = document.querySelector("#image");
    const url = imageInput.value.toLowerCase();
    if (!url.endsWith(".jpg") && !url.endsWith(".jpeg") && !url.endsWith(".png")) {
        alert("Only .jpg, .jpeg, or .png image URLs are allowed.");
        e.preventDefault();
    }
});

document.addEventListener('DOMContentLoaded', function() {
    // Edit button click handler
    document.querySelectorAll('.edit-btn').forEach(button => {
        button.addEventListener('click', function() {
            const modal = new bootstrap.Modal(document.getElementById('editModal'));
            
            // Set form values
            document.getElementById('edit_id').value = this.dataset.id;
            document.getElementById('edit_name').value = this.dataset.name;
            document.getElementById('edit_price').value = this.dataset.price;
            document.getElementById('edit_category').value = this.dataset.category;
            document.getElementById('edit_borrowable').checked = this.dataset.borrowable === '1';
            document.getElementById('edit_out_of_stock').checked = this.dataset.outOfStock === '1';
            
            // Set current image
            const currentImage = this.closest('tr').querySelector('img').src;
            document.getElementById('edit_current_image').value = currentImage;
            document.getElementById('edit_current_image_preview').src = currentImage;
            
            modal.show();
        });
    });

    // Form submit handler
    document.getElementById('editForm').addEventListener('submit', function(e) {
        e.preventDefault();
        this.submit();
    });
});
</script>

</body>
</html>