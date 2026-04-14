<?php

namespace App\Core;

class Session
{
    private static bool $started = false;

    public static function start(): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $config = require __DIR__ . '/../../config/app.php';
        
        $secure = \App\Core\Env::bool('SESSION_COOKIE_SECURE', false);

        session_set_cookie_params([
            'lifetime' => $config['session_lifetime'] * 60,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        
        session_start();
        
        if (!isset($_SESSION['_started'])) {
            $_SESSION['_started'] = time();
            $_SESSION['_regenerated'] = time();
        }
        
        self::$started = true;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        self::start();
        return isset($_SESSION[$key]);
    }

    public static function remove(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }

    public static function all(): array
    {
        self::start();
        return $_SESSION;
    }

    public static function clear(): void
    {
        self::start();
        $_SESSION = [];
    }

    public static function destroy(): void
    {
        self::start();
        
        $_SESSION = [];
        
        if (isset($_COOKIE[session_name()])) {
            $secure = \App\Core\Env::bool('SESSION_COOKIE_SECURE', false);
            setcookie(session_name(), '', [
                'expires' => time() - 3600,
                'path' => '/',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        }
        
        session_destroy();
        self::$started = false;
    }

    public static function regenerate(): void
    {
        self::start();
        
        if (time() - ($_SESSION['_regenerated'] ?? 0) > 300) {
            session_regenerate_id(true);
            $_SESSION['_regenerated'] = time();
        }
    }

    public static function flash(string $key, mixed $value = null): mixed
    {
        self::start();
        
        if ($value !== null) {
            $_SESSION['_flash'][$key] = $value;
            return null;
        }
        
        $value = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return $value;
    }

    public static function hasFlash(string $key): bool
    {
        self::start();
        return isset($_SESSION['_flash'][$key]);
    }

    public static function getOld(string $key, mixed $default = null): mixed
    {
        $old = self::flash('old_input') ?: [];
        return $old[$key] ?? $default;
    }

    public static function getErrors(): array
    {
        return self::flash('errors') ?: [];
    }

    public static function hasErrors(): bool
    {
        return !empty(self::getErrors());
    }

    public static function csrfToken(): string
    {
        self::start();
        
        $config = require __DIR__ . '/../../config/app.php';
        $tokenName = $config['csrf_token_name'];
        
        if (!isset($_SESSION[$tokenName])) {
            $_SESSION[$tokenName] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION[$tokenName];
    }

    public static function validateCsrf(string $token): bool
    {
        self::start();
        
        $config = require __DIR__ . '/../../config/app.php';
        $tokenName = $config['csrf_token_name'];
        
        return isset($_SESSION[$tokenName]) && hash_equals($_SESSION[$tokenName], $token);
    }

    public static function csrfField(): string
    {
        $config = require __DIR__ . '/../../config/app.php';
        $token = self::csrfToken();
        $tokenName = $config['csrf_token_name'];
        
        return '<input type="hidden" name="' . $tokenName . '" value="' . htmlspecialchars($token) . '">';
    }

    public static function csrfMeta(): string
    {
        $config = require __DIR__ . '/../../config/app.php';
        $token = self::csrfToken();
        $tokenName = $config['csrf_token_name'];
        
        return '<meta name="csrf-token" content="' . htmlspecialchars($token) . '">';
    }

    public static function isExpired(): bool
    {
        self::start();
        
        $config = require __DIR__ . '/../../config/app.php';
        $lifetime = $config['session_lifetime'] * 60;
        
        return (time() - ($_SESSION['_started'] ?? 0)) > $lifetime;
    }

    public static function user(): ?array
    {
        return self::get('user');
    }

    public static function isAuthenticated(): bool
    {
        return self::has('user');
    }

    public static function hasRole(string $role): bool
    {
        $user = self::user();
        return $user && ($user['role'] === $role || $user['role'] === 'admin');
    }

    public static function logout(): void
    {
        self::destroy();
    }
}
