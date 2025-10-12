<?php

/**
 * Updates management page
 * 
 * Allows adding updates to the current live game and viewing all updates.
 * Supports HTML content, NHL goal visualizer URLs, and YouTube embeds.
 */

require_once '_auth.php';

$error = $_SESSION['error_message'] ?? '';
$success = $_SESSION['success_message'] ?? '';

// Clear session messages
unset($_SESSION['error_message'], $_SESSION['success_message']);

// Get current live game
$liveGame = getCurrentLiveGame();

// If no live game, redirect to game management
if (!$liveGame) {
    header('Location: /admin/game.php');
    exit;
}

// Process form submission for adding new update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';
    
    // Validate CSRF token
    if (!Auth::csrfValidate($csrfToken)) {
        $error = 'Invalid security token. Please try again.';
    } else {
        switch ($action) {
            case 'add_update':
                $type = $_POST['type'] ?? '';
                $content = trim($_POST['content'] ?? '');
                $url = trim($_POST['url'] ?? '');
                
                // Validate update type
                $validTypes = ['html', 'nhl_goal', 'youtube'];
                if (!in_array($type, $validTypes)) {
                    $error = 'Invalid update type.';
                    break;
                }
                
                // Validate content based on type
                $sanitizedContent = null;
                $sanitizedUrl = null;
                
                switch ($type) {
                    case 'html':
                        if (empty($content)) {
                            $error = 'HTML content is required.';
                            break;
                        }
                        
                        $sanitizedContent = Sanitizer::sanitizeHtml($content);
                        if ($sanitizedContent === null) {
                            $error = 'HTML content is invalid or too long (max 1000 characters).';
                            break;
                        }
                        break;
                        
                    case 'nhl_goal':
                        if (empty($url)) {
                            $error = 'NHL goal URL is required.';
                            break;
                        }
                        
                        $sanitizedUrl = Sanitizer::sanitizeUrl($url, 'nhl_goal');
                        if ($sanitizedUrl === null) {
                            $error = 'Invalid NHL URL. Must be HTTPS and from nhl.com domain.';
                            break;
                        }
                        break;
                        
                    case 'youtube':
                        if (empty($url)) {
                            $error = 'YouTube URL is required.';
                            break;
                        }
                        
                        $sanitizedUrl = Sanitizer::sanitizeUrl($url, 'youtube');
                        if ($sanitizedUrl === null) {
                            $error = 'Invalid YouTube URL. Must be HTTPS and from an allowed YouTube domain.';
                            break;
                        }
                        break;
                }
                
                // If validation passed, insert the update
                if (empty($error)) {
                    try {
                        Db::execute(
                            'INSERT INTO game_updates (game_id, type, content, url) VALUES (?, ?, ?, ?)',
                            [$liveGame['id'], $type, $sanitizedContent, $sanitizedUrl]
                        );
                        
                        $success = 'Update added successfully.';
                        
                        // Clear form data after successful submission
                        $_POST = [];
                        
                    } catch (Exception $e) {
                        $error = 'Database error: ' . $e->getMessage();
                    }
                }
                break;
        }
    }
}

// Get all updates for current live game (newest first)
$updates = Db::fetchAll(
    'SELECT * FROM game_updates WHERE game_id = ? ORDER BY created_at DESC',
    [$liveGame['id']]
);

renderAdminHeader('Manage Updates', 'updates');

?>

<div class="card">
    <div class="card-header">Current Live Game</div>
    <div class="card-body">
        <div class="status-live mb-10">LIVE</div>
        <h3><?= Helpers::escapeHtml($liveGame['title']) ?></h3>
        <div class="score">
            <?= Helpers::escapeHtml($liveGame['away_team']) ?> 
            <?= (int)$liveGame['score_away'] ?>
            <span class="vs">-</span>
            <?= (int)$liveGame['score_home'] ?>
            <?= Helpers::escapeHtml($liveGame['home_team']) ?>
        </div>
        <p><small>Last updated: <?= Helpers::relativeTime(new DateTime($liveGame['updated_at'])) ?></small></p>
        
        <div class="mt-20">
            <form id="update-json-form" method="POST" action="/admin/update_json.php" style="display: inline; margin-right: 10px;">
                <?= Auth::csrfField() ?>
                <button type="submit" class="btn btn-success btn-small">
                    🔄 Update JSON Feed
                </button>
            </form>
            <pre id="update-json-output" style="white-space:pre-wrap;background:#111;color:#eee;padding:8px;border-radius:4px;min-height:3em;margin-top:10px;display:none;"></pre>
            <a href="/current.json" class="btn btn-secondary btn-small" target="_blank" rel="noopener noreferrer">
                View JSON
            </a>
        </div>
    </div>
</div>

<?php if ($error): ?>
    <?php showErrorMessage($error); ?>
<?php endif; ?>

<?php if ($success): ?>
    <?php showSuccessMessage($success); ?>
<?php endif; ?>

<div class="card">
    <div class="card-header">Add New Update</div>
    <div class="card-body">
        <form method="POST" id="updateForm">
            <?= Auth::csrfField() ?>
            <input type="hidden" name="action" value="add_update">
            
            <div class="form-group">
                <label>Update Type *</label>
                <div class="radio-group">
                    <div class="radio-option">
                        <input type="radio" id="type_html" name="type" value="html" 
                               <?= ($_POST['type'] ?? 'html') === 'html' ? 'checked' : '' ?>
                               onchange="toggleUpdateFields()">
                        <label for="type_html">HTML Content</label>
                    </div>
                    <div class="radio-option">
                        <input type="radio" id="type_nhl_goal" name="type" value="nhl_goal"
                               <?= ($_POST['type'] ?? '') === 'nhl_goal' ? 'checked' : '' ?>
                               onchange="toggleUpdateFields()">
                        <label for="type_nhl_goal">NHL Goal Visualizer</label>
                    </div>
                    <div class="radio-option">
                        <input type="radio" id="type_youtube" name="type" value="youtube"
                               <?= ($_POST['type'] ?? '') === 'youtube' ? 'checked' : '' ?>
                               onchange="toggleUpdateFields()">
                        <label for="type_youtube">YouTube Video</label>
                    </div>
                </div>
            </div>
            
            <div class="form-group" id="content_field">
                <label for="content">HTML Content *</label>
                <textarea 
                    id="content" 
                    name="content" 
                    maxlength="1000"
                    placeholder="Enter HTML content. Allowed tags: a, p, br, strong, em, ul, ol, li, blockquote"
                    oninput="updateCharCount('content', 1000)"
                ><?= Helpers::escapeHtml($_POST['content'] ?? '') ?></textarea>
                <div class="char-count" id="content_count">0 / 1000 characters</div>
            </div>
            
            <div class="form-group d-none" id="url_field">
                <label for="url" id="url_label">URL *</label>
                <input 
                    type="url" 
                    id="url" 
                    name="url" 
                    value="<?= Helpers::escapeHtml($_POST['url'] ?? '') ?>"
                    maxlength="1000"
                    placeholder="https://"
                >
                <small id="url_help" class="form-text"></small>
            </div>
            
            <div class="btn-group">
                <button type="submit" class="btn btn-primary">Add Update</button>
                <button type="button" class="btn btn-secondary" onclick="clearForm()">Clear</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">All Updates (<?= count($updates) ?>)</div>
    <div class="card-body">
        <?php if (empty($updates)): ?>
            <p>No updates yet. Add your first update above.</p>
        <?php else: ?>
            <?php foreach ($updates as $update): ?>
                <div class="update-item">
                    <div class="update-header">
                        <span class="update-type update-type-<?= $update['type'] ?>">
                            <?= strtoupper(str_replace('_', ' ', $update['type'])) ?>
                        </span>
                        <span class="update-time">
                            <?= Helpers::relativeTime(new DateTime($update['created_at'])) ?>
                        </span>
                        <form method="POST" action="/admin/update_delete.php" style="display: inline;">
                            <?= Auth::csrfField() ?>
                            <input type="hidden" name="update_id" value="<?= $update['id'] ?>">
                            <button 
                                type="submit" 
                                class="btn btn-danger btn-small"
                                onclick="return confirm('Are you sure you want to delete this update?')"
                            >
                                Delete
                            </button>
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
                                    <iframe 
                                        width="300" 
                                        height="200" 
src="<?= Helpers::escapeHtml($embedUrl) ?>
                                        frameborder="0" 
                                        allowfullscreen
                                        style="max-width: 100%;"
                                    ></iframe>
                                </div>
                            <?php else: ?>
                                <em>YouTube video</em>
                            <?php endif; ?>
                        <?php elseif ($update['type'] === 'nhl_goal'): ?>
                            <div style="margin-top: 10px;">
                                <iframe 
src="<?= Helpers::escapeHtml($update['url']) ?>
                                    height="400" 
                                    style="width: 100%; max-width: 600px; border: 0;" 
                                    allow="clipboard-write *; fullscreen *"
                                ></iframe>
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

<script>
function toggleUpdateFields() {
    const contentField = document.getElementById('content_field');
    const urlField = document.getElementById('url_field');
    const urlLabel = document.getElementById('url_label');
    const urlHelp = document.getElementById('url_help');
    const urlInput = document.getElementById('url');
    
    const selectedType = document.querySelector('input[name="type"]:checked').value;
    
    if (selectedType === 'html') {
        contentField.classList.remove('d-none');
        urlField.classList.add('d-none');
        urlInput.required = false;
    } else {
        contentField.classList.add('d-none');
        urlField.classList.remove('d-none');
        urlInput.required = true;
        
        if (selectedType === 'nhl_goal') {
            urlLabel.textContent = 'NHL Goal Visualizer URL *';
            urlHelp.textContent = 'Must be an HTTPS URL from nhl.com (e.g., https://www.nhl.com/ppt-replay/goal/2025020026/875)';
            urlInput.placeholder = 'https://www.nhl.com/ppt-replay/goal/...';
        } else if (selectedType === 'youtube') {
            urlLabel.textContent = 'YouTube URL *';
            urlHelp.textContent = 'YouTube video URL (youtube.com, youtu.be)';
            urlInput.placeholder = 'https://www.youtube.com/watch?v=...';
        }
    }
}

function updateCharCount(fieldId, maxChars) {
    const field = document.getElementById(fieldId);
    const counter = document.getElementById(fieldId + '_count');
    const currentLength = field.value.length;
    
    counter.textContent = `${currentLength} / ${maxChars} characters`;
    
    if (currentLength > maxChars) {
        counter.classList.add('over-limit');
    } else {
        counter.classList.remove('over-limit');
    }
}

function clearForm() {
    document.getElementById('updateForm').reset();
    document.getElementById('type_html').checked = true;
    toggleUpdateFields();
    updateCharCount('content', 1000);
}

// Initialize form on page load
document.addEventListener('DOMContentLoaded', function() {
    toggleUpdateFields();
    updateCharCount('content', 1000);
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

<?php renderAdminFooter(); ?>