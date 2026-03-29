-- Migration: Přidání sloupce total_rounds pro správné ukládání délky hry
-- Datum: 2026-03-29

ALTER TABLE `games`
ADD COLUMN `total_rounds` TINYINT DEFAULT NULL AFTER `max_cards`;
