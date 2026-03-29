<?php
/**
 * API endpoint pro správu her
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$auth = new Auth();
$db = getDB();
$input = getJsonInput();
$action = $input['action'] ?? $_GET['action'] ?? '';

// Pro většinu akcí vyžadujeme přihlášení
if (!in_array($action, [])) {
    requireAuth($auth);
}

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
            jsonResponse(['success' => false, 'error' => 'Chyba při vytváření hry: ' . $e->getMessage()], 500);
        }
        break;
        
    /**
     * Seznam her uživatele
     */
    case 'list':
        $status = $input['status'] ?? $_GET['status'] ?? null;
        
        $sql = 'SELECT g.*, 
                (SELECT COUNT(*) FROM rounds WHERE game_id = g.id) as rounds_played
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
        $gameId = intval($input['game_id'] ?? $_GET['game_id'] ?? 0);
        
        // Ověření vlastnictví (vyloučit smazané hry - valid_to IS NULL)
        $stmt = $db->prepare('SELECT * FROM games WHERE id = ? AND user_id = ? AND valid_to IS NULL');
        $stmt->execute([$gameId, $userId]);
        $game = $stmt->fetch();
        
        if (!$game) {
            jsonResponse(['success' => false, 'error' => 'Hra nenalezena'], 404);
        }
        
        // Načtení hráčů
        $stmt = $db->prepare('SELECT * FROM game_players WHERE game_id = ? ORDER BY position');
        $stmt->execute([$gameId]);
        $players = $stmt->fetchAll();
        
        // Načtení kol
        $stmt = $db->prepare('SELECT * FROM rounds WHERE game_id = ? ORDER BY round_number');
        $stmt->execute([$gameId]);
        $rounds = $stmt->fetchAll();
        
        // Pro každé kolo načíst výsledky
        foreach ($rounds as &$round) {
            $stmt = $db->prepare('
                SELECT rr.*, gp.name as player_name, gp.position
                FROM round_results rr
                JOIN game_players gp ON gp.id = rr.player_id
                WHERE rr.round_id = ?
                ORDER BY gp.position
            ');
            $stmt->execute([$round['id']]);
            $round['results'] = $stmt->fetchAll();
        }
        
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
        
        // Ověření vlastnictví
        $stmt = $db->prepare('SELECT * FROM games WHERE id = ? AND user_id = ?');
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
            jsonResponse(['success' => false, 'error' => 'Chyba: ' . $e->getMessage()], 500);
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
        
        // Soft-delete - nastavení valid_to na aktuální čas
        $stmt = $db->prepare('UPDATE games SET valid_to = NOW() WHERE id = ?');
        $stmt->execute([$gameId]);
        
        jsonResponse([
            'success' => true,
            'message' => 'Hra byla smazána'
        ]);
        break;
        
    default:
        jsonResponse(['success' => false, 'error' => 'Neznámá akce'], 400);
}
