<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config/database.php';
checkAdminLogin();

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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_book':
                try {
                    $conn = getConnection();
                    
                    // Fixed upload directory path
                    $uploadDir = __DIR__ . '/uploads/books/';
                    
                    // Create directory if it doesn't exist
                    if (!is_dir($uploadDir)) {
                        if (!mkdir($uploadDir, 0755, true)) {
                            throw new Exception("Failed to create upload directory");
                        }
                    }
                    
                    $imagePath = '';
                    if (isset($_FILES['book_image']) && $_FILES['book_image']['error'] === UPLOAD_ERR_OK) {
                        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                        $fileType = $_FILES['book_image']['type'];
                        
                        if (in_array($fileType, $allowedTypes)) {
                            // Check file size (5MB max)
                            if ($_FILES['book_image']['size'] > 5 * 1024 * 1024) {
                                throw new Exception("File size too large. Maximum 5MB allowed.");
                            }
                            
                            $extension = pathinfo($_FILES['book_image']['name'], PATHINFO_EXTENSION);
                            $filename = uniqid('book_') . '.' . $extension;
                            $fullPath = $uploadDir . $filename;
                            
                            if (move_uploaded_file($_FILES['book_image']['tmp_name'], $fullPath)) {
                                // Store relative path for database
                                $imagePath = 'uploads/books/' . $filename;
                            } else {
                                throw new Exception("Failed to move uploaded file");
                            }
                        } else {
                            throw new Exception("Invalid file type. Only JPEG, PNG, GIF, and WebP are allowed.");
                        }
                    }
                    
                    $stmt = $conn->prepare("INSERT INTO books (title, author, price, quantity, reorder_level, description, image, rating) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("ssdiissd", 
                        $_POST['title'], 
                        $_POST['author'], 
                        $_POST['price'], 
                        $_POST['quantity'], 
                        $_POST['reorder_level'], 
                        $_POST['description'], 
                        $imagePath, 
                        $_POST['rating']
                    );
                    
                    if ($stmt->execute()) {
                        $success = "Book added successfully!";
                    } else {
                        $error = "Error adding book: " . $stmt->error;
                    }
                    $stmt->close();
                    $conn->close();
                } catch (Exception $e) {
                    $error = "Error: " . $e->getMessage();
                }
                break;
                
            case 'edit_book':
                try {
                    $conn = getConnection();
                    $uploadDir = __DIR__ . '/uploads/books/';
                    
                    $imagePath = $_POST['current_image'] ?? '';
                    if (isset($_FILES['edit_image']) && $_FILES['edit_image']['error'] === UPLOAD_ERR_OK) {
                        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                        $fileType = $_FILES['edit_image']['type'];
                        
                        if (in_array($fileType, $allowedTypes)) {
                            // Check file size (5MB max)
                            if ($_FILES['edit_image']['size'] > 5 * 1024 * 1024) {
                                throw new Exception("File size too large. Maximum 5MB allowed.");
                            }
                            
                            // Delete old image if exists
                            if (!empty($imagePath) && file_exists(__DIR__ . '/' . $imagePath)) {
                                unlink(__DIR__ . '/' . $imagePath);
                            }
                            
                            $extension = pathinfo($_FILES['edit_image']['name'], PATHINFO_EXTENSION);
                            $filename = uniqid('book_') . '.' . $extension;
                            $fullPath = $uploadDir . $filename;
                            
                            if (move_uploaded_file($_FILES['edit_image']['tmp_name'], $fullPath)) {
                                // Store relative path for database
                                $imagePath = 'uploads/books/' . $filename;
                            } else {
                                throw new Exception("Failed to move uploaded file");
                            }
                        } else {
                            throw new Exception("Invalid file type. Only JPEG, PNG, GIF, and WebP are allowed.");
                        }
                    }
                    
                    $stmt = $conn->prepare("UPDATE books SET title=?, author=?, price=?, quantity=?, reorder_level=?, description=?, image=?, rating=? WHERE id=?");
                    $stmt->bind_param("ssdiissdi", 
                        $_POST['edit_title'], 
                        $_POST['edit_author'], 
                        $_POST['edit_price'],
                        $_POST['edit_quantity'], 
                        $_POST['edit_reorder_level'], 
                        $_POST['edit_description'], 
                        $imagePath, 
                        $_POST['edit_rating'],
                        $_POST['edit_book_id']
                    );
                    
                    if ($stmt->execute()) {
                        $success = "Book updated successfully!";
                    } else {
                        $error = "Error updating book: " . $stmt->error;
                    }
                    $stmt->close();
                    $conn->close();
                } catch (Exception $e) {
                    $error = "Error: " . $e->getMessage();
                }
                break;
                
            case 'delete_book':
                try {
                    $conn = getConnection();
                    
                    // Get image path before deletion
                    $stmt = $conn->prepare("SELECT image FROM books WHERE id = ?");
                    $stmt->bind_param("i", $_POST['book_id']);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $book = $result->fetch_assoc();
                    $stmt->close();
                    
                    // Delete the book
                    $stmt = $conn->prepare("DELETE FROM books WHERE id = ?");
                    $stmt->bind_param("i", $_POST['book_id']);
                    
                    if ($stmt->execute()) {
                        // Delete image file if exists
                        if (!empty($book['image']) && file_exists(__DIR__ . '/' . $book['image'])) {
                            unlink(__DIR__ . '/' . $book['image']);
                        }
                        $success = "Book deleted successfully!";
                    } else {
                        $error = "Error deleting book: " . $stmt->error;
                    }
                    $stmt->close();
                    $conn->close();
                } catch (Exception $e) {
                    $error = "Error: " . $e->getMessage();
                }
                break;
        }
        
        // Refresh data after operations
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
    }
}
include 'nav_bar.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Books Management - KiddleBookshop Admin</title>
    <link rel="stylesheet" href="assets/css/admin-style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">

        <!-- Main Content -->
        <main class="main-content">
            <!-- Page Header -->
            <div class="page-header">
                <h1 class="page-title">Books Management</h1>
                <div class="books-actions">
                    <button onclick="openAddModal()" class="btn btn-success">
                        <i class="fas fa-plus"></i> Add New Book
                    </button>
                </div>
            </div>

            <!-- Alerts -->
            <?php if (isset($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <!-- Books List -->
            <div class="table-container">
                <div class="table-header">
                    <h3>All Books (<?= count($books ?? []) ?>)</h3>
                </div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Author</th>
                            <th>Price</th>
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
                                        <?php if (!empty($book['image']) && file_exists(__DIR__ . '/' . $book['image'])): ?>
                                            <img src="<?= htmlspecialchars($book['image']) ?>" alt="Book Image" class="item-image">
                                        <?php else: ?>
                                            <div class="no-image">No Image</div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $book['id'] ?></td>
                                    <td><?= htmlspecialchars($book['title']) ?></td>
                                    <td><?= htmlspecialchars($book['author']) ?></td>
                                    <td>Ksh<?= number_format($book['price'], 2) ?></td>
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
                                        <?php if ($book['quantity'] == 0): ?>
                                            <span class="badge badge-danger">Out of Stock</span>
                                        <?php elseif ($book['quantity'] <= ($book['reorder_level'] ?? 10)): ?>
                                            <span class="badge badge-warning">Low Stock</span>
                                        <?php else: ?>
                                            <span class="badge badge-success">In Stock</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button onclick="openEditModal(
                                            <?= $book['id'] ?>, 
                                            '<?= htmlspecialchars($book['title']) ?>', 
                                            '<?= htmlspecialchars($book['author']) ?>', 
                                            <?= $book['price'] ?>, 
                                            <?= $book['quantity'] ?>, 
                                            <?= $book['reorder_level'] ?? 10 ?>, 
                                            '<?= htmlspecialchars($book['description'] ?? '') ?>', 
                                            '<?= htmlspecialchars($book['image'] ?? '') ?>', 
                                            <?= $book['rating'] ?? 0 ?>
                                        )" class="btn btn-sm btn-edit-book-modal">
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
                                <td colspan="9" class="text-center text-muted-alt">No books found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- Add Book Modal -->
    <div id="addModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Add New Book</h2>
                <button type="button" class="close-btn" onclick="closeAddModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add_book">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="title">Title</label>
                        <input type="text" id="title" name="title" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="author">Author</label>
                        <input type="text" id="author" name="author" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="price">Price (Ksh)</label>
                        <input type="number" id="price" name="price" class="form-control" min="0" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label for="rating">Rating (0-5)</label>
                        <input type="number" id="rating" name="rating" class="form-control" min="0" max="5" step="0.1" value="0">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="quantity">Quantity</label>
                        <input type="number" id="quantity" name="quantity" class="form-control" min="0" required>
                    </div>
                    <div class="form-group">
                        <label for="reorder_level">Reorder Level</label>
                        <input type="number" id="reorder_level" name="reorder_level" class="form-control" min="0" value="10">
                    </div>
                </div>
                
                <div class="form-row single">
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" class="form-control" rows="4"></textarea>
                    </div>
                </div>
                
                <div class="form-row single">
                    <div class="form-group">
                        <label>Book Image</label>
                        <div id="bookImageUpload" class="image-upload-container" onclick="document.getElementById('book_image').click()">
                            <div class="upload-icon">
                                <i class="fas fa-cloud-upload-alt"></i>
                            </div>
                            <div class="upload-text">
                                Click to upload image or drag and drop<br>
                                <small>Max 5MB • JPEG, PNG, GIF, WebP</small>
                            </div>
                        </div>
                        <input type="file" id="book_image" name="book_image" style="display: none;" accept="image/*" onchange="previewImage(this, 'bookImageUpload')">
                    </div>
                </div>
                
                <div class="button-group">
                    <button type="button" onclick="closeAddModal()" class="btn btn-sm btn-cancel">Cancel</button>
                    <button type="submit" class="btn btn-success">Add Book</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Book Modal -->
    <div id="editModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Edit Book</h2>
                <button type="button" class="close-btn" onclick="closeEditModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit_book">
                <input type="hidden" id="edit_book_id" name="edit_book_id">
                <input type="hidden" id="current_image" name="current_image">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_title">Title</label>
                        <input type="text" id="edit_title" name="edit_title" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_author">Author</label>
                        <input type="text" id="edit_author" name="edit_author" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_price">Price (Ksh)</label>
                        <input type="number" id="edit_price" name="edit_price" class="form-control" min="0" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_rating">Rating (0-5)</label>
                        <input type="number" id="edit_rating" name="edit_rating" class="form-control" min="0" max="5" step="0.1" value="0">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_quantity">Quantity</label>
                        <input type="number" id="edit_quantity" name="edit_quantity" class="form-control" min="0" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_reorder_level">Reorder Level</label>
                        <input type="number" id="edit_reorder_level" name="edit_reorder_level" class="form-control" min="0" value="10">
                    </div>
                </div>
                
                <div class="form-row single">
                    <div class="form-group">
                        <label for="edit_description">Description</label>
                        <textarea id="edit_description" name="edit_description" class="form-control" rows="4"></textarea>
                    </div>
                </div>
                
                <div class="form-row single">
                    <div class="form-group">
                        <label>Current Image</label>
                        <div id="editCurrentImage" class="upload-text">No current image</div>
                        <label for="edit_image" class="edit-image-label">Upload New Image</label>
                        <input type="file" id="edit_image" name="edit_image" class="form-control" accept="image/*" onchange="previewEditImage(this)">
                    </div>
                </div>
                
                <div class="button-group">
                    <button type="button" onclick="closeEditModal()" class="btn btn-sm btn-cancel">Cancel</button>
                    <button type="submit" class="btn btn-success">Update Book</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddModal() {
            const modal = document.getElementById('addModal');
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closeAddModal() {
            const modal = document.getElementById('addModal');
            modal.classList.remove('show');
            document.body.style.overflow = 'auto';
            
            // Reset form and image preview
            document.querySelector('#addModal form').reset();
            resetImageUpload('bookImageUpload');
        }

        function openEditModal(id, title, author, price, quantity, reorderLevel, description, image, rating) {
            document.getElementById('edit_book_id').value = id;
            document.getElementById('edit_title').value = title;
            document.getElementById('edit_author').value = author;
            document.getElementById('edit_price').value = price;
            document.getElementById('edit_quantity').value = quantity;
            document.getElementById('edit_reorder_level').value = reorderLevel;
            document.getElementById('edit_description').value = description || '';
            document.getElementById('edit_rating').value = rating || 0;
            document.getElementById('current_image').value = image || '';
            
            // Display current image
            const editImageContainer = document.getElementById('editCurrentImage');
            if (image && image.trim() !== '') {
                editImageContainer.innerHTML = `<img src="${image}" alt="Current Image" class="image-preview">`;
            } else {
                editImageContainer.innerHTML = '<div class="upload-text">No current image</div>';
            }
            
            const modal = document.getElementById('editModal');
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closeEditModal() {
            const modal = document.getElementById('editModal');
            modal.classList.remove('show');
            document.body.style.overflow = 'auto';
        }

        function deleteBook(id) {
            if (confirm('Are you sure you want to delete this book? This action cannot be undone.')) {
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
            const container = document.getElementById('editCurrentImage');
            
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
                        <img src="${e.target.result}" alt="New Preview" class="image-preview">
                        <div class="upload-text">New image selected</div>
                    `;
                };
                reader.readAsDataURL(file);
            }
        }

        function removeImage(inputId, containerId) {
            document.getElementById(inputId).value = '';
            resetImageUpload(containerId);
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
        });

        // Close modal on overlay click
        document.getElementById('addModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeAddModal();
            }
        });

        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditModal();
            }
        });

        // Close modal on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeAddModal();
                closeEditModal();
            }
        });
    </script>
</body>
</html>