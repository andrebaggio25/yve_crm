<?php

declare(strict_types=1);

/**
 * Executa migrations e/ou seeds via CLI (deploy, SSH, cron).
 *
 * Uso:
 *   php scripts/migrate.php # migrations pendentes + todos os seeds
 *   php scripts/migrate.php --migrations-only
 *   php scripts/migrate.php --seeds-only
 *
 * Requer .env na raiz do projeto (DB_*). Codigo de saida: 0 ok, 1 erro.
 */

$basePath = dirname(__DIR__);

require_once $basePath . '/app/Core/Env.php';
\App\Core\Env::load($basePath);

require_once $basePath . '/app/Core/Database.php';
require_once $basePath . '/app/Core/App.php';
require_once $basePath . '/app/Core/Migration.php';

use App\Core\App;
use App\Core\Migration;

$args = array_slice($argv, 1);
$migrationsOnly = in_array('--migrations-only', $args, true);
$seedsOnly = in_array('--seeds-only', $args, true);

if ($migrationsOnly && $seedsOnly) {
    fwrite(STDERR, "Use apenas uma opcao: --migrations-only ou --seeds-only.\n");
    exit(1);
}

$failed = false;

try {
    if (!$seedsOnly) {
        $result = Migration::runAll();
        echo $result['message'] . "\n";
        if (!empty($result['executed'])) {
            foreach ($result['executed'] as $name) {
                echo "  - {$name}\n";
            }
        }
        if (!empty($result['errors'])) {
            $failed = true;
            foreach ($result['errors'] as $err) {
                fwrite(STDERR, "Erro migration {$err['migration']}: {$err['error']}\n");
            }
        }
    }

    if (!$migrationsOnly && !$failed) {
        $seedResult = Migration::runSeeds();
        echo $seedResult['message'] . "\n";
        if (!empty($seedResult['executed'])) {
            foreach ($seedResult['executed'] as $item) {
                $msg = $item['result']['message'] ?? 'OK';
                echo "  - {$item['seed']}: {$msg}\n";
            }
        }
        if (!empty($seedResult['errors'])) {
            $failed = true;
            foreach ($seedResult['errors'] as $err) {
                fwrite(STDERR, "Erro seed {$err['seed']}: {$err['error']}\n");
            }
        }
    }
} catch (\Throwable $e) {
    App::logError('migrate.php', $e);
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

exit($failed ? 1 : 0);
