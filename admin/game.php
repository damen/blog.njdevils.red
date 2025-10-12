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
                
                // Lineups (plain text, normalize line endings to LF)
                $homeLineupText = $_POST['home_lineup_text'] ?? '';
                $awayLineupText = $_POST['away_lineup_text'] ?? '';
                $homeLineupText = str_replace(["\r\n", "\r"], "\n", $homeLineupText);
                $awayLineupText = str_replace(["\r\n", "\r"], "\n", $awayLineupText);
                $homeLineupText = rtrim($homeLineupText);
                $awayLineupText = rtrim($awayLineupText);
                
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
                                'UPDATE games SET title = ?, home_team = ?, away_team = ?, score_home = ?, score_away = ?, home_lineup_text = ?, away_lineup_text = ?, updated_at = NOW() WHERE id = ?',
                                [$title, $homeTeam, $awayTeam, $scoreHome, $scoreAway, $homeLineupText, $awayLineupText, $game['id']]
                            );
                            $success = 'Game updated successfully.';
                        } else {
                            // Create new game
                            $stmt = Db::execute(
                                'INSERT INTO games (title, home_team, away_team, score_home, score_away, home_lineup_text, away_lineup_text) VALUES (?, ?, ?, ?, ?, ?, ?)',
                                [$title, $homeTeam, $awayTeam, $scoreHome, $scoreAway, $homeLineupText, $awayLineupText]
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

            case 'add_update':
                if (!$game) {
                    $error = 'Create or select a game first before adding updates.';
                    break;
                }
                $type = $_POST['type'] ?? '';
                $content = trim($_POST['content'] ?? '');
                $url = trim($_POST['url'] ?? '');

                $validTypes = ['html', 'nhl_goal', 'youtube'];
                if (!in_array($type, $validTypes, true)) {
                    $error = 'Invalid update type.';
                    break;
                }

                $sanitizedContent = null;
                $sanitizedUrl = null;
                switch ($type) {
                    case 'html':
                        if ($content === '') { $error = 'HTML content is required.'; break; }
                        $sanitizedContent = Sanitizer::sanitizeHtml($content);
                        if ($sanitizedContent === null) { $error = 'HTML content is invalid or too long (max 1000 characters).'; }
                        break;
                    case 'nhl_goal':
                        if ($url === '') { $error = 'NHL goal URL is required.'; break; }
                        $sanitizedUrl = Sanitizer::sanitizeUrl($url, 'nhl_goal');
                        if ($sanitizedUrl === null) { $error = 'Invalid NHL URL. Must be HTTPS and from nhl.com domain.'; }
                        break;
                    case 'youtube':
                        if ($url === '') { $error = 'YouTube URL is required.'; break; }
                        $sanitizedUrl = Sanitizer::sanitizeUrl($url, 'youtube');
                        if ($sanitizedUrl === null) { $error = 'Invalid YouTube URL. Must be HTTPS and from an allowed YouTube domain.'; }
                        break;
                }

                if (empty($error)) {
                    try {
                        Db::execute(
                            'INSERT INTO game_updates (game_id, type, content, url) VALUES (?, ?, ?, ?)',
                            [$game['id'], $type, $sanitizedContent, $sanitizedUrl]
                        );
                        $success = 'Update added successfully.';
                        // Clear POST fields so the form resets
                        $_POST = [];
                    } catch (Exception $e) {
                        $error = 'Database error: ' . $e->getMessage();
                    }
                }
                break;

            case 'delete_update':
                if (!$game) {
                    $error = 'No game selected.';
                    break;
                }
                $updateId = $_POST['update_id'] ?? '';
                if (!is_numeric($updateId) || (int)$updateId <= 0) {
                    $error = 'Invalid update ID.';
                    break;
                }
                try {
                    $update = Db::fetchOne('SELECT * FROM game_updates WHERE id = ? AND game_id = ?', [(int)$updateId, $game['id']]);
                    if (!$update) {
                        $error = 'Update not found for this game.';
                        break;
                    }
                    Db::execute('DELETE FROM game_updates WHERE id = ?', [(int)$updateId]);
                    $success = 'Update deleted successfully.';
                } catch (Exception $e) {
                    $error = 'Database error: ' . $e->getMessage();
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
            
            <div class="form-group">
                <label for="home_lineup_text">Home lineup (multi-line text)</label>
                <textarea id="home_lineup_text" name="home_lineup_text" rows="8" placeholder="Enter plain text. One line per player or any format."><?= Helpers::escapeHtml($game['home_lineup_text'] ?? '') ?></textarea>
                <small class="form-text">Enter plain text. Use one line per player or any format you prefer. No parsing is performed.</small>
            </div>
            
            <div class="form-group">
                <label for="away_lineup_text">Away lineup (multi-line text)</label>
                <textarea id="away_lineup_text" name="away_lineup_text" rows="8" placeholder="Enter plain text. One line per player or any format."><?= Helpers::escapeHtml($game['away_lineup_text'] ?? '') ?></textarea>
                <small class="form-text">Enter plain text. Use one line per player or any format you prefer. No parsing is performed.</small>
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
            <a href="/current.json" class="btn btn-secondary" target="_blank" rel="noopener noreferrer">View JSON Feed</a>
            <a href="/example.html" class="btn btn-secondary" target="_blank" rel="noopener noreferrer">Test Client</a>
        </div>
        
        <div class="btn-group" style="margin-top: 15px;">
            <form id="update-json-form" method="POST" action="/admin/update_json.php" style="display: inline;">
                <?= Auth::csrfField() ?>
                <button type="submit" class="btn btn-success btn-small">
                    🔄 Update JSON Now
                </button>
            </form>
            <pre id="update-json-output" style="white-space:pre-wrap;background:#111;color:#eee;padding:8px;border-radius:4px;min-height:3em;margin-top:10px;display:none;"></pre>
        </div>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<?php
// Load updates for this game (newest first)
$updates = [];
if ($game) {
    try {
        $updates = Db::fetchAll(
            'SELECT * FROM game_updates WHERE game_id = ? ORDER BY created_at DESC',
            [$game['id']]
        );
    } catch (Exception $e) {
        $updates = [];
    }
}
?>

<?php if ($game): ?>
<div class="card">
    <div class="card-header">Manage Updates for "<?= Helpers::escapeHtml($game['title']) ?>"</div>
    <div class="card-body">
        <form method="POST" id="inlineUpdateForm" style="margin-bottom: 20px;">
            <?= Auth::csrfField() ?>
            <input type="hidden" name="action" value="add_update">

            <div class="form-group">
                <label>Update Type *</label>
                <div class="radio-group">
                    <div class="radio-option">
                        <input type="radio" id="inline_type_html" name="type" value="html" <?= ($_POST['type'] ?? 'html') === 'html' ? 'checked' : '' ?> onchange="inlineToggleUpdateFields()">
                        <label for="inline_type_html">HTML Content</label>
                    </div>
                    <div class="radio-option">
                        <input type="radio" id="inline_type_nhl_goal" name="type" value="nhl_goal" <?= ($_POST['type'] ?? '') === 'nhl_goal' ? 'checked' : '' ?> onchange="inlineToggleUpdateFields()">
                        <label for="inline_type_nhl_goal">NHL Goal Visualizer</label>
                    </div>
                    <div class="radio-option">
                        <input type="radio" id="inline_type_youtube" name="type" value="youtube" <?= ($_POST['type'] ?? '') === 'youtube' ? 'checked' : '' ?> onchange="inlineToggleUpdateFields()">
                        <label for="inline_type_youtube">YouTube Video</label>
                    </div>
                </div>
            </div>

            <div class="form-group" id="inline_content_field">
                <label for="inline_content">HTML Content *</label>
                <textarea id="inline_content" name="content" maxlength="1000" placeholder="Enter HTML or plain text. Allowed tags: a, p, br, strong, em, ul, ol, li, blockquote" oninput="inlineUpdateCharCount('inline_content', 1000)"><?= Helpers::escapeHtml($_POST['content'] ?? '') ?></textarea>
                <div class="char-count" id="inline_content_count">0 / 1000 characters</div>
            </div>

            <div class="form-group d-none" id="inline_url_field">
                <label for="inline_url" id="inline_url_label">URL *</label>
                <input type="url" id="inline_url" name="url" value="<?= Helpers::escapeHtml($_POST['url'] ?? '') ?>" maxlength="1000" placeholder="https://">
                <small id="inline_url_help" class="form-text"></small>
            </div>

            <div class="btn-group">
                <button type="submit" class="btn btn-primary">Add Update</button>
            </div>
        </form>

        <div class="card" style="box-shadow:none; border:1px solid #eee;">
            <div class="card-header">All Updates (<?= count($updates) ?>)</div>
            <div class="card-body">
                <?php if (empty($updates)): ?>
                    <p>No updates yet. Add your first update above.</p>
                <?php else: ?>
                    <?php foreach ($updates as $update): ?>
                        <div class="update-item">
                            <div class="update-header">
                                <span class="update-type update-type-<?= $update['type'] ?>"><?= strtoupper(str_replace('_', ' ', $update['type'])) ?></span>
                                <span class="update-time"><?= Helpers::relativeTime(new DateTime($update['created_at'])) ?></span>
                                <form method="POST" style="display:inline;">
                                    <?= Auth::csrfField() ?>
                                    <input type="hidden" name="action" value="delete_update">
                                    <input type="hidden" name="update_id" value="<?= (int)$update['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-small" onclick="return confirm('Delete this update?')">Delete</button>
                                </form>
                            </div>
                            <div class="update-content">
                                <?php if ($update['type'] === 'html'): ?>
                                    <?= $update['content'] ?>
                                <?php else: ?>
                                    <?php if ($update['type'] === 'youtube'): ?>
                                        <?php $embedUrl = Helpers::youtubeEmbedUrl($update['url']); ?>
                                        <?php if ($embedUrl): ?>
                                            <div style="margin-top: 10px;">
<iframe width="300" height="200" src="<?= Helpers::escapeHtml($embedUrl) ?>" frameborder="0" allowfullscreen style="max-width: 100%;"></iframe>
                                            </div>
                                        <?php else: ?>
                                            <em>YouTube video</em>
                                        <?php endif; ?>
                                    <?php elseif ($update['type'] === 'nhl_goal'): ?>
                                        <div style="margin-top: 10px;">
<iframe src="<?= Helpers::escapeHtml($update['url']) ?>" height="400" style="width: 100%; max-width: 600px; border: 0;" allow="clipboard-write *; fullscreen *"></iframe>
                                            <p><small><em>Goal visualizer will display full-size (825px height) on the live page</em></small></p>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function inlineToggleUpdateFields() {
    const contentField = document.getElementById('inline_content_field');
    const urlField = document.getElementById('inline_url_field');
    const urlLabel = document.getElementById('inline_url_label');
    const urlHelp = document.getElementById('inline_url_help');
    const urlInput = document.getElementById('inline_url');

    const selected = document.querySelector('input[name="type"]:checked').value;
    if (selected === 'html') {
        contentField.classList.remove('d-none');
        urlField.classList.add('d-none');
        urlInput.required = false;
    } else {
        contentField.classList.add('d-none');
        urlField.classList.remove('d-none');
        urlInput.required = true;
        if (selected === 'nhl_goal') {
            urlLabel.textContent = 'NHL Goal Visualizer URL *';
            urlHelp.textContent = 'Must be an HTTPS URL from nhl.com';
            urlInput.placeholder = 'https://www.nhl.com/ppt-replay/goal/...';
        } else if (selected === 'youtube') {
            urlLabel.textContent = 'YouTube URL *';
            urlHelp.textContent = 'YouTube video URL (youtube.com, youtu.be)';
            urlInput.placeholder = 'https://www.youtube.com/watch?v=...';
        }
    }
}
function inlineUpdateCharCount(id, max) {
    const el = document.getElementById(id);
    const counter = document.getElementById(id + '_count');
    const len = el.value.length;
    counter.textContent = `${len} / ${max} characters`;
    counter.classList.toggle('over-limit', len > max);
}

document.addEventListener('DOMContentLoaded', function() {
    inlineToggleUpdateFields();
    inlineUpdateCharCount('inline_content', 1000);
    const form = document.getElementById('update-json-form');
    if (form) {
        const out = document.getElementById('update-json-output');
        form.addEventListener('submit', async function(e){
            e.preventDefault();
            out.style.display = 'block';
            out.textContent = 'Running update.php...';
            const fd = new FormData(form);
            try {
                const res = await fetch(form.action, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd });
                const ct = res.headers.get('content-type') || '';
                if (!res.ok) {
                    const t = await res.text();
                    throw new Error('HTTP ' + res.status + ' ' + res.statusText + (t ? ('\n' + t) : ''));
                }
                let text;
                if (ct.includes('application/json')) {
                    const data = await res.json();
                    text = 'exitCode: ' + (data.exitCode ?? 'n/a');
                    if (data.stdout) text += '\n\n' + data.stdout;
                    if (data.stderr) text += '\n\nSTDERR:\n' + data.stderr;
                    if (data.error && !data.ok) text += '\n\nError: ' + data.error;
                } else {
                    const t = await res.text();
                    text = 'Non-JSON response:\n' + t;
                }
                out.textContent = text;
            } catch (err) {
                out.textContent = 'Request failed: ' + err;
            }
        });
    }
});
</script>
<?php endif; ?>

<?php
// Fetch all games for listing
try {
    $allGames = Db::fetchAll('SELECT * FROM games ORDER BY created_at DESC');
} catch (Exception $e) {
    $allGames = [];
}
?>

<div class="card">
    <div class="card-header">All Games</div>
    <div class="card-body">
        <?php if (empty($allGames)): ?>
            <p>No games found. Create a new game above.</p>
        <?php else: ?>
            <div style="overflow-x:auto;">
                <table style="width:100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th style="text-align:left; padding:8px; border-bottom: 1px solid #ddd;">Title</th>
                            <th style="text-align:left; padding:8px; border-bottom: 1px solid #ddd;">Teams</th>
                            <th style="text-align:left; padding:8px; border-bottom: 1px solid #ddd;">Score</th>
                            <th style="text-align:left; padding:8px; border-bottom: 1px solid #ddd;">Status</th>
                            <th style="text-align:left; padding:8px; border-bottom: 1px solid #ddd;">Updated</th>
                            <th style="text-align:left; padding:8px; border-bottom: 1px solid #ddd;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allGames as $g): ?>
                            <tr>
                                <td style="padding:8px; border-bottom: 1px solid #f0f0f0;">
                                    <a href="/admin/game.php?id=<?= (int)$g['id'] ?>">
                                        <?= Helpers::escapeHtml($g['title']) ?>
                                    </a>
                                </td>
                                <td style="padding:8px; border-bottom: 1px solid #f0f0f0;">
                                    <?= Helpers::escapeHtml($g['away_team']) ?> @ <?= Helpers::escapeHtml($g['home_team']) ?>
                                </td>
                                <td style="padding:8px; border-bottom: 1px solid #f0f0f0;">
                                    <?= (int)$g['score_away'] ?> - <?= (int)$g['score_home'] ?>
                                </td>
                                <td style="padding:8px; border-bottom: 1px solid #f0f0f0;">
                                    <?php if ((int)$g['is_live'] === 1): ?>
                                        <span class="status-live">LIVE</span>
                                    <?php else: ?>
                                        <span class="status-inactive">—</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding:8px; border-bottom: 1px solid #f0f0f0;">
                                    <?= Helpers::relativeTime(new DateTime($g['updated_at'])) ?>
                                </td>
                                <td style="padding:8px; border-bottom: 1px solid #f0f0f0; white-space:nowrap;">
                                    <a class="btn btn-secondary btn-small" href="/admin/game.php?id=<?= (int)$g['id'] ?>">Edit</a>
                                    <?php if ((int)$g['is_live'] === 1): ?>
                                        <form method="POST" action="/admin/game.php?id=<?= (int)$g['id'] ?>" style="display:inline; margin-left:6px;">
                                            <?= Auth::csrfField() ?>
                                            <input type="hidden" name="action" value="unset_live">
                                            <button type="submit" class="btn btn-danger btn-small" onclick="return confirm('Stop live feed for this game?')">Unset Live</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" action="/admin/game.php?id=<?= (int)$g['id'] ?>" style="display:inline; margin-left:6px;">
                                            <?= Auth::csrfField() ?>
                                            <input type="hidden" name="action" value="set_live">
                                            <button type="submit" class="btn btn-success btn-small" onclick="return confirm('Set this game as live? This will stop any other live game.')">Set Live</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php renderAdminFooter(); ?>
