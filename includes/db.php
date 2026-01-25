<?php
/**
 * Databázové připojení pomocí PDO
 */

require_once __DIR__ . '/../config/database.php';

class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        try {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                DB_HOST,
                DB_NAME,
                DB_CHARSET
            );
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            throw new Exception('Chyba připojení k databázi: ' . $e->getMessage());
        }
    }

    public static function getInstance(): Database {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection(): PDO {
        return $this->pdo;
    }

    // Zabránění klonování
    private function __clone() {}

    // Zabránění deserializaci - kompatibilní s PHP 8+
    public function __wakeup(): void {
        throw new Exception('Cannot unserialize singleton');
    }
}

/**
 * Pomocná funkce pro získání PDO instance
 */
function getDB(): PDO {
    return Database::getInstance()->getConnection();
}
