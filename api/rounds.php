<?php
/**
 * API endpoint pro správu kol
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

requireAuth($auth);
$userId = $auth->getUserId();

/**
 * Pomocná funkce pro ověření vlastnictví hry
 */
function verifyGameOwnership(PDO $db, int $gameId, int $userId): ?array {
    // valid_to IS NULL: na soft-smazané hře nelze pracovat (oprava živého bugu).
    $stmt = $db->prepare('SELECT * FROM games WHERE id = ? AND user_id = ? AND valid_to IS NULL');
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

        // Idempotence proti dvojkliku: pokud poslední kolo ještě nemá zadané hlášení
        // (status 'bidding'), NEvytvářet nové – aktualizovat trumf a vrátit stávající kolo.
        $stmt = $db->prepare('SELECT * FROM rounds WHERE game_id = ? AND valid_to IS NULL ORDER BY round_number DESC LIMIT 1');
        $stmt->execute([$gameId]);
        $lastRound = $stmt->fetch();
        if ($lastRound && $lastRound['status'] === 'bidding') {
            $upd = $db->prepare('UPDATE rounds SET trump_suit = ?, trump_value = ? WHERE id = ?');
            $upd->execute([$trumpSuit, $trumpValue, $lastRound['id']]);
            jsonResponse([
                'success' => true,
                'round_id' => (int)$lastRound['id'],
                'round_number' => (int)$lastRound['round_number'],
                'cards_count' => (int)$lastRound['cards_count'],
                'dealer_position' => (int)$lastRound['dealer_position'],
                'total_rounds' => getTotalRounds($game),
                'reused' => true
            ]);
        }

        // Zjištění čísla kola (jen nesmazaná kola -> po smazání posledního kola se číslo znovu použije)
        $stmt = $db->prepare('SELECT MAX(round_number) as max_round FROM rounds WHERE game_id = ? AND valid_to IS NULL');
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
            // Souběh (dvojklik): jiný požadavek už kolo vytvořil -> vrátit existující místo chyby
            $stmt = $db->prepare('SELECT * FROM rounds WHERE game_id = ? AND valid_to IS NULL ORDER BY round_number DESC LIMIT 1');
            $stmt->execute([$gameId]);
            $lr = $stmt->fetch();
            if ($lr && $lr['status'] === 'bidding') {
                // aplikovat i zvolený trumf (konzistence s idempotentní větví výše)
                $db->prepare('UPDATE rounds SET trump_suit = ?, trump_value = ? WHERE id = ?')
                   ->execute([$trumpSuit, $trumpValue, $lr['id']]);
                jsonResponse([
                    'success' => true,
                    'round_id' => (int)$lr['id'],
                    'round_number' => (int)$lr['round_number'],
                    'cards_count' => (int)$lr['cards_count'],
                    'dealer_position' => (int)$lr['dealer_position'],
                    'total_rounds' => getTotalRounds($game),
                    'reused' => true
                ]);
            }
            error_log('rounds.php create: ' . $e->getMessage());
            jsonResponse(['success' => false, 'error' => 'Chyba serveru.'], 500);
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
            WHERE r.id = ? AND g.valid_to IS NULL AND r.valid_to IS NULL
        ');
        $stmt->execute([$roundId]);
        $round = $stmt->fetch();
        
        if (!$round || (int)$round['user_id'] !== $userId) {
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
            error_log('rounds.php: ' . $e->getMessage()); jsonResponse(['success' => false, 'error' => 'Chyba serveru.'], 500);
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
            WHERE r.id = ? AND g.valid_to IS NULL AND r.valid_to IS NULL
        ');
        $stmt->execute([$roundId]);
        $round = $stmt->fetch();
        
        if (!$round || (int)$round['user_id'] !== $userId) {
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
            error_log('rounds.php: ' . $e->getMessage()); jsonResponse(['success' => false, 'error' => 'Chyba serveru.'], 500);
        }
        break;
        
    /**
     * Detail kola
     */
    case 'get':
        $roundId = intval($input['round_id'] ?? 0);
        
        $stmt = $db->prepare('
            SELECT r.*, g.user_id
            FROM rounds r
            JOIN games g ON g.id = r.game_id
            WHERE r.id = ? AND g.valid_to IS NULL AND r.valid_to IS NULL
        ');
        $stmt->execute([$roundId]);
        $round = $stmt->fetch();
        
        if (!$round || (int)$round['user_id'] !== $userId) {
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
            WHERE r.id = ? AND g.valid_to IS NULL AND r.valid_to IS NULL
        ');
        $stmt->execute([$roundId]);
        $round = $stmt->fetch();
        
        if (!$round || (int)$round['user_id'] !== $userId) {
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
            error_log('rounds.php: ' . $e->getMessage()); jsonResponse(['success' => false, 'error' => 'Chyba serveru.'], 500);
        }
        break;
        
    /**
     * Smazání kola
     */
    case 'delete':
        // Mazání kola (soft-delete přes valid_to, NIKDY hard-delete):
        //  - PRÁZDNÉ kolo (status 'bidding', bez hlášení) lze smazat KDEKOLIV (úklid),
        //  - POSLEDNÍ kolo lze smazat i se zadanými výsledky (undo) – skóre se odečte,
        //  - dřívější kolo se zadanými výsledky smazat NELZE (rozbila by se posloupnost).
        $roundId = intval($input['round_id'] ?? 0);

        $stmt = $db->prepare('
            SELECT r.*, g.user_id
            FROM rounds r
            JOIN games g ON g.id = r.game_id
            WHERE r.id = ? AND g.valid_to IS NULL AND r.valid_to IS NULL
        ');
        $stmt->execute([$roundId]);
        $round = $stmt->fetch();

        if (!$round || (int)$round['user_id'] !== $userId) {
            jsonResponse(['success' => false, 'error' => 'Kolo nenalezeno'], 404);
        }

        $stmt = $db->prepare('SELECT MAX(round_number) AS m FROM rounds WHERE game_id = ? AND valid_to IS NULL');
        $stmt->execute([$round['game_id']]);
        $maxRn = (int)($stmt->fetch()['m'] ?? 0);

        $isEmpty = ($round['status'] === 'bidding');
        $isLast  = ((int)$round['round_number'] === $maxRn);
        if (!$isEmpty && !$isLast) {
            jsonResponse(['success' => false, 'error' => 'Smazat lze prázdné kolo, nebo poslední kolo. Dřívější kolo se zadanými výsledky smazat nelze – nejdřív smažte novější kola.'], 400);
        }

        // UNIQUE(game_id, round_number) nezohledňuje valid_to -> uvolnit slot pod aktuální minimum,
        // aby další create mohl číslo znovu použít bez kolize. Čtení stejně filtrují přes valid_to.
        $stmt = $db->prepare('SELECT COALESCE(MIN(round_number), 0) AS mn FROM rounds WHERE game_id = ?');
        $stmt->execute([$round['game_id']]);
        $freeNum = ((int)$stmt->fetch()['mn']) - 1;

        try {
            $db->beginTransaction();

            // Prázdné kolo: revalidace status='bidding' (souběh se save_bids). Ostatní: jen valid_to.
            if ($isEmpty) {
                $stmt = $db->prepare('UPDATE rounds SET valid_to = NOW(), valid_to_user_id = ?, round_number = ? WHERE id = ? AND valid_to IS NULL AND status = "bidding"');
            } else {
                $stmt = $db->prepare('UPDATE rounds SET valid_to = NOW(), valid_to_user_id = ?, round_number = ? WHERE id = ? AND valid_to IS NULL');
            }
            $stmt->execute([$userId, $freeNum, $roundId]);
            if ($stmt->rowCount() === 0) {
                $db->rollBack();
                jsonResponse(['success' => false, 'error' => 'Kolo se mezitím změnilo, zkuste to znovu.'], 409);
            }

            // Kolo se skóre: odečíst body z uloženého total_score (čtení sice přepočítává,
            // ale držíme cache konzistentní i pro seznam her / statistiky).
            if (!$isEmpty) {
                $rs = $db->prepare('SELECT player_id, score FROM round_results WHERE round_id = ? AND valid_to IS NULL AND score IS NOT NULL');
                $rs->execute([$roundId]);
                $sub = $db->prepare('UPDATE game_players SET total_score = total_score - ? WHERE id = ?');
                foreach ($rs->fetchAll() as $r) {
                    $sub->execute([(int)$r['score'], (int)$r['player_id']]);
                }
            }

            $db->prepare('UPDATE round_results SET valid_to = NOW(), valid_to_user_id = ? WHERE round_id = ? AND valid_to IS NULL')
               ->execute([$userId, $roundId]);
            $db->commit();
            jsonResponse(['success' => true, 'message' => 'Kolo bylo smazáno']);
        } catch (Exception $e) {
            $db->rollBack();
            error_log('rounds.php delete: ' . $e->getMessage());
            jsonResponse(['success' => false, 'error' => 'Chyba serveru.'], 500);
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
