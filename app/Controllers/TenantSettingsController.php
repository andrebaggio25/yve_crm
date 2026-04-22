<?php

namespace App\Controllers;

use App\Core\App;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\TenantContext;

class TenantSettingsController
{
    public function page(Request $request, Response $response): void
    {
        $user = Session::user();
        // Usa o tenant do contexto (fallback para user.tenant_id)
        $tid = TenantContext::getTenantId() ?? (int) ($user['tenant_id'] ?? 0);
        $tenant = $tid ? Database::fetch('SELECT * FROM tenants WHERE id = :id', [':id' => $tid]) : null;

        $response->view('settings.tenant', [
            'title' => 'Organizacao',
            'pageTitle' => 'Dados da organizacao',
            'tenant' => $tenant,
        ]);
    }

    public function apiUpdate(Request $request, Response $response): void
    {
        $data = $request->getJsonInput() ?? [];
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            $response->jsonError('Nome obrigatorio', 422);

            return;
        }

        $settings = $data['settings'] ?? null;
        if (!is_array($settings)) {
            $settings = null;
        }

        try {
            $user = Session::user();
            // Usa o tenant do contexto (fallback para user.tenant_id)
            $tid = TenantContext::getTenantId() ?? (int) ($user['tenant_id'] ?? 0);
            if ($tid <= 0) {
                $response->jsonError('Sem tenant associado ao usuario', 400);

                return;
            }

            // Verifica se o tenant existe
            $row = Database::fetch('SELECT id, settings_json FROM tenants WHERE id = :id', [':id' => $tid]);
            if (!$row) {
                $response->jsonError('Tenant nao encontrado', 404);

                return;
            }

            $merged = [];
            if (!empty($row['settings_json'])) {
                $decoded = is_string($row['settings_json']) ? json_decode($row['settings_json'], true) : $row['settings_json'];
                $merged = is_array($decoded) ? $decoded : [];
            }
            if (is_array($settings)) {
                $oldSmtpPwd = $merged['smtp_password'] ?? '';
                $preserveSmtpPwd = array_key_exists('smtp_password', $settings)
                    && trim((string) $settings['smtp_password']) === '';
                if ($preserveSmtpPwd) {
                    unset($settings['smtp_password']);
                }
                $merged = array_merge($merged, $settings);
                if ($preserveSmtpPwd && $oldSmtpPwd !== '') {
                    $merged['smtp_password'] = $oldSmtpPwd;
                }
            }

            Database::update(
                'tenants',
                [
                    'name' => $name,
                    'settings_json' => json_encode($merged, JSON_UNESCAPED_UNICODE),
                ],
                'id = :id',
                [':id' => $tid]
            );

            Session::remove('_tenant_cache');
            App::log("Tenant {$tid} atualizado por user {$user['id']}");

            $response->jsonSuccess(['tenant_id' => $tid], 'Salvo com sucesso');
        } catch (\Throwable $e) {
            App::logError('Erro ao salvar tenant', $e);
            $response->jsonError('Erro ao salvar: ' . $e->getMessage(), 500);
        }
    }
}
