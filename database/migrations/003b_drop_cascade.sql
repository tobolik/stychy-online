-- Migration 003b: Zrušení ON DELETE CASCADE (FÁZE 2, defense-in-depth)
-- Datum: 2026-06-24
-- Engine: MariaDB 11.8 (prod). Po Fázi 1 už žádný kód nemaže natvrdo; tahle migrace
-- je pojistka, aby DB sama odmítla i případný budoucí omylný DELETE.
-- Spouštět statement po statementu, BEZ --force.
--
-- KROK 1 (POVINNÝ): orphan-check. VŠECHNY tyto SELECTy MUSÍ vrátit 0.
--   Pokud kterýkoli vrátí > 0, NEPOKRAČOVAT (ADD RESTRICT FK by spadl) a nejdřív vyřešit.
-- SELECT COUNT(*) FROM game_players gp LEFT JOIN games g ON g.id=gp.game_id WHERE g.id IS NULL;
-- SELECT COUNT(*) FROM rounds r LEFT JOIN games g ON g.id=r.game_id WHERE g.id IS NULL;
-- SELECT COUNT(*) FROM round_results rr LEFT JOIN rounds r ON r.id=rr.round_id WHERE r.id IS NULL;
-- SELECT COUNT(*) FROM round_results rr LEFT JOIN game_players gp ON gp.id=rr.player_id WHERE gp.id IS NULL;
-- SELECT COUNT(*) FROM games g LEFT JOIN users u ON u.id=g.user_id WHERE u.id IS NULL;
-- SELECT COUNT(*) FROM user_stats s LEFT JOIN users u ON u.id=s.user_id WHERE u.id IS NULL;

-- KROK 2: drop + re-add 6 FK bez ON DELETE CASCADE (default RESTRICT).
ALTER TABLE `games` DROP FOREIGN KEY `fk_games_user`;
ALTER TABLE `games` ADD CONSTRAINT `fk_games_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

ALTER TABLE `game_players` DROP FOREIGN KEY `fk_players_game`;
ALTER TABLE `game_players` ADD CONSTRAINT `fk_players_game` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`);

ALTER TABLE `rounds` DROP FOREIGN KEY `fk_rounds_game`;
ALTER TABLE `rounds` ADD CONSTRAINT `fk_rounds_game` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`);

ALTER TABLE `round_results` DROP FOREIGN KEY `fk_results_round`;
ALTER TABLE `round_results` ADD CONSTRAINT `fk_results_round` FOREIGN KEY (`round_id`) REFERENCES `rounds` (`id`);

ALTER TABLE `round_results` DROP FOREIGN KEY `fk_results_player`;
ALTER TABLE `round_results` ADD CONSTRAINT `fk_results_player` FOREIGN KEY (`player_id`) REFERENCES `game_players` (`id`);

ALTER TABLE `user_stats` DROP FOREIGN KEY `fk_stats_user`;
ALTER TABLE `user_stats` ADD CONSTRAINT `fk_stats_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

-- ROLLBACK (krajní pojistka – re-add s CASCADE jako dřív):
-- ALTER TABLE `games` DROP FOREIGN KEY `fk_games_user`;
-- ALTER TABLE `games` ADD CONSTRAINT `fk_games_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
-- ALTER TABLE `game_players` DROP FOREIGN KEY `fk_players_game`;
-- ALTER TABLE `game_players` ADD CONSTRAINT `fk_players_game` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE CASCADE;
-- ALTER TABLE `rounds` DROP FOREIGN KEY `fk_rounds_game`;
-- ALTER TABLE `rounds` ADD CONSTRAINT `fk_rounds_game` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE CASCADE;
-- ALTER TABLE `round_results` DROP FOREIGN KEY `fk_results_round`;
-- ALTER TABLE `round_results` ADD CONSTRAINT `fk_results_round` FOREIGN KEY (`round_id`) REFERENCES `rounds` (`id`) ON DELETE CASCADE;
-- ALTER TABLE `round_results` DROP FOREIGN KEY `fk_results_player`;
-- ALTER TABLE `round_results` ADD CONSTRAINT `fk_results_player` FOREIGN KEY (`player_id`) REFERENCES `game_players` (`id`) ON DELETE CASCADE;
-- ALTER TABLE `user_stats` DROP FOREIGN KEY `fk_stats_user`;
-- ALTER TABLE `user_stats` ADD CONSTRAINT `fk_stats_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
