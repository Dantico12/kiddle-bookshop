<?php
// Enhanced session security with proper initialization
require_once 'config.php';

// Get database connection
$conn = getConnection();

// Check if user has pending orders (based on session or email)
$hasPendingOrders = false;
$latestOrderNumber = null;

if (isset($_SESSION['current_order'])) {
    $hasPendingOrders = true;
    $latestOrderNumber = $_SESSION['current_order']['order_number'];
} elseif (isset($_SESSION['customer_email'])) {
    // Check database for pending orders
    $email = $_SESSION['customer_email'];
    $pendingStmt = $conn->prepare("
        SELECT order_number 
        FROM orders 
        WHERE customer_email = ? 
        AND tracking_status IN ('pending', 'processing', 'shipped', 'out_for_delivery')
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $pendingStmt->bind_param("s", $email);
    $pendingStmt->execute();
    $result = $pendingStmt->get_result();
    
    if ($result->num_rows > 0) {
        $hasPendingOrders = true;
        $latestOrderNumber = $result->fetch_assoc()['order_number'];
    }
    $pendingStmt->close();
}

// Fetch featured books
$books_query = "SELECT * FROM books WHERE quantity > 0 ORDER BY created_at DESC LIMIT 8";
$books_result = $conn->query($books_query);
$books = [];
if ($books_result) {
    while ($book = $books_result->fetch_assoc()) {
        $book['image'] = getImagePath($book['image']);
        $book['category'] = 'books'; // Add category for consistency
        $books[] = $book;
    }
}

// Fetch featured stationery
$stationery_query = "SELECT * FROM stationery WHERE quantity > 0 ORDER BY created_at DESC LIMIT 8";
$stationery_result = $conn->query($stationery_query);
$stationery = [];
if ($stationery_result) {
    while ($item = $stationery_result->fetch_assoc()) {
        $item['image'] = getImagePath($item['image']);
        $item['category'] = 'stationery'; // Add category for consistency
        $item['title'] = $item['name']; // Standardize field name
        $stationery[] = $item;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Kiddle Bookstore | Your trusted store for books & stationery</title>
  <link rel="stylesheet" href="styles.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Source+Sans+Pro:wght@300;400;600&display=swap" rel="stylesheet">
  <style>
    .order-tracking-banner {
      background: linear-gradient(135deg, #4a7c59 0%, #3d6849 100%);
      color: white;
      padding: 1rem;
      text-align: center;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .order-tracking-content {
      max-width: 1200px;
      margin: 0 auto;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 1rem;
      flex-wrap: wrap;
    }

    .tracking-icon {
      font-size: 1.5rem;
      animation: pulse 2s infinite;
    }

    @keyframes pulse {
      0%, 100% { transform: scale(1); }
      50% { transform: scale(1.1); }
    }

    .tracking-text {
      font-size: 1rem;
      margin: 0;
    }

    .tracking-btn {
      background: white;
      color: #4a7c59;
      padding: 0.5rem 1.5rem;
      border-radius: 25px;
      text-decoration: none;
      font-weight: 600;
      transition: all 0.3s;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
    }

    .tracking-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    }

    @media (max-width: 768px) {
      .order-tracking-content {
        flex-direction: column;
      }
    }

    /* Enhanced Carousel Styles */
    .carousel-section {
      position: relative;
      overflow: hidden;
    }

    .carousel-track {
      display: flex;
      animation: scroll 40s linear infinite;
    }

    .carousel-track:hover {
      animation-play-state: paused;
    }

    @keyframes scroll {
      0% {
        transform: translateX(0);
      }
      100% {
        transform: translateX(calc(-50% - var(--spacing-md)));
      }
    }

    /* Loading States */
    .loading-state {
      text-align: center;
      padding: 3rem;
      color: #666;
    }

    .loading-spinner {
      border: 4px solid #f3f3f3;
      border-top: 4px solid #4a7c59;
      border-radius: 50%;
      width: 40px;
      height: 40px;
      animation: spin 1s linear infinite;
      margin: 0 auto 1rem;
    }

    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }
  </style>
</head>
<body>
  <header class="site-header">
    <div class="container">
      <div class="header-content">
        <a href="index.php" class="logo">
          <img src="kiddle1.jpeg" alt="Kiddle Bookstore Logo" width="180" height="60">
        </a>
        
        <!-- Enhanced Navigation with Secure Order Tracking -->
        <nav class="main-nav" aria-label="Main navigation">
          <ul class="nav-list">
            <li><a href="index.php" class="nav-link active">Home</a></li>
            <li><a href="bookshop.php" class="nav-link">Products</a></li>
            <li><a href="#contact" class="nav-link">Contact</a></li>
            <?php 
            // Secure order tracking check
            $showTrackOrder = false;
            if (isset($_SESSION['current_order'])) {
                $showTrackOrder = true;
            } elseif (isset($_SESSION['customer_email'])) {
                try {
                    $conn = getConnection();
                    $email = $_SESSION['customer_email'];
                    $checkStmt = $conn->prepare("
                        SELECT COUNT(*) as pending_count 
                        FROM orders o 
                        JOIN customers c ON o.customer_id = c.id 
                        WHERE c.email = ? AND o.status IN ('pending', 'paid') AND o.tracking_status != 'delivered'
                    ");
                    $checkStmt->bind_param("s", $email);
                    $checkStmt->execute();
                    $result = $checkStmt->get_result()->fetch_assoc();
                    $showTrackOrder = $result['pending_count'] > 0;
                    $checkStmt->close();
                    $conn->close();
                } catch (Exception $e) {
                    // Log error but don't break the page
                    error_log("Error checking pending orders: " . $e->getMessage());
                }
            }
            if ($showTrackOrder): ?>
            <li><a href="order-tracking.php" class="nav-link track-order-link">Track Order</a></li>
            <?php endif; ?>
            <li class="cart-icon">
                <a href="checkout.php" class="nav-link cart-link">
                    <span class="cart-icon-svg" aria-hidden="true">🛒</span>
                    <span class="cart-count" id="cartCount">0</span>
                </a>
            </li>
          </ul>
          <button class="hamburger" aria-label="Toggle navigation menu">
              <span></span>
              <span></span>
              <span></span>
          </button>
        </nav>
        
        <!-- Cart Preview Modal -->
        <div class="cart-preview" id="cartPreview">
          <div class="cart-preview-content">
            <div class="cart-preview-header">
              <h3>Your Shopping Cart</h3>
              <button class="cart-preview-close" id="cartPreviewClose" aria-label="Close cart">×</button>
            </div>
            <div class="cart-preview-items" id="cartPreviewItems">
              <div class="loading-state">
                <div class="loading-spinner"></div>
                <p>Loading cart...</p>
              </div>
            </div>
            <div class="cart-preview-footer">
              <div class="cart-preview-total" id="cartPreviewTotal">Total: Ksh 0.00</div>
              <a href="checkout.php" class="cart-preview-checkout">Proceed to Checkout</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </header>

  <?php if ($hasPendingOrders && $latestOrderNumber): ?>
  <!-- Order Tracking Banner - Only shows when customer has pending orders -->
  <div class="order-tracking-banner">
    <div class="order-tracking-content">
      <span class="tracking-icon">📦</span>
      <p class="tracking-text">You have an order in progress!</p>
      <a href="track_order.php?order=<?php echo htmlspecialchars($latestOrderNumber); ?>" class="tracking-btn">
        <span>Track Order</span>
        <span>→</span>
      </a>
    </div>
  </div>
  <?php endif; ?>

  <main>
    <!-- Enhanced Hero Section -->
    <section class="hero">
      <div class="container">
        <div class="hero-content">
          <h1>Your Trusted Store for Books & Stationery</h1>
          <p class="hero-subtitle">
            Discover a world of knowledge and creativity at Kiddle Bookstore. 
            From bestselling books to premium stationery, we have everything you need 
            to fuel your imagination and organize your thoughts.
          </p>
          <div class="hero-cta">
            <a href="bookshop.php?tab=books" class="btn">Explore Books</a>
            <a href="bookshop.php?tab=stationery" class="btn btn-secondary">Browse Stationery</a>
          </div>
        </div>
      </div>
    </section>

    <!-- Featured Books Carousel -->
    <section class="carousel-section" id="featuredBooks">
      <div class="container">
        <div class="carousel-header">
          <h2>Featured Books</h2>
          <div class="carousel-controls">
            <a href="bookshop.php?tab=books" class="btn">View All Books</a>
          </div>
        </div>
        <?php if (!empty($books)): ?>
        <div class="carousel-container" role="region" aria-label="Featured Books carousel">
          <div class="carousel-track" id="booksCarousel">
            <!-- Books will be populated by JavaScript -->
          </div>
        </div>
        <?php else: ?>
        <div class="loading-state">
          <p>No books available at the moment. Please check back later.</p>
        </div>
        <?php endif; ?>
      </div>
    </section>

    <!-- Featured Stationery Carousel -->
    <section class="carousel-section" id="featuredStationery">
      <div class="container">
        <div class="carousel-header">
          <h2>Featured Stationery</h2>
          <div class="carousel-controls">
            <a href="bookshop.php?tab=stationery" class="btn">View All Stationery</a>
          </div>
        </div>
        <?php if (!empty($stationery)): ?>
        <div class="carousel-container" role="region" aria-label="Featured Stationery carousel">
          <div class="carousel-track" id="stationeryCarousel">
            <!-- Stationery will be populated by JavaScript -->
          </div>
        </div>
        <?php else: ?>
        <div class="loading-state">
          <p>No stationery available at the moment. Please check back later.</p>
        </div>
        <?php endif; ?>
      </div>
    </section>

    <!-- Enhanced Contact Section -->
    <section class="contact" id="contact">
      <div class="container">
        <h2>Visit Us Today</h2>
        <div class="contact-grid">
          <div class="contact-info">
            <h3>📍 Our Location</h3>
            <p>123 Bookworm Lane<br>Reading City, RC 12345<br>Nairobi, Kenya</p>
            <h3>🕒 Store Hours</h3>
            <p>Monday-Friday: 8:00 AM - 8:00 PM<br>Saturday: 9:00 AM - 6:00 PM<br>Sunday: 10:00 AM - 5:00 PM</p>
          </div>
          <div class="contact-info">
            <h3>📞 Get in Touch</h3>
            <p>Email: hello@kiddlebookstore.com<br>Phone: +254 712 345 678<br>WhatsApp: +254 712 345 679</p>
            <h3>🌐 Follow Our Story</h3>
            <p>Instagram: @kiddlebookstore<br>Twitter: @kiddlebooks<br>Facebook: Kiddle Bookstore</p>
          </div>
        </div>
      </div>
    </section>
  </main>

  <footer class="site-footer">
    <div class="container">
      <p>&copy; <?php echo date('Y'); ?> Kiddle Bookstore. All rights reserved. | Building minds, one page at a time.</p>
    </div>
  </footer>

  <!-- Enhanced JavaScript Integration -->
  <script>
    // Pass product data to JavaScript for carousels with proper validation
    const HOME_BOOKS = <?php 
        echo !empty($books) ? json_encode($books, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_NUMERIC_CHECK) : '[]';
    ?>;
    
    const HOME_STATIONERY = <?php 
        echo !empty($stationery) ? json_encode($stationery, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_NUMERIC_CHECK) : '[]';
    ?>;

    // Debug information (only in development)
    if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
        console.log('Homepage data loaded:', {
            books: HOME_BOOKS.length,
            stationery: HOME_STATIONERY.length
        });
    }
  </script>
  
  <!-- Use the unified enhanced JavaScript -->
  <script src="script.js"></script>
  
  <!-- Initialize homepage features -->
  <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize carousels if data exists
        if (typeof initCarousel === 'function') {
            if (HOME_BOOKS && HOME_BOOKS.length > 0) {
                console.log('Initializing books carousel with', HOME_BOOKS.length, 'items');
                initCarousel('booksCarousel', HOME_BOOKS);
            } else {
                document.getElementById('booksCarousel').innerHTML = 
                    '<div class="loading-state"><p>No books available</p></div>';
            }
            
            if (HOME_STATIONERY && HOME_STATIONERY.length > 0) {
                console.log('Initializing stationery carousel with', HOME_STATIONERY.length, 'items');
                initCarousel('stationeryCarousel', HOME_STATIONERY);
            } else {
                document.getElementById('stationeryCarousel').innerHTML = 
                    '<div class="loading-state"><p>No stationery available</p></div>';
            }
        }
        
        // Ensure cart is properly initialized
        if (window.secureCartManager) {
            window.secureCartManager.updateUI();
        }
    });
  </script>
</body>
</html>