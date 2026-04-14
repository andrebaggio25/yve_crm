<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Migration;
use App\Core\App;

class MigrationController
{
    private function assertWebMigrationsAllowed(Response $response): bool
    {
        $config = require App::basePath() . '/config/app.php';
        if (($config['env'] ?? '') === 'production' && empty($config['allow_migrations_web'])) {
            $response->jsonError('Operacao de migrations desabilitada em producao. Use pipeline/CLI ou defina ALLOW_MIGRATIONS_WEB.', 403);
            return false;
        }
        return true;
    }

    public function index(Request $request, Response $response): void
    {
        $status = Migration::status();
        $count = Migration::getCount();
        $hasPending = Migration::hasPending();
        $currentVersion = Migration::getCurrentVersion();
        
        $response->view('settings.migrations', [
            'migrations' => $status,
            'count' => $count,
            'hasPending' => $hasPending,
            'currentVersion' => $currentVersion
        ]);
    }

    public function apiStatus(Request $request, Response $response): void
    {
        try {
            $status = Migration::status();
            $count = Migration::getCount();
            $hasPending = Migration::hasPending();
            $currentVersion = Migration::getCurrentVersion();
            
            $response->jsonSuccess([
                'migrations' => $status,
                'count' => $count,
                'hasPending' => $hasPending,
                'currentVersion' => $currentVersion
            ]);
        } catch (\Exception $e) {
            $response->jsonError($e->getMessage(), 500);
        }
    }

    public function apiRun(Request $request, Response $response): void
    {
        if (!$this->assertWebMigrationsAllowed($response)) {
            return;
        }
        try {
            $result = Migration::runAll();
            
            App::log('Migrations executadas: ' . json_encode($result['executed']));
            
            $response->jsonSuccess($result, 'Migrations executadas com sucesso');
        } catch (\Exception $e) {
            App::logError('Erro ao executar migrations', $e);
            $response->jsonError($e->getMessage(), 500);
        }
    }

    public function apiRollback(Request $request, Response $response): void
    {
        if (!$this->assertWebMigrationsAllowed($response)) {
            return;
        }
        try {
            $steps = $request->input('steps', 1);
            $result = Migration::rollback($steps);
            
            App::log('Migrations revertidas: ' . json_encode($result['rolledBack']));
            
            $response->jsonSuccess($result, 'Migrations revertidas com sucesso');
        } catch (\Exception $e) {
            App::logError('Erro ao reverter migrations', $e);
            $response->jsonError($e->getMessage(), 500);
        }
    }

    public function apiSeed(Request $request, Response $response): void
    {
        if (!$this->assertWebMigrationsAllowed($response)) {
            return;
        }
        try {
            $result = Migration::runSeeds();
            
            App::log('Seeds executados: ' . json_encode($result['executed']));
            
            $response->jsonSuccess($result, 'Seeds executados com sucesso');
        } catch (\Exception $e) {
            App::logError('Erro ao executar seeds', $e);
            $response->jsonError($e->getMessage(), 500);
        }
    }

    public function apiReset(Request $request, Response $response): void
    {
        $config = require App::basePath() . '/config/app.php';
        
        if ($config['env'] !== 'development') {
            $response->jsonError('Reset so permitido em ambiente de desenvolvimento', 403);
            return;
        }
        
        try {
            $result = Migration::resetAndSeed();
            
            App::log('Banco resetado e seeds executados');
            
            $response->jsonSuccess($result, 'Banco de dados resetado com sucesso');
        } catch (\Exception $e) {
            App::logError('Erro ao resetar banco', $e);
            $response->jsonError($e->getMessage(), 500);
        }
    }
}
