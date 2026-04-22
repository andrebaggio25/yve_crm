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
        return self::isSystemSmtpComplete(self::getSmtp());
    }

    /**
     * Alias semantico: SMTP global (sistema + .env) pronto.
     */
    public static function isSystemConfigured(): bool
    {
        return self::isConfigured();
    }

    /**
     * @param array{host:string,port:int,encryption:string,username:string,password:string,from_address:string,from_name:string} $c
     */
    public static function isSystemSmtpComplete(array $c): bool
    {
        return $c['host'] !== '' && $c['username'] !== '';
    }

    /**
     * Resolucao para uma linha de fila: tenant com smtp completo no settings_json,
     * senao o mesmo retorno de getSmtp() (sistema + .env).
     *
     * @return array{host:string,port:int,encryption:string,username:string,password:string,from_address:string,from_name:string}
     */
    public static function getSmtpForTenant(?int $tenantId): array
    {
        $global = self::getSmtp();
        if ($tenantId === null || $tenantId <= 0) {
            return $global;
        }

        $row = null;
        try {
            $row = Database::fetch('SELECT settings_json FROM tenants WHERE id = :id LIMIT 1', [':id' => $tenantId]);
        } catch (\Throwable) {
            return $global;
        }
        if (!$row || empty($row['settings_json'])) {
            return $global;
        }
        $decoded = json_decode((string) $row['settings_json'], true);
        if (!is_array($decoded)) {
            return $global;
        }

        $th = self::strOr($decoded['smtp_host'] ?? null, '');
        $tu = self::strOr($decoded['smtp_username'] ?? null, '');

        if ($th === '' || $tu === '') {
            return $global;
        }

        $portGlobal = (int) ($global['port'] > 0 ? $global['port'] : 587);
        $port = self::intOr($decoded['smtp_port'] ?? null, $portGlobal);
        $encRaw = self::strOr($decoded['smtp_encryption'] ?? null, (string) ($global['encryption'] ?? 'tls'));
        $enc = strtolower($encRaw) === 'ssl' ? 'ssl' : (strtolower($encRaw) === 'none' || $encRaw === '' ? 'none' : 'tls');
        $tp = self::strOr($decoded['smtp_password'] ?? null, $global['password']);

        return [
            'host' => $th,
            'port' => $port,
            'encryption' => $enc,
            'username' => $tu,
            'password' => $tp,
            'from_address' => self::strOr($decoded['smtp_from_address'] ?? null, $global['from_address']),
            'from_name' => self::strOr($decoded['smtp_from_name'] ?? null, $global['from_name']),
        ];
    }

    public static function isReadyForOutboxRow(?int $tenantId): bool
    {
        return self::isSystemSmtpComplete(self::getSmtpForTenant($tenantId));
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
