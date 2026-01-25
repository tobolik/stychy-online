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
 * Získání sekvence kol (7,6,5,4,3,2,1,1,2,3,4,5,6,7)
 */
function getRoundSequence(int $maxCards = 7): array {
    $sequence = [];
    // Sestupně: 7, 6, 5, 4, 3, 2, 1
    for ($i = $maxCards; $i >= 1; $i--) {
        $sequence[] = $i;
    }
    // Vzestupně včetně opakování 1: 1, 2, 3, 4, 5, 6, 7
    for ($i = 1; $i <= $maxCards; $i++) {
        $sequence[] = $i;
    }
    return $sequence;
}

/**
 * Kontrola validity sázky dealera
 */
function isValidDealerBid(int $bid, int $currentSum, int $cardsCount): bool {
    // Dealer nesmí nahlásit tak, aby součet = počet karet
    return ($currentSum + $bid) !== $cardsCount;
}
