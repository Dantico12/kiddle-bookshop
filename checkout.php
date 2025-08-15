<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout | KiddleBookshop</title>
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
                <a href="index.php" class="nav-btn">
                    <i class="fas fa-home"></i> Home
                </a>
                <a href="bookshop.php" class="nav-btn">
                    <i class="fas fa-store"></i> Shop
                </a>
            </div>
            <div class="cart-icon" onclick="window.location.href='index.php?cart=true'">
                <i class="fas fa-shopping-cart"></i>
                <span class="cart-count" id="cart-count">0</span>
            </div>
        </div>
    </nav>

    <!-- Checkout Container -->
    <div class="checkout-container">
        <h1 class="checkout-title"><i class="fas fa-credit-card"></i> Checkout</h1>
        
        <div class="checkout-grid">
            <!-- Customer Information Form -->
            <div class="checkout-form">
                <h2 class="section-title">Customer Information</h2>
                <form id="checkout-form">
                    <div class="form-group">
                        <label for="full-name">Full Name</label>
                        <input type="text" id="full-name" name="full-name" required placeholder="Enter your full name">
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" required placeholder="Enter your email">
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone" required placeholder="Enter your M-Pesa phone number">
                    </div>
                    
                    <div class="form-group">
                        <label for="address">Shipping Address</label>
                        <textarea id="address" name="address" rows="4" required placeholder="Enter your full shipping address"></textarea>
                    </div>
                    
                    <button type="button" class="pay-mpesa-btn" onclick="payWithMpesa()">
                        <i class="fas fa-mobile-alt"></i> Pay with M-Pesa
                    </button>
                </form>
            </div>
            
            <!-- Order Summary -->
            <div class="order-summary">
                <h2 class="section-title">Order Summary</h2>
                <div class="order-items" id="order-items">
                    <!-- Cart items will be dynamically populated here -->
                </div>
                <div class="order-total">
                    <div class="total-row">
                        <span>Subtotal:</span>
                        <span id="subtotal">Ksh0.00</span>
                    </div>
                    <div class="total-row">
                        <span>Shipping:</span>
                        <span id="shipping">Ksh5.99</span>
                    </div>
                    <div class="total-row grand-total">
                        <span>Total:</span>
                        <span id="grand-total">Ksh0.00</span>
                    </div>
                </div>
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