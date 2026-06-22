<?php
/**
 * API endpoint pro kontaktní formulář (zájem o přístup k záznamníku).
 * Odesílá e-mail na honza@tobolik.cz. Bez DB.
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);

function handleError($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Server error'], JSON_UNESCAPED_UNICODE);
    exit;
}
set_error_handler('handleError');

function handleShutdown() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Fatal error'], JSON_UNESCAPED_UNICODE);
    }
}
register_shutdown_function('handleShutdown');

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    require_once __DIR__ . '/../includes/helpers.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['success' => false, 'error' => 'Neplatná metoda'], 405);
    }

    // Jednoduchý rate-limit (IP, 1 zpráva / 30 s)
    session_start();
    $rateKey = 'contact_last_' . md5($_SERVER['REMOTE_ADDR'] ?? 'x');
    if (isset($_SESSION[$rateKey]) && (time() - $_SESSION[$rateKey]) < 30) {
        jsonResponse(['success' => false, 'error' => 'Chvilku počkejte, než odešlete další zprávu.'], 429);
    }

    $input = getJsonInput();
    $name    = trim($input['name'] ?? '');
    $email   = trim($input['email'] ?? '');
    $message = trim($input['message'] ?? '');
    $honey   = trim($input['website'] ?? ''); // honeypot

    // Honeypot — bot vyplnil skryté pole → tváříme se jako úspěch, nic neposíláme
    if ($honey !== '') {
        jsonResponse(['success' => true]);
    }

    // Validace
    if ($name === '' || $email === '' || $message === '') {
        jsonResponse(['success' => false, 'error' => 'Vyplňte prosím všechna pole.'], 400);
    }
    if (mb_strlen($name) > 100) {
        jsonResponse(['success' => false, 'error' => 'Jméno je příliš dlouhé.'], 400);
    }
    if (mb_strlen($message) > 5000) {
        jsonResponse(['success' => false, 'error' => 'Zpráva je příliš dlouhá.'], 400);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $email)) {
        jsonResponse(['success' => false, 'error' => 'Neplatný e-mail.'], 400);
    }

    // Sestavení e-mailu — From pevné (SPF/doručitelnost), Reply-To = odesílatel
    $to      = 'honza@tobolik.cz';
    $subject = 'Stychy.cz – kontakt: ' . mb_substr(strip_tags($name), 0, 60);
    $body  = "Nová zpráva z kontaktního formuláře na stychy.cz\n\n";
    $body .= 'Jméno: ' . strip_tags($name) . "\n";
    $body .= 'E-mail: ' . $email . "\n";
    $body .= 'Čas: ' . date('Y-m-d H:i:s') . "\n\n";
    $body .= "Zpráva:\n" . str_repeat('-', 40) . "\n";
    $body .= strip_tags($message) . "\n";

    $headers  = "From: Štychy web <noreply@stychy.cz>\r\n";
    $headers .= 'Reply-To: ' . $email . "\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= "X-Mailer: Stychy-Contact\r\n";

    // Předmět musí být MIME-enkódovaný kvůli diakritice
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

    if (@mail($to, $encodedSubject, $body, $headers)) {
        $_SESSION[$rateKey] = time();
        jsonResponse(['success' => true, 'message' => 'Zpráva odeslána. Děkujeme, ozveme se vám.']);
    } else {
        error_log('contact.php: mail() selhal pro ' . $email);
        jsonResponse(['success' => false, 'error' => 'Zprávu se nepodařilo odeslat. Zkuste to prosím později nebo napište přímo na honza@tobolik.cz.'], 500);
    }

} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Chyba serveru.'], JSON_UNESCAPED_UNICODE);
}
