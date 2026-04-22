<?php

namespace App\Services\Mail;

use App\Core\Database;
use App\Core\Env;

/**
 * Config SMTP: preferencia para valores em system tenant (settings_json id=0);
 * alternativa: variaveis MAIL_* do .env.
 */
class MailConfig
{
    /**
     * @return array{host:string,port:int,encryption:string,username:string,password:string,from_address:string,from_name:string}
     */
    public static function getSmtp(): array
    {
        $db = self::readSystemSettings();

        $portEnv = (int) (Env::get('MAIL_PORT', '587') ?: 587);

        $host = self::strOr($db['smtp_host'] ?? null, Env::get('MAIL_HOST', ''));
        $port = self::intOr($db['smtp_port'] ?? null, $portEnv > 0 ? $portEnv : 587);
        $encRaw = self::strOr($db['smtp_encryption'] ?? null, Env::get('MAIL_ENCRYPTION', 'tls'));
        $enc = strtolower($encRaw) === 'ssl' ? 'ssl' : (strtolower($encRaw) === 'none' || $encRaw === '' ? 'none' : 'tls');

        return [
            'host' => $host,
            'port' => $port,
            'encryption' => $enc,
            'username' => self::strOr($db['smtp_username'] ?? null, Env::get('MAIL_USERNAME', '')),
            'password' => self::strOr($db['smtp_password'] ?? null, Env::get('MAIL_PASSWORD', '')),
            'from_address' => self::strOr($db['smtp_from_address'] ?? null, Env::get('MAIL_FROM_ADDRESS', 'noreply@example.com')),
            'from_name' => self::strOr($db['smtp_from_name'] ?? null, Env::get('MAIL_FROM_NAME', 'Yve CRM')),
        ];
    }

    public static function isConfigured(): bool
    {
        $c = self::getSmtp();

        return $c['host'] !== '' && $c['username'] !== '';
    }

    private static function strOr(mixed $v, string $fallback): string
    {
        if ($v === null) {
            return $fallback;
        }
        if (is_string($v) || is_numeric($v)) {
            return trim((string) $v);
        }

        return $fallback;
    }

    private static function intOr(mixed $v, int $fallback): int
    {
        if ($v === null || $v === '') {
            return $fallback;
        }
        $i = (int) $v;

        return $i > 0 ? $i : $fallback;
    }

    /**
     * @return array<string, mixed>
     */
    public static function readSystemSettings(): array
    {
        try {
            $row = Database::fetch(
                "SELECT settings_json FROM tenants WHERE id = 0 OR slug = 'system' LIMIT 1"
            );
            if ($row && !empty($row['settings_json'])) {
                $decoded = json_decode((string) $row['settings_json'], true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        } catch (\Throwable) {
        }

        return [];
    }
}
