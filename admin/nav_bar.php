<?php
// nav_bar.php
$current_page = $current_page ?? 'dashboard';
?>
<nav class="sidebar">
    <div class="sidebar-header">
        <h3><b> Kiddle</b> Admin</h3>
        <p>Welcome, <?= htmlspecialchars($_SESSION['admin_username'] ?? 'Admin') ?></p>
    </div>

    <!-- Mobile menu toggle button -->
    <button id="mobileToggle" class="mobile-toggle" style="display: none;">
        <i class="fas fa-bars"></i>
    </button>
    <ul class="sidebar-nav">
        <li class="nav-item">
            <a href="dashboard.php" class="nav-link <?= $current_page == 'dashboard' ? 'active' : '' ?>">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
        </li>
        <li class="nav-item">
            <a href="users.php" class="nav-link <?= $current_page == 'users' ? 'active' : '' ?>">
                <i class="fas fa-users-cog"></i> Admin Management
            </a>
        </li>
        <li class="nav-item">
            <a href="inventory.php" class="nav-link <?= $current_page == 'inventory' ? 'active' : '' ?>">
                <i class="fas fa-boxes"></i> Inventory
            </a>
        </li>
        <li class="nav-item">
            <a href="book.php" class="nav-link <?= $current_page == 'book' ? 'active' : '' ?>">
                <i class="fas fa-book"></i> Books
            </a>
        </li>
        <li class="nav-item">
            <a href="stationery.php" class="nav-link <?= $current_page == 'stationery' ? 'active' : '' ?>">
                <i class="fas fa-pencil-alt"></i> Stationery
            </a>
        </li>
        <li class="nav-item">
            <a href="orders.php" class="nav-link <?= $current_page == 'orders' ? 'active' : '' ?>">
                <i class="fas fa-shopping-bag"></i> Orders & Customers
            </a>
        </li>
        <li class="nav-item">
            <a href="transactions.php" class="nav-link <?= $current_page == 'transactions' ? 'active' : '' ?>">
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