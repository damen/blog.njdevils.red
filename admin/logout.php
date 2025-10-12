<?php

/**
 * Admin logout page
 * 
 * Destroys the user session and redirects to login page.
 */

require_once __DIR__ . '/../src/bootstrap.php';

// Perform logout
Auth::logout();

// Redirect to login page with a success message parameter
header('Location: /admin/login.php?logged_out=1');
exit;