<?php
// Include database connection
require_once 'config/database.php';
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// Initialize variables to prevent undefined variable errors
$totalItems = 0;
$totalBooks = 0;
$totalStationery = 0;
$lowStockItems = [];
$outOfStockItems = [];
$totalValue = 0;
$allItems = [];

// Get all books and stationery
try {
    $conn = getConnection();
    
    // Get books
    $booksResult = $conn->query("SELECT id, title as name, author, price, category, quantity, reorder_level, description, image, rating, created_at, 'book' as item_type FROM books ORDER BY created_at DESC");
    $books = [];
    while ($row = $booksResult->fetch_assoc()) {
        $books[] = $row;
    }
    
    // Get stationery
    $stationeryResult = $conn->query("SELECT id, name, price, quantity, reorder_level, description, image, created_at, 'stationery' as item_type FROM stationery ORDER BY created_at DESC");
    $stationery = [];
    while ($row = $stationeryResult->fetch_assoc()) {
        $stationery[] = $row;
    }
    
    // Combine all items
    $allItems = array_merge($books, $stationery);
    
    // Sort by created_at DESC
    usort($allItems, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    
    // Calculate statistics
    $totalBooks = count($books);
    $totalStationery = count($stationery);
    $totalItems = $totalBooks + $totalStationery;
    
    $lowStockItems = array_filter($allItems, function($item) {
        return $item['quantity'] <= ($item['reorder_level'] ?? 10);
    });
    
    $outOfStockItems = array_filter($allItems, function($item) {
        return $item['quantity'] == 0;
    });
    
    $totalValue = array_sum(array_map(function($item) {
        return $item['price'] * $item['quantity'];
    }, $allItems));
    
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
    <title>Inventory Overview - KiddleBookshop Admin</title>
    <link rel="stylesheet" href="assets/css/admin-style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">

        <!-- Main Content -->
        <main class="main-content">
            <!-- Page Header -->
            <div class="page-header">
                <h1 class="page-title">Inventory Overview</h1>
                <div class="quick-actions">
                    <div class="search-box">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" id="searchInput" class="search-input" placeholder="Search items...">
                    </div>
                    <button onclick="window.location.href='book.php'" class="btn btn-success">
                        <i class="fas fa-plus"></i> Add Book
                    </button>
                    <button onclick="window.location.href='stationery.php'" class="btn btn-success">
                        <i class="fas fa-plus"></i> Add Stationery
                    </button>
                </div>
            </div>

            <!-- Inventory Statistics -->
            <div class="inventory-stats">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-boxes"></i>
                    </div>
                    <div class="stat-number"><?= $totalItems ?></div>
                    <div class="stat-label">Total Items</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="stat-number"><?= $totalBooks ?></div>
                    <div class="stat-label">Books</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-pen"></i>
                    </div>
                    <div class="stat-number"><?= $totalStationery ?></div>
                    <div class="stat-label">Stationery</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-number"><?= count($lowStockItems) ?></div>
                    <div class="stat-label">Low Stock</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stat-number"><?= count($outOfStockItems) ?></div>
                    <div class="stat-label">Out of Stock</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-number">Ksh<?= number_format($totalValue, 2) ?></div>
                    <div class="stat-label">Total Value</div>
                </div>
            </div>

            <!-- Filter Tabs -->
            <div class="filter-tabs">
                <button class="filter-btn active" onclick="filterItems('all')">All Items</button>
                <button class="filter-btn" onclick="filterItems('book')">Books Only</button>
                <button class="filter-btn" onclick="filterItems('stationery')">Stationery Only</button>
                <button class="filter-btn" onclick="filterItems('low-stock')">Low Stock</button>
                <button class="filter-btn" onclick="filterItems('out-of-stock')">Out of Stock</button>
            </div>

            <!-- Alerts -->
            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <!-- Inventory Table -->
            <div class="table-container">
                <div class="table-header">
                    <h3>All Inventory Items (<?= $totalItems ?>)</h3>
                </div>
                <table class="table" id="inventoryTable">
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Type</th>
                            <th>ID</th>
                            <th>Name/Title</th>
                            <th>Author</th>
                            <th>Price</th>
                            <th>Category</th>
                            <th>Rating</th>
                            <th>Quantity</th>
                            <th>Reorder Level</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($allItems)): ?>
                            <?php foreach ($allItems as $item): ?>
                                <tr class="item-row" data-type="<?= $item['item_type'] ?>" data-quantity="<?= $item['quantity'] ?>" data-reorder="<?= $item['reorder_level'] ?? 10 ?>">
                                    <td>
                                        <?php if (!empty($item['image']) && file_exists($item['image'])): ?>
                                            <img src="<?= htmlspecialchars($item['image']) ?>" alt="Item Image" class="item-image">
                                        <?php else: ?>
                                            <div class="no-image">No Image</div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="item-type-badge item-type-<?= $item['item_type'] ?>">
                                            <?= ucfirst($item['item_type']) ?>
                                        </span>
                                    </td>
                                    <td><?= $item['id'] ?></td>
                                    <td><?= htmlspecialchars($item['name']) ?></td>
                                    <td>
                                        <?php if ($item['item_type'] === 'book'): ?>
                                            <?= htmlspecialchars($item['author'] ?? 'N/A') ?>
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </td>
                                    <td>Ksh<?= number_format($item['price'], 2) ?></td>
                                    <td><?= htmlspecialchars($item['category'] ?? 'N/A') ?></td>
                                    <td>
                                        <?php if ($item['item_type'] === 'book' && isset($item['rating'])): ?>
                                            <div class="star-rating">
                                                <?php 
                                                $rating = $item['rating'] ?? 0;
                                                for ($i = 1; $i <= 5; $i++): 
                                                ?>
                                                    <span class="star"><?= $i <= $rating ? '★' : '☆' ?></span>
                                                <?php endfor; ?>
                                                <small>(<?= number_format($rating, 1) ?>)</small>
                                            </div>
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $item['quantity'] ?></td>
                                    <td><?= $item['reorder_level'] ?? 10 ?></td>
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
                                        <?php if ($item['item_type'] === 'book'): ?>
                                            <button onclick="window.location.href='books.php?edit=<?= $item['id'] ?>'" class="btn btn-sm btn-edit-book">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                        <?php else: ?>
                                            <button onclick="window.location.href='stationery.php?edit=<?= $item['id'] ?>'" class="btn btn-sm btn-edit-stationery">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                        <?php endif; ?>
                                        <button onclick="deleteItem('<?= $item['item_type'] ?>', <?= $item['id'] ?>)" class="btn btn-sm btn-danger">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="12" class="text-center text-muted-alt">No items found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <script>
        // Filter functionality
        function filterItems(filter) {
            const rows = document.querySelectorAll('.item-row');
            const buttons = document.querySelectorAll('.filter-btn');
            
            // Update active button
            buttons.forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            
            rows.forEach(row => {
                const type = row.dataset.type;
                const quantity = parseInt(row.dataset.quantity);
                const reorderLevel = parseInt(row.dataset.reorder);
                
                let show = false;
                
                switch(filter) {
                    case 'all':
                        show = true;
                        break;
                    case 'book':
                        show = type === 'book';
                        break;
                    case 'stationery':
                        show = type === 'stationery';
                        break;
                    case 'low-stock':
                        show = quantity <= reorderLevel && quantity > 0;
                        break;
                    case 'out-of-stock':
                        show = quantity === 0;
                        break;
                }
                
                row.style.display = show ? '' : 'none';
            });
        }

        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('.item-row');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });

        // Delete item function
        function deleteItem(type, id) {
            if (confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
                if (type === 'book') {
                    window.location.href = `books.php?delete=${id}`;
                } else {
                    window.location.href = `stationery.php?delete=${id}`;
                }
            }
        }

        // Auto-refresh every 5 minutes
        setInterval(function() {
            window.location.reload();
        }, 300000);
    </script>
</body>
</html>