-- Migration 003a: Audit sloupce pro soft-delete celého stromu hry (FÁZE 1, aditivní)
-- Datum: 2026-06-23
-- Engine: MariaDB 11.8 (prod). Aditivní + backfill, NEmění FK (to je až 003b).
-- Spouštět statement po statementu, BEZ --force. Tabulky jsou malé (stovky řádků) -> rychlé.
-- POZN.: games už má valid_from/valid_to (migrace 001). Zde se přidává jen valid_to_user_id.

-- 1) Nové sloupce (nullable -> neškodné, kód je čte jen pod flagem SOFT_DELETE_SUBTREE)
ALTER TABLE `games`
  ADD COLUMN `valid_to_user_id` INT UNSIGNED NULL AFTER `valid_to`;

ALTER TABLE `game_players`
  ADD COLUMN `valid_to` TIMESTAMP NULL DEFAULT NULL,
  ADD COLUMN `valid_to_user_id` INT UNSIGNED NULL;
ALTER TABLE `game_players` ADD KEY `valid_to_idx` (`valid_to`);

ALTER TABLE `rounds`
  ADD COLUMN `valid_to` TIMESTAMP NULL DEFAULT NULL,
  ADD COLUMN `valid_to_user_id` INT UNSIGNED NULL;
ALTER TABLE `rounds` ADD KEY `valid_to_idx` (`valid_to`);

ALTER TABLE `round_results`
  ADD COLUMN `valid_to` TIMESTAMP NULL DEFAULT NULL,
  ADD COLUMN `valid_to_user_id` INT UNSIGNED NULL;
ALTER TABLE `round_results` ADD KEY `valid_to_idx` (`valid_to`);

-- 2) Backfill: dorovnat podstrom už soft-smazaných her (valid_to z rodičovské hry).
--    valid_to_user_id zůstává NULL = historicky neznámý mazatel (dokumentované omezení).
UPDATE `game_players` gp
  JOIN `games` g ON g.id = gp.game_id
  SET gp.valid_to = g.valid_to
  WHERE g.valid_to IS NOT NULL AND gp.valid_to IS NULL;

UPDATE `rounds` r
  JOIN `games` g ON g.id = r.game_id
  SET r.valid_to = g.valid_to
  WHERE g.valid_to IS NOT NULL AND r.valid_to IS NULL;

UPDATE `round_results` rr
  JOIN `rounds` r ON r.id = rr.round_id
  JOIN `games` g ON g.id = r.game_id
  SET rr.valid_to = g.valid_to
  WHERE g.valid_to IS NOT NULL AND rr.valid_to IS NULL;

-- ROLLBACK (krajní pojistka; reálný rollback je git revert kódu, sloupce jsou neškodné):
-- ALTER TABLE `round_results` DROP KEY `valid_to_idx`, DROP COLUMN `valid_to`, DROP COLUMN `valid_to_user_id`;
-- ALTER TABLE `rounds`        DROP KEY `valid_to_idx`, DROP COLUMN `valid_to`, DROP COLUMN `valid_to_user_id`;
-- ALTER TABLE `game_players`  DROP KEY `valid_to_idx`, DROP COLUMN `valid_to`, DROP COLUMN `valid_to_user_id`;
-- ALTER TABLE `games`         DROP COLUMN `valid_to_user_id`;
