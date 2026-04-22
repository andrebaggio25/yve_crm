<?php

namespace App\Services;

use App\Core\Database;

/**
 * Hora "agora" no fuso do tenant (automacoes e exibicao).
 */
class TenantTime
{
    public static function nowForTenant(int $tenantId): \DateTimeImmutable
    {
        $row = Database::fetch('SELECT timezone FROM tenants WHERE id = :id LIMIT 1', [':id' => $tenantId]);
        $tz = (string) ($row['timezone'] ?? 'UTC');
        try {
            $zone = new \DateTimeZone($tz);
        } catch (\Throwable $e) {
            $zone = new \DateTimeZone('UTC');
        }

        return new \DateTimeImmutable('now', $zone);
    }
}
