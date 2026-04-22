<?php

namespace App\Controllers;

use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\TenantAwareDatabase;
use App\Core\TenantContext;

class UserController
{
    public function index(Request $request, Response $response): void
    {
        $users = TenantAwareDatabase::fetchAll(
            "SELECT id, name, email, role, status, phone, locale, created_at
             FROM users
             WHERE deleted_at IS NULL AND tenant_id = :tenant_id
             ORDER BY created_at DESC",
            TenantAwareDatabase::mergeTenantParams()
        );

        $response->view('users.index', [
            'title' => __('users.title'),
            'pageTitle' => __('users.page_title'),
            'users' => $users,
        ]);
    }

    public function apiList(Request $request, Response $response): void
    {
        try {
            $users = TenantAwareDatabase::fetchAll(
                "SELECT id, name, email, role, status, phone, locale, created_at
                 FROM users
                 WHERE deleted_at IS NULL AND tenant_id = :tenant_id
                 ORDER BY created_at DESC",
                TenantAwareDatabase::mergeTenantParams()
            );

            $response->jsonSuccess(['users' => $users]);
        } catch (\Exception $e) {
            App::logError('Erro ao listar usuarios', $e);
            $response->jsonError('Erro ao carregar usuarios', 500);
        }
    }

    public function apiShow(Request $request, Response $response): void
    {
        $id = $request->getParam('id');

        if (!$id) {
            $response->jsonError('ID nao fornecido', 400);

            return;
        }

        try {
            $user = TenantAwareDatabase::fetch(
                "SELECT id, name, email, role, status, phone, locale, created_at
                 FROM users
                 WHERE id = :id AND deleted_at IS NULL AND tenant_id = :tenant_id",
                TenantAwareDatabase::mergeTenantParams([':id' => $id])
            );

            if (!$user) {
                $response->jsonError('Usuario nao encontrado', 404);

                return;
            }

            $response->jsonSuccess(['user' => $user]);
        } catch (\Exception $e) {
            App::logError('Erro ao buscar usuario', $e);
            $response->jsonError('Erro ao carregar usuario', 500);
        }
    }

    public function apiCreate(Request $request, Response $response): void
    {
        try {
            $data = $request->validate([
                'name' => 'required|min:3',
                'email' => 'required|email',
                'password' => 'required|min:8',
                'role' => 'required',
                'phone' => '',
                'locale' => '',
            ]);
        } catch (\InvalidArgumentException $e) {
            $errors = json_decode($e->getMessage(), true);
            $response->jsonError('Dados invalidos', 422, $errors);

            return;
        }

        $json = $request->getJsonInput();
        if (isset($json['locale'])) {
            $data['locale'] = $json['locale'];
        }

        if (($data['role'] ?? '') === 'superadmin' && (string) (Session::user()['role'] ?? '') !== 'superadmin') {
            $response->jsonError('Perfil invalido', 422);

            return;
        }

        $existing = TenantAwareDatabase::fetch(
            'SELECT id FROM users WHERE email = :email AND deleted_at IS NULL AND tenant_id = :tenant_id',
            TenantAwareDatabase::mergeTenantParams([':email' => $data['email']])
        );

        if ($existing) {
            $response->jsonError('Este email ja esta em uso', 422, ['email' => ['Email ja cadastrado']]);

            return;
        }

        $tenant = TenantContext::getTenant();
        $tloc = in_array($tenant['default_locale'] ?? 'es', ['en', 'es', 'pt'], true) ? (string) ($tenant['default_locale'] ?? 'es') : 'es';
        $uloc = (string) ($data['locale'] ?? $tloc);
        if (!in_array($uloc, ['en', 'es', 'pt'], true)) {
            $uloc = $tloc;
        }

        try {
            $userId = TenantAwareDatabase::insert('users', [
                'name' => $data['name'],
                'email' => $data['email'],
                'password_hash' => password_hash($data['password'], PASSWORD_BCRYPT),
                'role' => $data['role'],
                'phone' => $data['phone'] ?? null,
                'status' => 'active',
                'locale' => $uloc,
            ]);

            $user = TenantAwareDatabase::fetch(
                'SELECT id, name, email, role, status, phone, locale, created_at FROM users WHERE id = :id AND tenant_id = :tenant_id',
                TenantAwareDatabase::mergeTenantParams([':id' => $userId])
            );

            App::log("Usuario criado: {$user['email']}");

            $response->jsonSuccess(['user' => $user], 'Usuario criado com sucesso');
        } catch (\Exception $e) {
            App::logError('Erro ao criar usuario', $e);
            $response->jsonError('Erro ao criar usuario', 500);
        }
    }

    public function apiUpdate(Request $request, Response $response): void
    {
        $id = $request->getParam('id');

        if (!$id) {
            $response->jsonError('ID nao fornecido', 400);

            return;
        }

        $data = $request->getJsonInput();

        if (empty($data)) {
            $response->jsonError('Dados nao fornecidos', 400);

            return;
        }

        if (isset($data['role']) && $data['role'] === 'superadmin' && (string) (Session::user()['role'] ?? '') !== 'superadmin') {
            $response->jsonError('Perfil invalido', 422);

            return;
        }

        $user = TenantAwareDatabase::fetch(
            'SELECT id FROM users WHERE id = :id AND deleted_at IS NULL AND tenant_id = :tenant_id',
            TenantAwareDatabase::mergeTenantParams([':id' => $id])
        );

        if (!$user) {
            $response->jsonError('Usuario nao encontrado', 404);

            return;
        }

        $updateData = [];

        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }

        if (isset($data['email'])) {
            $existing = TenantAwareDatabase::fetch(
                'SELECT id FROM users WHERE email = :email AND id != :id AND deleted_at IS NULL AND tenant_id = :tenant_id',
                TenantAwareDatabase::mergeTenantParams([':email' => $data['email'], ':id' => $id])
            );

            if ($existing) {
                $response->jsonError('Este email ja esta em uso', 422);

                return;
            }

            $updateData['email'] = $data['email'];
        }

        if (isset($data['role'])) {
            $updateData['role'] = $data['role'];
        }

        if (isset($data['phone'])) {
            $updateData['phone'] = $data['phone'];
        }

        if (isset($data['status'])) {
            $updateData['status'] = $data['status'];
        }

        if (isset($data['locale']) && in_array($data['locale'], ['en', 'es', 'pt'], true)) {
            $updateData['locale'] = $data['locale'];
        }

        if (!empty($data['password'])) {
            if (strlen($data['password']) < 8) {
                $response->jsonError('Senha muito curta (min 8)', 422);

                return;
            }
            $updateData['password_hash'] = password_hash($data['password'], PASSWORD_BCRYPT);
        }

        if (empty($updateData)) {
            $response->jsonError('Nenhum dado para atualizar', 400);

            return;
        }

        try {
            TenantAwareDatabase::update('users', $updateData, 'id = :id', [':id' => $id]);

            $user = TenantAwareDatabase::fetch(
                'SELECT id, name, email, role, status, phone, locale, created_at FROM users WHERE id = :id AND tenant_id = :tenant_id',
                TenantAwareDatabase::mergeTenantParams([':id' => $id])
            );

            App::log("Usuario atualizado: {$user['email']}");

            $response->jsonSuccess(['user' => $user], 'Usuario atualizado com sucesso');
        } catch (\Exception $e) {
            App::logError('Erro ao atualizar usuario', $e);
            $response->jsonError('Erro ao atualizar usuario', 500);
        }
    }

    public function apiDelete(Request $request, Response $response): void
    {
        $id = $request->getParam('id');

        if (!$id) {
            $response->jsonError('ID nao fornecido', 400);

            return;
        }

        $user = TenantAwareDatabase::fetch(
            'SELECT id, email FROM users WHERE id = :id AND deleted_at IS NULL AND tenant_id = :tenant_id',
            TenantAwareDatabase::mergeTenantParams([':id' => $id])
        );

        if (!$user) {
            $response->jsonError('Usuario nao encontrado', 404);

            return;
        }

        try {
            TenantAwareDatabase::update('users', ['deleted_at' => date('Y-m-d H:i:s')], 'id = :id', [':id' => $id]);

            App::log("Usuario excluido: {$user['email']}");

            $response->jsonSuccess([], 'Usuario excluido com sucesso');
        } catch (\Exception $e) {
            App::logError('Erro ao excluir usuario', $e);
            $response->jsonError('Erro ao excluir usuario', 500);
        }
    }
}
