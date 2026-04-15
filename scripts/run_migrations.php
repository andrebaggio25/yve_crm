#!/usr/bin/env php
<?php
/**
 * Script CLI: executa todas as migrations pendentes
 * Uso: php scripts/run_migrations.php
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

$root = dirname(__DIR__);

// Carregar variáveis de ambiente
if (file_exists($root . '/.env')) {
    $lines = file($root . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($key, $value) = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
        putenv(trim($key) . '=' . trim($value));
    }
}

// Carregar autoload
if (is_file($root . '/vendor/autoload.php')) {
    require_once $root . '/vendor/autoload.php';
}

// Autoloader simples
spl_autoload_register(function ($class) use ($root) {
    $prefix = 'App\\';
    $base_dir = $root . '/app/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

echo "=== Yve CRM - Executar Migrations ===\n\n";

try {
    // Inicializar migration
    \App\Core\Migration::init();
    
    // Verificar status atual
    $count = \App\Core\Migration::getCount();
    echo "Migrations executadas: {$count['executed']}\n";
    echo "Migrations pendentes: {$count['pending']}\n";
    echo "Total disponíveis: {$count['available']}\n\n";
    
    if ($count['pending'] === 0) {
        echo "✅ Nenhuma migration pendente!\n";
        exit(0);
    }
    
    // Executar todas as migrations pendentes
    echo "Executando migrations...\n";
    $result = \App\Core\Migration::runAll();
    
    echo "\n=== Resultado ===\n";
    echo "Batch: {$result['batch']}\n";
    echo "Executadas: " . count($result['executed']) . "\n";
    
    foreach ($result['executed'] as $migration) {
        echo "  ✅ {$migration}\n";
    }
    
    if (!empty($result['errors'])) {
        echo "\n❌ Erros:\n";
        foreach ($result['errors'] as $error) {
            echo "  - {$error['migration']}: {$error['error']}\n";
        }
        exit(1);
    }
    
    echo "\n✅ Migrations concluídas com sucesso!\n";
    
    // Perguntar se quer executar seeds também
    echo "\nDeseja executar os seeds? (s/n): ";
    $handle = fopen('php://stdin', 'r');
    $line = fgets($handle);
    fclose($handle);
    
    if (trim(strtolower($line)) === 's') {
        echo "\nExecutando seeds...\n";
        $seeds = \App\Core\Migration::runSeeds();
        echo "Seeds executados: " . count($seeds['executed']) . "\n";
        foreach ($seeds['executed'] as $seed) {
            echo "  ✅ {$seed['seed']}\n";
        }
    }
    
    exit(0);
    
} catch (\Exception $e) {
    echo "\n❌ Erro: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
