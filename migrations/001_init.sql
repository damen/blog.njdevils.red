-- NHL Game Day Microservice - Initial Schema
-- This migration creates the games and game_updates tables

-- Create games table
CREATE TABLE IF NOT EXISTS `games` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL COMMENT 'Game headline/title',
    `home_team` VARCHAR(100) NOT NULL COMMENT 'Home team name',
    `away_team` VARCHAR(100) NOT NULL COMMENT 'Away team name', 
    `score_home` INT NOT NULL DEFAULT 0 COMMENT 'Home team score',
    `score_away` INT NOT NULL DEFAULT 0 COMMENT 'Away team score',
    `is_live` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Whether this is the current live game',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX `idx_is_live` (`is_live`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create game_updates table
CREATE TABLE IF NOT EXISTS `game_updates` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `game_id` BIGINT UNSIGNED NOT NULL COMMENT 'Reference to games table',
    `type` ENUM('html','nhl_goal','youtube') NOT NULL COMMENT 'Type of update content',
    `content` TEXT NULL COMMENT 'HTML content for html type updates',
    `url` VARCHAR(512) NULL COMMENT 'URL for nhl_goal and youtube type updates',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`game_id`) REFERENCES `games`(`id`) ON DELETE CASCADE,
    INDEX `idx_game_created` (`game_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;