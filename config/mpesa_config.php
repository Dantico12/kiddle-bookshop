<?php
// config/mpesa_config.php

// Load environment variables
require_once __DIR__ . '/load_env.php';

class MpesaConfig {
    private static $initialized = false;
    
    public static function init() {
        if (self::$initialized) return;
        
        // API URLs based on environment
        if ($_ENV['MPESA_ENVIRONMENT'] === 'sandbox') {
            define('MPESA_AUTH_URL', 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials');
            define('MPESA_STK_PUSH_URL', 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest');
            define('MPESA_QUERY_URL', 'https://sandbox.safaricom.co.ke/mpesa/stkpushquery/v1/query');
        } else {
            // LIVE/PRODUCTION URLs
            define('MPESA_AUTH_URL', 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials');
            define('MPESA_STK_PUSH_URL', 'https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest');
            define('MPESA_QUERY_URL', 'https://api.safaricom.co.ke/mpesa/stkpushquery/v1/query');
        }
        
        self::$initialized = true;
    }
    
    public static function getConsumerKey() {
        return $_ENV['MPESA_CONSUMER_KEY'];
    }
    
    public static function getConsumerSecret() {
        return $_ENV['MPESA_CONSUMER_SECRET'];
    }
    
    public static function getPasskey() {
        return $_ENV['MPESA_PASSKEY'];
    }
    
    public static function getShortcode() {
        return $_ENV['MPESA_SHORTCODE']; // This will be 516600
    }
    
    public static function getAccountNumber() {
        return $_ENV['MPESA_ACCOUNT_NUMBER']; // This will be 440441
    }
    
    public static function getCallbackUrl() {
        return $_ENV['MPESA_CALLBACK_URL'];
    }
    
    public static function getEnvironment() {
        return $_ENV['MPESA_ENVIRONMENT'];
    }
}

// Initialize configuration
MpesaConfig::init();
?>