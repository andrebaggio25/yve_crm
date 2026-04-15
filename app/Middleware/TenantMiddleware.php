<?php

namespace App\Middleware;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\TenantContext;

class TenantMiddleware
{
    public function handle(Request $request, Response $response): bool
    {
        Session::start();

        $wantsJson = $request->isJson() || str_starts_with($request->getPath(), 'api/');

        $user = Session::user();
        if (!$user) {
            TenantContext::clear();

            return true;
        }

        $impersonate = Session::get('impersonate_tenant_id');
        if ($impersonate !== null && $impersonate !== '' && (string) ($user['role'] ?? '') === 'superadmin') {
            $tenantId = (int) $impersonate;
        } else {
            $tenantId = isset($user['tenant_id']) ? (int) $user['tenant_id'] : 0;
        }

        if ($tenantId <= 0) {
            TenantContext::clear();
            if ($wantsJson) {
                $response->jsonError('Usuario sem tenant associado', 403);
            } else {
                $response->with('error', 'Usuario sem tenant associado.')->redirect('/login');
            }

            return false;
        }

        $cacheKey = '_tenant_cache';
        $cached = Session::get($cacheKey);
        if (is_array($cached) && (int) ($cached['id'] ?? 0) === $tenantId) {
            $tenant = $cached;
        } else {
            $tenant = Database::fetch(
                'SELECT * FROM tenants WHERE id = :id LIMIT 1',
                [':id' => $tenantId]
            );
            if (!$tenant) {
                TenantContext::clear();
                if ($wantsJson) {
                    $response->jsonError('Tenant nao encontrado', 403);
                } else {
                    $response->with('error', 'Tenant invalido.')->redirect('/login');
                }

                return false;
            }
            Session::set($cacheKey, $tenant);
        }

        $status = (string) ($tenant['status'] ?? '');
        if (!in_array($status, ['active', 'trial'], true)) {
            TenantContext::clear();
            if ($wantsJson) {
                $response->jsonError('Tenant suspenso ou cancelado', 403);
            } else {
                $response->with('error', 'Conta suspensa. Contate o suporte.')->redirect('/login');
            }

            return false;
        }

        TenantContext::setTenant($tenantId, $tenant);

        return true;
    }
}
