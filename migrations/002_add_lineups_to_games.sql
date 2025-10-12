-- Migration: Add lineup text fields to games table
-- Purpose: Store plain-text lineups for home and away teams
-- Usage (DDEV): ddev mysql < migrations/002_add_lineups_to_games.sql

ALTER TABLE `games`
  ADD COLUMN `home_lineup_text` TEXT NULL DEFAULT NULL AFTER `score_away`,
  ADD COLUMN `away_lineup_text` TEXT NULL DEFAULT NULL AFTER `home_lineup_text`;
