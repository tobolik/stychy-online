<?php
/**
 * API endpoint pro správu her
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

// Stavové operace jen přes POST (frontend volá vše přes POST; CSRF defense-in-depth k SameSite=Strict)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Metoda není povolena'], 405);
}

$auth = new Auth();
$db = getDB();
$input = getJsonInput();
$action = $input['action'] ?? '';

// Všechny akce vyžadují přihlášení
requireAuth($auth);

$userId = $auth->getUserId();

switch ($action) {
    /**
     * Vytvoření nové hry
     */
    case 'create':
        $name = trim($input['name'] ?? '');
        $playerCount = intval($input['player_count'] ?? 0);
        $maxCards = intval($input['max_cards'] ?? 7);
        $players = $input['players'] ?? [];
        
        if (empty($name)) {
            jsonResponse(['success' => false, 'error' => 'Název hry je povinný'], 400);
        }
        
        if ($playerCount < 3 || $playerCount > 11) {
            jsonResponse(['success' => false, 'error' => 'Počet hráčů musí být 3-11'], 400);
        }
        
        if (count($players) !== $playerCount) {
            jsonResponse(['success' => false, 'error' => 'Počet jmen hráčů neodpovídá'], 400);
        }
        
        try {
            $db->beginTransaction();
            
            // Vytvoření hry
            $stmt = $db->prepare('INSERT INTO games (user_id, name, player_count, max_cards) VALUES (?, ?, ?, ?)');
            $stmt->execute([$userId, $name, $playerCount, $maxCards]);
            $gameId = $db->lastInsertId();
            
            // Vytvoření hráčů
            $stmt = $db->prepare('INSERT INTO game_players (game_id, position, name) VALUES (?, ?, ?)');
            foreach ($players as $position => $playerName) {
                $stmt->execute([$gameId, $position, trim($playerName)]);
            }
            
            $db->commit();
            
            jsonResponse([
                'success' => true,
                'game_id' => $gameId,
                'message' => 'Hra byla vytvořena'
            ]);
        } catch (Exception $e) {
            $db->rollBack();
            error_log('games.php create: ' . $e->getMessage()); jsonResponse(['success' => false, 'error' => 'Chyba serveru.'], 500);
        }
        break;
        
    /**
     * Seznam her uživatele
     */
    case 'list':
        $status = $input['status'] ?? null;
        
        $vf = SOFT_DELETE_SUBTREE ? ' AND valid_to IS NULL' : '';
        $sql = 'SELECT g.*,
                (SELECT COUNT(*) FROM rounds WHERE game_id = g.id' . $vf . ') as rounds_played
                FROM games g
                WHERE g.user_id = ? AND g.valid_to IS NULL';
        $params = [$userId];
        
        if ($status) {
            $sql .= ' AND g.status = ?';
            $params[] = $status;
        }
        
        $sql .= ' ORDER BY g.created_at DESC';
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $games = $stmt->fetchAll();
        
        jsonResponse(['success' => true, 'games' => $games]);
        break;
        
    /**
     * Detail hry
     */
    case 'get':
        $gameId = intval($input['game_id'] ?? 0);
        
        // Ověření vlastnictví (vyloučit smazané hry - valid_to IS NULL)
        $stmt = $db->prepare('SELECT * FROM games WHERE id = ? AND user_id = ? AND valid_to IS NULL');
        $stmt->execute([$gameId, $userId]);
        $game = $stmt->fetch();
        
        if (!$game) {
            jsonResponse(['success' => false, 'error' => 'Hra nenalezena'], 404);
        }
        
        // Soft-delete filtr na podtabulky (gated flagem – bezpečné i bez sloupců)
        $vf = SOFT_DELETE_SUBTREE ? ' AND valid_to IS NULL' : '';

        // Načtení hráčů
        $stmt = $db->prepare('SELECT * FROM game_players WHERE game_id = ?' . $vf . ' ORDER BY position');
        $stmt->execute([$gameId]);
        $players = $stmt->fetchAll();

        // Načtení kol
        $stmt = $db->prepare('SELECT * FROM rounds WHERE game_id = ?' . $vf . ' ORDER BY round_number');
        $stmt->execute([$gameId]);
        $rounds = $stmt->fetchAll();
        
        // Výsledky všech kol jedním dotazem (místo N+1) a rozřazení v PHP.
        // Reference do $rounds: zápis do $byId[id]['results'] mění přímo prvek $rounds.
        // results=[] inicializujeme pro každé kolo předem, aby kolo bez výsledků nezmizelo.
        $byId = [];
        foreach ($rounds as &$round) {
            $round['results'] = [];
            $byId[$round['id']] = &$round;
        }
        unset($round);

        $stmt = $db->prepare('
            SELECT rr.*, gp.name as player_name, gp.position
            FROM round_results rr
            JOIN game_players gp ON gp.id = rr.player_id
            JOIN rounds r ON r.id = rr.round_id
            WHERE r.game_id = ?' . (SOFT_DELETE_SUBTREE ? ' AND rr.valid_to IS NULL' : '') . '
            ORDER BY r.round_number, gp.position
        ');
        $stmt->execute([$gameId]);
        while ($row = $stmt->fetch()) {
            if (isset($byId[$row['round_id']])) {
                $byId[$row['round_id']]['results'][] = $row;
            }
        }
        unset($byId);

        // Přepočítat total_score ze skutečných výsledků kol (ochrana proti double-count)
        $scoreMap = [];
        foreach ($players as $p) {
            $scoreMap[$p['position']] = 0;
        }
        foreach ($rounds as $round) {
            if ($round['status'] === 'finished') {
                foreach ($round['results'] as $result) {
                    if ($result['score'] !== null) {
                        $scoreMap[$result['position']] += $result['score'];
                    }
                }
            }
        }
        foreach ($players as &$player) {
            $player['total_score'] = $scoreMap[$player['position']] ?? $player['total_score'];
        }
        unset($player);
        
        jsonResponse([
            'success' => true,
            'game' => $game,
            'players' => $players,
            'rounds' => $rounds
        ]);
        break;
        
    /**
     * Ukončení hry
     */
    case 'finish':
        $gameId = intval($input['game_id'] ?? 0);

        // Ověření vlastnictví (jen nesmazaná hra)
        $stmt = $db->prepare('SELECT * FROM games WHERE id = ? AND user_id = ? AND valid_to IS NULL');
        $stmt->execute([$gameId, $userId]);
        $game = $stmt->fetch();
        
        if (!$game) {
            jsonResponse(['success' => false, 'error' => 'Hra nenalezena'], 404);
        }
        
        try {
            $db->beginTransaction();
            
            // Výpočet pořadí
            $stmt = $db->prepare('
                SELECT id, total_score 
                FROM game_players 
                WHERE game_id = ? 
                ORDER BY total_score DESC
            ');
            $stmt->execute([$gameId]);
            $players = $stmt->fetchAll();
            
            $rank = 1;
            $prevScore = null;
            $sameRankCount = 0;
            
            foreach ($players as $player) {
                if ($prevScore !== null && $player['total_score'] < $prevScore) {
                    $rank += $sameRankCount;
                    $sameRankCount = 1;
                } else {
                    $sameRankCount++;
                }
                
                $stmt = $db->prepare('UPDATE game_players SET final_rank = ? WHERE id = ?');
                $stmt->execute([$rank, $player['id']]);
                
                $prevScore = $player['total_score'];
            }
            
            // Ukončení hry
            $stmt = $db->prepare('UPDATE games SET status = "finished", finished_at = NOW() WHERE id = ?');
            $stmt->execute([$gameId]);
            
            // Aktualizace statistik uživatele
            $stmt = $db->prepare('
                UPDATE user_stats 
                SET games_played = games_played + 1
                WHERE user_id = ?
            ');
            $stmt->execute([$userId]);
            
            $db->commit();
            
            jsonResponse(['success' => true, 'message' => 'Hra byla ukončena']);
        } catch (Exception $e) {
            $db->rollBack();
            error_log('games.php: ' . $e->getMessage()); jsonResponse(['success' => false, 'error' => 'Chyba serveru.'], 500);
        }
        break;
        
    /**
     * Přejmenování hry
     */
    case 'rename':
        $gameId = intval($input['game_id'] ?? 0);
        $newName = trim($input['name'] ?? '');
        
        if (empty($newName)) {
            jsonResponse(['success' => false, 'error' => 'Název hry je povinný'], 400);
        }
        
        // Ověření vlastnictví
        $stmt = $db->prepare('SELECT * FROM games WHERE id = ? AND user_id = ? AND valid_to IS NULL');
        $stmt->execute([$gameId, $userId]);
        $game = $stmt->fetch();
        
        if (!$game) {
            jsonResponse(['success' => false, 'error' => 'Hra nenalezena'], 404);
        }
        
        // Aktualizace názvu
        $stmt = $db->prepare('UPDATE games SET name = ? WHERE id = ?');
        $stmt->execute([$newName, $gameId]);
        
        jsonResponse([
            'success' => true,
            'game_id' => $gameId,
            'message' => 'Název hry byl změněn'
        ]);
        break;
        
    /**
     * Smazání hry (soft-delete - nastaví valid_to na NOW())
     */
    case 'delete':
        $gameId = intval($input['game_id'] ?? 0);
        
        // Ověření vlastnictví (pouze platné záznamy)
        $stmt = $db->prepare('SELECT * FROM games WHERE id = ? AND user_id = ? AND valid_to IS NULL');
        $stmt->execute([$gameId, $userId]);
        $game = $stmt->fetch();
        
        if (!$game) {
            jsonResponse(['success' => false, 'error' => 'Hra nenalezena'], 404);
        }

        // Soft-delete: propagace na celý strom hry v jedné transakci.
        // Flag OFF (před migrací 003a) = chování jako dřív (jen games). Stampujeme jen
        // řádky valid_to IS NULL (nepřepisujeme dřívější audit).
        try {
            $db->beginTransaction();
            if (SOFT_DELETE_SUBTREE) {
                $db->prepare('UPDATE games SET valid_to = NOW(), valid_to_user_id = ? WHERE id = ? AND valid_to IS NULL')
                   ->execute([$userId, $gameId]);
                $db->prepare('UPDATE game_players SET valid_to = NOW(), valid_to_user_id = ? WHERE game_id = ? AND valid_to IS NULL')
                   ->execute([$userId, $gameId]);
                $db->prepare('UPDATE rounds SET valid_to = NOW(), valid_to_user_id = ? WHERE game_id = ? AND valid_to IS NULL')
                   ->execute([$userId, $gameId]);
                $db->prepare('UPDATE round_results SET valid_to = NOW(), valid_to_user_id = ? WHERE round_id IN (SELECT id FROM rounds WHERE game_id = ?) AND valid_to IS NULL')
                   ->execute([$userId, $gameId]);
            } else {
                $db->prepare('UPDATE games SET valid_to = NOW() WHERE id = ?')->execute([$gameId]);
            }
            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            error_log('games.php delete: ' . $e->getMessage());
            jsonResponse(['success' => false, 'error' => 'Chyba serveru.'], 500);
        }

        jsonResponse([
            'success' => true,
            'message' => 'Hra byla smazána'
        ]);
        break;
        
    /**
     * Úprava sestavy hry (název hry + jména a pořadí hráčů) – JEN u hry BEZ odehraných kol.
     * Nemění počet hráčů ani max. karet. Pořadí se přepisuje dvoufázově kvůli
     * UNIQUE(game_id, position). Soft-delete model: řádky se aktualizují in-place, nic se nemaže.
     */
    case 'update_setup':
        $gameId = intval($input['game_id'] ?? 0);
        $newName = trim($input['name'] ?? '');
        $players = $input['players'] ?? [];

        if (empty($newName)) {
            jsonResponse(['success' => false, 'error' => 'Název hry je povinný'], 400);
        }

        // Ověření vlastnictví (jen platné záznamy)
        $stmt = $db->prepare('SELECT * FROM games WHERE id = ? AND user_id = ? AND valid_to IS NULL');
        $stmt->execute([$gameId, $userId]);
        $game = $stmt->fetch();
        if (!$game) {
            jsonResponse(['success' => false, 'error' => 'Hra nenalezena'], 404);
        }

        // Bezpečnostní pojistka: hráče lze upravit jen když hra NEMÁ odehraná kola –
        // jinak by přepis pozic rozbil uložený dealer_position u existujících kol.
        $stmt = $db->prepare('SELECT COUNT(*) FROM rounds WHERE game_id = ? AND valid_to IS NULL');
        $stmt->execute([$gameId]);
        if ((int)$stmt->fetchColumn() > 0) {
            jsonResponse(['success' => false, 'error' => 'Hráče nelze upravit u rozehrané hry'], 400);
        }

        // Aktivní hráči hry (stabilní pořadí dle id) – slouží jako sloty k přepsání
        $stmt = $db->prepare('SELECT id FROM game_players WHERE game_id = ? AND valid_to IS NULL ORDER BY id ASC');
        $stmt->execute([$gameId]);
        $existingIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $count = count($existingIds);

        if (count($players) !== $count) {
            jsonResponse(['success' => false, 'error' => 'Počet hráčů nelze měnit'], 400);
        }

        // Nová jména v pořadí 0..N-1 (klíč = cílová pozice)
        $names = [];
        for ($i = 0; $i < $count; $i++) {
            if (!isset($players[$i]) || trim($players[$i]) === '') {
                jsonResponse(['success' => false, 'error' => 'Jméno hráče nesmí být prázdné'], 400);
            }
            $names[$i] = trim($players[$i]);
        }

        try {
            $db->beginTransaction();

            // Název hry
            $db->prepare('UPDATE games SET name = ? WHERE id = ?')->execute([$newName, $gameId]);

            // Dvoufázový přepis pozic (obchází UNIQUE(game_id, position)):
            // 1) uvolnit rozsah 0..N-1 posunem aktivních řádků o +100 (pozice je TINYINT, N<=11)
            $db->prepare('UPDATE game_players SET position = position + 100 WHERE game_id = ? AND valid_to IS NULL')
               ->execute([$gameId]);
            // 2) nastavit finální pozici + jméno na každý slot
            $upd = $db->prepare('UPDATE game_players SET position = ?, name = ? WHERE id = ?');
            for ($k = 0; $k < $count; $k++) {
                $upd->execute([$k, $names[$k], $existingIds[$k]]);
            }

            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            error_log('games.php update_setup: ' . $e->getMessage());
            jsonResponse(['success' => false, 'error' => 'Chyba serveru.'], 500);
        }

        jsonResponse([
            'success' => true,
            'game_id' => $gameId,
            'message' => 'Sestava hry byla upravena'
        ]);
        break;

    default:
        jsonResponse(['success' => false, 'error' => 'Neznámá akce'], 400);
}
