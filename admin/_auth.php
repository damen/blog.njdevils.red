<?php

/**
 * Authentication middleware for admin pages
 * 
 * This file should be included at the top of all protected admin pages.
 * It enforces authentication requirements and provides common layout helpers.
 */

// Bootstrap the application
require_once __DIR__ . '/../src/bootstrap.php';

// Require authentication for all admin pages
Auth::requireAuth();

// Set cache headers to prevent caching of admin pages
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

/**
 * Render the admin page header with navigation
 * 
 * @param string $pageTitle Page title
 * @param string $currentPage Current page identifier for navigation highlighting
 */
function renderAdminHeader(string $pageTitle, string $currentPage = ''): void
{
    $cacheVersion = Helpers::timestamp();
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= Helpers::escapeHtml($pageTitle) ?> - NJ Devils Game Day Admin</title>
        <link rel="stylesheet" href="/assets/css/admin.css?v=<?= $cacheVersion ?>">
    </head>
    <body>
        <div class="header">
            <div class="container header-inner">
                <div class="brand">
                    <img class="brand-logo" src="/assets/img/NJD_dark.svg?v=<?= $cacheVersion ?>" alt="New Jersey Devils" width="40" height="40">
                    <div class="brand-text">
                        <h1>NJ Devils Game Day Admin</h1>
                        <div class="subtitle"><?= Helpers::escapeHtml($pageTitle) ?></div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="nav">
            <div class="container">
                <ul>
                    <li><a href="/admin/" class="<?= $currentPage === 'dashboard' ? 'active' : '' ?>">Dashboard</a></li>
                    <li><a href="/admin/game.php" class="<?= $currentPage === 'game' ? 'active' : '' ?>">Game Management</a></li>
                    <li><a href="/admin/updates.php" class="<?= $currentPage === 'updates' ? 'active' : '' ?>">Updates</a></li>
                    <li><a href="/admin/logout.php">Logout</a></li>
                </ul>
            </div>
        </div>
        
        <div class="container">
    <?php
}

/**
 * Render the admin page footer
 */
function renderAdminFooter(): void
{
    ?>
        </div>
    </body>
    </html>
    <?php
}

/**
 * Display a success message
 */
function showSuccessMessage(string $message): void
{
    echo '<div class="message message-success">' . Helpers::escapeHtml($message) . '</div>';
}

/**
 * Display an error message
 */
function showErrorMessage(string $message): void
{
    echo '<div class="message message-error">' . Helpers::escapeHtml($message) . '</div>';
}

/**
 * Display a warning message
 */
function showWarningMessage(string $message): void
{
    echo '<div class="message message-warning">' . Helpers::escapeHtml($message) . '</div>';
}

/**
 * Get the current live game or null if none exists
 */
function getCurrentLiveGame(): ?array
{
    return Db::fetchOne('SELECT * FROM games WHERE is_live = 1 LIMIT 1');
}