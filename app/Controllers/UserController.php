<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;
use App\Core\App;

class UserController
{
    public function index(Request $request, Response $response): void
    {
        $users = Database::fetchAll(
            "SELECT id, name, email, role, status, phone, created_at 
             FROM users 
             WHERE deleted_at IS NULL 
             ORDER BY created_at DESC"
        );

        $response->view('users.index', [
            'title' => 'Usuarios',
            'pageTitle' => 'Gerenciamento de Usuarios',
            'users' => $users
        ]);
    }

    public function apiList(Request $request, Response $response): void
    {
        try {
            $users = Database::fetchAll(
                "SELECT id, name, email, role, status, phone, created_at 
                 FROM users 
                 WHERE deleted_at IS NULL 
                 ORDER BY created_at DESC"
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
            $user = Database::fetch(
                "SELECT id, name, email, role, status, phone, created_at 
                 FROM users 
                 WHERE id = :id AND deleted_at IS NULL",
                [':id' => $id]
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
                'password' => 'required|min:6',
                'role' => 'required',
                'phone' => ''
            ]);
        } catch (\InvalidArgumentException $e) {
            $errors = json_decode($e->getMessage(), true);
            $response->jsonError('Dados invalidos', 422, $errors);
            return;
        }

        $existing = Database::fetch(
            "SELECT id FROM users WHERE email = :email AND deleted_at IS NULL",
            [':email' => $data['email']]
        );

        if ($existing) {
            $response->jsonError('Este email ja esta em uso', 422, ['email' => ['Email ja cadastrado']]);
            return;
        }

        try {
            $userId = Database::insert('users', [
                'name' => $data['name'],
                'email' => $data['email'],
                'password_hash' => password_hash($data['password'], PASSWORD_BCRYPT),
                'role' => $data['role'],
                'phone' => $data['phone'] ?? null,
                'status' => 'active'
            ]);

            $user = Database::fetch(
                "SELECT id, name, email, role, status, phone, created_at FROM users WHERE id = :id",
                [':id' => $userId]
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

        $user = Database::fetch(
            "SELECT id FROM users WHERE id = :id AND deleted_at IS NULL",
            [':id' => $id]
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
            $existing = Database::fetch(
                "SELECT id FROM users WHERE email = :email AND id != :id AND deleted_at IS NULL",
                [':email' => $data['email'], ':id' => $id]
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

        if (!empty($data['password'])) {
            $updateData['password_hash'] = password_hash($data['password'], PASSWORD_BCRYPT);
        }

        if (empty($updateData)) {
            $response->jsonError('Nenhum dado para atualizar', 400);
            return;
        }

        try {
            Database::update('users', $updateData, 'id = :id', [':id' => $id]);

            $user = Database::fetch(
                "SELECT id, name, email, role, status, phone, created_at FROM users WHERE id = :id",
                [':id' => $id]
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

        $user = Database::fetch(
            "SELECT id, email FROM users WHERE id = :id AND deleted_at IS NULL",
            [':id' => $id]
        );

        if (!$user) {
            $response->jsonError('Usuario nao encontrado', 404);
            return;
        }

        try {
            Database::update('users', ['deleted_at' => date('Y-m-d H:i:s')], 'id = :id', [':id' => $id]);

            App::log("Usuario excluido: {$user['email']}");

            $response->jsonSuccess([], 'Usuario excluido com sucesso');
        } catch (\Exception $e) {
            App::logError('Erro ao excluir usuario', $e);
            $response->jsonError('Erro ao excluir usuario', 500);
        }
    }
}
