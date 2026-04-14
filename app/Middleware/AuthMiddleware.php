<?php

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

class AuthMiddleware
{
    public function handle(Request $request, Response $response): bool
    {
        Session::start();
        
        $apiLike = str_starts_with($request->getPath(), 'api/');
        $wantsJson = $request->isJson() || $apiLike;

        if (Session::isExpired()) {
            Session::destroy();

            if ($wantsJson) {
                $response->jsonError('Sessao expirada. Faca login novamente.', 401);
            } else {
                $response->with('error', 'Sessao expirada. Faca login novamente.')
                    ->redirect('/login');
            }

            return false;
        }

        if (!Session::isAuthenticated()) {
            if ($wantsJson) {
                $response->jsonError('Nao autorizado. Faca login.', 401);
            } else {
                $response->redirect('/login');
            }

            return false;
        }
        
        Session::regenerate();
        
        return true;
    }
}
