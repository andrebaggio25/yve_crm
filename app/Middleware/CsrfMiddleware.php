<?php

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

class CsrfMiddleware
{
    public function handle(Request $request, Response $response): bool
    {
        Session::start();
        
        if ($request->getMethod() === 'GET') {
            return true;
        }
        
        $config = require __DIR__ . '/../../config/app.php';
        $tokenName = $config['csrf_token_name'];

        $token = $request->input($tokenName);
        if (!$token || $token === '') {
            $token = self::csrfTokenFromHeaders();
        }

        $wantsJson = $request->isJson() || str_starts_with($request->getPath(), 'api/');

        if (!$token) {
            if ($wantsJson) {
                $response->jsonError('Token CSRF nao fornecido', 403);
            } else {
                $response->with('error', 'Token de seguranca invalido. Tente novamente.')
                    ->back();
            }

            return false;
        }

        if (!Session::validateCsrf($token)) {
            if ($wantsJson) {
                $response->jsonError('Token CSRF invalido', 403);
            } else {
                $response->with('error', 'Token de seguranca expirado. Tente novamente.')
                    ->back();
            }

            return false;
        }
        
        return true;
    }

    private static function csrfTokenFromHeaders(): ?string
    {
        if (!function_exists('getallheaders')) {
            return null;
        }
        $headers = getallheaders();
        if (!is_array($headers)) {
            return null;
        }
        foreach ($headers as $name => $value) {
            if (strtolower((string) $name) === 'x-csrf-token') {
                return is_string($value) && $value !== '' ? $value : null;
            }
        }

        return null;
    }
}
