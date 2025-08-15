<?php
// Include database configuration
require_once 'config.php';

// Get database connection
$conn = getConnection();

// Initialize variables
$featured_books = [];
$featured_stationery = [];
$error_message = '';

try {
    // Fetch featured books from database
    $books_sql = "SELECT id, title, author, price, image, 'books' as category,quantity, reorder_level, description, created_at FROM books WHERE quantity > 0 ORDER BY created_at DESC LIMIT 6";
    $stmt = $conn->prepare($books_sql);
    $stmt->execute();
    $books_result = $stmt->get_result();
    $featured_books = $books_result->fetch_all(MYSQLI_ASSOC);
    
    // Fetch featured stationery from database
    $stationery_sql = "SELECT id, name as title, '' as author, price, image, 'stationery' as category, 0 as quantity, reorder_level, description, created_at FROM stationery WHERE quantity > 0 ORDER BY created_at DESC LIMIT 6";
    $stmt = $conn->prepare($stationery_sql);
    $stmt->execute();
    $stationery_result = $stmt->get_result();
    $featured_stationery = $stationery_result->fetch_all(MYSQLI_ASSOC);
    
} catch (Exception $e) {
    $error_message = "Error fetching items: " . $e->getMessage();
    error_log($error_message);
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KiddleBookshop - Your One-Stop Shop for Books & Stationery</title>
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
            <button class="menu-toggle">☰</button>
            <div class="nav-links">
                <a href="index.php" class="nav-btn active">
                    <i class="fas fa-home"></i> Home
                </a>
                <a href="bookshop.php?category=books" class="nav-btn">
                    <i class="fas fa-book"></i> Books
                </a>
                <a href="bookshop.php?category=stationery" class="nav-btn">
                    <i class="fas fa-pen"></i> Stationery
                </a>
            </div>
            <div class="cart-icon" onclick="toggleCart()">
                <i class="fas fa-shopping-cart"></i>
                <span class="cart-count" id="cart-count">0</span>
            </div>
        </div>
    </nav>

    <!-- User View -->
    <div id="user-view">
        <!-- Intro Section -->
        <div class="intro-section">
            <div class="intro-content">
                <h1 class="intro-title">Welcome to KiddleBookshop</h1>
                <p class="intro-description">
                    Your one-stop destination for quality books and premium stationery items. 
                    Discover amazing stories, educational materials, and essential supplies for all your reading and writing needs.
                </p>
            </div>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- Featured Books Section -->
        <div class="featured-container">
            <div class="section-header">
                <h2 class="section-title"><i class="fas fa-book"></i> Featured Books</h2>
                <a href="bookshop.php?category=books" class="view-all-btn">View All Books</a>
            </div>
            <div class="featured-books" id="featured-books">
                <?php if (empty($featured_books)): ?>
                    <div class="no-items">
                        <i class="fas fa-book"></i>
                        <h3>No books available</h3>
                        <p>Check back soon for new arrivals!</p>
                    </div>
                <?php else: ?>
                    <!-- Original items -->
                    <?php foreach ($featured_books as $book): ?>
                        <?php
                        $image_src = $book['image'];
                        if (!filter_var($book['image'], FILTER_VALIDATE_URL)) {
                            $image_src = "admin/uploads/books/" . basename($book['image']);
                        }
                        ?>
                        <div class="item-card" data-category="books">
                            <div class="item-image-container">
                                <img src="<?php echo htmlspecialchars($image_src); ?>" 
                                     alt="<?php echo htmlspecialchars($book['title']); ?>" 
                                     class="item-image"
                                     onerror="this.onerror=null; this.src='placeholder.jpg';">
                                <div class="featured-badge">
                                    <i class="fas fa-star"></i> Featured
                                </div>
                            </div>
                            
                            <div class="item-content">
                                <h3 class="item-title"><?php echo htmlspecialchars($book['title']); ?></h3>
                                <p class="item-author"><?php echo htmlspecialchars($book['author']); ?></p>
                                
                                <div class="item-meta">
                                    <div class="item-price">Ksh<?php echo number_format($book['price'], 2); ?></div>
                                    <div class="item-category">
                                        <i class="fas fa-book"></i>
                                        Book
                                    </div>
                    </div>
                                
                                <?php if (!empty($book['description'])): ?>
                                    <p class="item-description"><?php echo htmlspecialchars(substr($book['description'], 0, 80)) . (strlen($book['description']) > 80 ? '...' : ''); ?></p>
                                <?php endif; ?>
                            
                                <button class="add-to-cart-btn" 
                                        data-id="<?php echo $book['id']; ?>"
                                        data-title="<?php echo htmlspecialchars($book['title']); ?>"
                                        data-price="<?php echo $book['price']; ?>"
                                        data-image="<?php echo htmlspecialchars($image_src); ?>"
                                        data-category="books">
                                    <i class="fas fa-cart-plus"></i> Add to Cart
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <!-- Duplicated items -->
                    <?php foreach ($featured_books as $book): ?>
                        <?php
                        $image_src = $book['image'];
                        if (!filter_var($book['image'], FILTER_VALIDATE_URL)) {
                            $image_src = "admin/uploads/books/" . basename($book['image']);
                        }
                        ?>
                        <div class="item-card" data-category="books">
                            <div class="item-image-container">
                                <img src="<?php echo htmlspecialchars($image_src); ?>" 
                                     alt="<?php echo htmlspecialchars($book['title']); ?>" 
                                     class="item-image"
                                     onerror="this.onerror=null; this.src='placeholder.jpg';">
                                <div class="featured-badge">
                                    <i class="fas fa-star"></i> Featured
                                </div>
                            </div>
                            
                            <div class="item-content">
                                <h3 class="item-title"><?php echo htmlspecialchars($book['title']); ?></h3>
                                <p class="item-author"><?php echo htmlspecialchars($book['author']); ?></p>
                                
                                <div class="item-meta">
                                    <div class="item-price">Ksh<?php echo number_format($book['price'], 2); ?></div>
                                    <div class="item-category">
                                        <i class="fas fa-book"></i>
                                        Book
                                    </div>
                                </div>
                                
                                <?php if (!empty($book['description'])): ?>
                                    <p class="item-description"><?php echo htmlspecialchars(substr($book['description'], 0, 80)) . (strlen($book['description']) > 80 ? '...' : ''); ?></p>
                                <?php endif; ?>
                                <button class="add-to-cart-btn" 
                                        data-id="<?php echo $book['id']; ?>"
                                        data-title="<?php echo htmlspecialchars($book['title']); ?>"
                                        data-price="<?php echo $book['price']; ?>"
                                        data-image="<?php echo htmlspecialchars($image_src); ?>"
                                        data-category="books">
                                    <i class="fas fa-cart-plus"></i> Add to Cart
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Featured Stationery Section -->
        <div class="featured-container">
            <div class="section-header">
                <h2 class="section-title"><i class="fas fa-pen"></i> Featured Stationery</h2>
                <a href="bookshop.php?category=stationery" class="view-all-btn">View All Stationery</a>
            </div>
            <div class="featured-books" id="featured-stationery">
                <?php if (empty($featured_stationery)): ?>
                    <div class="no-items">
                        <i class="fas fa-pen"></i>
                        <h3>No stationery available</h3>
                        <p>Check back soon for new arrivals!</p>
                    </div>
                <?php else: ?>
                    <!-- Original items -->
                    <?php foreach ($featured_stationery as $item): ?>
                        <?php
                        $image_src = $item['image'];
                        if (!filter_var($item['image'], FILTER_VALIDATE_URL)) {
                            $image_src = "admin/uploads/stationery/" . basename($item['image']);
                        }
                        ?>
                        <div class="item-card" data-category="stationery">
                            <div class="item-image-container">
                                <img src="<?php echo htmlspecialchars($image_src); ?>" 
                                     alt="<?php echo htmlspecialchars($item['title']); ?>" 
                                     class="item-image"
                                     onerror="this.onerror=null; this.src='placeholder.jpg';">
                                <div class="featured-badge">
                                    <i class="fas fa-star"></i> Featured
                                </div>
                            </div>
                            
                            <div class="item-content">
                                <h3 class="item-title"><?php echo htmlspecialchars($item['title']); ?></h3>
                                <p class="item-author">KiddleBookshop</p>
                                
                                <div class="item-meta">
                                    <div class="item-price">Ksh<?php echo number_format($item['price'], 2); ?></div>
                                </div>
                                
                                <?php if (!empty($item['description'])): ?>
                                    <p class="item-description"><?php echo htmlspecialchars(substr($item['description'], 0, 80)) . (strlen($item['description']) > 80 ? '...' : ''); ?></p>
                                <?php endif; ?>
                                
                                <button class="add-to-cart-btn" 
                                        data-id="<?php echo $item['id']; ?>"
                                        data-title="<?php echo htmlspecialchars($item['title']); ?>"
                                        data-price="<?php echo $item['price']; ?>"
                                        data-image="<?php echo htmlspecialchars($image_src); ?>"
                                        data-category="stationery">
                                    <i class="fas fa-cart-plus"></i> Add to Cart
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <!-- Duplicated items -->
                    <?php foreach ($featured_stationery as $item): ?>
                        <?php
                        $image_src = $item['image'];
                        if (!filter_var($item['image'], FILTER_VALIDATE_URL)) {
                            $image_src = "admin/uploads/stationery/" . basename($item['image']);
                        }
                        ?>
                        <div class="item-card" data-category="stationery">
                            <div class="item-image-container">
                                <img src="<?php echo htmlspecialchars($image_src); ?>" 
                                     alt="<?php echo htmlspecialchars($item['title']); ?>" 
                                     class="item-image"
                                     onerror="this.onerror=null; this.src='placeholder.jpg';">
                                <div class="featured-badge">
                                    <i class="fas fa-star"></i> Featured
                                </div>
                            </div>
                            
                            <div class="item-content">
                                <h3 class="item-title"><?php echo htmlspecialchars($item['title']); ?></h3>
                                <p class="item-author">KiddleBookshop</p>
                                
                                <div class="item-meta">
                                    <div class="item-price">Ksh<?php echo number_format($item['price'], 2); ?></div>
                                </div>
                                
                                <?php if (!empty($item['description'])): ?>
                                    <p class="item-description"><?php echo htmlspecialchars(substr($item['description'], 0, 80)) . (strlen($item['description']) > 80 ? '...' : ''); ?></p>
                                <?php endif; ?>
                                
                             
                                <button class="add-to-cart-btn" 
                                        data-id="<?php echo $item['id']; ?>"
                                        data-title="<?php echo htmlspecialchars($item['title']); ?>"
                                        data-price="<?php echo $item['price']; ?>"
                                        data-image="<?php echo htmlspecialchars($image_src); ?>"
                                        data-category="stationery">
                                    <i class="fas fa-cart-plus"></i> Add to Cart
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Contact Us Section -->
        <div class="contact-section">
            <div class="contact-content">
                <h2 class="contact-title">Contact Us</h2>
                <div class="contact-info">
                    <div class="contact-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <div>
                            <h3>Store Location</h3>
                            <p>123 Book Street, Reading District<br>Nairobi, Kenya</p>
                        </div>
                    </div>
                    <div class="contact-item">
                        <i class="fas fa-phone"></i>
                        <div>
                            <h3>Phone Number</h3>
                            <p>+254 700 123 456</p>
                        </div>
                    </div>
                    <div class="contact-item">
                        <i class="fas fa-envelope"></i>
                        <div>
                            <h3>Email Address</h3>
                            <p>info@kiddlebookshop.com</p>
                        </div>
                    </div>
                    <div class="contact-item">
                        <i class="fas fa-clock"></i>
                        <div>
                            <h3>Business Hours</h3>
                            <p>Mon - Sat: 8:00 AM - 8:00 PM<br>Sunday: 10:00 AM - 6:00 PM</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Cart Modal -->
    <div id="cart-modal" class="cart-modal" style="display: none;">
        <div class="cart-content">
            <div class="cart-header">
                <h3>Shopping Cart</h3>
                <button class="close-cart" onclick="toggleCart()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="cart-body">
                <div id="cart-items"></div>
            </div>
            <div class="cart-footer">
                <div id="cart-total" class="cart-total">Total: Ksh0.00</div>
                <button class="checkout-btn" onclick="redirectToCheckout()">
                    <i class="fas fa-credit-card"></i> Checkout
                </button>
            </div>
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