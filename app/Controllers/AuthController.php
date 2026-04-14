<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Database;
use App\Core\App;

class AuthController
{
    public function showLogin(Request $request, Response $response): void
    {
        if (Session::isAuthenticated()) {
            $response->redirect('/kanban');
            return;
        }
        
        $response->view('auth.login', [], null);
    }

    public function login(Request $request, Response $response): void
    {
        try {
            $data = $request->validate([
                'email' => 'required|email',
                'password' => 'required|min:3'
            ]);
        } catch (\InvalidArgumentException $e) {
            $errors = json_decode($e->getMessage(), true);
            
            if ($request->isJson()) {
                $response->jsonError('Dados invalidos', 422, $errors);
            } else {
                $response->withErrors($errors)
                         ->withInput()
                         ->back();
            }
            return;
        }
        
        $user = Database::fetch(
            "SELECT * FROM users WHERE email = :email AND status = 'active' AND deleted_at IS NULL",
            [':email' => $data['email']]
        );
        
        if (!$user || !password_verify($data['password'], $user['password_hash'])) {
            if ($request->isJson()) {
                $response->jsonError('Email ou senha incorretos', 401);
            } else {
                $response->with('error', 'Email ou senha incorretos.')
                         ->withInput()
                         ->back();
            }
            return;
        }
        
        unset($user['password_hash']);
        
        Session::set('user', $user);
        Session::regenerate();

        App::log("Login realizado: user_id={$user['id']} email={$user['email']}");

        if ($request->isJson()) {
            $response->jsonSuccess(['user' => $user], 'Login realizado com sucesso');
        } else {
            $response->with('success', 'Bem-vindo, ' . $user['name'] . '!')
                     ->redirect('/kanban');
        }
    }

    public function logout(Request $request, Response $response): void
    {
        $user = Session::user();

        if ($user) {
            App::log("Logout realizado: user_id={$user['id']}");
        }

        Session::logout();
        
        if ($request->isJson()) {
            $response->jsonSuccess([], 'Logout realizado com sucesso');
        } else {
            $response->with('success', 'Voce saiu do sistema.')
                     ->redirect('/login');
        }
    }
}
