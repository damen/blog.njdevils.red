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

$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// Validate CSRF token
$csrfToken = $_POST['csrf_token'] ?? '';
if (!Auth::csrfValidate($csrfToken)) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'Invalid security token. Please reload and try again.']);
        exit;
    }
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

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode([
            'ok' => $returnCode === 0,
            'exitCode' => $returnCode,
            'stdout' => implode("\n", $output)
        ]);
        exit;
    }
    
    if ($returnCode === 0) {
        $_SESSION['success_message'] = 'JSON feed updated successfully! Generated at ' . date('g:i:s A');
    } else {
        $_SESSION['error_message'] = 'Failed to update JSON feed. Error: ' . implode(' ', $output);
    }
    
} catch (Exception $e) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'Error updating JSON feed: ' . $e->getMessage()]);
        exit;
    }
    $_SESSION['error_message'] = 'Error updating JSON feed: ' . $e->getMessage();
}

// Redirect back to the referring page or dashboard
$redirectUrl = $_SERVER['HTTP_REFERER'] ?? '/admin/';
header('Location: ' . $redirectUrl);
exit;
