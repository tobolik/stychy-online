<?php
/**
 * API endpoint pro správu kol
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

requireAuth($auth);
$userId = $auth->getUserId();

/**
 * Pomocná funkce pro ověření vlastnictví hry
 */
function verifyGameOwnership(PDO $db, int $gameId, int $userId): ?array {
    $stmt = $db->prepare('SELECT * FROM games WHERE id = ? AND user_id = ?');
    $stmt->execute([$gameId, $userId]);
    return $stmt->fetch() ?: null;
}

switch ($action) {
    /**
     * Vytvoření nového kola
     */
    case 'create':
        $gameId = intval($input['game_id'] ?? 0);
        $trumpSuit = $input['trump_suit'] ?? 'none';
        $trumpValue = $input['trump_value'] ?? null;
        
        $game = verifyGameOwnership($db, $gameId, $userId);
        if (!$game) {
            jsonResponse(['success' => false, 'error' => 'Hra nenalezena'], 404);
        }
        
        if ($game['status'] !== 'active') {
            jsonResponse(['success' => false, 'error' => 'Hra již byla ukončena'], 400);
        }
        
        // Zjištění čísla kola
        $stmt = $db->prepare('SELECT MAX(round_number) as max_round FROM rounds WHERE game_id = ?');
        $stmt->execute([$gameId]);
        $result = $stmt->fetch();
        $roundNumber = ($result['max_round'] ?? 0) + 1;
        
        // Výpočet počtu karet a dealera
        $sequence = getRoundSequence($game['max_cards']);
        $totalRounds = getTotalRounds($game);

        if ($roundNumber > $totalRounds) {
            jsonResponse(['success' => false, 'error' => 'Všechna kola již byla odehrána'], 400);
        }

        // Pro kola v rámci sekvence použijeme sekvenci, jinak (zpětná kompatibilita starých her) max_cards
        $cardsCount = $sequence[$roundNumber - 1] ?? (int)$game['max_cards'];
        $dealerPosition = ($roundNumber - 1) % $game['player_count'];
        
        try {
            $db->beginTransaction();
            
            // Vytvoření kola
            $stmt = $db->prepare('
                INSERT INTO rounds (game_id, round_number, cards_count, trump_suit, trump_value, dealer_position)
                VALUES (?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([$gameId, $roundNumber, $cardsCount, $trumpSuit, $trumpValue, $dealerPosition]);
            $roundId = $db->lastInsertId();
            
            // Vytvoření prázdných výsledků pro všechny hráče
            $stmt = $db->prepare('SELECT id FROM game_players WHERE game_id = ? ORDER BY position');
            $stmt->execute([$gameId]);
            $players = $stmt->fetchAll();
            
            $stmt = $db->prepare('INSERT INTO round_results (round_id, player_id) VALUES (?, ?)');
            foreach ($players as $player) {
                $stmt->execute([$roundId, $player['id']]);
            }
            
            $db->commit();
            
            jsonResponse([
                'success' => true,
                'round_id' => $roundId,
                'round_number' => $roundNumber,
                'cards_count' => $cardsCount,
                'dealer_position' => $dealerPosition,
                'total_rounds' => $totalRounds
            ]);
        } catch (Exception $e) {
            $db->rollBack();
            jsonResponse(['success' => false, 'error' => 'Chyba: ' . $e->getMessage()], 500);
        }
        break;
        
    /**
     * Uložení sázek (hlášení)
     */
    case 'save_bids':
        $roundId = intval($input['round_id'] ?? 0);
        $bids = $input['bids'] ?? [];
        
        // Načtení kola a ověření
        $stmt = $db->prepare('
            SELECT r.*, g.user_id, g.player_count 
            FROM rounds r 
            JOIN games g ON g.id = r.game_id 
            WHERE r.id = ?
        ');
        $stmt->execute([$roundId]);
        $round = $stmt->fetch();
        
        if (!$round || $round['user_id'] != $userId) {
            jsonResponse(['success' => false, 'error' => 'Kolo nenalezeno'], 404);
        }
        
        // Validace sázek
        $totalBids = array_sum($bids);
        
        // Kontrola, že dealer nemá nevalidní sázku (je to jen upozornění, uložíme vždy)
        $warning = null;
        if ($totalBids === $round['cards_count']) {
            $warning = 'Pozor: Součet sázek se rovná počtu karet!';
        }
        
        try {
            $stmt = $db->prepare('
                UPDATE round_results rr
                JOIN game_players gp ON gp.id = rr.player_id
                SET rr.bid = ?
                WHERE rr.round_id = ? AND gp.position = ?
            ');
            
            foreach ($bids as $position => $bid) {
                $stmt->execute([$bid, $roundId, $position]);
            }
            
            // Aktualizace stavu kola
            $stmt = $db->prepare('UPDATE rounds SET status = "playing" WHERE id = ?');
            $stmt->execute([$roundId]);
            
            jsonResponse([
                'success' => true,
                'message' => 'Sázky byly uloženy',
                'warning' => $warning
            ]);
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'error' => 'Chyba: ' . $e->getMessage()], 500);
        }
        break;
        
    /**
     * Uložení výsledků kola
     */
    case 'save_results':
        $roundId = intval($input['round_id'] ?? 0);
        $tricks = $input['tricks'] ?? [];
        
        // Načtení kola
        $stmt = $db->prepare('
            SELECT r.*, g.user_id, g.id as game_id
            FROM rounds r 
            JOIN games g ON g.id = r.game_id 
            WHERE r.id = ?
        ');
        $stmt->execute([$roundId]);
        $round = $stmt->fetch();
        
        if (!$round || $round['user_id'] != $userId) {
            jsonResponse(['success' => false, 'error' => 'Kolo nenalezeno'], 404);
        }

        if ($round['status'] === 'finished') {
            jsonResponse(['success' => false, 'error' => 'Výsledky tohoto kola již byly uloženy'], 400);
        }

        // Kontrola součtu triků je volitelná (v jednoduchém režimu se nezadává přesný počet)
        // $totalTricks = array_sum($tricks);
        // if ($totalTricks !== $round['cards_count']) { ... }
        
        try {
            $db->beginTransaction();
            
            // Načtení sázek a aktualizace výsledků
            $stmt = $db->prepare('
                SELECT rr.id, rr.bid, gp.id as player_id, gp.position
                FROM round_results rr
                JOIN game_players gp ON gp.id = rr.player_id
                WHERE rr.round_id = ?
            ');
            $stmt->execute([$roundId]);
            $results = $stmt->fetchAll();
            
            $updateStmt = $db->prepare('UPDATE round_results SET tricks_won = ?, score = ? WHERE id = ?');
            $updatePlayerStmt = $db->prepare('UPDATE game_players SET total_score = total_score + ? WHERE id = ?');
            
            foreach ($results as $result) {
                $position = $result['position'];
                $tricksWon = $tricks[$position] ?? 0;
                $score = calculateScore($result['bid'], $tricksWon);
                
                $updateStmt->execute([$tricksWon, $score, $result['id']]);
                $updatePlayerStmt->execute([$score, $result['player_id']]);
            }
            
            // Ukončení kola
            $stmt = $db->prepare('UPDATE rounds SET status = "finished" WHERE id = ?');
            $stmt->execute([$roundId]);
            
            $db->commit();
            
            // Načtení aktualizovaných skóre
            $stmt = $db->prepare('SELECT position, name, total_score FROM game_players WHERE game_id = ? ORDER BY position');
            $stmt->execute([$round['game_id']]);
            $players = $stmt->fetchAll();
            
            jsonResponse([
                'success' => true,
                'message' => 'Výsledky byly uloženy',
                'players' => $players
            ]);
        } catch (Exception $e) {
            $db->rollBack();
            jsonResponse(['success' => false, 'error' => 'Chyba: ' . $e->getMessage()], 500);
        }
        break;
        
    /**
     * Detail kola
     */
    case 'get':
        $roundId = intval($input['round_id'] ?? $_GET['round_id'] ?? 0);
        
        $stmt = $db->prepare('
            SELECT r.*, g.user_id
            FROM rounds r 
            JOIN games g ON g.id = r.game_id 
            WHERE r.id = ?
        ');
        $stmt->execute([$roundId]);
        $round = $stmt->fetch();
        
        if (!$round || $round['user_id'] != $userId) {
            jsonResponse(['success' => false, 'error' => 'Kolo nenalezeno'], 404);
        }
        
        // Výsledky
        $stmt = $db->prepare('
            SELECT rr.*, gp.name as player_name, gp.position
            FROM round_results rr
            JOIN game_players gp ON gp.id = rr.player_id
            WHERE rr.round_id = ?
            ORDER BY gp.position
        ');
        $stmt->execute([$roundId]);
        $results = $stmt->fetchAll();
        
        jsonResponse([
            'success' => true,
            'round' => $round,
            'results' => $results
        ]);
        break;

    /**
     * Aktualizace výsledků kola (oprava)
     */
    case 'update_results':
        $roundId = intval($input['round_id'] ?? 0);
        $tricks = $input['tricks'] ?? [];
        $newCardsCount = isset($input['cards_count']) ? intval($input['cards_count']) : null;
        
        // Načtení kola
        $stmt = $db->prepare('
            SELECT r.*, g.user_id, g.id as game_id
            FROM rounds r 
            JOIN games g ON g.id = r.game_id 
            WHERE r.id = ?
        ');
        $stmt->execute([$roundId]);
        $round = $stmt->fetch();
        
        if (!$round || $round['user_id'] != $userId) {
            jsonResponse(['success' => false, 'error' => 'Kolo nenalezeno'], 404);
        }
        
        try {
            $db->beginTransaction();
            
            // Aktualizace počtu karet, pokud se změnil
            if ($newCardsCount !== null && $newCardsCount != $round['cards_count']) {
                $stmt = $db->prepare('UPDATE rounds SET cards_count = ? WHERE id = ?');
                $stmt->execute([$newCardsCount, $roundId]);
            }
            
            // Načtení starých výsledků
            $stmt = $db->prepare('
                SELECT rr.id, rr.bid, rr.score as old_score, gp.id as player_id, gp.position
                FROM round_results rr
                JOIN game_players gp ON gp.id = rr.player_id
                WHERE rr.round_id = ?
            ');
            $stmt->execute([$roundId]);
            $results = $stmt->fetchAll();
            
            $updateStmt = $db->prepare('UPDATE round_results SET tricks_won = ?, score = ? WHERE id = ?');
            $updatePlayerStmt = $db->prepare('UPDATE game_players SET total_score = total_score - ? + ? WHERE id = ?');
            
            foreach ($results as $result) {
                $position = $result['position'];
                $tricksWon = $tricks[$position] ?? 0;
                $newScore = calculateScore($result['bid'], $tricksWon);
                $oldScore = $result['old_score'] ?? 0;
                
                $updateStmt->execute([$tricksWon, $newScore, $result['id']]);
                $updatePlayerStmt->execute([$oldScore, $newScore, $result['player_id']]);
            }
            
            $db->commit();
            
            // Načtení aktualizovaných skóre
            $stmt = $db->prepare('SELECT position, name, total_score FROM game_players WHERE game_id = ? ORDER BY position');
            $stmt->execute([$round['game_id']]);
            $players = $stmt->fetchAll();
            
            jsonResponse([
                'success' => true,
                'message' => 'Výsledky byly aktualizovány',
                'players' => $players
            ]);
        } catch (Exception $e) {
            $db->rollBack();
            jsonResponse(['success' => false, 'error' => 'Chyba: ' . $e->getMessage()], 500);
        }
        break;
        
    /**
     * Smazání kola
     */
    case 'delete':
        $roundId = intval($input['round_id'] ?? 0);
        
        // Načtení kola
        $stmt = $db->prepare('
            SELECT r.*, g.user_id, g.id as game_id
            FROM rounds r 
            JOIN games g ON g.id = r.game_id 
            WHERE r.id = ?
        ');
        $stmt->execute([$roundId]);
        $round = $stmt->fetch();
        
        if (!$round || $round['user_id'] != $userId) {
            jsonResponse(['success' => false, 'error' => 'Kolo nenalezeno'], 404);
        }
        
        try {
            $db->beginTransaction();
            
            // Pokud kolo má výsledky, odečíst body od hráčů
            $stmt = $db->prepare('
                SELECT rr.score, gp.id as player_id
                FROM round_results rr
                JOIN game_players gp ON gp.id = rr.player_id
                WHERE rr.round_id = ? AND rr.score IS NOT NULL
            ');
            $stmt->execute([$roundId]);
            $results = $stmt->fetchAll();
            
            $updatePlayerStmt = $db->prepare('UPDATE game_players SET total_score = total_score - ? WHERE id = ?');
            foreach ($results as $result) {
                if ($result['score']) {
                    $updatePlayerStmt->execute([$result['score'], $result['player_id']]);
                }
            }
            
            // Smazat výsledky kola
            $stmt = $db->prepare('DELETE FROM round_results WHERE round_id = ?');
            $stmt->execute([$roundId]);
            
            // Smazat kolo
            $stmt = $db->prepare('DELETE FROM rounds WHERE id = ?');
            $stmt->execute([$roundId]);
            
            // Přečíslovat zbývající kola
            $stmt = $db->prepare('
                SELECT id FROM rounds WHERE game_id = ? ORDER BY round_number
            ');
            $stmt->execute([$round['game_id']]);
            $rounds = $stmt->fetchAll();
            
            $updateRoundStmt = $db->prepare('UPDATE rounds SET round_number = ? WHERE id = ?');
            $newNumber = 1;
            foreach ($rounds as $r) {
                $updateRoundStmt->execute([$newNumber, $r['id']]);
                $newNumber++;
            }
            
            $db->commit();
            
            jsonResponse([
                'success' => true,
                'message' => 'Kolo bylo smazáno'
            ]);
        } catch (Exception $e) {
            $db->rollBack();
            jsonResponse(['success' => false, 'error' => 'Chyba: ' . $e->getMessage()], 500);
        }
        break;
        
    /**
     * Validace sázky dealera
     */
    case 'validate_bid':
        $cardsCount = intval($input['cards_count'] ?? 0);
        $currentSum = intval($input['current_sum'] ?? 0);
        $proposedBid = intval($input['proposed_bid'] ?? 0);
        
        $isValid = isValidDealerBid($proposedBid, $currentSum, $cardsCount);
        $forbidden = $cardsCount - $currentSum;
        
        jsonResponse([
            'success' => true,
            'is_valid' => $isValid,
            'forbidden_bid' => $forbidden >= 0 && $forbidden <= $cardsCount ? $forbidden : null
        ]);
        break;
        
    default:
        jsonResponse(['success' => false, 'error' => 'Neznámá akce'], 400);
}
