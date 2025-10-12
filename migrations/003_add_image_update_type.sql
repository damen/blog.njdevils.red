-- Migration: Add 'image' update type to game_updates.type enum
-- Usage (DDEV): ddev mysql < migrations/003_add_image_update_type.sql

ALTER TABLE `game_updates`
  MODIFY COLUMN `type` ENUM('html','nhl_goal','youtube','image') NOT NULL COMMENT 'Type of update content';
