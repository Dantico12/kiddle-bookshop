<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'config/database.php';
checkAdminLogin();

// Get all stationery
try {
    $conn = getConnection();
    $result = $conn->query("SELECT * FROM stationery ORDER BY created_at DESC");
    $stationery = [];
    while ($row = $result->fetch_assoc()) {
        $stationery[] = $row;
    }
    $conn->close();
} catch (mysqli_sql_exception $e) {
    $error = "Database error: " . $e->getMessage();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_stationery':
                try {
                    $conn = getConnection();
                    $uploadDir = __DIR__ . '/uploads/stationery/';
                    if (!is_dir($uploadDir)) {
                        if (!mkdir($uploadDir, 0755, true)) {
                            $uploadDir = sys_get_temp_dir() . '/kiddle_stationery/';
                            if (!is_dir($uploadDir)) {
                                mkdir($uploadDir, 0755, true);
                            }
                        }
                    }
                    if (!is_writable($uploadDir)) {
                        $uploadDir = sys_get_temp_dir() . '/kiddle_stationery/';
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0755, true);
                        }
                    }
                    $imagePath = '';
                    if (isset($_FILES['stationery_image']) && $_FILES['stationery_image']['error'] === UPLOAD_ERR_OK) {
                        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                        $fileType = $_FILES['stationery_image']['type'];
                        if (in_array($fileType, $allowedTypes)) {
                            if ($_FILES['stationery_image']['size'] > 5 * 1024 * 1024) {
                                throw new Exception("File size too large. Maximum 5MB allowed.");
                            }
                            $extension = pathinfo($_FILES['stationery_image']['name'], PATHINFO_EXTENSION);
                            $filename = uniqid('stationery_') . '.' . $extension;
                            $fullPath = $uploadDir . $filename;
                            if (move_uploaded_file($_FILES['stationery_image']['tmp_name'], $fullPath)) {
                                if (!file_exists($fullPath)) {
                                    throw new Exception("File upload failed. Please check directory permissions.");
                                }
                                $imagePath = str_replace(__DIR__ . '/', '', $fullPath);
                            } else {
                                $uploadError = error_get_last();
                                throw new Exception("Failed to move uploaded file. Please check directory permissions.");
                            }
                        } else {
                            throw new Exception("Invalid file type. Only JPEG, PNG, GIF, and WebP are allowed.");
                        }
                    }
                    $stmt = $conn->prepare("INSERT INTO stationery (name, price, quantity, reorder_level, description, image) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("sdiiss", 
                        $_POST['name'], 
                        $_POST['price'], 
                        $_POST['quantity'], 
                        $_POST['reorder_level'], 
                        $_POST['description'], 
                        $imagePath
                    );
                    if ($stmt->execute()) {
                        $success = "Stationery item added successfully!";
                    } else {
                        $error = "Error adding stationery: " . $stmt->error;
                    }
                    $stmt->close();
                    $conn->close();
                } catch (Exception $e) {
                    $error = "Upload Error: " . $e->getMessage();
                }
                break;
                
            case 'edit_stationery':
                try {
                    $conn = getConnection();
                    $uploadDir = __DIR__ . '/uploads/stationery/';
                    if (!is_writable($uploadDir)) {
                        $uploadDir = sys_get_temp_dir() . '/kiddle_stationery/';
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0755, true);
                        }
                    }
                    $imagePath = $_POST['current_image'] ?? '';
                    if (isset($_FILES['edit_image']) && $_FILES['edit_image']['error'] === UPLOAD_ERR_OK) {
                        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                        $fileType = $_FILES['edit_image']['type'];
                        if (in_array($fileType, $allowedTypes)) {
                            if ($_FILES['edit_image']['size'] > 5 * 1024 * 1024) {
                                throw new Exception("File size too large. Maximum 5MB allowed.");
                            }
                            if (!empty($imagePath) && file_exists(__DIR__ . '/' . $imagePath)) {
                                unlink(__DIR__ . '/' . $imagePath);
                            }
                            $extension = pathinfo($_FILES['edit_image']['name'], PATHINFO_EXTENSION);
                            $filename = uniqid('stationery_') . '.' . $extension;
                            $fullPath = $uploadDir . $filename;
                            if (move_uploaded_file($_FILES['edit_image']['tmp_name'], $fullPath)) {
                                $imagePath = str_replace(__DIR__ . '/', '', $fullPath);
                            } else {
                                throw new Exception("Failed to move uploaded file. Please check directory permissions.");
                            }
                        } else {
                            throw new Exception("Invalid file type. Only JPEG, PNG, GIF, and WebP are allowed.");
                        }
                    }
                    $stmt = $conn->prepare("UPDATE stationery SET name=?, price=?, quantity=?, reorder_level=?, description=?, image=? WHERE id=?");
                    $stmt->bind_param("sdiissi", 
                        $_POST['edit_name'], 
                        $_POST['edit_price'], 
                        $_POST['edit_quantity'], 
                        $_POST['edit_reorder_level'], 
                        $_POST['edit_description'], 
                        $imagePath,
                        $_POST['edit_stationery_id']
                    );
                    if ($stmt->execute()) {
                        $success = "Stationery item updated successfully!";
                    } else {
                        $error = "Error updating stationery: " . $stmt->error;
                    }
                    $stmt->close();
                    $conn->close();
                } catch (Exception $e) {
                    $error = "Error: " . $e->getMessage();
                }
                break;
                
            case 'delete_stationery':
                try {
                    $conn = getConnection();
                    $stmt = $conn->prepare("SELECT image FROM stationery WHERE id = ?");
                    $stmt->bind_param("i", $_POST['stationery_id']);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $item = $result->fetch_assoc();
                    $stmt->close();
                    $stmt = $conn->prepare("DELETE FROM stationery WHERE id = ?");
                    $stmt->bind_param("i", $_POST['stationery_id']);
                    if ($stmt->execute()) {
                        if (!empty($item['image']) && file_exists(__DIR__ . '/' . $item['image'])) {
                            unlink(__DIR__ . '/' . $item['image']);
                        }
                        $success = "Stationery item deleted successfully!";
                    } else {
                        $error = "Error deleting stationery: " . $stmt->error;
                    }
                    $stmt->close();
                    $conn->close();
                } catch (Exception $e) {
                    $error = "Error: " . $e->getMessage();
                }
                break;
        }
        try {
            $conn = getConnection();
            $result = $conn->query("SELECT * FROM stationery ORDER BY created_at DESC");
            $stationery = [];
            while ($row = $result->fetch_assoc()) {
                $stationery[] = $row;
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
    <title>Stationery Management - KiddleBookshop Admin</title>
    <link rel="stylesheet" href="assets/css/admin-style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">
        <main class="main-content">
            <div class="page-header">
                <h1 class="page-title">Stationery Management</h1>
                <div class="stationery-actions">
                    <button onclick="openAddModal()" class="btn btn-success">
                        <i class="fas fa-plus"></i> Add New Stationery
                    </button>
                </div>
            </div>

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

            <!-- Upload status div (hidden by default) -->
            <div id="uploadStatus"></div>

            <div class="table-container">
                <div class="table-header">
                    <h3>All Stationery Items (<?= count($stationery ?? []) ?>)</h3>
                </div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Price</th>
                            <th>Quantity</th>
                            <th>Reorder Level</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($stationery)): ?>
                            <?php foreach ($stationery as $item): ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($item['image']) && file_exists(__DIR__ . '/' . $item['image'])): ?>
                                            <img src="<?= htmlspecialchars($item['image']) ?>" alt="Stationery Image" class="item-image">
                                        <?php else: ?>
                                            <div class="no-image">No Image</div>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong>#<?= $item['id'] ?></strong></td>
                                    <td>
                                        <div class="stationery-name"><?= htmlspecialchars($item['name']) ?></div>
                                        <?php if (!empty($item['description'])): ?>
                                            <span class="stationery-description-preview"><?= htmlspecialchars(substr($item['description'], 0, 50)) ?>...</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong>Ksh<?= number_format($item['price'], 2) ?></strong></td>
                                    <td><span class="stationery-quantity"><?= $item['quantity'] ?></span></td>
                                    <td><span class="stationery-reorder-level"><?= $item['reorder_level'] ?? 10 ?></span></td>
                                    <td>
                                        <?php if ($item['quantity'] == 0): ?>
                                            <span class="badge badge-danger">Out of Stock</span>
                                        <?php elseif ($item['quantity'] <= ($item['reorder_level'] ?? 10)): ?>
                                            <span class="badge badge-warning">Low Stock</span>
                                        <?php else: ?>
                                            <span class="badge badge-success">In Stock</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="stationery-actions-cell">
                                            <button onclick="openEditModal(
                                                <?= $item['id'] ?>, 
                                                '<?= htmlspecialchars($item['name']) ?>', 
                                                <?= $item['price'] ?>, 
                                                <?= $item['quantity'] ?>, 
                                                <?= $item['reorder_level'] ?? 10 ?>, 
                                                '<?= htmlspecialchars($item['description'] ?? '') ?>', 
                                                '<?= htmlspecialchars($item['image'] ?? '') ?>'
                                            )" class="btn btn-sm btn-edit-stationery-modal">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <button onclick="deleteStationery(<?= $item['id'] ?>)" class="btn btn-sm btn-danger">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="empty-state">
                                    <i class="fas fa-box-open empty-state-icon"></i>
                                    <div>No stationery items found</div>
                                    <button onclick="openAddModal()" class="btn btn-success mt-2">
                                        <i class="fas fa-plus"></i> Add Your First Stationery Item
                                    </button>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- Add Stationery Modal -->
    <div id="addModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Add New Stationery</h2>
                <button type="button" class="close-btn" onclick="closeAddModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" enctype="multipart/form-data" id="addStationeryForm">
                <input type="hidden" name="action" value="add_stationery">
                <div class="form-row">
                    <div class="form-group">
                        <label for="name">Name *</label>
                        <input type="text" id="name" name="name" class="form-control" required placeholder="Enter stationery name">
                    </div>
                    <div class="form-group">
                        <label for="price">Price (Ksh) *</label>
                        <input type="number" id="price" name="price" class="form-control" min="0" step="0.01" required placeholder="0.00">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="quantity">Quantity *</label>
                        <input type="number" id="quantity" name="quantity" class="form-control" min="0" required placeholder="0">
                    </div>
                    <div class="form-group">
                        <label for="reorder_level">Reorder Level</label>
                        <input type="number" id="reorder_level" name="reorder_level" class="form-control" min="0" value="10" placeholder="10">
                        <span class="form-help-text">Low stock alert threshold</span>
                    </div>
                </div>
                <div class="form-row single">
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" class="form-control" rows="4" placeholder="Enter stationery description (optional)"></textarea>
                    </div>
                </div>
                <div class="form-row single">
                    <div class="form-group">
                        <label>Stationery Image</label>
                        <div id="stationeryImageUpload" class="image-upload-container" onclick="document.getElementById('stationery_image').click()">
                            <div class="upload-icon">
                                <i class="fas fa-cloud-upload-alt"></i>
                            </div>
                            <div class="upload-text">
                                Click to upload image or drag and drop<br>
                                <small>Max 5MB • JPEG, PNG, GIF, WebP</small>
                            </div>
                        </div>
                        <input type="file" id="stationery_image" name="stationery_image" style="display: none;" accept="image/*" onchange="previewImage(this, 'stationeryImageUpload')">
                    </div>
                </div>
                <div class="button-group">
                    <button type="button" onclick="closeAddModal()" class="btn btn-sm btn-cancel-gray">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Add Stationery
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Stationery Modal -->
    <div id="editModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Edit Stationery</h2>
                <button type="button" class="close-btn" onclick="closeEditModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" enctype="multipart/form-data" id="editStationeryForm">
                <input type="hidden" name="action" value="edit_stationery">
                <input type="hidden" id="edit_stationery_id" name="edit_stationery_id">
                <input type="hidden" id="current_image" name="current_image">
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_name">Name *</label>
                        <input type="text" id="edit_name" name="edit_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_price">Price (Ksh) *</label>
                        <input type="number" id="edit_price" name="edit_price" class="form-control" min="0" step="0.01" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_quantity">Quantity *</label>
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
                        <div id="editImageUpload" class="image-upload-container" onclick="document.getElementById('edit_image').click()">
                            <div class="upload-icon">
                                <i class="fas fa-sync-alt"></i>
                            </div>
                            <div class="upload-text">
                                Click to change image<br>
                                <small>Max 5MB • JPEG, PNG, GIF, WebP</small>
                            </div>
                        </div>
                        <input type="file" id="edit_image" name="edit_image" style="display: none;" accept="image/*" onchange="previewEditImage(this)">
                    </div>
                </div>
                <div class="button-group">
                    <button type="button" onclick="closeEditModal()" class="btn btn-sm btn-cancel-gray">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Update Stationery
                    </button>
                </div>
            </form>
        </div>
    </div>

   <script>
    // Modal Functions
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
        document.getElementById('addStationeryForm').reset();
        resetImageUpload('stationeryImageUpload');
    }

    function openEditModal(id, name, price, quantity, reorderLevel, description, image) {
        document.getElementById('edit_stationery_id').value = id;
        document.getElementById('edit_name').value = name;
        document.getElementById('edit_price').value = price;
        document.getElementById('edit_quantity').value = quantity;
        document.getElementById('edit_reorder_level').value = reorderLevel;
        document.getElementById('edit_description').value = description || '';
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

    function deleteStationery(id) {
        if (confirm('Are you sure you want to delete this stationery item? This action cannot be undone.')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="delete_stationery">
                <input type="hidden" name="stationery_id" value="${id}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }

    // Image Upload Functions
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
                    <div class="upload-text">New image selected - will replace current</div>
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

    // Drag and Drop functionality
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
            container.addEventListener(eventName, () => {
                container.classList.add('drag-over');
            }, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            container.addEventListener(eventName, () => {
                container.classList.remove('drag-over');
            }, false);
        });

        container.addEventListener('drop', function(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            if (files.length > 0) {
                input.files = files;
                previewImage(input, containerId);
            }
        }, false);
    }

    // Optional: Upload permission check (can be removed if not needed)
    // Removed inline style; uses global alert classes if you implement .alert-info
    // For now, we'll keep it simple or remove it since it's not critical

    // Initialize drag and drop
    document.addEventListener('DOMContentLoaded', function() {
        setupDragAndDrop('stationeryImageUpload', 'stationery_image');
        setupDragAndDrop('editImageUpload', 'edit_image');
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

    // Form validation
    document.getElementById('addStationeryForm').addEventListener('submit', function(e) {
        const price = parseFloat(document.getElementById('price').value);
        const quantity = parseInt(document.getElementById('quantity').value);
        if (isNaN(price) || price <= 0) {
            alert('Price must be greater than 0');
            e.preventDefault();
            return;
        }
        if (isNaN(quantity) || quantity < 0) {
            alert('Quantity cannot be negative');
            e.preventDefault();
            return;
        }
    });

    document.getElementById('editStationeryForm').addEventListener('submit', function(e) {
        const price = parseFloat(document.getElementById('edit_price').value);
        const quantity = parseInt(document.getElementById('edit_quantity').value);
        if (isNaN(price) || price <= 0) {
            alert('Price must be greater than 0');
            e.preventDefault();
            return;
        }
        if (isNaN(quantity) || quantity < 0) {
            alert('Quantity cannot be negative');
            e.preventDefault();
            return;
        }
    });
</script>
</body>
</html>