<?php

/**
 * Admin dashboard
 * 
 * Shows current live game status, recent activity, and provides
 * quick navigation to game management and update functions.
 */

require_once '_auth.php';

// Handle session messages
$error = $_SESSION['error_message'] ?? '';
$success = $_SESSION['success_message'] ?? '';
unset($_SESSION['error_message'], $_SESSION['success_message']);

renderAdminHeader('Dashboard', 'dashboard');

// Show messages if any
if ($error) showErrorMessage($error);
if ($success) showSuccessMessage($success);

// Get current live game
$liveGame = getCurrentLiveGame();

// Get recent updates for live game
$recentUpdates = [];
if ($liveGame) {
    $recentUpdates = Db::fetchAll(
        'SELECT * FROM game_updates WHERE game_id = ? ORDER BY created_at DESC LIMIT 5',
        [$liveGame['id']]
    );
}

// Get total counts for stats
$totalGames = Db::fetchOne('SELECT COUNT(*) as count FROM games')['count'] ?? 0;
$totalUpdates = Db::fetchOne('SELECT COUNT(*) as count FROM game_updates')['count'] ?? 0;

?>

<div class="card">
    <div class="card-header">Game Status</div>
    <div class="card-body">
        <?php if ($liveGame): ?>
            <div class="status-live">LIVE GAME</div>
            <h3 class="mb-10"><?= Helpers::escapeHtml($liveGame['title']) ?></h3>
            
            <div class="score">
                <?= Helpers::escapeHtml($liveGame['away_team']) ?>
                <span class="vs">@</span>
                <?= Helpers::escapeHtml($liveGame['home_team']) ?>
            </div>
            
            <div class="score">
                <?= (int)$liveGame['score_away'] ?>
                <span class="vs">-</span>
                <?= (int)$liveGame['score_home'] ?>
            </div>
            
            <p><strong>Last Updated:</strong> 
                <?= Helpers::relativeTime(new DateTime($liveGame['updated_at'])) ?>
            </p>
            
            <div class="btn-group">
                <a href="/admin/game.php" class="btn btn-primary">Edit Game</a>
                <a href="/admin/updates.php" class="btn btn-secondary">Manage Updates</a>
            </div>
        <?php else: ?>
            <div class="status-inactive">NO LIVE GAME</div>
            <p>No game is currently live. Create or select a game to begin live updates.</p>
            <a href="/admin/game.php" class="btn btn-primary">Create Game</a>
        <?php endif; ?>
    </div>
</div>

<?php if ($liveGame && !empty($recentUpdates)): ?>
<div class="card">
    <div class="card-header">Recent Updates</div>
    <div class="card-body">
        <?php foreach ($recentUpdates as $update): ?>
            <div class="update-item">
                <div class="update-header">
                    <span class="update-type update-type-<?= $update['type'] ?>">
                        <?= strtoupper(str_replace('_', ' ', $update['type'])) ?>
                    </span>
                    <span class="update-time">
                        <?= Helpers::relativeTime(new DateTime($update['created_at'])) ?>
                    </span>
                </div>
                <div class="update-content">
                    <?php if ($update['type'] === 'html'): ?>
                        <?= $update['content'] ?>
                    <?php else: ?>
                        <a href="<?= Helpers::escapeHtml($update['url']) ?>" target="_blank" rel="noopener noreferrer">
                            <?= Helpers::escapeHtml($update['url']) ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
        
        <div class="text-center mt-20">
            <a href="/admin/updates.php" class="btn btn-secondary">View All Updates</a>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">System Stats</div>
    <div class="card-body">
        <div class="form-row">
            <div class="form-col text-center">
                <h3><?= $totalGames ?></h3>
                <p>Total Games</p>
            </div>
            <div class="form-col text-center">
                <h3><?= $totalUpdates ?></h3>
                <p>Total Updates</p>
            </div>
            <div class="form-col text-center">
                <h3><?= $liveGame ? count($recentUpdates) : 0 ?></h3>
                <p>Current Game Updates</p>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">Quick Actions</div>
    <div class="card-body">
        <div class="btn-group">
            <a href="/admin/game.php" class="btn btn-primary">
                <?= $liveGame ? 'Edit Current Game' : 'Create New Game' ?>
            </a>
            <a href="/admin/updates.php" class="btn btn-secondary">
                Manage Updates
            </a>
            <a href="/current.json" class="btn btn-secondary" target="_blank" rel="noopener noreferrer">
                View JSON Feed
            </a>
        </div>
        
        <div class="btn-group" style="margin-top: 15px;">
            <form method="POST" action="/admin/update_json.php" style="display: inline;">
                <?= Auth::csrfField() ?>
                <button type="submit" class="btn btn-success" onclick="return confirm('Manually update the JSON feed now?')">
                    🔄 Update JSON Feed
                </button>
            </form>
        </div>
    </div>
</div>

<?php renderAdminFooter(); ?>