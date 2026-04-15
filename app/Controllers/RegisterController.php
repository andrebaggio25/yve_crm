<?php

namespace App\Controllers;

use App\Core\App;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\TenantOnboardingService;

class RegisterController
{
    public function show(Request $request, Response $response): void
    {
        $response->view('auth.register', [], null);
    }

    public function register(Request $request, Response $response): void
    {
        try {
            $data = $request->validate([
                'company' => 'required|min:2',
                'name' => 'required|min:2',
                'email' => 'required|email',
                'password' => 'required|min:6',
            ]);
        } catch (\InvalidArgumentException $e) {
            $errors = json_decode($e->getMessage(), true);
            $response->withErrors($errors)->withInput()->back();

            return;
        }

        $slug = $this->uniqueSlug($this->slugify($data['company']));

        $exists = Database::fetch('SELECT id FROM users WHERE email = :e AND deleted_at IS NULL', [':e' => $data['email']]);
        if ($exists) {
            $response->with('error', 'Este email ja esta cadastrado.')->withInput()->back();

            return;
        }

        $db = Database::getInstance();

        try {
            $db->beginTransaction();

            $tenantId = Database::insert('tenants', [
                'name' => $data['company'],
                'slug' => $slug,
                'plan' => 'free',
                'status' => 'trial',
                'max_users' => 5,
                'max_leads' => 500,
            ]);

            $userId = Database::insert('users', [
                'tenant_id' => $tenantId,
                'name' => $data['name'],
                'email' => $data['email'],
                'password_hash' => password_hash($data['password'], PASSWORD_BCRYPT),
                'role' => 'admin',
                'status' => 'active',
            ]);

            Database::update('tenants', ['owner_user_id' => $userId], 'id = :id', [':id' => $tenantId]);

            TenantOnboardingService::seed($db, $tenantId);

            $db->commit();

            App::log("Registro tenant={$tenantId} user={$userId}");
            $response->with('success', 'Conta criada! Faca login para continuar.')->redirect('/login');
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            App::logError('Erro no registro', $e);
            $response->with('error', 'Nao foi possivel criar a conta. Tente novamente.')->withInput()->back();
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
}
