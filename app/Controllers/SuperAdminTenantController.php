<?php

namespace App\Controllers;

use App\Core\App;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\TenantOnboardingService;

class SuperAdminTenantController
{
    public function index(Request $request, Response $response): void
    {
        $response->view('superadmin.tenants', [
            'title' => 'Super Admin',
            'pageTitle' => 'Tenants',
        ]);
    }

    /**
     * Criar novo tenant + usuario admin (superadmin apenas)
     */
    public function apiCreate(Request $request, Response $response): void
    {
        $data = $request->getJsonInput() ?? [];

        $company = trim((string) ($data['company'] ?? ''));
        $name = trim((string) ($data['name'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        // Validacoes
        if (strlen($company) < 2) {
            $response->jsonError('Nome da empresa invalido (min 2 caracteres)', 422);
            return;
        }
        if (strlen($name) < 2) {
            $response->jsonError('Nome do administrador invalido', 422);
            return;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $response->jsonError('Email invalido', 422);
            return;
        }
        if (strlen($password) < 8) {
            $response->jsonError('Senha muito curta (min 8 caracteres)', 422);
            return;
        }

        $timezone = trim((string) ($data['timezone'] ?? 'Europe/Madrid'));
        try {
            new \DateTimeZone($timezone === '' ? 'Europe/Madrid' : $timezone);
        } catch (\Throwable $e) {
            $timezone = 'Europe/Madrid';
        }
        $defaultLocale = (string) ($data['default_locale'] ?? 'es');
        if (!in_array($defaultLocale, ['en', 'es', 'pt'], true)) {
            $defaultLocale = 'es';
        }
        $currency = strtoupper((string) ($data['currency'] ?? 'EUR'));
        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            $currency = 'EUR';
        }

        // Verifica se email ja existe
        $exists = Database::fetch('SELECT id FROM users WHERE email = :e AND deleted_at IS NULL', [':e' => $email]);
        if ($exists) {
            $response->jsonError('Este email ja esta em uso', 422);
            return;
        }

        // Gera slug unico
        $slug = $this->uniqueSlug($this->slugify($company));

        $db = Database::getInstance();

        try {
            $db->beginTransaction();

            // Cria tenant
            $tenantId = Database::insert('tenants', [
                'name' => $company,
                'slug' => $slug,
                'plan' => 'free',
                'status' => 'trial',
                'max_users' => 5,
                'max_leads' => 500,
                'timezone' => $timezone,
                'default_locale' => $defaultLocale,
                'currency' => $currency,
            ]);

            // Cria usuario admin
            $userId = Database::insert('users', [
                'tenant_id' => $tenantId,
                'name' => $name,
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_BCRYPT),
                'role' => 'admin',
                'status' => 'active',
                'locale' => $defaultLocale,
            ]);

            // Atualiza owner do tenant
            Database::update('tenants', ['owner_user_id' => $userId], 'id = :id', [':id' => $tenantId]);

            // Seed inicial (pipeline e etapas)
            TenantOnboardingService::seed($db, $tenantId);

            $db->commit();

            App::log("SuperAdmin criou tenant {$tenantId} com admin {$userId}");

            $response->jsonSuccess([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'email' => $email,
            ], 'Empresa criada com sucesso');
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            App::logError('Erro ao criar tenant pelo superadmin', $e);
            $response->jsonError('Erro ao criar empresa: ' . $e->getMessage(), 500);
        }
    }

    private function slugify(string $text): string
    {
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        $text = @iconv('UTF-8', 'ASCII//TRANSLIT', $text) ?: $text;
        $text = preg_replace('~[^-\w]+~', '', $text);
        $text = trim($text, '-');
        $text = preg_replace('~-+~', '-', $text);
        return strtolower($text ?: 'empresa');
    }

    private function uniqueSlug(string $base): string
    {
        $slug = $base;
        $n = 0;
        while (Database::fetch('SELECT id FROM tenants WHERE slug = :s LIMIT 1', [':s' => $slug])) {
            $n++;
            $slug = $base . '-' . $n;
        }
        return $slug;
    }

    public function apiList(Request $request, Response $response): void
    {
        try {
            $rows = Database::fetchAll(
                "SELECT t.*, 
                    (SELECT COUNT(*) FROM users u WHERE u.tenant_id = t.id AND u.deleted_at IS NULL) AS users_count,
                    (SELECT COUNT(*) FROM leads l WHERE l.tenant_id = t.id AND l.deleted_at IS NULL) AS leads_count
                 FROM tenants t
                 ORDER BY t.id DESC"
            );
            $response->jsonSuccess(['tenants' => $rows]);
        } catch (\Throwable $e) {
            $response->jsonError('Erro ao listar', 500);
        }
    }

    public function apiSetStatus(Request $request, Response $response): void
    {
        $id = (int) ($request->getParam('id') ?? 0);
        $data = $request->getJsonInput() ?? [];
        $status = (string) ($data['status'] ?? '');
        if ($id <= 0 || !in_array($status, ['active', 'trial', 'suspended', 'cancelled'], true)) {
            $response->jsonError('Dados invalidos', 422);

            return;
        }
        try {
            Database::update('tenants', ['status' => $status], 'id = :id', [':id' => $id]);
            $response->jsonSuccess([], 'Atualizado');
        } catch (\Throwable $e) {
            $response->jsonError('Erro', 500);
        }
    }

    public function impersonate(Request $request, Response $response): void
    {
        $id = (int) ($request->getParam('id') ?? 0);
        if ($id <= 0) {
            $response->jsonError('ID invalido', 400);

            return;
        }
        Session::set('impersonate_tenant_id', $id);
        $response->jsonSuccess(['redirect' => '/kanban'], 'Impersonando tenant');
    }

    public function stopImpersonate(Request $request, Response $response): void
    {
        Session::remove('impersonate_tenant_id');
        $response->jsonSuccess(['redirect' => '/superadmin/tenants'], 'Impersonacao encerrada');
    }
}
