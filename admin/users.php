<?php
require_once 'config/database.php';
checkAdminLogin();

$message = '';
$error = '';

// Handle add admin
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'add_admin') {
    $full_name = $_POST['full_name'] ?? '';
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (!empty($full_name) && !empty($username) && !empty($email) && !empty($password)) {
        try {
            $conn = getConnection();
            
            // Check if username or email already exists
            $check_stmt = $conn->prepare("SELECT id FROM admins WHERE username = ? OR email = ?");
            $check_stmt->bind_param("ss", $username, $email);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows > 0) {
                $error = 'Username or email already exists';
            } else {
                // Hash password and insert admin
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO admins (full_name, username, email, password) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $full_name, $username, $email, $hashed_password);
                $stmt->execute();
                $message = 'Admin added successfully!';
                $stmt->close();
            }
            
            $check_stmt->close();
            $conn->close();
        } catch (mysqli_sql_exception $e) {
            $error = 'Error adding admin: ' . $e->getMessage();
        }
    } else {
        $error = 'Please fill in all required fields';
    }
}

// Handle edit admin
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'edit_admin') {
    $admin_id = $_POST['admin_id'] ?? '';
    $full_name = $_POST['full_name'] ?? '';
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (!empty($admin_id) && !empty($full_name) && !empty($username) && !empty($email)) {
        try {
            $conn = getConnection();
            
            if (!empty($password)) {
                // Update with new password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE admins SET full_name = ?, username = ?, email = ?, password = ? WHERE id = ?");
                $stmt->bind_param("ssssi", $full_name, $username, $email, $hashed_password, $admin_id);
            } else {
                // Update without changing password
                $stmt = $conn->prepare("UPDATE admins SET full_name = ?, username = ?, email = ? WHERE id = ?");
                $stmt->bind_param("sssi", $full_name, $username, $email, $admin_id);
            }
            
            $stmt->execute();
            $message = 'Admin updated successfully!';
            $stmt->close();
            $conn->close();
        } catch (mysqli_sql_exception $e) {
            $error = 'Error updating admin: ' . $e->getMessage();
        }
    } else {
        $error = 'Please fill in all required fields';
    }
}

// Handle delete admin
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'delete_admin') {
    $admin_id = $_POST['admin_id'] ?? '';
    
    if (!empty($admin_id) && $admin_id != $_SESSION['admin_id']) {
        try {
            $conn = getConnection();
            $stmt = $conn->prepare("DELETE FROM admins WHERE id = ?");
            $stmt->bind_param("i", $admin_id);
            $stmt->execute();
            $message = 'Admin deleted successfully!';
            $stmt->close();
            $conn->close();
        } catch (mysqli_sql_exception $e) {
            $error = 'Error deleting admin: ' . $e->getMessage();
        }
    } else {
        $error = 'Cannot delete your own account or invalid admin ID';
    }
}

// Get all admins
try {
    $conn = getConnection();
    $result = $conn->query("SELECT * FROM admins ORDER BY created_at DESC");
    $admins = [];
    while ($row = $result->fetch_assoc()) {
        $admins[] = $row;
    }
    $conn->close();
} catch (mysqli_sql_exception $e) {
    $error = "Database error: " . $e->getMessage();
}
include 'nav_bar.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Management - KiddleBookshop Admin</title>
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
            max-width: 500px;
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

        .button-group {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            justify-content: flex-end;
        }

        @media (max-width: 768px) {
            .modal-content {
                width: 95%;
                padding: 1.5rem;
                max-height: 90vh;
            }

            .button-group {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <h1 class="page-title">Admin Management</h1>
                <button onclick="openAddModal()" class="btn btn-success">
                    <i class="fas fa-plus"></i> Add Admin
                </button>
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

            <!-- Admins List -->
            <div class="table-container">
                <div style="padding: 1.5rem; border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
                    <h3>All Admins (<?= count($admins ?? []) ?>)</h3>
                </div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Full Name</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($admins)): ?>
                            <?php foreach ($admins as $admin): ?>
                                <tr>
                                    <td><?= $admin['id'] ?></td>
                                    <td><?= htmlspecialchars($admin['full_name']) ?></td>
                                    <td><?= htmlspecialchars($admin['username']) ?></td>
                                    <td><?= htmlspecialchars($admin['email']) ?></td>
                                    <td><?= date('M j, Y', strtotime($admin['created_at'])) ?></td>
                                    <td>
                                        <button onclick="openEditModal(<?= $admin['id'] ?>, '<?= htmlspecialchars($admin['full_name']) ?>', '<?= htmlspecialchars($admin['username']) ?>', '<?= htmlspecialchars($admin['email']) ?>')" class="btn btn-sm" style="background: linear-gradient(45deg, #00bcd4, #0097a7); color: white; margin-right: 0.5rem;">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <?php if ($admin['id'] != $_SESSION['admin_id']): ?>
                                            <button onclick="deleteAdmin(<?= $admin['id'] ?>)" class="btn btn-sm btn-danger">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        <?php else: ?>
                                            <span class="badge badge-success">Current User</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; color: #666;">No admins found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- Add Admin Modal -->
    <div class="modal-overlay" id="addModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Add New Admin</h2>
                <button class="close-btn" onclick="closeAddModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form method="POST">
                <input type="hidden" name="action" value="add_admin">
                <div class="form-group">
                    <label for="admin_full_name">Full Name *</label>
                    <input type="text" id="admin_full_name" name="full_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="admin_username">Username *</label>
                    <input type="text" id="admin_username" name="username" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="admin_email">Email *</label>
                    <input type="email" id="admin_email" name="email" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="admin_password">Password *</label>
                    <input type="password" id="admin_password" name="password" class="form-control" required>
                </div>
                <div class="button-group">
                    <button type="button" onclick="closeAddModal()" class="btn btn-danger">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Save Admin
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Admin Modal -->
    <div class="modal-overlay" id="editModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Edit Admin</h2>
                <button class="close-btn" onclick="closeEditModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form method="POST">
                <input type="hidden" name="action" value="edit_admin">
                <input type="hidden" name="admin_id" id="edit_admin_id">
                <div class="form-group">
                    <label for="edit_full_name">Full Name *</label>
                    <input type="text" id="edit_full_name" name="full_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="edit_username">Username *</label>
                    <input type="text" id="edit_username" name="username" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="edit_email">Email *</label>
                    <input type="email" id="edit_email" name="email" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="edit_password">New Password (leave blank to keep current)</label>
                    <input type="password" id="edit_password" name="password" class="form-control">
                </div>
                <div class="button-group">
                    <button type="button" onclick="closeEditModal()" class="btn btn-danger">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Update Admin
                    </button>
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
            
            // Reset form
            document.querySelector('#addModal form').reset();
        }

        function openEditModal(id, fullName, username, email) {
            document.getElementById('edit_admin_id').value = id;
            document.getElementById('edit_full_name').value = fullName;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_password').value = '';
            
            const modal = document.getElementById('editModal');
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closeEditModal() {
            const modal = document.getElementById('editModal');
            modal.classList.remove('show');
            document.body.style.overflow = 'auto';
        }

        function deleteAdmin(id) {
            if (confirm('Are you sure you want to delete this admin? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_admin">
                    <input type="hidden" name="admin_id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Close modals on overlay click
        document.querySelectorAll('.modal-overlay').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    if (this.id === 'addModal') {
                        closeAddModal();
                    } else if (this.id === 'editModal') {
                        closeEditModal();
                    }
                }
            });
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