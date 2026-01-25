<?php
/**
 * Test připojení k databázi a PHP konfigurace
 * Po otestování SMAZAT!
 */

header('Content-Type: application/json; charset=utf-8');

$result = [
    'php_version' => PHP_VERSION,
    'php_version_id' => PHP_VERSION_ID,
    'extensions' => [
        'pdo' => extension_loaded('pdo'),
        'pdo_mysql' => extension_loaded('pdo_mysql'),
        'json' => extension_loaded('json'),
        'session' => extension_loaded('session')
    ],
    'config_exists' => false,
    'db_connection' => false,
    'tables_exist' => false,
    'errors' => []
];

// Test konfigurace
$configPath = __DIR__ . '/../config/database.php';
if (file_exists($configPath)) {
    $result['config_exists'] = true;
    try {
        require_once $configPath;
        $result['db_name'] = DB_NAME ?? 'not set';
        $result['db_host'] = DB_HOST ?? 'not set';
    } catch (Exception $e) {
        $result['errors'][] = 'Config error: ' . $e->getMessage();
    }
} else {
    $result['errors'][] = 'Config file not found: ' . $configPath;
}

// Test DB připojení
if ($result['config_exists']) {
    try {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_NAME);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        $result['db_connection'] = true;
        
        // Test tabulek
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        $result['tables'] = $tables;
        $result['tables_exist'] = in_array('users', $tables);
        
    } catch (PDOException $e) {
        $result['errors'][] = 'DB error: ' . $e->getMessage();
    }
}

// Test session
try {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $result['session_works'] = true;
} catch (Exception $e) {
    $result['session_works'] = false;
    $result['errors'][] = 'Session error: ' . $e->getMessage();
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
