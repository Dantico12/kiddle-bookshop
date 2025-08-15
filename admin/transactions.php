
<?php
require_once 'config/database.php';
checkAdminLogin();

$message = '';
$error = '';

// Handle edit transaction
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'edit_transaction') {
    $transaction_id = $_POST['transaction_id'] ?? '';
    $customer_name = $_POST['customer_name'] ?? '';
    $customer_email = $_POST['customer_email'] ?? '';
    $customer_phone = $_POST['customer_phone'] ?? '';
    $status = $_POST['status'] ?? '';
    
    if (!empty($transaction_id) && !empty($customer_name) && !empty($customer_phone) && !empty($status)) {
        try {
            $conn = getConnection();
            $stmt = $conn->prepare("UPDATE transactions SET customer_name = ?, customer_email = ?, customer_phone = ?, status = ? WHERE id = ?");
            $stmt->bind_param("ssssi", $customer_name, $customer_email, $customer_phone, $status, $transaction_id);
            $stmt->execute();
            $message = 'Transaction updated successfully!';
            $stmt->close();
            $conn->close();
        } catch (mysqli_sql_exception $e) {
            $error = 'Error updating transaction: ' . $e->getMessage();
        }
    } else {
        $error = 'Please fill in all required fields';
    }
}

// Handle delete transaction
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'delete_transaction') {
    $transaction_id = $_POST['transaction_id'] ?? '';
    
    if (!empty($transaction_id)) {
        try {
            $conn = getConnection();
            $stmt = $conn->prepare("DELETE FROM transactions WHERE id = ?");
            $stmt->bind_param("i", $transaction_id);
            $stmt->execute();
            $message = 'Transaction deleted successfully!';
            $stmt->close();
            $conn->close();
        } catch (mysqli_sql_exception $e) {
            $error = 'Error deleting transaction: ' . $e->getMessage();
        }
    }
}

// Get all transactions with book details
try {
    $conn = getConnection();
    $query = "SELECT t.*, b.title, b.author 
              FROM transactions t 
              LEFT JOIN books b ON t.book_id = b.id 
              ORDER BY t.created_at DESC";
    $result = $conn->query($query);
    $transactions = [];
    while ($row = $result->fetch_assoc()) {
        $transactions[] = $row;
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
    <title>Transactions - NeoBooks Admin</title>
    <link rel="stylesheet" href="assets/css/admin-style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
            <nav class="sidebar">
            <div class="sidebar-header">
                <h3>NeoBooks Admin</h3>
                <p>Welcome, <?= htmlspecialchars($_SESSION['admin_username']) ?></p>
            </div>
            <ul class="sidebar-nav">
                <li class="nav-item">
                    <a href="dashboard.php" class="nav-link active">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a href="users.php" class="nav-link">
                        <i class="fas fa-users-cog"></i> Admin Management
                    </a>
                </li>
                <li class="nav-item">
                    <a href="inventory.php" class="nav-link">
                        <i class="fas fa-boxes"></i> Inventory
                    </a>
                </li>
                <li class="nav-item">
                    <a href="book.php" class="nav-link">
                        <i class="fas fa-credit-card"></i> Books
                    </a>
                </li>
                <li class="nav-item">
                    <a href="stationery.php" class="nav-link">
                        <i class="fas fa-credit-card"></i> Stationery
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
                <h1 class="page-title">Transaction Management</h1>
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

            <!-- Edit Transaction Form -->
            <div id="editTransactionForm" style="display: none;">
                <div class="form-container">
                    <h3>Edit Transaction</h3>
                    <form method="POST" id="editForm">
                        <input type="hidden" name="action" value="edit_transaction">
                        <input type="hidden" name="transaction_id" id="edit_transaction_id">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="edit_customer_name">Customer Name *</label>
                                <input type="text" id="edit_customer_name" name="customer_name" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label for="edit_customer_email">Customer Email</label>
                                <input type="email" id="edit_customer_email" name="customer_email" class="form-control">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="edit_customer_phone">Customer Phone *</label>
                                <input type="tel" id="edit_customer_phone" name="customer_phone" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label for="edit_status">Status *</label>
                                <select id="edit_status" name="status" class="form-control" required>
                                    <option value="pending">Pending</option>
                                    <option value="completed">Completed</option>
                                    <option value="failed">Failed</option>
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Update Transaction
                        </button>
                        <button type="button" onclick="toggleEditForm()" class="btn btn-danger">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </form>
                </div>
            </div>

            <!-- Transactions List -->
            <div class="table-container">
                <div style="padding: 1.5rem; border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
                    <h3>All Transactions (<?= count($transactions ?? []) ?>)</h3>
                </div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Customer</th>
                            <th>Phone</th>
                            <th>Book</th>
                            <th>Quantity</th>
                            <th>Amount</th>
                            <th>M-Pesa Ref</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($transactions)): ?>
                            <?php foreach ($transactions as $transaction): ?>
                                <tr>
                                    <td><?= $transaction['id'] ?></td>
                                    <td>
                                        <div><?= htmlspecialchars($transaction['customer_name']) ?></div>
                                        <small style="color: var(--text-muted);"><?= htmlspecialchars($transaction['customer_email'] ?? 'N/A') ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($transaction['customer_phone']) ?></td>
                                    <td>
                                        <div><?= htmlspecialchars($transaction['title'] ?? 'Book not found') ?></div>
                                        <small style="color: var(--text-muted);"><?= htmlspecialchars($transaction['author'] ?? '') ?></small>
                                    </td>
                                    <td><?= $transaction['quantity'] ?></td>
                                    <td>$<?= number_format($transaction['total_amount'], 2) ?></td>
                                    <td><?= htmlspecialchars($transaction['mpesa_reference'] ?? 'N/A') ?></td>
                                    <td>
                                        <?php
                                        $status = $transaction['status'];
                                        $badgeClass = 'badge-warning';
                                        if ($status === 'completed') $badgeClass = 'badge-success';
                                        if ($status === 'failed') $badgeClass = 'badge-danger';
                                        ?>
                                        <span class="badge <?= $badgeClass ?>"><?= ucfirst($status) ?></span>
                                    </td>
                                    <td><?= date('M j, Y g:i A', strtotime($transaction['created_at'])) ?></td>
                                    <td>
                                        <button onclick="editTransaction(<?= $transaction['id'] ?>, '<?= htmlspecialchars($transaction['customer_name']) ?>', '<?= htmlspecialchars($transaction['customer_email']) ?>', '<?= htmlspecialchars($transaction['customer_phone']) ?>', '<?= $transaction['status'] ?>')" class="btn btn-sm" style="background: linear-gradient(45deg, #00bcd4, #0097a7); color: white; margin-right: 0.5rem;">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button onclick="deleteTransaction(<?= $transaction['id'] ?>)" class="btn btn-sm btn-danger">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" style="text-align: center; color: #666;">No transactions found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <script>
        function toggleEditForm() {
            const form = document.getElementById('editTransactionForm');
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }

        function editTransaction(id, customerName, customerEmail, customerPhone, status) {
            document.getElementById('edit_transaction_id').value = id;
            document.getElementById('edit_customer_name').value = customerName;
            document.getElementById('edit_customer_email').value = customerEmail || '';
            document.getElementById('edit_customer_phone').value = customerPhone;
            document.getElementById('edit_status').value = status;
            toggleEditForm();
        }

        function deleteTransaction(id) {
            if (confirm('Are you sure you want to delete this transaction? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_transaction">
                    <input type="hidden" name="transaction_id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
