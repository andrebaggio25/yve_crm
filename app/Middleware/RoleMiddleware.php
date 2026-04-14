<?php

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

class RoleMiddleware
{
    private string $requiredRole;

    public function __construct(string $role = 'admin')
    {
        $this->requiredRole = $role;
    }

    public function handle(Request $request, Response $response): bool
    {
        Session::start();
        
        if (!Session::isAuthenticated()) {
            if ($request->isJson()) {
                $response->jsonError('Nao autorizado', 401);
            } else {
                $response->redirect('/login');
            }
            return false;
        }
        
        $user = Session::user();
        
        if ($user['role'] === 'admin') {
            return true;
        }
        
        if ($user['role'] !== $this->requiredRole) {
            if ($request->isJson()) {
                $response->jsonError('Acesso negado. Permissao insuficiente.', 403);
            } else {
                $response->with('error', 'Voce nao tem permissao para acessar esta pagina.')
                         ->redirect('/kanban');
            }
            return false;
        }
        
        return true;
    }
}
