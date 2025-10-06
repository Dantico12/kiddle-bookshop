<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Kiddle Bookstore | Your trusted store for books & stationery</title>
  <link rel="stylesheet" href="styles.css">
  <!-- Google Fonts - Optional -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Source+Sans+Pro:wght@300;400;600&display=swap" rel="stylesheet">
</head>
<body>
  <header class="site-header">
    <div class="container">
      <div class="header-content">
        <a href="index.php" class="logo">
          <img src="kiddle1.jpeg" alt="Kiddle Bookstore Logo" width="180" height="60">
        </a>
        <nav class="main-nav" aria-label="Main navigation">
          <ul class="nav-list">
            <li><a href="index.php" class="nav-link active">Home</a></li>
            <li><a href="bookshop.php" class="nav-link">Products</a></li>
            <li><a href="#contact" class="nav-link">Contact</a></li>
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
              <h3>Your Cart</h3>
              <button class="cart-preview-close" id="cartPreviewClose" aria-label="Close cart">×</button>
            </div>
            <div class="cart-preview-items" id="cartPreviewItems">
              <!-- Cart items will be populated by JavaScript -->
            </div>
            <div class="cart-preview-footer">
              <div class="cart-preview-total" id="cartPreviewTotal">Total: $0.00</div>
              <a href="checkout.php" class="cart-preview-checkout">Checkout</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </header>

  <main>
  <section class="hero">
    <div class="container">
      <h1>Your trusted store for books & stationery</h1>
    </div>
  </section>

  <!-- Featured Books Carousel -->
  <section class="carousel-section" id="featuredBooks">
    <div class="container">
      <h2>Featured Books</h2>
      <div class="carousel-container" role="region" aria-label="Featured Books carousel">
        <div class="carousel-track" id="booksCarousel">
          <!-- Cards will be populated by JavaScript -->
        </div>
      </div>
      <div class="carousel-controls">
        <a href="bookshop.php?tab=books" class="btn">View All Books</a>
      </div>
    </div>
  </section>

  <!-- Featured Stationery Carousel -->
  <section class="carousel-section" id="featuredStationery">
    <div class="container">
      <h2>Featured Stationery</h2>
      <div class="carousel-container" role="region" aria-label="Featured Stationery carousel">
        <div class="carousel-track" id="stationeryCarousel">
          <!-- Cards will be populated by JavaScript -->
        </div>
      </div>
      <div class="carousel-controls">
        <a href="bookshop.php?tab=stationery" class="btn">View All Stationery</a>
      </div>
    </div>
  </section>

  <section class="contact" id="contact">
    <div class="container">
      <h2>Contact Us</h2>
      <div class="contact-grid">
        <div class="contact-info">
          <h3>Visit Our Store</h3>
          <p>123 Bookworm Lane<br>Reading City, RC 12345</p>
          <h3>Hours</h3>
          <p>Monday-Friday: 9am-7pm<br>Saturday: 10am-6pm<br>Sunday: 11am-5pm</p>
        </div>
        <div class="contact-info">
          <h3>Get in Touch</h3>
          <p>Email: hello@kiddlebookstore.com<br>Phone: (555) 123-4567</p>
          <h3>Follow Us</h3>
          <p>Instagram: @kiddlebookstore<br>Twitter: @kiddlebooks</p>
        </div>
      </div>
    </div>
  </section>
</main>

  <footer class="site-footer">
    <div class="container">
      <p>&copy; 2023 Kiddle Bookstore. All rights reserved.</p>
    </div>
  </footer>

  <script src="script.js"></script>
</body>
</html>