<?php

use App\Core\Env;

$env = Env::get('APP_ENV', 'development');
$allowMigrationsDefault = $env !== 'production';
$allowMigrationsWeb = Env::get('ALLOW_MIGRATIONS_WEB') === null
    ? $allowMigrationsDefault
    : Env::bool('ALLOW_MIGRATIONS_WEB', false);

return [
    'name' => Env::get('APP_NAME', 'Yve CRM'),
    'env' => $env,
    'debug' => Env::bool('APP_DEBUG', true),
    'url' => Env::get('APP_URL', 'http://localhost/yve_crm'),
    'timezone' => Env::get('APP_TIMEZONE', 'Europe/Madrid'),
    'locale' => Env::get('APP_LOCALE', 'pt_BR'),
    'session_lifetime' => (int) Env::get('SESSION_LIFETIME_MINUTES', '120'),
    'csrf_token_name' => Env::get('CSRF_TOKEN_NAME', 'csrf_token'),
    'upload_max_size' => (int) Env::get('UPLOAD_MAX_BYTES', (string) (10 * 1024 * 1024)),
    'allowed_upload_types' => (static function () {
        $raw = Env::get('ALLOWED_UPLOAD_TYPES', 'text/csv,application/vnd.ms-excel');
        $types = array_values(array_filter(array_map('trim', explode(',', (string) $raw))));
        return $types ?: ['text/csv', 'application/vnd.ms-excel'];
    })(),
    /** Em producao, false por padrao (defina ALLOW_MIGRATIONS_WEB=true se necessario). */
    'allow_migrations_web' => $allowMigrationsWeb,
];
