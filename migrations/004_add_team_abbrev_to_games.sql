-- Migration: Add team abbreviations to games
-- Purpose: Store 2–4 letter NHL team abbreviations to derive logo URLs
-- Usage (DDEV): ddev mysql < migrations/004_add_team_abbrev_to_games.sql

ALTER TABLE `games`
  ADD COLUMN `home_abbrev` VARCHAR(5) NULL AFTER `home_team`,
  ADD COLUMN `away_abbrev` VARCHAR(5) NULL AFTER `away_team`;
