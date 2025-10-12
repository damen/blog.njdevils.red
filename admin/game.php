<?php

/**
 * Game management page
 * 
 * Create new games or edit existing games. Allows setting a game as live,
 * which automatically unsets any other live games.
 */

require_once '_auth.php';

$error = $_SESSION['error_message'] ?? '';
$success = $_SESSION['success_message'] ?? '';

// Clear session messages
unset($_SESSION['error_message'], $_SESSION['success_message']);
$game = null;

// Get game ID from URL if editing
$gameId = $_GET['id'] ?? null;
if ($gameId && is_numeric($gameId)) {
    $game = Db::fetchOne('SELECT * FROM games WHERE id = ?', [$gameId]);
    if (!$game) {
        $error = 'Game not found.';
    }
}

// If no specific game requested, try to load the current live game
if (!$game && !$gameId) {
    $game = getCurrentLiveGame();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';
    
    // Validate CSRF token
    if (!Auth::csrfValidate($csrfToken)) {
        $error = 'Invalid security token. Please try again.';
    } else {
        // Process different actions
        switch ($action) {
            case 'save':
                $title = trim($_POST['title'] ?? '');
                $homeTeam = trim($_POST['home_team'] ?? '');
                $awayTeam = trim($_POST['away_team'] ?? '');
                $scoreHome = (int)($_POST['score_home'] ?? 0);
                $scoreAway = (int)($_POST['score_away'] ?? 0);
                
                // Validate required fields
                $errors = [];
                if (empty($title)) $errors[] = 'Game title is required.';
                if (empty($homeTeam)) $errors[] = 'Home team is required.';
                if (empty($awayTeam)) $errors[] = 'Away team is required.';
                if (strlen($title) > 255) $errors[] = 'Title is too long (max 255 characters).';
                if (strlen($homeTeam) > 100) $errors[] = 'Home team name is too long (max 100 characters).';
                if (strlen($awayTeam) > 100) $errors[] = 'Away team name is too long (max 100 characters).';
                if ($scoreHome < 0 || $scoreHome > 99) $errors[] = 'Home team score must be between 0 and 99.';
                if ($scoreAway < 0 || $scoreAway > 99) $errors[] = 'Away team score must be between 0 and 99.';
                
                if (!empty($errors)) {
                    $error = implode(' ', $errors);
                } else {
                    try {
                        if ($game) {
                            // Update existing game
                            Db::execute(
                                'UPDATE games SET title = ?, home_team = ?, away_team = ?, score_home = ?, score_away = ?, updated_at = NOW() WHERE id = ?',
                                [$title, $homeTeam, $awayTeam, $scoreHome, $scoreAway, $game['id']]
                            );
                            $success = 'Game updated successfully.';
                        } else {
                            // Create new game
                            $stmt = Db::execute(
                                'INSERT INTO games (title, home_team, away_team, score_home, score_away) VALUES (?, ?, ?, ?, ?)',
                                [$title, $homeTeam, $awayTeam, $scoreHome, $scoreAway]
                            );
                            $gameId = Db::pdo()->lastInsertId();
                            $success = 'Game created successfully.';
                            
                            // Load the newly created game
                            $game = Db::fetchOne('SELECT * FROM games WHERE id = ?', [$gameId]);
                        }
                        
                        // Refresh game data
                        if ($game) {
                            $game = Db::fetchOne('SELECT * FROM games WHERE id = ?', [$game['id']]);
                        }
                    } catch (Exception $e) {
                        $error = 'Database error: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'set_live':
                if (!$game) {
                    $error = 'No game to set live.';
                } else {
                    try {
                        // Use transaction to ensure atomic operation
                        Db::pdo()->beginTransaction();
                        
                        // First, unset all other live games
                        Db::execute('UPDATE games SET is_live = 0 WHERE is_live = 1');
                        
                        // Then set this game as live
                        Db::execute('UPDATE games SET is_live = 1, updated_at = NOW() WHERE id = ?', [$game['id']]);
                        
                        Db::pdo()->commit();
                        
                        $success = 'Game is now live!';
                        
                        // Refresh game data
                        $game = Db::fetchOne('SELECT * FROM games WHERE id = ?', [$game['id']]);
                    } catch (Exception $e) {
                        Db::pdo()->rollBack();
                        $error = 'Failed to set game live: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'unset_live':
                if (!$game || !$game['is_live']) {
                    $error = 'Game is not currently live.';
                } else {
                    try {
                        Db::execute('UPDATE games SET is_live = 0, updated_at = NOW() WHERE id = ?', [$game['id']]);
                        $success = 'Game is no longer live.';
                        
                        // Refresh game data
                        $game = Db::fetchOne('SELECT * FROM games WHERE id = ?', [$game['id']]);
                    } catch (Exception $e) {
                        $error = 'Failed to unset game live: ' . $e->getMessage();
                    }
                }
                break;
        }
    }
}

renderAdminHeader($game ? 'Edit Game' : 'Create Game', 'game');

?>

<?php if ($error): ?>
    <?php showErrorMessage($error); ?>
<?php endif; ?>

<?php if ($success): ?>
    <?php showSuccessMessage($success); ?>
<?php endif; ?>

<div class="card">
    <div class="card-header"><?= $game ? 'Edit Game' : 'Create New Game' ?></div>
    <div class="card-body">
        <form method="POST">
            <?= Auth::csrfField() ?>
            <input type="hidden" name="action" value="save">
            
            <div class="form-group">
                <label for="title">Game Title/Headline *</label>
                <input 
                    type="text" 
                    id="title" 
                    name="title" 
                    value="<?= Helpers::escapeHtml($game['title'] ?? '') ?>" 
                    required 
                    maxlength="255"
                    placeholder="e.g., Devils vs Rangers - Season Opener"
                >
            </div>
            
            <div class="form-row">
                <div class="form-col">
                    <label for="away_team">Away Team *</label>
                    <input 
                        type="text" 
                        id="away_team" 
                        name="away_team" 
                        value="<?= Helpers::escapeHtml($game['away_team'] ?? '') ?>" 
                        required 
                        maxlength="100"
                        placeholder="e.g., Rangers"
                    >
                </div>
                <div class="form-col">
                    <label for="home_team">Home Team *</label>
                    <input 
                        type="text" 
                        id="home_team" 
                        name="home_team" 
                        value="<?= Helpers::escapeHtml($game['home_team'] ?? '') ?>" 
                        required 
                        maxlength="100"
                        placeholder="e.g., Devils"
                    >
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-col">
                    <label for="score_away">Away Team Score</label>
                    <input 
                        type="number" 
                        id="score_away" 
                        name="score_away" 
                        value="<?= (int)($game['score_away'] ?? 0) ?>" 
                        min="0" 
                        max="99"
                    >
                </div>
                <div class="form-col">
                    <label for="score_home">Home Team Score</label>
                    <input 
                        type="number" 
                        id="score_home" 
                        name="score_home" 
                        value="<?= (int)($game['score_home'] ?? 0) ?>" 
                        min="0" 
                        max="99"
                    >
                </div>
            </div>
            
            <div class="btn-group">
                <button type="submit" class="btn btn-primary">
                    <?= $game ? 'Update Game' : 'Create Game' ?>
                </button>
                <a href="/admin/" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php if ($game): ?>
<div class="card">
    <div class="card-header">Live Status Control</div>
    <div class="card-body">
        <?php if ($game['is_live']): ?>
            <div class="status-live mb-20">THIS GAME IS CURRENTLY LIVE</div>
            <p>This game is currently generating the live JSON feed. Updates posted to this game will appear on your website.</p>
            
            <form method="POST" style="display: inline;">
                <?= Auth::csrfField() ?>
                <input type="hidden" name="action" value="unset_live">
                <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to stop the live feed for this game?')">
                    Stop Live Feed
                </button>
            </form>
        <?php else: ?>
            <div class="status-inactive mb-20">GAME NOT LIVE</div>
            <p>Set this game as live to start generating the JSON feed. This will automatically stop any other live game.</p>
            
            <form method="POST" style="display: inline;">
                <?= Auth::csrfField() ?>
                <input type="hidden" name="action" value="set_live">
                <button type="submit" class="btn btn-success" onclick="return confirm('Set this game as live? This will stop any other live game.')">
                    Set Game Live
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if ($game['is_live']): ?>
<div class="card">
    <div class="card-header">Quick Actions</div>
    <div class="card-body">
        <div class="btn-group">
            <a href="/admin/updates.php" class="btn btn-primary">Manage Updates</a>
            <a href="/json_test.php" class="btn btn-secondary" target="_blank" rel="noopener noreferrer">View JSON Feed</a>
            <a href="/public/example.html" class="btn btn-secondary" target="_blank" rel="noopener noreferrer">Test Client</a>
        </div>
        
        <div class="btn-group" style="margin-top: 15px;">
            <form method="POST" action="/admin/update_json.php" style="display: inline;">
                <?= Auth::csrfField() ?>
                <button type="submit" class="btn btn-success btn-small">
                    🔄 Update JSON Now
                </button>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<?php renderAdminFooter(); ?>