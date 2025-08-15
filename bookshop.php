<?php
require_once 'config.php';
// Get database connection
$conn = getConnection();

// Initialize variables
$items = [];
$search = isset($_GET['search']) ? $_GET['search'] : '';
$category = isset($_GET['category']) ? $_GET['category'] : 'all';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$items_per_page = 30; // Fixed at 30 items per page
$total_items = 0;
$total_pages = 0;

try {
    if ($category === 'books') {
        // Count total books
        $count_sql = "SELECT COUNT(*) as total FROM books WHERE 1=1";
        $count_params = [];
        $count_types = "";
        
        if (!empty($search)) {
            $count_sql .= " AND (title LIKE ? OR author LIKE ?)";
            $count_params[] = "%$search%";
            $count_params[] = "%$search%";
            $count_types .= "ss";
        }
        
        $stmt = $conn->prepare($count_sql);
        if (!empty($count_params)) {
            $stmt->bind_param($count_types, ...$count_params);
        }
        $stmt->execute();
        $count_result = $stmt->get_result();
        $total_items = $count_result->fetch_assoc()['total'];
        $total_pages = ceil($total_items / $items_per_page);
        
        // Calculate offset
        $offset = ($page - 1) * $items_per_page;
        
        // Query books
        $sql = "SELECT id, title, author, price, image, 'books' as category, rating, quantity, reorder_level, description, created_at, 0 as featured FROM books WHERE 1=1";
        $params = [];
        $types = "";
        
        if (!empty($search)) {
            $sql .= " AND (title LIKE ? OR author LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $types .= "ss";
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $items_per_page;
        $params[] = $offset;
        $types .= "ii";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $items = $result->fetch_all(MYSQLI_ASSOC);
        
    } elseif ($category === 'stationery') {
        // Count total stationery
        $count_sql = "SELECT COUNT(*) as total FROM stationery WHERE 1=1";
        $count_params = [];
        $count_types = "";
        
        if (!empty($search)) {
            $count_sql .= " AND name LIKE ?";
            $count_params[] = "%$search%";
            $count_types .= "s";
        }
        
        $stmt = $conn->prepare($count_sql);
        if (!empty($count_params)) {
            $stmt->bind_param($count_types, ...$count_params);
        }
        $stmt->execute();
        $count_result = $stmt->get_result();
        $total_items = $count_result->fetch_assoc()['total'];
        $total_pages = ceil($total_items / $items_per_page);
        
        $offset = ($page - 1) * $items_per_page;
        
        // Query stationery
        $sql = "SELECT id, name as title, '' as author, price, image, 'stationery' as category, 0 as rating, quantity, reorder_level, description, created_at, 0 as featured FROM stationery WHERE 1=1";
        $params = [];
        $types = "";
        
        if (!empty($search)) {
            $sql .= " AND name LIKE ?";
            $params[] = "%$search%";
            $types .= "s";
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $items_per_page;
        $params[] = $offset;
        $types .= "ii";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $items = $result->fetch_all(MYSQLI_ASSOC);
        
    } else {
        // For 'all' category - handle combined pagination
        // First get counts
        $books_count_sql = "SELECT COUNT(*) as total FROM books";
        $stationery_count_sql = "SELECT COUNT(*) as total FROM stationery";
        
        $books_count_params = [];
        $stationery_count_params = [];
        $books_count_types = "";
        $stationery_count_types = "";
        
        if (!empty($search)) {
            $books_count_sql .= " WHERE (title LIKE ? OR author LIKE ?)";
            $books_count_params[] = "%$search%";
            $books_count_params[] = "%$search%";
            $books_count_types = "ss";
            
            $stationery_count_sql .= " WHERE name LIKE ?";
            $stationery_count_params[] = "%$search%";
            $stationery_count_types = "s";
        }
        
        // Count books
        $stmt = $conn->prepare($books_count_sql);
        if (!empty($books_count_params)) {
            $stmt->bind_param($books_count_types, ...$books_count_params);
        }
        $stmt->execute();
        $books_count = $stmt->get_result()->fetch_assoc()['total'];
        
        // Count stationery
        $stmt = $conn->prepare($stationery_count_sql);
        if (!empty($stationery_count_params)) {
            $stmt->bind_param($stationery_count_types, ...$stationery_count_params);
        }
        $stmt->execute();
        $stationery_count = $stmt->get_result()->fetch_assoc()['total'];
        
        $total_items = $books_count + $stationery_count;
        $total_pages = ceil($total_items / $items_per_page);
        
        // Calculate how many books and stationery items we need for this page
        $items_needed = $items_per_page;
        $books_offset = ($page - 1) * $items_per_page;
        $books_limit = min($books_count - $books_offset, $items_needed);
        $books_limit = max(0, $books_limit); // Ensure not negative
        
        $stationery_offset = 0;
        $stationery_limit = 0;
        
        if ($books_limit < $items_needed) {
            // We need some stationery items to fill the page
            $stationery_limit = $items_needed - $books_limit;
            $stationery_offset = max(0, ($page - 1) * $items_per_page - $books_count);
        }
        
        // Query books
        $books_sql = "SELECT id, title, author, price, image, 'books' as category, rating, quantity, reorder_level, description, created_at, 0 as featured FROM books";
        $books_params = [];
        $books_types = "";
        
        if (!empty($search)) {
            $books_sql .= " WHERE (title LIKE ? OR author LIKE ?)";
            $books_params[] = "%$search%";
            $books_params[] = "%$search%";
            $books_types = "ss";
        }
        
        $books_sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $books_params[] = $books_limit;
        $books_params[] = $books_offset;
        $books_types .= "ii";
        
        $stmt = $conn->prepare($books_sql);
        if (!empty($books_params)) {
            $stmt->bind_param($books_types, ...$books_params);
        }
        $stmt->execute();
        $books = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Query stationery if needed
        $stationery = [];
        if ($stationery_limit > 0) {
            $stationery_sql = "SELECT id, name as title, '' as author, price, image, 'stationery' as category, 0 as rating, quantity, reorder_level, description, created_at, 0 as featured FROM stationery";
            $stationery_params = [];
            $stationery_types = "";
            
            if (!empty($search)) {
                $stationery_sql .= " WHERE name LIKE ?";
                $stationery_params[] = "%$search%";
                $stationery_types = "s";
            }
            
            $stationery_sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
            $stationery_params[] = $stationery_limit;
            $stationery_params[] = $stationery_offset;
            $stationery_types .= "ii";
            
            $stmt = $conn->prepare($stationery_sql);
            if (!empty($stationery_params)) {
                $stmt->bind_param($stationery_types, ...$stationery_params);
            }
            $stmt->execute();
            $stationery = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }
        
        // Combine results
        $items = array_merge($books, $stationery);
    }
} catch (Exception $e) {
    $error_message = "Error fetching items: " . $e->getMessage();
}

$conn->close();

// Pagination helper function
function generatePaginationUrl($page, $category, $search) {
    $params = [
        'page' => $page,
        'category' => $category
    ];
    if (!empty($search)) {
        $params['search'] = $search;
    }
    return 'bookshop.php?' . http_build_query($params);
}
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
    <style>
        .pagination-container {
            margin: 2rem 0;
            padding: 1rem;
            text-align: center;
        }
        .pagination {
            display: inline-flex;
            gap: 0.5rem;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(15px);
            border: 2px solid rgba(255, 215, 0, 0.3);
            border-radius: 25px;
            padding: 1rem 1.5rem;
            box-shadow: 0 10px 30px rgba(255, 215, 0, 0.1);
        }
        .pagination-btn {
            background: rgba(255, 215, 0, 0.1);
            border: 1px solid rgba(255, 215, 0, 0.3);
            color: #FFD700;
            padding: 0.8rem 1.2rem;
            border-radius: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Rajdhani', sans-serif;
            font-weight: 600;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 45px;
            height: 45px;
        }
        .pagination-btn:hover {
            background: rgba(255, 215, 0, 0.2);
            box-shadow: 0 0 20px rgba(255, 215, 0, 0.4);
            transform: translateY(-2px);
            border-color: #FFD700;
        }
        .pagination-current {
            background: linear-gradient(45deg, #FFD700, #FFA500);
            color: #000;
            border-color: #FFD700;
            box-shadow: 0 0 25px rgba(255, 215, 0, 0.6);
            font-weight: 700;
        }
        .pagination-ellipsis {
            color: rgba(255, 215, 0, 0.6);
            padding: 0.8rem 0.5rem;
            font-weight: 600;
            font-size: 1.1rem;
        }
    </style>
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
            <div class="results-info">
                <div class="results-count">
                    <?php echo count($items); ?> item<?php echo count($items) !== 1 ? 's' : ''; ?> found
                </div>
                <?php if ($total_pages > 1): ?>
                <div class="page-info">
                    Page <?php echo $page; ?> of <?php echo $total_pages; ?> (<?php echo $total_items; ?> total items)
                </div>
                <?php endif; ?>
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
                    <?php
                    // Handle image paths
                    $image_src = $item['image'];
                    if (!filter_var($item['image'], FILTER_VALIDATE_URL)) {
                        // Local file - use appropriate directory based on category
                        if ($item['category'] === 'books') {
                            $image_src = "admin/uploads/books/" . basename($item['image']);
                        } else {
                            $image_src = "admin/uploads/stationery/" . basename($item['image']);
                        }
                    }
                    ?>
                    <div class="item-card" data-category="<?php echo htmlspecialchars($item['category']); ?>">
                        <div class="item-image-container">
                            <img src="<?php echo htmlspecialchars($image_src); ?>" 
                                 alt="<?php echo htmlspecialchars($item['title']); ?>" 
                                 class="item-image"
                                 onerror="this.onerror=null; this.src='placeholder.jpg';">
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
                                <div class="item-price">Ksh<?php echo number_format($item['price'], 2); ?></div>
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
                                    data-image="<?php echo htmlspecialchars($image_src); ?>"
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
        
        <!-- Pagination - Only show if we have more than one page -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination-container">
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="<?php echo generatePaginationUrl($page - 1, $category, $search); ?>" class="pagination-btn pagination-prev">
                        <i class="fas fa-chevron-left"></i> Previous
                    </a>
                <?php endif; ?>
                
                <?php
                // Show page numbers
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                if ($start_page > 1): ?>
                    <a href="<?php echo generatePaginationUrl(1, $category, $search); ?>" class="pagination-btn">1</a>
                    <?php if ($start_page > 2): ?>
                        <span class="pagination-ellipsis">...</span>
                    <?php endif; ?>
                <?php endif; ?>
                
                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="pagination-btn pagination-current"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="<?php echo generatePaginationUrl($i, $category, $search); ?>" class="pagination-btn"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($end_page < $total_pages): ?>
                    <?php if ($end_page < $total_pages - 1): ?>
                        <span class="pagination-ellipsis">...</span>
                    <?php endif; ?>
                    <a href="<?php echo generatePaginationUrl($total_pages, $category, $search); ?>" class="pagination-btn"><?php echo $total_pages; ?></a>
                <?php endif; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="<?php echo generatePaginationUrl($page + 1, $category, $search); ?>" class="pagination-btn pagination-next">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
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
                Total: Ksh0.00
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
    
    <script src="script.js"></script>
</body>
</html>