<?php
require_once 'config/database.php';
checkAdminLogin();

$message = '';
$error = '';

// Create uploads directory if it doesn't exist
$uploadDir = 'uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Function to handle image upload
function handleImageUpload($file) {
    global $uploadDir;
    
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($file['type'], $allowedTypes)) {
        throw new Exception('Invalid file type. Only JPEG, PNG, GIF, and WebP are allowed.');
    }
    
    if ($file['size'] > $maxSize) {
        throw new Exception('File size too large. Maximum 5MB allowed.');
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'item_' . time() . '_' . rand(1000, 9999) . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return $filepath;
    } else {
        throw new Exception('Failed to upload image.');
    }
}

// Handle edit book
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'edit_book') {
    $book_id = $_POST['book_id'] ?? '';
    $title = $_POST['title'] ?? '';
    $author = $_POST['author'] ?? '';
    $price = $_POST['price'] ?? '';
    $quantity = $_POST['quantity'] ?? '';
    $reorder_level = $_POST['reorder_level'] ?? '';
    $category = $_POST['category'] ?? '';
    $description = $_POST['description'] ?? '';
    $rating = $_POST['rating'] ?? 0;
    
    if (!empty($book_id) && !empty($title) && !empty($author) && !empty($price)) {
        try {
            $conn = getConnection();
            
            // Handle image upload if new image is provided
            $imagePath = null;
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $imagePath = handleImageUpload($_FILES['image']);
                
                // Delete old image if exists
                $oldImageQuery = $conn->prepare("SELECT image FROM books WHERE id = ?");
                $oldImageQuery->bind_param("i", $book_id);
                $oldImageQuery->execute();
                $oldImageResult = $oldImageQuery->get_result();
                if ($oldImageRow = $oldImageResult->fetch_assoc()) {
                    $oldImage = $oldImageRow['image'];
                    if ($oldImage && file_exists($oldImage) && strpos($oldImage, 'uploads/') === 0) {
                        unlink($oldImage);
                    }
                }
                $oldImageQuery->close();
                
                $stmt = $conn->prepare("UPDATE books SET title = ?, author = ?, price = ?, quantity = ?, reorder_level = ?, category = ?, description = ?, rating = ?, image = ? WHERE id = ?");
                $stmt->bind_param("ssdiissdi", $title, $author, $price, $quantity, $reorder_level, $category, $description, $rating, $imagePath, $book_id);
            } else {
                $stmt = $conn->prepare("UPDATE books SET title = ?, author = ?, price = ?, quantity = ?, reorder_level = ?, category = ?, description = ?, rating = ? WHERE id = ?");
                $stmt->bind_param("ssdiissi", $title, $author, $price, $quantity, $reorder_level, $category, $description, $rating, $book_id);
            }
            
            $stmt->execute();
            $message = 'Book updated successfully!';
            $stmt->close();
            $conn->close();
        } catch (Exception $e) {
            $error = 'Error updating book: ' . $e->getMessage();
        }
    } else {
        $error = 'Please fill in all required fields';
    }
}

// Handle add book
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'add_book') {
    $title = $_POST['title'] ?? '';
    $author = $_POST['author'] ?? '';
    $price = $_POST['price'] ?? '';
    $quantity = $_POST['quantity'] ?? '';
    $description = $_POST['description'] ?? '';
    $rating = $_POST['rating'] ?? 0;
    
    if (!empty($title) && !empty($author) && !empty($price)) {
        try {
            $imagePath = null;
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $imagePath = handleImageUpload($_FILES['image']);
            }
            
            $conn = getConnection();
            $stmt = $conn->prepare("INSERT INTO books (title, author, price, quantity, description, category, image, rating) VALUES (?, ?, ?, ?, ?, 'Books', ?, ?)");
            $stmt->bind_param("ssdissd", $title, $author, $price, $quantity, $description, $imagePath, $rating);
            $stmt->execute();
            $message = 'Book added successfully!';
            $stmt->close();
            $conn->close();
        } catch (Exception $e) {
            $error = 'Error adding book: ' . $e->getMessage();
        }
    } else {
        $error = 'Please fill in all required fields';
    }
}

// Handle add stationery
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'add_stationery') {
    $item_name = $_POST['item_name'] ?? '';
    $price = $_POST['price'] ?? '';
    $quantity = $_POST['quantity'] ?? '';
    $description = $_POST['description'] ?? '';
    
    if (!empty($item_name) && !empty($price)) {
        try {
            $imagePath = null;
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $imagePath = handleImageUpload($_FILES['image']);
            }
            
            $conn = getConnection();
            $stmt = $conn->prepare("INSERT INTO books (title, author, price, quantity, description, category, image, rating) VALUES (?, 'Stationery Item', ?, ?, ?, 'Stationery', ?, 0)");
            $stmt->bind_param("sdiss", $item_name, $price, $quantity, $description, $imagePath);
            $stmt->execute();
            $message = 'Stationery item added successfully!';
            $stmt->close();
            $conn->close();
        } catch (Exception $e) {
            $error = 'Error adding stationery: ' . $e->getMessage();
        }
    } else {
        $error = 'Please fill in all required fields';
    }
}

// Handle delete book
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'delete_book') {
    $book_id = $_POST['book_id'] ?? '';
    
    if (!empty($book_id)) {
        try {
            $conn = getConnection();
            
            // Get image path before deleting
            $imageQuery = $conn->prepare("SELECT image FROM books WHERE id = ?");
            $imageQuery->bind_param("i", $book_id);
            $imageQuery->execute();
            $imageResult = $imageQuery->get_result();
            if ($imageRow = $imageResult->fetch_assoc()) {
                $imagePath = $imageRow['image'];
                if ($imagePath && file_exists($imagePath) && strpos($imagePath, 'uploads/') === 0) {
                    unlink($imagePath);
                }
            }
            $imageQuery->close();
            
            $stmt = $conn->prepare("DELETE FROM books WHERE id = ?");
            $stmt->bind_param("i", $book_id);
            $stmt->execute();
            $message = 'Item deleted successfully!';
            $stmt->close();
            $conn->close();
        } catch (mysqli_sql_exception $e) {
            $error = 'Error deleting item: ' . $e->getMessage();
        }
    }
}

// Get all books
try {
    $conn = getConnection();
    $result = $conn->query("SELECT * FROM books ORDER BY created_at DESC");
    $books = [];
    while ($row = $result->fetch_assoc()) {
        $books[] = $row;
    }
    $conn->close();
} catch (mysqli_sql_exception $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management - KiddleBookshop Admin</title>
    <link rel="stylesheet" href="assets/css/admin-style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 2000;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .modal-overlay.show {
            display: flex;
            opacity: 1;
        }

        .modal-content {
            background: var(--bg-glass);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--border-glass);
            border-radius: 20px;
            padding: 2.5rem;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: var(--shadow-deep);
            position: relative;
            transform: scale(0.9) translateY(30px);
            transition: transform 0.3s ease;
        }

        .modal-overlay.show .modal-content {
            transform: scale(1) translateY(0);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-glass);
        }

        .modal-title {
            font-family: 'Orbitron', monospace;
            font-size: 1.5rem;
            font-weight: 700;
            background: linear-gradient(45deg, var(--accent-primary), var(--accent-secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin: 0;
        }

        .close-btn {
            background: none;
            border: none;
            color: var(--accent-primary);
            font-size: 1.8rem;
            cursor: pointer;
            transition: all 0.3s ease;
            padding: 0.5rem;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .close-btn:hover {
            transform: rotate(90deg) scale(1.2);
            color: var(--danger);
            background: rgba(255, 107, 107, 0.1);
        }

        .form-tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            border-bottom: 1px solid var(--border-glass);
        }

        .tab-btn {
            background: none;
            border: none;
            color: var(--text-secondary);
            padding: 1rem 1.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            border-bottom: 2px solid transparent;
            position: relative;
        }

        .tab-btn.active {
            color: var(--accent-primary);
            border-bottom-color: var(--accent-primary);
        }

        .tab-btn:hover {
            color: var(--accent-primary);
            background: rgba(0, 255, 231, 0.1);
        }

        .form-content {
            display: none;
        }

        .form-content.active {
            display: block;
        }

        .button-group {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            justify-content: flex-end;
        }

        .inventory-actions {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }

        /* Image Upload Styles */
        .image-upload-container {
            position: relative;
            border: 2px dashed var(--border-glass);
            border-radius: 10px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            margin-bottom: 1rem;
        }

        .image-upload-container:hover {
            border-color: var(--accent-primary);
            background: rgba(0, 255, 231, 0.05);
        }

        .image-upload-container.has-image {
            border-style: solid;
            border-color: var(--accent-primary);
            padding: 1rem;
        }

        .upload-icon {
            font-size: 3rem;
            color: var(--accent-primary);
            margin-bottom: 1rem;
        }

        .upload-text {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .image-preview {
            max-width: 200px;
            max-height: 200px;
            border-radius: 10px;
            object-fit: cover;
            margin-bottom: 1rem;
        }

        .remove-image {
            position: absolute;
            top: 10px;
            right: 10px;
            background: var(--danger);
            color: white;
            border: none;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
        }

        .item-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid var(--border-glass);
        }

        .no-image {
            width: 60px;
            height: 60px;
            background: var(--bg-glass);
            border: 2px solid var(--border-glass);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-secondary);
            font-size: 0.8rem;
        }

        .rating-input {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .star-rating {
            display: flex;
            gap: 2px;
        }

        .star {
            color: #ffd700;
            font-size: 1rem;
        }

        @media (max-width: 768px) {
            .modal-content {
                width: 95%;
                padding: 1.5rem;
                max-height: 90vh;
            }

            .inventory-actions {
                justify-content: center;
            }

            .button-group {
                justify-content: center;
            }

            .image-upload-container {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
<?php if (isset($_GET['debug'])): ?>
    <div class="debug-info">
        <h4>Debug Information:</h4>
        <p><strong>Upload Directory:</strong> <?= $uploadDir ?></p>
        <p><strong>Directory Exists:</strong> <?= is_dir($uploadDir) ? 'Yes' : 'No' ?></p>
        <p><strong>Directory Writable:</strong> <?= is_writable($uploadDir) ? 'Yes' : 'No' ?></p>
        <p><strong>Upload Max Filesize:</strong> <?= ini_get('upload_max_filesize') ?></p>
        <p><strong>Post Max Size:</strong> <?= ini_get('post_max_size') ?></p>
        <p><strong>File Uploads:</strong> <?= ini_get('file_uploads') ? 'Enabled' : 'Disabled' ?></p>
    </div>
    <?php endif; ?>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <nav class="sidebar">
            <div class="sidebar-header">
                <h3>KiddleBookshop Admin</h3>
                <p>Welcome, <?= htmlspecialchars($_SESSION['admin_username']) ?></p>
            </div>
            <ul class="sidebar-nav">
                <li class="nav-item">
                    <a href="dashboard.php" class="nav-link">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a href="users.php" class="nav-link">
                        <i class="fas fa-users-cog"></i> Admin Management
                    </a>
                </li>
                <li class="nav-item">
                    <a href="inventory.php" class="nav-link active">
                        <i class="fas fa-boxes"></i> Inventory
                    </a>
                </li>
                <li class="nav-item">
                    <a href="transactions.php" class="nav-link">
                        <i class="fas fa-credit-card"></i> Transactions
                    </a>
                </li>
                <li class="nav-item">
                    <a href="logout.php" class="nav-link">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </li>
            </ul>
        </nav>

        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <h1 class="page-title">Inventory Management</h1>
                <div class="inventory-actions">
                    <button onclick="openAddModal('book')" class="btn btn-success">
                        <i class="fas fa-book"></i> Add Book
                    </button>
                    <button onclick="openAddModal('stationery')" class="btn" style="background: linear-gradient(45deg, #9b5de5, #7c3aed); color: white;">
                        <i class="fas fa-pen"></i> Add Stationery
                    </button>
                    <a href="inventory.php" class="btn" style="background: linear-gradient(45deg, #00bcd4, #0097a7); color: white;">
                        <i class="fas fa-eye"></i> View Inventory
                    </a>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <!-- Edit Book Form (hidden by default) -->
            <div id="editBookForm" style="display: none;">
                <div class="form-container">
                    <h3>Edit Item</h3>
                    <form method="POST" id="editForm" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="edit_book">
                        <input type="hidden" name="book_id" id="edit_book_id">
                        
                        <!-- Image Upload Section -->
                        <div class="form-group">
                            <label>Current Image</label>
                            <div id="editImagePreview" class="image-upload-container">
                                <div id="editCurrentImage"></div>
                            </div>
                            <label for="edit_image">Upload New Image (Optional)</label>
                            <input type="file" id="edit_image" name="image" class="form-control" accept="image/*" onchange="previewEditImage(this)">
                            <small class="form-text">Max file size: 5MB. Supported formats: JPEG, PNG, GIF, WebP</small>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="edit_title">Title *</label>
                                <input type="text" id="edit_title" name="title" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label for="edit_author">Author *</label>
                                <input type="text" id="edit_author" name="author" class="form-control" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="edit_price">Price *</label>
                                <input type="number" step="0.01" id="edit_price" name="price" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label for="edit_category">Category</label>
                                <input type="text" id="edit_category" name="category" class="form-control">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="edit_quantity">Quantity *</label>
                                <input type="number" id="edit_quantity" name="quantity" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label for="edit_reorder_level">Reorder Level</label>
                                <input type="number" id="edit_reorder_level" name="reorder_level" class="form-control" value="10">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="edit_rating">Rating (0-5)</label>
                                <input type="number" step="0.1" min="0" max="5" id="edit_rating" name="rating" class="form-control">
                            </div>
                        </div>
                        <div class="form-row single">
                            <div class="form-group">
                                <label for="edit_description">Description</label>
                                <textarea id="edit_description" name="description" class="form-control" rows="3"></textarea>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Update Item
                        </button>
                        <button type="button" onclick="toggleEditForm()" class="btn btn-danger">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </form>
                </div>
            </div>

            <!-- Inventory List -->
            <div class="table-container">
                <div style="padding: 1.5rem; border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
                    <h3>All Items (<?= count($books ?? []) ?>)</h3>
                </div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Author/Type</th>
                            <th>Price</th>
                            <th>Category</th>
                            <th>Rating</th>
                            <th>Quantity</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($books)): ?>
                            <?php foreach ($books as $book): ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($book['image']) && file_exists($book['image'])): ?>
                                            <img src="<?= htmlspecialchars($book['image']) ?>" alt="Item Image" class="item-image">
                                        <?php else: ?>
                                            <div class="no-image">No Image</div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $book['id'] ?></td>
                                    <td><?= htmlspecialchars($book['title']) ?></td>
                                    <td><?= htmlspecialchars($book['author']) ?></td>
                                    <td>$<?= number_format($book['price'], 2) ?></td>
                                    <td><?= htmlspecialchars($book['category'] ?? 'Books') ?></td>
                                    <td>
                                        <div class="star-rating">
                                            <?php 
                                            $rating = $book['rating'] ?? 0;
                                            for ($i = 1; $i <= 5; $i++): 
                                            ?>
                                                <span class="star"><?= $i <= $rating ? '★' : '☆' ?></span>
                                            <?php endfor; ?>
                                            <small>(<?= number_format($rating, 1) ?>)</small>
                                        </div>
                                    </td>
                                    <td><?= $book['quantity'] ?></td>
                                    <td>
                                        <?php if ($book['quantity'] <= ($book['reorder_level'] ?? 10)): ?>
                                            <span class="badge badge-warning">Low Stock</span>
                                        <?php elseif ($book['quantity'] == 0): ?>
                                            <span class="badge badge-danger">Out of Stock</span>
                                        <?php else: ?>
                                            <span class="badge badge-success">In Stock</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button onclick="editBook(<?= $book['id'] ?>, '<?= htmlspecialchars($book['title']) ?>', '<?= htmlspecialchars($book['author']) ?>', <?= $book['price'] ?>, '<?= htmlspecialchars($book['category']) ?>', <?= $book['quantity'] ?>, <?= $book['reorder_level'] ?? 10 ?>, '<?= htmlspecialchars($book['description']) ?>', '<?= htmlspecialchars($book['image'] ?? '') ?>', <?= $book['rating'] ?? 0 ?>)" class="btn btn-sm" style="background: linear-gradient(45deg, #00bcd4, #0097a7); color: white; margin-right: 0.5rem;">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button onclick="deleteBook(<?= $book['id'] ?>)" class="btn btn-sm btn-danger">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" style="text-align: center; color: #666;">No items found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- Add Item Modal -->
    <div class="modal-overlay" id="addModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="modalTitle">Add New Item</h2>
                <button class="close-btn" onclick="closeAddModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="form-tabs">
                <button class="tab-btn active" id="bookTab" onclick="switchTab('book')">
                    <i class="fas fa-book"></i> Book
                </button>
                <button class="tab-btn" id="stationeryTab" onclick="switchTab('stationery')">
                    <i class="fas fa-pen"></i> Stationery
                </button>
            </div>

            <!-- Book Form -->
            <div class="form-content active" id="bookForm">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add_book">
                    
                    <!-- Image Upload -->
                    <div class="form-group">
                        <label>Book Image</label>
                        <div class="image-upload-container" id="bookImageUpload" onclick="document.getElementById('book_image').click()">
                            <div class="upload-icon">
                                <i class="fas fa-cloud-upload-alt"></i>
                            </div>
                            <div class="upload-text">
                                Click to upload image or drag and drop<br>
                                <small>Max 5MB • JPEG, PNG, GIF, WebP</small>
                            </div>
                        </div>
                        <input type="file" id="book_image" name="image" accept="image/*" style="display: none;" onchange="previewImage(this, 'bookImageUpload')">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="book_title">Book Title *</label>
                            <input type="text" id="book_title" name="title" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="book_author">Author *</label>
                            <input type="text" id="book_author" name="author" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="book_price">Price *</label>
                            <input type="number" step="0.01" id="book_price" name="price" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="book_quantity">Quantity *</label>
                            <input type="number" min="0" id="book_quantity" name="quantity" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="book_rating">Rating (0-5)</label>
                        <input type="number" step="0.1" min="0" max="5" id="book_rating" name="rating" class="form-control" placeholder="4.5">
                    </div>
                    <div class="form-group">
                        <label for="book_description">Description</label>
                        <textarea id="book_description" name="description" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="button-group">
                        <button type="button" onclick="closeAddModal()" class="btn btn-danger">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Save Book
                        </button>
                    </div>
                </form>
            </div>

            <!-- Stationery Form -->
            <div class="form-content" id="stationeryForm">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add_stationery">
                    
                    <!-- Image Upload -->
                    <div class="form-group">
                        <label>Stationery Image</label>
                        <div class="image-upload-container" id="stationeryImageUpload" onclick="document.getElementById('stationery_image').click()">
                            <div class="upload-icon">
                                <i class="fas fa-cloud-upload-alt"></i>
                            </div>
                            <div class="upload-text">
                                Click to upload image or drag and drop<br>
                                <small>Max 5MB • JPEG, PNG, GIF, WebP</small>
                            </div>
                        </div>
                        <input type="file" id="stationery_image" name="image" accept="image/*" style="display: none;" onchange="previewImage(this, 'stationeryImageUpload')">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="stationery_name">Item Name *</label>
                            <input type="text" id="stationery_name" name="item_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="stationery_price">Price *</label>
                            <input type="number" step="0.01" id="stationery_price" name="price" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="stationery_quantity">Quantity *</label>
                        <input type="number" min="0" id="stationery_quantity" name="quantity" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="stationery_description">Description</label>
                        <textarea id="stationery_description" name="description" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="button-group">
                        <button type="button" onclick="closeAddModal()" class="btn btn-danger">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Save Stationery
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function toggleEditForm() {
            const form = document.getElementById('editBookForm');
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }

        function editBook(id, title, author, price, category, quantity, reorderLevel, description, image, rating) {
            document.getElementById('edit_book_id').value = id;
            document.getElementById('edit_title').value = title;
            document.getElementById('edit_author').value = author;
            document.getElementById('edit_price').value = price;
            document.getElementById('edit_category').value = category || '';
            document.getElementById('edit_quantity').value = quantity;
            document.getElementById('edit_reorder_level').value = reorderLevel;
            document.getElementById('edit_description').value = description || '';
            document.getElementById('edit_rating').value = rating || 0;
            
            // Display current image
            const editImagePreview = document.getElementById('editCurrentImage');
            if (image && image.trim() !== '') {
                editImagePreview.innerHTML = `<img src="${image}" alt="Current Image" class="image-preview">`;
            } else {
                editImagePreview.innerHTML = '<div class="upload-text">No current image</div>';
            }
            
            toggleEditForm();
        }

        function deleteBook(id) {
            if (confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_book">
                    <input type="hidden" name="book_id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function openAddModal(type = 'book') {
            const modal = document.getElementById('addModal');
            modal.classList.add('show');
            switchTab(type);
            document.body.style.overflow = 'hidden';
        }

        function closeAddModal() {
            const modal = document.getElementById('addModal');
            modal.classList.remove('show');
            document.body.style.overflow = 'auto';
            
            // Reset forms and image previews
            document.querySelectorAll('#addModal form').forEach(form => form.reset());
            resetImageUpload('bookImageUpload');
            resetImageUpload('stationeryImageUpload');
        }

        function switchTab(type) {
            // Update tabs
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.form-content').forEach(content => content.classList.remove('active'));
            
            if (type === 'book') {
                document.getElementById('bookTab').classList.add('active');
                document.getElementById('bookForm').classList.add('active');
                document.getElementById('modalTitle').textContent = 'Add New Book';
            } else {
                document.getElementById('stationeryTab').classList.add('active');
                document.getElementById('stationeryForm').classList.add('active');
                document.getElementById('modalTitle').textContent = 'Add New Stationery';
            }
        }

        function previewImage(input, containerId) {
            const container = document.getElementById(containerId);
            const file = input.files[0];
            
            if (file) {
                // Validate file type
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Invalid file type. Only JPEG, PNG, GIF, and WebP are allowed.');
                    input.value = '';
                    return;
                }
                
                // Validate file size (5MB)
                if (file.size > 5 * 1024 * 1024) {
                    alert('File size too large. Maximum 5MB allowed.');
                    input.value = '';
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    container.innerHTML = `
                        <img src="${e.target.result}" alt="Preview" class="image-preview">
                        <button type="button" class="remove-image" onclick="removeImage('${input.id}', '${containerId}')">
                            <i class="fas fa-times"></i>
                        </button>
                        <div class="upload-text">Click to change image</div>
                    `;
                    container.classList.add('has-image');
                };
                reader.readAsDataURL(file);
            }
        }

        function previewEditImage(input) {
            const file = input.files[0];
            const container = document.getElementById('editImagePreview');
            
            if (file) {
                // Validate file type
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Invalid file type. Only JPEG, PNG, GIF, and WebP are allowed.');
                    input.value = '';
                    return;
                }
                
                // Validate file size (5MB)
                if (file.size > 5 * 1024 * 1024) {
                    alert('File size too large. Maximum 5MB allowed.');
                    input.value = '';
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('editCurrentImage').innerHTML = `
                        <img src="${e.target.result}" alt="New Preview" class="image-preview">
                        <button type="button" class="remove-image" onclick="removeEditImage()">
                            <i class="fas fa-times"></i>
                        </button>
                        <div class="upload-text">New image selected</div>
                    `;
                    container.classList.add('has-image');
                };
                reader.readAsDataURL(file);
            }
        }

        function removeImage(inputId, containerId) {
            document.getElementById(inputId).value = '';
            resetImageUpload(containerId);
        }

        function removeEditImage() {
            document.getElementById('edit_image').value = '';
            const container = document.getElementById('editImagePreview');
            document.getElementById('editCurrentImage').innerHTML = '<div class="upload-text">No current image</div>';
            container.classList.remove('has-image');
        }

        function resetImageUpload(containerId) {
            const container = document.getElementById(containerId);
            container.innerHTML = `
                <div class="upload-icon">
                    <i class="fas fa-cloud-upload-alt"></i>
                </div>
                <div class="upload-text">
                    Click to upload image or drag and drop<br>
                    <small>Max 5MB • JPEG, PNG, GIF, WebP</small>
                </div>
            `;
            container.classList.remove('has-image');
        }

        // Drag and drop functionality
        function setupDragAndDrop(containerId, inputId) {
            const container = document.getElementById(containerId);
            const input = document.getElementById(inputId);

            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                container.addEventListener(eventName, preventDefaults, false);
            });

            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }

            ['dragenter', 'dragover'].forEach(eventName => {
                container.addEventListener(eventName, highlight, false);
            });

            ['dragleave', 'drop'].forEach(eventName => {
                container.addEventListener(eventName, unhighlight, false);
            });

            function highlight(e) {
                container.style.borderColor = 'var(--accent-primary)';
                container.style.background = 'rgba(0, 255, 231, 0.1)';
            }

            function unhighlight(e) {
                container.style.borderColor = 'var(--border-glass)';
                container.style.background = '';
            }

            container.addEventListener('drop', handleDrop, false);

            function handleDrop(e) {
                const dt = e.dataTransfer;
                const files = dt.files;

                if (files.length > 0) {
                    input.files = files;
                    previewImage(input, containerId);
                }
            }
        }

        // Initialize drag and drop
        document.addEventListener('DOMContentLoaded', function() {
            setupDragAndDrop('bookImageUpload', 'book_image');
            setupDragAndDrop('stationeryImageUpload', 'stationery_image');
        });

        // Close modal on overlay click
        document.getElementById('addModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeAddModal();
            }
        });

        // Close modal on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeAddModal();
            }
        });
    </script>
</body>
</html>