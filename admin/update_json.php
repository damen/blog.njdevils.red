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
        if (function_exists('header_remove')) { header_remove('Content-Type'); }
        header('Content-Type: application/json');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        echo json_encode(['ok' => false, 'error' => 'Invalid security token. Please reload and try again.']);
        exit;
    }
    $_SESSION['error_message'] = 'Invalid security token. Please try again.';
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/admin/'));
    exit;
}

try {
    // Execute the update script with robust fallbacks
    $updateScriptPath = __DIR__ . '/../update.php';
    $stdout = '';
    $stderr = '';
    $exitCode = 0;

    // Helper to check if a function is disabled
    $isDisabled = static function(string $fn): bool {
        $disabled = array_map('trim', explode(',', (string)ini_get('disable_functions')));
        return !function_exists($fn) || in_array($fn, $disabled, true);
    };

    // Resolve PHP binary
    $phpCandidates = [];
    if (defined('PHP_BINARY') && PHP_BINARY) { $phpCandidates[] = PHP_BINARY; }
    $phpCandidates = array_merge($phpCandidates, [
        '/usr/bin/php', '/usr/local/bin/php', '/bin/php',
        '/usr/bin/php8.3', '/usr/bin/php8.2', '/usr/bin/php8.1', '/usr/bin/php8.0'
    ]);
    $phpBin = null;
    foreach ($phpCandidates as $cand) {
        if (is_file($cand) && is_executable($cand)) { $phpBin = $cand; break; }
    }
    if ($phpBin === null) { $phpBin = 'php'; }

    // Prefer proc_open when available
    if (!$isDisabled('proc_open')) {
        $descriptors = [
            1 => ['pipe', 'w'], // stdout
            2 => ['pipe', 'w'], // stderr
        ];
        $cwd = dirname(__DIR__);
        $cmd = [$phpBin, $updateScriptPath];
        $proc = proc_open($cmd, $descriptors, $pipes, $cwd);
        if (is_resource($proc)) {
            $stdout = stream_get_contents($pipes[1]); fclose($pipes[1]);
            $stderr = stream_get_contents($pipes[2]); fclose($pipes[2]);
            $exitCode = proc_close($proc);
        } else {
            $exitCode = 1;
            $stderr = 'Failed to start process with proc_open';
        }
    } elseif (!$isDisabled('exec')) {
        $output = [];
        $cmd = escapeshellarg($phpBin) . ' ' . escapeshellarg($updateScriptPath) . ' 2>&1';
        @exec($cmd, $output, $exitCode);
        $stdout = implode("\n", (array)$output);
        $stderr = '';
    } else {
        // Fallback: include the script and capture output buffer
        $cwd = getcwd();
        chdir(dirname(__DIR__));
        ob_start();
        try {
            include $updateScriptPath; // Will echo JSON when not CLI; acceptable for capturing
            $stdout = ob_get_clean();
            $exitCode = 0;
        } catch (Throwable $t) {
            $stdout = ob_get_clean();
            $stderr = $t->getMessage();
            $exitCode = 1;
        } finally {
            if ($cwd) { chdir($cwd); }
        }
    }

    if ($isAjax) {
        // Ensure correct content type overrides any defaults from bootstrap
        if (function_exists('header_remove')) { header_remove('Content-Type'); }
        header('Content-Type: application/json');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        echo json_encode([
            'ok' => $exitCode === 0,
            'exitCode' => $exitCode,
            'stdout' => $stdout,
            'stderr' => $stderr,
        ]);
        exit;
    }
    
    if ($exitCode === 0) {
        $_SESSION['success_message'] = 'JSON feed updated successfully! Generated at ' . date('g:i:s A');
    } else {
        $_SESSION['error_message'] = 'Failed to update JSON feed. ' . ($stderr ?: $stdout);
    }
    
} catch (Exception $e) {
    if ($isAjax) {
        if (function_exists('header_remove')) { header_remove('Content-Type'); }
        header('Content-Type: application/json');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        echo json_encode(['ok' => false, 'error' => 'Error updating JSON feed: ' . $e->getMessage()]);
        exit;
    }
    $_SESSION['error_message'] = 'Error updating JSON feed: ' . $e->getMessage();
}

// Redirect back to the referring page or dashboard
$redirectUrl = $_SERVER['HTTP_REFERER'] ?? '/admin/';
header('Location: ' . $redirectUrl);
exit;
