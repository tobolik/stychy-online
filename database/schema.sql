-- Štychy Online - Databázové schéma
-- Verze: 1.0

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------
-- Tabulka uživatelů
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(50) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `last_login` TIMESTAMP NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    PRIMARY KEY (`id`),
    UNIQUE KEY `username_unique` (`username`),
    UNIQUE KEY `email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- -----------------------------------------------------
-- Tabulka her (offline záznamů)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `games` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `player_count` TINYINT NOT NULL,
    `max_cards` TINYINT DEFAULT 7,
    `status` ENUM('active', 'finished', 'cancelled') DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `finished_at` TIMESTAMP NULL,
    `valid_from` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `valid_to` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    KEY `user_games` (`user_id`),
    KEY `valid_to_idx` (`valid_to`),
    CONSTRAINT `fk_games_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- -----------------------------------------------------
-- Tabulka hráčů v konkrétní hře
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `game_players` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `game_id` INT UNSIGNED NOT NULL,
    `position` TINYINT NOT NULL,
    `name` VARCHAR(50) NOT NULL,
    `total_score` INT DEFAULT 0,
    `final_rank` TINYINT NULL,
    PRIMARY KEY (`id`),
    KEY `game_players_idx` (`game_id`),
    UNIQUE KEY `game_position_unique` (`game_id`, `position`),
    CONSTRAINT `fk_players_game` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- -----------------------------------------------------
-- Tabulka kol
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `rounds` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `game_id` INT UNSIGNED NOT NULL,
    `round_number` TINYINT NOT NULL,
    `cards_count` TINYINT NOT NULL,
    `trump_suit` ENUM('hearts', 'diamonds', 'clubs', 'spades', 'none') NOT NULL,
    `trump_value` VARCHAR(2) NULL,
    `dealer_position` TINYINT NOT NULL,
    `status` ENUM('bidding', 'playing', 'finished') DEFAULT 'bidding',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `game_rounds_idx` (`game_id`),
    UNIQUE KEY `game_round_unique` (`game_id`, `round_number`),
    CONSTRAINT `fk_rounds_game` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- -----------------------------------------------------
-- Tabulka sázek a výsledků jednotlivých hráčů v kole
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `round_results` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `round_id` INT UNSIGNED NOT NULL,
    `player_id` INT UNSIGNED NOT NULL,
    `bid` TINYINT NULL,
    `tricks_won` TINYINT NULL,
    `score` INT NULL,
    PRIMARY KEY (`id`),
    KEY `round_results_idx` (`round_id`),
    KEY `player_results_idx` (`player_id`),
    UNIQUE KEY `round_player_unique` (`round_id`, `player_id`),
    CONSTRAINT `fk_results_round` FOREIGN KEY (`round_id`) REFERENCES `rounds` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_results_player` FOREIGN KEY (`player_id`) REFERENCES `game_players` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- -----------------------------------------------------
-- Tabulka pro uživatelské statistiky (volitelné)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_stats` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `games_played` INT DEFAULT 0,
    `games_won` INT DEFAULT 0,
    `total_score` INT DEFAULT 0,
    `best_score` INT DEFAULT 0,
    `perfect_rounds` INT DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `user_stats_idx` (`user_id`),
    CONSTRAINT `fk_stats_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

SET FOREIGN_KEY_CHECKS = 1;
