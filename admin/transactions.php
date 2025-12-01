<?php
require_once 'config/database.php';
checkAdminLogin();

$message = '';
$error = '';

// Handle edit order
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'edit_order') {
    $order_id = $_POST['order_id'] ?? '';
    $customer_name = $_POST['customer_name'] ?? '';
    $customer_email = $_POST['customer_email'] ?? '';
    $customer_phone = $_POST['customer_phone'] ?? '';
    $delivery_location = $_POST['delivery_location'] ?? '';
    $status = $_POST['status'] ?? '';
    $tracking_status = $_POST['tracking_status'] ?? '';
    $tracking_notes = $_POST['tracking_notes'] ?? '';
    
    if (!empty($order_id) && !empty($customer_name) && !empty($customer_phone) && !empty($status)) {
        try {
            $conn = getConnection();
            $stmt = $conn->prepare("UPDATE orders SET customer_name = ?, customer_email = ?, customer_phone = ?, delivery_location = ?, status = ?, tracking_status = ?, tracking_notes = ? WHERE id = ?");
            $stmt->bind_param("sssssssi", $customer_name, $customer_email, $customer_phone, $delivery_location, $status, $tracking_status, $tracking_notes, $order_id);
            $stmt->execute();
            $message = 'Order updated successfully!';
            $stmt->close();
            $conn->close();
        } catch (mysqli_sql_exception $e) {
            $error = 'Error updating order: ' . $e->getMessage();
        }
    } else {
        $error = 'Please fill in all required fields';
    }
}

// Handle delete order
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'delete_order') {
    $order_id = $_POST['order_id'] ?? '';
    
    if (!empty($order_id)) {
        try {
            $conn = getConnection();
            
            // Begin transaction
            $conn->begin_transaction();
            
            // Delete order items first
            $stmt = $conn->prepare("DELETE FROM order_items WHERE order_id = ?");
            $stmt->bind_param("i", $order_id);
            $stmt->execute();
            $stmt->close();
            
            // Delete tracking history
            $stmt = $conn->prepare("DELETE FROM order_tracking_history WHERE order_id = ?");
            $stmt->bind_param("i", $order_id);
            $stmt->execute();
            $stmt->close();
            
            // Delete the order
            $stmt = $conn->prepare("DELETE FROM orders WHERE id = ?");
            $stmt->bind_param("i", $order_id);
            $stmt->execute();
            $stmt->close();
            
            // Commit transaction
            $conn->commit();
            $message = 'Order deleted successfully!';
            $conn->close();
        } catch (mysqli_sql_exception $e) {
            // Rollback transaction on error
            if (isset($conn)) {
                $conn->rollback();
            }
            $error = 'Error deleting order: ' . $e->getMessage();
        }
    }
}

// Get all orders with customer details and order items
try {
    $conn = getConnection();
    $query = "
        SELECT o.*, 
               COUNT(oi.id) as item_count,
               SUM(oi.quantity) as total_quantity
        FROM orders o 
        LEFT JOIN order_items oi ON o.id = oi.order_id 
        GROUP BY o.id 
        ORDER BY o.created_at DESC
    ";
    $result = $conn->query($query);
    $orders = [];
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
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
    <title>Orders Management - Kiddle Bookstore Admin</title>
    <link rel="stylesheet" href="assets/css/admin-style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">
       <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <h1 class="page-title">Orders Management</h1>
                <div class="action-buttons">
                    <a href="orders.php" class="btn btn-primary">
                        <i class="fas fa-chart-line"></i> Customer Analysis
                    </a>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <!-- Edit Order Form -->
            <div id="editOrderForm" style="display: none;">
                <div class="form-container">
                    <h3>Edit Order</h3>
                    <form method="POST" id="editForm">
                        <input type="hidden" name="action" value="edit_order">
                        <input type="hidden" name="order_id" id="edit_order_id">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="edit_customer_name">Customer Name *</label>
                                <input type="text" id="edit_customer_name" name="customer_name" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label for="edit_customer_email">Customer Email *</label>
                                <input type="email" id="edit_customer_email" name="customer_email" class="form-control" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="edit_customer_phone">Customer Phone *</label>
                                <input type="tel" id="edit_customer_phone" name="customer_phone" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label for="edit_delivery_location">Delivery Location</label>
                                <input type="text" id="edit_delivery_location" name="delivery_location" class="form-control">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="edit_status">Payment Status *</label>
                                <select id="edit_status" name="status" class="form-control" required>
                                    <option value="pending">Pending</option>
                                    <option value="paid">Paid</option>
                                    <option value="delivered">Delivered</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="edit_tracking_status">Tracking Status *</label>
                                <select id="edit_tracking_status" name="tracking_status" class="form-control" required>
                                    <option value="pending">Pending</option>
                                    <option value="processing">Processing</option>
                                    <option value="shipped">Shipped</option>
                                    <option value="out_for_delivery">Out for Delivery</option>
                                    <option value="delivered">Delivered</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row single">
                            <div class="form-group">
                                <label for="edit_tracking_notes">Tracking Notes</label>
                                <textarea id="edit_tracking_notes" name="tracking_notes" class="form-control" rows="3" placeholder="Add tracking updates or notes..."></textarea>
                            </div>
                        </div>
                        
                        <div class="action-buttons">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save"></i> Update Order
                            </button>
                            <button type="button" onclick="toggleEditForm()" class="btn btn-danger">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Orders List -->
            <div class="table-container">
                <div style="padding: 1.5rem; border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
                    <h3>All Orders (<?= count($orders ?? []) ?>)</h3>
                </div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Order Number</th>
                            <th>Customer</th>
                            <th>Contact</th>
                            <th>Items</th>
                            <th>Total Amount</th>
                            <th>Payment Status</th>
                            <th>Tracking Status</th>
                            <th>M-Pesa Receipt</th>
                            <th>Order Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($orders)): ?>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($order['order_number']) ?></strong>
                                        <?php if ($order['delivery_location']): ?>
                                            <br><small style="color: var(--text-muted);">📍 <?= htmlspecialchars($order['delivery_location']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div><?= htmlspecialchars($order['customer_name']) ?></div>
                                        <small style="color: var(--text-muted);"><?= htmlspecialchars($order['customer_email']) ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($order['customer_phone']) ?></td>
                                    <td>
                                        <div><?= $order['item_count'] ?> items</div>
                                        <small style="color: var(--text-muted);"><?= $order['total_quantity'] ?> total qty</small>
                                    </td>
                                    <td>
                                        <strong style="color: var(--accent-primary);">
                                            Ksh <?= number_format($order['total_amount'], 2) ?>
                                        </strong>
                                    </td>
                                    <td>
                                        <?php
                                        $status = $order['status'];
                                        $badgeClass = 'badge-warning';
                                        if ($status === 'paid') $badgeClass = 'badge-success';
                                        if ($status === 'delivered') $badgeClass = 'badge-success';
                                        if ($status === 'cancelled') $badgeClass = 'badge-danger';
                                        ?>
                                        <span class="badge <?= $badgeClass ?>"><?= ucfirst($status) ?></span>
                                    </td>
                                    <td>
                                        <?php
                                        $tracking_status = $order['tracking_status'];
                                        $trackingBadgeClass = 'badge-warning';
                                        if ($tracking_status === 'delivered') $trackingBadgeClass = 'badge-success';
                                        if ($tracking_status === 'out_for_delivery') $trackingBadgeClass = 'badge-success';
                                        if ($tracking_status === 'shipped') $trackingBadgeClass = 'badge-warning';
                                        if ($tracking_status === 'processing') $trackingBadgeClass = 'badge-warning';
                                        if ($tracking_status === 'cancelled') $trackingBadgeClass = 'badge-danger';
                                        ?>
                                        <span class="badge <?= $trackingBadgeClass ?>">
                                            <?= ucfirst(str_replace('_', ' ', $tracking_status)) ?>
                                        </span>
                                        <?php if ($order['tracking_notes']): ?>
                                            <br><small style="color: var(--text-muted); font-size: 0.8rem;"><?= htmlspecialchars(substr($order['tracking_notes'], 0, 30)) ?>...</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($order['mpesa_receipt']): ?>
                                            <code style="background: rgba(0, 255, 231, 0.1); padding: 0.2rem 0.4rem; border-radius: 4px; font-size: 0.8rem;">
                                                <?= htmlspecialchars($order['mpesa_receipt']) ?>
                                            </code>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted);">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('M j, Y g:i A', strtotime($order['created_at'])) ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <button onclick="editOrder(
                                                <?= $order['id'] ?>, 
                                                '<?= htmlspecialchars($order['customer_name']) ?>', 
                                                '<?= htmlspecialchars($order['customer_email']) ?>', 
                                                '<?= htmlspecialchars($order['customer_phone']) ?>',
                                                '<?= htmlspecialchars($order['delivery_location'] ?? '') ?>',
                                                '<?= $order['status'] ?>',
                                                '<?= $order['tracking_status'] ?>',
                                                '<?= htmlspecialchars($order['tracking_notes'] ?? '') ?>'
                                            )" class="btn btn-sm" style="background: linear-gradient(45deg, var(--accent-tertiary), var(--accent-primary)); color: white; margin-right: 0.5rem;">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <button onclick="deleteOrder(<?= $order['id'] ?>)" class="btn btn-sm btn-danger">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" style="text-align: center; color: var(--text-muted); padding: 2rem;">
                                    <i class="fas fa-shopping-cart" style="font-size: 2rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                                    <br>No orders found
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <script>
        function toggleEditForm() {
            const form = document.getElementById('editOrderForm');
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }

        function editOrder(id, customerName, customerEmail, customerPhone, deliveryLocation, status, trackingStatus, trackingNotes) {
            document.getElementById('edit_order_id').value = id;
            document.getElementById('edit_customer_name').value = customerName;
            document.getElementById('edit_customer_email').value = customerEmail;
            document.getElementById('edit_customer_phone').value = customerPhone;
            document.getElementById('edit_delivery_location').value = deliveryLocation || '';
            document.getElementById('edit_status').value = status;
            document.getElementById('edit_tracking_status').value = trackingStatus;
            document.getElementById('edit_tracking_notes').value = trackingNotes || '';
            
            // Scroll to edit form
            toggleEditForm();
            document.getElementById('editOrderForm').scrollIntoView({ behavior: 'smooth' });
        }

        function deleteOrder(id) {
            if (confirm('Are you sure you want to delete this order? This will also delete all order items and tracking history. This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_order">
                    <input type="hidden" name="order_id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Close edit form when clicking outside
        document.addEventListener('click', function(event) {
            const editForm = document.getElementById('editOrderForm');
            const editButton = event.target.closest('button[onclick*="editOrder"]');
            
            if (editForm.style.display === 'block' && !editForm.contains(event.target) && !editButton) {
                toggleEditForm();
            }
        });
    </script>
</body>
</html>