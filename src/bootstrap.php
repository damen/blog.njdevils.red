<?php

/**
 * Application Bootstrap
 * 
 * This file initializes the core application components:
 * - Environment configuration
 * - Timezone settings
 * - Database connection
 * - Authentication system
 * - Helper functions
 * - Content sanitization
 */

// Load core classes first
require_once __DIR__ . '/Env.php';
require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Helpers.php';
require_once __DIR__ . '/Sanitizer.php';
require_once __DIR__ . '/Auth.php';

// Load environment configuration
Env::load(__DIR__ . '/../.env');

// Set error reporting for development
if (Env::get('APP_ENV', 'production') === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ERROR | E_WARNING | E_PARSE);
    ini_set('display_errors', '0');
}

// Set timezone from configuration
Helpers::setDefaultTimezone();

// Set default headers for all responses
header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Initialize database connection (will be created on first use)
// $pdo is available globally after including bootstrap
try {
    $pdo = Db::pdo();
} catch (Exception $e) {
    // Log error and show generic message
    error_log('Database connection failed: ' . $e->getMessage());
    
    if (Env::get('APP_ENV', 'production') === 'development') {
        die('Database connection failed: ' . $e->getMessage());
    } else {
        die('Database connection failed. Please check configuration.');
    }
}

// Start session for admin pages (Auth will handle session security)
Auth::startSession();