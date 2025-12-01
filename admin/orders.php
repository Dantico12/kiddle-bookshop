<?php
// admin/customer_orders.php
require_once 'config/database.php';
checkAdminLogin();

$current_page = 'customer_orders';

// Get parameters from URL
$selected_customer_id = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : null;
$selected_order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : null;
$show_packaging = isset($_GET['packaging']) ? true : false;

// Fetch all data
$customers = [];
$customer_stats = [];
$customer_orders = [];
$order_details = [];
$order_items = [];
$packaging_items = [];

try {
    $conn = getConnection();
    
    // Fetch all customers with order statistics
    $customers_query = "
        SELECT 
            c.*,
            COUNT(o.id) as total_orders,
            SUM(o.total_amount) as total_spent,
            MAX(o.created_at) as last_order_date
        FROM customers c
        LEFT JOIN orders o ON c.id = o.customer_id
        GROUP BY c.id
        ORDER BY total_spent DESC, c.created_at DESC
    ";
    
    $customers_result = $conn->query($customers_query);
    if ($customers_result) {
        $customers = $customers_result->fetch_all(MYSQLI_ASSOC);
    }
    
    // If a specific customer is selected, fetch their orders
    if ($selected_customer_id) {
        $customer_orders_query = "
            SELECT o.* 
            FROM orders o 
            WHERE o.customer_id = ? 
            ORDER BY o.created_at DESC
        ";
        $stmt = $conn->prepare($customer_orders_query);
        $stmt->bind_param("i", $selected_customer_id);
        $stmt->execute();
        $customer_orders_result = $stmt->get_result();
        $customer_orders = $customer_orders_result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        // Get customer details
        $customer_stmt = $conn->prepare("SELECT * FROM customers WHERE id = ?");
        $customer_stmt->bind_param("i", $selected_customer_id);
        $customer_stmt->execute();
        $selected_customer = $customer_stmt->get_result()->fetch_assoc();
        $customer_stmt->close();
        
        // Fetch packaging items (all items from all orders for this customer)
        if ($show_packaging) {
            $packaging_query = "
                SELECT 
                    oi.product_name,
                    oi.quantity,
                    oi.price,
                    o.order_number,
                    o.delivery_location,
                    o.created_at as order_date,
                    CASE 
                        WHEN b.id IS NOT NULL THEN 'book'
                        WHEN s.id IS NOT NULL THEN 'stationery'
                        ELSE 'unknown'
                    END as product_type
                FROM order_items oi
                JOIN orders o ON oi.order_id = o.id
                LEFT JOIN books b ON oi.product_name = b.title
                LEFT JOIN stationery s ON oi.product_name = s.name
                WHERE o.customer_id = ?
                ORDER BY oi.product_name, o.created_at DESC
            ";
            $stmt = $conn->prepare($packaging_query);
            $stmt->bind_param("i", $selected_customer_id);
            $stmt->execute();
            $packaging_result = $stmt->get_result();
            $packaging_items = $packaging_result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }
    }
    
    // If a specific order is selected, fetch order details and items
    if ($selected_order_id) {
        $order_details_query = "
            SELECT o.*, c.full_name, c.email, c.phone, c.location 
            FROM orders o 
            JOIN customers c ON o.customer_id = c.id 
            WHERE o.id = ?
        ";
        $stmt = $conn->prepare($order_details_query);
        $stmt->bind_param("i", $selected_order_id);
        $stmt->execute();
        $order_details_result = $stmt->get_result();
        $order_details = $order_details_result->fetch_assoc();
        $stmt->close();
        
        // Fetch order items
        $order_items_query = "
            SELECT oi.* 
            FROM order_items oi 
            WHERE oi.order_id = ? 
            ORDER BY oi.id
        ";
        $stmt = $conn->prepare($order_items_query);
        $stmt->bind_param("i", $selected_order_id);
        $stmt->execute();
        $order_items_result = $stmt->get_result();
        $order_items = $order_items_result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
    
    $conn->close();
} catch (Exception $e) {
    $error = "Error fetching data: " . $e->getMessage();
}

// Function to get status badge class
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'delivered': return 'badge-success';
        case 'paid': return 'badge-success';
        case 'pending': return 'badge-warning';
        case 'cancelled': return 'badge-danger';
        default: return 'badge-warning';
    }
}

// Function to get tracking status badge class
function getTrackingStatusBadgeClass($status) {
    switch ($status) {
        case 'delivered': return 'badge-success';
        case 'out_for_delivery': return 'badge-success';
        case 'shipped': return 'badge-warning';
        case 'processing': return 'badge-warning';
        case 'pending': return 'badge-warning';
        case 'cancelled': return 'badge-danger';
        default: return 'badge-warning';
    }
}

include 'nav_bar.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Orders Analysis | Kiddle Bookstore Admin</title>
    <link rel="stylesheet" href="assets/css/admin-style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .customer-card {
            background: var(--bg-glass);
            backdrop-filter: blur(15px);
            border: 1px solid var(--border-glass);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        }
        
        .customer-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-glow);
            border-color: var(--accent-primary);
        }
        
        .customer-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .stat-item {
            text-align: center;
            padding: 0.8rem;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
        }
        
        .stat-value {
            font-size: 1.2rem;
            font-weight: 700;
            font-family: 'Orbitron', monospace;
            color: var(--accent-primary);
            display: block;
        }
        
        .stat-label {
            font-size: 0.8rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .orders-grid {
            display: grid;
            gap: 1rem;
            margin-top: 1.5rem;
        }
        
        .order-card {
            background: var(--bg-glass);
            backdrop-filter: blur(15px);
            border: 1px solid var(--border-glass);
            border-radius: 12px;
            padding: 1.2rem;
            transition: all 0.3s ease;
        }
        
        .order-card:hover {
            transform: translateX(3px);
            border-color: var(--accent-primary);
            box-shadow: 0 4px 15px rgba(0, 255, 231, 0.2);
        }
        
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .order-meta {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .detail-section {
            background: var(--bg-glass);
            backdrop-filter: blur(15px);
            border: 1px solid var(--border-glass);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .detail-section h4 {
            color: var(--accent-primary);
            margin-bottom: 1rem;
            font-family: 'Orbitron', monospace;
            border-bottom: 1px solid var(--border-glass);
            padding-bottom: 0.5rem;
        }
        
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .detail-item {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        .detail-label {
            color: var(--text-muted);
            font-weight: 500;
        }
        
        .detail-value {
            color: var(--text-primary);
            font-weight: 600;
        }
        
        .back-button {
            margin-bottom: 1.5rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--text-muted);
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        .customer-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .info-card {
            background: var(--bg-glass);
            backdrop-filter: blur(15px);
            border: 1px solid var(--border-glass);
            border-radius: 10px;
            padding: 1rem;
            text-align: center;
        }
        
        .info-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--accent-primary);
            font-family: 'Orbitron', monospace;
        }
        
        .info-label {
            font-size: 0.9rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .customer-action-buttons {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
            flex-wrap: wrap;
        }
        
        /* Packaging List Styles */
        .packaging-list {
            background: var(--bg-glass);
            backdrop-filter: blur(15px);
            border: 1px solid var(--border-glass);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .packaging-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .packaging-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .summary-card {
            background: rgba(0, 255, 231, 0.1);
            border: 1px solid var(--accent-primary);
            border-radius: 8px;
            padding: 1rem;
            text-align: center;
        }
        
        .summary-value {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--accent-primary);
            font-family: 'Orbitron', monospace;
        }
        
        .summary-label {
            font-size: 0.8rem;
            color: var(--text-muted);
            text-transform: uppercase;
        }
        
        .packaging-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        
        .packaging-table th {
            background: rgba(0, 255, 231, 0.2);
            color: var(--accent-primary);
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid var(--accent-primary);
        }
        
        .packaging-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--border-glass);
        }
        
        .packaging-table tr:hover {
            background: rgba(0, 255, 231, 0.05);
        }
        
        .product-type-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .badge-book {
            background: rgba(76, 175, 80, 0.2);
            color: #4caf50;
            border: 1px solid #4caf50;
        }
        
        .badge-stationery {
            background: rgba(33, 150, 243, 0.2);
            color: #2196f3;
            border: 1px solid #2196f3;
        }
        
        .badge-unknown {
            background: rgba(158, 158, 158, 0.2);
            color: #9e9e9e;
            border: 1px solid #9e9e9e;
        }
        
        .print-btn {
            background: var(--accent-primary);
            color: var(--bg-primary);
            border: none;
            padding: 0.8rem 1.5rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .print-btn:hover {
            background: #00e6cf;
            transform: translateY(-2px);
        }
        
        .packaging-btn {
            display: block;
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, var(--accent-primary), #00b3a7);
            color: white;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .packaging-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 255, 231, 0.4);
        }
        
        .view-customer-btn {
            background: var(--accent-primary);
            color: white;
            border: none;
            padding: 0.8rem 1.5rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .view-customer-btn:hover {
            background: #00e6cf;
            transform: translateY(-2px);
        }
        
        @media (max-width: 768px) {
            .customer-stats {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .order-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .order-meta {
                width: 100%;
                justify-content: space-between;
            }
            
            .action-buttons {
                width: 100%;
                justify-content: center;
            }
            
            .customer-action-buttons {
                width: 100%;
                justify-content: center;
            }
            
            .packaging-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .packaging-table {
                font-size: 0.9rem;
            }
            
            .packaging-table th,
            .packaging-table td {
                padding: 0.5rem;
            }
        }
        
        @media print {
            .sidebar, .page-header, .action-buttons, .back-button {
                display: none !important;
            }
            
            .packaging-list {
                box-shadow: none !important;
                border: 2px solid #000 !important;
                margin: 0 !important;
                padding: 1rem !important;
            }
            
            body {
                background: white !important;
                color: black !important;
                font-size: 12pt !important;
            }
            
            .packaging-table {
                font-size: 10pt !important;
            }
            
            .summary-card {
                border: 1px solid #000 !important;
                background: #f8f8f8 !important;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Main Content -->
        <div class="main-content">
            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h1 class="page-title">
                        <?php if ($show_packaging): ?>
                            <i class="fas fa-box"></i> Packaging List
                        <?php elseif ($selected_order_id): ?>
                            <i class="fas fa-receipt"></i> Order Details
                        <?php elseif ($selected_customer_id): ?>
                            <i class="fas fa-user"></i> Customer Orders
                        <?php else: ?>
                            <i class="fas fa-chart-bar"></i> Customer Orders Analysis
                        <?php endif; ?>
                    </h1>
                    <p class="text-muted">
                        <?php if ($show_packaging): ?>
                            Complete item list for <?php echo htmlspecialchars($selected_customer['full_name'] ?? ''); ?> - Ready for packaging and delivery
                        <?php elseif ($selected_order_id): ?>
                            Detailed view of order #<?php echo htmlspecialchars($order_details['order_number'] ?? ''); ?>
                        <?php elseif ($selected_customer_id): ?>
                            Order history for <?php echo htmlspecialchars($selected_customer['full_name'] ?? ''); ?>
                        <?php else: ?>
                            Analyze customer orders and purchasing patterns
                        <?php endif; ?>
                    </p>
                </div>
                
                <?php if ($selected_customer_id || $selected_order_id || $show_packaging): ?>
                <div class="back-button">
                    <a href="orders.php<?php 
                        if ($show_packaging) {
                            echo '?customer_id=' . $selected_customer_id;
                        } elseif ($selected_order_id) {
                            echo '?customer_id=' . $selected_customer_id;
                        }
                    ?>" class="btn btn-primary">
                        <i class="fas fa-arrow-left"></i>
                        Back to <?php 
                            if ($show_packaging) echo 'Customer Orders';
                            elseif ($selected_order_id) echo 'Customer Orders';
                            else echo 'Customers';
                        ?>
                    </a>
                </div>
                <?php endif; ?>
            </div>

            <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>

            <!-- Main Content Area -->
            <?php if ($show_packaging && $selected_customer_id): ?>
                <!-- Packaging List View -->
                <div class="packaging-list">
                    <div class="packaging-header">
                        <div>
                            <h3 style="color: var(--accent-primary); margin: 0;">
                                <i class="fas fa-box"></i> Complete Packaging List
                            </h3>
                            <p style="color: var(--text-muted); margin: 0.5rem 0 0 0;">
                                Customer: <strong><?php echo htmlspecialchars($selected_customer['full_name']); ?></strong> | 
                                Phone: <strong><?php echo htmlspecialchars($selected_customer['phone']); ?></strong> |
                                Delivery: <strong><?php echo htmlspecialchars($selected_customer['location'] ?? 'Not specified'); ?></strong>
                            </p>
                        </div>
                        <div class="action-buttons">
                            <button onclick="window.print()" class="print-btn">
                                <i class="fas fa-print"></i> Print List
                            </button>
                            <a href="orders.php?customer_id=<?php echo $selected_customer_id; ?>" class="btn btn-primary">
                                <i class="fas fa-list"></i> Back to Orders
                            </a>
                        </div>
                    </div>

                    <!-- Packaging Summary -->
                    <div class="packaging-summary">
                        <?php
                        $total_items = array_sum(array_column($packaging_items, 'quantity'));
                        $total_orders = count(array_unique(array_column($packaging_items, 'order_number')));
                        $unique_products = count(array_unique(array_column($packaging_items, 'product_name')));
                        $total_value = array_sum(array_map(function($item) {
                            return $item['quantity'] * $item['price'];
                        }, $packaging_items));
                        ?>
                        
                        <div class="summary-card">
                            <div class="summary-value"><?php echo $total_orders; ?></div>
                            <div class="summary-label">Total Orders</div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-value"><?php echo $total_items; ?></div>
                            <div class="summary-label">Total Items</div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-value"><?php echo $unique_products; ?></div>
                            <div class="summary-label">Unique Products</div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-value">Ksh <?php echo number_format($total_value, 2); ?></div>
                            <div class="summary-label">Total Value</div>
                        </div>
                    </div>

                    <!-- Packaging Items Table -->
                    <div class="table-container">
                        <table class="packaging-table">
                            <thead>
                                <tr>
                                    <th>Product Name</th>
                                    <th>Type</th>
                                    <th>Quantity</th>
                                    <th>Unit Price</th>
                                    <th>Total</th>
                                    <th>Order Numbers</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($packaging_items)): ?>
                                    <?php 
                                    $grouped_items = [];
                                    foreach ($packaging_items as $item) {
                                        $key = $item['product_name'] . '_' . $item['price'];
                                        if (!isset($grouped_items[$key])) {
                                            $grouped_items[$key] = $item;
                                            $grouped_items[$key]['order_numbers'] = [$item['order_number']];
                                            $grouped_items[$key]['total_quantity'] = $item['quantity'];
                                        } else {
                                            $grouped_items[$key]['total_quantity'] += $item['quantity'];
                                            if (!in_array($item['order_number'], $grouped_items[$key]['order_numbers'])) {
                                                $grouped_items[$key]['order_numbers'][] = $item['order_number'];
                                            }
                                        }
                                    }
                                    ?>
                                    
                                    <?php foreach ($grouped_items as $item): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($item['product_name']); ?></strong></td>
                                        <td>
                                            <span class="product-type-badge badge-<?php echo $item['product_type']; ?>">
                                                <?php echo ucfirst($item['product_type']); ?>
                                            </span>
                                        </td>
                                        <td><strong style="font-size: 1.1em;"><?php echo $item['total_quantity']; ?></strong></td>
                                        <td>Ksh <?php echo number_format($item['price'], 2); ?></td>
                                        <td><strong>Ksh <?php echo number_format($item['total_quantity'] * $item['price'], 2); ?></strong></td>
                                        <td>
                                            <?php 
                                            $unique_orders = array_unique($item['order_numbers']);
                                            echo implode(', ', array_slice($unique_orders, 0, 3));
                                            if (count($unique_orders) > 3) {
                                                echo '... (+' . (count($unique_orders) - 3) . ' more)';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="empty-state">
                                            <i class="fas fa-box-open"></i>
                                            <p>No items found for packaging.</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Delivery Information -->
                    <div class="detail-section" style="margin-top: 2rem;">
                        <h4><i class="fas fa-truck"></i> Delivery Information</h4>
                        <div class="detail-grid">
                            <div class="detail-item">
                                <span class="detail-label">Customer Name:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($selected_customer['full_name']); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Delivery Location:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($selected_customer['location'] ?? 'Not specified'); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Phone Number:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($selected_customer['phone']); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Email Address:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($selected_customer['email']); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Total Items to Pack:</span>
                                <span class="detail-value"><strong style="color: var(--accent-primary);"><?php echo $total_items; ?> items</strong></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Total Package Value:</span>
                                <span class="detail-value"><strong style="color: var(--accent-primary);">Ksh <?php echo number_format($total_value, 2); ?></strong></span>
                            </div>
                        </div>
                    </div>
                </div>

            <?php elseif ($selected_order_id): ?>
                <!-- Order Details View -->
                <div class="detail-section">
                    <h4><i class="fas fa-info-circle"></i> Order Information</h4>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <span class="detail-label">Order Number:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($order_details['order_number']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Order Date:</span>
                            <span class="detail-value"><?php echo date('F j, Y g:i A', strtotime($order_details['created_at'])); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Total Amount:</span>
                            <span class="detail-value">Ksh <?php echo number_format($order_details['total_amount'], 2); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Payment Status:</span>
                            <span class="badge <?php echo getStatusBadgeClass($order_details['status']); ?>">
                                <?php echo htmlspecialchars(ucfirst($order_details['status'])); ?>
                            </span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Tracking Status:</span>
                            <span class="badge <?php echo getTrackingStatusBadgeClass($order_details['tracking_status']); ?>">
                                <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $order_details['tracking_status']))); ?>
                            </span>
                        </div>
                        <?php if ($order_details['mpesa_receipt']): ?>
                        <div class="detail-item">
                            <span class="detail-label">M-Pesa Receipt:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($order_details['mpesa_receipt']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="detail-section">
                    <h4><i class="fas fa-user"></i> Customer Information</h4>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <span class="detail-label">Full Name:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($order_details['full_name']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Email:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($order_details['email']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Phone:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($order_details['phone']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Location:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($order_details['location'] ?? 'Not specified'); ?></span>
                        </div>
                    </div>
                </div>

                <div class="detail-section">
                    <h4><i class="fas fa-shopping-cart"></i> Order Items</h4>
                    <?php if (!empty($order_items)): ?>
                        <div class="table-container">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Product Name</th>
                                        <th>Quantity</th>
                                        <th>Price</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($order_items as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                        <td><?php echo $item['quantity']; ?></td>
                                        <td>Ksh <?php echo number_format($item['price'], 2); ?></td>
                                        <td>Ksh <?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <tr style="background: rgba(0, 255, 231, 0.1);">
                                        <td colspan="3" style="text-align: right; font-weight: bold;">Grand Total:</td>
                                        <td style="font-weight: bold;">Ksh <?php echo number_format($order_details['total_amount'], 2); ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-shopping-cart"></i>
                            <p>No items found for this order.</p>
                        </div>
                    <?php endif; ?>
                </div>

            <?php elseif ($selected_customer_id): ?>
                <!-- Customer Orders View -->
                <?php if (isset($selected_customer)): ?>
                <div class="customer-info">
                    <div class="info-card">
                        <div class="info-value"><?php echo $selected_customer['total_orders'] ?? 0; ?></div>
                        <div class="info-label">Total Orders</div>
                    </div>
                    <div class="info-card">
                        <div class="info-value">Ksh <?php echo number_format($selected_customer['total_spent'] ?? 0, 2); ?></div>
                        <div class="info-label">Total Spent</div>
                    </div>
                    <div class="info-card">
                        <div class="info-value">
                            <?php if ($selected_customer['last_order_date']): ?>
                                <?php echo date('M j, Y', strtotime($selected_customer['last_order_date'])); ?>
                            <?php else: ?>
                                Never
                            <?php endif; ?>
                        </div>
                        <div class="info-label">Last Order</div>
                    </div>
                    <div class="info-card">
                        <a href="orders.php?customer_id=<?php echo $selected_customer_id; ?>&packaging=1" class="packaging-btn">
                            <i class="fas fa-box"></i>
                            <div style="margin-top: 0.5rem; font-size: 0.9rem;">Packaging List</div>
                        </a>
                    </div>
                </div>

                <div class="detail-section">
                    <h4><i class="fas fa-user-circle"></i> Customer Details</h4>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <span class="detail-label">Full Name:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($selected_customer['full_name']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Email:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($selected_customer['email']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Phone:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($selected_customer['phone']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Location:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($selected_customer['location'] ?? 'Not specified'); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Member Since:</span>
                            <span class="detail-value"><?php echo date('F j, Y', strtotime($selected_customer['created_at'])); ?></span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="detail-section">
                    <h4><i class="fas fa-history"></i> Order History</h4>
                    <?php if (!empty($customer_orders)): ?>
                        <div class="orders-grid">
                            <?php foreach ($customer_orders as $order): ?>
                            <div class="order-card">
                                <div class="order-header">
                                    <div>
                                        <h5 style="color: var(--accent-primary); margin-bottom: 0.5rem;">
                                            <?php echo htmlspecialchars($order['order_number']); ?>
                                        </h5>
                                        <small style="color: var(--text-muted);">
                                            <?php echo date('F j, Y g:i A', strtotime($order['created_at'])); ?>
                                        </small>
                                    </div>
                                    <div class="order-meta">
                                        <span class="badge <?php echo getStatusBadgeClass($order['status']); ?>">
                                            <?php echo htmlspecialchars(ucfirst($order['status'])); ?>
                                        </span>
                                        <span class="badge <?php echo getTrackingStatusBadgeClass($order['tracking_status']); ?>">
                                            <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $order['tracking_status']))); ?>
                                        </span>
                                        <strong style="color: var(--accent-primary);">
                                            Ksh <?php echo number_format($order['total_amount'], 2); ?>
                                        </strong>
                                    </div>
                                </div>
                                
                                <div class="action-buttons">
                                    <a href="orders.php?customer_id=<?php echo $selected_customer_id; ?>&order_id=<?php echo $order['id']; ?>" 
                                       class="btn btn-primary btn-sm">
                                        <i class="fas fa-eye"></i> View Details
                                    </a>
                                    <a href="orders.php?edit=<?php echo $order['id']; ?>" 
                                       class="btn btn-success btn-sm">
                                        <i class="fas fa-edit"></i> Manage Order
                                    </a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-shopping-bag"></i>
                            <p>No orders found for this customer.</p>
                        </div>
                    <?php endif; ?>
                </div>

            <?php else: ?>
                <!-- Customers List View -->
                <div class="dashboard-stats">
                    <div class="stat-card">
                        <div class="stat-icon users">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-number"><?php echo count($customers); ?></div>
                        <div class="stat-label">Total Customers</div>
                    </div>
                    
                    <?php
                    $total_revenue = array_sum(array_column($customers, 'total_spent'));
                    $avg_order_value = count($customers) > 0 ? $total_revenue / count($customers) : 0;
                    $active_customers = array_filter($customers, function($customer) {
                        return ($customer['total_orders'] ?? 0) > 0;
                    });
                    ?>
                    
                    <div class="stat-card">
                        <div class="stat-icon transactions">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="stat-number">Ksh <?php echo number_format($total_revenue, 2); ?></div>
                        <div class="stat-label">Total Revenue</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon inventory">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <div class="stat-number">Ksh <?php echo number_format($avg_order_value, 2); ?></div>
                        <div class="stat-label">Avg. Customer Value</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon books">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <div class="stat-number"><?php echo count($active_customers); ?></div>
                        <div class="stat-label">Active Customers</div>
                    </div>
                </div>

                <div class="table-container">
                    <h3 style="padding: 1.5rem 2rem; margin: 0; color: var(--accent-primary); font-family: 'Orbitron', monospace; border-bottom: 1px solid var(--border-glass); background: rgba(0, 255, 231, 0.05);">
                        <i class="fas fa-users"></i> All Customers
                    </h3>
                    
                    <?php if (!empty($customers)): ?>
                        <div style="padding: 1.5rem;">
                            <div class="orders-grid">
                                <?php foreach ($customers as $customer): ?>
                                <div class="customer-card">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h5 style="color: var(--accent-primary); margin-bottom: 0.5rem;">
                                                <?php echo htmlspecialchars($customer['full_name']); ?>
                                            </h5>
                                            <p style="color: var(--text-muted); margin-bottom: 0.5rem;">
                                                <?php echo htmlspecialchars($customer['email']); ?>
                                            </p>
                                            <p style="color: var(--text-muted); font-size: 0.9rem;">
                                                <?php echo htmlspecialchars($customer['phone']); ?>
                                                <?php if ($customer['location']): ?>
                                                 • <?php echo htmlspecialchars($customer['location']); ?>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                        <div style="text-align: right;">
                                            <span class="badge <?php echo ($customer['total_orders'] ?? 0) > 0 ? 'badge-success' : 'badge-warning'; ?>">
                                                <?php echo ($customer['total_orders'] ?? 0) > 0 ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="customer-stats">
                                        <div class="stat-item">
                                            <span class="stat-value"><?php echo $customer['total_orders'] ?? 0; ?></span>
                                            <span class="stat-label">Orders</span>
                                        </div>
                                        <div class="stat-item">
                                            <span class="stat-value">Ksh <?php echo number_format($customer['total_spent'] ?? 0, 2); ?></span>
                                            <span class="stat-label">Spent</span>
                                        </div>
                                        <div class="stat-item">
                                            <span class="stat-value">
                                                <?php if ($customer['last_order_date']): ?>
                                                    <?php echo date('M j', strtotime($customer['last_order_date'])); ?>
                                                <?php else: ?>
                                                    Never
                                                <?php endif; ?>
                                            </span>
                                            <span class="stat-label">Last Order</span>
                                        </div>
                                    </div>
                                    
                                    <!-- BUTTONS instead of clickable card -->
                                    <div class="customer-action-buttons">
                                        <a href="orders.php?customer_id=<?php echo $customer['id']; ?>" class="view-customer-btn">
                                            <i class="fas fa-eye"></i> View Orders
                                        </a>
                                        <a href="orders.php?customer_id=<?php echo $customer['id']; ?>&packaging=1" class="btn btn-success">
                                            <i class="fas fa-box"></i> Packaging List
                                        </a>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="empty-state" style="padding: 3rem;">
                            <i class="fas fa-users"></i>
                            <p>No customers found.</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="assets/js/admin.js"></script>
    <script>
        // Safe event listeners
        document.addEventListener('DOMContentLoaded', function() {
            const mobileToggle = document.getElementById('mobileToggle');
            const sidebar = document.querySelector('.sidebar');
            const navLinks = document.querySelectorAll('.nav-link');

            if (mobileToggle && sidebar) {
                mobileToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('show');
                });
            }

            if (navLinks.length > 0 && sidebar) {
                navLinks.forEach(link => {
                    link.addEventListener('click', function() {
                        if (window.innerWidth <= 768 && sidebar.classList.contains('show')) {
                            sidebar.classList.remove('show');
                        }
                    });
                });
            }
        });
    </script>
</body>
</html>