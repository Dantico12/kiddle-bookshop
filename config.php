<?php
// config.php - ENHANCED SECURE VERSION (CART FUNCTIONALITY REMOVED)
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// =============================================================================
// SECURITY CONFIGURATION
// =============================================================================
class SecurityConfig {
    // Encryption key - CHANGE THIS IN PRODUCTION
    const ENCRYPTION_KEY = 'kiddle-bookstore-secure-key-2025-change-me';
    
    // Session security
    const CSRF_TOKEN_LIFETIME = 3600; // 1 hour
    const SESSION_LIFETIME = 1800; // 30 minutes
    const SESSION_ABSOLUTE_TIMEOUT = 7200; // 2 hours
    
    // Rate limiting
    const RATE_LIMIT_CHECKOUT = 10; // 10 requests
    const RATE_LIMIT_WINDOW = 300; // 5 minutes
    const MAX_LOGIN_ATTEMPTS = 5;
    
    // Input validation limits
    const MAX_NAME_LENGTH = 100;
    const MAX_EMAIL_LENGTH = 255;
    const MAX_LOCATION_LENGTH = 255;
    const MAX_PRODUCTS_PER_ORDER = 50; // Kept for potential future use, but unused now
    const MAX_QUANTITY_PER_ITEM = 100;
    
    // Payment limits
    const MIN_PAYMENT_AMOUNT = 1;
    const MAX_PAYMENT_AMOUNT = 150000;
}

// =============================================================================
// DATABASE CONFIGURATION
// =============================================================================
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'bookstore_db');

// Base URL for images
define('BASE_URL', 'http://localhost');
define('PROJECT_PATH', '/kiddle/kiddle-bookshop');

// =============================================================================
// SECURE SESSION CONFIGURATION
// =============================================================================
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_lifetime', SecurityConfig::SESSION_LIFETIME);
ini_set('session.gc_maxlifetime', SecurityConfig::SESSION_ABSOLUTE_TIMEOUT);

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Session security validation
if (empty($_SESSION['initiated'])) {
    session_regenerate_id(true);
    $_SESSION['initiated'] = true;
    $_SESSION['session_start'] = time();
    $_SESSION['last_activity'] = time();
}

// Validate session lifetime
if (isset($_SESSION['last_activity']) && 
    (time() - $_SESSION['last_activity']) > SecurityConfig::SESSION_ABSOLUTE_TIMEOUT) {
    session_destroy();
    session_start();
    session_regenerate_id(true);
    $_SESSION['initiated'] = true;
    $_SESSION['session_start'] = time();
}

$_SESSION['last_activity'] = time();

// =============================================================================
// SECURE DATABASE CONNECTION
// =============================================================================
function getSecureConnection() {
    static $connection = null;
    
    if ($connection === null) {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        
        try {
            $connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            
            if ($connection->connect_error) {
                error_log("Database connection failed: " . $connection->connect_error);
                throw new Exception("Database connection unavailable. Please try again later.");
            }
            
            $connection->set_charset("utf8mb4");
            
        } catch (Exception $e) {
            error_log("Database connection error: " . $e->getMessage());
            throw new Exception("Unable to connect to database. Please try again later.");
        }
    }
    
    return $connection;
}

// Maintain backward compatibility
function getConnection() {
    return getSecureConnection();
}

// =============================================================================
// SECURITY HELPER CLASS
// =============================================================================
class SecurityHelper {
    
    /**
     * Generate CSRF token
     */
    public static function generateCSRFToken() {
        if (empty($_SESSION['csrf_token']) || time() > $_SESSION['csrf_token_expiry']) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_expiry'] = time() + SecurityConfig::CSRF_TOKEN_LIFETIME;
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Validate CSRF token
     */
    public static function validateCSRFToken($token) {
        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        
        if (time() > $_SESSION['csrf_token_expiry']) {
            unset($_SESSION['csrf_token'], $_SESSION['csrf_token_expiry']);
            return false;
        }
        
        $isValid = hash_equals($_SESSION['csrf_token'], $token);
        
        if (!$isValid) {
            self::logSecurityEvent('CSRF_VALIDATION_FAILED', "CSRF token mismatch");
        }
        
        return $isValid;
    }
    
    /**
     * Sanitize input data
     */
    public static function sanitizeInput($input) {
        if (is_array($input)) {
            return array_map([self::class, 'sanitizeInput'], $input);
        }
        
        if ($input === null) {
            return '';
        }
        
        $input = trim($input);
        $input = stripslashes($input);
        $input = htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        return $input;
    }
    
    /**
     * Validate email address
     */
    public static function validateEmail($email) {
        if (empty($email) || strlen($email) > SecurityConfig::MAX_EMAIL_LENGTH) {
            return false;
        }
        
        $email = filter_var($email, FILTER_VALIDATE_EMAIL);
        if ($email === false) {
            return false;
        }
        
        // Additional email validation
        return preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $email);
    }
    
    /**
     * Validate Kenyan phone number
     */
   public static function validatePhone($phone) {
    // Remove all spaces and special characters except +
    $cleanPhone = preg_replace('/[^0-9+]/', '', $phone);
    
    // Updated regex to handle various Kenyan phone formats including customer service numbers
    $phoneRegex = '/^(?:254|\+254|0)?(1\d{8,9}|7\d{8})$/';
    
    return preg_match($phoneRegex, $cleanPhone);
}
    /**
     * Validate full name
     */
    public static function validateName($name) {
        return !empty($name) && 
               strlen($name) <= SecurityConfig::MAX_NAME_LENGTH && 
               preg_match('/^[a-zA-Z\s\-\']+$/', $name) &&
               strlen($name) >= 2;
    }
    
    /**
     * Validate delivery location
     */
    public static function validateLocation($location) {
        return !empty($location) && 
               strlen($location) <= SecurityConfig::MAX_LOCATION_LENGTH &&
               strlen($location) >= 2;
    }
    
    /**
     * Validate price
     */
    public static function validatePrice($price) {
        return is_numeric($price) && 
               $price >= 0 && 
               $price <= 100000; // Reasonable upper limit
    }
    
    /**
     * Validate quantity
     */
    public static function validateQuantity($quantity) {
        return is_numeric($quantity) && 
               $quantity > 0 && 
               $quantity <= SecurityConfig::MAX_QUANTITY_PER_ITEM;
    }
    
    /**
     * Validate payment amount
     */
    public static function validatePaymentAmount($amount) {
        return is_numeric($amount) && 
               $amount >= SecurityConfig::MIN_PAYMENT_AMOUNT && 
               $amount <= SecurityConfig::MAX_PAYMENT_AMOUNT;
    }
    
    /**
     * Log security events
     */
    public static function logSecurityEvent($eventType, $description, $userId = null) {
        try {
            $conn = getSecureConnection();
            
            // Check if security_logs table exists, create if not
            $checkTable = $conn->query("SHOW TABLES LIKE 'security_logs'");
            if ($checkTable->num_rows == 0) {
                self::createSecurityLogsTable($conn);
            }
            
            $stmt = $conn->prepare("
                INSERT INTO security_logs (event_type, user_id, ip_address, user_agent, description) 
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 255);
            
            $stmt->bind_param("sisss", $eventType, $userId, $ipAddress, $userAgent, $description);
            $stmt->execute();
            $stmt->close();
            
        } catch (Exception $e) {
            // Silent fail for security logs to not break application
            error_log("Security log error: " . $e->getMessage());
        }
    }
    
    /**
     * Create security logs table if it doesn't exist
     */
    private static function createSecurityLogsTable($conn) {
        $sql = "
            CREATE TABLE security_logs (
                id INT PRIMARY KEY AUTO_INCREMENT,
                event_type VARCHAR(50) NOT NULL,
                user_id INT NULL,
                ip_address VARCHAR(45) NOT NULL,
                user_agent VARCHAR(255) NULL,
                description TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_event_type (event_type),
                INDEX idx_created_at (created_at),
                INDEX idx_ip_address (ip_address)
            )
        ";
        
        $conn->query($sql);
    }
    
    /**
     * Encrypt sensitive data (for future use)
     */
    public static function encryptData($data) {
        if (empty($data)) return '';
        
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        $encrypted = openssl_encrypt(
            $data,
            'aes-256-cbc',
            SecurityConfig::ENCRYPTION_KEY,
            0,
            $iv
        );
        return base64_encode($encrypted . '::' . $iv);
    }
    
    /**
     * Decrypt sensitive data (for future use)
     */
    public static function decryptData($data) {
        if (empty($data)) return '';
        
        list($encrypted_data, $iv) = explode('::', base64_decode($data), 2);
        return openssl_decrypt(
            $encrypted_data,
            'aes-256-cbc',
            SecurityConfig::ENCRYPTION_KEY,
            0,
            $iv
        );
    }
    
    /**
     * Generate secure random string
     */
    public static function generateRandomString($length = 32) {
        return bin2hex(random_bytes($length / 2));
    }
    
    /**
     * Validate file upload
     */
    public static function validateFileUpload($file, $allowedTypes = ['image/jpeg', 'image/png', 'image/gif']) {
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            return false;
        }
        
        // Check file type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime, $allowedTypes)) {
            return false;
        }
        
        // Check file size (max 5MB)
        if ($file['size'] > 5 * 1024 * 1024) {
            return false;
        }
        
        return true;
    }
}

// =============================================================================
// RATE LIMITING CLASS
// =============================================================================
class RateLimiter {
    
    /**
     * Check rate limit for an identifier
     */
    public static function checkRateLimit($identifier, $maxRequests = null, $window = null) {
        $maxRequests = $maxRequests ?? SecurityConfig::RATE_LIMIT_CHECKOUT;
        $window = $window ?? SecurityConfig::RATE_LIMIT_WINDOW;
        
        $key = "rate_limit_{$identifier}";
        $now = time();
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = [
                'count' => 1,
                'start_time' => $now,
                'last_request' => $now
            ];
            return true;
        }
        
        $data = $_SESSION[$key];
        
        // Reset if window has passed
        if ($now - $data['start_time'] > $window) {
            $_SESSION[$key] = [
                'count' => 1,
                'start_time' => $now,
                'last_request' => $now
            ];
            return true;
        }
        
        // Check if rate limit exceeded
        if ($data['count'] >= $maxRequests) {
            SecurityHelper::logSecurityEvent('RATE_LIMIT_EXCEEDED', "Rate limit exceeded for {$identifier}");
            return false;
        }
        
        // Update count
        $_SESSION[$key]['count']++;
        $_SESSION[$key]['last_request'] = $now;
        
        return true;
    }
    
    /**
     * Get remaining requests
     */
    public static function getRemainingRequests($identifier, $maxRequests = null) {
        $maxRequests = $maxRequests ?? SecurityConfig::RATE_LIMIT_CHECKOUT;
        $key = "rate_limit_{$identifier}";
        
        if (!isset($_SESSION[$key])) {
            return $maxRequests;
        }
        
        return max(0, $maxRequests - $_SESSION[$key]['count']);
    }
}

// =============================================================================
// IMAGE PATH FUNCTION
// =============================================================================
function getImagePath($dbPath) {
    if (empty($dbPath)) {
        return 'https://via.placeholder.com/400x300?text=No+Image';
    }
    
    // If it's already a full URL, return as is
    if (strpos($dbPath, 'http') === 0) {
        return $dbPath;
    }
    
    // Clean the path
    $cleanPath = ltrim($dbPath, './');
    
    // Check if file exists locally
    $localPath = $_SERVER['DOCUMENT_ROOT'] . PROJECT_PATH . '/admin/' . $cleanPath;
    
    if (file_exists($localPath)) {
        return BASE_URL . PROJECT_PATH . '/admin/' . $cleanPath;
    } else {
        // Fallback to generated URL if file check fails
        return BASE_URL . PROJECT_PATH . '/admin/' . $cleanPath;
    }
}

// =============================================================================
// ERROR HANDLING CONFIGURATION
// =============================================================================
function initializeErrorHandling() {
    // Don't expose errors in production
    if (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'localhost') === false) {
        ini_set('display_errors', 0);
        ini_set('display_startup_errors', 0);
        error_reporting(0);
    } else {
        // Development environment
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
        error_reporting(E_ALL);
    }
    
    // Always log errors
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../logs/error.log');
}

// Initialize error handling
initializeErrorHandling();

// =============================================================================
// SECURITY HEADERS
// =============================================================================
function setSecurityHeaders() {
    if (!headers_sent()) {
        header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
        header("X-Frame-Options: DENY");
        header("X-Content-Type-Options: nosniff");
        header("Referrer-Policy: strict-origin-when-cross-origin");
        header("X-XSS-Protection: 1; mode=block");
        
        // Content Security Policy
        $csp = "default-src 'self'; ";
        $csp .= "script-src 'self' 'unsafe-inline' https://fonts.googleapis.com; ";
        $csp .= "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; ";
        $csp .= "font-src 'self' https://fonts.gstatic.com; ";
        $csp .= "img-src 'self' data: https:;";
        
        header("Content-Security-Policy: " . $csp);
    }
}

// Set security headers
setSecurityHeaders();

// =============================================================================
// GLOBAL SECURITY INITIALIZATION
// =============================================================================

// Initialize security components
$securityHelper = new SecurityHelper();
$rateLimiter = new RateLimiter();

// Log page access for security monitoring
$currentPage = basename($_SERVER['PHP_SELF']);
SecurityHelper::logSecurityEvent('PAGE_ACCESS', "Accessed page: {$currentPage}");

?>