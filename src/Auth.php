<?php
/**
 * Authentication Handler
 */
class Auth {
    const CSRF_TOKEN_NAME = '_csrf_token';
    const SESSION_USER_KEY = 'user_id';
    const SESSION_ROLE_KEY = 'user_role';

    public static function init() {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.save_path', sys_get_temp_dir());
            ini_set('session.name', 'learning_platform');
            ini_set('session.gc_maxlifetime', 86400 * 7);
            session_start();
        }
    }

    public static function register($username, $email, $password) {
        if (strlen($password) < 8) {
            return ['error' => 'Password must be at least 8 characters'];
        }

        $stmt = DB::prepare('SELECT id FROM users WHERE username = ? OR email = ?');
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            return ['error' => 'Username or email already exists'];
        }

        $password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);

        try {
            DB::execute(
                'INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, ?)',
                [$username, $email, $password_hash, ROLE_USER]
            );
            return ['success' => true];
        } catch (Exception $e) {
            return ['error' => 'Registration failed'];
        }
    }

    public static function login($username, $password) {
        $user = DB::queryOne('SELECT id, username, password_hash, role, is_banned FROM users WHERE username = ?', [$username]);

        if (!$user) {
            return ['error' => 'Invalid credentials'];
        }

        if ($user['is_banned']) {
            return ['error' => 'This account has been banned'];
        }

        if (!password_verify($password, $user['password_hash'])) {
            return ['error' => 'Invalid credentials'];
        }

        self::init();
        $_SESSION[self::SESSION_USER_KEY] = $user['id'];
        $_SESSION[self::SESSION_ROLE_KEY] = $user['role'];

        return ['success' => true, 'user_id' => $user['id']];
    }

    public static function logout() {
        self::init();
        session_destroy();
    }

    public static function isLoggedIn() {
        self::init();
        return isset($_SESSION[self::SESSION_USER_KEY]);
    }

    public static function getCurrentUserId() {
        self::init();
        return $_SESSION[self::SESSION_USER_KEY] ?? null;
    }

    public static function getCurrentUserRole() {
        self::init();
        return $_SESSION[self::SESSION_ROLE_KEY] ?? ROLE_USER;
    }

    public static function isAdmin() {
        return self::getCurrentUserRole() === ROLE_ADMIN;
    }

    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            if (strpos($_SERVER['REQUEST_URI'], '/api/') !== false) {
                http_response_code(401);
                die(json_encode(['error' => 'Unauthorized']));
            }
            header('Location: /login.php');
            exit;
        }
    }

    public static function requireAdmin() {
        if (!self::isLoggedIn()) {
            header('Location: /login.php');
            exit;
        }
        if (!self::isAdmin()) {
            if (strpos($_SERVER['REQUEST_URI'], '/api/') !== false) {
                http_response_code(403);
                die(json_encode(['error' => 'Forbidden']));
            }
            header('Location: /');
            exit;
        }
    }

    public static function generateCSRFToken() {
        self::init();
        if (empty($_SESSION[self::CSRF_TOKEN_NAME])) {
            $_SESSION[self::CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::CSRF_TOKEN_NAME];
    }

    public static function validateCSRFToken($token) {
        self::init();
        return isset($_SESSION[self::CSRF_TOKEN_NAME]) && hash_equals($_SESSION[self::CSRF_TOKEN_NAME], $token);
    }
}
