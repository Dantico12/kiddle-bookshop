<?php
// config/load_env.php - Load environment variables

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

// Load .env file from project root
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Validate required M-Pesa environment variables
$required = [
    'MPESA_CONSUMER_KEY',
    'MPESA_CONSUMER_SECRET',
    'MPESA_PASSKEY',
    'MPESA_SHORTCODE',
    'MPESA_CALLBACK_URL',
    'MPESA_ENVIRONMENT'
];

$dotenv->required($required)->notEmpty();

// Optional: Log successful environment loading (only in development)
if (isset($_ENV['MPESA_ENVIRONMENT']) && $_ENV['MPESA_ENVIRONMENT'] === 'sandbox') {
    error_log("M-Pesa environment loaded successfully: " . $_ENV['MPESA_ENVIRONMENT']);
}
?>