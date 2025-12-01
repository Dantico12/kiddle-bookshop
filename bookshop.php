<?php
// Enhanced session security with proper initialization
require_once 'config.php';

$conn = getConnection();

// Fetch all books
$books_query = "SELECT * FROM books WHERE quantity > 0 ORDER BY title";
$books_result = $conn->query($books_query);
$books = [];
if ($books_result) {
    while ($book = $books_result->fetch_assoc()) {
        $book['image'] = getImagePath($book['image']);
        $book['category'] = 'books'; // Add category for consistency
        $books[] = $book;
    }
}

// Fetch all stationery
$stationery_query = "SELECT * FROM stationery WHERE quantity > 0 ORDER BY name";
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
  <title>Products | Kiddle Bookstore - Books & Stationery</title>
  <link rel="stylesheet" href="styles.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Source+Sans+Pro:wght@300;400;600&display=swap" rel="stylesheet">
  <style>
    /* Enhanced Products Page Styles */
    .products-header {
      background: linear-gradient(135deg, var(--light-cream) 0%, #f0f4f8 100%);
      padding: var(--spacing-xl) 0;
      text-align: center;
    }

    .products-header .subtitle {
      font-size: 1.2rem;
      color: #666;
      margin-bottom: var(--spacing-lg);
      max-width: 600px;
      margin-left: auto;
      margin-right: auto;
    }

    .tabs {
      display: flex;
      justify-content: center;
      margin-bottom: var(--spacing-lg);
      gap: var(--spacing-sm);
      flex-wrap: wrap;
    }

    .tab {
      padding: 0.8rem 1.5rem;
      background: white;
      border-radius: var(--border-radius);
      cursor: pointer;
      font-weight: 600;
      transition: var(--transition);
      box-shadow: 0 4px 10px var(--shadow);
      border: 2px solid transparent;
    }

    .tab:hover, .tab.active {
      background: var(--gradient-primary);
      color: white;
      transform: translateY(-2px);
      box-shadow: 0 6px 15px var(--shadow-heavy);
      border-color: var(--accent);
    }

    .search-bar {
      max-width: 500px;
      margin: 0 auto;
      width: 100%;
    }

    .search-bar input {
      width: 100%;
      padding: 1rem 1.2rem;
      border: 2px solid var(--muted);
      border-radius: var(--border-radius);
      font-size: 1rem;
      transition: var(--transition);
      box-shadow: 0 4px 10px var(--shadow);
    }

    .search-bar input:focus {
      outline: none;
      border-color: var(--accent);
      box-shadow: 0 6px 15px var(--shadow-heavy);
    }

    .product-author {
      color: #666;
      font-size: 0.9rem;
      margin-bottom: var(--spacing-sm);
      font-style: italic;
    }

    .page-dots {
      padding: 0.5rem;
      color: #666;
    }

    /* Loading and Error States */
    .loading-products {
      text-align: center;
      padding: 3rem;
      color: #666;
    }

    .products-error {
      text-align: center;
      padding: 3rem;
      color: #e74c3c;
      background: #f8d7da;
      border-radius: var(--border-radius);
      margin: var(--spacing-lg) 0;
    }

    @media (max-width: 768px) {
      .tabs {
        flex-direction: column;
        align-items: center;
      }
      
      .tab {
        width: 100%;
        max-width: 250px;
        text-align: center;
      }
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
        
        <!-- Enhanced Navigation -->
        <nav class="main-nav" aria-label="Main navigation">
          <ul class="nav-list">
            <li><a href="index.php" class="nav-link">Home</a></li>
            <li><a href="bookshop.php" class="nav-link active">Products</a></li>
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

  <main>
    <section class="products-header">
      <div class="container">
        <div class="tabs">
          <button class="tab active" data-tab="all">All Products</button>
          <button class="tab" data-tab="books">Books</button>
          <button class="tab" data-tab="stationery">Stationery</button>
        </div>
        
        <div class="search-bar">
          <input type="text" id="searchInput" placeholder="Search products by name, author, or description...">
        </div>
      </div>
    </section>
    
    <section class="products-section">
      <div class="container">
        <div class="products-grid" id="productsGrid">
          <div class="loading-products">
            <div class="loading-spinner"></div>
            <p>Loading products...</p>
          </div>
        </div>
        
        <div class="pagination" id="pagination">
          <!-- Pagination will be populated by JavaScript -->
        </div>
      </div>
    </section>
  </main>

  <footer class="site-footer">
    <div class="container">
      <p>&copy; <?php echo date('Y'); ?> Kiddle Bookstore. All rights reserved. | Quality education materials for everyone</p>
    </div>
  </footer>

  <!-- Enhanced Product Data Integration -->
  <script>
    // Secure product data passing with proper validation
    const PRODUCTS = <?php 
        $all_products = [];
        
        // Add books to products array with standardized structure
        foreach ($books as $book) {
            $all_products[] = [
                'id' => (int)$book['id'],
                'title' => $book['title'],
                'category' => 'books',
                'price' => (float)$book['price'],
                'description' => $book['description'] ?: 'No description available',
                'image' => $book['image'],
                'author' => $book['author'] ?? 'Unknown Author',
                'rating' => (float)($book['rating'] ?? 0.0)
            ];
        }
        
        // Add stationery to products array with standardized structure
        foreach ($stationery as $item) {
            $all_products[] = [
                'id' => (int)$item['id'],
                'title' => $item['title'],
                'category' => 'stationery',
                'price' => (float)$item['price'],
                'description' => $item['description'] ?: 'No description available',
                'image' => $item['image'],
                'author' => 'Kiddle Store',
                'rating' => 4.0 // Default rating for stationery
            ];
        }
        
        echo json_encode($all_products, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_NUMERIC_CHECK);
    ?>;
    
    // Debug information (only in development)
    if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
        console.log('Products loaded successfully:', {
            total: PRODUCTS.length,
            books: PRODUCTS.filter(p => p.category === 'books').length,
            stationery: PRODUCTS.filter(p => p.category === 'stationery').length
        });
    }
  </script>
  
  <!-- Use the unified enhanced JavaScript -->
  <script src="script.js"></script>
  
  
</body>
</html>