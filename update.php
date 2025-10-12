<?php

/**
 * Game Day JSON Feed Generator
 * 
 * This script queries the database for the current live game and its updates,
 * then generates a JSON feed that is consumed by client-side JavaScript.
 * 
 * Designed to run via cron every minute, but also accessible via web for testing.
 * Writes output atomically to prevent partial file reads.
 */

// Determine script path for bootstrap inclusion
$bootstrapPath = __DIR__ . '/src/bootstrap.php';

// Only include bootstrap functionality, no auth required
try {
    require_once __DIR__ . '/src/Env.php';
    require_once __DIR__ . '/src/Db.php';
    require_once __DIR__ . '/src/Helpers.php';
    
    // Load environment configuration
    Env::load(__DIR__ . '/.env');
    
    // Set timezone
    Helpers::setDefaultTimezone();
    
    // Initialize database connection
    $pdo = Db::pdo();
    
} catch (Exception $e) {
    // Log error and exit gracefully
    error_log('update.php bootstrap failed: ' . $e->getMessage());
    exit(1);
}

// Get output path from environment
$outputPath = Env::get('JSON_OUTPUT_PATH', './public/current.json');

// Make path absolute if relative
if (!str_starts_with($outputPath, '/')) {
    $outputPath = __DIR__ . '/' . $outputPath;
}

try {
    // Get current live game
    $liveGame = Db::fetchOne('SELECT * FROM games WHERE is_live = 1 LIMIT 1');
    
    $now = new DateTime();
    $generatedAt = $now->format('c'); // ISO 8601 format
    
    if (!$liveGame) {
        // No live game - output minimal status
        $data = [
            'status' => 'no_live_game',
            'cache_control' => 'no-store',
            'generated_at' => $generatedAt
        ];
    } else {
        // Get updates for the live game (ordered by creation time)
        $updates = Db::fetchAll(
            'SELECT * FROM game_updates WHERE game_id = ? ORDER BY created_at ASC',
            [$liveGame['id']]
        );
        
        // Process updates and add relative time
        $processedUpdates = [];
        foreach ($updates as $update) {
            $createdAt = new DateTime($update['created_at']);
            $relativeTime = Helpers::relativeTime($createdAt, $now);
            
            $processedUpdate = [
                'id' => (int)$update['id'],
                'type' => $update['type'],
                'created_at' => $createdAt->format('c'),
                'relative_time' => $relativeTime
            ];
            
            // Add content based on type
            switch ($update['type']) {
                case 'html':
                    $processedUpdate['html'] = $update['content'];
                    break;
                    
                case 'nhl_goal':
                case 'youtube':
                    $processedUpdate['url'] = $update['url'];
                    
                    // For YouTube, also provide embed URL
                    if ($update['type'] === 'youtube') {
                        $embedUrl = Helpers::youtubeEmbedUrl($update['url']);
                        if ($embedUrl) {
                            $processedUpdate['embed_url'] = $embedUrl;
                        }
                    }
                    break;
            }
            
            $processedUpdates[] = $processedUpdate;
        }
        
        // Build complete game data
        $lastUpdated = new DateTime($liveGame['updated_at']);
        
        $data = [
            'cache_control' => 'no-store',
            'generated_at' => $generatedAt,
            'game' => [
                'title' => $liveGame['title'],
                'home_team' => $liveGame['home_team'],
                'away_team' => $liveGame['away_team'],
                'score' => [
                    'home' => (int)$liveGame['score_home'],
                    'away' => (int)$liveGame['score_away']
                ],
                'home_lineup' => (string)($liveGame['home_lineup_text'] ?? ''),
                'away_lineup' => (string)($liveGame['away_lineup_text'] ?? ''),
                'last_updated' => $lastUpdated->format('c')
            ],
            'updates' => $processedUpdates
        ];
    }
    
    // Write JSON atomically
    Helpers::atomicWriteJson($outputPath, $data);
    
    // If running via CLI, output success message
    if (php_sapi_name() === 'cli') {
        echo "JSON feed updated successfully: " . $outputPath . "\n";
        echo "Generated at: " . $generatedAt . "\n";
        echo "Status: " . ($liveGame ? 'Live game active' : 'No live game') . "\n";
        if ($liveGame) {
            echo "Updates: " . count($processedUpdates) . "\n";
        }
    } else {
        // If accessed via web, return JSON with appropriate headers
        header('Content-Type: application/json');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }
    
} catch (Exception $e) {
    error_log('update.php execution failed: ' . $e->getMessage());
    
    if (php_sapi_name() === 'cli') {
        echo "Error: " . $e->getMessage() . "\n";
        exit(1);
    } else {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'Internal server error',
            'generated_at' => (new DateTime())->format('c')
        ]);
    }
}