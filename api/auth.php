<?php
/**
 * API endpoint pro autentizaci
 */

// Zobrazení chyb pro debugging (v produkci vypnout)
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Error handler - klientovi jen generická hláška, detail do logu
function handleError($errno, $errstr, $errfile, $errline) {
    error_log("auth.php error: $errstr in $errfile:$errline");
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Chyba serveru.'], JSON_UNESCAPED_UNICODE);
    exit;
}
set_error_handler('handleError');

function handleShutdown() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        error_log("auth.php fatal: {$error['message']} in {$error['file']}:{$error['line']}");
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Chyba serveru.'], JSON_UNESCAPED_UNICODE);
    }
}
register_shutdown_function('handleShutdown');

try {
    require_once __DIR__ . '/../includes/auth.php';
    require_once __DIR__ . '/../includes/helpers.php';

    $auth = new Auth();
    $input = getJsonInput();
    $action = $input['action'] ?? $_GET['action'] ?? '';

    switch ($action) {
        case 'register':
            $username = trim($input['username'] ?? '');
            $email = trim($input['email'] ?? '');
            $password = $input['password'] ?? '';
            
            $result = $auth->register($username, $email, $password);
            jsonResponse($result, $result['success'] ? 200 : 400);
            break;
            
        case 'login':
            $username = trim($input['username'] ?? '');
            $password = $input['password'] ?? '';
            
            $result = $auth->login($username, $password);
            jsonResponse($result, $result['success'] ? 200 : 401);
            break;
            
        case 'logout':
            $auth->logout();
            jsonResponse(['success' => true]);
            break;
            
        case 'check':
            if ($auth->isLoggedIn()) {
                jsonResponse([
                    'success' => true,
                    'logged_in' => true,
                    'user' => $auth->getUserData()
                ]);
            } else {
                jsonResponse([
                    'success' => true,
                    'logged_in' => false
                ]);
            }
            break;
            
        default:
            jsonResponse(['success' => false, 'error' => 'Neznámá akce'], 400);
    }
} catch (PDOException $e) {
    error_log('auth.php DB error: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Chyba serveru.'], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log('auth.php error: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Chyba serveru.'], JSON_UNESCAPED_UNICODE);
}
