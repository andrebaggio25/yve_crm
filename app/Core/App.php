<?php

namespace App\Core;

class App
{
    private static ?Router $router = null;
    private static ?Request $request = null;
    private static ?Response $response = null;

    public static function init(): void
    {
        error_reporting(E_ALL);
        ini_set('display_errors', '1');
        
        $config = require __DIR__ . '/../../config/app.php';
        
        if (!$config['debug']) {
            error_reporting(0);
            ini_set('display_errors', '0');
        }
        
        date_default_timezone_set($config['timezone']);
        
        Session::start();
        
        self::$router = new Router();
        self::$request = new Request();
        self::$response = new Response();
        
        self::loadRoutes();
    }

    public static function run(): void
    {
        if (self::$router === null) {
            self::init();
        }
        
        try {
            self::$router->dispatch(self::$request, self::$response);
        } catch (\Exception $e) {
            App::logError('Application error', $e);

            $debug = self::config('debug', false);

            if (self::$request->isJson()) {
                $msg = $debug ? $e->getMessage() : 'Erro interno do servidor';
                self::$response->jsonError($msg, 500);
            } else {
                self::$response->setStatusCode(500);
                if ($debug) {
                    self::$response->view('errors.500', ['error' => $e->getMessage()], null);
                } else {
                    self::$response->view('errors.500', [], null);
                }
            }
        }
    }

    private static function loadRoutes(): void
    {
        $routes = require __DIR__ . '/../../config/routes.php';
        $routes(self::$router);
    }

    public static function getRouter(): Router
    {
        if (self::$router === null) {
            self::init();
        }
        return self::$router;
    }

    public static function getRequest(): Request
    {
        if (self::$request === null) {
            self::init();
        }
        return self::$request;
    }

    public static function getResponse(): Response
    {
        if (self::$response === null) {
            self::init();
        }
        return self::$response;
    }

    public static function config(string $key, mixed $default = null): mixed
    {
        $config = require __DIR__ . '/../../config/app.php';
        return $config[$key] ?? $default;
    }

    public static function basePath(): string
    {
        return dirname(__DIR__, 2);
    }

    public static function storagePath(string $path = ''): string
    {
        return self::basePath() . '/storage' . ($path ? '/' . $path : '');
    }

    public static function publicPath(string $path = ''): string
    {
        return self::basePath() . '/public' . ($path ? '/' . $path : '');
    }

    public static function databasePath(string $path = ''): string
    {
        return self::basePath() . '/database' . ($path ? '/' . $path : '');
    }

    public static function log(string $message, string $level = 'info'): void
    {
        $logFile = self::storagePath('logs/' . date('Y-m-d') . '.log');
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
        
        $dir = dirname($logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    public static function logError(string $message, ?\Throwable $e = null): void
    {
        $logMessage = $message;
        
        if ($e !== null) {
            $logMessage .= " | Error: " . $e->getMessage();
            $logMessage .= " | File: " . $e->getFile() . ":" . $e->getLine();
        }
        
        self::log($logMessage, 'error');
    }
}
