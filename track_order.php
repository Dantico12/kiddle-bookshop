<?php
// order-tracking.php - UPDATED FOR NEW DATABASE SCHEMA
session_start();
require_once 'config.php';

$order = null;
$orderItems = [];
$trackingHistory = [];
$error = '';

// Check if order number is provided
if (isset($_GET['order'])) {
    $orderNumber = trim($_GET['order']);
    
    try {
        $conn = getConnection();
        
        // Get order details with customer information
        $orderStmt = $conn->prepare("
            SELECT o.*, c.location, c.email, c.phone 
            FROM orders o 
            LEFT JOIN customers c ON o.customer_id = c.id 
            WHERE o.order_number = ?
        ");
        $orderStmt->bind_param("s", $orderNumber);
        $orderStmt->execute();
        $order = $orderStmt->get_result()->fetch_assoc();
        $orderStmt->close();
        
        if ($order) {
            // Get order items
            $itemsStmt = $conn->prepare("
                SELECT * FROM order_items 
                WHERE order_id = ? 
                ORDER BY id
            ");
            $itemsStmt->bind_param("i", $order['id']);
            $itemsStmt->execute();
            $orderItems = $itemsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $itemsStmt->close();
            
            // Get tracking history
            $historyStmt = $conn->prepare("
                SELECT * FROM order_tracking_history 
                WHERE order_id = ? 
                ORDER BY created_at DESC
            ");
            $historyStmt->bind_param("i", $order['id']);
            $historyStmt->execute();
            $trackingHistory = $historyStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $historyStmt->close();
        } else {
            $error = "Order not found. Please check your order number.";
        }
        
        $conn->close();
    } catch (Exception $e) {
        $error = "Error retrieving order details: " . $e->getMessage();
    }
}

// Function to get order status display
function getStatusDisplay($status) {
    $statuses = [
        'pending' => ['label' => 'Pending Payment', 'class' => 'status-pending'],
        'paid' => ['label' => 'Payment Received', 'class' => 'status-paid'],
        'delivered' => ['label' => 'Delivered', 'class' => 'status-delivered'],
        'cancelled' => ['label' => 'Cancelled', 'class' => 'status-cancelled']
    ];
    
    return $statuses[$status] ?? ['label' => 'Unknown', 'class' => 'status-unknown'];
}

// Function to get tracking status display
function getTrackingStatusDisplay($status) {
    $statuses = [
        'pending' => ['label' => 'Order Received', 'class' => 'status-pending', 'step' => 1],
        'processing' => ['label' => 'Processing', 'class' => 'status-processing', 'step' => 2],
        'shipped' => ['label' => 'Shipped', 'class' => 'status-shipped', 'step' => 3],
        'out_for_delivery' => ['label' => 'Out for Delivery', 'class' => 'status-out-for-delivery', 'step' => 4],
        'delivered' => ['label' => 'Delivered', 'class' => 'status-delivered', 'step' => 5],
        'cancelled' => ['label' => 'Cancelled', 'class' => 'status-cancelled', 'step' => 0]
    ];
    
    return $statuses[$status] ?? ['label' => 'Unknown', 'class' => 'status-unknown', 'step' => 0];
}

// Function to calculate progress percentage
function getProgressPercentage($trackingStatus) {
    $statusInfo = getTrackingStatusDisplay($trackingStatus);
    $currentStep = $statusInfo['step'];
    return ($currentStep / 5) * 100;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Tracking | Kiddle Bookstore</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Source+Sans+Pro:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        .tracking-container {
            max-width: 800px;
            margin: 0 auto;
            padding: var(--spacing-xl) 0;
        }
        
        .tracking-header {
            text-align: center;
            margin-bottom: var(--spacing-xl);
        }
        
        .tracking-card {
            background: white;
            border-radius: 12px;
            padding: var(--spacing-xl);
            margin-bottom: var(--spacing-lg);
            box-shadow: var(--shadow-md);
        }
        
        .progress-tracker {
            margin: var(--spacing-xl) 0;
        }
        
        .progress-bar {
            height: 8px;
            background: var(--light-gray);
            border-radius: 4px;
            margin-bottom: var(--spacing-md);
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: var(--primary-color);
            border-radius: 4px;
            transition: width 0.3s ease;
        }
        
        .progress-steps {
            display: flex;
            justify-content: space-between;
            position: relative;
        }
        
        .progress-step {
            text-align: center;
            flex: 1;
            position: relative;
        }
        
        .step-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--light-gray);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto var(--spacing-sm);
            font-weight: bold;
            color: var(--dark-gray);
            transition: all 0.3s ease;
        }
        
        .step-active .step-icon {
            background: var(--primary-color);
            color: white;
            transform: scale(1.1);
        }
        
        .step-completed .step-icon {
            background: var(--success-color);
            color: white;
        }
        
        .step-label {
            font-size: 0.875rem;
            color: var(--dark-gray);
            transition: all 0.3s ease;
        }
        
        .step-active .step-label {
            color: var(--primary-color);
            font-weight: 600;
        }
        
        .step-completed .step-label {
            color: var(--success-color);
        }
        
        .order-details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: var(--spacing-lg);
            margin-bottom: var(--spacing-xl);
        }
        
        .detail-group {
            margin-bottom: var(--spacing-lg);
        }
        
        .detail-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: var(--spacing-sm);
            padding-bottom: var(--spacing-sm);
            border-bottom: 1px solid var(--light-gray);
        }
        
        .detail-label {
            font-weight: 600;
            color: var(--dark-gray);
        }
        
        .detail-value {
            color: var(--text-color);
        }
        
        .order-items {
            margin-top: var(--spacing-lg);
        }
        
        .order-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: var(--spacing-md) 0;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .item-info {
            flex: 1;
        }
        
        .item-name {
            font-weight: 600;
            display: block;
        }
        
        .item-quantity {
            color: var(--dark-gray);
            font-size: 0.875rem;
        }
        
        .search-order {
            background: white;
            padding: var(--spacing-xl);
            border-radius: 12px;
            box-shadow: var(--shadow-md);
            text-align: center;
        }
        
        .search-form {
            max-width: 400px;
            margin: 0 auto;
        }
        
        .tracking-history {
            margin-top: var(--spacing-lg);
        }
        
        .history-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: var(--spacing-md);
            padding-bottom: var(--spacing-md);
            border-bottom: 1px solid var(--light-gray);
        }
        
        .history-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--light-gray);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: var(--spacing-md);
            flex-shrink: 0;
        }
        
        .history-content {
            flex: 1;
        }
        
        .history-status {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .history-message {
            color: var(--dark-gray);
            font-size: 0.875rem;
        }
        
        .history-date {
            color: var(--dark-gray);
            font-size: 0.75rem;
            margin-top: 0.25rem;
        }
        
        .status-processing {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .status-shipped {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        .status-out-for-delivery {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
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
                        <li><a href="order-tracking.php" class="nav-link active">Track Order</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <main>
        <section class="tracking-section">
            <div class="container">
                <div class="tracking-container">
                    <div class="tracking-header">
                        <h1>Track Your Order</h1>
                        <p>Enter your order number to check the status of your purchase</p>
                    </div>

                    <?php if ($error): ?>
                        <div class="error-message">
                            <p><?php echo htmlspecialchars($error); ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if (!$order): ?>
                        <!-- Order Search Form -->
                        <div class="search-order">
                            <form method="GET" class="search-form">
                                <div class="form-group">
                                    <label for="orderNumber" class="form-label">Order Number</label>
                                    <input type="text" id="orderNumber" name="order" class="form-control" 
                                           placeholder="e.g., ORD20251126192525667" required>
                                    <small class="form-text">You can find your order number in your confirmation email</small>
                                </div>
                                <button type="submit" class="btn btn-primary">Track Order</button>
                            </form>
                        </div>
                    <?php else: ?>
                        <!-- Order Tracking Details -->
                        <div class="tracking-card">
                            <h2>Order #<?php echo htmlspecialchars($order['order_number']); ?></h2>
                            
                            <!-- Progress Tracker -->
                            <div class="progress-tracker">
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo getProgressPercentage($order['tracking_status']); ?>%"></div>
                                </div>
                                
                                <div class="progress-steps">
                                    <?php
                                    $steps = [
                                        ['status' => 'pending', 'label' => 'Order Received', 'icon' => '📦'],
                                        ['status' => 'processing', 'label' => 'Processing', 'icon' => '⚙️'],
                                        ['status' => 'shipped', 'label' => 'Shipped', 'icon' => '🚚'],
                                        ['status' => 'out_for_delivery', 'label' => 'Out for Delivery', 'icon' => '📬'],
                                        ['status' => 'delivered', 'label' => 'Delivered', 'icon' => '✅']
                                    ];
                                    
                                    $currentStep = getTrackingStatusDisplay($order['tracking_status'])['step'];
                                    
                                    foreach ($steps as $index => $step) {
                                        $stepNumber = $index + 1;
                                        $stepClass = '';
                                        if ($stepNumber < $currentStep) {
                                            $stepClass = 'step-completed';
                                        } elseif ($stepNumber === $currentStep) {
                                            $stepClass = 'step-active';
                                        }
                                        ?>
                                        <div class="progress-step <?php echo $stepClass; ?>">
                                            <div class="step-icon"><?php echo $step['icon']; ?></div>
                                            <div class="step-label"><?php echo $step['label']; ?></div>
                                        </div>
                                        <?php
                                    }
                                    ?>
                                </div>
                            </div>
                            
                            <!-- Current Status -->
                            <div class="current-status">
                                <?php 
                                $trackingStatus = getTrackingStatusDisplay($order['tracking_status']);
                                $orderStatus = getStatusDisplay($order['status']);
                                ?>
                                <h3>Current Status: 
                                    <span class="status-badge <?php echo $trackingStatus['class']; ?>">
                                        <?php echo $trackingStatus['label']; ?>
                                    </span>
                                </h3>
                                <p>Payment Status: 
                                    <span class="status-badge <?php echo $orderStatus['class']; ?>">
                                        <?php echo $orderStatus['label']; ?>
                                    </span>
                                </p>
                                <p>Last updated: <?php echo date('F j, Y \a\t g:i A', strtotime($order['created_at'])); ?></p>
                                
                                <?php if ($order['tracking_notes']): ?>
                                <div class="tracking-notes">
                                    <p><strong>Note:</strong> <?php echo htmlspecialchars($order['tracking_notes']); ?></p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Order Details -->
                        <div class="tracking-card">
                            <h3>Order Details</h3>
                            <div class="order-details-grid">
                                <div class="detail-group">
                                    <h4>Customer Information</h4>
                                    <div class="detail-item">
                                        <span class="detail-label">Name:</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($order['customer_name']); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Email:</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($order['email']); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Phone:</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($order['phone']); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Delivery Location:</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($order['delivery_location']); ?></span>
                                    </div>
                                </div>
                                
                                <div class="detail-group">
                                    <h4>Order Information</h4>
                                    <div class="detail-item">
                                        <span class="detail-label">Order Date:</span>
                                        <span class="detail-value"><?php echo date('F j, Y', strtotime($order['created_at'])); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Order Time:</span>
                                        <span class="detail-value"><?php echo date('g:i A', strtotime($order['created_at'])); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Total Amount:</span>
                                        <span class="detail-value">Ksh <?php echo number_format($order['total_amount'], 2); ?></span>
                                    </div>
                                    <?php if ($order['estimated_delivery']): ?>
                                    <div class="detail-item">
                                        <span class="detail-label">Estimated Delivery:</span>
                                        <span class="detail-value"><?php echo date('F j, Y', strtotime($order['estimated_delivery'])); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($order['mpesa_receipt']): ?>
                                    <div class="detail-item">
                                        <span class="detail-label">M-Pesa Receipt:</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($order['mpesa_receipt']); ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Order Items -->
                            <div class="order-items">
                                <h4>Order Items</h4>
                                <?php foreach ($orderItems as $item): ?>
                                <div class="order-item">
                                    <div class="item-info">
                                        <span class="item-name"><?php echo htmlspecialchars($item['product_name']); ?></span>
                                        <span class="item-quantity">Quantity: <?php echo $item['quantity']; ?></span>
                                    </div>
                                    <span class="item-price">Ksh <?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
                                </div>
                                <?php endforeach; ?>
                                
                                <div class="order-total">
                                    <span class="total-label">Total Amount:</span>
                                    <span class="total-amount">Ksh <?php echo number_format($order['total_amount'], 2); ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Tracking History -->
                        <?php if (!empty($trackingHistory)): ?>
                        <div class="tracking-card">
                            <h3>Tracking History</h3>
                            <div class="tracking-history">
                                <?php foreach ($trackingHistory as $history): ?>
                                <div class="history-item">
                                    <div class="history-icon">
                                        <?php 
                                        $icons = [
                                            'pending' => '📦',
                                            'processing' => '⚙️',
                                            'shipped' => '🚚',
                                            'out_for_delivery' => '📬',
                                            'delivered' => '✅',
                                            'cancelled' => '❌'
                                        ];
                                        echo $icons[$history['status']] ?? '📋';
                                        ?>
                                    </div>
                                    <div class="history-content">
                                        <div class="history-status">
                                            <?php echo getTrackingStatusDisplay($history['status'])['label']; ?>
                                        </div>
                                        <?php if ($history['message']): ?>
                                        <div class="history-message">
                                            <?php echo htmlspecialchars($history['message']); ?>
                                        </div>
                                        <?php endif; ?>
                                        <div class="history-date">
                                            <?php echo date('F j, Y \a\t g:i A', strtotime($history['created_at'])); ?>
                                            <?php if ($history['created_by'] && $history['created_by'] !== 'system'): ?>
                                            • by <?php echo htmlspecialchars($history['created_by']); ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Support Information -->
                        <div class="tracking-card">
                            <h3>Need Help?</h3>
                            <p>If you have any questions about your order, please contact our customer support:</p>
                            <div class="support-contact">
                                <p>📧 Email: <strong>support@kiddlebookstore.com</strong></p>
                                <p>📞 Phone: <strong>+254 712 345 678</strong></p>
                                <p>🕒 Support Hours: Monday-Friday, 8:00 AM - 6:00 PM</p>
                            </div>
                        </div>
                        
                        <!-- Track Another Order -->
                        <div class="text-center">
                            <a href="order-tracking.php" class="btn btn-secondary">Track Another Order</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <div class="container">
            <p>&copy; 2023 Kiddle Bookstore. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>