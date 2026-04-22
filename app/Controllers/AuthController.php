<?php

namespace App\Controllers;

use App\Core\App;
use App\Core\Lang;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Database;
use App\Core\TenantContext;
use App\Services\Security\LoginRateLimiter;

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
        Lang::initFromRequest();
        try {
            $data = $request->validate([
                'email' => 'required|email',
                'password' => 'required|min:1',
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

        $ip = $request->getIp();
        if (LoginRateLimiter::isTooManyAttempts($ip, $data['email'])) {
            if ($request->isJson()) {
                $response->jsonError(Lang::get('auth.rate_limited'), 429);
            } else {
                $response->with('error', Lang::get('auth.rate_limited'))->withInput()->back();
            }

            return;
        }

        $user = Database::fetch(
            "SELECT u.* FROM users u
             LEFT JOIN tenants t ON t.id = u.tenant_id
             WHERE u.email = :email AND u.status = 'active' AND u.deleted_at IS NULL
               AND (u.role = 'superadmin' OR t.status IN ('active','trial'))",
            [':email' => $data['email']]
        );

        if (!$user || !password_verify($data['password'], $user['password_hash'])) {
            LoginRateLimiter::recordFailure($ip, $data['email']);
            if ($request->isJson()) {
                $response->jsonError(Lang::get('auth.invalid_credentials'), 401);
            } else {
                $response->with('error', Lang::get('auth.invalid_credentials'))
                    ->withInput()
                    ->back();
            }

            return;
        }

        LoginRateLimiter::recordSuccess($ip, $data['email']);
        unset($user['password_hash']);

        if (empty($user['locale']) || !in_array($user['locale'], ['en', 'es', 'pt'], true)) {
            $user['locale'] = 'es';
        }

        TenantContext::clear();
        Session::remove('_tenant_cache');
        Session::remove('impersonate_tenant_id');

        Session::set('user', $user);
        session_regenerate_id(true);
        Lang::setLocale((string) $user['locale']);

        App::log("Login realizado: user_id={$user['id']} email={$user['email']}");

        if ($request->isJson()) {
            $response->jsonSuccess(['user' => $user], 'OK');
        } else {
            $response->with('success', Lang::get('auth.welcome', ['name' => $user['name']]))
                ->redirect('/kanban');
        }
    }

    public function logout(Request $request, Response $response): void
    {
        Lang::initFromRequest();
        $user = Session::user();

        if ($user) {
            App::log("Logout realizado: user_id={$user['id']}");
        }

        Session::logout();

        if ($request->isJson()) {
            $response->jsonSuccess([], 'OK');
        } else {
            $response->with('success', Lang::get('auth.logout_done'))
                ->redirect('/login');
        }
    }
}
