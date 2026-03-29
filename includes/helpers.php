<?php
/**
 * Pomocné funkce
 */

/**
 * Odeslání JSON odpovědi
 */
function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Získání JSON dat z requestu
 */
function getJsonInput(): array {
    $input = file_get_contents('php://input');
    return json_decode($input, true) ?? [];
}

/**
 * Kontrola přihlášení pro API
 */
function requireAuth(Auth $auth): void {
    if (!$auth->isLoggedIn()) {
        jsonResponse(['success' => false, 'error' => 'Nepřihlášen'], 401);
    }
}

/**
 * Výpočet bodů za kolo
 * - Splněná sázka 0: +5 bodů
 * - Nesplněná sázka 0: -5 bodů
 * - Splněná sázka X: 10 + X bodů
 * - Nesplněná sázka X: -X bodů (záporná hodnota sázky)
 */
function calculateScore(int $bid, int $tricks): int {
    if ($bid === 0) {
        return $tricks === 0 ? 5 : -5;
    }
    
    if ($bid === $tricks) {
        return 10 + $bid;
    }
    
    // Nesplněná sázka = záporná hodnota sázky
    return -$bid;
}

/**
 * Získání sekvence kol (7,6,5,4,3,2,1,2,3,4,5,6,7)
 */
function getRoundSequence(int $maxCards = 7): array {
    $sequence = [];
    // Sestupně: 7, 6, 5, 4, 3, 2, 1
    for ($i = $maxCards; $i >= 1; $i--) {
        $sequence[] = $i;
    }
    // Vzestupně: 2, 3, 4, 5, 6, 7 (1 se neopakuje)
    for ($i = 2; $i <= $maxCards; $i++) {
        $sequence[] = $i;
    }
    return $sequence;
}

/**
 * Celkový počet kol hry — používá uložené total_rounds (nové hry),
 * nebo starý vzorec 2×max_cards (staré hry bez záznamu).
 */
function getTotalRounds(array $game): int {
    if (!empty($game['total_rounds'])) {
        return (int)$game['total_rounds'];
    }
    // Zpětná kompatibilita: staré hry nemají total_rounds, použijeme starý vzorec
    return (int)$game['max_cards'] * 2;
}

/**
 * Kontrola validity sázky dealera
 */
function isValidDealerBid(int $bid, int $currentSum, int $cardsCount): bool {
    // Dealer nesmí nahlásit tak, aby součet = počet karet
    return ($currentSum + $bid) !== $cardsCount;
}
