<?php

/**
 * Admin login page
 * 
 * Handles authentication form display and login processing.
 * Redirects authenticated users to their intended destination.
 */

require_once __DIR__ . '/../src/bootstrap.php';

$error = '';
$success = '';

// If already logged in, redirect to admin dashboard
if (Auth::isLoggedIn()) {
    header('Location: /admin/');
    exit;
}

// Process login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } elseif (Auth::checkLogin($username, $password)) {
        // Login successful
        Auth::login();
        
        // Redirect to intended page or dashboard
        $redirect = Auth::getLoginRedirect();
        header('Location: ' . $redirect);
        exit;
    } else {
        $error = 'Invalid username or password.';
    }
}

$cacheVersion = Helpers::timestamp();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - NJ Devils Game Day Admin</title>
    <link rel="stylesheet" href="/assets/css/admin.css?v=<?= $cacheVersion ?>">
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>NJ Devils Game Day</h1>
            <p>Admin Login</p>
        </div>

        <?php if ($error): ?>
            <div class="message message-error"><?= Helpers::escapeHtml($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="message message-success"><?= Helpers::escapeHtml($success) ?></div>
        <?php endif; ?>

        <form method="POST" action="/admin/login.php">
            <div class="form-group">
                <label for="username">Username:</label>
                <input 
                    type="text" 
                    id="username" 
                    name="username" 
                    value="<?= Helpers::escapeHtml($_POST['username'] ?? '') ?>"
                    required 
                    autofocus
                    autocomplete="username"
                >
            </div>

            <div class="form-group">
                <label for="password">Password:</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    required
                    autocomplete="current-password"
                >
            </div>

            <div class="form-group">
                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    Login
                </button>
            </div>
        </form>

        <div style="text-align: center; margin-top: 30px; font-size: 14px; color: #888;">
            <p>Default credentials: admin / admin</p>
            <p>Change these in your .env file</p>
        </div>
    </div>
</body>
</html>