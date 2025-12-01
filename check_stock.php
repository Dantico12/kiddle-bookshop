<?php
// check_stock.php
require_once 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $cartItems = $input['cartItems'] ?? [];
    
    $errors = [];
    $success = true;
    
    try {
        $conn = getConnection();
        
        foreach ($cartItems as $item) {
            $productId = $item['id'];
            $quantity = $item['quantity'];
            $productName = $item['title'];
            
            if ($item['type'] === 'book') {
                $stmt = $conn->prepare("SELECT title, quantity FROM books WHERE id = ?");
            } else {
                $stmt = $conn->prepare("SELECT name as title, quantity FROM stationery WHERE id = ?");
            }
            
            $stmt->bind_param("i", $productId);
            $stmt->execute();
            $stmt->bind_result($dbTitle, $dbQuantity);
            $stmt->fetch();
            $stmt->close();
            
            if ($dbQuantity < $quantity) {
                $errors[] = "Only {$dbQuantity} '{$productName}' available (you requested {$quantity})";
                $success = false;
            }
        }
        
        $conn->close();
        
    } catch (Exception $e) {
        $errors[] = "Error checking stock: " . $e->getMessage();
        $success = false;
    }
    
    echo json_encode([
        'success' => $success,
        'errors' => $errors
    ]);
}
?>