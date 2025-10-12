<?php

/**
 * Update deletion endpoint
 * 
 * Handles POST requests to delete game updates with CSRF protection.
 * Ensures the update belongs to the current live game before deletion.
 */

require_once '_auth.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/updates.php');
    exit;
}

$error = '';
$success = '';

// Get current live game
$liveGame = getCurrentLiveGame();

// If no live game, redirect to game management
if (!$liveGame) {
    header('Location: /admin/game.php');
    exit;
}

// Validate CSRF token
$csrfToken = $_POST['csrf_token'] ?? '';
if (!Auth::csrfValidate($csrfToken)) {
    $_SESSION['error_message'] = 'Invalid security token. Please try again.';
    header('Location: /admin/updates.php');
    exit;
}

// Get update ID
$updateId = $_POST['update_id'] ?? '';
if (!is_numeric($updateId) || $updateId <= 0) {
    $_SESSION['error_message'] = 'Invalid update ID.';
    header('Location: /admin/updates.php');
    exit;
}

try {
    // Verify the update exists and belongs to the current live game
    $update = Db::fetchOne(
        'SELECT * FROM game_updates WHERE id = ? AND game_id = ?',
        [$updateId, $liveGame['id']]
    );
    
    if (!$update) {
        $_SESSION['error_message'] = 'Update not found or does not belong to the current live game.';
        header('Location: /admin/updates.php');
        exit;
    }
    
    // Delete the update
    $result = Db::execute('DELETE FROM game_updates WHERE id = ?', [$updateId]);
    
    if ($result->rowCount() > 0) {
        $_SESSION['success_message'] = 'Update deleted successfully.';
    } else {
        $_SESSION['error_message'] = 'Failed to delete update.';
    }
    
} catch (Exception $e) {
    $_SESSION['error_message'] = 'Database error: ' . $e->getMessage();
}

// Redirect back to updates page
header('Location: /admin/updates.php');
exit;