<?php
// callback/mpesa_callback.php - M-Pesa Callback Handler

ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';

// Create logs directory if it doesn't exist
$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

// Log all incoming requests
$callbackLog = $logDir . '/mpesa_callbacks.log';
$rawInput = file_get_contents('php://input');
$logEntry = date('Y-m-d H:i:s') . " - RAW CALLBACK: " . $rawInput . "\n";
file_put_contents($callbackLog, $logEntry, FILE_APPEND | LOCK_EX);

try {
    // Parse M-Pesa callback data
    $callbackData = json_decode($rawInput, true);
    
    if (!$callbackData || !isset($callbackData['Body']['stkCallback'])) {
        throw new Exception('Invalid callback data structure');
    }
    
    $stkCallback = $callbackData['Body']['stkCallback'];
    $merchantRequestID = $stkCallback['MerchantRequestID'] ?? '';
    $checkoutRequestID = $stkCallback['CheckoutRequestID'] ?? '';
    $resultCode = $stkCallback['ResultCode'] ?? 999;
    $resultDesc = $stkCallback['ResultDesc'] ?? 'Unknown error';
    
    // Database connection
    $conn = getConnection();
    
    if ($resultCode == 0) {
        // Payment successful - extract details
        $amount = $mpesaReceiptNumber = $transactionDate = $phoneNumber = '';
        
        if (isset($stkCallback['CallbackMetadata']['Item'])) {
            foreach ($stkCallback['CallbackMetadata']['Item'] as $item) {
                switch ($item['Name']) {
                    case 'Amount':
                        $amount = $item['Value'] ?? 0;
                        break;
                    case 'MpesaReceiptNumber':
                        $mpesaReceiptNumber = $item['Value'] ?? '';
                        break;
                    case 'TransactionDate':
                        $transactionDate = $item['Value'] ?? '';
                        break;
                    case 'PhoneNumber':
                        $phoneNumber = $item['Value'] ?? '';
                        break;
                }
            }
        }
        
        // Update order status to paid
        $updateStmt = $conn->prepare("
            UPDATE orders 
            SET status = 'paid', 
                mpesa_receipt = ?,
                updated_at = NOW()
            WHERE mpesa_receipt = ? OR order_number LIKE ?
        ");
        
        $orderSearchPattern = '%' . substr($checkoutRequestID, -10);
        $updateStmt->bind_param("sss", $mpesaReceiptNumber, $checkoutRequestID, $orderSearchPattern);
        $updateStmt->execute();
        
        $affectedRows = $updateStmt->affected_rows;
        $updateStmt->close();
        
        // Log successful payment
        $successLog = date('Y-m-d H:i:s') . " - PAYMENT SUCCESS: " . json_encode([
            'receipt' => $mpesaReceiptNumber,
            'amount' => $amount,
            'phone' => $phoneNumber,
            'checkout_id' => $checkoutRequestID,
            'orders_updated' => $affectedRows
        ]) . "\n";
        file_put_contents($callbackLog, $successLog, FILE_APPEND | LOCK_EX);
        
    } else {
        // Payment failed
        $updateStmt = $conn->prepare("
            UPDATE orders 
            SET status = 'cancelled',
                updated_at = NOW()
            WHERE mpesa_receipt = ? OR order_number LIKE ?
        ");
        
        $orderSearchPattern = '%' . substr($checkoutRequestID, -10);
        $updateStmt->bind_param("ss", $checkoutRequestID, $orderSearchPattern);
        $updateStmt->execute();
        $updateStmt->close();
        
        // Log failed payment
        $failLog = date('Y-m-d H:i:s') . " - PAYMENT FAILED: " . json_encode([
            'result_code' => $resultCode,
            'result_desc' => $resultDesc,
            'checkout_id' => $checkoutRequestID
        ]) . "\n";
        file_put_contents($callbackLog, $failLog, FILE_APPEND | LOCK_EX);
    }
    
    $conn->close();
    
} catch (Exception $e) {
    $errorLog = date('Y-m-d H:i:s') . " - CALLBACK ERROR: " . $e->getMessage() . "\n";
    file_put_contents($callbackLog, $errorLog, FILE_APPEND | LOCK_EX);
}

// Always return success to M-Pesa (required by Safaricom)
header('Content-Type: application/json');
echo json_encode([
    'ResultCode' => 0,
    'ResultDesc' => 'Accepted'
]);
exit;
?>