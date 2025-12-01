<?php
// sync_cart.php - Cart Synchronization Endpoint
header('Content-Type: application/json');
require_once 'config.php';

// Security: Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['action'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

$securityHelper = new SecurityHelper();
$response = ['success' => false];

try {
    switch ($data['action']) {
        case 'sync':
            // Sync cart from JavaScript to PHP session
            if (isset($data['cart']) && is_array($data['cart'])) {
                // Validate and sanitize cart items
                $validatedCart = [];
                
                foreach ($data['cart'] as $item) {
                    // Validate item structure
                    if (!isset($item['id'], $item['title'], $item['price'], $item['quantity'], $item['type'])) {
                        continue;
                    }
                    
                    // Sanitize and validate
                    $validatedItem = [
                        'id' => (int)$item['id'],
                        'title' => $securityHelper->sanitizeInput($item['title']),
                        'price' => (float)$item['price'],
                        'quantity' => (int)$item['quantity'],
                        'type' => $securityHelper->sanitizeInput($item['type']),
                        'image' => $securityHelper->sanitizeInput($item['image'] ?? '')
                    ];
                    
                    // Additional validation
                    if ($validatedItem['id'] <= 0 || 
                        $validatedItem['price'] < 0 || 
                        $validatedItem['quantity'] <= 0 || 
                        $validatedItem['quantity'] > 100 ||
                        !in_array($validatedItem['type'], ['book', 'stationery'])) {
                        continue;
                    }
                    
                    $validatedCart[] = $validatedItem;
                }
                
                // Store in session
                $_SESSION['cart'] = $validatedCart;
                
                $response = [
                    'success' => true,
                    'cart' => $validatedCart,
                    'count' => count($validatedCart),
                    'message' => 'Cart synced successfully'
                ];
                
                SecurityHelper::logSecurityEvent('CART_SYNC', "Cart synced with " . count($validatedCart) . " items");
            } else {
                // No cart provided, return session cart if exists
                $sessionCart = $_SESSION['cart'] ?? [];
                $response = [
                    'success' => true,
                    'cart' => $sessionCart,
                    'count' => count($sessionCart),
                    'message' => 'Session cart retrieved'
                ];
            }
            break;
            
        case 'get':
            // Get cart from session
            $sessionCart = $_SESSION['cart'] ?? [];
            $response = [
                'success' => true,
                'cart' => $sessionCart,
                'count' => count($sessionCart)
            ];
            break;
            
        case 'clear':
            // Clear cart
            $_SESSION['cart'] = [];
            $response = [
                'success' => true,
                'message' => 'Cart cleared'
            ];
            SecurityHelper::logSecurityEvent('CART_CLEARED', "Cart cleared by user");
            break;
            
        default:
            http_response_code(400);
            $response = [
                'success' => false,
                'error' => 'Unknown action'
            ];
    }
    
} catch (Exception $e) {
    error_log("Cart sync error: " . $e->getMessage());
    http_response_code(500);
    $response = [
        'success' => false,
        'error' => 'Server error occurred'
    ];
}

echo json_encode($response);
exit;
?>