<?php

namespace App\Core;

/**
 * Contexto do tenant atual (request autenticada).
 * Definido pelo TenantMiddleware apos login.
 */
class TenantContext
{
    private static ?int $tenantId = null;

    /** @var array<string, mixed>|null */
    private static ?array $tenant = null;

    public static function clear(): void
    {
        self::$tenantId = null;
        self::$tenant = null;
    }

    /**
     * @param array<string, mixed> $tenant Row de tenants.*
     */
    public static function setTenant(int $tenantId, array $tenant): void
    {
        self::$tenantId = $tenantId;
        self::$tenant = $tenant;
    }

    public static function hasTenant(): bool
    {
        return self::$tenantId !== null;
    }

    public static function getTenantId(): ?int
    {
        return self::$tenantId;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function getTenant(): ?array
    {
        return self::$tenant;
    }

    public static function requireTenantId(): int
    {
        if (self::$tenantId === null) {
            throw new \RuntimeException('TenantContext: tenant nao definido');
        }

        return self::$tenantId;
    }

    /**
     * Retorna o tenant_id do contexto ou 1 como fallback (single-tenant).
     * Use para retrocompatibilidade em ambientes sem multi-tenant ativado.
     */
    public static function getEffectiveTenantId(): int
    {
        return self::$tenantId ?? 1;
    }
}
