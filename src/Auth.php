<?php

/**
 * Authentication and CSRF protection system
 * 
 * Manages session-based authentication and CSRF token validation
 * for admin access to the game day management interface.
 */
class Auth
{
    private const SESSION_KEY = 'gameday_admin_logged_in';
    private const CSRF_KEY = 'gameday_csrf_token';
    
    /**
     * Start secure session with appropriate flags
     */
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            // Set secure session configuration
            $sessionConfig = [
                'cookie_httponly' => true,
                'cookie_samesite' => 'Strict',
                'use_strict_mode' => true,
                'use_only_cookies' => true
            ];
            
            // Add secure flag if HTTPS
            if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
                $sessionConfig['cookie_secure'] = true;
            }
            
            foreach ($sessionConfig as $key => $value) {
                ini_set("session.{$key}", $value);
            }
            
            session_start();
            
            // Regenerate session ID periodically for security
            if (!isset($_SESSION['last_regeneration'])) {
                $_SESSION['last_regeneration'] = time();
            } elseif (time() - $_SESSION['last_regeneration'] > 300) {
                session_regenerate_id(true);
                $_SESSION['last_regeneration'] = time();
            }
        }
    }
    
    /**
     * Check login credentials against environment configuration
     * 
     * @param string $username Submitted username
     * @param string $password Submitted password
     * @return bool True if credentials are valid
     */
    public static function checkLogin(string $username, string $password): bool
    {
        $validUsername = Env::get('ADMIN_USER');
        $validPassword = Env::get('ADMIN_PASS');
        
        if (!$validUsername || !$validPassword) {
            return false;
        }
        
        // Use hash_equals for constant-time comparison to prevent timing attacks
        $usernameValid = hash_equals($validUsername, $username);
        $passwordValid = hash_equals($validPassword, $password);
        
        return $usernameValid && $passwordValid;
    }
    
    /**
     * Check if user is currently authenticated
     */
    public static function isLoggedIn(): bool
    {
        self::startSession();
        return isset($_SESSION[self::SESSION_KEY]) && $_SESSION[self::SESSION_KEY] === true;
    }
    
    /**
     * Log in the user (set session flag)
     */
    public static function login(): void
    {
        self::startSession();
        $_SESSION[self::SESSION_KEY] = true;
    }
    
    /**
     * Log out the user (destroy session)
     */
    public static function logout(): void
    {
        self::startSession();
        
        // Clear all session data
        $_SESSION = [];
        
        // Destroy session cookie
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        
        // Destroy session
        session_destroy();
    }
    
    /**
     * Require authentication - redirect to login if not authenticated
     */
    public static function requireAuth(): void
    {
        if (!self::isLoggedIn()) {
            // Store intended URL for redirect after login
            if (!isset($_SESSION['login_redirect'])) {
                $_SESSION['login_redirect'] = $_SERVER['REQUEST_URI'] ?? '/admin/';
            }
            
            header('Location: /admin/login.php');
            exit;
        }
    }
    
    /**
     * Generate CSRF token for forms
     */
    public static function csrfToken(): string
    {
        self::startSession();
        
        // Generate new token if none exists
        if (!isset($_SESSION[self::CSRF_KEY])) {
            $_SESSION[self::CSRF_KEY] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION[self::CSRF_KEY];
    }
    
    /**
     * Validate CSRF token and rotate it
     * 
     * @param string|null $token Submitted token
     * @return bool True if token is valid
     */
    public static function csrfValidate(?string $token): bool
    {
        self::startSession();
        
        if (!$token || !isset($_SESSION[self::CSRF_KEY])) {
            return false;
        }
        
        $valid = hash_equals($_SESSION[self::CSRF_KEY], $token);
        
        // Keep a stable per-session token to avoid stale tokens across multiple forms
        // on the same page. Rotation can cause subsequent form submissions to fail
        // without a full page reload. If desired, rotation could be re-enabled with
        // AJAX-based token refresh per form submit.
        return $valid;
    }
    
    /**
     * Generate CSRF hidden input field for forms
     */
    public static function csrfField(): string
    {
        $token = self::csrfToken();
        return '<input type="hidden" name="csrf_token" value="' . Helpers::escapeHtml($token) . '">';
    }
    
    /**
     * Get redirect URL after login (and clear it)
     */
    public static function getLoginRedirect(): string
    {
        self::startSession();
        $redirect = $_SESSION['login_redirect'] ?? '/admin/';
        unset($_SESSION['login_redirect']);
        return $redirect;
    }
}