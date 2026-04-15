<?php

namespace App\Core;

use PDO;

/**
 * Wrapper sobre Database que injeta tenant_id em insert/update/delete
 * e oferece helpers para queries manuais (fetch/fetchAll/query).
 *
 * Tabelas sem tenant: tenants, migrations
 */
class TenantAwareDatabase
{
    private const EXEMPT = ['tenants', 'migrations', 'automation_actions'];

    public static function getInstance(): PDO
    {
        return Database::getInstance();
    }

    /**
     * Mescla :tenant_id (ou sobrescrito) com params para uso em SQL com filtro manual.
     * Em ambiente single-tenant (sem contexto), assume tenant_id = 1.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public static function mergeTenantParams(array $params = [], string $key = ':tenant_id'): array
    {
        if (TenantContext::hasTenant()) {
            return array_merge($params, [$key => TenantContext::requireTenantId()]);
        }

        // Fallback single-tenant: assume tenant 1 para retrocompatibilidade
        return array_merge($params, [$key => 1]);
    }

    public static function query(string $sql, array $params = []): \PDOStatement
    {
        return Database::query($sql, $params);
    }

    public static function fetch(string $sql, array $params = []): ?array
    {
        return Database::fetch($sql, $params);
    }

    public static function fetchAll(string $sql, array $params = []): array
    {
        return Database::fetchAll($sql, $params);
    }

    public static function insert(string $table, array $data): int
    {
        if (!self::isExempt($table)) {
            $data['tenant_id'] = TenantContext::hasTenant()
                ? TenantContext::requireTenantId()
                : 1; // Fallback single-tenant
        }

        return Database::insert($table, $data);
    }

    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        if (!self::isExempt($table)) {
            $where = '(' . $where . ') AND tenant_id = :_tdb_tid';
            $whereParams[':_tdb_tid'] = TenantContext::hasTenant()
                ? TenantContext::requireTenantId()
                : 1; // Fallback single-tenant
        }

        return Database::update($table, $data, $where, $whereParams);
    }

    public static function delete(string $table, string $where, array $whereParams = []): int
    {
        if (!self::isExempt($table)) {
            $where = '(' . $where . ') AND tenant_id = :_tdb_tid';
            $whereParams[':_tdb_tid'] = TenantContext::hasTenant()
                ? TenantContext::requireTenantId()
                : 1; // Fallback single-tenant
        }

        return Database::delete($table, $where, $whereParams);
    }

    private static function isExempt(string $table): bool
    {
        $table = strtolower(trim($table));

        return in_array($table, self::EXEMPT, true);
    }
}
