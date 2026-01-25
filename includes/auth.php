<?php
/**
 * Autentizační třída
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/db.php';

class Auth {
    private $db;
    
    public function __construct() {
        $this->db = getDB();
        $this->initSession();
    }
    
    private function initSession() {
        if (session_status() === PHP_SESSION_NONE) {
            // Kompatibilita s PHP 7.2+
            $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
            
            if (PHP_VERSION_ID >= 70300) {
                session_set_cookie_params([
                    'lifetime' => SESSION_LIFETIME,
                    'path' => '/',
                    'secure' => $secure,
                    'httponly' => true,
                    'samesite' => 'Strict'
                ]);
            } else {
                session_set_cookie_params(
                    SESSION_LIFETIME,
                    '/; samesite=Strict',
                    '',
                    $secure,
                    true
                );
            }
            session_start();
        }
    }
    
    /**
     * Registrace nového uživatele
     */
    public function register(string $username, string $email, string $password): array {
        // Validace
        if (strlen($username) < 3 || strlen($username) > 50) {
            return ['success' => false, 'error' => 'Uživatelské jméno musí mít 3-50 znaků'];
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Neplatný email'];
        }
        
        if (strlen($password) < 6) {
            return ['success' => false, 'error' => 'Heslo musí mít alespoň 6 znaků'];
        }
        
        // Kontrola duplicit
        $stmt = $this->db->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
        $stmt->execute([$username, $email]);
        
        if ($stmt->fetch()) {
            return ['success' => false, 'error' => 'Uživatelské jméno nebo email již existuje'];
        }
        
        // Vytvoření uživatele
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $this->db->prepare('INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)');
        $stmt->execute([$username, $email, $passwordHash]);
        
        $userId = $this->db->lastInsertId();
        
        // Vytvoření statistik
        $stmt = $this->db->prepare('INSERT INTO user_stats (user_id) VALUES (?)');
        $stmt->execute([$userId]);
        
        return ['success' => true, 'user_id' => $userId];
    }
    
    /**
     * Přihlášení uživatele
     */
    public function login(string $username, string $password): array {
        $stmt = $this->db->prepare('SELECT id, username, password_hash, is_active FROM users WHERE username = ? OR email = ?');
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return ['success' => false, 'error' => 'Neplatné přihlašovací údaje'];
        }
        
        if (!$user['is_active']) {
            return ['success' => false, 'error' => 'Účet je deaktivován'];
        }
        
        if (!password_verify($password, $user['password_hash'])) {
            return ['success' => false, 'error' => 'Neplatné přihlašovací údaje'];
        }
        
        // Aktualizace last_login
        $stmt = $this->db->prepare('UPDATE users SET last_login = NOW() WHERE id = ?');
        $stmt->execute([$user['id']]);
        
        // Nastavení session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['logged_in'] = true;
        
        return ['success' => true, 'user' => ['id' => $user['id'], 'username' => $user['username']]];
    }
    
    /**
     * Odhlášení
     */
    public function logout(): void {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'] ?? '',
                $params['secure'] ?? false, $params['httponly'] ?? true
            );
        }
        session_destroy();
    }
    
    /**
     * Kontrola přihlášení
     */
    public function isLoggedIn(): bool {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    /**
     * Získání ID přihlášeného uživatele
     */
    public function getUserId(): ?int {
        return $_SESSION['user_id'] ?? null;
    }
    
    /**
     * Získání uživatelského jména
     */
    public function getUsername(): ?string {
        return $_SESSION['username'] ?? null;
    }
    
    /**
     * Získání dat uživatele
     */
    public function getUserData(): ?array {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        $stmt = $this->db->prepare('
            SELECT u.id, u.username, u.email, u.created_at, 
                   s.games_played, s.games_won, s.total_score, s.best_score, s.perfect_rounds
            FROM users u
            LEFT JOIN user_stats s ON s.user_id = u.id
            WHERE u.id = ?
        ');
        $stmt->execute([$this->getUserId()]);
        return $stmt->fetch();
    }
}
