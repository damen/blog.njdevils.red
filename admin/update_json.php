<?php

/**
 * Manual JSON Feed Update Endpoint
 * 
 * Allows admin users to manually trigger JSON feed generation
 * instead of waiting for the cron job.
 */

require_once '_auth.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/');
    exit;
}

// Validate CSRF token
$csrfToken = $_POST['csrf_token'] ?? '';
if (!Auth::csrfValidate($csrfToken)) {
    $_SESSION['error_message'] = 'Invalid security token. Please try again.';
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/admin/'));
    exit;
}

try {
    // Execute the update script
    $output = [];
    $returnCode = 0;
    
    // Run the update.php script
    $updateScriptPath = __DIR__ . '/../update.php';
    exec("php " . escapeshellarg($updateScriptPath) . " 2>&1", $output, $returnCode);
    
    if ($returnCode === 0) {
        $_SESSION['success_message'] = 'JSON feed updated successfully! Generated at ' . date('g:i:s A');
    } else {
        $_SESSION['error_message'] = 'Failed to update JSON feed. Error: ' . implode(' ', $output);
    }
    
} catch (Exception $e) {
    $_SESSION['error_message'] = 'Error updating JSON feed: ' . $e->getMessage();
}

// Redirect back to the referring page or dashboard
$redirectUrl = $_SERVER['HTTP_REFERER'] ?? '/admin/';
header('Location: ' . $redirectUrl);
exit;