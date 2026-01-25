-- Migration: Přidání sloupců valid_from a valid_to pro soft-delete
-- Datum: 2026-01-25

-- Přidat sloupce valid_from a valid_to do tabulky games
ALTER TABLE `games` 
ADD COLUMN `valid_from` TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER `finished_at`,
ADD COLUMN `valid_to` TIMESTAMP NULL AFTER `valid_from`,
ADD INDEX `valid_to_idx` (`valid_to`);

-- Nastavit valid_from pro existující záznamy
UPDATE `games` SET `valid_from` = `created_at` WHERE `valid_from` IS NULL;

-- Pokud existují záznamy se statusem 'deleted', nastavit jim valid_to
UPDATE `games` SET `valid_to` = NOW() WHERE `status` = 'deleted';
