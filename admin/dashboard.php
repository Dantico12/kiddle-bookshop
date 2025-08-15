
<?php
require_once 'config/database.php';
checkAdminLogin();

// Get statistics
try {
    $conn = getConnection();
    
    // Count users
    $result = $conn->query("SELECT COUNT(*) as count FROM users");
    $userCount = $result->fetch_assoc()['count'];
    
    // Count books
    $result = $conn->query("SELECT COUNT(*) as count FROM books");
    $bookCount = $result->fetch_assoc()['count'];
    
    // Count transactions
    $result = $conn->query("SELECT COUNT(*) as count FROM transactions WHERE status = 'completed'");
    $transactionCount = $result->fetch_assoc()['count'];
    
    // Low stock books
    $result = $conn->query("SELECT COUNT(*) as count FROM books WHERE quantity <= reorder_level");
    $lowStockCount = $result->fetch_assoc()['count'];
    
    // Recent transactions
    $result = $conn->query("
        SELECT t.*, u.full_name, b.title 
        FROM transactions t 
        JOIN users u ON t.user_id = u.id 
        JOIN books b ON t.book_id = b.id 
        ORDER BY t.created_at DESC 
        LIMIT 5
    ");
    $recentTransactions = [];
    while ($row = $result->fetch_assoc()) {
        $recentTransactions[] = $row;
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
    <title>Admin Dashboard - NeoBooks</title>
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
                <h1 class="page-title">Dashboard Overview</h1>
                <div>
                    <span><?= date('F j, Y') ?></span>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="dashboard-stats">
                <div class="stat-card">
                    <div class="stat-icon users">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-number"><?= $userCount ?? 0 ?></div>
                    <div class="stat-label">Total Users</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon books">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="stat-number"><?= $bookCount ?? 0 ?></div>
                    <div class="stat-label">Books in Stock</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon transactions">
                        <i class="fas fa-credit-card"></i>
                    </div>
                    <div class="stat-number"><?= $transactionCount ?? 0 ?></div>
                    <div class="stat-label">Completed Orders</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon inventory">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-number"><?= $lowStockCount ?? 0 ?></div>
                    <div class="stat-label">Low Stock Items</div>
                </div>
            </div>

            <!-- Recent Transactions -->
            <div class="table-container">
                <div style="padding: 1.5rem; border-bottom: 1px solid #ecf0f1;">
                    <h3>Recent Transactions</h3>
                </div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Book</th>
                            <th>Amount</th>
                            <th>M-Pesa Ref</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($recentTransactions)): ?>
                            <?php foreach ($recentTransactions as $transaction): ?>
                                <tr>
                                    <td>#<?= $transaction['id'] ?></td>
                                    <td><?= htmlspecialchars($transaction['full_name']) ?></td>
                                    <td><?= htmlspecialchars($transaction['title']) ?></td>
                                    <td>Ksh <?= number_format($transaction['total_amount'], 2) ?></td>
                                    <td><?= htmlspecialchars($transaction['mpesa_reference'] ?? 'N/A') ?></td>
                                    <td>
                                        <span class="badge badge-<?= $transaction['status'] === 'completed' ? 'success' : ($transaction['status'] === 'pending' ? 'warning' : 'danger') ?>">
                                            <?= ucfirst($transaction['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= date('M j, Y', strtotime($transaction['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; color: #666;">No transactions found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>
