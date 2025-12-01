<?php
require_once 'config/database.php';
checkAdminLogin();

// Get enhanced statistics
try {
    $conn = getConnection();
    
    // Count customers (not users - your table is customers)
    $result = $conn->query("SELECT COUNT(*) as count FROM customers");
    $customerCount = $result->fetch_assoc()['count'];
    
    // Count books
    $result = $conn->query("SELECT COUNT(*) as count FROM books");
    $bookCount = $result->fetch_assoc()['count'];
    
    // Count stationery items
    $result = $conn->query("SELECT COUNT(*) as count FROM stationery");
    $stationeryCount = $result->fetch_assoc()['count'];
    
    // Count orders (not transactions - your main table is orders)
    $result = $conn->query("SELECT COUNT(*) as count FROM orders");
    $orderCount = $result->fetch_assoc()['count'];
    
    // Count paid orders
    $result = $conn->query("SELECT COUNT(*) as count FROM orders WHERE status = 'paid'");
    $paidOrderCount = $result->fetch_assoc()['count'];
    
    // Low stock items
    $result = $conn->query("SELECT COUNT(*) as count FROM books WHERE quantity <= reorder_level");
    $lowStockBooks = $result->fetch_assoc()['count'];
    
    $result = $conn->query("SELECT COUNT(*) as count FROM stationery WHERE quantity <= reorder_level");
    $lowStockStationery = $result->fetch_assoc()['count'];
    $lowStockCount = $lowStockBooks + $lowStockStationery;
    
    // Total revenue from paid orders
    $result = $conn->query("SELECT SUM(total_amount) as total FROM orders WHERE status = 'paid'");
    $totalRevenue = $result->fetch_assoc()['total'] ?? 0;
    
    // If no paid orders, calculate from all orders for demo
    if (!$totalRevenue) {
        $result = $conn->query("SELECT SUM(total_amount) as total FROM orders");
        $totalRevenue = $result->fetch_assoc()['total'] ?? 0;
    }
    
    // Sales by category (books vs stationery)
    $result = $conn->query("
        SELECT 
            'books' as category,
            COUNT(*) as count,
            SUM(oi.quantity * oi.price) as revenue
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN books b ON oi.product_id = b.id
        WHERE o.status = 'paid'
        UNION ALL
        SELECT 
            'stationery' as category,
            COUNT(*) as count,
            SUM(oi.quantity * oi.price) as revenue
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN stationery s ON oi.product_id = s.id
        WHERE o.status = 'paid'
    ");
    
    $salesByCategory = [];
    while ($row = $result->fetch_assoc()) {
        $salesByCategory[] = $row;
    }
    
    // Monthly sales data from orders
    $result = $conn->query("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as order_count,
            SUM(total_amount) as revenue
        FROM orders 
        WHERE status = 'paid'
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month DESC
        LIMIT 6
    ");
    
    $monthlySales = [];
    while ($row = $result->fetch_assoc()) {
        $monthlySales[] = $row;
    }
    
    // If no monthly sales data, use all orders for demo
    if (empty($monthlySales)) {
        $result = $conn->query("
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                COUNT(*) as order_count,
                SUM(total_amount) as revenue
            FROM orders 
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY month DESC
            LIMIT 6
        ");
        
        while ($row = $result->fetch_assoc()) {
            $monthlySales[] = $row;
        }
    }
    
    // Top selling products
    $result = $conn->query("
        SELECT 
            oi.product_name,
            SUM(oi.quantity) as total_sold,
            SUM(oi.quantity * oi.price) as revenue
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        WHERE o.status = 'paid'
        GROUP BY oi.product_name
        ORDER BY total_sold DESC
        LIMIT 5
    ");
    
    $topProducts = [];
    while ($row = $result->fetch_assoc()) {
        $topProducts[] = $row;
    }
    
    // If no top products from paid orders, use all orders for demo
    if (empty($topProducts)) {
        $result = $conn->query("
            SELECT 
                oi.product_name,
                SUM(oi.quantity) as total_sold,
                SUM(oi.quantity * oi.price) as revenue
            FROM order_items oi
            JOIN orders o ON oi.order_id = o.id
            GROUP BY oi.product_name
            ORDER BY total_sold DESC
            LIMIT 5
        ");
        
        while ($row = $result->fetch_assoc()) {
            $topProducts[] = $row;
        }
    }
    
    // Recent orders (transactions)
    $result = $conn->query("
        SELECT o.*, c.full_name, c.email, c.phone
        FROM orders o
        JOIN customers c ON o.customer_id = c.id
        ORDER BY o.created_at DESC 
        LIMIT 5
    ");
    $recentOrders = [];
    while ($row = $result->fetch_assoc()) {
        $recentOrders[] = $row;
    }
    
    // Inventory stats for chart
    $inventoryStats = [
        'books' => $bookCount,
        'stationery' => $stationeryCount,
        'lowStock' => $lowStockCount
    ];
    
    $conn->close();
} catch (mysqli_sql_exception $e) {
    $error = "Database error: " . $e->getMessage();
}
include 'nav_bar.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - NeoBooks</title>
    <link rel="stylesheet" href="assets/css/admin-style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="dashboard-container">
        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <h1 class="page-title">Dashboard Overview</h1>
                <div class="header-info">
                    <span class="date-display"><?= date('F j, Y') ?></span>
                    <span class="time-display" id="current-time"></span>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="dashboard-stats">
                <div class="stat-card">
                    <div class="stat-icon users">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?= $customerCount ?? 0 ?></div>
                        <div class="stat-label">Total Customers</div>
                        <div class="stat-trend up">
                            <i class="fas fa-arrow-up"></i>
                            <span><?= $customerCount ?> registered</span>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon books">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?= $bookCount ?? 0 ?></div>
                        <div class="stat-label">Books in Stock</div>
                        <div class="stat-trend up">
                            <i class="fas fa-arrow-up"></i>
                            <span><?= $lowStockBooks ?> low stock</span>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon transactions">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?= $orderCount ?? 0 ?></div>
                        <div class="stat-label">Total Orders</div>
                        <div class="stat-trend up">
                            <i class="fas fa-arrow-up"></i>
                            <span><?= $paidOrderCount ?> paid</span>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon revenue">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number">Ksh <?= number_format($totalRevenue ?? 0, 2) ?></div>
                        <div class="stat-label">Total Revenue</div>
                        <div class="stat-trend up">
                            <i class="fas fa-arrow-up"></i>
                            <span>From <?= $orderCount ?> orders</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="charts-grid">
                <!-- Sales Chart -->
                <div class="chart-container">
                    <div class="chart-header">
                        <h3>Monthly Revenue</h3>
                        <div class="chart-actions">
                            <button class="chart-action-btn active" data-period="month">Month</button>
                            <button class="chart-action-btn" data-period="quarter">Quarter</button>
                            <button class="chart-action-btn" data-period="year">Year</button>
                        </div>
                    </div>
                    <div class="chart-wrapper">
                        <canvas id="salesChart"></canvas>
                    </div>
                </div>

                <!-- Category Distribution -->
                <div class="chart-container">
                    <div class="chart-header">
                        <h3>Sales by Category</h3>
                    </div>
                    <div class="chart-wrapper">
                        <canvas id="categoryChart"></canvas>
                    </div>
                </div>

                <!-- Inventory Status -->
                <div class="chart-container">
                    <div class="chart-header">
                        <h3>Inventory Status</h3>
                    </div>
                    <div class="chart-wrapper">
                        <canvas id="inventoryChart"></canvas>
                    </div>
                </div>

                <!-- Top Products -->
                <div class="chart-container">
                    <div class="chart-header">
                        <h3>Top Selling Products</h3>
                    </div>
                    <div class="chart-wrapper">
                        <canvas id="productsChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Additional Info Cards -->
            <div class="info-cards-grid">
                <div class="info-card alert">
                    <div class="info-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="info-content">
                        <h4>Low Stock Alert</h4>
                        <p><?= $lowStockCount ?? 0 ?> items need restocking</p>
                        <a href="inventory.php" class="info-link">View Inventory</a>
                    </div>
                </div>

                <div class="info-card success">
                    <div class="info-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="info-content">
                        <h4>Sales Performance</h4>
                        <p>Ksh <?= number_format($totalRevenue ?? 0, 2) ?> total revenue</p>
                        <a href="orders.php" class="info-link">View Orders</a>
                    </div>
                </div>

                <div class="info-card warning">
                    <div class="info-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="info-content">
                        <h4>Pending Orders</h4>
                        <p><?= $orderCount - $paidOrderCount ?> orders awaiting processing</p>
                        <a href="orders.php?status=pending" class="info-link">Process Now</a>
                    </div>
                </div>
            </div>

            <!-- Recent Orders -->
            <div class="table-container">
                <div class="table-header">
                    <h3>Recent Orders</h3>
                    <a href="orders.php" class="view-all-btn">View All</a>
                </div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Amount</th>
                            <th>M-Pesa Ref</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($recentOrders)): ?>
                            <?php foreach ($recentOrders as $order): ?>
                                <tr>
                                    <td>#<?= $order['id'] ?></td>
                                    <td>
                                        <div class="customer-info">
                                            <div class="customer-name"><?= htmlspecialchars($order['full_name']) ?></div>
                                            <div class="customer-email"><?= htmlspecialchars($order['email']) ?></div>
                                        </div>
                                    </td>
                                    <td>Ksh <?= number_format($order['total_amount'], 2) ?></td>
                                    <td class="mpesa-ref"><?= htmlspecialchars($order['mpesa_receipt'] ?? 'N/A') ?></td>
                                    <td>
                                        <span class="status-badge status-<?= $order['status'] ?>">
                                            <?= ucfirst($order['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= date('M j, Y g:i A', strtotime($order['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="no-data">
                                    <i class="fas fa-inbox"></i>
                                    <span>No orders found</span>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

   <script>
    // Real-time clock
    function updateClock() {
        const now = new Date();
        const timeString = now.toLocaleTimeString('en-US', { 
            hour: '2-digit', 
            minute: '2-digit',
            second: '2-digit',
            hour12: true 
        });
        document.getElementById('current-time').textContent = timeString;
    }
    setInterval(updateClock, 1000);
    updateClock();

    // Chart data from PHP
    const monthlySalesData = <?= json_encode($monthlySales) ?>;
    const salesByCategory = <?= json_encode($salesByCategory) ?>;
    const topProducts = <?= json_encode($topProducts) ?>;
    const inventoryStats = <?= json_encode($inventoryStats) ?>;

    // Initialize Charts
    document.addEventListener('DOMContentLoaded', function() {
        // Sales Chart
        const salesCtx = document.getElementById('salesChart').getContext('2d');
        const salesChart = new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: monthlySalesData.map(item => {
                    const date = new Date(item.month + '-01');
                    return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
                }).reverse(),
                datasets: [{
                    label: 'Revenue',
                    data: monthlySalesData.map(item => item.revenue || 0).reverse(),
                    borderColor: '#00ffe7',
                    backgroundColor: 'rgba(0, 255, 231, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        },
                        ticks: {
                            callback: function(value) {
                                return 'Ksh ' + value.toLocaleString();
                            }
                        }
                    },
                    x: {
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        }
                    }
                }
            }
        });

        // Category Chart
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        const categoryChart = new Chart(categoryCtx, {
            type: 'doughnut',
            data: {
                labels: ['Books', 'Stationery'],
                datasets: [{
                    data: [
                        salesByCategory.find(item => item.category === 'books')?.revenue || 0,
                        salesByCategory.find(item => item.category === 'stationery')?.revenue || 0
                    ],
                    backgroundColor: [
                        '#00ffe7',
                        '#9b5de5'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                cutout: '70%',
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Inventory Chart
        const inventoryCtx = document.getElementById('inventoryChart').getContext('2d');
        const inventoryChart = new Chart(inventoryCtx, {
            type: 'bar',
            data: {
                labels: ['Books', 'Stationery', 'Low Stock'],
                datasets: [{
                    label: 'Items',
                    data: [
                        inventoryStats.books,
                        inventoryStats.stationery,
                        inventoryStats.lowStock
                    ],
                    backgroundColor: [
                        '#00ffe7',
                        '#9b5de5',
                        '#ff6b6b'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // Products Chart - FIXED: Using bar with indexAxis instead of horizontalBar
        const productsCtx = document.getElementById('productsChart').getContext('2d');
        const productsChart = new Chart(productsCtx, {
            type: 'bar',
            data: {
                labels: topProducts.map(item => {
                    // Shorten long product names for better display
                    const name = item.product_name;
                    return name.length > 20 ? name.substring(0, 20) + '...' : name;
                }),
                datasets: [{
                    label: 'Units Sold',
                    data: topProducts.map(item => item.total_sold),
                    backgroundColor: '#00bcd4',
                    borderWidth: 0,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                indexAxis: 'y', // This makes it horizontal
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        }
                    },
                    y: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // Chart period switcher
        document.querySelectorAll('.chart-action-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.chart-action-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                // Here you would typically reload chart data based on period
                console.log('Switching to period:', this.dataset.period);
            });
        });
    });
</script>
</body>
</html>