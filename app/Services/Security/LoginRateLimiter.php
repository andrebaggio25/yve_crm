<?php

namespace App\Services\Security;

use App\Core\Database;
use App\Core\Env;

/**
 * Limita tentativas de login por IP e por e-mail (hash).
 */
class LoginRateLimiter
{
    public static function maxAttempts(): int
    {
        return max(3, (int) Env::get('LOGIN_MAX_ATTEMPTS', '8'));
    }

    public static function decayMinutes(): int
    {
        return max(5, (int) Env::get('LOGIN_DECAY_MINUTES', '15'));
    }

    public static function emailHash(string $email): string
    {
        return hash('sha256', strtolower(trim($email)));
    }

    public static function recordFailure(string $ip, string $email): void
    {
        $eh = self::emailHash($email);
        $stmt = Database::getInstance()->prepare(
            'INSERT INTO login_attempts (ip_address, email_hash, attempted_at, success) VALUES (:ip, :eh, NOW(), 0)'
        );
        $stmt->execute([':ip' => $ip, ':eh' => $eh]);
    }

    public static function recordSuccess(string $ip, string $email): void
    {
        $eh = self::emailHash($email);
        $stmt = Database::getInstance()->prepare(
            'INSERT INTO login_attempts (ip_address, email_hash, attempted_at, success) VALUES (:ip, :eh, NOW(), 1)'
        );
        $stmt->execute([':ip' => $ip, ':eh' => $eh]);
    }

    public static function isTooManyAttempts(string $ip, string $email): bool
    {
        $since = date('Y-m-d H:i:s', time() - self::decayMinutes() * 60);
        $max = self::maxAttempts();
        $eh = self::emailHash($email);

        $ipCount = Database::fetch(
            'SELECT COUNT(*) AS c FROM login_attempts WHERE ip_address = :ip AND attempted_at >= :since AND success = 0',
            [':ip' => $ip, ':since' => $since]
        );
        if ((int) ($ipCount['c'] ?? 0) >= $max) {
            return true;
        }

        $emailCount = Database::fetch(
            'SELECT COUNT(*) AS c FROM login_attempts WHERE email_hash = :eh AND attempted_at >= :since AND success = 0',
            [':eh' => $eh, ':since' => $since]
        );

        return (int) ($emailCount['c'] ?? 0) >= $max;
    }

    /** Apenas IP (esquecer senha) para nao bloquear email da vitima. */
    public static function isTooManyFromIp(string $ip): bool
    {
        $since = date('Y-m-d H:i:s', time() - self::decayMinutes() * 60);
        $ipCount = Database::fetch(
            'SELECT COUNT(*) AS c FROM login_attempts WHERE ip_address = :ip AND attempted_at >= :since',
            [':ip' => $ip, ':since' => $since]
        );

        return (int) ($ipCount['c'] ?? 0) >= self::maxAttempts() * 4;
    }

    public static function recordGenericAttempt(string $ip): void
    {
        $stmt = Database::getInstance()->prepare(
            'INSERT INTO login_attempts (ip_address, email_hash, attempted_at, success) VALUES (:ip, :eh, NOW(), 0)'
        );
        $stmt->execute([':ip' => $ip, ':eh' => hash('sha256', 'forgot-form')]);
    }
}
