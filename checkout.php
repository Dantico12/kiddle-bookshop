<?php
// checkout.php - COMPLETE FIXED VERSION WITH INTEGRATED PAYMENT CONFIRMATION
require_once 'config.php';
require_once 'services/MpesaService.php';

// Security headers
header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Error handling
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/secure_error.log');

// Secure session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', 1);

// Initialize security components
$securityHelper = new SecurityHelper();
$rateLimiter = new RateLimiter();

// Rate limiting by IP
$clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (!$rateLimiter->checkRateLimit("checkout_{$clientIP}", 15, 300)) {
    $errors[] = "Too many requests. Please try again in 5 minutes.";
    http_response_code(429);
    SecurityHelper::logSecurityEvent('RATE_LIMIT_CHECKOUT', "Checkout rate limit exceeded for IP: {$clientIP}");
}

// Generate CSRF token
$csrfToken = $securityHelper->generateCSRFToken();

$orderSuccess = false;
$orderProcessing = false;
$paymentPending = false;
$paymentConfirmed = false;
$orderNumber = '';
$errors = [];
$paymentMessage = '';
$checkoutRequestID = '';
$merchantRequestID = '';

// For display purposes only - these will be overridden by JavaScript
$cartItemsCount = 0;
$cartTotal = 0;
$cartSubtotal = 0;
$cartTax = 0;
$cartShipping = 100.00;

// Check if we're in payment confirmation mode
$paymentConfirmationMode = isset($_GET['confirm_payment']) && $_GET['confirm_payment'] == '1';
$paymentCheckMode = isset($_GET['check_payment']) && $_GET['check_payment'] == '1';

// Handle payment status check
if ($paymentCheckMode && isset($_SESSION['current_order'])) {
    $orderData = $_SESSION['current_order'];
    $mpesaService = new MpesaService();
    
    if (!empty($orderData['checkout_request_id'])) {
        $statusResult = $mpesaService->queryTransactionStatus($orderData['checkout_request_id']);
        
        if ($statusResult['success']) {
            if ($statusResult['resultCode'] == 0) {
                // Payment confirmed
                $paymentConfirmed = true;
                $orderSuccess = true;
                $paymentMessage = 'Payment confirmed successfully!';
                
                // Update order in database
                try {
                    $conn = getSecureConnection();
                    $updateStmt = $conn->prepare("
                        UPDATE orders 
                        SET status = 'paid', 
                            tracking_status = 'processing',
                            updated_at = NOW()
                        WHERE order_number = ?
                    ");
                    $updateStmt->bind_param("s", $orderData['order_number']);
                    $updateStmt->execute();
                    $updateStmt->close();
                    $conn->close();
                    
                    // Update session
                    $_SESSION['current_order']['status'] = 'paid';
                    
                    // Clear cart
                    echo '<script>localStorage.removeItem("kiddle_secure_cart_v3");</script>';
                    
                } catch (Exception $e) {
                    error_log("Payment confirmation update error: " . $e->getMessage());
                }
            } else {
                $paymentPending = true;
                $paymentMessage = 'Payment status: ' . $statusResult['resultDesc'];
            }
        }
    }
    
    // Return JSON for AJAX calls
    if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
        header('Content-Type: application/json');
        echo json_encode([
            'paymentConfirmed' => $paymentConfirmed,
            'message' => $paymentMessage,
            'orderNumber' => $orderData['order_number'] ?? ''
        ]);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token first
    $postedToken = $_POST['csrf_token'] ?? '';
    if (!$securityHelper->validateCSRFToken($postedToken)) {
        $errors[] = "Security token invalid or expired. Please refresh the page.";
        http_response_code(419);
        SecurityHelper::logSecurityEvent('CSRF_FAILURE', "CSRF token validation failed", null);
    }
    
    // Sanitize all inputs
    $fullName = $securityHelper->sanitizeInput(trim($_POST['fullName'] ?? ''));
    $email = $securityHelper->sanitizeInput(trim($_POST['email'] ?? ''));
    $phone = $securityHelper->sanitizeInput(trim($_POST['phone'] ?? ''));
    $location = $securityHelper->sanitizeInput(trim($_POST['location'] ?? ''));
    $finalTotal = floatval($_POST['finalTotal'] ?? 0);
    
    // Get cart items from JSON (sent by JavaScript)
    $cartItemsJson = $_POST['cartItems'] ?? '[]';
    $cartItems = json_decode($cartItemsJson, true) ?? [];
    
    // Enhanced validation
    if (!$securityHelper->validateName($fullName)) {
        $errors[] = "Valid full name is required (2-100 characters, letters and spaces only)";
    }
    
    if (!$securityHelper->validateEmail($email)) {
        $errors[] = "Valid email address is required";
    }
    
    // FIXED: More flexible phone validation
    $cleanPhone = preg_replace('/[^0-9+]/', '', $phone);
    if (!$securityHelper->validatePhone($phone)) {
        if (empty($cleanPhone)) {
            $errors[] = "Phone number is required";
        } else if (strlen($cleanPhone) < 9) {
            $errors[] = "Phone number is too short. Please enter a complete Kenyan phone number.";
        } else if (strlen($cleanPhone) > 13) {
            $errors[] = "Phone number is too long. Please check and try again.";
        } else {
            $errors[] = "Please enter a valid Kenyan phone number. Examples: 254712345678, 0712345678, 112554479, or 2541125554479";
        }
    }
    
    if (!$securityHelper->validateLocation($location)) {
        $errors[] = "Delivery location is required (max 255 characters)";
    }
    
    if ($finalTotal <= 0 || $finalTotal > 100000) {
        $errors[] = "Invalid order total";
    }
    
    if (empty($cartItems)) {
        $errors[] = "Your cart is empty";
    }

    // Check stock availability
    if (empty($errors)) {
        try {
            $conn = getSecureConnection();
            
            // Check stock for all items first with prepared statements
            $stockErrors = [];
            foreach ($cartItems as $item) {
                $productId = intval($item['id']);
                $quantity = intval($item['quantity']);
                $productName = $securityHelper->sanitizeInput($item['title']);
                
                // Check if product exists and has sufficient stock
                if ($item['type'] === 'book') {
                    $stockStmt = $conn->prepare("SELECT title, quantity FROM books WHERE id = ?");
                } else {
                    $stockStmt = $conn->prepare("SELECT name as title, quantity FROM stationery WHERE id = ?");
                }
                
                if ($stockStmt) {
                    $stockStmt->bind_param("i", $productId);
                    $stockStmt->execute();
                    $stockStmt->store_result();
                    $stockStmt->bind_result($dbTitle, $dbQuantity);
                    
                    if ($stockStmt->fetch()) {
                        if ($dbQuantity < $quantity) {
                            $stockErrors[] = "Insufficient stock for '{$productName}'. Available: {$dbQuantity}, Requested: {$quantity}";
                        }
                    } else {
                        $stockErrors[] = "Product '{$productName}' is no longer available";
                    }
                    
                    $stockStmt->close();
                }
            }
            
            if (!empty($stockErrors)) {
                $errors = array_merge($errors, $stockErrors);
                SecurityHelper::logSecurityEvent('STOCK_CHECK_FAILED', "Stock check failed for order", null);
            }
        } catch (Exception $e) {
            error_log("Stock check error: " . $e->getMessage());
            $errors[] = "Unable to verify stock availability. Please try again.";
        } finally {
            if (isset($conn)) {
                $conn->close();
            }
        }
    }

    // Process order if no errors
    if (empty($errors)) {
        try {
            $conn = getSecureConnection();
            
            // Begin transaction
            $conn->begin_transaction();

            // STEP 1: Create or find customer
            $customerStmt = $conn->prepare("
                INSERT INTO customers (full_name, email, phone, location, created_at) 
                VALUES (?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                full_name = VALUES(full_name), 
                location = VALUES(location),
                updated_at = NOW()
            ");
            
            // Use cleaned phone number for database
            $customerStmt->bind_param("ssss", $fullName, $email, $cleanPhone, $location);
            $customerStmt->execute();
            
            $customerId = $conn->insert_id;
            if ($customerId === 0) {
                // Customer already exists, get their ID
                $findCustomerStmt = $conn->prepare("SELECT id FROM customers WHERE email = ?");
                $findCustomerStmt->bind_param("s", $email);
                $findCustomerStmt->execute();
                $findCustomerStmt->bind_result($customerId);
                $findCustomerStmt->fetch();
                $findCustomerStmt->close();
            }
            
            $customerStmt->close();

            // STEP 2: Generate secure order number
            $orderNumber = 'ORD' . date('YmdHis') . random_int(100, 999);

            // STEP 3: Insert order with pending status
            $orderStmt = $conn->prepare("
                INSERT INTO orders (
                    order_number, customer_id, customer_name, customer_email, 
                    customer_phone, delivery_location, total_amount, status, 
                    tracking_status, ip_address, user_agent, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending_payment', 'pending', ?, ?, NOW())
            ");
            
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 255);
            
            $orderStmt->bind_param(
                "sissssdss", 
                $orderNumber, $customerId, $fullName, $email, 
                $cleanPhone, $location, $finalTotal, $ipAddress, $userAgent
            );
            
            $orderStmt->execute();
            $orderId = $conn->insert_id;
            $orderStmt->close();

            // STEP 4: Insert order items
            $itemStmt = $conn->prepare("
                INSERT INTO order_items (order_id, product_id, product_name, quantity, price)
                VALUES (?, ?, ?, ?, ?)
            ");

            foreach ($cartItems as $item) {
                $productId = intval($item['id']);
                $productName = $securityHelper->sanitizeInput($item['title']);
                $quantity = intval($item['quantity']);
                $price = floatval($item['price']);
                
                $itemStmt->bind_param("iisid", $orderId, $productId, $productName, $quantity, $price);
                $itemStmt->execute();
            }
            
            $itemStmt->close();

            // STEP 5: Initiate REAL M-Pesa payment
            $mpesaService = new MpesaService();
            
            // Validate M-Pesa configuration
            $configErrors = $mpesaService->validateConfiguration();
            if (!empty($configErrors)) {
                throw new Exception('M-Pesa configuration error: ' . implode(', ', $configErrors));
            }
            
            // Initiate STK Push
            $mpesaResult = $mpesaService->initiateSTKPush(
                $cleanPhone,
                $finalTotal,
                $orderNumber,
                'Kiddle Bookstore Purchase'
            );

            if ($mpesaResult['success']) {
                // Save checkout request ID and merchant request ID
                $checkoutRequestID = $mpesaResult['checkoutRequestID'];
                $merchantRequestID = $mpesaResult['merchantRequestID'];
                
                // Update order with M-Pesa request IDs
                $updateOrderStmt = $conn->prepare("
                    UPDATE orders 
                    SET mpesa_checkout_id = ?, 
                        mpesa_merchant_id = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $updateOrderStmt->bind_param("ssi", $checkoutRequestID, $merchantRequestID, $orderId);
                $updateOrderStmt->execute();
                $updateOrderStmt->close();
                
                $conn->commit();
                
                // Store order data in session
                $_SESSION['current_order'] = [
                    'order_id' => $orderId,
                    'order_number' => $orderNumber,
                    'customer_name' => $fullName,
                    'customer_email' => $email,
                    'customer_phone' => $cleanPhone,
                    'total_amount' => $finalTotal,
                    'checkout_request_id' => $checkoutRequestID,
                    'status' => 'pending_payment',
                    'mpesa_message' => $mpesaResult['customerMessage'],
                    'cart_items' => $cartItems
                ];
                
                $orderProcessing = true;
                $paymentPending = true;
                $paymentMessage = $mpesaResult['customerMessage'];
                
                SecurityHelper::logSecurityEvent('MPESA_STK_INITIATED', 
                    "STK Push initiated for order {$orderNumber}. Checkout ID: {$checkoutRequestID}", 
                    $customerId
                );
                
                // DON'T redirect - stay on same page but show payment confirmation UI
                
            } else {
                // M-Pesa failed
                $conn->rollback();
                $errors[] = $mpesaResult['message'];
                SecurityHelper::logSecurityEvent('MPESA_INITIATION_FAILED', 
                    "M-Pesa initiation failed for order {$orderNumber}: " . $mpesaResult['message'], 
                    $customerId
                );
            }
            
        } catch (Exception $e) {
            if (isset($conn)) {
                $conn->rollback();
            }
            error_log("Order processing error: " . $e->getMessage());
            $errors[] = "We encountered an issue processing your payment. Please try again.";
            SecurityHelper::logSecurityEvent('ORDER_FAILED', "Order processing failed: " . $e->getMessage(), null);
        } finally {
            if (isset($conn)) {
                $conn->close();
            }
        }
    }
}

// Check for success redirect (for completed payments)
if (isset($_GET['success']) && $_GET['success'] == 1 && isset($_GET['order'])) {
    $orderNumberFromURL = $securityHelper->sanitizeInput($_GET['order']);
    
    // Verify the order belongs to the current session
    if (isset($_SESSION['current_order']) && 
        $_SESSION['current_order']['order_number'] === $orderNumberFromURL &&
        $_SESSION['current_order']['status'] === 'paid') {
        
        $orderSuccess = true;
        $currentOrder = $_SESSION['current_order'];
        
        // Load order details for display
        try {
            $conn = getSecureConnection();
            $orderStmt = $conn->prepare("
                SELECT customer_name, customer_email, customer_phone, 
                       delivery_location, total_amount, mpesa_receipt, created_at
                FROM orders 
                WHERE order_number = ? AND status = 'paid'
            ");
            $orderStmt->bind_param("s", $orderNumberFromURL);
            $orderStmt->execute();
            $orderStmt->bind_result(
                $fullName, $email, $phone, $location, $finalTotal, $mpesaReceipt, $createdAt
            );
            
            if ($orderStmt->fetch()) {
                // Load order items from session or database
                if (isset($currentOrder['cart_items'])) {
                    $cartItems = $currentOrder['cart_items'];
                } else {
                    $itemsStmt = $conn->prepare("
                        SELECT product_name, quantity, price 
                        FROM order_items 
                        WHERE order_id = ?
                    ");
                    $itemsStmt->bind_param("i", $currentOrder['order_id']);
                    $itemsStmt->execute();
                    $itemsStmt->bind_result($productName, $quantity, $price);
                    
                    $cartItems = [];
                    while ($itemsStmt->fetch()) {
                        $cartItems[] = [
                            'title' => $productName,
                            'quantity' => $quantity,
                            'price' => $price
                        ];
                    }
                    $itemsStmt->close();
                }
            }
            $orderStmt->close();
            $conn->close();
            
        } catch (Exception $e) {
            error_log("Order retrieval error: " . $e->getMessage());
            $errors[] = "Unable to retrieve order details.";
            $orderSuccess = false;
        }
    }
}

// Pre-fill form values if available
$prefillName = $_POST['fullName'] ?? '';
$prefillEmail = $_POST['email'] ?? '';
$prefillPhone = $_POST['phone'] ?? '';
$prefillLocation = $_POST['location'] ?? '';

// Check if we should show Track Order in navigation
$showTrackOrder = isset($_SESSION['current_order']) && 
                  isset($_SESSION['current_order']['status']) && 
                  $_SESSION['current_order']['status'] === 'paid';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Checkout | Kiddle Bookstore</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Source+Sans+Pro:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        .payment-processing-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            z-index: 10000;
            justify-content: center;
            align-items: center;
        }
        
        .payment-processing-content {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            text-align: center;
            max-width: 500px;
            width: 90%;
        }
        
        .payment-spinner {
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
        
        .input-security-feedback {
            display: none;
            font-size: 0.875rem;
            margin-top: 0.25rem;
            padding: 0.5rem;
            border-radius: 4px;
        }
        
        .input-secure {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .input-insecure {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .form-control.valid {
            border-color: #28a745;
        }
        
        .form-control.invalid {
            border-color: #dc3545;
        }
        
        .checkout-loading {
            display: none;
            text-align: center;
            padding: 2rem;
        }
        
        .checkout-loading .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #4a7c59;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 1rem;
        }
        
        .phone-examples {
            font-size: 0.8rem;
            color: #666;
            margin-top: 0.25rem;
        }
        
        .phone-examples code {
            background: #f5f5f5;
            padding: 0.1rem 0.3rem;
            border-radius: 3px;
            font-size: 0.75rem;
        }
        
        .payment-confirmation-section {
            display: none;
            max-width: 600px;
            margin: 2rem auto;
            padding: 2rem;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .payment-status {
            margin: 2rem 0;
            padding: 1.5rem;
            border-radius: 8px;
        }
        
        .status-pending {
            background: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
        }
        
        .status-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        .status-failed {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        .payment-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        
        .mpesa-instructions {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin: 1.5rem 0;
            text-align: left;
        }
        
        .mpesa-instructions h4 {
            color: #4a7c59;
            margin-bottom: 0.5rem;
        }
        
        .countdown-timer {
            font-size: 1.5rem;
            font-weight: bold;
            color: #4a7c59;
            margin: 1rem 0;
            padding: 0.5rem;
            background: #f8f9fa;
            border-radius: 5px;
            display: inline-block;
            min-width: 80px;
        }
        
        .payment-details-card {
            background: white;
            border: 2px solid #4a7c59;
            border-radius: 8px;
            padding: 1.5rem;
            margin: 1.5rem 0;
            text-align: center;
        }
        
        .payment-details-card h3 {
            color: #4a7c59;
            margin-bottom: 1rem;
        }
        
        .payment-detail-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #eee;
        }
        
        .payment-detail-row:last-child {
            border-bottom: none;
        }
        
        .payment-detail-label {
            font-weight: bold;
            color: #333;
        }
        
        .payment-detail-value {
            color: #4a7c59;
            font-weight: bold;
        }
        
        .auto-check-message {
            font-size: 0.9rem;
            color: #666;
            margin-top: 1rem;
            padding: 0.5rem;
            background: #f8f9fa;
            border-radius: 5px;
        }
        
        #checkoutFormSection {
            transition: opacity 0.3s ease;
        }
        
        #checkoutFormSection.hidden {
            display: none;
        }
        
        #paymentConfirmationSection {
            display: none;
        }
        
        #paymentConfirmationSection.visible {
            display: block;
            animation: fadeIn 0.5s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
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
                
                <nav class="main-nav" aria-label="Main navigation">
                    <ul class="nav-list">
                        <li><a href="index.php" class="nav-link">Home</a></li>
                        <li><a href="bookshop.php" class="nav-link">Products</a></li>
                        <?php if ($showTrackOrder): ?>
                        <li><a href="track_order.php" class="nav-link track-order-link">Track Order</a></li>
                        <?php endif; ?>
                        <li class="cart-icon" style="position: relative;">
                            <a href="checkout.php" class="nav-link cart-link">
                                <span class="cart-icon-svg" aria-hidden="true">🛒</span>
                                <span class="cart-count" id="cartCount">0</span>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <main>
        <!-- Payment Processing Overlay -->
        <div class="payment-processing-overlay" id="paymentProcessingOverlay">
            <div class="payment-processing-content">
                <div class="payment-spinner"></div>
                <h3>Processing Payment</h3>
                <p>Please wait while we initiate M-Pesa payment request...</p>
                <p><small>This may take a few seconds</small></p>
            </div>
        </div>

        <section class="checkout-section">
            <div class="container">
                <!-- Security Notice -->
                <div class="security-notice">
                    <div class="security-indicator">
                        <span>🔒</span>
                        <span>Secure Checkout - Your information is protected</span>
                    </div>
                    <p>All data is encrypted and processed securely. We never store your payment details.</p>
                </div>

                <div class="checkout-header">
                    <h1>
                        <?php if ($orderSuccess): ?>
                            Order Confirmed! 🎉
                        <?php elseif ($paymentPending): ?>
                            Complete Your Payment
                        <?php else: ?>
                            Complete Your Order
                        <?php endif; ?>
                    </h1>
                    <p>
                        <?php if ($orderSuccess): ?>
                            Thank you for your purchase!
                        <?php elseif ($paymentPending): ?>
                            Complete M-Pesa payment to finalize your order
                        <?php else: ?>
                            Enter your details to complete your purchase
                        <?php endif; ?>
                    </p>
                </div>
                
                <?php if (!empty($errors)): ?>
                    <div class="error-message">
                        <h3>Please check the following:</h3>
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <!-- PAYMENT CONFIRMATION SECTION (Shows when payment is pending) -->
                <div id="paymentConfirmationSection" class="<?php echo $paymentPending ? 'visible' : ''; ?>">
                    <?php if ($paymentPending): ?>
                        <div class="payment-confirmation-section">
                            <div class="payment-status status-pending">
                                <div class="payment-icon">📱</div>
                                <h2>Awaiting Payment Confirmation</h2>
                                
                                <div class="payment-details-card">
                                    <h3>Payment Details</h3>
                                    <div class="payment-detail-row">
                                        <span class="payment-detail-label">Order Number:</span>
                                        <span class="payment-detail-value"><?php echo htmlspecialchars($orderNumber); ?></span>
                                    </div>
                                    <div class="payment-detail-row">
                                        <span class="payment-detail-label">Amount:</span>
                                        <span class="payment-detail-value">Ksh <?php echo number_format($finalTotal, 2); ?></span>
                                    </div>
                                    <div class="payment-detail-row">
                                        <span class="payment-detail-label">PayBill:</span>
                                        <span class="payment-detail-value">516600</span>
                                    </div>
                                    <div class="payment-detail-row">
                                        <span class="payment-detail-label">Account:</span>
                                        <span class="payment-detail-value">440441</span>
                                    </div>
                                </div>
                                
                                <div class="mpesa-instructions">
                                    <h4>Complete M-Pesa Payment:</h4>
                                    <ol style="margin-left: 1.5rem; margin-top: 0.5rem;">
                                        <li>Check your phone for M-Pesa prompt</li>
                                        <li>Enter your M-Pesa PIN when prompted</li>
                                        <li>Wait for payment confirmation</li>
                                        <li>This page will update automatically</li>
                                    </ol>
                                    
                                    <div style="margin-top: 1rem; padding: 0.5rem; background: #e9f7ef; border-radius: 5px;">
                                        <p><strong>Status:</strong> <?php echo htmlspecialchars($paymentMessage); ?></p>
                                    </div>
                                </div>
                                
                                <div class="payment-spinner" style="margin: 1rem auto;"></div>
                                <p>Checking payment status...</p>
                                
                                <div class="countdown-timer" id="countdownTimer">30</div>
                                <p class="auto-check-message">Auto-checking in <span id="countdownSeconds">30</span> seconds</p>
                                
                                <div class="action-buttons" style="margin-top: 2rem; display: flex; gap: 1rem; justify-content: center;">
                                    <button onclick="checkPaymentStatus()" class="btn btn-primary">Check Status Now</button>
                                    <button onclick="showCheckoutForm()" class="btn btn-secondary">Back to Details</button>
                                </div>
                            </div>
                            
                            <div class="help-text" style="margin-top: 2rem; font-size: 0.9rem; color: #666;">
                                <p>Having issues? Call our support: <strong>+254 112 554 479</strong></p>
                                <p>Or email: <strong>support@kiddlebookstore.com</strong></p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- ORDER SUCCESS SECTION -->
                <?php if ($orderSuccess): ?>
                    <!-- SUCCESS STATE -->
                    <div class="success-message">
                        <div class="success-header">
                            <div class="success-icon">✅</div>
                            <h2>Payment Confirmed Successfully!</h2>
                            <p class="order-reference">Order Number: <strong><?php echo htmlspecialchars($orderNumber); ?></strong></p>
                        </div>
                        
                        <div class="success-grid">
                            <!-- Order Status Card -->
                            <div class="success-card">
                                <h3>Order Details</h3>
                                <div class="detail-group">
                                    <div class="detail-item">
                                        <span class="detail-label">Order Number:</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($orderNumber); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Status:</span>
                                        <span class="status-badge status-success">Paid</span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Tracking Status:</span>
                                        <span class="status-badge status-pending">Processing</span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Order Date:</span>
                                        <span class="detail-value"><?php echo date('F j, Y \a\t g:i A', strtotime($createdAt)); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Delivery Location:</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($location); ?></span>
                                    </div>
                                </div>
                            </div>

                            <!-- M-Pesa Instructions Card -->
                            <div class="success-card mpesa-card">
                                <div class="mpesa-header">
                                    <div class="mpesa-icon">📱</div>
                                    <h3>Payment Information</h3>
                                </div>
                                <div class="mpesa-instructions">
                                    <div class="paybill-info">
                                        <p><strong>PayBill Number:</strong> <span class="paybill-number">516600</span></p>
                                        <p><strong>Account Number:</strong> <span class="account-number">440441</span></p>
                                    </div>
                                    <p><strong>Payment received for:</strong> <?php echo htmlspecialchars($phone); ?></p>
                                    <p>Your payment has been processed successfully via M-Pesa.</p>
                                    <div class="mpesa-steps">
                                        <div class="step">
                                            <span class="step-number">✓</span>
                                            <span class="step-text">M-Pesa prompt sent to your phone</span>
                                        </div>
                                        <div class="step">
                                            <span class="step-number">✓</span>
                                            <span class="step-text">Payment confirmed successfully</span>
                                        </div>
                                        <div class="step">
                                            <span class="step-number">✓</span>
                                            <span class="step-text">Order is now being processed</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Order Summary Card -->
                            <div class="success-card">
                                <h3>Order Summary</h3>
                                <div class="order-items">
                                    <?php foreach ($cartItems as $item): ?>
                                    <div class="order-item">
                                        <div class="item-info">
                                            <span class="item-name"><?php echo htmlspecialchars($item['title']); ?></span>
                                            <span class="item-quantity">Qty: <?php echo $item['quantity']; ?></span>
                                        </div>
                                        <span class="item-price">Ksh <?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="order-total">
                                    <span class="total-label">Total Amount:</span>
                                    <span class="total-amount">Ksh <?php echo number_format($finalTotal, 2); ?></span>
                                </div>
                            </div>

                            <!-- Customer Information Card -->
                            <div class="success-card">
                                <h3>Customer Information</h3>
                                <div class="detail-group">
                                    <div class="detail-item">
                                        <span class="detail-label">Name:</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($fullName); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Email:</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($email); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Phone:</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($phone); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Delivery Location:</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($location); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="success-actions">
                            <p class="support-note">Need help? Contact us at <strong>support@kiddlebookstore.com</strong> or call <strong>+254 112 554 479</strong></p>
                            <div class="action-buttons">
                                <a href="index.php" class="btn btn-primary">Continue Shopping</a>
                                <a href="track_order.php?order=<?php echo $orderNumber; ?>" class="btn btn-secondary">Track Your Order</a>
                            </div>
                        </div>
                    </div>
                
                <!-- CHECKOUT FORM SECTION (Shows when no payment is pending) -->
                <?php elseif (!$paymentPending && !$orderSuccess): ?>
                    <div id="checkoutFormSection">
                        <div id="checkoutLoading" class="checkout-loading">
                            <div class="spinner"></div>
                            <p>Loading your cart...</p>
                        </div>
                        
                        <div id="emptyCartState" class="empty-cart-state" style="display: none;">
                            <div class="empty-cart-message">
                                <h2>Your cart is empty</h2>
                                <p>Add some items to your cart before checking out</p>
                                <a href="index.php" class="btn btn-primary">Continue Shopping</a>
                            </div>
                        </div>

                        <div id="checkoutContainer" class="checkout-container" style="display: none;">
                            <!-- Order Summary Card -->
                            <div class="cart-summary">
                                <h2 class="section-title">Order Summary</h2>
                                <div class="checkout-summary">
                                    <div class="summary-item">
                                        <span>Items:</span>
                                        <span id="summaryItemsCount">0</span>
                                    </div>
                                    <div class="summary-item">
                                        <span>Subtotal:</span>
                                        <span id="summarySubtotal">Ksh 0.00</span>
                                    </div>
                                    <div class="summary-item">
                                        <span>Tax (8%):</span>
                                        <span id="summaryTax">Ksh 0.00</span>
                                    </div>
                                    <div class="summary-item">
                                        <span>Shipping:</span>
                                        <span>Ksh 100.00</span>
                                    </div>
                                    <div class="summary-item summary-total">
                                        <span>Total:</span>
                                        <span id="summaryTotal">Ksh 0.00</span>
                                    </div>
                                </div>
                                
                                <div class="cart-items-preview" id="cartItemsPreview">
                                    <!-- Cart items will be populated by JavaScript -->
                                </div>
                            </div>

                            <!-- Customer Details Card -->
                            <div class="checkout-form">
                                <h2 class="section-title">Customer Information</h2>
                                <form method="POST" id="checkoutForm" class="secure-form">
                                    <!-- CSRF Protection -->
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                    <input type="hidden" name="finalTotal" id="finalTotal" value="0">
                                    <input type="hidden" name="cartItems" id="cartItemsInput" value="[]">
                                    
                                    <div class="form-group">
                                        <label for="fullName">Full Name *</label>
                                        <input type="text" id="fullName" name="fullName" class="form-control" 
                                               maxlength="100" pattern="[A-Za-z\s\-']{2,100}" 
                                               title="Please enter a valid name (2-100 characters, letters and spaces only)"
                                               value="<?php echo htmlspecialchars($prefillName); ?>" 
                                               required>
                                        <div class="input-security-feedback input-secure" id="nameSecurity">
                                            ✓ Name format is secure
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="email">Email Address *</label>
                                        <input type="email" id="email" name="email" class="form-control" 
                                               maxlength="255"
                                               value="<?php echo htmlspecialchars($prefillEmail); ?>" 
                                               placeholder="your.email@example.com" required>
                                        <div class="input-security-feedback input-secure" id="emailSecurity">
                                            ✓ Email format is secure
                                        </div>
                                    </div>
                                    
                                    <!-- FIXED PHONE INPUT -->
                                    <div class="form-group">
                                        <label for="phone">Phone Number *</label>
                                        <input type="tel" id="phone" name="phone" class="form-control" 
                                               value="<?php echo htmlspecialchars($prefillPhone); ?>" 
                                               placeholder="254 112 554 479 or 0712 345 678" 
                                               title="Please enter a valid Kenyan phone number"
                                               required>
                                        <div class="phone-examples">
                                            Examples: 
                                            <code>254112554479</code>, 
                                            <code>0712345678</code>, 
                                            <code>112554479</code>, 
                                            <code>254 11 25 54 479</code>
                                        </div>
                                        <div class="input-security-feedback input-secure" id="phoneSecurity">
                                            ✓ Phone format is secure
                                        </div>
                                    </div>

                                    <!-- LOCATION FIELD -->
                                    <div class="form-group">
                                        <label for="location">Delivery Location *</label>
                                        <input type="text" id="location" name="location" class="form-control" 
                                               value="<?php echo htmlspecialchars($prefillLocation); ?>" 
                                               placeholder="Enter your delivery location (e.g., Nairobi CBD, Westlands, etc.)" 
                                               maxlength="255"
                                               required>
                                        <small class="location-note">Please provide your specific area for delivery</small>
                                        <div class="input-security-feedback input-secure" id="locationSecurity">
                                            ✓ Location format is secure
                                        </div>
                                    </div>
                                    
                                    <!-- Payment Information -->
                                    <div class="payment-info">
                                        <h3>Payment Method</h3>
                                        <div class="paybill-info">
                                            <p><strong>PayBill Number:</strong> <span class="paybill-number">516600</span></p>
                                            <p><strong>Account Number:</strong> <span class="account-number">440441</span></p>
                                        </div>
                                        <p class="payment-note">You will receive an M-Pesa prompt on your phone to complete payment after submitting this order.</p>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary" id="secureSubmitBtn">
                                        <span>Complete Purchase with M-Pesa</span>
                                        <span id="payAmount">Ksh 0.00</span>
                                    </button>

                                    <div class="checkout-loading" id="formLoading" style="display: none;">
                                        <div class="spinner"></div>
                                        <p>Processing your order securely...</p>
                                    </div>
                                    
                                    <div class="terms-notice">
                                        <small>By completing this purchase, you agree to our <a href="terms.php">Terms of Service</a> and <a href="privacy.php">Privacy Policy</a></small>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <div class="container">
            <p>&copy; 2023 Kiddle Bookstore. All rights reserved. | <a href="privacy.php">Privacy Policy</a> | <a href="terms.php">Terms of Service</a></p>
        </div>
    </footer>

    <!-- Include the JavaScript cart system -->
    <script src="script.js"></script>
    
    <!-- Payment Confirmation Script -->
    <script>
        let countdown = 30;
        let countdownInterval;
        let paymentCheckInterval;
        
        // Start countdown timer
        function startCountdown() {
            const timerElement = document.getElementById('countdownTimer');
            const secondsElement = document.getElementById('countdownSeconds');
            
            if (!timerElement || !secondsElement) return;
            
            countdown = 30;
            timerElement.textContent = countdown;
            secondsElement.textContent = countdown;
            
            clearInterval(countdownInterval);
            
            countdownInterval = setInterval(() => {
                countdown--;
                timerElement.textContent = countdown;
                secondsElement.textContent = countdown;
                
                if (countdown <= 0) {
                    clearInterval(countdownInterval);
                    checkPaymentStatus();
                }
            }, 1000);
        }
        
        // Check payment status via AJAX
        function checkPaymentStatus() {
            console.log('Checking payment status...');
            
            // Show loading
            const timerElement = document.getElementById('countdownTimer');
            if (timerElement) {
                timerElement.innerHTML = '<div class="payment-spinner" style="width: 20px; height: 20px; margin: 0 auto;"></div>';
            }
            
            // Make AJAX request
            fetch('checkout.php?check_payment=1&ajax=1', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                }
            })
            .then(response => response.json())
            .then(data => {
                console.log('Payment status response:', data);
                
                if (data.paymentConfirmed) {
                    // Payment confirmed - reload page to show success
                    window.location.href = 'checkout.php?success=1&order=' + encodeURIComponent(data.orderNumber);
                } else {
                    // Still pending - restart countdown
                    startCountdown();
                    
                    // Update status message if available
                    if (data.message) {
                        const statusElement = document.querySelector('.mpesa-instructions p:last-child');
                        if (statusElement) {
                            statusElement.innerHTML = '<strong>Status:</strong> ' + data.message;
                        }
                    }
                }
            })
            .catch(error => {
                console.error('Error checking payment status:', error);
                startCountdown(); // Restart countdown on error
            });
        }
        
        // Show checkout form (back button)
        function showCheckoutForm() {
            document.getElementById('paymentConfirmationSection').classList.remove('visible');
            document.getElementById('checkoutFormSection').classList.remove('hidden');
        }
        
        // Enhanced phone validation that accepts various formats
        function validateKenyanPhone(phone) {
            // Remove all spaces and special characters except +
            const cleanPhone = phone.replace(/[^0-9+]/g, '');
            
            // More flexible regex for Kenyan phone numbers
            const phoneRegex = /^(?:254|\+254|0)?(1\d{8,9}|7\d{8})$/;
            
            return phoneRegex.test(cleanPhone);
        }

        // Auto-format phone number as user types
        document.addEventListener('DOMContentLoaded', function() {
            const phoneInput = document.getElementById('phone');
            if (phoneInput) {
                phoneInput.addEventListener('input', function(e) {
                    let value = e.target.value.replace(/[^\d+]/g, '');
                    
                    // Auto-format based on length and prefix
                    if (value.startsWith('+254')) {
                        if (value.length > 4) value = value.substring(0, 4) + ' ' + value.substring(4);
                        if (value.length > 7) value = value.substring(0, 7) + ' ' + value.substring(7);
                        if (value.length > 10) value = value.substring(0, 10) + ' ' + value.substring(10);
                        if (value.length > 13) value = value.substring(0, 13) + ' ' + value.substring(13);
                    } else if (value.startsWith('254')) {
                        if (value.length > 3) value = value.substring(0, 3) + ' ' + value.substring(3);
                        if (value.length > 6) value = value.substring(0, 6) + ' ' + value.substring(6);
                        if (value.length > 9) value = value.substring(0, 9) + ' ' + value.substring(9);
                        if (value.length > 12) value = value.substring(0, 12) + ' ' + value.substring(12);
                    } else if (value.startsWith('0')) {
                        if (value.length > 4) value = value.substring(0, 4) + ' ' + value.substring(4);
                        if (value.length > 7) value = value.substring(0, 7) + ' ' + value.substring(7);
                        if (value.length > 10) value = value.substring(0, 10) + ' ' + value.substring(10);
                    } else if (value.startsWith('1')) {
                        if (value.length > 3) value = value.substring(0, 3) + ' ' + value.substring(3);
                        if (value.length > 6) value = value.substring(0, 6) + ' ' + value.substring(6);
                        if (value.length > 9) value = value.substring(0, 9) + ' ' + value.substring(9);
                    } else if (value.startsWith('7')) {
                        if (value.length > 3) value = value.substring(0, 3) + ' ' + value.substring(3);
                        if (value.length > 6) value = value.substring(0, 6) + ' ' + value.substring(6);
                    }
                    
                    e.target.value = value.trim();
                });
            }
            
            // Form submission handler
            const checkoutForm = document.getElementById('checkoutForm');
            if (checkoutForm) {
                checkoutForm.addEventListener('submit', function(e) {
                    // Show processing overlay
                    const overlay = document.getElementById('paymentProcessingOverlay');
                    if (overlay) {
                        overlay.style.display = 'flex';
                    }
                    
                    // The form will submit normally and reload the page
                    // Server-side code will handle M-Pesa and show payment confirmation
                });
            }
            
            // Start payment check if we're in payment confirmation mode
            <?php if ($paymentPending): ?>
                console.log('Payment pending, starting countdown...');
                startCountdown();
                
                // Also start periodic checks every 30 seconds
                paymentCheckInterval = setInterval(checkPaymentStatus, 30000);
            <?php endif; ?>
        });
        
        // Clean up intervals when leaving page
        window.addEventListener('beforeunload', function() {
            clearInterval(countdownInterval);
            clearInterval(paymentCheckInterval);
        });
    </script>
</body>
</html>