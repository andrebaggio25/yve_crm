<?php

/**
 * Router para o servidor embutido do PHP (`php -S ... router.php`).
 * Serve arquivos estáticos existentes; demais pedidos vão para index.php.
 */
if (PHP_SAPI !== 'cli-server') {
    http_response_code(403);
    exit('Forbidden');
}

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$file = __DIR__ . $uri;
if ($uri !== '/' && is_file($file)) {
    return false;
}

require __DIR__ . '/index.php';
