<?php
/**
 * API endpoint pro autentizaci
 */

// Zobrazení chyb pro debugging (v produkci vypnout)
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Error handler pro zachycení fatálních chyb
function handleError($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $errstr,
        'file' => basename($errfile),
        'line' => $errline
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
set_error_handler('handleError');

// Shutdown handler pro fatální chyby
function handleShutdown() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error' => 'Fatal error: ' . $error['message'],
            'file' => basename($error['file']),
            'line' => $error['line']
        ], JSON_UNESCAPED_UNICODE);
    }
}
register_shutdown_function('handleShutdown');

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

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
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => 'Error: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
