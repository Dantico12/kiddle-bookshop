<?php
require_once 'config.php';
// Get database connection
$conn = getConnection();
// Initialize variables
$items = [];
$search = isset($_GET['search']) ? $_GET['search'] : '';
$category = isset($_GET['category']) ? $_GET['category'] : 'all';

try {
    if ($category === 'books') {
        // Query only books table
        $sql = "SELECT id, title, author, price, image, 'books' as category, rating, quantity, reorder_level, description, created_at, 0 as featured FROM books WHERE 1=1";
        $params = [];
        $types = "";
        
        if (!empty($search)) {
            $sql .= " AND (title LIKE ? OR author LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $types .= "ss";
        }
        
        $sql .= " ORDER BY created_at DESC";
        
        $stmt = $conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $items = $result->fetch_all(MYSQLI_ASSOC);
        
    } elseif ($category === 'stationery') {
        // Query only stationery table
        $sql = "SELECT id, name as title, '' as author, price, image, 'stationery' as category, 0 as rating, quantity, reorder_level, description, created_at, 0 as featured FROM stationery WHERE 1=1";
        $params = [];
        $types = "";
        
        if (!empty($search)) {
            $sql .= " AND name LIKE ?";
            $params[] = "%$search%";
            $types .= "s";
        }
        
        $sql .= " ORDER BY created_at DESC";
        
        $stmt = $conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $items = $result->fetch_all(MYSQLI_ASSOC);
        
    } else {
        // Query both tables and combine results
        $books_sql = "SELECT id, title, author, price, image, 'books' as category, rating, quantity, reorder_level, description, created_at, 0 as featured FROM books";
        $stationery_sql = "SELECT id, name as title, '' as author, price, image, 'stationery' as category, 0 as rating, quantity, reorder_level, description, created_at, 0 as featured FROM stationery";
        
        $books_params = [];
        $stationery_params = [];
        $books_types = "";
        $stationery_types = "";
        
        if (!empty($search)) {
            $books_sql .= " WHERE (title LIKE ? OR author LIKE ?)";
            $books_params[] = "%$search%";
            $books_params[] = "%$search%";
            $books_types = "ss";
            
            $stationery_sql .= " WHERE name LIKE ?";
            $stationery_params[] = "%$search%";
            $stationery_types = "s";
        }
        
        $books_sql .= " ORDER BY created_at DESC";
        $stationery_sql .= " ORDER BY created_at DESC";
        
        // Execute books query
        $stmt = $conn->prepare($books_sql);
        if (!empty($books_params)) {
            $stmt->bind_param($books_types, ...$books_params);
        }
        $stmt->execute();
        $books_result = $stmt->get_result();
        $books = $books_result->fetch_all(MYSQLI_ASSOC);
        
        // Execute stationery query
        $stmt = $conn->prepare($stationery_sql);
        if (!empty($stationery_params)) {
            $stmt->bind_param($stationery_types, ...$stationery_params);
        }
        $stmt->execute();
        $stationery_result = $stmt->get_result();
        $stationery = $stationery_result->fetch_all(MYSQLI_ASSOC);
        
        // Combine and sort results
        $items = array_merge($books, $stationery);
        // Sort by created_at descending
        usort($items, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
    }
} catch (Exception $e) {
    $error_message = "Error fetching items: " . $e->getMessage();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KiddleBookshop - Books & Stationery</title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">
                <img src="kiddle.jpeg" alt="KiddleBookshop Logo" class="logo-image">
                <span class="logo-text">KiddleBookshop</span>
            </div>
            <div class="nav-links">
                <a href="index.php" class="nav-btn">
                    <i class="fas fa-home"></i> Home
                </a>
                <a href="bookshop.php" class="nav-btn active">
                    <i class="fas fa-store"></i> Shop
                </a>
                <div class="cart-icon" onclick="toggleCart()">
                    <i class="fas fa-shopping-cart"></i>
                    <span class="cart-count" id="cart-count">0</span>
                </div>
            </div>
        </div>
    </nav>
    <!-- Search and Filter Container -->
    <div class="search-container">
        <form method="GET" action="bookshop.php" class="search-form">
            <input type="text" class="search-box" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search books and stationery...">
            <div class="filter-buttons">
                <button type="submit" name="category" value="all" class="filter-btn <?php echo $category === 'all' ? 'active' : ''; ?>">
                    All Items
                </button>
                <button type="submit" name="category" value="books" class="filter-btn <?php echo $category === 'books' ? 'active' : ''; ?>">
                    <i class="fas fa-book"></i> Books
                </button>
                <button type="submit" name="category" value="stationery" class="filter-btn <?php echo $category === 'stationery' ? 'active' : ''; ?>">
                    <i class="fas fa-pen"></i> Stationery
                </button>
            </div>
            <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
        </form>
    </div>
    <!-- Items Container -->
    <div class="shop-container">
        <div class="shop-header">
            <h2 class="shop-title">
                <?php if ($category === 'books'): ?>
                    <i class="fas fa-book"></i> Books Collection
                <?php elseif ($category === 'stationery'): ?>
                    <i class="fas fa-pen"></i> Stationery Collection
                <?php else: ?>
                    <i class="fas fa-store"></i> All Products
                <?php endif; ?>
            </h2>
            <div class="results-count">
                <?php echo count($items); ?> item<?php echo count($items) !== 1 ? 's' : ''; ?> found
            </div>
        </div>
        <?php if (isset($error_message)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        <div class="items-grid" id="items-container">
            <?php if (empty($items)): ?>
                <div class="no-items">
                    <i class="fas fa-search"></i>
                    <h3>No items found</h3>
                    <p>Try adjusting your search terms or browse all categories.</p>
                </div>
            <?php else: ?>
                <?php foreach ($items as $item): ?>
                    <div class="item-card" data-category="<?php echo htmlspecialchars($item['category']); ?>">
                        <div class="item-image-container">
                            <img src="<?php echo htmlspecialchars($item['image']); ?>" 
                                 alt="<?php echo htmlspecialchars($item['title']); ?>" 
                                 class="item-image"
                                 onerror="this.src=''">
                            <?php if (isset($item['featured']) && $item['featured']): ?>
                                <div class="featured-badge">
                                    <i class="fas fa-star"></i> Featured
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="item-content">
                            <h3 class="item-title"><?php echo htmlspecialchars($item['title']); ?></h3>
                            <?php if (!empty($item['author'])): ?>
                                <p class="item-author"><?php echo htmlspecialchars($item['author']); ?></p>
                            <?php endif; ?>
                            
                            <div class="item-meta">
                                <div class="item-price">$<?php echo number_format($item['price'], 2); ?></div>
                                <div class="item-category">
                                    <i class="fas fa-<?php echo $item['category'] === 'books' ? 'book' : 'pen'; ?>"></i>
                                    <?php echo ucfirst($item['category']); ?>
                                </div>
                            </div>
                            
                            <?php if (isset($item['rating']) && $item['rating'] > 0): ?>
                                <div class="item-rating">
                                    <?php
                                    $rating = floatval($item['rating']);
                                    $fullStars = floor($rating);
                                    $halfStar = $rating - $fullStars >= 0.5;
                                    
                                    for ($i = 0; $i < $fullStars; $i++): ?>
                                        <i class="fas fa-star"></i>
                                    <?php endfor;
                                    
                                    if ($halfStar): ?>
                                        <i class="fas fa-star-half-alt"></i>
                                    <?php endif;
                                    
                                    $emptyStars = 5 - $fullStars - ($halfStar ? 1 : 0);
                                    for ($i = 0; $i < $emptyStars; $i++): ?>
                                        <i class="far fa-star"></i>
                                    <?php endfor; ?>
                                    <span class="rating-value"><?php echo number_format($rating, 1); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($item['description'])): ?>
                                <p class="item-description"><?php echo htmlspecialchars(substr($item['description'], 0, 100)) . (strlen($item['description']) > 100 ? '...' : ''); ?></p>
                            <?php endif; ?>
                            
                            <div class="item-stock">
                                <?php if ($item['quantity'] > 0): ?>
                                    <span class="in-stock"><i class="fas fa-check-circle"></i> In Stock (<?php echo $item['quantity']; ?>)</span>
                                <?php else: ?>
                                    <span class="out-of-stock"><i class="fas fa-times-circle"></i> Out of Stock</span>
                                <?php endif; ?>
                            </div>
                            
                            <button class="add-to-cart-btn <?php echo $item['quantity'] <= 0 ? 'disabled' : ''; ?>" 
                                    data-id="<?php echo $item['id']; ?>"
                                    data-title="<?php echo htmlspecialchars($item['title']); ?>"
                                    data-price="<?php echo $item['price']; ?>"
                                    data-image="<?php echo htmlspecialchars($item['image']); ?>"
                                    data-category="<?php echo $item['category']; ?>"
                                    <?php echo $item['quantity'] <= 0 ? 'disabled' : ''; ?>>
                                <i class="fas fa-cart-plus"></i> 
                                <?php echo $item['quantity'] <= 0 ? 'Out of Stock' : 'Add to Cart'; ?>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <!-- Cart Modal -->
    <div class="cart-modal" id="cart-modal">
        <div class="cart-content">
            <div class="cart-header">
                <h2 class="cart-title">Your Cart</h2>
                <button class="close-cart" onclick="toggleCart()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="cart-items">
                <!-- Cart items will be populated here -->
            </div>
            <div class="cart-total" id="cart-total">
                Total: $0.00
            </div>
            <button class="checkout-btn" onclick="redirectToCheckout()">
                <i class="fas fa-credit-card"></i> Checkout
            </button>
        </div>
    </div>
    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-logo">KiddleBookshop</div>
            <div class="footer-links">
                <a href="#" class="footer-link">About Us</a>
                <a href="#" class="footer-link">Contact</a>
                <a href="#" class="footer-link">Privacy Policy</a>
                <a href="#" class="footer-link">Terms of Service</a>
            </div>
            <p class="footer-text">© 2023 KiddleBookshop - Your Learning Companion</p>
        </div>
    </footer>
    <script src="shop.js"></script>
</body>
</html>